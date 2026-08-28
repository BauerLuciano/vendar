# F10 — Auditoría Exhaustiva para Producción (Read-Only)

> **Tipo**: Auditoría de go-live · **Modo**: solo lectura (sin implementación)
> **Fecha**: 2026-08-06 · **Reemplaza parcialmente**: F10-final.md (lo amplía y corrige)
> **Método**: 4 subagentes de exploración en paralelo (seguridad, multi-tenant, validaciones,
> producción) + verificación manual de cada hallazgo crítico sobre el código real.

---

## 1. Veredicto

**NO está lista para producción.**

El núcleo fiscal (dominio hexagonal, ledger inmutable, invariantes de emisión) es sólido y
está probado con **238 tests** (77 domain + 43 arca unit + 97 feature + 21 feature arca).
Pero la auditoría exhaustiva encontró **3 bloqueantes de producción** (cola sin worker,
trazabilidad incompleta del emisor, configuración global multi-tenant) y una lista de
endurecimiento que impiden el go-live responsable.

Esto **no contradice** la auditoría previa: la confirma y la profundiza. La recomendación de
ChatGPT de "revisar absolutamente todo" era correcta; su ejemplo de scorecard (98-99%) era
optimista — la realidad está muy por debajo en frontend/UX/producción.

---

## 2. Scorecard por Área (0-100, crítico y honesto)

| Área | % | Justificación |
|---|---|---|
| **Arquitectura** | **75** | Hexagonal sólida, ledger solo-insert, invariantes fiscales bien implementadas (doble emisión con `lockForUpdate`, snapshots de alícuota y QR). Restan: emisor sin snapshot (C1), CAE perdido sin wiring (C2), CUIT global (C3). |
| **Backend** | **68** | Código limpio, DI completa, transacciones con rollback correcto. Penaliza: sin logging estructurado del módulo, sin reintentos SOAP, handler de excepciones vacío, mensajes crudos al usuario. |
| **Frontend** | **48** | Wizard/Diagnóstico funcionales (5 pasos ARCA completos), sin navegación en el menú (I1), sin loaders ni guard de doble envío (I3), resets de formulario tras éxito (I4), flash volátil (I5). |
| **UX** | **45** | El cajero ve errores SOAP técnicos en el Swal del POS, sin explicación de "por qué no factura" (R2), sin aviso para monotributo (R3), wizard con fricción real en onboarding. |
| **Seguridad** | **72** | Muy bueno: pfx cifrado en DB (AES-256-CBC con APP_KEY), `.env` sin trackear, sin credenciales ARCA en repo, sin rutas de descarga del certificado, IDOR bloqueado en 2 capas. Penaliza: subida de pfx sin límites, secretos cifrados volcados a `activity_log`, sin Policies. |
| **Multi-tenant** | **62** | El dominio filtra `comercio_id` correctamente (repositorios, secuencia, wizard, POS). Pero `configuraciones` es **tabla global sin `comercio_id`** y el módulo la lee/escribe → contaminación de lectura Y escritura entre tenants (C3/H1). |
| **Validaciones** | **78** | Núcleo excelente (CUIT con DV, letra, elegibilidad, NC — 238 tests). Faltan: simetría front/back del CUIT (el pegado con guiones falla en el wizard), PV forjado persiste, pfx sin límites, vigencia del cert solo al cargar. |
| **Producción** | **30** | Bloqueado: sin worker de cola ni scheduler en supervisord (los tickets digitales y tareas nunca corren), `.env.example` sin vars ARCA/queue, defaults hardcodeados a `produccion`, sin retries, sin logging de auditoría fiscal. |

**Promedio ponderado de preparación: ~60/100.** No es un sistema a desplegar mañana.

---

## 3. Hallazgos Consolidados por Severidad

### 🔴 Críticos (6)

