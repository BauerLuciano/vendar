---
name: vendar-architecture
description: 'Guía la arquitectura real de VendAR (Laravel 11 + Inertia + Vue 3 + PostgreSQL, SaaS multi-tenant por comercio_id). Usala antes de diseñar o implementar una funcionalidad para decidir en qué capa va el código, qué patrón existente reutilizar y qué riesgo tiene tocar POS, caja, stock o fiados. Disparadores: arquitectura, nuevo módulo, controller, service, modelo, migración, refactor, dónde va esto, impacto, plan técnico.'
compatibility: opencode
metadata:
  version: "1.0.0"
  domain: architecture
  scope: design
  owner: VendAR
  related-skills: vendar-multitenancy, vendar-business-rules, vendar-testing, vendar-ui-guidelines
---

# Arquitectura de VendAR

## Cuándo usar esta Skill

Usala cuando haya que:

- Diseñar una funcionalidad nueva o un módulo completo.
- Decidir si algo va en controller, service, modelo, job, observer o Vue.
- Evaluar el impacto de un cambio en POS, caja, stock, fiados, compras o pedidos web.
- Revisar si una solución respeta la arquitectura existente.
- Escribir un plan técnico antes de tocar código.

No la uses para reglas comerciales concretas (usá `vendar-business-rules`), aislamiento entre comercios (usá `vendar-multitenancy`), estilos (usá `vendar-ui-guidelines`) o tests (usá `vendar-testing`).

## Stack real del proyecto

| Capa | Tecnología | Versión / nota |
|---|---|---|
| Backend | Laravel | 11.x, PHP `^8.2` |
| Frontend | Vue 3 + Composition API | JavaScript, **sin TypeScript** |
| Puente | Inertia.js | 2.x |
| Bundler | Vite | 5.x |
| Estilos | Tailwind CSS | 3.x |
| Base de datos | PostgreSQL | Docker / Laravel Sail |
| Auth y permisos | Spatie Permission | 6.25, `teams=false` |
| Tests | PHPUnit | 10.5 |
| Formato | Laravel Pint | `vendor/bin/pint` |
| PDF / Excel | dompdf, PhpSpreadsheet | tickets, cierres, reportes |

No agregues TypeScript, Pinia, Nuxt, Livewire, Horizon ni otro framework: no están en el stack y no hacen falta.

## Mapa de capas

```
routes/web.php                  Mapa completo de rutas y middleware
bootstrap/app.php               Middleware global y aliases
app/Http/Controllers/           Orquestación por módulo
app/Http/Middleware/            Auth de tenant, suspensión, módulos, impersonación
app/Http/Requests/              Validaciones reutilizables (pocas)
app/Models/                     Eloquent, relaciones, casts, accessors, scopes
app/Services/                   Operaciones de negocio y transacciones
app/Enums/                      MetodoPago, VentaStatus, PaymentStatus, PaymentChannel
app/Observers/                  Efectos secundarios auditables
app/Jobs/                       Procesos asíncronos
app/Traits/Auditable.php        Activity log en modelos críticos
resources/js/Pages/             Páginas Inertia (una carpeta por módulo)
resources/js/Components/        Componentes compartidos (UI, Commerce, Tienda)
resources/js/Layouts/           AuthenticatedLayout, GlobalAdminLayout, StoreLayout
resources/js/Composables/       Lógica reutilizable de Vue
resources/js/Utils/             Formatters y helpers puros
tests/Feature/                  Tests HTTP/Inertia por módulo
tests/Unit/                     Tests de servicios y lógica pura
docs/informes/                  Informes técnicos por fase (F12, F13, F14, ...)
```

## Ciclo de un request

1. `routes/web.php` aplica el grupo de middleware (`auth`, `verified`, `role:*`, `permission:*`, `modulo:*`).
2. Middleware global (`bootstrap/app.php`): `HandleInertiaRequests`, `VerificarEstadoCuenta`, `VerificarActivacionCuenta`.
3. El controller valida la entrada, autoriza el recurso contra el tenant y delega.
4. Si hay dinero o stock: `DB::transaction` + `lockForUpdate` en las filas críticas.
5. Se escriben los movimientos de trazabilidad (`movimientos_stock`, `movimiento_cajas`, `movimientos_cuenta_corriente`).
6. Se responde con `Inertia::render(...)`, un redirect con flash o JSON.
7. Si hay trabajo pesado: se despacha un Job, nunca se bloquea el request.

## Dónde va cada cosa

| Necesidad | Ubicación correcta | No hacer |
|---|---|---|
| Operación con dinero/stock y varios pasos | `app/Services` + `DB::transaction` | Meterla en el controller o en un modelo |
| Validación de un formulario | `$request->validate()`; `FormRequest` si se reutiliza | Validar solo en Vue |
| Autorización por rol/permiso/módulo | Middleware `role`, `permission`, `modulo` | Chequear roles en el template |
| Autorización por comercio/sucursal | `SucursalScopeService` + validación explícita | Confiar en el `id` que manda el cliente |
| Consulta reutilizable | Scope en el modelo o método de service | Query gigante repetida en controllers |
| Cálculo de presentación | Accessor del modelo o `resources/js/Utils/formatters.js` | Duplicar `Intl.NumberFormat` en cada página |
| Proceso asíncrono | `app/Jobs` | Hacerlo dentro del request |
| Cambio de estado auditable | Método del service + `Auditable` | `update()` suelto sin trazabilidad |
| Tabla o columna nueva | Migración + modelo + casts + test | Tocar la DB a mano |
| Página nueva | `resources/js/Pages/<Modulo>/<Vista>.vue` | Componente suelto sin layout |

