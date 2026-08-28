# F10 — Certificación de Producción (Read-Only, exigencia máxima)

> **Tipo**: Certificación de go-live · **Modo**: solo lectura (sin implementación)
> **Fecha**: 2026-08-06
> **Método**: 5 subagentes en paralelo (seguridad, multi-tenant, validaciones, testing,
> calidad+arquitectura, DevOps, cumplimiento ARCA) + verificación manual de los hallazgos
> críticos contra el código real y contra los manuales oficiales de `docs/arca/`.
> **Criterio**: extremadamente estricto. Cada hallazgo con evidencia `archivo:línea`.
> Si algo no está demostrado, se declara "no demostrado".

---

## 1. Veredicto Ejecutivo

# 🔴 NO. NO pondría este módulo en producción para facturar comprobantes reales hoy.

Motivos de fondo, verificados uno por uno:

1. **Hay 3 bugs de cumplimiento ARCA** que pueden rechazar comprobantes reales o quemar
   números/CAE: TRA con `<generatedAt>` en vez de `<generationTime>`, `Resultado 'O'` tratado
   como rechazo (CAE huérfano + hueco de numeración), y la regla RG 4291/4444 de Factura B
   > $10.000 sin identificación no implementada.
2. **La trazabilidad es incompleta**: reimpresión/PDF/QR reconstruyen el emisor y el receptor
   desde datos ACTUALES, no del momento de emisión.
3. **El stack de despliegue es de desarrollo**: sin worker de cola, sin scheduler, sin TLS,
   sin backups, con `APP_DEBUG=true`.
4. **Hay contaminación multi-tenant real** en el encabezado de tickets/emails/PDFs fiscales
   (tabla global `configuraciones`).

La base (dominio puro, ledger, invariantes, 238 tests) es sólida y **la mejor parte del
sistema**. Pero "sólido en el dominio" no equivale a "listo para producción".

---

## 2. Scorecard por Área (0-100, criterio estricto)

| Área | % | Justificación |
|---|---|---|
| **Arquitectura** | 70 | Hexagonal con dominio 100% puro (verificado: cero Laravel/Facades/Eloquent en `Domain/`). Penaliza: `Application/` acoplada a Eloquent/Infrastructure, `ComprobanteFiscalAdapter` muerto, `Wsfet` sin binding, `VentaOperacionFiscalService` de 355 líneas. |
| **Backend** | 63 | Código limpio, DI por constructor, transacciones correctas. Penaliza: sin logging estructurado, sin retries, handler de excepciones vacío, mensajes SOAP crudos, `CuitInvalidoException` no capturada (500). |
| **Frontend** | 45 | Wizard completo (5 pasos ARCA) pero sin navegación en el menú, sin loaders, resets post-guardado, flash volátil. |
| **UX** | 40 | El cajero ve errores SOAP internos en el Swal del POS; monotributista y motivos de bloqueo sin explicar; onboarding con fricción. |
| **Seguridad** | 70 | Muy bueno en el núcleo (pfx cifrado con APP_KEY, sin rutas de descarga, IDOR bloqueado en 2 capas, `.env` sin trackear). Penaliza: `$fillable` con secretos, `logAll()` volcando secretos a `activity_log`, sin Policies, `trustProxies('*')`. |
| **Multi-tenant** | 58 | El dominio filtra `comercio_id` correctamente. Pero `configuraciones` es global y el módulo la lee/escribe (ticket, email, job) → contaminación de lectura Y escritura entre tenants. |
| **DevOps** | 22 | Sin worker de cola, sin scheduler, sin TLS funcional, sin backups, `.env.example` incompleto, imagen dev en prod. |
| **Validaciones** | 75 | CUIT con DV excelente en backend. Pero el pegado con guiones falla en el wizard, PV forjado se persiste, pfx sin límites, criterios divergentes de teléfono/nombre entre módulos. |
| **Facturación (cumplimiento ARCA)** | 55 | Request WSFEv1/QR/numeración/NC bien armados. Penaliza: `generatedAt`, `Resultado 'O'`, RG 4444 ausente, sin reintentos, `CaePerdidoHandler` muerto, sin cache de padrón. |
| **Producción** | 18 | Bloqueado por TODO lo anterior en conjunto. |

**Preparación global estimada: ~50/100. Facturación legal en producción: NO.**

---

## 3. Hallazgos 🔴 BLOQUEANTES (13)

