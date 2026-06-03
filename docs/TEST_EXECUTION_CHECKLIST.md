# FASE 7 — Test Execution Checklist

## Setup Requirements

### Prerequisites
- [ ] Base de datos PostgreSQL corriendo
- [ ] Migraciones ejecutadas (`php artisan migrate:fresh`)
- [ ] Seeders ejecutados (`php artisan db:seed`)
- [ ] `.env` con `APP_ENV=local`, `APP_DEBUG=true`

### Dependencias faltantes (NO existen en seeders actuales)

| Dependencia | Para qué prueba | Bloqueante |
|---|---|---|
| Comercio A (ID=1) | Setup base | **SÍ** |
| Comercio B (ID=2) | Setup base, cross-tenant | **SÍ** |
| Sucursal A (branch_id=1) con comercio_id=1 | Setup base | **SÍ** |
| Sucursal B (branch_id=2) con comercio_id=2 | Tests B, cross-tenant | **SÍ** |
| Usuario A con branch_id=1, comercio_id=1 | Sesión A | **SÍ** |
| Usuario B con branch_id=2, comercio_id=2 | Sesión B | **SÍ** |
| 10 productos vinculados a sucursal A (IDs 1-10) | Módulo 1 | **SÍ** |
| 10 productos vinculados a sucursal B (IDs 11-20) | Módulo 1 | **SÍ** |
| 5 consumidores comercio A (IDs 1-5) | Módulo 4 | **SÍ** |
| 5 consumidores comercio B (IDs 6-10) | Módulo 4 | **SÍ** |
| Stock inicial (`producto_sucursal.cantidad_fisica`) para cada producto | Ventas, Transferencias | **SÍ** |
| Cajas en sucursal A y B | Módulo 3 | **SÍ** |
| Cuentas corrientes para consumidores de A y B | Ventas fiado, cobros | **SÍ** |
| Planes (ya existen via PlanSeeder) | Módulo 8 | NO (existen) |
| MercadoPago sandbox credentials | Webhook MP | Parcial |

### Riesgos detectados

| # | Riesgo | Impacto |
|---|---|---|
| 1 | **Brute-force del POST /login** (`throttle:5,1`) puede bloquear IP durante pruebas | Bajo — esperar 1 minuto |
| 2 | **Route Model Binding sin scope** en CategoriaController, MarcaController, ProveedorController (update/status/destroy) | Cross-tenant: IDs globales accesibles entre comercios |
| 3 | **MP Webhook** requiere X-Signature real — imposible mockear sin token sandbox | Prueba 5.2 solo parcial |
| 4 | **Upgrade plan** requiere llamada real a MP API — sin sandbox no se puede | Prueba 8.1 solo parcial |
| 5 | **Estados de caja compartidos**: `TurnoCaja` con `sucursal_id` pero sin `comercio_id` directo | Cierre/movimientos scoped por sucursal, no por comercio |
| 6 | **Transferencias**: `TransferenciaSugerida` no tiene `comercio_id` — scoping por origen/destino via sucursal | Potencial fuga entre comercios |

---

## Test Execution

### Módulo 1: Productos (7 pruebas)

#### 1.1 Crear producto (4)

| ID | Precondiciones | Pasos | Esperado | Obtenido | PASS/FAIL |
|---|---|---|---|---|---|
| P1.1.1 | Sesión como Encargado de A | `POST /productos` `{nombre:"Prod A1", categoria_id:1, marca_id:1, precio_venta:100, stock_minimo:5}` | 302, producto creado. `producto.sucursales[0].pivot.cantidad_fisica = 0` | | |
| P1.1.2 | Sesión como Encargado de B | `POST /productos` `{nombre:"Prod B1", ...}` | 302, producto creado | | |
| P1.1.3 | Sesión como Encargado de A | `GET /productos` | Lista contiene "Prod A1", NO contiene "Prod B1" | | |
| P1.1.4 | Sesión como Encargado de B | `GET /productos` | Lista contiene "Prod B1", NO contiene "Prod A1" | | |

#### 1.2 Editar producto (3)

| ID | Precondiciones | Pasos | Esperado | Obtenido | PASS/FAIL |
|---|---|---|---|---|---|
| P1.2.1 | Sesión A, producto A existe | `POST /productos/{idProdA}` `{nombre:"Prod A1 Editado"}` | 302, nombre actualizado en DB | | |
| P1.2.2 | Sesión A, producto B existe (ID 11-20) | `POST /productos/{idProdB}` `{nombre:"Hackeado"}` | 403 | | |
| P1.2.3 | Sesión B, producto A existe (ID 1-10) | `POST /productos/{idProdA}` `{nombre:"Hackeado"}` | 403 | | |

