# BUILD B3 — Auditoría: Administrador Global → Facturación / Control de Cobros

**Fecha:** 2026-11-05  
**Etapa:** 1 de 2 — auditoría read-only  
**Estado:** entregado para revisión. La ETAPA 2 queda bloqueada hasta aprobación explícita.  
**Alcance:** módulo de facturación de suscripciones del Administrador Global (`/admin-global/facturacion`), sus modelos, rutas, permisos, datos, pagos, suspensión, Mercado Pago y auditoría.

> En esta etapa **no se modificó código, base de datos, migraciones ni datos**. Solo se creó este informe.  
> No se ejecutaron la suite completa ni builds, porque no hubo cambios de implementación.

---

## 1. Veredicto ejecutivo

El módulo existe y tiene una pantalla funcional, pero **no está listo para operar cobros reales**. Los problemas principales son de exactitud financiera y de trazabilidad:

| ID | Severidad | Hallazgo | Impacto |
|---|---|---|---|
| B3-A01 | **P0** | La columna `comercios.plan` tapa la relación `plan()`. La pantalla muestra `PRO` y `$0` en vez del plan y precio reales. | Se cobra/muestra un importe incorrecto. |
| B3-A02 | **P0** | `marcarPagado()` no reactiva cuentas suspendidas porque `status` no está en `Comercio::$fillable` y el `update()` lo descarta silenciosamente. | El comercio queda bloqueado aunque el administrador vea “Pagado”. |
| B3-A03 | **P0** | El pago manual siempre fija `vencimiento_pago = now()->addMonth()` y **no prorroga desde el vencimiento vigente**. Si se paga antes del vencimiento, el período se acorta. | Pérdida de días ya pagados por el cliente. |
| B3-A04 | **P0** | El pago manual no crea un registro en `payments`, no guarda importe, método, período, responsable ni referencia y no tiene historial por comercio. | No hay comprobante ni trazabilidad financiera. |
| B3-A05 | **P1** | Los totales excluyen a los comercios suspendidos; un moroso auto-suspendido desaparece de “Pagos Atrasados” y “Locales en Mora”. | Métricas de cobranza engañosas. |
| B3-A06 | **P1** | El día del vencimiento la pantalla lo marca “Vencido” desde las 00:00, mientras el middleware corta recién al final del día. | Discordancias entre estado financiero y acceso real. |
| B3-A07 | **P1** | `dias_restantes` está invertido: fechas futuras dan negativos y fechas pasadas dan positivos. | Dato incorrecto en la API, aunque la UI todavía no lo use. |
| B3-A08 | **P1** | Los botones “Link MP” y “Credenciales MP” son simulados; no generan link ni configuran credenciales. | El administrador cree que tiene una acción que no existe. |
| B3-A09 | **P1** | No hay control de duplicados para pagos manuales; el servidor no bloquea reintentos ni solicitudes concurrentes. | Riesgo de doble registro o de pisar fechas. |
| B3-A10 | **P2** | Hay deriva de esquema y campos legacy: `planes` tiene columnas que el modelo actual no maneja, y `comercios` conserva `estado_cuenta`, `modulos` y `vence_at` sin uso. | Riesgo al migrar o al probar contra otra base. |
| B3-A11 | **P2** | El token de Mercado Pago por comercio puede llegar al frontend en `payment_gateways.configuration.access_token`; el controlador solo enmascara `client_secret`. | Exposición innecesaria de una credencial. |

**Conclusión:** la ETAPA 2 no debería limitarse a “arreglar la pantalla”. Debe corregir el cálculo del período, registrar el pago y hacer que la operación sea idempotente y auditable.

---

## 2. Método y evidencia

Se revisaron, entre otros:

- `app/Http/Controllers/GlobalAdminController.php` → `facturacion()`, `marcarPagado()`, `generarLinkMP()`.
- `resources/js/Pages/AdminGlobal/Facturacion.vue`.
- `app/Models/Comercio.php`, `app/Models/Plan.php`, `app/Models/Payment.php`.
- `app/Services/Suscripcion/SuscripcionService.php`.
- `app/Http/Controllers/SuscripcionController.php`.
- `app/Http/Controllers/MercadoPagoNotificacionController.php`.
- `app/Services/Payment/*` y `app/Services/Payment/Gateways/MercadopagoGateway.php`.
- `app/Http/Middleware/VerificarEstadoCuenta.php` y `VerificarActivacionCuenta.php`.
- `routes/web.php`, `bootstrap/app.php`.
- Migraciones de `payments`, `payment_gateways`, `planes` y `comercios`.
- Esquema real y datos de `vendar` con `psql`.
- Comportamiento real de Eloquent/Carbon con `php artisan tinker`.

Verificaciones empíricas relevantes:

```text
Comercio 1:
  $comercio->plan                  = "pro" (string)
  $comercio->plan?->precio_mensual = null
  $comercio->getRelation('plan')->precio_mensual = "2.00"
  $comercio->getRelation('plan')->nombre = "Plan Profesional"
  mapeo actual de facturación      = plan "PRO", monto 0, estado "Al Día"
  dias_restantes                   = negativo (≈ -49.31)

Comercio 2:
  mapeo actual de facturación      = plan "PRO", monto 0, estado "Al Día"
  dias_restantes                   = negativo (≈ -28.31)
```

```text
$comercio->fill([
  'vencimiento_pago' => '2027-01-01',
  'status' => 'activo',
  'plan_id' => 2,
])->getAttributes()
=> solo ['vencimiento_pago' => '2027-01-01 00:00:00']
```

```text
Carbon::parse('2027-01-31')->addMonth()             => 2027-03-03
Carbon::parse('2027-01-31')->addMonthNoOverflow()   => 2027-02-28
```

> La comparación de fechas se hizo con la zona horaria de la aplicación: `America/Argentina/Buenos_Aires`.

---

## 3. Cómo funciona hoy

### 3.1 Ruta, middleware y permisos

Las rutas están en `routes/web.php:385-405`:

```php
Route::middleware(['auth', 'role:Administrador Global'])
    ->prefix('admin-global')
    ->group(function () {
        Route::get('/facturacion', [GlobalAdminController::class, 'facturacion'])
            ->name('admin.facturacion');
        Route::post('/facturacion/{comercio}/pagar', [GlobalAdminController::class, 'marcarPagado'])
            ->name('admin.facturacion.pagar');
        Route::post('/facturacion/{comercio}/link-mp', [GlobalAdminController::class, 'generarLinkMP'])
            ->name('admin.facturacion.link-mp');
    });
```

