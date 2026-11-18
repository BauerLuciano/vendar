---
name: vendar-business-rules
description: 'Documenta las reglas de negocio verificadas de VendAR para POS, caja, stock, fiados, compras, pedidos web y suscripciones, con los archivos donde viven. Usala antes de tocar ventas, pagos, arqueos, inventario, cuentas corrientes, órdenes de compra o planes, y para distinguir qué es regla del sistema y qué es configurable. Disparadores: regla de negocio, venta, pago, recargo, caja, arqueo, stock, lote, fiado, cuenta corriente, compra, pedido web, suscripción, precio, costo, PPP.'
compatibility: opencode
metadata:
  version: "1.0.0"
  domain: business
  scope: reference
  owner: VendAR
  related-skills: vendar-architecture, vendar-multitenancy, vendar-testing
---

# Reglas de negocio de VendAR

## Cómo usar esta Skill

Esta Skill es una **referencia de comportamiento verificado en el código**, no un diseño ideal. Antes de cambiar una regla:

1. Leé el archivo citado; si el código y esta Skill difieren, manda el código.
2. Si el cambio altera dinero, stock o deuda, tratá la regla como crítica.
3. Si la regla es comercial (recargos, límites, stock negativo), verificá si ya es parametrizable antes de hardcodearla.
4. Si encontrás una regla no documentada acá, agregala con su archivo de origen.

No inventes reglas ni completes con supuestos: si falta información, preguntá.

## Jerarquía de verdad

| Dato | Fuente de verdad |
|---|---|
| Total de una venta | Servidor: `precio_venta × cantidad` de los ítems recibidos, más recargos de tarjeta |
| Saldo de caja | `movimiento_cajas`: `INGRESO − EGRESO`, separado por categoría de método |
| Stock disponible | `producto_sucursal.cantidad_fisica − cantidad_reservada` |
| Detalle por lote | `lotes.stock_actual` + `detalle_venta_lote` |
| Deuda del cliente | `cuentas_corrientes.saldo_deudor` + `movimientos_cuenta_corriente` |
| Estado de suscripción | `SuscripcionService::estadoSuscripcion()` |
| Estados y métodos | Enums `VentaStatus`, `MetodoPago`, `PaymentStatus`, `PaymentChannel` |

El navegador **nunca** es fuente de verdad de montos, stock ni deuda. Puede enviar datos, pero el servidor los recalcula y valida.

## POS y ventas

Archivos: `app/Http/Controllers/VentaController.php`, `app/Services/VentaOperacionService.php`, `app/Models/Venta.php`.

### Cálculo y validación del total

- El total se calcula en el servidor: `Σ (precio_venta × cantidad)` de los ítems enviados.
- La suma de pagos debe coincidir con ese total con tolerancia de `0.01`. Si no coincide, la venta se rechaza.
- El `total` que manda el frontend se valida como numérico, pero **no se usa como verdad**.
- El recargo de tarjeta (`recargo_monto`) se calcula del lado del servidor para pagos con `tipo_tarjeta` `DEBITO` o `CREDITO`, y se suma al monto del pago y al total final.
- Los ítems se ordenan por `id` antes de procesar, para que dos requests iguales sigan el mismo orden de lotes y locks.
- Montos en pesos con 2 decimales; comparaciones con `abs($a - $b) > 0.01`.

### Producto vendible

- El producto debe existir, estar `estado = true` y pertenecer a la sucursal del turno vía `producto_sucursal`.
- `productos` no tiene `comercio_id`: el scope es la sucursal del turno.
- Si `permitir_stock_negativo` (tabla `configuraciones`) está en `false`, se rechaza la venta cuando `cantidad_fisica − cantidad_reservada < cantidad`.
- Si está en `true`, la venta se permite y el movimiento de stock queda marcado `(STOCK NEGATIVO)`.

### Formas de pago y estados

- Una venta con un solo pago guarda ese método; con varios, guarda `MULTIPLE`.
- **Flujo instantáneo** (sin configuración de pasarela para el método): la venta nace `Completada`, impacta caja/cuenta corriente y se encola el ticket digital.
- **Flujo manual** (configuración `channel = manual`): la venta nace `Pendiente`, se registra un `Payment` pendiente y se devuelve `display_info` para mostrar el código/alias.
- **Flujo gateway** (`channel = qr`, `point` o `api`): la venta nace `Pendiente`, se registra el pago pendiente y se llama a la pasarela **fuera** de la transacción de base de datos.
- No se pueden mezclar métodos manuales con métodos electrónicos en la misma venta.
- Si la pasarela falla después de crear la venta, la venta queda pendiente y el mensaje indica cancelarla y reintentar.
- Una venta pendiente se completa con `confirmarPago`: revalida que siga `Pendiente`, impacta caja/cuenta corriente y recién ahí pasa a `Completada`. Si ya no está pendiente, rechaza la operación, así que no duplica efectos.