| ID | Hallazgo | Evidencia | Impacto |
|----|----------|-----------|---------|
| **C1** | Emisor reconstruido desde config ACTUAL al reimprimir | `app/Facturacion/Infrastructure/Persistence/EloquentComprobanteFiscalRepository.php:160` → `emisorDesdeConfig()` L190-205. QR sí snapshot (L171-173), emisor no | Si cambia CUIT/razón social, reimpresiones y PDFs históricos divergen del CAE/QR original → documento fiscal inconsistente |
| **C2** | Recuperación de CAE perdido sin wiring | `CaePerdidoHandler.php:19` — solo test unitario; ningún controller/ruta lo invoca | Ante CAE emitido pero perdido, no hay recovery operativo |
| **C3** | `configuraciones` es tabla GLOBAL sin `comercio_id` y el módulo la lee/escribe | migración `2026_04_17_175459:12-19` (clave única, sin comercio_id); `ConfiguracionController.php:128-155` (escritura global); `EnviarTicketDigital.php:44-45` (lee flag global) | El último comercio que guarda pisa la config de todos (nombre, cuit, ticket_digital_auto_email). Si un comercio activa "ticket digital por email", se mandan mails a clientes de TODOS |
| **C4** | Sin worker de cola ni scheduler en el stack de deploy | `docker/8.5/supervisord.conf:7-24` (solo php-fpm + nginx; sin `queue:work` ni `schedule:work`); `EnviarTicketDigital.php:17` (`ShouldQueue`) | Los tickets digitales jamás se envían y las tareas programadas (renovación cert, mora, etc.) nunca corren. Bloqueante de producción |
| **C5** | Ticket/email/PDF muestran identidad mezclada entre tenants | `app/Services/Ticket/TicketBuilder.php:18-30` (pluck global) + `facturacion/a4.blade.php:44-48` (nombre global L44 + CUIT per-comercio L45) + `app/Mail/TicketVenta.php:26,51,74-75` | Comprobante fiscal legal con encabezado de otro comercio → rechazo ARCA (QR/CAE llevan el CUIT correcto pero el impreso no) + fuga de datos entre tenants |
| **C6** | Defaults de entorno hardcodeados a `produccion` + `.env.example` sin vars ARCA | migraciones `2026_08_02_000005:18` y `2026_08_02_000004:14` (`default('produccion')`); `WizardConfiguracionFiscalController.php:193` (`?? 'produccion'`); `.env.example` (35 líneas, sin `ARCA_*` ni `QUEUE_CONNECTION`) | Comercio en onboarding apunta directo a ARCA real; riesgo de emisiones reales accidentales durante pruebas |

### 🟠 Importantes (11)

| ID | Hallazgo | Evidencia | Impacto |
|----|----------|-----------|---------|
| **I1** | Módulo sin navegación en el menú | `Sidebar.vue` sin enlaces fiscales; rutas solo en `web.php:337-348` | Feature implementada pero invisible |
| **I2** | Doble fuente de verdad del CUIT | `configuraciones.cuit` (global, sin DV, max:20, `ConfiguracionController.php:132`) vs `configuracion_fiscal_comercios.cuit` (per-comercio, con DV). `comercios` sin columna `cuit` | El ticket no fiscal imprime `config['cuit']` sin validar (`TicketBuilder.php:24`); el fiscal usa el del wizard. Dos CUITs divergentes sin aviso |
| **I3** | CUIT con guiones pegado falla en wizard/consumidor | `Wizard.vue:226-228` (`maxlength="11"` trunca `20-12345678-9` a 10 dígitos → DV falla); `ConsumidorController.php:105` (sin max, acepta) vs `PosController.php:563` (`max:11`, rechaza) | El mismo CUIT válido tiene 3 comportamientos según dónde se cargue; formato estándar AFIP siempre falla en el wizard |
| **I4** | PV forjado se persiste | `WizardConfiguracionFiscalController.php:131-133` (`min:1` sin max/unique/lista) + `WizardConfiguracionService.php:161-169` (persiste directo) | POST `punto_venta=9999` se guarda; recién explota en emisión ARCA |
| **I5** | Subida pfx sin límites | `WizardConfiguracionFiscalController.php:96-99` (`['required','file']` sin mimes/max) + `:101` (`file_get_contents` a memoria) | DoS por memoria + inflar BD con archivos arbitrarios |
| **I6** | Vigencia del certificado solo al cargar | `CertificadoService.php:25-27` valida al cargar; `WsfetResolverPorComercio.php:34` (`materialPara`) no revalida al emitir | Cert vencido entre carga y emisión → falla en WSAA como error genérico de autenticación, venta revertida sin mensaje claro |
| **I7** | Wizard sin loaders ni guard de doble envío | `Wizard.vue` — `router.reload` en L65/75/90/101/114/138 sin `loading`/`submitting` | Doble clic → doble POST |
| **I8** | Reset de formulario tras éxito | `Wizard.vue:63,88` | Campos guardados se limpian; fricción en onboarding |
| **I9** | Flash de conectividad se pierde | `Diagnostico.vue:46` — `router.reload({only:['diagnostico','resultadoConexion']})` | El resultado de probar conexión desaparece |
| **I10** | Sin Policies (directorio inexistente) | `app/Policies/` no existe; `CertificadoFiscal`, `PendienteNc`, `ComprobanteFiscal` sin policy | Autorización delegada a services; cualquier futuro caller que omita el filtro de comercio rompe el multi-tenant sin barrera de framework |
| **I11** | Errores SOAP crudos a UI/logs | `SoapTransport.php:27-31`; `Terminal.vue:974-982` (Swal con texto técnico); `VentaController.php:489-493` (`Log::error` sin stack); `nc_pendientes.motivo_fallo` | El cajero ve "La operación SOAP FECAESolicitar falló: Could not connect to host"; fuga de detalle técnico + mala UX |

