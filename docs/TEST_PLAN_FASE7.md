# FASE 7 — QA Multi-Tenant End-to-End

## Setup

### Comercio A — "Almacén Norte"
- ID esperado: 1
- slug: `almacen-norte`
- 1 sucursal: "Casa Central" (branch_id: 1)
- 2 usuarios (cajero, encargado)
- 10 productos (IDs 1-10)
- 5 consumidores (IDs 1-5)

### Comercio B — "Almacén Sur"
- ID esperado: 2
- slug: `almacen-sur`
- 1 sucursal: "Casa Central" (branch_id: 2)
- 2 usuarios (cajero, encargado)
- 10 productos (IDs 11-20)
- 5 consumidores (IDs 6-10)

---

## Módulo 1: Productos

### 1.1 Crear producto

| Test | Acción | Esperado | Resultado |
|------|--------|----------|-----------|
| A crea producto | POST `/productos` (nombre: "Prod A1") | Creado, visible en comercio A | |
| B crea producto | POST `/productos` (nombre: "Prod B1") | Creado, visible en comercio B | |
| A ve producto B | GET `/productos` como usuario A | NO debe aparecer "Prod B1" | |
| B ve producto A | GET `/productos` como usuario B | NO debe aparecer "Prod A1" | |

### 1.2 Editar producto

| Test | Acción | Esperado | Resultado |
|------|--------|----------|-----------|
| A edita producto propio | POST `/productos/{prodA}` (nuevo nombre) | 200, nombre actualizado | |
| A edita producto de B | POST `/productos/{prodB}` | 403 o error, sin cambios en B | |
| B edita producto de A | POST `/productos/{prodA}` | 403 o error, sin cambios en A | |

### 1.3 Desactivar producto

| Test | Acción | Esperado | Resultado |
|------|--------|----------|-----------|
| A desactiva producto propio | PATCH `/productos/{prodA}/status` | status cambiado a false | |
| A desactiva producto de B | PATCH `/productos/{prodB}/status` | 403 o error | |

### 1.4 Stock

| Test | Acción | Esperado | Resultado |
|------|--------|----------|-----------|
| A ajusta stock producto propio | POST `/productos/{prodA}/ajuste-stock` | stock actualizado | |
| A ajusta stock producto de B | POST `/productos/{prodB}/ajuste-stock` | 403 o error | |

### 1.5 Auditoría

| Test | Acción | Esperado | Resultado |
|------|--------|----------|-----------|
| A ve auditoría producto propio | GET `/productos/{prodA}/auditoria` | movimientos visibles | |
| A ve auditoría producto de B | GET `/productos/{prodB}/auditoria` | 403 o error | |

---

## Módulo 2: Ventas

### 2.1 Venta contado

| Test | Acción | Esperado | Resultado |
|------|--------|----------|-----------|
| A vende contado en sucursal A | POST `/ventas` | Venta creada, stock descontado | |
| B vende contado en sucursal B | POST `/ventas` | Venta creada, stock descontado | |
| A ve venta de B | GET `/ventas` | NO debe aparecer venta de B | |

### 2.2 Venta fiado

| Test | Acción | Esperado | Resultado |
|------|--------|----------|-----------|
| A vende fiado a consumidor de A | POST `/ventas` (consumidor_id: A) | Venta creada, deuda registrada | |
| A vende fiado a consumidor de B | POST `/ventas` (consumidor_id: B) | 403 o error | |

### 2.3 Cancelación

| Test | Acción | Esperado | Resultado |
|------|--------|----------|-----------|
| A cancela venta propia | PATCH `/ventas/{ventaA}/cancelar` | Venta cancelada, stock repuesto | |
| A cancela venta de B | PATCH `/ventas/{ventaB}/cancelar` | 403 | |

---

## Módulo 3: Caja

### 3.1 Apertura