### Cuenta corriente en la venta

- Requiere `consumidor_id`.
- El consumidor debe existir, estar activo y pertenecer al mismo comercio.
- El monto fiado no puede superar `limite_cuenta_corriente − saldo_deudor`.
- Se bloquea la cuenta con `lockForUpdate` antes de validar el límite.
- El cargo genera `movimientos_cuenta_corriente` con `tipo = cargo`.

### Anulación

`VentaOperacionService::anular()` es la única vía correcta. En una sola transacción:

1. Bloquea la venta y sus lotes.
2. Si ya está `Cancelada`, no hace nada (idempotente).
3. Restaura `lotes.stock_actual` según `detalle_venta_lote`.
4. Restaura `producto_sucursal.cantidad_fisica` y escribe `movimientos_stock` (`Cancelación Venta`).
5. Si estaba `Completada`, revierte caja (`EGRESO`, `ANULACION_VENTA`) o cuenta corriente (`abono`).
6. Cancela los pagos manuales registrados.
7. Marca la venta `Cancelada`, guarda `motivo_anulacion` y setea `cantidad_devuelta = cantidad` en cada detalle.

La anulación exige `motivo` (máx. 255). No modifiques una venta completada por fuera de este service.

### Devolución

`VentaOperacionService::devolver()`:

- Solo sobre ventas `Completada`.
- Cada ítem referencia un `detalle_id` de esa venta y una `cantidad > 0`.
- No se puede devolver más que `cantidad − cantidad_devuelta`.
- Restaura stock por lote y por producto/sucursal, y escribe `movimientos_stock` (`Devolución`).
- Revierte caja o cuenta corriente **en proporción** al monto devuelto sobre el total de pagos.
- No cambia el estado de la venta a cancelada: es una devolución parcial o total con trazabilidad en `cantidad_devuelta`.

## Caja y turnos

Archivos: `app/Http/Controllers/CajaDiariaController.php`, `app/Http/Controllers/CajaController.php`.

### Apertura

- La caja física debe existir, estar activa y pertenecer a una sucursal accesible por el usuario.
- **Una caja no puede tener dos turnos abiertos**: si ya hay uno `Abierto`, responde `409`.
- `saldo_inicial_efectivo` es obligatorio y `>= 0`; `saldo_inicial_mp` es opcional.
- Se crea `TurnoCaja` en estado `Abierto` y se registran movimientos `FONDO_INICIAL` (efectivo y/o Mercado Pago).
- Si efectivo y MP son ambos `0`, igual se registra el fondo inicial en efectivo por `0`.

### Movimientos

- Los movimientos manuales solo se registran en el turno abierto **del propio usuario**; si no tiene turno, se rechaza.
- Tipos: `INGRESO` y `EGRESO`. Conceptos de sistema: `VENTA_MOSTRADOR`, `FONDO_INICIAL`, `ANULACION_VENTA`, `DEVOLUCION`, `COBRO_CUENTA_CORRIENTE`.
- No borres movimientos para “corregir”: generá el movimiento inverso.

### Balance y arqueo

El balance esperado se calcula por categoría:

| Categoría | Métodos |
|---|---|
| Efectivo | `EFECTIVO` |
| Transferencias | `MERCADO_PAGO`, `VIUMI`, `TRANSFERENCIA` |
| Tarjetas | `DEBITO`, `CREDITO` |

`esperado = Σ INGRESO − Σ EGRESO` por categoría. `CUENTA_CORRIENTE` cae en transferencias por el `default` del `match`, pero **no genera movimiento de caja** cuando se usa en una venta: impacta solo la cuenta corriente. Tenelo en cuenta al leer un arqueo.

### Cierre

- Solo un turno `Abierto` se puede cerrar.
- Se guardan los montos reales declarados (`efectivo`, `transferencias`, `tarjetas`) y observaciones.
- El cierre **no rechaza diferencias**: el arqueo se compara después en el PDF y en el cierre diario.
- `saldo_final_mp_real` guarda las transferencias y `saldo_final_transf_real` queda en `0` por compatibilidad.
- `monto_cierre` guarda el efectivo real declarado.