- Solo el rol `Administrador Global` puede acceder.
- No hay policies ni comprobaciones adicionales dentro del controlador.
- `VerificarEstadoCuenta` deja pasar al Administrador Global por rol, aunque el usuario tenga `branch_id`.
- `VerificarActivacionCuenta` también lo deja pasar.
- No hay rate limiting específico para estas acciones.

### 3.2 `GET /admin-global/facturacion`

`GlobalAdminController::facturacion()` (`GlobalAdminController.php:147-191`):

1. Trae `id`, `nombre`, `plan` (columna string), `plan_id`, `status`, `vencimiento_pago`.
2. Carga la relación `plan` con `id`, `precio_mensual`, `nombre as plan_nombre`, `slug`.
3. Mapea cada comercio a:
   - `plan`: `$c->plan?->nombre ?? strtoupper($c->plan)`.
   - `slug`: `$c->plan?->slug ?? $c->plan`.
   - `monto`: `$c->plan?->precio_mensual ?? 0`.
   - `vencimiento`: `d/m/Y` o `Sin fecha`.
   - `estado_cobro`: `Al Día`, `Vencido` o `Suspendido`.
   - `dias_restantes`: `Carbon::parse($vencimiento)->diffInDays($hoy, false)`.
4. Suma los estados `Al Día` y `Vencido` para `total_esperado`.
5. Suma solo `Vencido` para `total_vencido` y cuenta solo `Vencido` para `clientes_morosos`.

Problemas de esta implementación:

- Usa la **columna** `plan`, no la relación cargada.
- La relación se seleccionó con alias `nombre as plan_nombre`, pero el código busca `nombre`; el alias no se usa.
- El fallback `strtoupper($c->plan)` convierte `pro` en `PRO`.
- `monto` queda en `0` cuando no hay relación accesible.
- No hay estado “Por Vencer”.
- No hay deuda acumulada ni importe pendiente real: `monto` es el precio mensual actual del plan, no el saldo del comercio.
- No hay paginación ni historial.

### 3.3 `POST /admin-global/facturacion/{comercio}/pagar`

`GlobalAdminController::marcarPagado()` (`GlobalAdminController.php:285-295`):

```php
$request->validate(['fecha' => 'nullable|date']);

$comercio->update([
    'vencimiento_pago' => $request->fecha ?? now()->addMonth(),
    'status' => 'activo',
]);
```

Qué hace realmente:

- Valida únicamente que `fecha`, si viene, sea una fecha.
- Si no viene fecha, usa `now()->addMonth()`.
- Si viene fecha, la usa tal cual, sin comparar contra el vencimiento actual ni contra hoy.
- **No** usa `addMonthNoOverflow()`.
- **No** crea un registro en `payments`.
- **No** registra importe, método, período, referencia ni responsable.
- **No** usa transacción ni bloqueo.
- **No** evita duplicados.
- **No** registra un evento de auditoría específico de pago.
- **No** reactiva la cuenta si estaba suspendida, porque `status` no está en `$fillable` y `update()` lo descarta.

La UI (`Facturacion.vue:38-59`) envía `{}`; no hay formulario de fecha, importe ni método. El texto dice “Se extiende el vencimiento un mes”, pero el backend no extiende desde el vencimiento actual.

### 3.4 `POST /admin-global/facturacion/{comercio}/link-mp`

`GlobalAdminController::generarLinkMP()` (`GlobalAdminController.php:297-303`) devuelve:

```php
response()->json([
    'message' => 'Funcionalidad próxima a implementarse.',
    'preview' => route('admin.facturacion'),
]);
```

No genera un link, no llama a Mercado Pago, no modifica nada. La UI igual muestra un botón activo. Además, el frontend usa `router.post()` de Inertia, pero el backend responde JSON plano; la acción no tiene el contrato de una respuesta Inertia.

El botón “Credenciales MP” (`Facturacion.vue:74-79`) solo abre un `Swal` con “Próximamente”. No tiene ruta ni backend.

### 3.5 Frontend actual

`resources/js/Pages/AdminGlobal/Facturacion.vue` espera:

| Prop | Tipo | Uso |
|---|---|---|
| `comercios[].id` | number | clave y ruta de acción |
| `comercios[].nombre` | string | nombre del comercio |
| `comercios[].plan` | string | se muestra en mayúsculas |
| `comercios[].monto` | number/string | “A Cobrar” |
| `comercios[].vencimiento` | string | fecha ya formateada |
| `comercios[].estado_cobro` | string | badge de estado |
| `comercios[].dias_restantes` | number | **no se usa en la UI actual** |
| `resumen.total_esperado` | number | tarjeta “Proyección del Mes” |
| `resumen.total_vencido` | number | tarjeta “Pagos Atrasados” |
| `resumen.clientes_morosos` | number | tarjeta “Locales en Mora” |

`formatearDinero()` usa `maximumFractionDigits: 0`. Si el precio real tuviera centavos, se redondearía en pantalla.

### 3.6 Suspensión actual

`VerificarEstadoCuenta` (`VerificarEstadoCuenta.php:12-56`):

- Excluye rutas de login, logout, landing, `cuenta.suspendida`, `admin.comercios.*`, impersonación, webhooks, tienda, cliente y las rutas de suscripción `mi-plan`, `pagar`, `confirmar-upgrade` y `plan-actual`.
- Si el usuario tiene rol `Administrador Global`, pasa siempre.
- Resuelve la sucursal activa y su comercio.
- Calcula `vencimiento = Carbon::parse($comercio->vencimiento_pago)->endOfDay()`.
- Si `status === 'suspendido'` o `now() > vencimiento`, redirige a `cuenta.suspendida`.
- Si la fecha pasó pero el estado seguía activo, cambia `status = 'suspendido'` y guarda.

No hay una tarea programada de suspensión: el cambio ocurre **en el request** del usuario. Si el comercio no recibe tráfico, no se auto-suspende.

### 3.7 Mercado Pago real y Mercado Pago simulado

**Lo real (autoservicio del comercio):**

- `SuscripcionController::generarPreferencia()` (`SuscripcionController.php:123-149`) arma un `CheckoutRequest` con el precio del plan y llama a `PaymentService::forPlatform()->createCheckout('mercadopago', ...)`.
- Usa las credenciales de plataforma (`config/services.php` → `MERCADOPAGO_ACCESS_TOKEN`), no las credenciales de `payment_gateways` del comercio.
- `confirmarUpgrade()` verifica el pago contra la API de Mercado Pago antes de aplicar.
- `MercadoPagoNotificacionController::procesarUpgradePlan()` procesa el webhook con `tipo=plan` y aplica `SuscripcionService::aplicarPago()`.