### B1. TRA de WSAA con elemento `generatedAt` en vez de `generationTime`
- **Evidencia**: `app/Facturacion/Infrastructure/Arca/Wsaa/WsaaClient.php:121` emite `<generatedAt>{...}</generatedAt>`. Manual oficial `docs/arca/WSAAmanualDev.pdf` §5.1 (extracción L212): `<generationTime>2019-09-26T10:09:20</generationTime>` — el manual valida el XML contra schema (§10.10 "No se ha podido interpretar el XML contra el schema").
- **Explicación**: el elemento del TRA tiene un nombre que difiere del manual en todos los ejemplos y el ejemplo PHP oficial.
- **Riesgo**: rechazo de autenticación WSAA en ARCA (cada venta que intente emitir falla). No está demostrado que ARCA acepte `generatedAt`.
- **Solución**: renombrar a `generationTime`. Considerar el margen oficial de `now − 60s` (§5.1 ejemplo PHP).
- **Tests que deberían existir**: aserción del XML del TRA emitido por `mensajeLoginTicket()` (hoy el test solo verifica que no lanza / el token).

### B2. `Resultado 'O'` (aprobado con observaciones) tratado como rechazo
- **Evidencia**: `app/Facturacion/Infrastructure/Arca/Wsfe/CaeMapper.php:20` `if ($resultado !== 'A') { return null; }`. Manual `docs/arca/wsfev1-RG-4291 - Para wsfev1 - R.G. N° 4.291.pdf` (L~90): "No supera alguna de las validaciones no excluyentes, el comprobante es **aprobado con observaciones, se le asigna el CAE con la fecha de vencimiento**".
- **Riesgo**: ARCA otorga CAE con `Resultado 'O'`; el mapper lo descarta, la venta se revierte, el número se quema y el CAE queda huérfano → hueco de numeración + comprobante emitido en ARCA que el comercio nunca registra. Inconsistencia fiscal grave.
- **Solución**: aceptar 'A' y 'O' como aprobación (persistir CAE); diferenciar 'R' como rechazo.
- **Tests**: `CaeMapperTest` cubre 'A' y 'R' pero no 'O' (verificado). Agregar caso 'O' con CAE y Obs[].

### B3. Regla RG 4291/4444: Factura B > $10.000 sin identificación del comprador
- **Evidencia**: `app/Facturacion/Infrastructure/Arca/Wsfe/FECAERequestBuilder.php:214-224` siempre envía `DocTipo=96, DocNro=0` para consumidor final sin CUIT. El manual COMPG/RG 4291 (validaciones 10014/10015) exige identificar al comprador (DocTipo 80/96 + DocNro válido) para comprobantes B que superen el umbral (monto = ImpTotal × cotización). El POS permite vender cualquier monto sin identificación (`Pos/Terminal.vue`, sin bloqueo por monto).
- **Riesgo**: rechazo 10015/10014 para Factura B > $10.000 emitidas a consumidor final.
- **Solución**: implementar el umbral RG 4444 en el flujo (bloquear o exigir identificación en POS/backend según monto).
- **Tests**: ninguno contempla el umbral (grep `4444|10000` en tests sin matches relevantes).

### B4. Emisor y receptor reconstruidos desde datos ACTUALES (sin snapshot) en reimpresión
- **Evidencia**: `app/Facturacion/Infrastructure/Persistence/EloquentComprobanteFiscalRepository.php:160` `emisor: $this->emisorDesdeConfig(...)` → `:190-205` lee `ConfiguracionFiscalComercio` actual. `receptorDesdeVenta()` `:207-219` lee el consumidor actual. El QR sí está snapshot (`:171-173`), el emisor no.
- **Riesgo**: si cambia CUIT/razón social/condición fiscal después de emitir, reimpresiones y PDFs históricos muestran datos divergentes del CAE/QR original.
- **Solución**: persistir snapshot de emisor (y receptor) en el ledger al emitir; usarlo en `reconstruir()`.
- **Tests**: inexistente. Escribir: emitir → cambiar datos → reimprimir debe mostrar datos del momento de emisión.

### B5. `CaePerdidoHandler` no cableado (código muerto de producción)
- **Evidencia**: `app/Facturacion/Domain/Services/CaePerdidoHandler.php:19`. Única referencia en producción: docblock `EmisionService.php:23`. Única instanciación: `tests/Unit/FacturacionDomain/CaePerdidoHandlerTest.php:41`. Sin binding en `AppServiceProvider.php:132-175`, sin ruta/controller.
- **Riesgo**: un CAE emitido pero no persistido (timeout tras otorgamiento) no tiene recuperación operativa. Ya documentado como deuda (F10-auditoria H1.2/H4.2, F10-final C2).
- **Solución**: cablear a una acción ("recuperar CAE") o deshabilitarlo explícitamente.

