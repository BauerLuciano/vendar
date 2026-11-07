# BUILD B3 — Facturación y control de cobros (ETAPA 2)

**Fecha:** 2026-11-06  
**Etapa:** 2 de 2 — implementación  
**Estado:** implementado y verificado en `testing`. Cobros reales deshabilitados por bandera.  
**Alcance:** módulo de facturación del Administrador Global (`/admin-global/facturacion`): lectura de plan/precio, estados de cobro, mora, pago manual, historial, idempotencia, auditoría, permisos y ocultamiento de Mercado Pago simulado.

> No se definieron ni modificaron precios comerciales, duración de planes, días de prueba, reglas de suspensión/suscripción ni integración real de Mercado Pago/ARCA.  
> No se tocaron POS, Caja, Compras, Productos, Stock, Lotes, Clientes, Pedidos web, B1 ni B2.

---

## 1. Objetivo

Convertir la pantalla de facturación, que era una vista de consulta con un botón que movía una fecha, en un módulo confiable para administrar suscripciones:

- mostrar el plan y el precio configurado reales, sin confundirlos con la columna legacy `comercios.plan`;
- clasificar correctamente `Al Día`, `Por Vencer`, `Vencido` y `Suspendido`, incluyendo a los suspendidos en la mora;
- registrar pagos manuales de forma transaccional, idempotente y auditable;
- calcular el período sin acortar uno vigente;
- exponer historial de pagos;
- ocultar/deshabilitar las acciones simuladas de Mercado Pago y no filtrar credenciales;
- **no habilitar cobros reales mientras los precios comerciales estén pendientes**.

---

## 2. Archivos creados

| Archivo | Propósito |
|---|---|
| `config/vendar.php` | Banderas `facturacion.precios_confirmados` y `facturacion.cobros_habilitados`, ambas `false` por defecto. |
| `app/Exceptions/PagoManualDeshabilitadoException.php` | Excepción cuando los cobros están deshabilitados. |
| `app/Exceptions/PagoManualInvalidoException.php` | Excepción de validaciones de negocio del pago manual. |
| `app/Services/Suscripcion/PagoManualService.php` | Registro transaccional, validación de importe, idempotencia, anti-duplicados y auditoría. |
| `database/migrations/2026_11_06_000001_add_tracking_to_payments_table.php` | Columnas de tracking en `payments`, índice y unique de idempotencia. |
| `tests/Feature/BuildB3_FacturacionTest.php` | 20 casos B3. |
| `docs/informes/BUILD-B3.md` | Este informe. |

## 3. Archivos modificados

| Archivo | Cambio |
|---|---|
| `app/Models/Payment.php` | Nuevos campos en `$fillable`, casts de período y relación `createdBy()`. |
| `app/Models/Comercio.php` | Relación inequívoca `planContratado()`; `plan()` se conserva por compatibilidad. |
| `app/Services/Suscripcion/SuscripcionService.php` | Nuevo `calcularPeriodo()` y duración configurable por plan. |
| `app/Http/Controllers/GlobalAdminController.php` | Nueva `facturacion()`, `historialPagos()`, `construirHistorial()`, `marcarPagado()` y `generarLinkMP()`; MRR condicionado en `metricas()`. |
| `app/Http/Controllers/ConfiguracionController.php` | Elimina `access_token` y `client_secret` de las props Inertia; expone `credenciales_configuradas`. |
| `routes/web.php` | Ruta `admin.facturacion.historial`. |
| `resources/js/Pages/AdminGlobal/Facturacion.vue` | Estados, aviso de precios, historial con filtros y acción de cobro condicionada. |
| `resources/js/Pages/AdminGlobal/Metricas.vue` | MRR oculto mientras los precios no estén confirmados. |
| `.env.example` | Documenta las dos banderas en `false`. |

---

## 4. Decisiones técnicas

### 4.1 Resolución del conflicto `comercios.plan` vs `plan()`

Se agregó `Comercio::planContratado()` como relación inequívoca al plan real (`plan_id`). El código nuevo usa `planContratado`; `plan()` se mantiene para no romper consumidores existentes. La columna legacy `plan` no se renombró ni se migró.

### 4.2 Precios pendientes

- La fuente de precio es `planes.precio_mensual`, pero se expone como `precio_configurado` y se marca `precio_es_referencia = true` mientras `precios_confirmados` sea `false`.
- No se muestran proyecciones monetarias (`total_esperado`, `total_vencido`) ni MRR como si fueran tarifas oficiales.
- El MRR queda en `null` hasta que se confirmen precios.

### 4.3 Estados de facturación

| Condición | Estado |
|---|---|
| `status = suspendido` | `Suspendido` (precedencia absoluta) |
| Sin `vencimiento_pago` | `Sin vencimiento` |
| `dias_restantes < 0` | `Vencido` |
| `dias_restantes = 0` | `Al Día` (el día de vencimiento todavía hay acceso) |
| `1..10` | `Por Vencer` |
| `> 10` | `Al Día` |