| Test | Acción | Esperado | Resultado |
|------|--------|----------|-----------|
| A abre turno en sucursal A | POST `/pos/abrir-turno` | Turno abierto | |
| B abre turno en sucursal B | POST `/pos/abrir-turno` | Turno abierto | |
| A abre turno en sucursal B | POST `/pos/abrir-turno` (otra sucursal) | 403 o error | |

### 3.2 Cierre

| Test | Acción | Esperado | Resultado |
|------|--------|----------|-----------|
| A cierra su turno | POST `api/sesiones-caja/{id}/cerrar` | Turno cerrado | |
| A cierra turno de B | POST `api/sesiones-caja/{idB}/cerrar` | 403 | |

### 3.3 Movimientos

| Test | Acción | Esperado | Resultado |
|------|--------|----------|-----------|
| A ve movimientos de su turno | GET `api/sesiones-caja/{idA}/movimientos` | Movimientos de A | |
| A ve movimientos de turno de B | GET `api/sesiones-caja/{idB}/movimientos` | 403 | |

---

## Módulo 4: Consumidores

### 4.1 Creación

| Test | Acción | Esperado | Resultado |
|------|--------|----------|-----------|
| A crea consumidor | POST `/clientes` | Creado con comercio_id = A | |
| B crea consumidor | POST `/clientes` | Creado con comercio_id = B | |

### 4.2 Deuda (vista)

| Test | Acción | Esperado | Resultado |
|------|--------|----------|-----------|
| A ve consumidores de A | GET `/clientes` | Solo consumidores de A | |
| A ve deuda de consumidor de A | GET `/consumidores/{consA}/cuenta` | OK | |
| A ve deuda de consumidor de B | GET `/consumidores/{consB}/cuenta` | 403 | |

### 4.3 Cobros

| Test | Acción | Esperado | Resultado |
|------|--------|----------|-----------|
| A cobra deuda de consumidor A | POST `/consumidores/{consA}/cobrar` | Deuda reducida, movimiento registrado | |
| A cobra deuda de consumidor B | POST `/consumidores/{consB}/cobrar` | 403 | |

---

## Módulo 5: Pedidos Web

### 5.1 Creación

| Test | Acción | Esperado | Resultado |
|------|--------|----------|-----------|
| Cliente crea pedido en comercio A | POST `/api/pedidos-web` (comercio_id: A) | Pedido creado en A | |
| Cliente crea pedido en comercio B | POST `/api/pedidos-web` (comercio_id: B) | Pedido creado en B | |

### 5.2 Pago MP

| Test | Acción | Esperado | Resultado |
|------|--------|----------|-----------|
| Webhook pago para pedido A | POST `/api/mercadopago/notificacion` (external_ref: pedidoA) | estado_pago → pagado | |
| Webhook pago con external_ref de B pedido A | POST `/api/mercadopago/notificacion` (con token de A, ref: pedidoB) | 404 (PedidoWeb no encontrado para ese comercio) | |

### 5.3 Cambio estado

| Test | Acción | Esperado | Resultado |
|------|--------|----------|-----------|
| A cambia estado pedido de A | PATCH `/pedidos/{idA}/pago` | estado_pago actualizado | |
| A cambia estado pedido de B | PATCH `/pedidos/{idB}/pago` | 404 (scoped por sucursal) | |

---

## Módulo 6: Usuarios

### 6.1 Creación

| Test | Acción | Esperado | Resultado |
|------|--------|----------|-----------|
| A crea usuario en sucursal A | POST `/usuarios` (branch_id: 1) | Creado con comercio_id = A | |
| A crea usuario en sucursal de B | POST `/usuarios` (branch_id: 2) | Error: sucursal no pertenece a tu comercio | |

### 6.2 Edición

| Test | Acción | Esperado | Resultado |
|------|--------|----------|-----------|
| A edita usuario de A | PUT `/usuarios/{userA}` | OK | |
| A edita usuario de B | PUT `/usuarios/{userB}` | 403 | |