### B6. Tabla `configuraciones` GLOBAL sin `comercio_id`: contaminación de lectura y escritura entre tenants
- **Evidencia**: migración `2026_04_17_175459_create_configuracions_table.php:12-19` (clave única, sin `comercio_id`). Escritura: `app/Http/Controllers/ConfiguracionController.php:128-155` (whitelist global: `cuit`, `nombre_empresa`, `ticket_digital_auto_email`, `formato_impresion`, etc.). Lectura en el módulo: `app/Jobs/EnviarTicketDigital.php:44-45` (`Configuracion::pluck`), `app/Services/Ticket/TicketBuilder.php:18-30`.
- **Riesgo**: el último comercio que guarda pisa la config de todos. Si un comercio activa `ticket_digital_auto_email`, se mandan mails de clientes de TODOS los comercios. Encabezado de tickets/emails/PDFs muestra nombre/CUIT/domicilio de otro tenant.
- **Solución**: mover `cuit`, `nombre_empresa`, `ticket_digital_auto_email`, `formato_impresion`, `ticket_mensaje_pie` a datos per-comercio (ver sección 6).

### B7. Ticket/email/PDF fiscal con identidad mezclada entre tenants
- **Evidencia**: `app/Services/Ticket/TicketBuilder.php:18-30` (pluck global) + `resources/views/facturacion/a4.blade.php:44-48` (nombre_empresa GLOBAL L44 junto a CUIT per-comercio L45) + `app/Mail/TicketVenta.php:26,51,74-75`.
- **Riesgo**: comprobante fiscal legal con encabezado de otro comercio → riesgo de rechazo/observación ARCA y fuga de datos entre tenants.
- **Solución**: resolver identidad por `$venta->...->comercio_id`, nunca por pluck global.

### B8. Sin worker de cola ni scheduler en el stack de despliegue
- **Evidencia**: `docker/8.5/supervisord.conf:7-15` (solo `[program:php-fpm]`) y `:17-24` (solo `[program:nginx]`). Sin `queue:work` ni `schedule:work` en todo el repo (git grep). `app/Jobs/EnviarTicketDigital.php:17` (`ShouldQueue`) despachado en `app/Http/Controllers/VentaController.php:500,691`. `routes/console.php:15,18,21,24,27,30` define 6 tareas.
- **Riesgo**: los tickets digitales con PDF/CAE/QR jamás se envían; mora/lotes/promos/pedidos no corren; renovación de certificados no existe.
- **Solución**: `[program:queue-worker]` + `[program:scheduler]` en supervisord.conf.

### B9. Defaults de entorno ARCA a `produccion` + `.env.example` sin vars ARCA
- **Evidencia**: migraciones `2026_08_02_000004:14` y `2026_08_02_000005:18` `default('produccion')`; `WizardConfiguracionService.php:213-225` (`sinDatos()` con `'produccion'`). `.env.example` (35 líneas) sin ninguna var `ARCA_*` (vs `config/services.php:61-83`).
- **Riesgo**: comercio en onboarding apunta directo a ARCA real → CAE reales accidentales durante pruebas.
- **Solución**: default a `homologacion`; documentar vars ARCA en `.env.example`.

### B10. `APP_DEBUG=true`, `APP_ENV=local` para producción
- **Evidencia**: `.env:4` (`APP_DEBUG=true`), `.env:2` (`APP_ENV=local`), `.env:21` (`LOG_LEVEL=debug`). Con APP_DEBUG, cualquier excepción muestra stack traces y vars de entorno; el trace SOAP queda habilitado (`config/services.php:81`).
- **Riesgo**: fuga de credenciales (MP, Google, ARCA cifrado) en errores; overhead SOAP.
- **Solución**: prod con `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=info`, canal `daily`.

### B11. `trustProxies(at: '*')`
- **Evidencia**: `bootstrap/app.php:14`.
- **Riesgo**: la app confía en `X-Forwarded-*` de cualquier origen → spoofing de IP en rate limiting/auditoría y URLs/HTTPS mal resueltos.
- **Solución**: restringir a IPs del proxy/load balancer real.

