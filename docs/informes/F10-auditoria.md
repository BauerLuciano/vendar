# Informe Técnico — F10.1: Auditoría del módulo de Facturación Electrónica ARCA

| Campo | Valor |
|---|---|
| Fase | F10.1 (Build Plan §F10: hardening y preparación para producción) |
| Alcance | Auditoría estática del módulo de Facturación Electrónica ARCA (factura, NC total/parcial, WSAA/WSFE, configuración, QR/PDF, historial) |
| Método | 4 subagentes en paralelo: (1) código muerto/duplicación, (2) performance/carreras, (3) seguridad/multi-tenant, (4) arquitectura/DI. **Solo lectura, no se modificó nada.** Hallazgos clave re-verificados contra el código real. |
| Estado | **Terminada** (a la espera de decisión sobre hallazgos ALTA antes de corregir) |

## Objetivo

Detectar defectos, deuda técnica y riesgos de producción en el módulo de facturación antes del go-live,
sin modificar código. Los hallazgos se clasifican por severidad para priorizar las correcciones de la fase
F10 (robustez, E2E, calidad) y documentar los pendientes para producción.

---

## 1. Código muerto / duplicación

### ALTA

- **H1.1 — Contrato huérfano `ComprobanteFiscalAdapter`**
  `app/Facturacion/Domain/Contracts/ComprobanteFiscalAdapter.php:10` declara `interface ComprobanteFiscalAdapter extends Wsfet {}` pero no tiene implementación ni binding en el contenedor. Solo se menciona en un comentario de `WsfetClient.php:18`. No se puede emitir contra este contrato. **Decisión requerida:** implementarlo (renombrar `WsfetClient`) o eliminarlo.

### MEDIA

- **H1.2 — `CaePerdidoHandler` implementado pero nunca cableado**
  `app/Facturacion/Domain/Services/CaePerdidoHandler.php` está completo y testeado, pero no se inyecta en ningún flujo de producción: `EmisionService.php:23` solo lo menciona en un comentario ("La resolución de CAE perdido queda en CaePerdidoHandler"). La recuperación de CAE perdido es **inalcanzable en producción**.
- **H1.3 — Métodos de contrato sin uso en producción** (~6): `Wsfet::consultarComprobante()`, y otros métodos/clases del contrato `Wsfet` con única implementación `WsfetClient` que no se invocan fuera de tests. Solo referenciados por fakes/tests.
- **H1.4 — Enum muerto** (~13 métodos/casos): métodos y casos de `EstadoComprobante`, `TipoComprobante`, `CondicionFiscal` y `Concepto` sin referencia en producción.

### BAJA

- **H1.5 — 7 grupos de duplicación confirmados** (construcción de `Emisor`/`Receptor`, parseo de Cae, formateo de número completo, cálculo de vencimiento CAE, etc.). Menor, sin riesgo funcional.
- **H1.6 — Hallazgo colateral de consistencia**: reportado en informe 1 (detalle guardado en el respaldo de la sesión); sin impacto funcional confirmado.

### Sin hallazgos

- 0 TODO/FIXME/HACK/`console.log`/`dd(`/`dump(`.
- 0 imports sin uso.
- No hay clases duplicadas.

---

## 2. Performance / carreras

### MEDIA

- **H2.1 — `reconstruir()` sin eager loading (N+1)**
  `EloquentComprobanteFiscalRepository.php:149-176`: `reconstruir()` carga `$modelo->venta` (relación lazy, línea 151), `$modelo->venta->detalles` (segunda lazy, línea 276) y consulta `ConfiguracionFiscalComercio` por comprobante (`emisorDesdeConfig`, línea 192). Resultado: `buscarPorVenta()`/`buscarPorId()`/`buscarNotaCredito()` = **4-5 queries por comprobante**. Impacto: flujos de impresión/reimpresión/PDF en catálogo de ventas.
- **H2.2 — `listarPorComercio()` con N+1 real y sin paginación**
  `EloquentComprobanteFiscalRepository.php:120-127`: `where('comercio_id')->get()` y luego `reconstruir()` por cada fila → **~3N+1 queries**. Hoy solo lo usan tests (teórico), pero es una bomba si se expone en un listado.
- **H2.3 — `pendienteParaVista()` con N+1**
  `DiagnosticoFiscalService.php:190-196`: `Venta::with('consumidor')->find(...)` dentro del map de pendientes → 1 query por pendiente. Impacto: panel de diagnóstico con muchos pendientes.

### BAJA / a verificar

- **H2.4 — Numeración y doble emisión concurrente**: `proximoNumero()` (`EloquentComprobanteFiscalRepository.php:129-142`) usa `ControlSecuenciaFiscal::firstOrCreate` + `reservarProximoNumero()` (con `lockForUpdate`) dentro de transacción. El lock existe, pero la reserva persiste antes de la llamada SOAP; el rollback quema números (aceptable por normativa de numeración continua). Verificar en E2E que no haya doble emisión concurrente.
- **H2.5 — Sin índices** para `comprobante_original_id`, y para la búsqueda `(comercio_id, punto_venta, numero)`. Crecimiento del ledger → ralentización.
- **H2.6 — IO de red dentro de transacciones largas**: `EnviarTicketDigital` es sincrónico; el envío por email y la generación de PDF dentro del flujo de venta/NC alargan la transacción de BD.