### Cierre diario

- Filtra por rango de fechas de apertura y por comercio.
- Jefes (`SuperAdmin`, `Administrador Global`) ven todo el comercio; el resto, solo sus propios turnos.
- La columna `diferencia` se calcula como `monto_cierre − monto_apertura − facturado`, donde `facturado` suma **todas** las ventas del turno. Es el comportamiento actual del reporte; no lo tomes como fórmula de arqueo por método ni lo repliques en reportes nuevos sin revisarlo.
- Exporta a Excel con los totales de apertura, facturado, cierre y diferencia.

### Cajas físicas

- Nombre único por sucursal (case-insensitive, se normaliza).
- La sucursal debe pertenecer al comercio del usuario.
- Una caja con historial de turnos **no se elimina**: se inactiva (`estado = false`).
- Una caja inactiva no se puede abrir.

## Stock y lotes

Archivos: `app/Http/Controllers/VentaController.php`, `app/Http/Controllers/IngresoMercaderiaController.php`, `app/Http/Controllers/OrdenCompraController.php`, `app/Services/LoteService.php`, `app/Http/Controllers/ProductoController.php`.

### Verdad del stock

- `producto_sucursal.cantidad_fisica`: unidades físicas en la sucursal.
- `producto_sucursal.cantidad_reservada`: unidades comprometidas por pedidos web.
- Disponible para vender = `cantidad_fisica − cantidad_reservada`.
- Los lotes son la apertura FIFO por `fecha_vencimiento` ascendente.
- **Invariante**: si el producto maneja lotes, `SUM(lotes.stock_actual) = producto_sucursal.cantidad_fisica`. Está cubierto por `tests/Feature/Stock/StockInvariantTest.php`.

### Movimientos

Toda variación de stock escribe `movimientos_stock` con:

- `producto_id`, `sucursal_id`, `user_id`
- `tipo_movimiento`: `Venta`, `Cancelación Venta`, `Devolución`, `Ingreso Manual`, `Ingreso OC`, `Reserva Pedido Web`, `Ajuste`, etc.
- `cantidad_anterior`, `cantidad_movimiento` (con signo), `cantidad_actual`
- `motivo` y timestamps

No existe una actualización de stock válida sin su movimiento. Si encontrás una, es un bug.

### Venta y reserva

- La venta descuenta lotes FIFO y luego `cantidad_fisica`.
- La reserva de pedido web **no descuenta** `cantidad_fisica`: incrementa `cantidad_reservada` y deja un movimiento `Reserva Pedido Web` con `cantidad_movimiento = −cantidad` y `cantidad_actual = cantidad_fisica` (el campo refleja el físico, no el disponible).
- La entrega del pedido descuenta la reserva y el físico; la cancelación pre-entrega libera la reserva. El detalle exacto está en la sección “Pedidos web”.

### Ingreso de mercadería

`IngresoMercaderiaController::store()`:

- Valida acceso a la sucursal.
- Crea `ingresos_mercaderia` + `ingreso_detalles`.
- Si el ítem trae vencimiento, hace `LoteService::upsert()` (mismo producto + sucursal + fecha incrementa; si no, crea).
- Actualiza costo con **PPP** (precio promedio ponderado): `(stock_anterior × costo_anterior + cantidad × costo_nuevo) / stock_total`.
- Si el costo nuevo sube, recalcula `precio_venta` con `porcentaje_ganancia` (o el margen implícito actual) y devuelve `alertas_inflacion`.
- Si el PPP cambia, actualiza `productos.precio_costo` y registra `historico_costos` con `origen_tipo = Ingreso Manual`.
- Suma `cantidad_fisica` y escribe `movimientos_stock` (`Ingreso Manual`).
- El costo `0` o vacío se reemplaza por el `precio_costo` actual.

### Orden de compra

Estados: `Sugerida → Enviada → Confirmada → Parcialmente Recibida / Recibida`, más `Cancelada`.