### B12. Sin TLS funcional
- **Evidencia**: `compose.yaml:30` publica `443:443` para caddy, pero `docker/caddy/Caddyfile:1-3` escucha en `:80` sin dominio ni TLS; el 443 del contenedor no tiene listener. El nginx se publica directo en el 80 del host (`compose.yaml:12`).
- **Riesgo**: tráfico de facturación/credenciales en claro si se expone.
- **Solución**: Caddyfile con dominio + auto-HTTPS, o terminar TLS en el proxy del proveedor.

### B13. Sin mecanismo de backup (pese al claim comercial)
- **Evidencia**: git grep de `backup|pg_dump|spatie/laravel-backup` sin resultados; `composer.lock` sin `spatie/laravel-backup`. `README.md:35` promete "Backups automáticos diarios" — sin respaldo técnico. Solo volumen nombrado `sail-pgsql` (`compose.yaml:81-82`, mismo disco).
- **Riesgo**: pérdida de la BD = pérdida de trazabilidad fiscal (CAE, comprobantes, caja, fiados).
- **Solución**: `pg_dump` diario con retención + copia off-site; test de restauración.

---

## 4. Hallazgos 🟠 ALTOS (13)

### A1. CUIT del consumidor: 3 comportamientos distintos según dónde se cargue
- **Evidencia**: `Wizard.vue:226-228` (`maxlength="11"` trunca `20-12345678-9` a 10 dígitos → DV falla); `app/Http/Controllers/ConsumidorController.php:105` (sin max, acepta guiones, normaliza); `app/Http/Controllers/PosController.php:563` (`max:11`, rechaza guiones).
- **Riesgo**: el formato estándar AFIP (con guiones) falla en el wizard; el mismo CUIT pasa en un endpoint y falla en otro.
- **Solución**: normalizar y validar DV en el frontend del wizard (pegar con guiones) y unificar regla `cuit` entre ConsumidorController y PosController (usar la regla custom con DV sin `max:11`).

### A2. Punto de venta forjado se persiste sin validar contra ARCA
- **Evidencia**: `WizardConfiguracionFiscalController.php:131-133` (`punto_venta` `required|integer|min:1`, sin max/lista/bloqueado); `WizardConfiguracionService.php:161-169` persiste directo. `FEParamGetPtosVenta` se consulta solo para la UI (`WsfetClient.php:118-142`).
- **Riesgo**: POST `punto_venta=9999` se guarda; recién explota en emisión.
- **Solución**: validar server-side contra `FEParamGetPtosVenta` (existente + no bloqueado) y rango.

### A3. Subida de PFX sin límites
- **Evidencia**: `WizardConfiguracionFiscalController.php:96-99` (`['required','file']` sin mimes/max); `:101` `file_get_contents(...getRealPath())` a memoria.
- **Riesgo**: DoS por memoria; tabla inflada con archivos arbitrarios.
- **Solución**: `mimes:pfx,p12`, `max:4096` KB, chequear `getError() === UPLOAD_ERR_OK`.

### A4. Certificado vencido no se revalida al emitir
- **Evidencia**: vigencia validada solo al cargar (`CertificadoService.php:25-27`); `WsfetResolverPorComercio.php:34` (`materialPara()`) no revalida al emitir.
- **Riesgo**: cert vencido entre carga y emisión → fallo en WSAA como error genérico, venta revertida sin mensaje claro.
- **Solución**: revalidar vigencia antes de cada emisión con mensaje específico.

### A5. Sin reintentos SOAP: emisión puede bloquear 60s+ y fallar por timeout
- **Evidencia**: `SoapTransport.php:25-31` (un `__soapCall`), `WsfetClient.php:184-200`, `WsaaClient.php:66-75` sin retry. Timeout 30s (`config/services.php:78`). Emisión = WSAA + WSFE secuenciales.
- **Riesgo**: request HTTP del POS bloqueado >60s; timeout de proxy; doble clic; y ante timeout posterior al otorgamiento del CAE, estado incierto sin recovery (B5).
- **Solución**: reintentos con backoff para timeouts de red (nunca re-emitir si el CAE pudo otorgarse: idempotencia + `CaePerdidoHandler` cableado).

### A6. Sin Policies (directorio inexistente)
- **Evidencia**: `app/Policies/` no existe. `CertificadoFiscal`, `PendienteNc`, `App\Models\ComprobanteFiscal` sin policy. Autorización delegada a la capa de aplicación.
- **Riesgo**: cualquier futuro caller que omita el filtro de comercio rompe el multi-tenant sin barrera de framework.
- **Solución**: Policies con verificación de `comercio_id` + `Gate::before` para SuperAdmin/Admin Global.