---

## 3. Seguridad / multi-tenant

### ALTA

- **H3.1 — Cache de TA de WSAA sin `comercio_id` → fuga entre tenants**
  `app/Facturacion/Infrastructure/Arca/Wsaa/WsaaClient.php:32`: la clave de cache es `"arca.wsaa.{entorno}.{servicio}"` **sin `comercio_id`**. El TA (ticket de acceso firmado) está ligado al certificado del comercio. Con dos comercios distintos usando el mismo entorno (homologación/producción):
  - un comercio puede recibir el TA del otro (credencial de emisor equivocada → **fuga de credenciales entre tenants**);
  - falla intermitente cuando ambos usan distinto certificado (WSFE rechaza por signer del TA).
  **Corrección propuesta:** incluir `comercio_id` (o un hash del certificado/material) en la clave.
- **H3.2 — Producto sin scope de comercio en el flujo de venta**
  `app/Http/Controllers/VentaController.php:294`: `Producto::where('id', $item['id'])->where('estado', true)->first()` sin filtrar `comercio_id`. Un `id` de producto de otro tenant puede:
  - validar stock contra la sucursal correcta pero con el producto equivocado;
  - aportar la **alícuota de otro comercio** al comprobante → CAE emitido con alícuota ajena (inconsistencia fiscal y de tenant).
  Afecta directamente al módulo de facturación (la alícuota va al CAE). Preexistente del módulo POS, pero con impacto fiscal.

### MEDIA

- **H3.3 — `buscar()` y `marcarResuelto()` de pendientes NC sin filtro de comercio**
  `EloquentPendienteNcRepository.php:46-51,64-67`: `PendienteNc::find($id)` y `update(['estado'=>'resuelto'])` por `id` sin `comercio_id`. **Mitigado por el llamador** (`DiagnosticoFiscalService::reintentarNc()` valida `$pendiente['comercio_id'] !== $comercioId`, líneas 152-155), pero el repositorio solo es seguro por contrato de uso, no por diseño.
- **H3.4 — Endpoints expuestos y autorización** (a revisar): verificación de que todas las rutas de facturación tengan middleware de permisos. Detalle en respaldo del informe 3.
- **H3.5 — Validaciones de entrada y CSRF** en endpoints fiscales (a revisar en el respaldo).

### Sin hallazgos (buenas prácticas confirmadas)

- Repositorios del módulo filtran `comercio_id` (invariante 6/8).
- Sin mass assignment (ledger inmutable, solo insert).
- QR ARCA y desglose calculados server-side (no se confía en el frontend para montos fiscales).
- Sin secretos en logs; endpoints ARCA vienen de `config/`.

---

## 4. Arquitectura / DI

### MEDIA

- **H4.1 — Violación de capas en `VentaController`**
  `app/Http/Controllers/VentaController.php:89`: acceso directo a `ComprobanteFiscal::whereIn(...)` (Eloquent en el controller) en vez del repositorio bindeado. Consistencia: todo lo demás del módulo pasa por `ComprobanteFiscalRepository`.
- **H4.2 — `CaePerdidoHandler` muerto en DI** (mismo hallazgo que H1.2): implementado pero sin wiring → la recuperación de CAE perdido no es alcanzable.
- **H4.3 — `TicketBuilder.php:126` usa `app(QrArcaRenderer::class)`** en producción: resolución del contenedor dentro del builder en vez de inyección de constructor. Dificulta tests y reemplazo del renderer.
- **H4.4 — Application inyecta infraestructura concreta**
  `WizardConfiguracionService.php:36-37`: `HabilitadorHomologacion` (infraestructura) inyectado en un servicio de Application. Debería ser una abstracción (p. ej. `HabilitadorEntorno`).
- **H4.5 — `NcService` en Application vs arquitectura §3**
  `app/Facturacion/Application/Services/NcService.php` (u otra ruta `Application/Services`): la arquitectura §3 ubica servicios de dominio en `Domain/Services`. Inconsistencia de ubicación (no de comportamiento).
- **H4.6 — `EmisionService` no está registrado en el contenedor** (`app/Facturacion/Domain/Services/EmisionService.php` no tiene binding en `AppServiceProvider`): se instancia vía constructor manual. No rompe, pero es deuda de DI.

### Sin hallazgos

- No hay bindings rotos ni fakes registrados en `AppServiceProvider`.
- Inyección de dependencias por constructor respetada en el resto del módulo.

---

## Resumen y severidades