- Solo `Sugerida` se edita, envía o elimina.
- Solo `Enviada` se confirma o rechaza (rechazar vuelve a `Sugerida`).
- No se cancela una orden `Recibida` o `Cancelada`.
- Solo `Confirmada` o `Parcialmente Recibida` se reciben.
- La recepción nunca supera el pendiente (`cantidad_pedida − cantidad_recibida`); si es parcial, queda `Parcialmente Recibida`.
- La recepción usa el mismo PPP, actualiza costo, registra `historico_costos` (`origen_tipo = Recepción OC`), suma stock, escribe `movimientos_stock` (`Ingreso OC`) y crea un `ingreso_mercaderia` con remito `OC-0000`.
- **En la recepción de OC no se recalcula el precio de venta**; solo se actualiza el costo. No agregues ese recálculo sin decisión explícita.

## Fiados y cuenta corriente

Archivos: `app/Http/Controllers/ConsumidorController.php`, `app/Http/Controllers/VentaController.php`, `app/Services/VentaOperacionService.php`.

- La deuda vive en `cuentas_corrientes.saldo_deudor`, una por consumidor.
- Cada cargo y abono genera `movimientos_cuenta_corriente` (`tipo = cargo` / `abono`).
- Venta fiada: valida consumidor activo del mismo comercio y límite disponible; luego incrementa el saldo.
- Cobro de deuda (`cobrarDeuda`):
  - Valida el comercio solo cuando ambos valores existen: si `comercio_id` del usuario y del consumidor están presentes y difieren, `403`. Un consumidor con `comercio_id = null` no se rechaza por esta validación; es una deuda preexistente.
  - Exige al menos un pago con `monto > 0` y métodos distintos (no se repite el mismo método).
  - El total abonado no puede superar `saldo_deudor`.
  - Descuenta el saldo con lock y registra el abono.
  - Si el usuario tiene un turno abierto, registra `INGRESO` con concepto `COBRO_CUENTA_CORRIENTE` por cada pago.
  - Si no tiene turno abierto, **igual cobra la deuda** pero no impacta caja. Es el comportamiento actual: no lo cambies sin decisión.
- Anulación y devolución acreditan la cuenta con `abono` por el monto correspondiente.
- El límite se configura por consumidor (`limite_cuenta_corriente`, `>= 0`, máximo `99.999.999,99`).

## Pedidos web

Archivo: `app/Http/Controllers/PedidoWebController.php`.

- La sucursal debe pertenecer al comercio declarado; si no, `422`.
- El producto debe estar activo y tener registro en `producto_sucursal` de esa sucursal.
- Se valida `cantidad <= cantidad_fisica − cantidad_reservada` con lock.
- Se incrementa `cantidad_reservada` y se registra el movimiento de reserva.
- El precio se toma de `productos.precio_venta`; si hay regla de liquidación activa **y** lotes en liquidación con stock, se aplica el descuento.
- El envío se calcula con `comercio.envio_precio_base + distancia_km × comercio.envio_precio_km`; sin distancia, solo la base.
- El pedido nace `estado_pago = pendiente` y `estado_pedido = nuevo`.
- El consumidor del guard `consumidor` se asocia solo si pertenece al mismo comercio.
- `confirmarPago` solo opera sobre pedidos con pago pendiente y método `mercadopago`; es idempotente por `estado_pago`.
- Transición a `entregado`: descuenta `cantidad_reservada` **y** `cantidad_fisica`, consume lotes FIFO y registra `Entrega Pedido Web` + el detalle en `pedido_web_items_lotes`. Antes exige reserva y stock físico suficientes.
- Cancelación **antes** de entregar: libera `cantidad_reservada` y registra `Liberación Reserva Web`. No toca el stock físico.
- Cancelación **después** de entregar: repone `cantidad_fisica`, restaura lotes desde `pedido_web_items_lotes` y registra `Devolución Pedido Web`.
- Marcar el pago como `reembolsado` libera la reserva si el pedido todavía no fue entregado ni cancelado.
- Las transiciones de estado se validan contra `PedidoWeb::nextStates()`; un jefe puede forzar otras, un usuario común no.

## Suscripciones SaaS

Archivo: `app/Services/Suscripcion/SuscripcionService.php`.