#### 1.3 Desactivar producto (2)

| ID | Precondiciones | Pasos | Esperado | Obtenido | PASS/FAIL |
|---|---|---|---|---|---|
| P1.3.1 | Sesión A, producto A activo | `PATCH /productos/{idProdA}/status` | 302, `productos.estado = false` | | |
| P1.3.2 | Sesión A, producto B activo | `PATCH /productos/{idProdB}/status` | 403 | | |

#### 1.4 Ajuste stock (2)

| ID | Precondiciones | Pasos | Esperado | Obtenido | PASS/FAIL |
|---|---|---|---|---|---|
| P1.4.1 | Sesión A, producto A vinculado a suc A, stock inicial 10 | `POST /productos/{idProdA}/ajuste-stock` `{sucursal_id:1, cantidad:20, tipo:"ingreso"}` | `producto_sucursal.cantidad_fisica = 30` | | |
| P1.4.2 | Sesión A, producto B (de comercio B) | `POST /productos/{idProdB}/ajuste-stock` | 403 | | |

#### 1.5 Auditoría (2)

| ID | Precondiciones | Pasos | Esperado | Obtenido | PASS/FAIL |
|---|---|---|---|---|---|
| P1.5.1 | Sesión SuperAdmin, producto A con movimientos | `GET /productos/{idProdA}/auditoria` | 200, array de movimientos | | |
| P1.5.2 | Sesión SuperAdmin, producto B | `GET /productos/{idProdB}/auditoria` | 403 | | |

---

### Módulo 2: Ventas (5 pruebas)

#### 2.1 Venta contado (3)

| ID | Precondiciones | Pasos | Esperado | Obtenido | PASS/FAIL |
|---|---|---|---|---|---|
| P2.1.1 | Sesión A, turno abierto en suc A, producto A con stock 20 | `POST /ventas` `{items:[{producto_id:1,cantidad:2}], metodo_pago:"Efectivo", sucursal_id:1}` | 201, venta creada, stock → 18 | | |
| P2.1.2 | Sesión B, turno abierto en suc B, prod B con stock 20 | `POST /ventas` `{items:[{producto_id:11,cantidad:3}], metodo_pago:"Efectivo"}` | 201, stock prod B → 17 | | |
| P2.1.3 | Sesión A | `GET /ventas` | Lista incluye venta de A, NO incluye venta de B | | |

#### 2.2 Venta fiado (2)

| ID | Precondiciones | Pasos | Esperado | Obtenido | PASS/FAIL |
|---|---|---|---|---|---|
| P2.2.1 | Sesión A, consumidor A con cuenta corriente, límite suficiente | `POST /ventas` `{consumidor_id:{idConsA}, items:[...], metodo_pago:"Cuenta Corriente"}` | Venta creada, `cuenta_corriente.saldo_deudor += total` | | |
| P2.2.2 | Sesión A, consumidor B (ID 6-10) | `POST /ventas` `{consumidor_id:{idConsB}, metodo_pago:"Cuenta Corriente"}` | 403 (consumidor no pertenece a comercio A) | | |

#### 2.3 Cancelación (2)

| ID | Precondiciones | Pasos | Esperado | Obtenido | PASS/FAIL |
|---|---|---|---|---|---|
| P2.3.1 | Sesión A, venta A existe y no cancelada | `PATCH /ventas/{idVentaA}/cancelar` `{motivo:"Test"}` | 302, venta.estado = "Cancelada", stock repuesto | | |
| P2.3.2 | Sesión A, venta B existe | `PATCH /ventas/{idVentaB}/cancelar` | 403 | | |

---

### Módulo 3: Caja (5 pruebas)

#### 3.1 Apertura (3)

| ID | Precondiciones | Pasos | Esperado | Obtenido | PASS/FAIL |
|---|---|---|---|---|---|
| P3.1.1 | Sesión A, caja activa en suc A, sin turno abierto | `POST /pos/abrir-turno` `{caja_id:{idCajaA}, monto_apertura:5000}` | Turno abierto, `turno_cajas.estado = "Abierto"` | | |
| P3.1.2 | Sesión B, caja activa en suc B, sin turno abierto | `POST /pos/abrir-turno` `{caja_id:{idCajaB}, monto_apertura:3000}` | Turno abierto | | |
| P3.1.3 | Sesión A intenta abrir turno en caja de suc B | `POST /pos/abrir-turno` `{caja_id:{idCajaB}}` | 403 | | |

