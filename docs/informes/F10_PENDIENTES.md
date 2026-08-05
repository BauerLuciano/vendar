# Informe Técnico — F10: Pendientes de la suite global (fallos preexistentes ajenos al módulo)

| Campo | Valor |
|---|---|
| Fase | F10 (cierre) — documento complementario |
| Alcance | Diagnóstico de los 21 fallos de la suite PHPUnit global que NO pertenecen al módulo de Facturación Electrónica ARCA |
| Método | Suite completa del proyecto + re-ejecución en aislamiento de los archivos fallidos (con y sin el cambio H3.2 para confirmar preexistencia) |
| Estado | **Diagnosticado** — pendiente de corrección por el equipo dueño de cada módulo |

## Contexto

Durante el control de calidad de F10.4 se ejecutó la suite completa del proyecto:
**109 passed, 21 failed, 1 risky, 334 deprecated**. Uno de esos fallos
(`F10_E2EFlujoFacturacionTest`) fue causado por contaminación en suite y **ya se corrigió**
(filtro por id de venta en el historial; verde en aislamiento y en la suite de facturación).

Los **20 fallos restantes son preexistentes y ajenos al módulo de facturación**. Se verificó
re-ejecutando los archivos fallidos con el cambio H3.2 presente y con el stash (mismo resultado).
El AGENTS.md indica no intervenir en esas áreas, por lo que quedan documentados para el equipo dueño.

## Detalle de los fallos

### 1. Auth — PasswordResetTest (4 fallos)

| Test | Causa aparente | Módulo | Prioridad | Bloquea producción | Recomendación |
|---|---|---|---|---|---|
| `test_reset_password_link_screen_can_be_rendered` | `/forgot-password` responde 302 en vez de 200 (redirige a login) | Auth (Breeze/custom) | Media | No (funcionalidad de reset afectada en test, no en runtime evidente) | Revisar si las rutas de reset exigen autenticación; ajustar middleware o el test |
| `test_reset_password_link_can_be_requested` | La notificación `ResetPassword` no se envía (`NotificationFake` sin coincidencia) | Auth | Media | No | Verificar `Notification::send`, driver de notificaciones y que el email del factory exista |
| `test_reset_password_screen_can_be_rendered` | Misma causa: notificación `ResetPassword` nunca se emite | Auth | Media | No | Ídem anterior |
| `test_password_can_be_reset_with_valid_token` | Misma causa raíz: sin notificación no hay token válido | Auth | Media | No | Ídem anterior |

### 2. Auth — RegistrationTest (1 fallo)

| Test | Causa aparente | Módulo | Prioridad | Bloquea producción | Recomendación |
|---|---|---|---|---|---|
| `test_new_users_can_register` | "The user is not authenticated": el registro no deja sesión iniciada tras `POST /register` | Auth (Breeze/custom) | Media | No | Verificar si el registro redirige a verificación de email o cambió el flujo post-registro |

### 3. Caja — Modulo3_CajaTest (3 fallos)

| Test | Causa aparente | Módulo | Prioridad | Bloquea producción | Recomendación |
|---|---|---|---|---|---|
| `test_admin_a_no_puede_abrir_turno_en_caja_de_b` | Espera 404 (recurso inexistente) pero recibe 403 (prohibido): la policy de turno devuelve 403 en vez de ocultar el recurso | Caja/turnos | **Alta** | **Sí** (no se filtra por tenant, se filtra por política) | Unificar la estrategia de aislamiento: 404 para recursos ajenos o ajustar el test al 403 si la política es correcta |
| `test_admin_a_puede_cerrar_turno` | `ValidationException`: "El campo saldo final transferencias real es obligatorio" (y otro campo). El test no envía los saldos que ahora son obligatorios | Caja/turnos | **Alta** | **Sí** (cierre de turno roto si el frontend no envía esos campos) | Verificar que el frontend de cierre de turno envíe `saldo_final_transf_real` y `saldo_final_tarjetas_real`; actualizar el test |
| `test_admin_a_no_puede_cerrar_turno_de_b` | Espera 404 pero recibe 302 (redirect con errores de validación): la validación de saldos corre antes que el chequeo de tenant | Caja/turnos | **Alta** | **Sí** | Validar la pertenencia del turno al comercio ANTES de las validaciones de saldos |

### 4. Caja — CrossTenantAttackTest (1 fallo)

| Test | Causa aparente | Módulo | Prioridad | Bloquea producción | Recomendación |
|---|---|---|---|---|---|
| `test_ct10_cerrar_turno_de_otro_comercio_da_404` | Espera 404 pero recibe 302: las validaciones de saldo (`saldo_final_transf_real`, `saldo_final_tarjetas_real` obligatorios) corren antes del chequeo de pertenencia del turno | Caja/turnos / multi-tenant | **Alta** | **Sí** | Mismo fix que el caso anterior: validar tenencia del turno antes de las reglas de saldo |

### 5. Pedidos Web — Modulo4_PedidosWebTest (2 fallos)