Mora = `Vencido` + `Suspendido`. Los suspendidos no desaparecen de métricas ni listados de mora.

### 4.4 Cálculo de períodos

`SuscripcionService::calcularPeriodo()`:

- si el vencimiento actual es hoy o futuro, el período empieza en ese vencimiento;
- si ya venció o no existe, empieza en la fecha de pago;
- nunca acorta un período vigente;
- usa `addMonthsNoOverflow()`;
- la duración se toma de `duracion_meses` si el plan la define; hoy el default es 1 mes.

No se modificó `nuevaFechaVencimiento()` para no romper su test preexistente de mismo día.

### 4.5 Pago manual deshabilitado por defecto

`PagoManualService::estaHabilitado()` exige **dos** banderas en `true`:

- `vendar.facturacion.cobros_habilitados`
- `vendar.facturacion.precios_confirmados`

Con cualquiera en `false`:

- `marcarPagado()` responde `403`;
- no se crea ningún `Payment`;
- no se modifica `vencimiento_pago` ni `status`.

### 4.6 Idempotencia y anti-duplicados

- `idempotency_key` único (nullable). Un reintento con la misma clave devuelve el pago original sin extender dos veces.
- Se rechaza un segundo pago aprobado del mismo comercio para el mismo `period_end`.
- Todo ocurre dentro de `DB::transaction()` con `lockForUpdate()` sobre el comercio y sobre los pagos relevantes.

### 4.7 Auditoría

- Cada pago manual persiste en `payments` con importe, moneda, método, período, referencia, responsable y fecha.
- Se registra un evento dedicado `pago_manual_registrado` en `activity_log`, con valores anteriores y nuevos de vencimiento/estado.
- El `activity_log` genérico no se usa como libro de pagos.

### 4.8 Mercado Pago

- `generarLinkMP()` devuelve `disponible = false`, `cobro_generado = false` y “Los pagos online estarán disponibles próximamente.”.
- La UI no muestra botones simulados que aparenten generar cobros.
- No se implementó integración real de MP en B3.
- El flujo real de autoservicio (`/mi-plan/pagar`, `/retorno`, webhook) no se tocó.

### 4.9 Credenciales

`ConfiguracionController` elimina `access_token` y `client_secret` del `configuration` enviado a Inertia y sólo expone el booleano `credenciales_configuradas`. No se expone ningún secreto en props, JSON, logs ni reportes.

---

## 5. Migración

`database/migrations/2026_11_06_000001_add_tracking_to_payments_table.php` agrega a `payments`:

| Columna | Tipo | Notas |
|---|---|---|
| `period_start` | `date` nullable | inicio del período abonado |
| `period_end` | `date` nullable | fin del período / nuevo vencimiento |
| `payment_method` | `string(30)` nullable | efectivo, transferencia, tarjeta, otro |
| `created_by_id` | FK `users` nullable | `nullOnDelete()` |
| `idempotency_key` | `string(191)` nullable, unique | evita doble registro |

Además crea el índice `payments_payable_period_index` sobre `(payable_type, payable_id, period_end)`.

**Backup previo:** `C:\Users\lucia\AppData\Local\Temp\opencode\vendar_backup_B3_20261106_210646.dump`

**Verificación:** aplicada en `testing`, rollback ejecutado y re-migración correcta. Las cinco columnas se confirmaron presentes. No es destructiva y no toca datos existentes.

---

## 6. Bugs corregidos

| ID auditoría | Corrección |
|---|---|
| B3-A01 | Se usa `planContratado()` y `planes.precio_mensual`; ya no se muestra `PRO`/`$0` por la columna string. |
| B3-A02 | El pago manual asigna `status` explícitamente y reactiva correctamente (cuando los cobros están habilitados). |
| B3-A03 | `calcularPeriodo()` prorroga desde el vencimiento vigente y no acorta períodos. |
| B3-A04 | El pago manual crea un `Payment` con importe, método, período, responsable y referencia. |
| B3-A05 | Los suspendidos cuentan en `en_mora` y `clientes_morosos`. |
| B3-A06 | El día de vencimiento figura `Al Día`, no `Vencido`. |
| B3-A07 | `dias_restantes` ahora es positivo en el futuro y negativo en el pasado. |
| B3-A08 | Los botones MP simulados se ocultaron/deshabilitaron y la API declara `disponible = false`. |
| B3-A09 | Idempotencia por clave y rechazo de pago aprobado duplicado por período, con locks. |
| B3-A11 | `access_token` y `client_secret` ya no llegan al frontend. |

También se corrigió el uso ambiguo de `$c->plan` en `metricas()` y se evitó que un plan inexistente se muestre como `$0`.

---

## 7. Pruebas ejecutadas

### 7.1 Tests específicos de B3

```
docker exec vendar-app-laravel.test-1 php artisan test tests/Feature/BuildB3_FacturacionTest.php
```