### A7. Errores SOAP/ARCA crudos a UI y logs
- **Evidencia**: `SoapTransport.php:27-31`; `Terminal.vue:974-982` (Swal con texto técnico); `VentaController.php:489-493` (`Log::error($e->getMessage())` sin stack); `CaeMapper::errores()` `:53-70` pierde el `Code` oficial ARCA (solo `Msg`).
- **Riesgo**: fuga de detalle interno; el código de observación ARCA (clave para soporte) nunca llega al usuario.
- **Solución**: mapear errores ARCA a mensajes propios; loguear detalle técnico con stack en canal aparte; exponer `Code` ARCA.

### A8. `Application/` acoplada a Eloquent e Infrastructure (inversión de dependencia)
- **Evidencia**: `EmisionVentaService.php:30-31` (`use App\Models\Venta; use App\Models\Consumidor;`); `VentaOperacionFiscalService.php:11-18` (6 modelos + `DB::`); `NcService.php:29`; `WizardConfiguracionService.php:16-19` (importa Infrastructure); `DiagnosticoFiscalService.php:11-13` (modelos + Carbon).
- **Riesgo**: los casos de uso no son testeables sin BD; cambios de persistencia o errores ARCA tocan la capa de aplicación.
- **Solución**: extraer mapeadores Eloquent→dominio a Infrastructure; traducir excepciones de infraestructura en la frontera.

### A9. `Wsfet` sin binding en el contenedor
- **Evidencia**: `AppServiceProvider.php:132-175` no registra `Wsfet::class`. Se inyecta por tipo en `Domain/Services/EmisionService.php:33` y `CaePerdidoHandler.php:24`. Hoy no falla porque se construyen a mano (`EmisionVentaService.php:76`, `NcService.php:94`), pero resolverlos por auto-wiring lanzaría `BindingResolutionException`.
- **Riesgo**: latente; cualquier refactor a DI del contenedor rompe la emisión.
- **Solución**: registrar `Wsfet`, `EmisionService` y `CaePerdidoHandler` con sus dependencias.

### A10. Sin sincronización con la numeración autorizada por ARCA (`FECompUltimoAutorizado`)
- **Evidencia**: numeración local por `(comercio_id, punto_venta, tipo)` (`EloquentComprobanteFiscalRepository.php:131-136`, `lockForUpdate` ✅). Nunca se contrasta con `FECompUltimoAutorizado`.
- **Riesgo**: si el comercio emitió por otro canal, ARCA rechaza con "comp. no coincide con el próximo a autorizar" (código 10016 del manual COMPG).
- **Solución**: validar el próximo número contra ARCA antes de emitir (o documentar el supuesto de emisión exclusiva por VendAR).

### A11. Padrón sin cache: SOAP sincrónico en cada venta con CUIT
- **Evidencia**: `PadronClient.php:33-71` sin `Cache::remember`; consultado en `EmisionVentaService.php:144-145` y `NcService.php:148`.
- **Riesgo**: latencia/rate-limit en ARCA por venta.
- **Solución**: cachear por CUIT + entorno con TTL corto (p. ej. 24 h).

### A12. `VentaOperacionFiscalService` god-service (355 líneas) con lógica de dinero/stock fuera de repositorios
- **Evidencia**: `VentaOperacionFiscalService.php` `anular()` `:44-167` y `devolver()` `:177-324` mezclan `DB::table` (stock por lotes, producto_sucursal, pagos, caja, CC), Eloquent, NC y pendientes; ~200 líneas duplicadas entre anular/devolver. `anular()`/`devolver()` no validan internamente el comercio de la venta (defendido hoy solo en controllers `VentaController.php:710-718,744-752`).
- **Riesgo**: la operación más sensible (stock + caja + CC + NC) con lógica de bajo nivel y sin defensa propia.
- **Solución**: `StockReversionService`, `PagosReversionService`, validar `comercio_id` dentro del servicio.

### A13. README sin documentación de despliegue (runbook inexistente)
- **Evidencia**: `README.md` (58 líneas, 100% marketing) sin `sail`, `migrate`, worker, scheduler, ARCA, go-live. Única referencia operativa conceptual en `docs/build-plan-facturacion.md:449-451`.
- **Riesgo**: deploy por conocimiento tribal; errores de entorno ARCA probables.
- **Solución**: sección "Despliegue" con pasos reales y checklist de go-live.

---