### 🟡 Medios (8)

| ID | Hallazgo | Evidencia | Impacto |
|----|----------|-----------|---------|
| **M1** | `CertificadoFiscal` sin `$hidden` + `$fillable` con secretos | `app/Models/CertificadoFiscal.php:14-23` | Riesgo latente de serializar `archivo_pfx`/`password_pfx` si un futuro endpoint devuelve el modelo |
| **M2** | `logAll()` vuelca secretos cifrados a `activity_log` | `app/Traits/Auditable.php:14-17` + `CertificadoFiscal.php:10` | Copia del material cifrado fuera de su tabla, visible en Auditoría |
| **M3** | `VentaOperacionFiscalService::anular()/devolver()` no valida comercio interno | `VentaOperacionFiscalService.php:44-48,177-183` | Defendido hoy en controllers (`VentaController.php:710-718,744-752`) pero la frontera correcta es el servicio |
| **M4** | Teléfono con 4 criterios distintos | `ConsumidorController:103` (max:15), `PosController:562` (max:20), `ConfiguracionController:133` (max:50 sin regex), `SucursalController:61` (max:15) | Un teléfono de 30 dígitos pasa en Ajustes, falla en Clientes/POS |
| **M5** | Nombre consumidor con criterios distintos | `ConsumidorController:99,162` (max:50 + sin números) vs `PosController:560` (max:255 sin regex) | "Juan123" pasa en POS, falla en Clientes |
| **M6** | Cuit del consumidor sin max en un endpoint | `ConsumidorController.php:105` | Ver I3 |
| **M7** | Logs del módulo sin `comercio_id` | `DiagnosticoFiscalController.php:48,65`; `WizardConfiguracionFiscalController.php:70,123,160,240`; `VentaController.php:491,700,730,764` | No se puede diagnosticar por tenant; logs mezclados |
| **M8** | Sin reintentos SOAP en ningún nivel | `SoapTransport.php:25-31` (un `__soapCall`), `WsfetClient.php:184-200`, `WsaaClient.php:66-75`; timeout 30s configurable (`config/services.php:78`) | Emisión = WSAA + WSFE secuenciales → request POS puede bloquear 60s+; riesgo de timeout de proxy y doble clic |

### 🟢 Bajos (8)