### 6.3 Eliminación

| Test | Acción | Esperado | Resultado |
|------|--------|----------|-----------|
| A elimina usuario de A | DELETE `/usuarios/{userA}` | OK | |
| A elimina usuario de B | DELETE `/usuarios/{userB}` | 403 | |

---

## Módulo 7: Transferencias

### 7.1 Sugeridas

| Test | Acción | Esperado | Resultado |
|------|--------|----------|-----------|
| A ve transferencias de A | GET `/transferencias-sugeridas` | Solo transferencias con origen/destino en sucursales de A | |
| A ve transferencia de B | GET `/transferencias-sugeridas` (idB) | 403 | |

### 7.2 Aprobación

| Test | Acción | Esperado | Resultado |
|------|--------|----------|-----------|
| A aprueba transferencia de A | POST `/transferencias-sugeridas/{transA}/aprobar` | OK, stock movido | |
| A aprueba transferencia de B | POST `/transferencias-sugeridas/{transB}/aprobar` | 403 | |

---

## Módulo 8: Suscripciones

### 8.1 Upgrade

| Test | Acción | Esperado | Resultado |
|------|--------|----------|-----------|
| A genera preferencia MP | POST `/mi-plan/pagar` | pending_plan_id seteado en A | |
| Webhook upgrade para A | POST `/api/mercadopago/notificacion?tipo=plan` (external_ref: comercioA) | plan_id actualizado en A | |
| Webhook upgrade con ref de B | POST `/api/mercadopago/notificacion?tipo=plan` (external_ref: comercioB) pero con token de A | 404 o plan no coincide | |

### 8.2 Renovación

| Test | Acción | Esperado | Resultado |
|------|--------|----------|-----------|
| Admin global marca pago de A | POST `/admin-global/facturacion/{comercioA}/pagar` | vencimiento_pago actualizado | |
| Admin global marca pago de B | POST `/admin-global/facturacion/{comercioB}/pagar` | vencimiento_pago actualizado | |

---

## Cross-Tenant Attack Tests

### Intentar acceder a IDs de otro comercio

| # | Endpoint | ID a probar | Esperado | Resultado |
|---|----------|-------------|----------|-----------|
| 1 | GET `/productos/{id}` | producto de B desde sesión A | 403 | |
| 2 | POST `/productos/{id}/ajuste-stock` | producto de B desde sesión A | 403 | |
| 3 | PATCH `/productos/{id}/status` | producto de B desde sesión A | 403 | |
| 4 | PATCH `/ventas/{id}/cancelar` | venta de B desde sesión A | 403 | |
| 5 | POST `/consumidores/{id}/cobrar` | consumidor de B desde sesión A | 403 | |
| 6 | GET `/consumidores/{id}/cuenta` | consumidor de B desde sesión A | 403 | |
| 7 | PUT `/usuarios/{id}` | usuario de B desde sesión A | 403 | |
| 8 | DELETE `/usuarios/{id}` | usuario de B desde sesión A | 403 | |
| 9 | POST `/transferencias-sugeridas/{id}/aprobar` | transferencia de B desde sesión A | 403 | |
| 10 | POST `api/sesiones-caja/{id}/cerrar` | turno de B desde sesión A | 403 | |

---

## Reporte Final

| Módulo | Estado | PASS/FAIL |
|--------|--------|-----------|
| 1. Productos | | |
| 2. Ventas | | |
| 3. Caja | | |
| 4. Consumidores | | |
| 5. Pedidos Web | | |
| 6. Usuarios | | |
| 7. Transferencias | | |
| 8. Suscripciones | | |
| Cross-Tenant | | |

---

## Observaciones

- Completar columna "Resultado" durante la ejecución de pruebas.
- Marcar PASS si el resultado coincide con "Esperado".
- Cross-Tenant: todos deben dar 403 o error controlado.
- Levantar issue por cada FAIL con evidencia (request/response).
