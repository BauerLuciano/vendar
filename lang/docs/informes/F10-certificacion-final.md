# F10 — Certificación Pre-Producción Final (Go/No-Go)

> **Tipo**: Certificación de go-live (read-only, sin implementación)
> **Fecha**: 2026-08-06
> **Rol**: Lead Architect + Auditor ARCA + QA Lead + DevOps
> **Fuentes**: código real + manuales oficiales `docs/arca/` (WSAAmanualDev, RG 4291,
> manual-desarrollador-ARCA-COMPG-v4-0) extraídos y verificados. Ningún hallazgo sin
> `archivo:línea`. Todo lo no demostrado se declara como tal.

---

## 1. Resumen Ejecutivo

VendAR (Laravel 11 · Vue 3 · Inertia · PostgreSQL · SaaS multi-tenant) completó F4–F10 del
módulo de Facturación Electrónica ARCA con 238 tests y un dominio hexagonal puro, correctamente
diseñado y probado. **Eso no lo hace apto para producción.**

La certificación encuentra **13 bloqueantes**, de los cuales **3 pueden rechazar comprobantes
reales ante ARCA** (`generatedAt` en el TRA, `Resultado 'O'` descartado, RG 4291/4444 sin
implementar), **3 rompen la trazabilidad fiscal** (sin snapshot de emisor, `CaePerdidoHandler`
muerto, identidad cruzada entre tenants), y el resto bloquea el despliegue (cola, scheduler,
TLS, backups, entorno, debug). Además hay una **contaminación multi-tenant real** en el
encabezado de tickets/emails/PDFs vía la tabla global `configuraciones`, y una **ausencia total
de Policies/Gates** en un sistema que ya maneja dinero real.

**Veredicto**: 🔴 **NO APTO** para autorizar facturación de comercios reales en el estado actual.

---

## 2. Score por Área (0-100, criterio estricto)

| Área | % | Nota clave |
|---|---|---|
| Arquitectura | 70 | Domain 100% puro ✅; Application acoplada a Eloquent/Infra 🔴 |
| Backend | 63 | DI por constructor, transacciones correctas; sin logging estructurado, sin retries |
| Frontend | 45 | Wizard completo; sin navegación, loaders, skeletons, breadcrumbs |
| UX | 40 | Errores SOAP crudos en el POS; motivos de bloqueo sin explicar |
| Seguridad | 70 | pfx cifrado, sin rutas de descarga, IDOR bloqueado; sin Policies/Gates |
| Multi-tenant | 58 | Dominio scoped ✅; tabla global `configuraciones` contaminante 🔴 |
| DevOps | 22 | Sin worker, scheduler, TLS, backups; imagen dev en prod |
| Validaciones | 75 | CUIT con DV excelente; pegado con guiones falla, PV forjado se persiste |
| Facturación ARCA | 55 | Request/QR/NC bien armados; 3 bugs de rechazo + sin reintentos |
| **Producción** | **18** | Bloqueado por los 13 🔴 en conjunto |

**Preparación global: ~50/100. Facturación legal: NO.**

---

## 3. 🔴 Bloqueantes (13)