**Lo simulado (Administrador Global):**

- “Link MP” no genera link.
- “Credenciales MP” no configura credenciales.
- No existe una pantalla global real de credenciales de plataforma.
- La pantalla de facturación global no usa Mercado Pago para cobrar.

Riesgo adicional: `MercadopagoGateway::verifyWebhookSignature()` (`MercadopagoGateway.php:156-205`) firma el mensaje como `"{$paymentId}|{$ts}|{$secret}"`. El esquema estándar de Mercado Pago usa un manifest con `id`, `request-id` y `ts`; el implementado no incluye `request-id` y usa separadores propios. Debe revisarse contra la documentación vigente antes de asumir que los webhooks legítimos de producción van a pasar. En el `.env` auditado **no está definido `MERCADOPAGO_WEBHOOK_SECRET`**; en local la verificación se omite, pero en producción su ausencia hace que el webhook responda 401.

### 3.8 Auditoría actual

- `Comercio` usa el trait `Auditable`, que registra cambios con `LogsActivity`, `logOnlyDirty()` y `logAll()`.
- Un cambio de `vencimiento_pago` o `status` hecho con Eloquent queda como un evento genérico `updated` en `activity_log`, con `causer_id` del usuario autenticado.
- No hay un evento dedicado `pago_manual`, `suscripcion_renovada` ni equivalente para el Administrador Global.
- No hay registro de importe, método ni período en ese log.
- En los datos auditados hay eventos genéricos de comercios, pero **no hay filas en `payments`**.
- La auditoría automática no reemplaza un libro de pagos: sirve para ver “qué campo cambió”, no “cuánto se cobró y por qué período”.

### 3.9 Esquema y datos reales auditados

**Comercios (base `vendar`):**

| ID | Nombre | `status` | `plan` (string) | `plan_id` | `vencimiento_pago` |
|---:|---|---|---|---:|---|
| 1 | Kiosco RckOlv | `activo` | `pro` | 2 | 2026-12-25 |
| 2 | Kiosco Rcky | `trial` | `pro` | 2 | 2026-12-04 |

**Planes (base `vendar`):**

| ID | Nombre | Slug | `precio_mensual` | `activo` |
|---:|---|---|---:|---|
| 1 | Plan Básico | `basico` | 1.00 | sí |
| 2 | Plan Profesional | `pro` | 2.00 | sí |
| 3 | Plan Premium | `premium` | 3.00 | sí |

**Contraste con el seeder:**

- `PlanSeeder` declara `8000`, `15000` y `35000`.
- La base auditada tiene `1.00`, `2.00` y `3.00`.
- No se modificaron precios. Es una decisión de negocio pendiente: **no se puede asumir que el precio del seeder es el precio comercial real**.

**Pagos:**

- `payments` existe y tiene estructura polimórfica.
- En `vendar` hay **0 filas**.
- `payments` tiene unique `(provider, gateway_transaction_id)`.
- No tiene `period_start`, `period_end`, `created_by`, `payment_method`, `paid_at` ni `idempotency_key`.
- El unique actual no protege duplicados manuales cuando `gateway_transaction_id` es `NULL`, porque PostgreSQL considera distintos los `NULL`.

**Payment gateways:**

- Hay una fila para el comercio 1 con `provider = mercadopago`, `enabled = false`, `configuration = {"access_token": null}`.
- Las credenciales de plataforma están en `.env` / `config/services.php`, no en `payment_gateways`.

**Deriva de esquema detectada:**

- La base tiene aplicadas migraciones `2026_08_02_000000_add_soft_deletes_to_master_tables` y `2026_08_02_000001_add_trial_activo_to_planes_table`, pero esos archivos no están en el árbol actual de `main`.
- `planes` tiene `trial_dias`, `dias_mora`, `trial_activo` y `deleted_at`; el modelo `Plan` actual no usa `SoftDeletes` y no incluye esos campos en `$fillable`.
- `comercios` conserva `estado_cuenta`, `modulos` y `vence_at`, que no participan de la lógica actual de facturación.
- `comercios.plan` no se sincroniza cuando se cambia `plan_id` desde `GlobalAdminController::update()` o desde `SuscripcionService::aplicarPago()`.
- `comercios.plan_id` es nullable y `comercios.plan` es obligatorio con default `basico`.

---

## 4. Respuestas a los 14 puntos auditados

### 4.1 ¿Existe conflicto entre `plan` y `plan()`?

**Sí. Es la causa principal del importe `$0` y del plan `PRO`.**

- `Comercio` tiene la columna string `plan` y la relación `plan()` (`Comercio.php:81-84`).
- En Eloquent, `$comercio->plan` devuelve el **atributo** de la columna, no la relación.
- La relación cargada se obtiene con `$comercio->getRelation('plan')` o `$comercio->plan()->first()`.
- `facturacion()` usa `$c->plan?->precio_mensual` y `$c->plan?->nombre`; como `$c->plan` es un string, el resultado es `null`.
- Luego cae en `strtoupper($c->plan)` y muestra `PRO`; el monto cae en `0`.

Verificación real:

```text
$c->plan                                  => "pro" (string)
$c->plan?->precio_mensual                 => null
$c->getRelation('plan')->precio_mensual   => "2.00"
```

Además, la consulta de `facturacion()` selecciona `nombre as plan_nombre`, por lo que incluso accediendo correctamente a la relación, `$c->getRelation('plan')->nombre` no estaría disponible con esa selección. El alias no se usa en ningún lado.

**Efectos colaterales:**

- `GlobalAdminController::metricas()` tiene el mismo patrón en `GlobalAdminController.php:129` y también muestra el string de la columna.
- La pantalla `cuenta-suspendida` (`routes/web.php:88`) tiene el mismo patrón: `$comercio->plan?->nombre ?? strtoupper($comercio->plan)`.
- `Comercios/Index.vue` “funciona” porque Inertia serializa la relación como objeto `plan`; no porque el acceso PHP sea correcto.
- `DashboardController` usa `$comercio->plan()->value('nombre')` y evita el conflicto.

**Opciones de corrección para ETAPA 2 (sin ejecutar):**