- `UMBRAL_AVISO_DIAS = 10`: con 10 días o menos, el estado es `por_vencer`.
- `VENTANA_RENOVACION_DIAS = 30`: el mismo plan solo se renueva con 30 días o menos restantes. Un upgrade de plan no está limitado por esta ventana.
- `MINUTOS_PAGO_EN_VUELO = 30`: no se genera una segunda preferencia de pago para el mismo comercio dentro de esa ventana.
- Estados: `sin_vencimiento`, `activa`, `por_vencer`, `vencida`.
- El acceso se corta al finalizar el día de vencimiento, no al comenzar.
- Renovación anticipada: el nuevo período empieza en el vencimiento vigente, incluido el día de vencimiento (el acceso dura hasta el fin de ese día). Si ya venció o no hay vencimiento, empieza en la **fecha actual**; la `fecha_pago` sólo se usa para `approved_at` y nunca retrasa el inicio del período.
- Hay dos caminos para extender el vencimiento y ambos usan `calcularPeriodo()` con `lockForUpdate()` sobre el comercio:
  - `PagoManualService::registrar()`: pago manual, idempotente por `idempotency_key` y con anti-duplicado por `period_end` sin importar el proveedor del pago existente. El camino de Mercado Pago no aplica esta misma guarda.
  - `SuscripcionService::aplicarPago()`: pago de Mercado Pago, idempotente por `paymentId`.
- El frontend no debe recalcular el estado: consume `estadoSuscripcion()`.

## Qué es configurable y qué no

Parametrizable (tabla `configuraciones` o por comercio):

- `permitir_stock_negativo`
- Recargos por tarjeta y cuotas (los envía el POS, el servidor recalcula el monto)
- Etiquetas de métodos de pago (`payment_method_configurations`)
- Reglas de liquidación por producto
- Costo de envío (`comercios.envio_precio_base`, `envio_precio_km`)
- Límite de cuenta corriente por consumidor
- Precios, márgenes y alícuotas de IVA

No parametrizable sin decisión de arquitectura:

- Tolerancia de `0.01` en la comparación de pagos.
- Invariante de stock por lotes.
- Una sola sesión abierta por caja.
- Filtro por comercio/sucursal.
- Idempotencia de anulación y de aplicación de pagos.
- Fórmula de PPP.
- Fechas y ventanas de suscripción (son constantes del service).

## Trampas y deudas conocidas

- `Comercio.plan` es una columna string que tapa la relación `plan()`; usá `plan_id`, `planContratado()` o `plan()`. `planContratado()` es la relación inequívoca que usa el código nuevo.
- `configuraciones` es global: no la uses para preferencias por comercio.
- `ventas` y `productos` no tienen `comercio_id`.
- Los roles Spatie son globales (`teams = false`).
- `IngresoMercaderiaController` y `OrdenCompraController` validan `proveedor_id` con `exists`, pero no contra el comercio del usuario; no copies ese patrón en código nuevo.
- La recepción de OC no recalcula precio de venta; el ingreso manual sí.
- El cierre diario calcula la diferencia contra el total facturado sin separar métodos.
- El cobro de fiado sin turno abierto no impacta caja.
- `payments` tiene `period_start`, `period_end`, `payment_method`, `created_by_id` e `idempotency_key` (unique global nullable). En suscripciones, el pago manual es idempotente por `idempotency_key` y el de Mercado Pago por `(provider, gateway_transaction_id)`/`pagoYaRegistrado`. En ventas y pedidos se mantiene la idempotencia por estado y `pagoYaRegistrado`.

## Checklist antes de cambiar una regla

- [ ] ¿Identifiqué el archivo y el método exactos que hoy implementan la regla?
- [ ] ¿Sé si afecta dinero, stock, deuda o solo presentación?
- [ ] ¿La operación sigue dentro de una transacción con los locks necesarios?
- [ ] ¿Se siguen escribiendo los movimientos de trazabilidad?
- [ ] ¿La regla nueva es configurable o realmente debe ser fija?
- [ ] ¿Agregué test de regresión y, si hay endpoint, test cross-tenant?
- [ ] ¿Actualicé el informe de fase en `docs/informes/`?

## Referencias

- `app/Http/Controllers/VentaController.php`
- `app/Services/VentaOperacionService.php`
- `app/Http/Controllers/CajaDiariaController.php`
- `app/Http/Controllers/CajaController.php`
- `app/Http/Controllers/IngresoMercaderiaController.php`
- `app/Http/Controllers/OrdenCompraController.php`
- `app/Http/Controllers/ConsumidorController.php`
- `app/Http/Controllers/PedidoWebController.php`
- `app/Services/LoteService.php`
- `app/Services/Suscripcion/SuscripcionService.php`
- `app/Enums/`
- `tests/Feature/Stock/StockInvariantTest.php`
- `docs/informes/BUILD-B1.md`, `BUILD-B3.md`, `F14-renovacion-mismo-plan.md`