| ID | Hallazgo | Evidencia | Impacto |
|----|----------|-----------|---------|
| **L1** | `LedgerComprobanteAsociadoResolver` sin filtro de comercio | `LedgerComprobanteAsociadoResolver.php:16` (`ComprobanteFiscalModel::find`) | No alcanzable desde input directo (flujo NC validado), pero es el único punto sin defensa |
| **L2** | `comercioId()` deriva `0` para Admin Global | `WizardConfiguracionFiscalController.php:181-186`, `DiagnosticoFiscalController.php:74-79` | El rol global no tiene branch explícito |
| **L3** | `tmpfile()` de FirmaCms sin try/finally | `FirmaCms.php:20-51` | Stream abierto si falla `file_get_contents($salida)` |
| **L4** | `DB::raw` con floats | `VentaOperacionFiscalService.php:67,86,227,249` | No inyectable (casteado) pero rompe el patrón seguro; usar `increment()` |
| **L5** | Flag `trace` SOAP muerto | `config/services.php:81` (`'trace' => env('APP_DEBUG', false)`) | Bandera sin lecturas; en prod con APP_DEBUG=true no afecta (no se usa) |
| **L6** | `ext-soap`/`ext-openssl` no declarados en composer.json | `composer.json:8` (solo `php:^8.2`) | `composer install` no falla en entorno sin soap/openssl → deploy silenciosamente roto |
| **L7** | Redis client phpredis sin garantía en prod | `.env:45` (`REDIS_CLIENT=phpredis`) | Cache/lock WSAA falla si el servidor no tiene phpredis |
| **L8** | Certificados cifrados con APP_KEY sin rotación documentada | `CertificadoEncryptor.php:13-21` | Perder APP_KEY = certificados irrecuperables |

---

## 4. Zonas Verificadas como Correctas (evidencia)

- **Ledger multi-tenant**: `EloquentComprobanteFiscalRepository` — todas las queries filtran `comercio_id` (guardar:59, buscarPorVenta:82, buscarPorId:95, listarPorComercio:122, proximoNumero:132-136 con `lockForUpdate`). Unique compuestos `(comercio_id, punto_venta, tipo, numero)`.
- **Config fiscal per-comercio**: `EloquentConfiguracionFiscalRepository.php:20,42-43`.
- **Certificados**: cifrados en DB (`CertificadoEncryptor.php:13-21`), nunca en filesystem → sin colisión de paths. PDFs generados en memoria (`TicketPdfService::generar`), nunca persistidos.
- **Resolvers por comercio**: `WsfetResolverPorComercio.php:34`, `ConectividadResolverPorComercio.php:42`, `PadronResolverPorComercio` — cada cliente SOAP se construye por comercio/entorno con su material.
- **QR**: `QrArcaPayloadBuilder.php:44` usa el CUIT del emisor de la entidad (per-comercio).
- **Reimpresión/descarga**: `ComprobanteImpresionResolver.php:25` (`buscarPorId` con comercioId) + `TicketController.php:16-18` (403 si la venta no es del comercio).
- **IDOR**: bloqueado en 2 capas (`VentaController.php:127-135` + `ComprobanteImpresionResolver.php:20-35`).
- **Sin inyección/XSS**: sin `whereRaw` con input en el módulo; sin `v-html` en páginas del módulo; Inertia escapa por defecto.
- **Sin XML SOAP logueado**: 0 usos de `__getLastRequest`/`__getLastResponse` en `app/`.
- **Rutas**: módulo completo bajo `auth` + `role:SuperAdmin|Administrador Global` (`web.php:313,337-348`).
- **Cifrado de la credencial de plataforma** (padrón): `CredencialPlataformaRepository.php:26-29`.
- **Tests**: 238 tests del módulo + E2E completo (venta→CAE→QR→historial→reimpresión→PDF→email→NC).

---

## 5. Decisión de Producto: Unificación del CUIT

**Pregunta clave**: ¿el CUIT de "Ajustes del Local" debe ser el mismo que el de Facturación Electrónica?

**Recomendación arquitectónica (pensando como producto SaaS):**

1. **Una sola fuente de verdad**: `comercios.cuit` (columna nueva, per-comercio, con DV).
2. **Ajustes del Local** (`ConfiguracionController`) deja de escribir `configuraciones.cuit` y pasa a leer/escribir `comercios.cuit`. Se elimina el CUIT de la whitelist global.
3. **Configuración Fiscal** (`configuracion_fiscal_comercios.cuit`) **se inicializa desde `comercios.cuit`** al crear la config, y por defecto **heredada** (si el comercio no la sobreescribe).
4. **Divergencia permitida pero advertida**: en el caso real de un comercio que factura con una sociedad distinta de la que identifica el local, la config fiscal puede tener su propio CUIT. En ese caso la UI muestra un warning "El CUIT fiscal difiere del CUIT del comercio" en Ajustes, wizard y ticket no fiscal.
5. **Prioridad**: para ARCA y tickets fiscales manda `configuracion_fiscal_comercios.cuit` (el que está en el CAE/QR). Para tickets NO fiscales manda `comercios.cuit`. Cuando coinciden (caso normal), no hay ambigüedad.
6. **TicketBuilder** debe resolver el encabezado por `$venta->...->comercio_id`, no por pluck global (C5).