| Test | Causa aparente | Módulo | Prioridad | Bloquea producción | Recomendación |
|---|---|---|---|---|---|
| `test_admin_a_no_puede_actualizar_estado_pedido_de_b` | Espera 404 pero recibe 403 (policy de pedido devuelve prohibido en vez de ocultar) | Pedidos web / multi-tenant | **Alta** | **Sí** | Unificar estrategia de aislamiento (404 vs 403) con el resto del sistema |
| `test_admin_a_no_puede_actualizar_pago_pedido_de_b` | Ídem anterior | Pedidos web / multi-tenant | **Alta** | **Sí** | Ídem anterior |

### 6. Transferencias — Modulo7_TransferenciasTest (1 fallo)

| Test | Causa aparente | Módulo | Prioridad | Bloquea producción | Recomendación |
|---|---|---|---|---|---|
| `test_admin_a_no_puede_cancelar_transferencia_ya_despachada` | `assertSessionHas('error')` falla: el flujo no setea `error` en sesión al intentar cancelar una transferencia ya despachada | Stock/transferencias | Media | No (el error se muestra por otro canal o se valida en frontend) | Verificar qué feedback se da al usuario y ajustar test o controller |

### 7. Multi-tenant — MultiTenantIsolationTest (1 fallo)

| Test | Causa aparente | Módulo | Prioridad | Bloquea producción | Recomendación |
|---|---|---|---|---|---|
| `test_proveedor_usuario_puede_editar_su_propio_proveedor` | "El formato del campo cuit no es válido": el CUIT `30-55555555-5` del test no pasa la validación de CUIT | Proveedores | Media | No (error del fixture, no del flujo) | Corregir el CUIT del fixture a un CUIT válido (11 dígitos sin guiones) |

### 8. Stock — PedidoWebStockTest (4 fallos)

| Test | Causa aparente | Módulo | Prioridad | Bloquea producción | Recomendación |
|---|---|---|---|---|---|
| `test_webhook_mp_pago_aprobado_libera_reserva` | `ArgumentCountError`: `MercadoPagoNotificacionController::__construct()` pasó de 2 a 3 dependencias (`PaymentConfirmationService`); el test lo instancia con 2 | Stock/pedidos web/pagos | **Alta** | **Sí** (test roto por firma del constructor) | Instanciar el controller vía `app()`/container en el test en vez de `new` |
| `test_webhook_mp_rechazo_libera_reserva` | Ídem anterior | Stock/pedidos web/pagos | **Alta** | **Sí** | Ídem anterior |
| `test_webhook_mp_rechazo_registra_movimiento` | Ídem anterior | Stock/pedidos web/pagos | **Alta** | **Sí** | Ídem anterior |
| `test_webhook_mp_aprobado_registra_movimiento` (2 apariciones) | Ídem anterior | Stock/pedidos web/pagos | **Alta** | **Sí** | Ídem anterior |

### 9. Webhooks — WebhookSeguridadTest (2 fallos)

| Test | Causa aparente | Módulo | Prioridad | Bloquea producción | Recomendación |
|---|---|---|---|---|---|
| `test_entrega_pedido_web_crea_movimiento_stock` | `assertGreaterThan(0, 0)` falla: no se crea el movimiento de stock "Pedido Web Entregado" tras la entrega | Stock/pedidos web | **Alta** | **Sí** (si el flujo real tampoco registra el movimiento) | Verificar el flujo de entrega del pedido: si el movimiento se registra con otro `tipo_movimiento` o no se registra |
| `test_webhook_mp_rechazo_registra_movimiento` | Ídem anterior (mismo archivo, ver PedidoWebStockTest) | Stock/pedidos web/pagos | **Alta** | **Sí** | Ídem PedidoWebStockTest |

### 10. Productos — Modulo1_ProductosTest (1 fallo)

| Test | Causa aparente | Módulo | Prioridad | Bloquea producción | Recomendación |
|---|---|---|---|---|---|
| `test_superadmin_a_puede_ver_auditoria_de_producto_b_porque_no_hay_scoping` | Espera 200 pero recibe 403: la ruta `/productos/11/auditoria` ahora está protegida por policy/permiso (el nombre del test documentaba un agujero de scoping que parece haberse cerrado) | Productos/auditoría | Baja | No | Revisar si el 403 es el comportamiento deseado (scoping correcto) y actualizar el test para reflejarlo |

## Resumen por severidad

| Prioridad | Cantidad | Criterio |
|---|---|---|
| Alta | 12 | Afectan aislamiento multi-tenant visible (403 vs 404), cierre de turno (validación antes de tenencia) o tests rotos por firma de constructor (webhooks de pago) |
| Media | 7 | Auth (reset/registro) y fixture de CUIT inválido; funcionalidad no evidentemente rota en runtime |
| Baja | 1 | Test que documentaba un agujero de scoping ya cerrado |

## Recomendación general

1. **Bloqueantes de merge global** (no bloquean el módulo de facturación): los 12 de prioridad Alta
   afectan caja/turnos, pedidos web y webhooks de pago — áreas con lógica crítica (dinero y stock)
   que el AGENTS.md manda a no tocar desde este módulo. Deben resolverlos los equipos dueños.
2. **Módulo de facturación**: aislado, terminado, documentado y verde (108/108 en la suite propia).
3. Los 334 deprecados son `PDO::MYSQL_ATTR_SSL_CA is deprecated` (preexistentes) y no bloquean nada.