## 5. Hallazgos 🟡 MEDIOS y 🟢 BAJOS (resumen)

### 🟡 Medios
| # | Hallazgo | Evidencia | Solución |
|---|---|---|---|
| M1 | `CertificadoFiscal` con `$fillable` incluyendo `archivo_pfx`/`password_pfx` y sin `$hidden` | `app/Models/CertificadoFiscal.php:14-23` | `$hidden` + sacar del `$fillable` |
| M2 | `Auditable::logAll()` vuelca secretos cifrados a `activity_log` | `app/Traits/Auditable.php:14-17` + `CertificadoFiscal.php:10` | `logOnly([...])` sin secretos |
| M3 | Logs del módulo sin `comercio_id` | `DiagnosticoFiscalController.php:48,65`; `WizardConfiguracionFiscalController.php:70,123,160,240`; `VentaController.php:491,700,730,764` | incluir `comercio_id` en contextos |
| M4 | `CuitInvalidoException` no capturada en `letraEsperada` → 500 con CUIT legacy | `EmisionVentaService.php:142` (fuera del try/catch :144-152); `PosController.php:617` solo captura `EmisionVentaException` | capturar y devolver 422 |
| M5 | Teléfono con 4 criterios (max:15/20/50+sin-regex) | `ConsumidorController:103`, `PosController:562`, `ConfiguracionController:133`, `SucursalController:61` | unificar |
| M6 | Nombre consumidor: max:50+sin números (Clientes) vs max:255+sin regex (POS) | `ConsumidorController:99,162` vs `PosController:560` | unificar |
| M7 | `LedgerComprobanteAsociadoResolver` usa `find()` sin `comercio_id` | `LedgerComprobanteAsociadoResolver.php:16` | pasar `comercioId` |
| M8 | "No NC sobre NC" solo operativo, no en dominio | `ReglasNotaCredito.php` (no hay regla) | regla explícita |
| M9 | Race de `firstOrCreate` en el primer número de una secuencia | `EloquentComprobanteFiscalRepository.php:132-136` | manejar unique conflict |
| M10 | QR `fecha` = momento de persistencia, puede divergir de `CbteFch` en medianoche | `EloquentComprobanteFiscalRepository.php:53` vs `FECAERequestBuilder.php:81` | usar `CbteFch` |
| M11 | `comercioId()` deriva `0` para rol global sin branch | `WizardConfiguracionFiscalController.php:181-186`, `DiagnosticoFiscalController.php:74-79` | 403 explícito |
| M12 | CAE vencido imprimible en reimpresión sin control | `QrArcaPayloadBuilder.php:32-36`, `TicketBuilder.php:123-124` | política (bloquear/advertir) |
| M13 | Mapas AFIP/condiciones hardcodeados (OCP) | `FECAERequestBuilder.php:22-40`, `CondicionFiscalMapper.php:17-25`, `DeterminacionLetraRule.php:29-33` | inyectar config (solo lo no-legal) |
| M14 | `EnviarTicketDigital` sin `$tries`/`$backoff` | `app/Jobs/EnviarTicketDigital.php` | definir retry policy |
| M15 | Sin cache de padrón (ver A11) | `PadronClient.php:33-71` | `Cache::remember` |
| M16 | Imagen dev en prod (xdebug/pcov, sin opcache) | `docker/8.5/Dockerfile:54,56` | split imagen prod |
| M17 | Logging `single` sin rotación, `LOG_LEVEL=debug` | `config/logging.php:21,61-66`, `.env:18-21` | `daily` + info |
| M18 | Sin Horizon; solo `failed_jobs` pasivo | `composer.json` (sin horizon) | monitoreo de cola |
| M19 | `.env.example` sin vars de infra (queue/redis/session/app_key) | `.env.example` vs `.env:38-45` | completar |

### 🟢 Bajos
- `tmpfile()` de FirmaCms sin `try/finally` (`FirmaCms.php:20-51`)
- `DB::raw('stock_actual + '.$cantidad)` con floats (`VentaOperacionFiscalService.php:67,86,227,249`) → usar `increment()`
- Flag `trace` SOAP muerto (`config/services.php:81`)
- `ext-soap`/`ext-openssl` no declarados en composer.json (`composer.json:8`)
- `REDIS_CLIENT=phpredis` sin garantía en prod (`.env:45`) — `WsaaClient.php:39` usa `Cache::lock`
- APP_KEY = clave de cifrado de certificados sin rotación documentada (`CertificadoEncryptor.php:13-21`)
- ComprobanteFiscalAdapter: contrato muerto (`Domain/Contracts/ComprobanteFiscalAdapter.php:10`)
- `buscarNotaCredito`/`listarPorComercio` sin llamadores de producción (`EloquentComprobanteFiscalRepository.php:106,120`)
- `conexionOk` computed muerto (`Wizard.vue:145-149`)
- `assertTrue(true)` en `HabilitadorHomologacionTest.php:19,43`
- Migraciones aditivas en `up()` (ok); backfill alícuota 21% en `2026_08_02_000001:19` con lock potencial

