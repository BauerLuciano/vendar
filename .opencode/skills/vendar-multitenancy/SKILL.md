---
name: vendar-multitenancy
description: 'Aplica las reglas de aislamiento multi-tenant de VendAR (SaaS por comercio_id, sucursales, roles Spatie y middleware de plan). Usala al crear o revisar endpoints, queries, validaciones, policies, reportes, importaciones o cualquier acceso a datos para evitar fugas entre comercios. Disparadores: multi-tenant, comercio_id, sucursal_id, branch_id, aislamiento, fuga de datos, cross-tenant, permisos, roles, scope, 403.'
compatibility: opencode
metadata:
  version: "1.0.0"
  domain: security
  scope: implementation
  owner: VendAR
  related-skills: vendar-architecture, vendar-business-rules, vendar-testing, secure-code-guardian
---

# Multi-tenancy de VendAR

## Regla de oro

**Todo acceso a datos de un comercio debe demostrar a qué comercio pertenece el recurso y a qué comercio pertenece el usuario.** Si no se puede demostrar, se falla cerrado: `403`, `404` o `whereRaw('1 = 0')`. Nunca se abre el acceso cuando `comercio_id` es `null`.

## Modelo de tenancy

- El tenant raíz es `comercios`.
- La operación diaria baja por `sucursales` (`sucursales.comercio_id`).
- El usuario del panel tiene `users.comercio_id` y `users.branch_id` (sucursal activa), más la pivote `sucursal_user`.
- El cliente de la tienda usa otro guard (`consumidor`) y `consumidores.comercio_id`.
- No hay `comercio_id` en todas las tablas: hay que conocer la cadena de pertenencia.

### Matriz de pertenencia

| Tabla / modelo | Cómo se determina el comercio |
|---|---|
| `comercios` | Es el tenant raíz (`id`) |
| `sucursales` | `comercio_id` directo |
| `users` | `comercio_id` directo; `branch_id` debe ser una sucursal del mismo comercio |
| `consumidores` | `comercio_id` directo |
| `pedidos_web` | `comercio_id` + `sucursal_id` (la sucursal debe pertenecer al comercio) |
| `categorias`, `marcas`, `proveedores` | `comercio_id` (nullable = registro global compartido) |
| `productos` | **No tiene `comercio_id`**: se scopea por `producto_sucursal` → `sucursales.comercio_id` |
| `cajas` | `sucursal_id` → `sucursales.comercio_id` |
| `turno_cajas` | `sucursal_id` y `caja_id` → `sucursales.comercio_id` |
| `ventas` | `turno_caja_id` → `turno_cajas.sucursal_id` → `sucursales.comercio_id` |
| `detalle_ventas` | `venta_id` → cadena de venta |
| `cuentas_corrientes` | `consumidor_id` → `consumidores.comercio_id` |
| `lotes`, `movimientos_stock`, `ingresos_mercaderia`, `ordenes_compra` | `sucursal_id` |
| `pagos` (`payments`) | Polimórfico: `payable` (Venta, PedidoWeb, Comercio) |
| `planes`, `configuraciones`, `global_products` | Globales del SaaS, sin tenant |
| `payment_method_configurations`, `store_configs`, `payment_gateways` | `comercio_id` directo |

## Roles y alcance

| Rol | Alcance |
|---|---|
| `Administrador Global` | Todo el SaaS. Puede impersonar comercios. Sus acciones deben quedar auditadas. |
| `SuperAdmin` | Todas las sucursales de **su** comercio. |
| `Encargado` | Solo la sucursal activa. |
| `Cajero` | Solo la sucursal activa. |
| `cliente` | Tienda pública, guard `consumidor`. No entra al panel. |

`spatie/laravel-permission` está configurado con `teams=false`: los roles y permisos son **globales**, no por comercio. Por eso un rol nunca alcanza para autorizar un recurso: siempre hay que validar la pertenencia al tenant.

## Servicio central: `SucursalScopeService`

Usalo en lugar de reimplementar la resolución de sucursal/comercio:

- `obtenerComercioId()`: `users.comercio_id` o `branch.comercio_id`.
- `obtenerSucursalActiva()`: `session('sucursal_activa_id')` o `branch_id`.
- `obtenerSucursalesPermitidas()` / `obtenerSucursalesPermitidasIds()`.
- `obtenerSucursalesDelComercio()` / `obtenerSucursalesDelComercioIds()`.
- `puedeAccederSucursal(int $sucursalId)`.
- `aplicarFiltroSucursal($query, $columna = 'sucursal_id')`.
- `aplicarFiltroComercio($query, $columna = 'comercio_id')`.
- `esAdminGlobal()`, `esSuperAdmin()`, `esJefe()`, `esEncargado()`, `esCajero()`.

`Administrador Global` devuelve colección vacía en `obtenerSucursalesPermitidas()` como señal de “sin filtro”; no interpretes esa colección vacía como “sin acceso”. Para saber si no hay filtro, preguntá `esAdminGlobal()`.

## Patrones de query correctos

### Filtro directo por comercio

```php
$query->where('comercio_id', $scope->obtenerComercioId());
```

### Filtro por sucursal del comercio

```php
$ids = $scope->obtenerSucursalesDelComercioIds();
$query->whereIn('sucursal_id', $ids); // si $ids está vacío, no devolver todo
```

### Productos (sin `comercio_id`)

```php
Producto::whereHas('sucursales', fn ($q) => $q->where('sucursal_id', $sucursalId))
    ->where('estado', true);
```

### Ventas (sin `comercio_id`)

```php
Venta::whereHas('turno.caja.sucursal', fn ($q) => $q->where('comercio_id', $comercioId));
```