1. **Mínima, sin migración:** usar `$c->getRelation('plan')` o `$c->plan()->first()` en los controladores; usar `with('plan')` sin alias y leer `nombre` desde la relación.
2. **Estructural, sin migración:** renombrar la relación a `planContratado()` y actualizar los consumos PHP/Vue. La columna `plan` queda como legacy.
3. **Estructural con migración:** renombrar la columna `plan` a `plan_slug` y dejar `plan()` como relación. Es más invasivo y no es necesario para B3.

Recomendación de auditoría: opción 1 para B3, y dejar la opción 2 como deuda técnica planificada. No conviene tocar la columna ni los datos históricos en esta fase.

### 4.2 ¿Cuál es el precio real?

- La fuente de verdad del precio es `planes.precio_mensual`.
- En la base auditada, el Plan Profesional (plan de ambos comercios) vale **`2.00`**, no `15000`.
- El `PlanSeeder` declara `8000/15000/35000`, pero **no está reflejado en la base auditada**.
- La pantalla actual muestra `$0` por el conflicto `plan` vs `plan()`.
- Aun corrigiendo el conflicto, mostraría `$2` (o `$2,00` según formato), no `$15.000`.

Riesgos:

- `monto` es el precio mensual **actual** del plan, no el precio del período que se está cobrando. Si el precio del plan cambia, no hay snapshot histórico.
- `Facturacion.vue` usa `maximumFractionDigits: 0`; se pierden centavos.
- No hay importe pendiente real ni deuda acumulada por meses vencidos. “A Cobrar” es una referencia mensual, no el saldo.
- La decisión de qué precio es el correcto **no es técnica**: hay que confirmar si la base `vendar` tiene precios de prueba o si el seeder quedó desactualizado.

### 4.3 ¿Cómo se calcula el vencimiento?

- `comercios.vencimiento_pago` es una fecha (`date`, nullable).
- No existe `fecha_inicio`, `periodo_desde` ni `periodo_hasta`.
- `facturacion()` compara `Carbon::parse($vencimiento)->isBefore($hoy)`, es decir, contra la hora actual.
- `VerificarEstadoCuenta` compara contra `Carbon::parse($vencimiento)->endOfDay()`.
- Consecuencia: **el mismo día del vencimiento, la pantalla lo marca “Vencido” desde las 00:00, pero el comercio sigue operando hasta el final del día.**
- `dias_restantes` usa `$vencimiento->diffInDays($hoy, false)`:
  - vencimiento futuro → negativo;
  - vencimiento pasado → positivo;
  - el día del vencimiento → un valor fraccionario positivo, aunque la pantalla lo marque “Vencido”.
- La UI actual no muestra `dias_restantes`, pero el dato es incorrecto para cualquier consumidor futuro.

Criterio correcto que ya existe en el sistema: `SuscripcionService::estadoSuscripcion()` usa `now()->startOfDay()->diffInDays($vencimiento->startOfDay(), false)` y define:
- `vencida` si `< 0`;
- `por_vencer` si `0..10`;
- `activa` si `> 10`;
- suspensión como booleano separado.

Para ETAPA 2, la pantalla global debería usar el mismo criterio, sin cambiar las reglas de negocio: `Al Día`, `Por Vencer` (umbral existente de 10 días), `Vencido` y `Suspendido`.

### 4.4 ¿Qué hace exactamente el pago manual?

Hoy hace solo esto:

```php
$comercio->update([
    'vencimiento_pago' => $request->fecha ?? now()->addMonth(),
    'status' => 'activo',
]);
```

No hay:

- registro en `payments`;
- importe;
- método de pago;
- período;
- referencia/comprobante;
- responsable;
- fecha de pago;
- transacción;
- bloqueo de concurrencia;
- idempotencia;
- evento de auditoría dedicado;
- validación de que la fecha no acorte el período.

Problemas concretos:

1. **No reactiva suspendidos.** `status` no está en `$fillable` (`Comercio.php:15-32`). El `update()` descarta `status`; se verificó con `fill()`, que solo conserva `vencimiento_pago`.
2. **Acorta períodos.** Si el comercio vence el 25/12 y el administrador marca pagado el 05/11, el resultado es 05/12, no 25/12 + 1 mes. El cliente pierde 20 días.
3. **No usa `addMonthNoOverflow()`.** El 31/01 + `addMonth()` da 03/03, no 28/02.
4. **Acepta cualquier fecha.** Puede fijar una fecha pasada (suspendiendo de inmediato), una fecha lejana o una fecha anterior al vencimiento actual.
5. **No deja rastro financiero.** No se puede responder “¿cuánto pagó, cuándo, cómo y por qué período?”.
6. **La UI no pide datos.** Envía `{}` y solo muestra una confirmación.

### 4.5 ¿Hay historial de pagos?

**No hay historial funcional de pagos de suscripción.**

- `Comercio::payments()` existe (`Comercio.php:61-64`) y `payments` es polimórfica, pero no hay controlador, ruta ni pantalla que la use para suscripciones.
- `Facturacion.vue` no recibe historial.
- `payments` no tiene filas en `vendar`.
- `activity_log` puede mostrar un evento `updated` del comercio, pero no es un libro de pagos: no tiene importe, método, período ni referencia.
- El pago manual histórico no se puede reconstruir con exactitud.

La tabla `payments` sí es reutilizable: ya se usa para ventas, pedidos web y pagos de suscripción por Mercado Pago. No hace falta crear una tabla nueva solo para B3.

### 4.6 ¿Se pueden duplicar pagos?

**En el flujo manual actual no hay protección.**

- `marcarPagado()` no tiene transacción, bloqueo ni clave de idempotencia.
- No hay restricción única para pagos manuales.
- La única restricción de `payments` es `(provider, gateway_transaction_id)`, pensada para Mercado Pago; con `gateway_transaction_id = NULL` no bloquea duplicados.
- Dos clics o dos requests concurrentes pueden ejecutar el mismo `update()`; el resultado final puede ser el mismo si no hay fecha personalizada, pero no hay evidencia de un único pago.
- Si se envía una fecha personalizada, dos requests pueden pisarse la fecha.
- El flujo de autoservicio sí tiene protecciones: `pagoEnVuelo` (30 minutos), `pagoYaRegistrado()` y `lockForUpdate()` dentro de `aplicarPago()`. Esas protecciones **no cubren** al Administrador Global.

### 4.7 ¿Qué período queda asociado al pago?

Hoy **ninguno**.