---

## 6. Datos Fiscales: ¿única fuente de verdad?

**No hay única fuente de verdad. Hoy conviven:**

| Dato | Fuente A | Fuente B | Riesgo |
|---|---|---|---|
| CUIT | `configuraciones.cuit` (global, sin DV, max:20) | `configuracion_fiscal_comercios.cuit` (per-comercio, con DV) | Ticket no fiscal imprime `config['cuit']` (`TicketBuilder.php:24`); ticket fiscal el del wizard. Pueden divergir sin aviso |
| Nombre/Razón social | `configuraciones.nombre_empresa` (global) | `configuracion_fiscal_comercios.razon_social` (per-comercio) | a4.blade mezcla global + per-comercio (B7) |
| Dirección | `configuraciones.direccion` (global) | (no hay campo fiscal de domicilio) | el PDF fiscal usa domicilio global |
| Teléfono | `configuraciones.telefono` (global) | — | sin scope |
| Punto de venta | `configuracion_fiscal_comercios.punto_venta` | — | única fuente ✅ |
| Certificado + password | `certificados_fiscales` (per-comercio) | — | única fuente ✅ |
| Entorno | `configuracion_fiscal_comercios.entorno` | — | única fuente ✅ |
| Condición IVA | `configuracion_fiscal_comercios.condicion_fiscal` | padrón (receptor) | emisor vs padrón son distintos ✅ |

**Recomendación** (detallada en `docs/informes/F10-exhaustiva.md` §5): una sola fuente para CUIT = `comercios.cuit` (nueva columna con DV); la config fiscal lo hereda y puede sobreescribirlo con warning explícito; el encabezado de tickets se resuelve por comercio, nunca por pluck global. Teléfono solo en el local; PV/certificado/entorno/condición IVA solo en la config fiscal.

---

## 7. Robustez y Multi-tenant: zona verificada CORRECTA (evidencia)

- **Ledger**: todas las queries del repositorio filtran `comercio_id` (`EloquentComprobanteFiscalRepository.php:82,95,108,122,132-136`); unique `(comercio_id, punto_venta, tipo, numero)` (`migración 2026_08_02_000006:30`).
- **Numeración atómica**: `ControlSecuenciaFiscal.php:31-41` con `lockForUpdate` + `DB::transaction`.
- **Certificados**: cifrados con `Crypt::encryptString` en BD (`CertificadoEncryptor.php:13-21`), nunca en filesystem; sin rutas de descarga; `materialPara()` filtra comercio+entorno (`CertificadoService.php:52-64`).
- **Resolvers per-comercio**: `WsfetResolverPorComercio.php:34`, `ConectividadResolverPorComercio.php:42`, `PadronResolverPorComercio`.
- **QR**: `QrArcaPayloadBuilder.php:44` usa el CUIT del emisor de la entidad (per-comercio).
- **Reimpresión/descargas**: `ComprobanteImpresionResolver.php:25` + `TicketController.php:16-18` (403/404 cross-comercio).
- **Cache WSAA**: clave con hash del pfx (`WsaaClient.php:35-36`), lock único (`:39`) — correcto.
- **Domain puro**: cero `Illuminate`/Facades/Eloquent en `app/Facturacion/Domain/**` (grep verificado).
- **Sin service location**: cero `app()`/`resolve()`/`make()` en `app/Facturacion/**`.
- **Tests**: 238 tests, E2E con asserts fuertes (CAE, QR, PDF, email — verificado línea por línea, no es smoke).

---

## 8. No demostrado (requiere verificación en homologación ARCA real)

