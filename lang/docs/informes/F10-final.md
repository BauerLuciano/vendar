# F10 — Auditoría Final del Módulo de Facturación ARCA (Read-Only)

> **Tipo**: Auditoría de cierre · **Modo**: solo lectura (sin implementación)
> **Fecha**: 2026-08-05 · **Fase**: F10 (continuidad del Build Plan)

---

## 1. Resumen Ejecutivo

El módulo de Facturación Electrónica ARCA (F4–F10) está **técnicamente sólido y probado**
(108/108 tests del módulo, 1008 assertions; E2E verde; Pint y build limpios). El dominio
cumple las invariantes fiscales críticas (doble emisión bloqueada, trazabilidad, QR snapshot).

Sin embargo, la auditoría read-only detectó **3 hallazgos críticos** y **7 importantes** que
**NO habilitan el paso a producción sin resolverse**. Los dos más graves:

1. **El emisor se reconstruye desde la configuración ACTUAL** al reimprimir comprobantes
   históricos, no desde un snapshot del momento de emisión (`EloquentComprobanteFiscalRepository.php:160`).
   Si cambia el CUIT/razón social, las reimpresiones divergen del CAE original.
2. **La recuperación de CAE perdido es inalcanzable en producción** (`CaePerdidoHandler`
   existe y está testeado, pero no hay ningún controller/ruta que lo invoque).

**Veredicto corto**: No listo para producción todavía. Hay trabajo acotado (≈3-5 días) para
cerrar los 🔴 y los 🟠 de mayor impacto.

---

## 2. Alcance y Metodología

- **Objetivo**: revisión final de frontend, UX, validaciones, robustez, multi-tenant, código
  muerto, calidad de código y criterios de producción del módulo ARCA.
- **Metodología**: 4 subagentes de exploración en paralelo + verificación manual de cada
  hallazgo crítico sobre el código real (evidencia `archivo:línea`).
- **Áreas auditadas**:
  1. Pantallas de configuración fiscal (`Wizard.vue`, `Diagnostico.vue`) y accesibilidad.
  2. Ajustes del Local vs CUIT fiscal (doble fuente de verdad).
  3. Validaciones frontend/backend y flujo de emisión.
  4. UX, robustez, multi-tenant, código muerto y calidad frontend.
- **Criterio de severidad**:
  - 🔴 **Crítico** — riesgo fiscal, de datos o bloqueante de producción.
  - 🟠 **Importante** — degrada confiabilidad, trazabilidad o UX crítica; recomendable antes de producción.
  - 🟡 **Recomendado** — mejora de robustez/usabilidad a corto plazo.
  - 🟢 **Mejora** — pulido opcional, no bloqueante.

---

## 3. Hallazgos por Severidad

### 🔴 Críticos (3)

| ID | Hallazgo | Evidencia | Impacto |
|----|----------|-----------|---------|
| **C1** | Emisor reconstruido desde config ACTUAL al reimprimir | `app/Facturacion/Infrastructure/Persistence/EloquentComprobanteFiscalRepository.php:160` → `emisorDesdeConfig()` L190-205 | Si cambia CUIT/razón social, reimpresiones y PDFs históricos muestran datos divergentes del CAE/QR original. El QR sí está snapshot (L171-173), pero el emisor no. |
| **C2** | Recuperación de CAE perdido sin wiring | `app/Facturacion/Domain/Services/CaePerdidoHandler.php:19` — solo existe el test `CaePerdidoHandlerTest.php`; ningún controller/ruta lo invoca | Ante un CAE emitido pero perdido (crash/red), no hay forma operativa de recuperarlo; riesgo de duplicación manual o venta sin comprobante. |
| **C3** | CUIT del "Local" vive en `configuraciones` (tabla GLOBAL sin `comercio_id`) | `app/Http/Controllers/ConfiguracionController.php:20`, `resources/js/Pages/Configuracion/Index.vue:257-258` | El CUIT del local se escribe en una fila compartida de todo el SaaS. Riesgo de fuga/sobreescritura entre tenants. Adicionalmente coexisten **dos CUITs** con fuentes distintas (ver I2). |