- No hay `period_start` / `period_end` en `payments`.
- El pago manual no crea fila, así que no hay período.
- El pago de Mercado Pago guarda en `metadata` solo `tipo`, `plan_id` e `intento` (`SuscripcionService::registrarPago()`, líneas 396-418).
- El `reference` del pago MP es el ID del comercio, no el período.
- `vencimiento_pago` es solo la fecha de corte; no alcanza para saber qué mes se cobró.

Para B3 hay que definir y persistir el período. Una propuesta compatible con el sistema actual:

- Si el vencimiento vigente es futuro: `base = vencimiento vigente`; `nuevo_vencimiento = base + 1 mes`.
- Si el vencimiento está vencido o no existe: `base = hoy`; `nuevo_vencimiento = hoy + 1 mes`.
- `period_start = base` y `period_end = nuevo_vencimiento` (o `base + 1 día` si el negocio define el período como “mes siguiente”; hay que decidirlo).
- Se debe usar `addMonthNoOverflow()`.

### 4.8 ¿Qué pasa si se paga antes o después del vencimiento?

**Autoservicio (Mercado Pago), comportamiento existente y correcto:**

- `SuscripcionService::nuevaFechaVencimiento()` (`SuscripcionService.php:169-176`):
  - si el vencimiento es futuro, prorroga desde ese vencimiento;
  - si ya venció, arranca desde hoy;
  - usa `addMonthNoOverflow()`.
- `puedeRenovarAhora()` limita la renovación anticipada del mismo plan a 30 días; el cambio de plan no tiene esa restricción.
- `aplicarPago()` es transaccional, usa `lockForUpdate()` y es idempotente por `paymentId`.

**Administrador Global, comportamiento actual:**

- Siempre usa `now()->addMonth()`, sin importar el vencimiento actual.
- **Antes del vencimiento:** acorta el período. Ejemplo: vence 25/12, paga 05/11 → queda 05/12.
- **Después del vencimiento:** el resultado coincide aproximadamente con “un mes desde hoy”, pero no reactiva el `status` por el problema de `$fillable`.
- **Fecha personalizada:** puede dejar el período más corto, más largo o en el pasado, sin validación.
- **No aplica** la ventana de 30 días (correcto según F14: la facturación global está excluida), pero tampoco aplica ninguna otra regla.

Para ETAPA 2 se debe reutilizar la fórmula de `SuscripcionService` para el cálculo de la fecha, sin copiar una fórmula nueva. No se debe modificar la duración de los períodos ni la ventana de renovación del autoservicio.

### 4.9 ¿Cómo se comportan los suspendidos?

- `status` puede ser `activo`, `trial` o `suspendido` (check constraint en PostgreSQL).
- La suspensión se aplica en cada request de un usuario de comercio:
  - si `status === 'suspendido'`; o
  - si `now() > vencimiento_pago->endOfDay()`.
- Si la fecha pasó y el estado era `activo`, el middleware lo cambia a `suspendido` y guarda.
- No hay job/cron de suspensión.
- El Administrador Global está exento.
- Las rutas `suscripcion.mi-plan`, `pagar`, `confirmar-upgrade` y `plan-actual` están exentas: el suspendido puede ver su plan y pagar.
- `suscripcion.cancelar-pago` **no** está exenta; si la cuenta está suspendida, el middleware puede redirigir antes de llegar al controlador. Es un detalle del flujo de pago en vuelo que conviene revisar en B3 o derivar.
- El pago por Mercado Pago reactiva la cuenta porque `SuscripcionService::aplicarPago()` asigna `status = 'activo'` directamente.
- El pago manual **no** la reactiva por el problema de `$fillable`.

**Impacto en las métricas:**

- `total_esperado` incluye solo `Al Día` y `Vencido`: excluye `Suspendido`.
- `total_vencido` y `clientes_morosos` cuentan solo `Vencido`: excluyen `Suspendido`.
- Un comercio vencido y auto-suspendido desaparece de “Pagos Atrasados” y “Locales en Mora”, aunque siga debiendo.
- Un comercio suspendido manualmente con vencimiento futuro tampoco suma a la proyección.
- El resultado depende de si el comercio hizo o no un request: si no lo hizo, puede seguir `activo` y aparecer como `Vencido`; si lo hizo, pasa a `Suspendido` y deja de contarse.

### 4.10 ¿Qué tipo de suspensión existe?

**No existe un tipo de suspensión persistido.**

- Solo hay `status = 'suspendido'`.
- No hay `suspendido_por`, `motivo`, `suspendido_at` ni `suspension_tipo`.
- La suspensión manual (desde el formulario de comercios del Administrador Global) y la automática (middleware por vencimiento) usan exactamente el mismo valor.
- `cuenta-suspendida` intenta inferir “por vencimiento” mirando si la fecha es pasada (`routes/web.php:83`), pero eso no distingue una suspensión manual con deuda de una automática.
- El `activity_log` registra `status` con `causer_id` del usuario que disparó el request, incluso si la suspensión fue automática por middleware. No es una fuente confiable para distinguir “sistema” de “administrador”.

Para B3 se puede:
- **Mínimo:** mostrar solo “Suspendido” y no afirmar la causa.
- **Robusto:** agregar metadatos de suspensión (tipo, motivo, fecha) y registrarlos desde el middleware y desde el formulario de administración. Esto no cambia la regla de suspensión, solo su trazabilidad. Requiere decisión y migración.

### 4.11 ¿Qué datos espera el frontend?

Exactamente los de la tabla de la sección 3.5. No recibe:

- `plan_id`;
- precio real separado del plan;
- `fecha_inicio`;
- `periodo_desde` / `periodo_hasta`;
- importe pendiente o deuda acumulada;
- historial de pagos;
- último pago;
- método;
- responsable;
- referencia;
- fecha de pago;
- `suspendido_por` / motivo;
- `puede_renovar`.

Además:

- El badge `badgeEstado()` tiene casos para `Al Día` y `Vencido`; cualquier otro estado cae en el color rosa. Si se agrega `Por Vencer` solo en backend, se verá como si fuera suspendido/moroso.
- Los botones “Link MP” y “Pagó” están siempre activos, incluso para un comercio ya al día o suspendido.
- No hay estado de carga ni deshabilitado por request en curso.

### 4.12 ¿Mercado Pago está realmente integrado en esta pantalla?

**No. La pantalla global tiene Mercado Pago simulado.**

- `Link MP` devuelve un JSON de “próximamente”.
- `Credenciales MP` es solo un `Swal`.
- No hay creación de preferencia desde la pantalla global.
- No hay link de pago por comercio.
- No hay conciliación de pagos manuales con MP.

**Sí existe integración real en el autoservicio del comercio:**