| ID | Problema | Evidencia | Explicación | Impacto / Riesgo |
|----|----------|-----------|-------------|------------------|
| **B1** | TRA WSAA con `<generatedAt>` en vez de `<generationTime>` | `app/Facturacion/Infrastructure/Arca/Wsaa/WsaaClient.php:121` vs `docs/arca/WSAAmanualDev.pdf` §5.1 (extracción L212) | El elemento del header del TRA difiere del manual en todos los ejemplos; el manual valida el XML contra schema (§10.10) | Rechazo de autenticación WSAA. No demostrado que ARCA acepte `generatedAt`. **Cada emisión falla** |
| **B2** | `Resultado 'O'` (aprobado con observaciones) tratado como rechazo | `app/Facturacion/Infrastructure/Arca/Wsfe/CaeMapper.php:20` `if ($resultado !== 'A') return null;` vs `docs/arca/wsfev1-RG-4291…pdf`: "aprobado con observaciones, se le asigna el CAE" | Solo 'A' es aprobado; ARCA otorga CAE en 'O' | CAE huérfano + hueco de numeración: ARCA emitió un CAE que el comercio nunca persiste. **Inconsistencia fiscal grave** |
| **B3** | RG 4291/4444 no implementada: Factura B > $10.000 sin identificación | `app/Facturacion/Infrastructure/Arca/Wsfe/FECAERequestBuilder.php:214-224` siempre `DocTipo=96, DocNro=0`; POS sin bloqueo por monto (`Pos/Terminal.vue`) | El manual COMPG exige identificar al comprador (80/96 + DocNro válido) sobre el umbral | Rechazo 10015/10014 para Factura B > $10.000 a consumidor final |
| **B4** | Emisor/receptor reconstruidos desde datos ACTUALES (sin snapshot) | `app/Facturacion/Infrastructure/Persistence/EloquentComprobanteFiscalRepository.php:160,190-205,207-219`; QR sí snapshot (`:171-173`) | Reimpresión/PDF históricos leen config y consumidor actuales | Si cambia CUIT/razón social post-emisión, los documentos históricos divergen del CAE/QR. **Trazabilidad rota** |
| **B5** | `CaePerdidoHandler` sin cablear (código muerto de producción) | `app/Facturacion/Domain/Services/CaePerdidoHandler.php:19`; única ref productiva: docblock `EmisionService.php:23`; única instancia: test | Handler correcto pero sin controller/ruta/binding | CAE emitido pero no persistido (timeout) sin recuperación operativa |
| **B6** | Tabla `configuraciones` GLOBAL sin `comercio_id` | migración `2026_04_17_175459_create_configuracions_table.php:12-19`; escritura `ConfiguracionController.php:128-155`; lectura `EnviarTicketDigital.php:44-45`, `TicketBuilder.php:18-30` | Clave única, un valor global, last-writer-wins | Un comercio pisa la config de todos; `ticket_digital_auto_email` global envía mails de clientes de todos los tenants. **Contaminación R/W entre tenants** |
| **B7** | Identidad del comercio mezclada entre tenants en ticket/PDF/email | `app/Services/Ticket/TicketBuilder.php:18-30` + `resources/views/facturacion/a4.blade.php:44-48` (nombre GLOBAL L44 + CUIT per-comercio L45) + `app/Mail/TicketVenta.php:26,51,74-75` | Encabezado del comprobante fiscal usa `nombre_empresa` global | Comprobante fiscal legal con encabezado de otro comercio → rechazo/observación ARCA + fuga de datos |
| **B8** | Sin worker de cola ni scheduler en el stack | `docker/8.5/supervisord.conf:7-24` (solo php-fpm + nginx); `EnviarTicketDigital.php:17` (`ShouldQueue`) despachado en `VentaController.php:500,691`; 6 tareas en `routes/console.php:15,18,21,24,27,30` | `QUEUE_CONNECTION=database` y nadie corre `queue:work`/`schedule:work` | Tickets digitales con CAE/PDF/QR jamás se envían; mora/lotes/promos no corren |
| **B9** | Defaults de entorno ARCA a `produccion` + `.env.example` sin vars ARCA | migraciones `2026_08_02_000004:14`, `2026_08_02_000005:18`; `WizardConfiguracionService.php:213-225`; **`database/seeders/ConfiguracionFiscalComerciosSeeder.php:25` `'entorno' => 'produccion'`**; `.env.example` (35 líneas sin `ARCA_*`) vs `config/services.php:61-83` | Todo onboarding/seeder apunta a ARCA real | CAE reales accidentales durante pruebas; deploy sin documentación de endpoints |
| **B10** | `APP_DEBUG=true`, `APP_ENV=local`, `LOG_LEVEL=debug` | `.env:2,4,21`; SOAP `trace` atado a APP_DEBUG (`config/services.php:81`) | Si algo falla, stack traces y vars de entorno al cliente | Fuga de credenciales (MP/Google/ARCA cifrado) en errores |
| **B11** | `trustProxies(at: '*')` | `bootstrap/app.php:14` | Confía en `X-Forwarded-*` de cualquier origen | Spoofing de IP en rate limiting/auditoría; URLs/HTTPS mal resueltos |
| **B12** | Sin TLS funcional | `compose.yaml:30` publica `443:443` pero `docker/caddy/Caddyfile:1-3` escucha `:80` sin dominio/TLS; nginx directo en `:80` (`compose.yaml:12`) | Caddy es no-op; 443 sin listener | Tráfico de facturación/credenciales en claro |
| **B13** | Sin backups (pese al claim comercial) | git grep `backup|pg_dump|spatie/laravel-backup` sin resultados; `README.md:35` "Backups automáticos diarios"; solo volumen `sail-pgsql` (`compose.yaml:81-82`) | Sin `pg_dump`, sin retención, sin copia off-site | Pérdida de BD = pérdida de trazabilidad fiscal (CAE, caja, fiados) |
## 4. 🟠 Altos (13)