### 🟠 Importantes (7)

| ID | Hallazgo | Evidencia | Impacto |
|----|----------|-----------|---------|
| **I1** | Módulo sin navegación en el menú | `resources/js/Components/Sidebar.vue` — sin enlaces fiscales; rutas solo en `routes/web.php:337-348` | El usuario no descubre ni accede a configuración/diagnóstico; solo por URL directa. Feature implementada pero invisible. |
| **I2** | Doble fuente de verdad del CUIT | `configuraciones.cuit` (global, sin DV, `max:20`) vs `configuracion_fiscal_comercios.cuit` (per-comercio, con DV y padrón). `comercios` no tiene columna `cuit` | El "Ajustes del Local" puede mostrar un CUIT distinto al fiscal sin aviso. Confusión operativa y riesgo de emitir con datos inconsistentes. |
| **I3** | Wizard sin estados de carga ni bloqueo de doble envío (pasos 1-4) | `resources/js/Pages/Facturacion/Wizard.vue` — `router.reload` en L65/75/90/101/114/138 sin `loading` ni guard de `submitting` | Doble clic → doble POST; sin feedback el usuario percibe congelamiento. |
| **I4** | Reset de formulario tras éxito impide avanzar | `Wizard.vue:63,88` | Campos limpiados (p.ej. CUIT) obligan a reingresar datos que ya se guardaron; fricción crítica en el flujo de onboarding. |
| **I5** | Flash de conectividad se pierde tras reload | `resources/js/Pages/Facturacion/Diagnostico.vue:46` — `router.reload({ only: ['diagnostico','resultadoConexion'] })` | El resultado de "probar conexión" se consume y desaparece; el usuario debe volver a probar para ver el estado. |
| **I6** | `Cuit` del consumidor sin `max:11` en backend | `app/Http/Controllers/Api/ConsumidorController.php:105` (`cuit` `nullable|string`) — el frontend POS limpia con `replace(/\D/g,'').slice(0,11)` (`Terminal.vue:1491-1492`) | La validación deja de ser simétrica front/back; input mayor a 11 chars pasa a backend y se trunca silenciosamente. |
| **I7** | Cambio de CUIT/PV/entorno sin confirmación ni aviso de impacto | `WizardConfiguracionFiscalController.php` — guarda sin validar que existan comprobantes emitidos ni advertir consecuencias | Riesgo de desconfiguración accidental que invalide la máquina de estados de emisión. |

### 🟡 Recomendados (6)

| ID | Hallazgo | Evidencia | Impacto |
|----|----------|-----------|---------|
| **R1** | `CaePerdidoHandler` y `consultarComprobante` solo cubiertos por tests unitarios | `WsfetClient.php:68`, `CaePerdidoHandler.php:29` | Feature de recuperación implementada y probada pero inalcanzable (relacionado con C2). |
| **R2** | `EstadoModuloFiscal::LISTO_PARA_FACTURAR` bien filtrado en POS pero sin mensaje de "por qué no" | `app/Http/Controllers/PosController.php:111` | El POS bloquea correctamente; falta explicar al cajero el motivo (config incompleta / conexión caída). |
| **R3** | Monotributo → letra `no_soportado` | `app/Facturacion/Domain/Rules/DeterminacionLetraRule.php:21-34` | Decisión correcta y documentada, pero el frontend no explica que el cliente monotributista impide la venta (UX). |
| **R4** | Reimpresión sin CAE/QR divergente del emitido | Derivado de C1 — `TicketPdfService` elige vista legal según comprobante reconstruido | Si el ledger no reconstruye bien, el PDF legal no muestra QR/CAE aunque existan. Mitigado parcialmente por snapshot del QR. |
| **R5** | `pluck('valor','clave')` en `Configuracion` y `pendienteParaVista` sin filtro explícito de comercio | `EnviarTicketDigital.php:44`, `DiagnosticoFiscalController.php` | Config global leída sin contexto de tenant; funciona hoy pero es frágil para multi-tenant. |
| **R6** | `Ventas.store` no idempotente a nivel HTTP | `routes/web.php` + `VentaController` | Doble POST del POS puede duplicar ventas; mitigado por el control de secuencia fiscal solo para la emisión. |