- `/mi-plan/pagar` crea una preferencia en Mercado Pago.
- `/retorno` y el webhook `tipo=plan` procesan el pago.
- `SuscripcionService::aplicarPago()` es la única fuente de verdad para aplicar el pago, extender el vencimiento y reactivar.

Esa integración real **no es la que usa el Administrador Global** y no debe modificarse en B3 sin autorización. La ETAPA 2 debería ocultar o deshabilitar los botones simulados, no “hacerlos pasar” por reales.

Riesgos de MP a documentar:

- `MERCADOPAGO_WEBHOOK_SECRET` no está en el `.env` auditado; en producción, sin secreto, el webhook se rechaza.
- La firma HMAC implementada no coincide con el manifest estándar de MP; hay que validarla contra la documentación vigente.
- La ruta de retorno no es una fuente de verdad: la confirmación real se hace contra la API y el webhook.

### 4.13 ¿Las credenciales están protegidas?

**Parcialmente.**

- Las credenciales de plataforma de Mercado Pago están en `.env` / `config/services.php`; no se envían a la pantalla global.
- `Comercio` oculta `mp_access_token` y `payway_public_key` (`Comercio.php:34-37`).
- La pantalla global no muestra ni permite editar credenciales (el botón es falso).
- Pero `ConfiguracionController::index()` carga `paymentGateways` y solo enmascara `configuration.client_secret`; **no enmascara `configuration.access_token`**. Ese JSON se serializa a Inertia y puede llegar al navegador del comercio.
- `payment_gateways` guarda credenciales en texto plano en JSON; no hay cifrado a nivel de modelo.
- El acceso a `/configuracion` está restringido a `SuperAdmin|Administrador Global`, pero sigue siendo una exposición innecesaria del token al frontend.
- La ruta global de facturación no expone las credenciales de plataforma, pero tampoco tiene una pantalla real para gestionarlas.

Para B3: no agregar nuevas exposiciones. Como corrección puntual y de bajo riesgo, enmascarar `access_token` en `ConfiguracionController` (mismo tratamiento que `client_secret`) o no serializar `configuration` completa. Esto es adyacente al módulo, pero es un riesgo de seguridad real.

### 4.14 ¿Las acciones están protegidas y auditadas?

**Protección de acceso:**

- Las rutas globales usan `auth` + `role:Administrador Global`; es la barrera principal.
- No hay policies específicas; el Administrador Global puede operar sobre cualquier comercio por diseño.
- No hay validación de “este comercio pertenece a...”: el alcance es plataforma completa.
- Las acciones son POST dentro del middleware web, por lo que tienen CSRF.
- No hay rate limiting ni confirmación server-side más allá de la validación mínima.
- El flujo de autoservicio (`/mi-plan`) está dentro de un grupo más amplio (`SuperAdmin|Administrador Global|Encargado`), no exclusivo de `SuperAdmin`. Un Encargado del comercio puede ver el plan y generar/cancelar pagos. No hay fuga entre comercios porque el controlador usa el `comercio_id` del usuario, pero es una decisión de permisos que conviene confirmar.

**Auditoría:**

- El cambio de comercio queda en `activity_log` por `Auditable`, como evento genérico `updated`.
- No hay un evento dedicado de pago manual ni un registro en `payments`.
- No se registra importe, método, período, referencia ni responsable en el pago.
- No hay tests de `facturacion`, `marcarPagado` ni `generarLinkMP`.

Para ETAPA 2, además del gate por rol, la operación de pago debe registrar explícitamente:
- comercio;
- usuario responsable;
- fecha/hora;
- importe;
- método;
- período;
- referencia;
- estado;
- y un evento de actividad dedicado.

---

## 5. Plan propuesto para ETAPA 2 (no ejecutado)

Este plan queda sujeto a aprobación. No se implementó nada.

### Paso 1 — Corregir la lectura del plan y el precio

- En `facturacion()` usar la relación cargada (`getRelation('plan')`) o `plan()->first()`, nunca `$c->plan` como si fuera un objeto.
- Corregir también `metricas()` y `cuenta-suspendida` si se decide resolver el conflicto de forma global.
- Evitar el fallback silencioso a `PRO`/`$0`: si no hay plan, mostrar “Sin plan” y no inventar un importe.
- Mantener la fuente de precio en `planes.precio_mensual`.
- Decidir si el monto es el precio mensual actual o si se debe mostrar además el saldo/deuda.

### Paso 2 — Unificar el cálculo de estado y días restantes

- Reutilizar el criterio de `SuscripcionService::estadoSuscripcion()` y `UMBRAL_AVISO_DIAS = 10`.
- Mapear a la UI:
  - `Al Día` (`activa`);
  - `Por Vencer` (`por_vencer`, 0..10 días);
  - `Vencido` (`vencida`, `< 0`);
  - `Suspendido` (precedencia sobre los anteriores).
- Corregir `dias_restantes` para que sea positivo en el futuro y negativo en el pasado.
- Alinear el día del vencimiento con el middleware (`endOfDay`): el día del vencimiento no debería figurar “Vencido” a las 00:00.
- Agregar el estilo visual de `Por Vencer` en `badgeEstado()` (ámbar/naranja), sin reutilizar el rojo de suspendido.

### Paso 3 — Implementar un pago manual completo

Crear un servicio transaccional (por ejemplo `SuscripcionService::registrarPagoManual()` o un `PagoManualService`) que:

1. Reciba comercio, plan, usuario responsable, importe, método, referencia y fecha de pago.
2. Ejecute `DB::transaction()`.
3. Bloquee el comercio con `lockForUpdate()`.
4. Calcule el nuevo vencimiento con la misma fórmula de `SuscripcionService::nuevaFechaVencimiento()`:
   - vencimiento futuro → extender desde ese vencimiento;
   - vencido o inexistente → extender desde hoy;
   - siempre `addMonthNoOverflow()`.
5. Actualice `status = 'activo'` con asignación explícita, no con `update()` masivo (por el `$fillable` actual).
6. Cree el registro en `payments` con:
   - `payable_type = Comercio::class`, `payable_id`;
   - `provider` / `channel = manual`;
   - `status = approved`;
   - `amount`, `currency = ARS`;
   - `reference` (comprobante/transferencia);
   - `approved_at` (fecha de pago);
   - `metadata` con `tipo = suscripcion`, `plan_id`, período y datos de auditoría.
7. Registre un evento de actividad dedicado, por ejemplo `suscripcion_pago_manual`, con propiedades de importe, método, período y responsable.
8. Devuelva un resultado idempotente.