**Resultado:** 20/20 tests, 115 aserciones. Todos pasan.

PHPUnit los reporta como `DEPR` por la constante `PDO::MYSQL_ATTR_SSL_CA` en PHP 8.5, un aviso preexistente del entorno, sin fallos.

### 7.2 Regresión de módulos relacionados

| Suite | Resultado |
|---|---|
| `tests/Feature/BuildB2_CajasUnicidadTest.php` | 13/13 |
| `tests/Unit/Suscripcion/SuscripcionServiceTest.php` | 25/25 |
| `tests/Feature/BuildB1_UsuariosSeguridadTest.php` | 28/28 |

Los 3 casos de B1 que fallaron en la primera corrida por un reinicio espontáneo de PostgreSQL (`terminating connection due to administrator command`) se repitieron con el contenedor healthy y pasaron (5 aserciones). No hubo fallo de lógica.

### 7.3 Frontend

```
npm run build
```

**Resultado:** Vite compiló 954 módulos sin errores y generó `public/build`. El chunk `Facturacion-*.js` se generó correctamente.

### 7.4 Sintaxis PHP

`php -l` sin errores en todos los archivos PHP creados o modificados.

---

## 8. Criterios de aceptación

- [x] El plan y el precio salen de la relación al plan y de `planes.precio_mensual`, no de la columna string.
- [x] No se muestra `$0` por fallo de relación; sin plan se informa explícitamente.
- [x] La UI muestra `Al Día`, `Por Vencer`, `Vencido` y `Suspendido` con el umbral existente de 10 días.
- [x] `dias_restantes` es correcto y el día de vencimiento no se marca `Vencido` antes de tiempo.
- [x] El pago manual registra importe, método, período, referencia, responsable y fecha.
- [x] El pago manual no acorta un período futuro y usa `addMonthsNoOverflow()`.
- [x] El pago manual puede reactivar una cuenta suspendida (con cobros habilitados).
- [x] El pago manual es idempotente y no duplica el mismo período.
- [x] Hay historial por comercio, separado de ventas y pedidos.
- [x] Las acciones MP simuladas están ocultas/deshabilitadas; no hay falsos positivos.
- [x] Las operaciones quedan auditadas con un evento dedicado.
- [x] Solo `Administrador Global` accede a las rutas globales; los usuarios de comercio reciben `403`.
- [x] Los tests B3 pasan y no se modificaron tests preexistentes.
- [x] No se tocaron precios, períodos, trial, suspensión, MP real ni módulos ajenos.
- [x] La migración fue no destructiva, con backup, aplicación en `testing` y rollback verificado.
- [x] Se creó `docs/informes/BUILD-B3.md`.

### Verificación explícita de que no se hicieron cobros reales

- `precios_confirmados = false` y `cobros_habilitados = false` por defecto.
- Con las banderas en `false`, `POST /admin-global/facturacion/{comercio}/pagar` responde `403`.
- Los tests confirman que no se crea ningún `Payment` ni se modifica `vencimiento_pago`/`status` en ese escenario.
- No se ejecutó ningún cobro contra Mercado Pago ni se generó ningún link real.

---

## 9. Pendientes de la siguiente fase

1. **Precios comerciales definitivos.** Definir la tarifa real de cada plan y reemplazar la fuente de verdad. Hoy conviven valores de prueba en la base (`1/2/3`) y en el seeder (`8000/15000/35000`); ninguno debe tratarse como oficial.
2. **Habilitación de cobros manuales.** Recién con precios confirmados, activar `VENDAR_PRECIOS_CONFIRMADOS=true` y `VENDAR_COBROS_HABILITADOS=true`, y validar el flujo completo de punta a punta.
3. **Integración real de Mercado Pago para el Administrador Global.** Fuera del alcance de B3; requiere decisión de negocio y credenciales de plataforma.
4. **Reglas de importes parciales/ajustes.** El esquema actual registra el precio completo del plan; si se permiten pagos parciales o prorrateos, hay que definirlos antes de implementarlos.
5. **Distinción de suspensión automática vs manual.** Hoy sólo existe `status = suspendido`; si se necesita trazabilidad de causa, requiere campos nuevos y una fase propia.
6. **Deriva de esquema preexistente.** `comercios.plan` sigue como legacy y `planes` conserva columnas no usadas por el modelo actual. No se tocaron en B3.
7. **Firma de webhooks de Mercado Pago.** Revisar `MercadopagoGateway::verifyWebhookSignature()` y la presencia de `MERCADOPAGO_WEBHOOK_SECRET` antes de producción; es deuda preexistente, no introducida en B3.

---

## 10. Conclusión

B3 ETAPA 2 queda implementado y verificado. El módulo ya calcula estados y períodos correctamente, registra pagos de forma transaccional y auditable, expone historial y no filtra credenciales. Los cobros reales permanecen **deshabilitados por configuración explícita** hasta que el negocio defina los precios comerciales.

No se avanzó a B4.