### 🟢 Mejoras (4)

| ID | Hallazgo | Evidencia | Impacto |
|----|----------|-----------|---------|
| **G1** | Sin `max:11` en backend del consumidor (ver I6) como regla `Form Request` dedicada | `ConsumidorController.php` | Centralizar validación CUIT en una regla reutilizable. |
| **G2** | Deprecaciones `PDO::MYSQL_ATTR_SSL_CA` en consola de tests | suite | Preexistentes, no bloqueantes. |
| **G3** | `Wizard.vue` y `Diagnostico.vue` no usan `Link` de Inertia para navegación interna | `Wizard.vue:93` | Mejora de accesibilidad/rendimiento. |
| **G4** | Cobertura de validación de máscara CUIT asimétrica (backend normaliza, frontend exige formato) | `Cuit.php:29-53` | Unificar criterio: el backend normaliza correctamente; el frontend debería mostrar errores claros por DV inválida. |

---

## 4. Configuración Fiscal Frontend

**Cobertura completa**: el `Wizard.vue` cubre los 5 pasos obligatorios de ARCA
(CUIT + condición IVA, razón social, punto de venta, certificado PFX + password, entorno).

**Hallazgo principal**: sin entrada en `Sidebar.vue` (I1). Las páginas existen y funcionan
pero son inaccesibles por navegación; el E2E y el diagnóstico las ejercitan por URL directa.

**Positivo**: `Diagnostico.vue:93` enlaza al wizard, y el wizard valida CUIT con DV antes de
persistir.

---

## 5. Ajustes del Local vs CUIT Fiscal

Problema de **arquitectura de datos** (I2 + C3):

- `configuraciones` = tabla **global** sin `comercio_id` → el CUIT del local es de todo el SaaS.
- `configuracion_fiscal_comercios` = tabla **per-comercio** → el CUIT fiscal sí es multi-tenant.
- `comercios` = no tiene columna `cuit`.

**Consecuencia**: un mismo negocio tiene dos CUITs conceptualmente distintos (el "del local"
para identificación comercial y el "fiscal" para ARCA). En la práctica son el mismo CUIT, pero
con reglas de validación distintas (uno `max:20` sin DV, el otro con DV y padrón).

**Recomendación (no implementada, solo propuesta)**: migrar `cuit` de `configuraciones` a la
tabla `comercios` (con DV), y que `configuracion_fiscal_comercios` tome el CUIT por defecto del
comercio al crear la config. Un solo origen de verdad multi-tenant.

---

## 6. Validaciones y Flujo

**Correcto**:
- CUIT con dígito verificador en backend: `app/Facturacion/Domain/ValueObjects/Cuit.php:29-53`
  (pesos `[5,4,3,2,7,6,5,4,3,2]`, normaliza no-dígitos).
- Determinación de letra por condiciones fiscales: `DeterminacionLetraRule.php:21-34`
  (RI+RI → A; resto → B; monotributo → no_soportado).
- Flujo POS → emisión → ledger → PDF → email conectado (verificado en F10-E2E).
- Doble emisión bloqueada por `ControlSecuenciaFiscal` con `lockForUpdate`; `anular()` idempotente.

**A corregir**:
- Simetría front/back del CUIT del consumidor (I6).
- Bloqueo de cambios de CUIT/PV tras emisión con advertencia explícita (I7).