### Validación de IDs que manda el cliente

```php
use Illuminate\Validation\Rule;

$request->validate([
    'sucursal_id' => [
        'required',
        Rule::exists('sucursales', 'id')->where('comercio_id', $comercioId),
    ],
    'branch_id' => [
        'required',
        Rule::exists('sucursales', 'id')->where('comercio_id', $comercioId),
    ],
]);
```

Para arrays (`sucursales[]`), validá cada elemento con la misma regla. Nunca confíes en un `comercio_id` enviado por el navegador: deducilo del usuario autenticado.

## Autorización de recursos existentes

Después del route model binding, validá la pertenencia antes de mutar:

```php
$turno = TurnoCaja::with('caja.sucursal')->findOrFail($id);

if (! $scope->puedeAccederSucursal((int) $turno->sucursal_id)) {
    abort(403, 'No tenés acceso a esta sesión de caja.');
}
```

Criterio usado en el proyecto:

- Recurso de otro comercio en un endpoint de mutación → `403`.
- Recurso inexistente o inaccesible por ruta anidada → `404` (`findOrFail`).
- Listados: filtrar, nunca devolver todo.
- Los tests cross-tenant fijan el código esperado; respetá el que ya existe para ese módulo.

## Registros globales

`comercio_id = null` en `categorias`, `marcas` o `proveedores` significa **registro global compartido**. Un usuario de comercio:

- Puede **leerlo**.
- **No** puede editarlo ni eliminarlo (los tests esperan `403`).
- Solo puede crear/editar registros con su propio `comercio_id`.

`configuraciones` es global (no tiene `comercio_id`): cambiar una clave afecta a todos los comercios. No la uses para preferencias por tenant; usá `comercios`, `store_configs` o `payment_method_configurations`.

## Middleware que condiciona el acceso

- `VerificarEstadoCuenta`: si `comercio.status === 'suspendido'` o `vencimiento_pago` ya pasó, redirige a `cuenta.suspendida`. Tiene una lista de rutas exceptuadas (login, tienda, suscripción, webhooks, admin global).
- `VerificarModulo`: lee `comercio.modulos_habilitados[$modulo]`; si no está habilitado, redirige al dashboard con error. Se usa como `modulo:pos`, `modulo:fiados`, `modulo:lotes`, `modulo:proveedores`, `modulo:transferencias`.
- `VerificarActivacionCuenta`: bloquea usuarios `is_active === false` y redirige clientes al área de cliente.
- `RedirectIfCliente`: separa el guard `consumidor` del panel.
- `role` / `permission` / `role_or_permission`: Spatie. Se combinan con la validación de tenant, no la reemplazan.

## Administrador Global e impersonación

- El modo “dios” se detecta con `session('admin_comercio_original_id')` y se expone como `auth.impersonating`.
- Las rutas `/admin-global/*` están protegidas con `role:Administrador Global`.
- Toda acción de admin global sobre datos de un comercio debe quedar auditada (`Auditable`, `MovimientoAuditoria`).
- Al impersonar, el `comercio_id` efectivo es el del comercio impersonado; no mezcles el original con el activo.

## Trampas conocidas del proyecto

- `Comercio` tiene una columna `plan` (string) y una relación `plan()` por `plan_id`. `$comercio->plan` devuelve el string, no el modelo `Plan`. Usá `$comercio->plan_id`, `$comercio->planContratado()` o `$comercio->plan()`. `planContratado()` es la relación inequívoca que usa el código nuevo; `plan()` se conserva por compatibilidad.
- `productos` no tiene `comercio_id`; cualquier filtro directo por esa columna es un bug.
- `ventas` no tiene `comercio_id`; se llega por `turno.caja.sucursal`.
- `configuraciones` es global.
- Los roles son globales (`teams=false`): no asumas que “Cajero de A” es distinto de “Cajero de B”.
- `payments` es polimórfico: validá el `payable` antes de operar.
- No uses `findOrFail($id)` y después `update()` sin comprobar el tenant.

## Tests obligatorios para endpoints nuevos

Agregá casos en `tests/Feature/CrossTenantAttackTest.php` o en un test del módulo:

1. Usuario A lista → no ve datos de B.
2. Usuario A intenta actualizar/borrar/status de B → `403` (o el código que fije el módulo).
3. Usuario A intenta usar un ID global → `403` si es edición.
4. Payload con `comercio_id`/`sucursal_id`/`branch_id` de B → rechazado.
5. Recurso sin comercio (`null`) → no abre acceso.
6. Admin Global → puede, y queda auditado.

## Checklist rápido

- [ ] ¿La query tiene filtro por comercio o por sucursal del comercio?
- [ ] ¿El `sucursal_id`/`branch_id`/`consumidor_id`/`turno_caja_id` del request fue validado contra el tenant?
- [ ] ¿El recurso existente se validó después del binding?
- [ ] ¿El caso “sin comercio” falla cerrado?
- [ ] ¿Los registros globales quedan de solo lectura?
- [ ] ¿El endpoint nuevo tiene test cross-tenant?
- [ ] ¿La acción de admin global queda auditada?

## Referencias

- `app/Services/SucursalScopeService.php`
- `app/Http/Middleware/VerificarEstadoCuenta.php`
- `app/Http/Middleware/VerificarModulo.php`
- `app/Http/Middleware/VerificarActivacionCuenta.php`
- `app/Http/Middleware/RedirectIfCliente.php`
- `tests/TestCaseMultiTenant.php`
- `tests/Feature/MultiTenantIsolationTest.php`
- `tests/Feature/CrossTenantAttackTest.php`