#### 3.2 Cierre (1)

| ID | Precondiciones | Pasos | Esperado | Obtenido | PASS/FAIL |
|---|---|---|---|---|---|
| P3.2.1 | Sesión A, turno A abierto | `POST /api/sesiones-caja/{idTurnoA}/cerrar` `{...}` | Turno cerrado, movimientos de cierre registrados | | |
| P3.2.2 | Sesión A, turno B abierto | `POST /api/sesiones-caja/{idTurnoB}/cerrar` | 403 | | |

#### 3.3 Movimientos (1)

| ID | Precondiciones | Pasos | Esperado | Obtenido | PASS/FAIL |
|---|---|---|---|---|---|
| P3.3.1 | Sesión A, turno A con movimientos | `GET /api/sesiones-caja/{idTurnoA}/movimientos` | Lista de movimientos del turno A | | |
| P3.3.2 | Sesión A, turno B | `GET /api/sesiones-caja/{idTurnoB}/movimientos` | 403 | | |

---

### Módulo 4: Consumidores (5 pruebas)

#### 4.1 Creación (2)

| ID | Precondiciones | Pasos | Esperado | Obtenido | PASS/FAIL |
|---|---|---|---|---|---|
| P4.1.1 | Sesión A, módulo fiados activo | `POST /clientes` `{nombre:"Juan", apellido:"Perez", documento:"12345678", limite_cuenta_corriente:5000}` | 302, consumidor creado con comercio_id = 1, cuenta corriente creada | | |
| P4.1.2 | Sesión B | `POST /clientes` (similar) | Creado con comercio_id = 2 | | |

#### 4.2 Deuda / vista (3)

| ID | Precondiciones | Pasos | Esperado | Obtenido | PASS/FAIL |
|---|---|---|---|---|---|
| P4.2.1 | Sesión A | `GET /clientes` | Lista contiene consumidores de A, NO contiene consumidores de B | | |
| P4.2.2 | Sesión A, consumidor A con deuda | `GET /consumidores/{idConsA}/cuenta` | 200, JSON con saldo_deudor > 0 | | |
| P4.2.3 | Sesión A, consumidor B | `GET /consumidores/{idConsB}/cuenta` | 403 | | |

#### 4.3 Cobros (2)

| ID | Precondiciones | Pasos | Esperado | Obtenido | PASS/FAIL |
|---|---|---|---|---|---|
| P4.3.1 | Sesión A, consumidor A con deuda, turno abierto | `POST /consumidores/{idConsA}/cobrar` `{pagos:[{monto:1000,metodo_pago:"Efectivo"}]}` | 302, saldo_deudor reducido, movimiento_caja y movimiento_cc creados | | |
| P4.3.2 | Sesión A, consumidor B | `POST /consumidores/{idConsB}/cobrar` | 403 | | |

---

### Módulo 5: Pedidos Web (4 pruebas)

#### 5.1 Creación (2)

| ID | Precondiciones | Pasos | Esperado | Obtenido | PASS/FAIL |
|---|---|---|---|---|---|
| P5.1.1 | Cliente anónimo, comercio A existe, suc A, productos con stock | `POST /api/pedidos-web` `{comercio_id:1, sucursal_id:1, items:[{id:1,cantidad:1}], metodo_pago:"mp", ...}` | 201, pedido creado en comercio A | | |
| P5.1.2 | Cliente anónimo, comercio B | `POST /api/pedidos-web` `{comercio_id:2, sucursal_id:2, ...}` | 201, pedido creado en comercio B | | |

#### 5.2 Pago MP (1 — parcial, requiere sandbox)

| ID | Precondiciones | Pasos | Esperado | Obtenido | PASS/FAIL |
|---|---|---|---|---|---|
| P5.2.1 | Pedido A existe, pendiente. X-Signature válida | `POST /api/mercadopago/notificacion` `{comercio_id:1, topic:"payment", data:{id:"..."}}` | `pedido.estado_pago = "pagado"` | | |
| P5.2.2 | X-Signature de comercio A, external_ref apunta a pedido B | `POST /api/mercadopago/notificacion` con token de A | 404 (PedidoWeb no encontrado para comercio A) | | |

> **Nota**: P5.2 solo ejecutable con sandbox de MP y webhook secret configurado.

#### 5.3 Cambio estado (2)