1. Que ARCA acepte `<generatedAt>` (B1) — probabilidad baja; riesgo alto.
2. Formato wire del `CmpAsoc` de la NC (los tests asertan solo el array en memoria, `WsfetClientTest.php:147-149`).
3. Códigos de condición IVA 4 (exento) y 15 (no alcanzado) — el manual extraído solo confirma 1/5/6.
4. Contenido real del PDF legal (bloque fiscal, QR embebido) — el E2E solo asevera `content-type: application/pdf`.
5. Adjunto del email — el E2E solo asevera `Mail::assertSent` con `hasTo`, no el adjunto PDF (aunque `EnviarTicketDigital.php:54` usa `attachData`).
6. Payload del QR contra la spec RG 4597 (el PDF de la spec no está en `docs/arca/`).

---

## 9. Respuestas Finales

### 1) ¿Lo pondrías en producción hoy?
**NO. Rotundamente no.** Hay 13 bloqueantes verificados, de los cuales 3 pueden rechazar
comprobantes reales ante ARCA (B1, B2, B3), 3 rompen la trazabilidad fiscal (B4, B5, B7),
3 son de integridad de infraestructura (B8, B13, B12) y 3 de configuración riesgosa
(B6, B9, B10, B11). Facturar real en estas condiciones es exponer a los comercios a
rechazos, huecos de numeración, CAE huérfanos y comprobantes con identidad cruzada.

### 2) ¿Cuántos días reales faltan?
**Estimación realista: 2-3 semanas de trabajo efectivo (10-15 días hábiles)** para cerrar
los bloqueantes B1–B5 y B8–B10, más 3-5 días de pruebas en homologación ARCA real (el
formato wire y el `generationTime` deben validarse contra ARCA, no contra fakes), más el
trabajo de infraestructura (worker, TLS, backups). Los 🟠 de alto impacto (A1–A5) suman
3-5 días adicionales. **Total realista: 3-4 semanas** si se trabaja a tiempo completo con
revisión.

### 3) Porcentajes estimados
| Área | % |
|---|---|
| Arquitectura | 70 |
| Backend | 63 |
| Frontend | 45 |
| UX | 40 |
| Seguridad | 70 |
| Multi-tenant | 58 |
| DevOps | 22 |
| Validaciones | 75 |
| Facturación (cumplimiento ARCA) | 55 |
| Producción | 18 |

### 4) Tareas absolutamente obligatorias antes del primer cliente real
1. **B1** — Corregir `generatedAt` → `generationTime` en el TRA.
2. **B2** — Tratar `Resultado 'O'` como aprobado (persistir CAE).
3. **B3** — Implementar RG 4291/4444 (identificación obligatoria para Factura B > umbral).
4. **B4** — Snapshot del emisor/receptor en el ledger; usar en `reconstruir()`.
5. **B5** — Cablear `CaePerdidoHandler` a una acción operativa (o deshabilitarlo explícitamente).
6. **B6/B7** — Migrar identidad del comercio a datos per-comercio; eliminar pluck global en TicketBuilder/job/email.
7. **B8** — Worker de cola + scheduler en el stack.
8. **B9** — Default de entorno a `homologacion` + `.env.example` documentado con vars ARCA.
9. **B10/B11/B12** — `APP_ENV=production`, `APP_DEBUG=false`, proxies restringidos, TLS funcional.
10. **B13** — Backup de PostgreSQL con retención + prueba de restauración.
11. **Pruebas en homologación ARCA real** de todo el flujo (WSAA, FECAESolicitar, NC, QR), porque los tests actuales usan fakes y nunca hablaron con ARCA.
12. **A1–A5** — Unificar CUIT front/back, validar PV contra ARCA, límites de pfx, revalidar vigencia al emitir, reintentos SOAP con idempotencia.
13. **Tests Tier-1** (sección 3 de `docs/informes/F10-exhaustiva.md`): snapshot de reimpresión, CUIT legacy → 422, CAE vencido, certificado vencido al emitir, doble POST, concurrencia, timeout en FECAESolicitar, queue job, Resultado 'O', RG 4444.
14. **A6** — Policies de `CertificadoFiscal`/`PendienteNc` con `comercio_id`.

---

## 10. Conclusión

El módulo tiene el mejor dominio del sistema y una suite de 238 tests con asserts fuertes.
Pero la brecha entre "dominio sólido" y "producción" está en tres frentes: **cumplimiento
ARCA** (3 bugs de rechazo), **trazabilidad** (snapshot incompleto + CAE perdido sin
recovery) e **infraestructura** (cola, scheduler, TLS, backups, entorno). No hay atajo:
hay que corregir los bloqueantes y probar contra homologación real de ARCA antes del
primer cliente. La regla del proyecto aplica: el sistema maneja dinero real y comprobantes
fiscales con CAE; un error aquí no es un bug, es un problema legal.