## Servicios existentes que hay que reutilizar

- `App\Services\VentaOperacionService`: anulación y devolución de ventas con stock, caja y cuenta corriente. **No reimplementes esta lógica.**
- `App\Services\Suscripcion\SuscripcionService`: única fuente de verdad del estado y la renovación del plan. `calcularPeriodo()` es la regla compartida de períodos.
- `App\Services\Suscripcion\PagoManualService`: registro de pagos manuales de suscripción con validación de precio, idempotencia y auditoría. No reimplementes esa lógica.
- `App\Services\SucursalScopeService`: resolución de sucursal activa, roles y filtros por sucursal/comercio.
- `App\Services\LoteService`: `upsert`, `consumirFifo`, `restaurarLotes`.
- `App\Services\Payment\PaymentRecorder`: registro y cancelación de pagos.
- `App\Services\Ticket\TicketPdfService`: generación de tickets.
- `App\Services\OnboardingService` / `OnboardingBootstrapService`: onboarding del comercio.

Si un service ya resuelve el 80% del problema, extendelo con un método nuevo y tests; no crees un service paralelo.

## Patrones obligatorios

- **Transacciones**: toda operación que toque stock, caja, cuenta corriente o pagos va dentro de `DB::transaction`.
- **Bloqueos**: `lockForUpdate()` sobre `turno_cajas`, `producto_sucursal`, `lotes`, `cuentas_corrientes` y `ventas` antes de leer-modificar-escribir.
- **Enums**: usá `MetodoPago`, `VentaStatus`, `PaymentStatus` y `PaymentChannel`; no compares strings mágicos nuevos.
- **Auditoría**: los modelos críticos usan `App\Traits\Auditable`; los cambios de producto dejan `MovimientoAuditoria`.
- **Idempotencia**: webhooks y confirmaciones de pago deben poder repetirse sin duplicar efectos (`pagoYaRegistrado`, `Payment`). En suscripciones, el pago manual usa `idempotency_key` único y el pago de Mercado Pago usa `(provider, gateway_transaction_id)`; ambos toman `lockForUpdate()` sobre la fila del comercio.
- **Montos**: redondeá a 2 decimales y compará con tolerancia (`abs($a - $b) > 0.01`). No confíes en el total que manda el navegador.
- **Stock**: la verdad es `producto_sucursal.cantidad_fisica` y `cantidad_reservada`; los lotes son FIFO por `fecha_vencimiento`.

## Convenciones de nombres

- Dominio en español: `Venta`, `Caja`, `Turno`, `Consumidor`, `Comercio`, `Sucursal`, `PedidoWeb`.
- Métodos de controller: `index`, `store`, `update`, `destroy` + acciones (`cancelar`, `devolver`, `cerrarCaja`).
- Rutas: `modulo.accion` (`ventas.cancelar`, `caja-diaria.cerrar`).
- Tablas y columnas: `snake_case` en español y plural (`movimientos_stock`, `cuenta_corriente_id`).
- Componentes Vue: `PascalCase.vue`; props y variables `camelCase`; páginas `Modulo/Index.vue`.
- Tests: `tests/Feature/ModuloX_...Test.php` o `tests/Feature/Stock/...Test.php`; métodos `test_...` descriptivos.

## Flujo obligatorio de cambio

1. **Planner**: leer `AGENTS.md`, identificar módulos afectados, riesgos y archivos.
2. **Builder**: cambios pequeños e incrementales, respetando esta arquitectura.
3. **Reviewer**: validar tenant, dinero, stock, trazabilidad y tests.
4. **Informe**: crear `docs/informes/F<##>-<tema>.md` con objetivo, archivos, decisiones, bugs, pruebas y pendientes.

No cambies la arquitectura aprobada ni hagas refactors grandes mezclados con una feature.

## Checklist antes de escribir código

- [ ] ¿Entendí qué módulos toca y si hay dinero/stock/fiados de por medio?
- [ ] ¿La regla va en un service, no en el controller ni en Vue?
- [ ] ¿La query filtra por `comercio_id` o por sucursal del comercio?
- [ ] ¿La operación necesita transacción y `lockForUpdate`?
- [ ] ¿Se registra el movimiento de stock/caja/cuenta corriente?
- [ ] ¿Se reutiliza un service, enum, accessor o formatter existente?
- [ ] ¿Hay test de regresión y test cross-tenant si hay endpoint nuevo?
- [ ] ¿El cambio es incremental y reversible?

## Anti-patrones prohibidos

- Lógica de ventas, stock o caja en `resources/js`.
- Modificar una venta completada sin pasar por anulación/devolución.
- `DB::table('producto_sucursal')->update(...)` sin lock ni movimiento de stock.
- Consultas sin filtro de tenant o que abren acceso cuando `comercio_id` es `null`.
- Duplicar formatters de moneda/fecha en cada componente.
- Agregar dependencias o cambiar versiones sin aprobación.
- Mezclar un refactor estructural con una corrección funcional.
- Tocar POS, caja o stock sin tests que cubran el invariante.

## Referencias del repo

- `AGENTS.md` — roles y flujo obligatorio.
- `routes/web.php` — mapa real de rutas y middleware.
- `bootstrap/app.php` — middleware global y aliases.
- `app/Services/VentaOperacionService.php` — operación comercial completa.
- `app/Services/SucursalScopeService.php` — permisos por sucursal.
- `docs/informes/BUILD-B1.md`, `BUILD-B2.md`, `BUILD-B3-AUDITORIA.md` — decisiones y deuda conocida.