| ID | Precondiciones | Pasos | Esperado | Obtenido | PASS/FAIL |
|---|---|---|---|---|---|
| P5.3.1 | Sesión con permiso `gestionar pedidos web`, pedido A existe | `PATCH /pedidos/{idPedidoA}/pago` `{estado_pago:"pagado"}` | 302, `pedido.estado_pago = "pagado"` | | |
| P5.3.2 | Misma sesión, pedido B | `PATCH /pedidos/{idPedidoB}/pago` | 404 (scoped por sucursal_ids del comercio A) | | |

---

### Módulo 6: Usuarios (4 pruebas)

#### 6.1 Creación (2)

| ID | Precondiciones | Pasos | Esperado | Obtenido | PASS/FAIL |
|---|---|---|---|---|---|
| P6.1.1 | Sesión como SuperAdmin o Admin Global de A | `POST /usuarios` `{name:"CajeroA2", email:"cajeroa2@test.com", password:"12345678", branch_id:1, rol:"Cajero"}` | 302, usuario creado con comercio_id = 1 | | |
| P6.1.2 | Sesión A, branch_id de B | `POST /usuarios` `{branch_id:2, ...}` | Error: "La sucursal seleccionada no pertenece a tu comercio" | | |

#### 6.2 Edición (1)

| ID | Precondiciones | Pasos | Esperado | Obtenido | PASS/FAIL |
|---|---|---|---|---|---|
| P6.2.1 | Sesión A, usuario de A existe | `PUT /usuarios/{idUserA}` `{name:"Editado", rol:"Cajero"}` | 302, nombre actualizado | | |
| P6.2.2 | Sesión A, usuario de B | `PUT /usuarios/{idUserB}` | 403 | | |

#### 6.3 Eliminación (1)

| ID | Precondiciones | Pasos | Esperado | Obtenido | PASS/FAIL |
|---|---|---|---|---|---|
| P6.3.1 | Sesión A, usuario de A existe (no soy yo mismo) | `DELETE /usuarios/{idUserA}` | 302, usuario eliminado | | |
| P6.3.2 | Sesión A, usuario de B | `DELETE /usuarios/{idUserB}` | 403 | | |

---

### Módulo 7: Transferencias (2 pruebas)

#### 7.1 Sugeridas (1)

| ID | Precondiciones | Pasos | Esperado | Obtenido | PASS/FAIL |
|---|---|---|---|---|---|
| P7.1.1 | Sesión A, transferencias creadas via job, suc A con productos faltantes y otra suc A con exceso | `GET /transferencias-sugeridas` | Solo transferencias con origen/destino en sucursales de A | | |
| P7.1.2 | Sesión A | `GET /transferencias-sugeridas/{transB}` | 403 | | |

> **Nota**: Requiere 2+ sucursales en el mismo comercio para que existan transferencias.

#### 7.2 Aprobación (1)

| ID | Precondiciones | Pasos | Esperado | Obtenido | PASS/FAIL |
|---|---|---|---|---|---|
| P7.2.1 | Sesión A, transferencia A pendiente | `POST /transferencias-sugeridas/{transA}/aprobar` | Stock movido, transferencia.estado = "aprobada" | | |
| P7.2.2 | Sesión A, transferencia B | `POST /transferencias-sugeridas/{transB}/aprobar` | 403 | | |

---

### Módulo 8: Suscripciones (3 pruebas)

#### 8.1 Upgrade (2)

| ID | Precondiciones | Pasos | Esperado | Obtenido | PASS/FAIL |
|---|---|---|---|---|---|
| P8.1.1 | Sesión A, comercio A con plan básico, plan premium activo | `POST /mi-plan/pagar` `{plan_id:{idPremium}}` | `comercio.pending_plan_id = idPremium` | | |
| P8.1.2 | Sesión A, plan existe | Direct call a webhook o confirmUpgrade | 200, `comercio.plan_id = nuevoPlan` | | |

> **Nota**: P8.1.2 requiere sandbox MP o llamada directa a `confirmarUpgrade` con `payment_id` mockeable.

#### 8.2 Renovación (1)

| ID | Precondiciones | Pasos | Esperado | Obtenido | PASS/FAIL |
|---|---|---|---|---|---|
| P8.2.1 | Admin Global, comercio A existe | `POST /admin-global/facturacion/{comercioA}/pagar` `{fecha:"2026-07-01"}` | `comercio.vencimiento_pago = "2026-07-01"` | | |
| P8.2.2 | Admin Global, comercio B | `POST /admin-global/facturacion/{comercioB}/pagar` | `comercio.vencimiento_pago` actualizado | | |

---

### Cross-Tenant Attack Tests (10)