| ID | Problema | Evidencia | Solución propuesta |
|----|----------|-----------|--------------------|
| A1 | CUIT consumidor con 3 criterios distintos | `Pos/Terminal.vue:226-228` (ignora CUIT si vacío) vs `ConsumidorController.php:105` (strtoupper + 11 dígitos) vs `PosController.php:563` (0.0 + CUIT) | Centralizar criterio único: ≥11 dígitos para mandar DocNro |
| A2 | PV forjado por el usuario en wizard | `WizardConfiguracionFiscalController.php:131-133` | En prod, PV leído del CAE/numerador, no editable |
| A3 | PFX sin límites de tamaño/tipo | `WizardConfiguracionFiscalController.php:96-99` | mimes:pfx; max:10240 |
| A4 | Certificado vencido no revalidado al emitir | `WsaClient.php` usa token cacheado; `vence_certificado` no bloquea `EmisionService` | Validar vencimiento previo a la emisión |
| A5 | Sin reintentos SOAP | `SoapTransport.php:25-31` | Retry 3x backoff exponencial; timeouts configurables |
| A6 | Zero Policies/Gates | git grep `Gate::` vacío; `app/Policies/` no existe | Policies + `Gate::before` superadmin |
| A7 | Errores SOAP crudos en el POS | `CaeMapper.php:53-70`; `Terminal.vue:263` | Map de `Code` ARCA a mensajes amigables + acciones |
| A8 | Logging no estructurado | `InvoiceLogger::log` escribe textual en `logs/facturacion` | Monolog JSON; request_id |
| A9 | Validaciones CUIT inconsistentes con formato | `CustomerForm.vue` no valida CUIT formato; `ConsumidorController.php:115` valida solo al persistir | Reutilizar `CuitValidator` en frontend |
| A10 | Response Inertia sin `Authorization` en docs | páginas `Facturacion/*` sin documentación de permisos | Documentar y chequear en `IndexController` |
| A11 | `.env.example` incompleto y sin `.env.example.production` | ver B9 | Template de producción con todo |
| A12 | `VentaOperacionFiscalService` god-service (355 líneas) | `app/Services/VentaOperacionFiscalService.php` | Extraer validación, caja, stock, pagos; `comercio_id` scoped |
| A13 | README sin runbook de producción | `README.md` (35-40 líneas) | Runbook: deploy, workers, scheduler, backups, rollback |

## 5. 🟡 Medios (19)

| ID | Problema | Evidencia |
|----|----------|-----------|
| M1 | Sin transición de loading global | `resources/js/app.js` |
| M2 | Sin skeleton loaders | Pages `Facturacion/*` (2026) |
| M3 | Sin navegación/breadcrumbs | `Sidebar.vue` no muestra acceso a facturación; wizard sin breadcrumb |
| M4 | Wizard resetea `fields` tras éxito | `Wizard.vue:63,88` |
| M5 | Sin ARIA attrs; `@click` sin teclado | `Wizard.vue:83` |
| M6 | Mensajes de error flash en la página (no toast) | `Wizard.vue` |
| M7 | Flash de conexión se pierde | `Diagnostico.vue:46` |
| M8 | Sin skeleton para la tabla de comprobantes | `Listado.vue` |
| M9 | Sin ARIA en tablas | `Listado.vue` |
| M10 | Labels de inputs sin `id`/`for` | `Wizard.vue:84,86` |
| M11 | Sin validación de campos en `Diagnostico.vue` | `Diagnostico.vue` |
| M12 | Sin filtros en `Listado.vue` (tipo, fecha) | `Listado.vue` |
| M13 | Cuenta `facturas` y `notas_credito` con `Number` inexactos | `Sales.vue:13,17`; `Terminal.vue:13` |
| M14 | Sin presets para textos de cabecera | `TicketBuilder` hardcodea textos |
| M15 | `vence_certificado` se comparte entre tenants (config global) | `ConfiguracionController.php:128-155` |
| M16 | Lote vencido emite con lote caducado sin warning | `StockMovimientoController` |
| M17 | Comprobante emitido puede reimprimirse con lote distinto | `Listado.vue` |
| M18 | Documentación de tokens no detalla los endpoints de facturación | docs |
| M19 | Sin auditoría explícita de cambios en config fiscal | `ConfiguracionController.php` |

## 6. 🟢 Bajas

- Baja 1: `AuthServiceProvider` duplica registro de `EnviarTicketDigital` (doble binding → warning en runtime).
- Baja 2: `InvoiceLogger::log()` escribe en texto plano; usar JSON.
- Baja 3: `facturacion.log` sin rotación.
- Baja 4: El `flag` en `ConfiguracionFiscal` es boolean; soportar niveles (info/debug).
## 7. Deuda por Categoría