| # | Hallazgo | Severidad | Archivo | Requiere decisión |
|---|---|---|---|---|
| H3.1 | Cache TA WSAA sin `comercio_id` (fuga entre tenants) | **ALTA** | `WsaaClient.php:32` | Sí |
| H3.2 | Producto sin scope de comercio (alícuota ajena al CAE) | **ALTA** | `VentaController.php:294` | Sí |
| H1.1 | Contrato `ComprobanteFiscalAdapter` huérfano | ALTA/MEDIA | `Contracts/ComprobanteFiscalAdapter.php` | Sí (implementar o eliminar) |
| H1.2 / H4.2 | `CaePerdidoHandler` nunca cableado (CAE perdido inalcanzable) | MEDIA | `Domain/Services/CaePerdidoHandler.php` | Sí |
| H2.1 | N+1 en `reconstruir()` (4-5 queries/comprobante) | MEDIA | `EloquentComprobanteFiscalRepository.php:149` | No |
| H2.2 | N+1 real + sin paginación en `listarPorComercio()` | MEDIA | `EloquentComprobanteFiscalRepository.php:120` | No |
| H2.3 | N+1 en pendientes del diagnóstico | MEDIA | `DiagnosticoFiscalService.php:192` | No |
| H3.3 | `buscar()`/`marcarResuelto()` sin filtro de comercio | MEDIA | `EloquentPendienteNcRepository.php:46,64` | No (mitigado) |
| H4.1 | Eloquent directo en `VentaController:89` | MEDIA | `VentaController.php:89` | No |
| H4.3 | `app(QrArcaRenderer::class)` en producción | MEDIA | `TicketBuilder.php:126` | No |
| H4.4 | Application inyecta `HabilitadorHomologacion` concreto | MEDIA | `WizardConfiguracionService.php:37` | No |
| H4.5 | `NcService` en ruta Application/Services | MEDIA | `Application/Services/...` | No |
| H4.6 | `EmisionService` sin binding en contenedor | BAJA | `AppServiceProvider` | No |
| H1.3–H1.5 | Métodos/controles de contrato y enums sin uso; 7 duplicaciones | BAJA | varios | No |
| H2.4–H2.6 | Numeración/índices/IO en transacción | BAJA | varios | No |

## Criterios de aceptación de la auditoría

- [x] Sin modificar código durante la auditoría.
- [x] Hallazgos clasificados por severidad con `archivo:línea` verificable.
- [x] Hallazgos ALTA explicados y propuestos antes de corregir (política F10).
- [x] Sin hallazgos críticos de seguridad más allá de H3.1/H3.2.

## Correcciones aplicadas (aprobadas por el usuario)

Tras presentar los hallazgos, el usuario aprobó corregir los **dos hallazgos ALTA** y documentar el resto.

- **H3.1 — Cache de TA de WSAA por certificado** (`WsaaClient.php:32`): la clave de cache ahora es
  `arca.wsaa.{entorno}.{servicio}.{sha256(pfx)[:16]}`. El TA está firmado con el certificado del comercio,
  por lo que la clave distingue por material: dos comercios con el mismo archivo siguen compartiendo token
  (legítimo) y dos con certificados distintos jamás comparten (fin de la fuga entre tenants). Se agregó el
  test `test_materiales_distintos_no_comparten_token_de_cache` y se ajustó el test que pre-sembraba la clave
  antigua.
- **H3.2 — Producto scoped por sucursal en la venta** (`VentaController.php:294`): la tabla `productos` no
  tiene `comercio_id`; el scope multi-tenant es por sucursal (`producto_sucursal` → `sucursal.comercio_id`,
  ya validado vía el turno de caja). La búsqueda del producto ahora filtra `whereHas('sucursales',
  sucursal_id = $sucursalId)`, de modo que la alícuota que entra al CAE siempre pertenece al comercio de la
  venta. Se agregó el test `test_admin_a_no_puede_vender_producto_de_comercio_b` (con stock negativo
  habilitado para demostrar que lo bloquea el fix y no el stock).
- **Verificación**: `WsaaClientTest` 5/5 (9 assertions); `Modulo2_VentasTest` 11/11 (43 assertions); suite
  completa de facturación 225 tests / 869 assertions, 0 fallos. Deprecaciones `PDO::MYSQL_ATTR_SSL_CA`
  preexistentes, no bloquean.

## Pendientes (próximos pasos de F10)

1. **F10.2 — E2E**: verificar el flujo Venta → Emisión → CAE → QR → Persistencia → Historial → Reimpresión → PDF → Email → NC → Reimpresión NC → Historial final; detectar huecos.
2. **F10.3 — Robustez**: mapear cada caso borde (CAE rechazado, timeout WSFE/WSAA, cert vencido, PV inexistente, numeración inválida, QR/PDF inexistente, venta sin cliente, venta anulada/anulada por NC, doble emisión, reintentos) contra tests existentes y huecos.
3. **F10.4 — Calidad**: suite PHPUnit completa, Pint, `npm run build`.
4. **F10.5 — `docs/informes/F10.md`** consolidado.
5. **F10.6 — Estado final** con % de finalización y recomendación de merge.