Reglas de negocio a respetar:

- No acortar un período futuro.
- No aceptar una fecha personalizada anterior al vencimiento actual si eso acorta el período.
- No cambiar la duración mensual ni la ventana de renovación del autoservicio.
- No cambiar precios, prueba gratuita, reglas de suspensión ni suscripciones existentes.
- Si se permite una fecha personalizada, validarla contra el cálculo y derivar correcciones excepcionales a una acción separada y auditada.

### Paso 4 — Historial por comercio

- Exponer el historial desde `Comercio::payments()`.
- Agregar una ruta y una sección en `Facturacion.vue` (o un modal/expandible) con:
  - fecha de pago;
  - importe;
  - método;
  - período;
  - referencia;
  - responsable;
  - estado.
- Mostrar el último pago en la fila del comercio.
- No mezclar pagos de ventas/pedidos: filtrar por `payable_type = Comercio::class` y `metadata.tipo = suscripcion`.

### Paso 5 — Anti-duplicados

- Deshabilitar el botón mientras hay un request en curso.
- Backend: bloquear por comercio en transacción.
- Definir una clave de idempotencia por operación.
- Agregar restricción única (por ejemplo `idempotency_key`) o una unique parcial sobre comercio + período + estado aprobado.
- Si el mismo período ya está pago, devolver un error claro (409/422) y no crear otra fila.
- Si se permiten pagos parciales, definir primero la regla; el esquema actual no la soporta.

### Paso 6 — Ocultar/deshabilitar Mercado Pago simulado

- Quitar o deshabilitar “Link MP” y “Credenciales MP” de la pantalla global.
- No devolver un falso “éxito” desde `generarLinkMP`.
- No implementar integración real de MP en B3 salvo pedido explícito.
- Mantener intacto el flujo real de autoservicio (`/mi-plan/pagar`, `/retorno`, webhook).

### Paso 7 — Auditoría y permisos

- Mantener `role:Administrador Global` para las rutas globales.
- Agregar tests de acceso para `SuperAdmin`, `Encargado`, `Cajero`, `cliente` y visitante.
- Registrar el evento dedicado de pago manual.
- No confiar en el `activity_log` genérico como libro de pagos.
- Evaluar si `Encargado` debe seguir accediendo a `/mi-plan`; no cambiarlo sin autorización.

### Paso 8 — Tests B3

Crear `tests/Feature/BuildB3_FacturacionTest.php` (o equivalente) con casos específicos. No modificar tests existentes para ocultar fallos.

---

## 6. Migraciones potenciales

No se aplicó ninguna migración en esta auditoría. Antes de una migración de B3 se debe:

1. Hacer backup de `vendar` y `testing` con `pg_dump -Fc`.
2. Revisar `\d payments`, `\d comercios`, `\d planes` y `migrate:status`.
3. Revisar filas existentes y posibles duplicados.
4. Aplicar primero en `testing` y verificar rollback.
5. No hacer cambios destructivos sobre `comercios` ni `planes`.

### Opción recomendada: reutilizar `payments` con columnas nuevas

La tabla ya es polimórfica y ya se usa para suscripciones por Mercado Pago. Agregar columnas nullable sería no destructivo:

| Columna propuesta | Tipo | Uso |
|---|---|---|
| `period_start` | `date`, nullable | inicio del período abonado |
| `period_end` | `date`, nullable | fin del período / nuevo vencimiento |
| `payment_method` | `string(30)`, nullable | efectivo, transferencia, etc. |
| `created_by_id` | FK `users`, nullable | responsable del registro |
| `idempotency_key` | `string`, nullable, unique | evitar doble pago |

Observaciones:

- `approved_at` ya existe y puede usarse como fecha de pago; `created_at` queda como fecha de creación del registro.
- `reference` ya existe para comprobante/transferencia.
- `amount` y `currency` ya existen.
- `provider` puede usarse para `manual` o el proveedor real; `channel` ya tiene el valor `manual`.
- No hace falta una tabla nueva solo para B3.
- La unique `(provider, gateway_transaction_id)` existente no debe eliminarse.
- La nueva unique debe evaluarse según la regla de negocio: si se permiten pagos parciales, no se puede unique por período sin una clave adicional.
- `created_by_id` con `nullOnDelete` conserva el pago si se elimina el usuario; igualmente conviene guardar el nombre en `metadata`.

### Opción mínima sin migración

Se podría guardar período y método en `payments.metadata` y usar `reference` como comprobante. Es más rápido, pero:

- no hay constraint única simple;
- las consultas por período requieren JSON;
- el historial es más frágil;
- no hay FK de responsable.

Para un sistema que maneja dinero real, la opción con columnas explícitas es más defendible.

### Migración no recomendada en B3

- Renombrar `comercios.plan` / `plan_id`.
- Modificar precios o `PlanSeeder`.
- Cambiar la duración de períodos.
- Cambiar la regla de suspensión.
- Tocar tablas fiscales, POS, caja, stock, compras, productos, clientes o pedidos web.

---

## 7. Decisiones de negocio pendientes

Antes de implementar, hay que confirmar:

1. **Precio vigente:** ¿la base auditada (`1/2/3`) es de prueba o el seeder (`8000/15000/35000`) es el precio comercial? ¿Se debe corregir la base antes de mostrar cobros?
2. **Importe manual:** ¿el pago manual debe ser siempre el precio mensual completo del plan, o se permiten importes parciales/ajustes? Si se permiten parciales, ¿cómo se prorratea el período?
3. **Período:** ¿el período se registra como `[vencimiento anterior, nuevo vencimiento]` o como `[vencimiento anterior + 1 día, nuevo vencimiento]`?
4. **Fecha personalizada:** ¿el administrador puede fijar una fecha de pago/vencimiento distinta? ¿Se permite corregir un período ya pagado? ¿Con qué auditoría?
5. **Pagos atrasados:** si un comercio debe dos meses y paga uno, ¿se extiende un mes desde hoy (comportamiento actual de `SuscripcionService`) o se cobra la deuda completa? No cambiar sin decisión.
6. **Duplicados:** ¿un mismo comercio puede tener dos pagos para el mismo período (por ejemplo, parciales) o debe rechazarse el segundo?
7. **Suspensión:** ¿alcanza con mostrar “Suspendido”, o se necesita distinguir automática/manual con campos nuevos?
8. **Trial:** `status = trial` no se trata de forma especial en la facturación; solo importa `vencimiento_pago`. ¿Se mantiene así?
9. **Roles:** ¿el Encargado debe seguir pudiendo acceder a `/mi-plan` y generar pagos? Hoy el grupo de rutas lo permite.
10. **Mercado Pago:** ¿B3 solo oculta los botones simulados, o se espera una integración real de link de pago? La instrucción actual dice ocultar/deshabilitar, no integrar.
11. **Credenciales:** ¿se corrige la exposición de `payment_gateways.configuration.access_token` en esta fase o se deriva a un B4 de seguridad?