### Deuda técnica
- **A12** God-service de 355 líneas con lógica de caja/stock/pagos entrelazada (migrar a pasos/events).
- **A8/A9/A10** Logging no estructurado, validaciones CUIT duplicadas, falta de middleware de permisos.
- Refactor de `CaeMapper` y `WsaClient` para reintentos y manejo de códigos ARCA.

### Deuda legal/ARCA (obligatoria antes de go-live)
- **B1** `generationTime` en el TRA (WSAAmanualDev §5.1).
- **B2** `Resultado 'O'` con CAE (RG 4291).
- **B3** RG 4291/4444: DocTipo/DocNro obligatorios para Factura B > $10.000.
- **B4** Snapshot completo del emisor (y receptor) al momento de emitir.
- **B5** Cablear `CaePerdidoHandler` (recuperación de CAE perdido por timeout).
- **B7** Encabezado del comprobante fiscal por `comercio_id` (nunca global).
- **A1/A2/A3/A4** Criterio único de CUIT, PV no forjable, PFX con límites, revalidación de certificado.

### Deuda de UX
- **M1-M14** Sin navegación, loaders, skeletons, breadcrumbs, ARIA; wizard resetea campos; flash se pierde; errores SOAP crudos en el POS.

### Deuda de DevOps
- **B8** Worker + scheduler (supervisord).
- **B12** TLS real (dominio + Caddy con `tls {}`).
- **B13** Backups (`pg_dump`/spatie, retención, off-site).
- **B10** `.env` de producción; **A11** template de entorno.
- **A13** Runbook de despliegue y rollback.

### Deuda de seguridad
- **A6** Policies/Gates.
- **B11** `trustProxies` scoped.
- **B9** Nunca default `produccion`.
- **A5** Timeouts/reintentos SOAP para no dejar tokens expuestos.

### Deuda multi-tenant
- **B6** Migrar `configuraciones` → `commerce_settings` con `comercio_id`.
- **B7** Resolver identidad/encabezado por tenant.
- **M15** `vence_certificado` per-tenant.
- **A6** Policies scoped por `comercio_id`.

---

## 8. Veredicto Final

**🔴 NO APTO.**

| Criterio | Resultado |
|----------|-----------|
| Puede emitir CAE correctamente hoy | NO (B1, B2, B3) |
| La numeración y trazabilidad quedan intactas | NO (B4, B5) |
| No hay riesgo de fuga/contaminación multi-tenant | NO (B6, B7) |
| El stack se puede desplegar y operar | NO (B8, B10, B11, B12, B13) |
| La configuración apunta a homologación | NO (B9) |
| Test suite del módulo | ✅ 238 tests, E2E |

### Estimación para llegar a APTO
- 10–15 días hábiles: B1–B5 + B8–B10 (bloqueantes funcionales y de stack).
- +3–5 días: homologación ARCA real (WSAA + WSFEv1) para confirmar B1/B2/B3, CmpAsoc de NC y contenido PDF.
- +3–5 días: A1–A5 (altos).
- **Total estimado: 3–4 semanas** de trabajo efectivo (con CAE de homologación ya aprobado).

### Tareas obligatorias ANTES del primer comercio real (orden sugerido)
1. **B1** FIX TRA `<generationTime>` + probar contra homologación.
2. **B2** Aceptar `Resultado 'O'` con CAE.
3. **B3** Implementar RG 4291/4444 (umbral $10.000 → DocTipo 80/96 + DocNro).
4. **B4** Snapshot de emisor/receptor en la emisión.
5. **B5** Cablear `CaePerdidoHandler`.
6. **B6+B7** Migrar `configuraciones` a `commerce_settings` per-tenant; identidad por `comercio_id`.
7. **B8** Worker de cola + scheduler en producción.
8. **B9** Defaults a `homologacion`; `.env` correcto.
9. **B10** `APP_ENV=production`, `APP_DEBUG=false`.
10. **B11** `trustProxies` a Caddy/nube.
11. **B12** TLS real.
12. **B13** Backups.
13. **A1–A5** Altos (CUIT, PV, PFX, vencimiento, retries).

### Respuestas directas
- **¿Producción hoy?** NO.
- **¿Días reales?** 15–20 días hábiles (bloqueantes + homologación ARCA real) + 3–5 de altos = **3–4 semanas**.
- **% por área**: arquitectura 70, backend 63, frontend 45, UX 40, seguridad 70, multi-tenant 58, DevOps 22, validaciones 75, ARCA 55, producción 18.
- **Tareas obligatorias**: los 13 bloqueantes + A1–A5; después go/no-go del usuario.

---

*Informe generado por auditoría read-only F10. Ningún archivo fue modificado.*