---

## 7. UX y Robustez

- Wizard sin loaders ni doble-envío (I3); reset de campos tras éxito (I4); flash de conexión
  volátil (I5); módulo invisible en el menú (I1).
- POS bloquea correctamente cuando el módulo no está `LISTO_PARA_FACTURAR`, pero sin explicar
  el motivo al cajero (R2).

---

## 8. Multi-Tenant y Seguridad

- ✅ El dominio filtra por `comercio_id` en emisión, ledger, secuencia y configuración fiscal.
- ✅ `PosController` filtra por `estado_modulo` y comercio.
- ❌ **C3**: el CUIT del "Local" en `configuraciones` (global) es la única fuga multi-tenant
  encontrada y es preexistente al módulo, pero el módulo la expone en pantalla.
- ✅ QR, CAE y desglose se persisten en el ledger (trazabilidad a prueba de cambios posteriores),
  con la excepción del emisor (C1).

---

## 9. Código Muerto y Deuda Técnica

- `CaePerdidoHandler` + `Wsfet::consultarComprobante`: implementados, probados (unit), **sin
  wiring de aplicación** (C2/R1).
- `ComprobanteFiscalAdapter`: reportado como sin cablear (no usado por flujo activo).
- Deprecaciones PHP (SSL_CA): preexistentes, no del módulo.
- Deuda preexistente documentada en `F10-auditoria.md` (H1.1/H1.2/H4.2) y `F10_PENDIENTES.md`
  (20 fallos ajenos al módulo).

---

## 10. Calidad de Código Frontend

- `npm run build` ✅ · Pint (backend) ✅ · Vue SFC con Composition API consistente.
- Faltan: estados de loading, guards de `submitting`, componentes `Link` de Inertia y manejo de
  errores por campo en el wizard (en vez de flash genérico).

---

## 11. ¿Listo para Producción?

**NO todavía.** El núcleo fiscal (dominio, invariantes, E2E, robustez) está en condiciones de
producción, pero se requieren correcciones acotadas antes del go-live:

### Imprescindibles (bloquean producción)
1. **C1** — Persistir snapshot del emisor en el ledger (CUIT, razón social, condición fiscal)
   al momento de emitir; usar ese snapshot en `reconstruir()` para reimpresión/PDF.
2. **C2** — Cablear `CaePerdidoHandler` a una ruta/controlador (p.ej. acción "recuperar CAE" en
   diagnóstico) o deshabilitar explícitamente la opción hasta implementarla.
3. **C3** — Migrar `configuraciones.cuit` a `comercios.cuit` (per-comercio, con DV) y sincronizar
   con `configuracion_fiscal_comercios`.

### Altamente recomendadas antes del go-live
4. **I1** — Agregar navegación al módulo (Sidebar + dashboard).
5. **I2** — Unificar origen del CUIT (single source of truth) con sincronización y advertencia.
6. **I4** — No resetear campos tras éxito en el wizard.
7. **I7** — Confirmación explícita al cambiar CUIT/PV con comprobantes emitidos.

### Pueden ir en el primer sprint post-go-live
- I3, I5, I6, R1–R6, G1–G4.

---

## Criterios de Aceptación para Producción

- [ ] C1: reimpresión de un comprobante tras cambiar CUIT/razón social muestra los datos del CAE original.
- [ ] C2: acción "recuperar CAE perdido" accesible y funcional (o deshabilitada con mensaje claro).
- [ ] C3: CUIT del local es per-comercio y validado con DV; sin escritura en tabla global.
- [ ] I1: acceso al módulo desde el menú principal.
- [ ] I2: un único CUIT visible/consistente entre "Ajustes del Local" y configuración fiscal.
- [ ] I4: el wizard conserva los datos guardados y permite avanzar sin reingresar.
- [ ] Suite facturación 108/108 + E2E verde tras los cambios.