---

## 8. Tests B3 propuestos

No se crearon tests en esta etapa. En ETAPA 2, ejecutar primero solo los B3 y avisar antes de correr la suite completa o builds.

Casos mínimos:

1. `facturacion` muestra el nombre y precio reales del plan, no la columna string.
2. Comercio sin `plan_id` o sin relación: no muestra `$0` silencioso; muestra un estado explícito.
3. `dias_restantes` es positivo para fechas futuras, cero para hoy y negativo para vencidas.
4. Estado `Al Día` con vencimiento > 10 días.
5. Estado `Por Vencer` con 10, 5, 1 y 0 días.
6. Estado `Vencido` con -1 día.
7. `Suspendido` tiene precedencia sobre cualquier estado de fecha.
8. Totales: incluyen/excluyen suspendidos según la regla aprobada; no pierden morosos.
9. Pago manual con vencimiento futuro: extiende desde el vencimiento, no desde hoy.
10. Pago manual vencido: extiende desde hoy.
11. Pago manual el día del vencimiento: no acorta.
12. Pago manual en 31/01: usa `addMonthNoOverflow()` (28/02 o 29/02).
13. Pago manual reactiva un comercio suspendido.
14. Pago manual crea un `Payment` con importe, método, período, responsable y referencia.
15. Pago manual registra el evento de auditoría dedicado.
16. Pago manual con fecha pasada/que acorta: rechazado, sin cambios.
17. Doble request concurrente/duplicado: una sola fila, respuesta idempotente.
18. Historial por comercio: solo muestra pagos de suscripción, no ventas/pedidos.
19. Historial: usuario no autorizado recibe 403.
20. Rutas globales: `Administrador Global` puede; `SuperAdmin`, `Encargado`, `Cajero`, `cliente` y visitante no.
21. El botón “Link MP”/“Credenciales MP” ya no está disponible o está deshabilitado.
22. No se modificaron tests preexistentes.
23. Migración (si se aprueba): se aplica en `testing`, se prueba rollback y no toca datos existentes.

---

## 9. Archivos que se tocarían en ETAPA 2

Posibles, según el alcance aprobado:

- `app/Http/Controllers/GlobalAdminController.php`
  - `facturacion()`;
  - `marcarPagado()`;
  - `generarLinkMP()` (eliminar/deshabilitar).
- `resources/js/Pages/AdminGlobal/Facturacion.vue`
  - datos, estados, formulario de pago, historial, botones MP.
- `app/Models/Comercio.php`
  - solo si se decide corregir la relación/atributo o el `$fillable`; evitar cambios amplios.
- `app/Models/Payment.php`
  - nuevas columnas en `$fillable`/casts si se migra.
- `app/Services/Suscripcion/SuscripcionService.php`
  - reutilización de `nuevaFechaVencimiento()` y nuevo método de pago manual.
- `routes/web.php`
  - ruta de historial y/o ajuste de rutas simuladas.
- `database/migrations/`
  - solo si se aprueba la migración de `payments`.
- `tests/Feature/BuildB3_FacturacionTest.php`
  - tests nuevos.
- `docs/informes/BUILD-B3.md`
  - informe final de implementación.
- Opcional/adyacente: `app/Http/Controllers/ConfiguracionController.php` para enmascarar `access_token`.

No se deben tocar en B3:

- duración de períodos;
- precios (sin decisión);
- prueba gratuita;
- reglas de suspensión;
- suscripciones existentes;
- integración real de Mercado Pago (salvo ocultar/deshabilitar lo simulado);
- facturación electrónica;
- POS, caja, compras, productos, stock, lotes, clientes o pedidos web.

---

## 10. Criterios de aceptación de ETAPA 2

- [ ] El plan y el precio mostrados salen de la relación `plan()` y de `planes.precio_mensual`, nunca de la columna string.
- [ ] No se muestra `$0` por un fallo de relación; si no hay plan, se informa explícitamente.
- [ ] La UI muestra `Al Día`, `Por Vencer`, `Vencido` y `Suspendido` con el criterio existente de 10 días.
- [ ] `dias_restantes` es correcto y el día del vencimiento no se marca “Vencido” antes de tiempo.
- [ ] El pago manual registra importe, método, período, referencia, responsable y fecha.
- [ ] El pago manual no acorta un período futuro y usa `addMonthNoOverflow()`.
- [ ] El pago manual reactiva una cuenta suspendida.
- [ ] El pago manual es idempotente y no duplica el mismo período.
- [ ] Hay historial por comercio, separado de ventas y pedidos.
- [ ] Los botones MP simulados están ocultos o deshabilitados; no hay falsos positivos.
- [ ] Las operaciones quedan auditadas con un evento dedicado.
- [ ] Solo `Administrador Global` accede a las rutas globales.
- [ ] Los tests B3 pasan; no se modificaron tests preexistentes para ocultar fallos.
- [ ] No se tocaron precios, períodos, trial, suspensión, MP real ni módulos ajenos.
- [ ] Si hubo migración: backup, revisión de datos, aplicación en `testing`, rollback verificado y migración no destructiva.
- [ ] Se creó `docs/informes/BUILD-B3.md` al finalizar.

---

## 11. Conclusión y bloqueo

La pantalla actual no es un módulo de cobros: es una vista de consulta con un botón que mueve una fecha. Para convertirlo en un módulo confiable hay que resolver, como mínimo:

1. La colisión `plan` / `plan()`.
2. El precio real y su formato.
3. El cálculo de estados y días.
4. El pago manual transaccional, sin acortar períodos y con reactivación real.
5. El registro en `payments`, el período y el historial.
6. La idempotencia y el control de duplicados.
7. La auditoría dedicada y los permisos.
8. La eliminación de las acciones MP simuladas.
9. La decisión sobre precios y reglas de negocio pendientes.

**No se implementó nada de esto.** La ETAPA 2 queda detenida hasta que se apruebe este diagnóstico y se respondan las decisiones de negocio de la sección 7, en especial la del precio vigente y la del alcance del pago manual.