| ID | Endpoint | Como usuario de A, acceder a ID de B | Esperado | Obtenido | PASS/FAIL |
|---|---|---|---|---|---|
| CT.1 | `POST /productos/{idProdB}` (update) | ID producto de comercio B | 403 | | |
| CT.2 | `POST /productos/{idProdB}/ajuste-stock` | ID producto de comercio B | 403 | | |
| CT.3 | `PATCH /productos/{idProdB}/status` | ID producto de comercio B | 403 | | |
| CT.4 | `PATCH /ventas/{idVentaB}/cancelar` | ID venta de comercio B | 403 | | |
| CT.5 | `POST /consumidores/{idConsB}/cobrar` | ID consumidor de comercio B | 403 | | |
| CT.6 | `GET /consumidores/{idConsB}/cuenta` | ID consumidor de comercio B | 403 | | |
| CT.7 | `PUT /usuarios/{idUserB}` | ID usuario de comercio B | 403 | | |
| CT.8 | `DELETE /usuarios/{idUserB}` | ID usuario de comercio B | 403 | | |
| CT.9 | `POST /transferencias-sugeridas/{transB}/aprobar` | ID transferencia de comercio B | 403 | | |
| CT.10 | `POST /api/sesiones-caja/{turnoB}/cerrar` | ID turno de comercio B | 403 | | |

---

## Reporte Final

| Módulo | Cantidad pruebas | PASS | FAIL | Estado |
|---|---|---|---|---|
| 1. Productos | 13 | | | |
| 2. Ventas | 7 | | | |
| 3. Caja | 6 | | | |
| 4. Consumidores | 7 | | | |
| 5. Pedidos Web | 5 | | | |
| 6. Usuarios | 6 | | | |
| 7. Transferencias | 4 | | | |
| 8. Suscripciones | 4 | | | |
| Cross-Tenant | 10 | | | |
| **Total** | **62** | | | |

---

## Automatización recomendada (Pest/PHPUnit)

| Prioridad | Pruebas | Por qué |
|---|---|---|
| **Alta** | CT.1 a CT.10 (Cross-Tenant) | Fáciles de mockear, validan el core de multi-tenant |
| **Alta** | P1.3.2, P1.4.2, P1.5.2 (Productos cross) | Mismo patrón, bajo esfuerzo |
| **Alta** | P2.2.2, P2.3.2 (Ventas cross) | Validan aislamiento financiero |
| **Media** | P1.1.1 a P1.1.4 (Crear/ver productos) | Flujo completo con factories |
| **Media** | P6.1.1, P6.1.2 (Crear usuario) | Scoping de sucursal |
| **Baja** | Módulo 8 (Suscripciones) | Requiere mock de MP API |

### Requisitos para automatización
- [ ] Factory para `Comercio` (con slug único)
- [ ] Factory para `Sucursal` (con comercio_id)
- [ ] Factory para `Producto` (con relación a sucursales via `producto_sucursal`)
- [ ] Factory para `Consumidor` (con comercio_id + cuenta corriente via evento)
- [ ] Factory para `Venta` (con turno, caja, detalles, lotes)
- [ ] Factory para `TurnoCaja` / `Caja`
- [ ] Factory para `PedidoWeb`
- [ ] Factory para `TransferenciaSugerida`

---

## Orden de ejecución recomendado

1. **Seed data**: Crear script para poblar Comercio A + B con datos completos
2. **Cross-Tenant (CT.1-10)**: Primero, validan el aislamiento base
3. **Productos (P1)**: Segundo, dependencia baja
4. **Usuarios (P6)**: Tercero, dependencia de sesiones
5. **Caja (P3)**: Cuarto, requiere apertura de turno
6. **Ventas (P2)**: Quinto, requiere productos con stock + turno abierto
7. **Consumidores (P4)**: Sexto, puede mezclarse con ventas
8. **Transferencias (P7)**: Séptimo, requiere múltiples sucursales en mismo comercio
9. **Pedidos Web (P5)**: Octavo, requiere comercio + productos
10. **Suscripciones (P8)**: Último, requiere MP sandbox

---

## Notas adicionales

- Para pruebas manuales, crear un script `database/seeders/QASeeder.php` con los 2 comercios y datos completos.
- El test 5.2 solo es válido con un webhook secret real en `services.mercadopago.webhook_secret`.
- `TurnoCaja` y `TransferenciaSugerida` no tienen `comercio_id` directo — el scoping es por relación (turno → caja → sucursal → comercio). Esto es correcto pero debe verificarse en cada query.
- `Categoria`, `Marca`, `Proveedor` son globales (sin `comercio_id`) y NO están scoped — deuda técnica conocida.