**Qué vive en cada lado**:

| Dato | Ajustes del Local (`comercios`) | Config Fiscal (`configuracion_fiscal_comercios`) |
|---|---|---|
| CUIT | Sí (fuente primaria) | Sí (inicializado desde ahí, editable con warning) |
| Razón social / nombre | Sí (nombre comercial) | Sí (razón social fiscal del padrón, de solo lectura) |
| Domicilio | Sí (comercial) | Sí (fiscal, si ARCA lo requiere) |
| Teléfono | Sí | No |
| Condición frente a IVA | No | Sí |
| Punto de venta | No | Sí |
| Certificado .pfx + password | No | Sí |
| Entorno (prod/homolog) | No | Sí |

**No duplicar**: el punto de venta, el certificado, el entorno y la condición IVA viven SOLO en la config fiscal. El teléfono SOLO en el local.

---

## 6. Checklist "Todo lo que falta antes del go-live"

### Bloqueantes (impiden desplegar)
- [ ] **B1** Worker de cola + scheduler en supervisord (`queue:work`, `schedule:work`) o procesamiento síncrono de `EnviarTicketDigital` (C4)
- [ ] **B2** Snapshot del emisor en el ledger (CUIT, razón social, condición fiscal) al emitir; usar ese snapshot en `reconstruir()` (C1)
- [ ] **B3** Migrar CUIT del local a `comercios.cuit` y sacarlo de la tabla global `configuraciones` (C3)
- [ ] **B4** TicketBuilder/TicketVenta/job: resolver identidad por comercio, no por pluck global (C5)
- [ ] **B5** Cablear `CaePerdidoHandler` a una acción operativa (C2)
- [ ] **B6** Documentar vars `ARCA_*`, `QUEUE_CONNECTION` en `.env.example` y default de entorno a `homologacion` (C6)

### Altamente recomendadas antes del go-live
- [ ] **R1** Navegación al módulo (Sidebar + dashboard) (I1)
- [ ] **R2** Simetría CUIT front/back: normalizar en el frontend del wizard (pegar con guiones), unificar criterio `ConsumidorController` vs `PosController` (I3)
- [ ] **R3** Validar PV contra `FEParamGetPtosVenta` en backend (I4)
- [ ] **R4** Límites de subida pfx (`mimes:pfx,p12`, `max:4096`, `getError()`) (I5)
- [ ] **R5** Revalidar vigencia del certificado al emitir con mensaje claro (I6)
- [ ] **R6** Loaders + guard de doble envío en wizard (I7)
- [ ] **R7** No resetear campos tras éxito (I8)
- [ ] **R8** Policies de `CertificadoFiscal` y `PendienteNc` (I10)
- [ ] **R9** Mapeo de errores ARCA a mensajes amigables en POS (I11)
- [ ] **R10** `$hidden` + `logOnly()` en `CertificadoFiscal` (M1, M2)

### Primer sprint post-go-live
- [ ] Reintentos SOAP con backoff y logging del módulo (M8, I11)
- [ ] Validación de comercio dentro de `VentaOperacionFiscalService` (M3)
- [ ] Unificar criterios de teléfono/nombre (M4, M5)
- [ ] Logs con `comercio_id` (M7)
- [ ] Scheduler de alerta de vencimiento del certificado
- [ ] Tests: cambio de CUIT post-emisión, cert vencido al emitir, doble POST, cola fallida
- [ ] Declarar `ext-soap`/`ext-openssl` en composer.json (L6)
- [ ] `LedgerComprobanteAsociadoResolver` con `comercioId` (L1)

---

## 7. Criterios de Aceptación para Producción

- [ ] Reimpresión de un comprobante tras cambiar CUIT/razón social muestra los datos del CAE original (B2)
- [ ] Los tickets/emails/PDFs usan identidad del comercio correcto en multi-tenant (B4)
- [ ] Las colas se procesan (los tickets digitales llegan) (B1)
- [ ] Suite del módulo 238/238 + E2E verde tras los cambios
- [ ] Sin defaults a producción durante onboarding (B6)
