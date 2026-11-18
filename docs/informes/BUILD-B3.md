# BUILD B3 — Facturación y control de cobros (ETAPA 2)

**Fecha original:** 2026-11-06

**Revisión final:** 2026-11-15

**Etapa:** 2 de 2 — implementación y correcciones de revisión

**Estado:** implementado y verificado en `testing` y en la base local `vendar`. Cobros reales deshabilitados por bandera.

**Alcance:** módulo de facturación del Administrador Global (`/admin-global/facturacion`): lectura de plan/precio, estados de cobro, mora, pago manual, historial, idempotencia, auditoría, permisos y ocultamiento de Mercado Pago simulado.

> No se definieron ni modificaron precios comerciales, duración de planes, días de prueba, reglas de suspensión/suscripción ni integración real de Mercado Pago/ARCA.  
> No se tocaron POS, Caja, Compras, Productos, Stock, Lotes, Clientes, Pedidos web, B1 ni B2.

---

## 1. Objetivo

Convertir la pantalla de facturación, que era una vista de consulta con un botón que movía una fecha, en un módulo confiable para administrar suscripciones:

- mostrar el plan y el precio configurado reales, sin confundirlos con la columna legacy `comercios.plan`;
- clasificar correctamente `Al Día`, `Por Vencer`, `Vencido` y `Suspendido`, incluyendo en la mora sólo la deuda real;
- registrar pagos manuales de forma transaccional, idempotente y auditable;
- calcular el período sin acortar uno vigente ni regalar/quitar días por la fecha del comprobante;
- exponer historial de pagos separado de ventas;
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
| `tests/Feature/BuildB3_FacturacionTest.php` | 37 casos B3. |
| `docs/informes/BUILD-B3.md` | Este informe. |

## 3. Archivos modificados

| Archivo | Cambio |
|---|---|
| `app/Models/Payment.php` | Nuevos campos en `$fillable`, casts de período y relación `createdBy()`. |
| `app/Models/Comercio.php` | Relación inequívoca `planContratado()`; `plan()` se conserva por compatibilidad. |
| `app/Services/Suscripcion/SuscripcionService.php` | `calcularPeriodo()` corregido; `aplicarPago()` persiste período/moneda/responsable y usa fin de día para reactivar. |
| `app/Services/Suscripcion/PagoManualService.php` | Rechazo de fecha futura, precio nulo e importe no positivo; idempotencia acotada al mismo comercio; anti-duplicado para cualquier proveedor. |
| `app/Http/Controllers/GlobalAdminController.php` | Plan/precio sin fallback al slug legacy; día 0 `Por Vencer`; mora refinada; validación de pago; historial filtrado por tipo suscripción; `metodos_pago` expuesto. |
| `app/Http/Controllers/ConfiguracionController.php` | Elimina `access_token` y `client_secret` de las props Inertia; expone `credenciales_configuradas`. |
| `app/Http/Middleware/VerificarEstadoCuenta.php` | Exime `suscripcion.cancelar-pago` para que una cuenta suspendida pueda liberar un pago en vuelo. |
| `routes/web.php` | Ruta `admin.facturacion.historial`; `cuenta.suspendida` usa `planContratado` y fin de día. |
| `resources/js/Pages/AdminGlobal/Facturacion.vue` | Modal explícito de pago (importe, método, referencia, fecha, clave de idempotencia); estados, aviso de precios e historial. |
| `resources/js/Pages/AdminGlobal/Metricas.vue` | MRR oculto mientras los precios no estén confirmados. |
| `.env.example` | Documenta las dos banderas en `false`. |

---

## 4. Decisiones técnicas

### 4.1 Resolución del conflicto `comercios.plan` vs `plan()`

Se agregó `Comercio::planContratado()` como relación inequívoca al plan real (`plan_id`). El código nuevo usa `planContratado`; `plan()` se mantiene para no romper consumidores existentes. La columna legacy `plan` no se renombró ni se migró.

**Corrección de la revisión:** `cuenta.suspendida` todavía leía `$comercio->plan?->nombre ?? strtoupper($comercio->plan)`. Como `plan` es una columna string, el nullsafe no aplicaba y el fallback mostraba `PRO` con un plan real inexistente. Ahora usa `planContratado` y, si no hay relación, muestra `Sin plan`. En la pantalla de facturación y en métricas se conserva el slug legacy en un campo separado `plan_legacy` sólo para diagnóstico, nunca como nombre ni precio.

### 4.2 Precios pendientes

- La fuente de precio es `planes.precio_mensual`, pero se expone como `precio_configurado` y se marca `precio_es_referencia = true` mientras `precios_confirmados` sea `false`.
- No se muestran proyecciones monetarias (`total_esperado`, `total_vencido`) ni MRR como si fueran tarifas oficiales.
- El MRR queda en `null` hasta que se confirmen precios.
- Si no hay plan relacionado, `precio_configurado` es `null`, no `0`.

### 4.3 Estados de facturación

| Condición | Estado |
|---|---|
| `status = suspendido` | `Suspendido` (precedencia visual) |
| Sin `vencimiento_pago` | `Sin vencimiento` |
| `dias_restantes < 0` | `Vencido` |
| `dias_restantes = 0` | `Por Vencer` (el día de vencimiento todavía hay acceso) |
| `1..10` | `Por Vencer` |
| `> 10` | `Al Día` |

**Corrección de la revisión:** el día 0 se mostraba `Al Día`, mientras `SuscripcionService::estadoSuscripcion()` ya lo consideraba `Por Vencer`. Se alineó la UI con el servicio y con la auditoría: día 0 = `Por Vencer`, nunca `Vencido`.

Mora (`en_mora` / `clientes_morosos`) = comercios con `dias_restantes < 0` **más** suspendidos sin vencimiento. Un suspendido manual con vencimiento futuro sigue listado como `Suspendido`, pero no engrosa la deuda porque tiene un período pago vigente. Un suspendido con vencimiento pasado sí cuenta.

### 4.4 Cálculo de períodos

`SuscripcionService::calcularPeriodo()`:

- si el vencimiento actual es hoy o futuro, el período empieza en ese vencimiento;
- si ya venció o no existe, empieza en la **fecha actual**, no en la fecha del comprobante;
- una `fecha_pago` pasada se usa para `approved_at` (registro contable), pero se acota a hoy para calcular el período: no puede regalar ni quitar días de suscripción;
- nunca acorta un período vigente;
- usa `addMonthsNoOverflow()`;
- la duración se toma de `duracion_meses` si el plan la define; hoy el default es 1 mes.

**Corrección de la revisión:** antes, si la suscripción ya había vencido, el período arrancaba en `fecha_pago`. Un comprobante cargado con fecha vieja acortaba el nuevo período. Ahora arranca hoy. `aplicarPago()` (Mercado Pago) dejó de usar `nuevaFechaVencimiento()` y usa `calcularPeriodo()`, por lo que ambos flujos comparten la misma regla. `nuevaFechaVencimiento()` se conserva sólo por compatibilidad y tests unitarios.

### 4.5 Pago manual deshabilitado por defecto

`PagoManualService::estaHabilitado()` exige **dos** banderas en `true`:

- `vendar.facturacion.cobros_habilitados`
- `vendar.facturacion.precios_confirmados`

Con cualquiera en `false`:

- `marcarPagado()` responde `403`;
- no se crea ningún `Payment`;
- no se modifica `vencimiento_pago` ni `status`.

**Corrección de la revisión:** el controlador ahora también captura `PagoManualDeshabilitadoException` por si la bandera cambia entre el pre-chequeo y el servicio, y valida `importe > 0` y `fecha_pago <= today` antes de llamar al servicio.

### 4.6 Idempotencia y anti-duplicados

- `idempotency_key` es único global y nullable. Un reintento con la misma clave devuelve el pago original sin extender dos veces.
- Si la clave existe pero pertenece a **otro** `payable`, se rechaza como reutilización cross-tenant. Nunca se devuelve el pago de otro comercio.
- En el camino manual se rechaza un segundo pago aprobado del mismo comercio para el mismo `period_end`, **sin importar el proveedor del pago existente** (`manual` o `mercadopago`). El camino de Mercado Pago no aplica esta misma guarda; ver B9 en §13.2.
- Todo ocurre dentro de `DB::transaction()` con `lockForUpdate()` sobre el comercio. El lock serializa los intentos concurrentes del mismo comercio.
- No se agregó una restricción unique parcial sobre `(payable, period_end)` porque los pagos parciales siguen siendo una decisión comercial pendiente. La protección actual es lock de fila + unique de idempotencia.

### 4.7 Auditoría y persistencia

- Cada pago manual persiste en `payments` con: comercio (`payable`), importe, moneda, método, período (`period_start`/`period_end`), fecha de pago (`approved_at`), responsable (`created_by_id`), referencia/comprobante, estado, fecha de creación y metadata (`tipo = suscripcion`, `origen = manual`).
- Cada pago de Mercado Pago persiste período, moneda `ARS`, `created_by_id` cuando hay usuario autenticado (`null` en webhook) y metadata `tipo = suscripcion`.
- Se registra un evento dedicado `pago_manual_registrado` en `activity_log`, con valores anteriores y nuevos de vencimiento/estado y el período aplicado.
- El `activity_log` genérico no se usa como libro de pagos.
- El historial de facturación filtra `payable_type = Comercio` y `metadata->tipo = suscripcion`, de modo que no mezcla pagos de ventas ni pedidos.

### 4.8 Mercado Pago

- `generarLinkMP()` devuelve `disponible = false`, `cobro_generado = false` y “Los pagos online estarán disponibles próximamente.”.
- La UI no muestra botones simulados que aparenten generar cobros.
- No se implementó integración real de MP en B3 y no se ejecutó ningún cobro.
- El flujo real de autoservicio (`/mi-plan/pagar`, `/retorno`, webhook) no se tocó, salvo la exención de `suscripcion.cancelar-pago` para que una cuenta suspendida pueda liberar una preferencia abandonada.
- No se inventaron links de pago ni se registraron pagos aprobados ficticios.

### 4.9 Credenciales

`ConfiguracionController` elimina `access_token` y `client_secret` del `configuration` enviado a Inertia y sólo expone el booleano `credenciales_configuradas`. No se expone ningún secreto en props, JSON, logs ni reportes.

### 4.10 Autorización

Las rutas `/admin-global/facturacion*` siguen protegidas por `role:Administrador Global`. Los usuarios de comercio (SuperAdmin, Cajero, Encargado) reciben `403`; un invitado es redirigido al login (o `401` en JSON). El comercio no puede modificar sus propios pagos ni los de otro, ni alterar plan/vencimiento por esta vía.

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

**Estado en esta revisión:** aplicada en las bases locales `testing` y `vendar` con `php artisan migrate --force`. Verificadas las cinco columnas, el índice y el unique. No se ejecutó en producción. Es aditiva, no borra datos y su `down()` es reversible.

**Nota:** el informe original mencionaba un backup en una ruta de Windows y un rollback de prueba; esa corrida correspondió a otro entorno. En este entorno Linux la migración se aplicó directamente sobre las bases locales de desarrollo y test.

---

## 6. Bugs y brechas corregidas

| ID | Problema detectado | Corrección |
|---|---|---|
| B3-A01 | `cuenta.suspendida` y pantallas globales mostraban `PRO`/`$0` por leer la columna string `comercios.plan`. | Se usa `planContratado`; sin relación se muestra `Sin plan` y precio `null`. El slug legacy queda en `plan_legacy`. |
| B3-A02 | El estado del pago dependía de asignación masiva y la reactivación podía ocurrir por un pago no aprobado. | `Payment` se crea explícitamente con `PaymentStatus::APPROVED`; la reactivación ocurre sólo dentro de la transacción, después de persistir el pago. |
| B3-A03 | Un pago con `fecha_pago` pasada acortaba el nuevo período. | `calcularPeriodo()` acota la fecha a hoy cuando ya venció. `aplicarPago()` usa la misma función. |
| B3-A04 | Los pagos de Mercado Pago no guardaban período, moneda ni responsable. | `registrarPago()` persiste `period_start`, `period_end`, `currency = ARS` y `created_by_id`. |
| B3-A05 | La mora sumaba todos los suspendidos, incluso los que tenían vencimiento futuro. | Mora = comercios con `dias_restantes < 0` (vencidos o suspendidos con vencimiento pasado) + suspendidos sin vencimiento. Un suspendido con período vigente sigue listado pero no cuenta como deuda. |
| B3-A06 | El día de vencimiento figuraba `Al Día` en la UI y `Por Vencer` en el servicio. | Día 0 = `Por Vencer` en UI, servicio y tests. |
| B3-A07 | `cuenta.suspendida` cortaba el acceso al inicio del día de vencimiento. | Usa `endOfDay()`, igual que `VerificarEstadoCuenta`. |
| B3-A08 | La clave de idempotencia era global y podía devolver el pago de otro comercio. | Si la clave pertenece a otro `payable`, se rechaza como cross-tenant. |
| B3-A09 | El anti-duplicado sólo miraba pagos manuales. | Se eliminó el filtro por proveedor: un pago MP aprobado del mismo período también bloquea el pago manual. El camino de Mercado Pago no aplica esta guarda; ver B9 en §13.2. |
| B3-A10 | El pago manual aceptaba fecha futura, importe 0/negativo, precio de plan nulo y referencias vacías. | Validaciones explícitas en servicio y controlador; referencia vacía se normaliza a `null`. |
| B3-A11 | El historial podía mezclar pagos de ventas con facturación SaaS. | Filtro `metadata->tipo = suscripcion` y `payable_type = Comercio`. |
| B3-A12 | Una cuenta suspendida no podía cancelar un pago en vuelo porque el middleware la redirigía. | `VerificarEstadoCuenta` exime `suscripcion.cancelar-pago`. |
| B3-A13 | La UI de cobro manual enviaba siempre `efectivo` y el precio configurado sin confirmación. | Modal explícito con importe, método, referencia, fecha y clave de idempotencia; el backend valida todo. |
| B3-A14 | `access_token` y `client_secret` llegaban al frontend. | Se eliminaron de las props Inertia; sólo se expone `credenciales_configuradas`. |

También se corrigió el uso ambiguo de `$c->plan` en `metricas()` y se evitó que un plan inexistente se muestre como `$0`.

---

## 7. Pruebas ejecutadas

### 7.1 Tests específicos de B3

```
docker compose exec -T laravel.test php artisan test tests/Feature/BuildB3_FacturacionTest.php
```

**Resultado:** 37/37 tests, 209 aserciones. Todos pasan.

Cobertura de la matriz B3:

- resolución de plan/precio real y fallback `Sin plan` sin `PRO`/`$0`;
- persistencia de estado, moneda, período, método, referencia, responsable y metadata;
- pago anticipado (30/06 → 30/07) y pago vencido (arranca hoy);
- pago con fecha pasada que no acorta el período;
- duración no mensual (`duracion_meses = 3`);
- rechazo de fecha futura e importe inválido;
- idempotencia con la misma clave (mismo pago, sin doble extensión);
- clave de idempotencia de otro comercio rechazada sin cambios;
- duplicado aprobado por Mercado Pago que bloquea el manual;
- suspendido con vencimiento futuro listado pero fuera de mora;
- día de vencimiento accesible y etiquetado `Por Vencer`;
- historial sólo de suscripción, con importe, moneda, método, período, estado y responsable;
- pago MP que persiste período y responsable;
- cuenta suspendida que puede cancelar un pago en vuelo;
- autorización: Administrador Global sí; SuperAdmin/Cajero no; invitado no;
- credenciales MP no expuestas y acciones simuladas sin apariencia de cobro real.

PHPUnit los reporta como `deprecated` por la constante `PDO::MYSQL_ATTR_SSL_CA` en PHP 8.5, un aviso preexistente del entorno, sin fallos.

### 7.2 Regresión de módulos relacionados

| Suite | Resultado |
|---|---|
| `tests/Unit/Suscripcion/SuscripcionServiceTest.php` | 25/25, 56 aserciones |
| `tests/Feature/Modulo8_SuscripcionesTest.php` | 69/69, 507 aserciones |

### 7.3 Suite completa

```
docker compose exec -T laravel.test php artisan test
```

**Resultado:** `431 deprecated, 23 failed, 1 risky, 1 passed` (1709 aserciones), 749.58 s.

Los 23 fallos coinciden en cantidad, suite y causa con la línea base preexistente de BUILD B1; no son de B3. La comparación nombre por nombre, mensaje y causa está en §13.3:

- `Auth\PasswordResetTest` (4) y `Auth\RegistrationTest` (1);
- `CrossTenantAttackTest` (1);
- `Modulo1_ProductosTest` (1);
- `Modulo3_CajaTest` (3);
- `Modulo4_PedidosWebTest` (2);
- `Modulo7_TransferenciasTest` (1);
- `MultiTenantIsolationTest` (1);
- `VentasCanceladasHistorialTest` (8);
- `WebhookSeguridadTest` (1).

Ningún test de `BuildB3_FacturacionTest`, `Modulo8_SuscripcionesTest` ni `SuscripcionServiceTest` falló. No se atribuyen los 23 fallos a B3 ni se corrigieron en esta fase.

### 7.4 Frontend, formato y sintaxis

- `npm run build`: compila sin errores; el chunk de `Facturacion` se genera correctamente.
- `vendor/bin/pint --test` sobre los archivos tocados: `pass`.
- `php -l` sin errores en todos los archivos PHP modificados.

---

## 8. Criterios de aceptación

- [x] El plan y el precio salen de la relación al plan y de `planes.precio_mensual`, no de la columna string.
- [x] No se muestra `$0` por fallo de relación; sin plan se informa `Sin plan`.
- [x] La UI muestra `Al Día`, `Por Vencer`, `Vencido` y `Suspendido` con el umbral existente de 10 días.
- [x] El día de vencimiento se etiqueta `Por Vencer` y el acceso dura hasta el fin de ese día.
- [x] `dias_restantes` es correcto y la mora no incluye suspendidos con período vigente.
- [x] El pago manual registra importe, moneda, método, período, referencia, responsable, estado y fecha.
- [x] El pago manual no acorta un período futuro y usa `addMonthsNoOverflow()`.
- [x] Un pago vencido arranca el nuevo período hoy, no en la fecha del comprobante.
- [x] El pago manual puede reactivar una cuenta suspendida (con cobros habilitados).
- [x] El pago manual es idempotente por `idempotency_key` y rechaza un pago aprobado existente con el mismo `period_end`. La asimetría con el camino de Mercado Pago queda documentada en B9 (§13.2).
- [x] La clave de idempotencia no se reutiliza entre comercios.
- [x] Hay historial por comercio, separado de ventas y pedidos.
- [x] Las acciones MP simuladas están ocultas/deshabilitadas; no hay falsos positivos.
- [x] Las operaciones quedan auditadas con un evento dedicado.
- [x] Solo `Administrador Global` accede a las rutas globales; los usuarios de comercio reciben `403`.
- [x] Los tests B3 pasan y no se modificaron tests preexistentes ajenos a B3.
- [x] No se tocaron precios, períodos, trial, suspensión, MP real ni módulos ajenos.
- [x] La migración fue no destructiva, aplicada en `testing` y `vendar` local, no en producción.
- [x] Se actualizó `docs/informes/BUILD-B3.md`.

### Verificación explícita de que no se hicieron cobros reales

- `precios_confirmados = false` y `cobros_habilitados = false` por defecto.
- Con las banderas en `false`, `POST /admin-global/facturacion/{comercio}/pagar` responde `403`.
- Los tests confirman que no se crea ningún `Payment` ni se modifica `vencimiento_pago`/`status` en ese escenario.
- No se ejecutó ningún cobro contra Mercado Pago ni se generó ningún link real.
- No se registraron pagos aprobados ficticios ni se activaron suscripciones por simulación.

---

## 9. Riesgos remanentes

1. **Duplicados sin lock de comercio.** La protección contra doble pago del mismo período depende de que todo flujo nuevo bloquee la fila del comercio. Si en el futuro se inserta un pago por fuera de `PagoManualService`/`aplicarPago`, el unique de idempotencia no alcanza. La restricción unique parcial `(payable, period_end)` queda pendiente por la decisión de pagos parciales.
2. **Método de pago de Mercado Pago.** El callback actual no aporta el detalle del método; se registra `provider = mercadopago` y `payment_method = null`. No se inventa un método.
3. **Responsable en webhook.** Un pago aplicado por webhook queda con `created_by_id = null` porque no hay usuario autenticado. Es esperado y queda auditado como anónimo.
4. **Duración de plan no persistida.** `calcularPeriodo()` lee `duracion_meses` si existe, pero `planes` no tiene esa columna. Hoy todos los planes se tratan como mensuales.
5. **`nuevaFechaVencimiento()` duplicada.** Se conserva por compatibilidad y tests; usa una regla levemente distinta. Todo flujo nuevo debe usar `calcularPeriodo()`. Conviene deprecarla en una fase de limpieza.
6. **`comercios.plan` legacy.** Sigue existiendo y puede confundir a código viejo. No se renombró ni se eliminó.
7. **Suite completa con 23 fallos preexistentes.** Pertenecen a B1 y a módulos ajenos; no se corrigieron en B3.
8. **`activity_log` no es libro de pagos.** El historial financiero se lee de `payments`; el log es auditoría complementaria.

---

## 10. Decisiones comerciales pendientes

1. **Precios comerciales definitivos.** Definir la tarifa real de cada plan y reemplazar la fuente de verdad. Hoy conviven valores de prueba en la base (`1/2/3`) y en el seeder (`8000/15000/35000`); ninguno debe tratarse como oficial.
2. **Duración de planes.** Si se van a ofrecer planes trimestrales/anuales, agregar la columna de duración y persistirla; el algoritmo ya la soporta.
3. **Pagos parciales, prorrateos y ajustes.** El esquema actual registra el precio completo del plan. Si se permiten pagos parciales, hay que definir la regla y luego evaluar la restricción unique parcial por período.
4. **Tipos y motivos de suspensión.** Hoy sólo existe `status = suspendido`. Si se necesita distinguir suspensión manual, automática por mora o por decisión comercial, requiere campos nuevos y una fase propia.
5. **Habilitación de cobros manuales.** Recién con precios confirmados, activar `VENDAR_PRECIOS_CONFIRMADOS=true` y `VENDAR_COBROS_HABILITADOS=true`, y validar el flujo completo de punta a punta.
6. **Integración real de Mercado Pago para el Administrador Global.** Fuera del alcance de B3; requiere decisión de negocio y credenciales de plataforma.
7. **Firma de webhooks de Mercado Pago.** Revisar `MercadopagoGateway::verifyWebhookSignature()` y la presencia de `MERCADOPAGO_WEBHOOK_SECRET` antes de producción; es deuda preexistente, no introducida en B3.
8. **Deriva de esquema preexistente.** `comercios.plan` sigue como legacy y `planes` conserva columnas no usadas por el modelo actual. No se tocaron en B3.

**Skills corregidas en la revisión final (2026-11-15):**

- `.opencode/skills/vendar-business-rules/SKILL.md`: un período vencido o sin vencimiento arranca en la **fecha actual**, no en la fecha de pago; la `fecha_pago` sólo alimenta `approved_at`. Se documentaron los dos caminos (`PagoManualService::registrar` por `idempotency_key` y `SuscripcionService::aplicarPago` por `paymentId`), los campos nuevos de `payments` y el uso de `planContratado()`.
- `.opencode/skills/vendar-testing/SKILL.md`: misma corrección de período; se aclaró la idempotencia manual vs. Mercado Pago; se agregó el baseline histórico de B1 (`376 deprecated / 1415 assertions`) y el actual de B3 (`431 / 1709`), ambos con los mismos 23 fallos, con la regla de comparar nombres, mensajes y causas.
- `.opencode/skills/vendar-multitenancy/SKILL.md`: la trampa de `Comercio.plan` ahora recomienda `plan_id`, `planContratado()` o `plan()`, y aclara que `planContratado()` es la relación inequívoca del código nuevo.
- `.opencode/skills/vendar-architecture/SKILL.md`: se agregó `PagoManualService` a los servicios reutilizables y se documentó la idempotencia de suscripciones con `idempotency_key`, `(provider, gateway_transaction_id)` y `lockForUpdate()` sobre el comercio.

No quedan contradicciones conocidas entre estas Skills y el comportamiento implementado en B3.

---

## 11. Funcionalidades deshabilitadas a propósito

- Cobros manuales reales: `cobros_habilitados = false` por defecto.
- Precios como tarifa oficial: `precios_confirmados = false` por defecto.
- MRR y proyecciones monetarias: ocultos hasta confirmar precios.
- Link de Mercado Pago del Administrador Global: `disponible = false`, `cobro_generado = false`.
- Cobros reales contra Mercado Pago, webhooks nuevos y links inventados: no implementados.
- Registro de pagos aprobados ficticios y activación por simulación: bloqueados por tests.

---

## 12. Conclusión

B3 ETAPA 2 queda implementado, corregido y verificado. El módulo calcula estados y períodos de forma consistente, registra pagos de forma transaccional y auditable, expone historial separado de ventas y no filtra credenciales. Los cobros reales permanecen **deshabilitados por configuración explícita** hasta que el negocio defina los precios comerciales.

Los 37 tests B3, los 25 unitarios de suscripción y los 69 de regresión de suscripciones pasan. La suite completa mantiene los 23 fallos preexistentes de BUILD B1, sin incorporar fallos nuevos.

No se avanzó a B4.

---

## 13. Revisión final independiente (2026-11-15)

### 13.1 Alcance y método

Se revisó el diff completo de B3 contra la línea base `c03fc1d`, incluyendo el working tree con las correcciones posteriores al commit `4a008fd`, y se usaron las Skills `vendar-architecture`, `vendar-multitenancy`, `vendar-business-rules`, `vendar-testing`, `code-reviewer`, `secure-code-guardian` y `test-master`.

Se verificaron: relaciones Eloquent, transacciones y excepciones, locks y concurrencia, idempotencia, integridad de datos, autorización, aislamiento multi-tenant, seguridad de credenciales, coherencia backend/frontend y regresiones.

**Restricción respetada:** no se modificó código de aplicación, no se ejecutaron migraciones, no se habilitaron cobros reales, no se integró Mercado Pago, no se cambiaron precios y no se inició B4. Sólo se corrigieron documentación y Skills propias.

**Sobre la corrida completa:** la evidencia de ejecución es la registrada en §7.3 (`431 deprecated, 23 failed, 1 risky, 1 passed`, 1709 aserciones, 749.58 s). En esta revisión los contenedores Docker estaban detenidos y no se volvió a correr la suite para no alterar la base `testing` con `RefreshDatabase`. La comparación de los 23 fallos se hizo contra los archivos de test y controladores (sin cambios en B3) y la salida registrada.

### 13.2 Hallazgos clasificados

#### Críticos

Ninguno.

#### Altos

**A1. XSS almacenado en el modal de pago manual (latente, se activa al habilitar cobros).**

- **Archivos/líneas:** `resources/js/Pages/AdminGlobal/Facturacion.vue:113-116` (opción `html` de SweetAlert con `${comercio.nombre}` sin escapar); `app/Http/Controllers/GlobalAdminController.php:378-402` (`aprobarSolicitud` guarda `nombre_comercio`); `resources/js/Pages/AdminGlobal/Solicitudes.vue:22` (prefill desde `solicitud.nombre`); `app/Http/Controllers/Auth/RegisteredUserController.php:39` (el nombre del registro sólo valida `string|max:255`).
- **Evidencia:** un usuario puede registrarse con un nombre como `<img src=x onerror=...>`. El modal de aprobación de `Solicitudes.vue` escapa el nombre para mostrarlo, pero el valor del input se envía sin sanear y queda guardado en `comercios.nombre`. Cuando el Administrador Global abre el modal de pago manual, `Facturacion.vue` inyecta ese nombre en `html`, que SweetAlert2 interpreta como HTML. El XSS se ejecuta en la sesión del Administrador Global, con acceso a todos los comercios.
- **Condición:** el botón de pago sólo se renderiza con `cobros_habilitados && precios_confirmados` (`Facturacion.vue:272`). Hoy ambas banderas están en `false`, por eso no es crítico en producción; se vuelve explotable al habilitar cobros.
- **Recomendación:** no interpolar `comercio.nombre` en `html`; usar `text` o construir el nodo con `textContent`, o escapar el valor con una función de escape HTML. Además, sanear/normalizar `comercios.nombre` al escribir. No habilitar cobros hasta corregirlo.
- **Estado:** documentado; no se modificó código en esta revisión.

#### Medios

**M1. El registro de pago manual no muestra éxito ni error de negocio.**

- **Archivos/líneas:** `resources/js/Pages/AdminGlobal/Facturacion.vue:138-165` (el `preConfirm` dispara `router.post` con `onSuccess` pero sin `onError`); `app/Http/Controllers/GlobalAdminController.php:465-474` (`withErrors(['pago' => ...])` y `with('exito', ...)`); `Facturacion.vue` no usa `usePage()` ni renderiza `errors.pago`/flash.
- **Evidencia:** si el backend rechaza el pago (importe distinto, período duplicado, plan sin precio, etc.), el modal se cierra y el mensaje de `errors.pago` no se muestra. Si el pago se registra, el flash `exito` tampoco se muestra. El administrador no sabe si la operación se completó ni por qué falló; puede reintentar sin contexto.
- **Recomendación:** agregar `onError` para mostrar `errors.pago` y `onSuccess` para confirmar; o devolver una promesa y mantener el modal abierto hasta la respuesta. Mostrar también el flash de éxito.
- **Estado:** documentado; no se modificó código.

**M2. Un pago manual no invalida la preferencia de Mercado Pago en vuelo.**

- **Archivos/líneas:** `app/Services/Suscripcion/PagoManualService.php:200-204` (reactiva y pone `pending_plan_id = null`), pero no llama a `SuscripcionService::limpiarPagoEnVuelo()`; `app/Services/Suscripcion/SuscripcionService.php:436-443` (método público disponible); `app/Http/Controllers/MercadoPagoNotificacionController.php:191-201` (si no hay `pending_plan_id`, registra un warning y devuelve `already_processed` sin crear `Payment` ni extender).
- **Evidencia:** si un comercio tiene un checkout de Mercado Pago abierto y el Administrador Global registra un pago manual, el cache `suscripcion:pago-en-vuelo:{id}` sigue vivo y `MiPlan.vue` puede ofrecer “Reintentar pago” con el link viejo (`MiPlan.vue:503-511`). Si el usuario paga ese link, Mercado Pago cobra, pero el webhook no aplica nada y no deja un `Payment` de suscripción para conciliar.
- **Recomendación:** al registrar un pago manual, limpiar el pago en vuelo y, si es posible, cancelar/expirar la preferencia en Mercado Pago; si el pago igual se aprueba, registrarlo como pago “superseded” para conciliación y auditoría. No habilitar cobros manuales sin resolver esta interacción.
- **Estado:** documentado; no se modificó código.

#### Bajos

**B1. Open redirect en el retorno de Mercado Pago.**

- **Archivos/líneas:** `app/Http/Controllers/SuscripcionController.php:355-365`.
- **Evidencia:** si `origin` falta o no está en la allowlist, el fallback es `$request->getSchemeAndHttpHost()`. Un Host header controlado por un proxy mal configurado puede redirigir a un dominio arbitrario. En un navegador normal el Host coincide con la URL visitada, por eso la explotación es limitada.
- **Recomendación:** usar `config('app.url')` o una allowlist de hosts como fallback; no confiar en el Host para redirecciones.

**B2. El aviso de precios pendientes se muestra aunque `precios_confirmados` sea `true`.**

- **Archivos/líneas:** `app/Http/Controllers/GlobalAdminController.php:259` (`aviso_precios` hardcodeado); `resources/js/Pages/AdminGlobal/Facturacion.vue:116` (el modal también lo dice siempre); la prop `precios_confirmados` sólo se usa para mostrar el botón (`:272`), no para condicionar el aviso.
- **Evidencia:** con precios confirmados, la UI sigue afirmando que el precio no está confirmado. Es contradictorio y puede confundir al operador.
- **Recomendación:** condicionar el texto y el banner a `precios_confirmados`.

**B3. Carrera de idempotencia entre comercios distintos.**

- **Archivos/líneas:** `app/Services/Suscripcion/PagoManualService.php:125-148` (consulta y luego inserta); `database/migrations/2026_11_06_000001_add_tracking_to_payments_table.php` (unique global de `idempotency_key`); `GlobalAdminController.php:465-469` sólo captura `PagoManualInvalidoException`/`PagoManualDeshabilitadoException`.
- **Evidencia:** dos comercios distintos que envían concurrentemente la misma `idempotency_key` no comparten el lock de comercio. Ambos pueden no encontrar la fila y uno recibe una violación de unique (`QueryException`), devolviendo 500 en lugar de un 422/409 controlado.
- **Recomendación:** capturar la violación de unique y devolver un error de negocio, o usar una clave compuesta por comercio y una validación explícita.

**B4. Historial limitado a 100 filas sin paginación.**

- **Archivos/líneas:** `app/Http/Controllers/GlobalAdminController.php:324` (`limit(100)`); `:283` (`'total' => count($historial['pagos'])`).
- **Evidencia:** con más de 100 pagos, los más viejos no se pueden consultar; el `total` del JSON es la cantidad devuelta, no el total real.
- **Recomendación:** paginar el historial y devolver el total real, o agregar un rango de fechas obligatorio.

**B5. Reutilización de `idempotency_key` con un payload distinto devuelve el pago original sin validar.**

- **Archivos/líneas:** `app/Services/Suscripcion/PagoManualService.php:134-146`.
- **Evidencia:** si la misma clave se reutiliza con otro importe, método o referencia, el servicio devuelve el `Payment` existente y el controlador responde “Pago #id registrado”. No se compara el payload.
- **Recomendación:** guardar un fingerprint del request (importe, método, período, referencia) y rechazar la reutilización con payload distinto.

**B6. Validación de período incompleta en filtros.**

- **Archivos/líneas:** `app/Http/Controllers/GlobalAdminController.php:275` (`regex:/^\d{4}-\d{2}$/`) y `:315` (`Carbon::createFromFormat('Y-m', ...)`).
- **Evidencia:** `2026-13` pasa la regex; según la versión de Carbon puede normalizarse a 2027-01 o lanzar una excepción no controlada. El endpoint es sólo para Administrador Global, por eso el impacto es bajo.
- **Recomendación:** validar mes `01-12` o capturar la excepción y devolver 422.

**B7. El pago manual sólo renueva el plan actual y cancela un upgrade pendiente.**

- **Archivos/líneas:** `app/Services/Suscripcion/PagoManualService.php:103` (usa `planContratado`) y `:203` (`pending_plan_id = null`).
- **Evidencia:** el modal no permite elegir plan; si el comercio tenía un upgrade pendiente, el pago manual lo descarta. No es un bug si el alcance es sólo renovación, pero debe quedar explícito.
- **Recomendación:** documentar la limitación o agregar selección de plan si el negocio necesita upgrades manuales.

**B8. El pago manual reactiva cualquier comercio suspendido.**

- **Archivos/líneas:** `app/Services/Suscripcion/PagoManualService.php:200-204`.
- **Evidencia:** no distingue suspensión por mora de suspensión manual/abusiva. Un pago manual reactiva ambos casos.
- **Recomendación:** distinguir tipos/motivos de suspensión o exigir una acción explícita de reactivación. Ya está listado como decisión comercial pendiente en §10.4.

**B9. Anti-duplicado por período asimétrico y limitado.**

- **Archivos/líneas:** `app/Services/Suscripcion/PagoManualService.php:158-172` (chequea `period_end`); `app/Services/Suscripcion/SuscripcionService.php:266-268` (`aplicarPago` sólo chequea `paymentId`).
- **Evidencia:** el camino manual rechaza un pago aprobado existente con el mismo `period_end`, pero el camino de Mercado Pago no. Además, como el período se recalcula desde el vencimiento ya extendido, dos pagos secuenciales distintos se aplican como dos renovaciones. La idempotencia por clave sólo cubre reintentos idénticos.
- **Recomendación:** si el negocio quiere bloquear doble cobro de la misma intención, agregar una clave de operación/ventana temporal compartida por ambos caminos; si dos pagos reales deben extender dos períodos, documentarlo.

**B10. Importes redondeados a pesos enteros en la tabla.**

- **Archivos/líneas:** `resources/js/Pages/AdminGlobal/Facturacion.vue:30-35` (`maximumFractionDigits: 0`).
- **Evidencia:** un importe con centavos se muestra redondeado; el valor almacenado y el validado por el backend conservan decimales.
- **Recomendación:** mostrar dos decimales si los precios pueden tener centavos.

#### Sin observaciones

- **Aislamiento multi-tenant:** las rutas nuevas `/admin-global/facturacion*` están bajo `role:Administrador Global`; `historialPagos` no acepta un comercio fuera del alcance del admin global; `cancelarPago` usa el `comercio_id` del usuario autenticado y no recibe IDs; `PagoManualService` rechaza una `idempotency_key` de otro `payable`. No se encontró fuga entre comercios.
- **Autorización:** `marcarPagado` valida la bandera, el rol y los campos; `generarLinkMP` está deshabilitado; un usuario de comercio recibe 403. La exención de `suscripcion.cancelar-pago` no saltea `auth` ni el rol.
- **Credenciales:** `ConfiguracionController` elimina `access_token`/`client_secret` de las props; `Comercio` oculta `mp_access_token`/`payway_public_key`; no se encontró token de Mercado Pago en props de B3, logs ni respuestas de error. `PaymentService` mantiene el token en el servidor.
- **Cancelación de pago:** `VerificarEstadoCuenta` sólo exime `suscripcion.cancelar-pago` de la redirección por suspensión; la ruta sigue con `auth` + `role:SuperAdmin|Administrador Global|Encargado`; `liberarPagoEnVuelo` sólo limpia cache y `pending_plan_id`, sin tocar pagos ni vencimiento.
- **Idempotencia del mismo comercio:** `lockForUpdate()` sobre `comercios` serializa los intentos concurrentes; el unique de `idempotency_key` y el chequeo de `period_end` cubren el caso normal. La carrera cross-comercio queda en B3.
- **Períodos:** pagos anticipados, vencidos, día límite y duración (`duracion_meses` con fallback mensual) son correctos; `addMonthsNoOverflow()` evita el corrimiento de fin de mes; el día de vencimiento se etiqueta `Por Vencer` y el acceso dura hasta `endOfDay()`.
- **Migración:** aditiva, reversible, sin borrar datos; columnas, índice, unique y FK verificados en `testing` y `vendar` local. No se volvió a ejecutar en esta revisión.
- **Transacciones:** el pago manual y la aplicación de Mercado Pago corren dentro de `DB::transaction()` con lock de comercio; la reactivación ocurre después de persistir el pago.
- **Coherencia backend/frontend:** los campos de plan/precio, estados, historial y filtros coinciden; las acciones simuladas de Mercado Pago están ocultas o deshabilitadas.

### 13.3 Comparación exacta con el baseline B1

`docs/informes/BUILD-B1.md` §10.4 documenta la distribución de los 23 fallos por suite, no los nombres y mensajes individuales. Para no asumir que “23 = 23”, se compararon los archivos de test (sin cambios entre `c03fc1d` y HEAD), los controladores involucrados (ninguno en la lista de archivos B3, salvo `VerificarEstadoCuenta`) y la salida actual registrada en §7.3.

La única modificación B3 en un archivo que participa de los fallos de autenticación es `VerificarEstadoCuenta` (working tree): agrega `suscripcion.cancelar-pago` a la lista de excepciones y normaliza `Carbon`/`endOfDay`. No agrega ni quita `password.*` ni cambia el nombre del POST `/register`. B3 sí tocó `MercadoPagoNotificacionController`, pero el fallo de `WebhookSeguridadTest` es del flujo `pedidos.estado` (stock de pedidos web), que no pasa por ese controlador. Los controladores de caja, pedidos, productos, transferencias, proveedores y ventas canceladas no están en la lista de archivos B3.

| # | Test | Evidencia / mensaje | Causa | ¿B3? |
|---:|---|---|---|---|
| 1 | `Auth\PasswordResetTest::test_reset_password_link_screen_can_be_rendered` | Espera 200, recibe 302 a login. | `VerificarEstadoCuenta` no exime `password.*`; el invitado se redirige. | No |
| 2 | `Auth\PasswordResetTest::test_reset_password_link_can_be_requested` | No se envía la notificación; el POST se redirige. | Misma causa: `password.*` no exento. | No |
| 3 | `Auth\PasswordResetTest::test_reset_password_screen_can_be_rendered` | Espera 200, recibe 302. | Misma causa. | No |
| 4 | `Auth\PasswordResetTest::test_password_can_be_reset_with_valid_token` | No se puede obtener el token del flujo. | Misma causa. | No |
| 5 | `Auth\RegistrationTest::test_new_users_can_register` | “The user is not authenticated.” | El POST `/register` no tiene nombre de ruta y el middleware lo redirige. | No |
| 6 | `CrossTenantAttackTest::test_ct10_cerrar_turno_de_otro_comercio_da_404` | Espera 404, recibe 302 por validación. | Payload con `saldo_final_transf_real`/`saldo_final_mp_real`; el controlador exige `saldo_final_transferencias_real`/`saldo_final_tarjetas_real`. | No |
| 7 | `Modulo1_ProductosTest::test_superadmin_a_puede_ver_auditoria_de_producto_b_porque_no_hay_scoping` | Espera 200, recibe 403. | `ProductoController::auditoria` ahora bloquea cross-tenant; el test codifica el gap viejo. | No |
| 8 | `Modulo3_CajaTest::test_admin_a_puede_cerrar_turno` | Espera JSON de éxito, recibe 302 por validación. | Payload con nombres de campo legacy. | No |
| 9 | `Modulo3_CajaTest::test_admin_a_no_puede_cerrar_turno_de_b` | Espera 404, recibe 302 por validación. | Mismo payload legacy; la validación corre antes del chequeo de tenant. | No |
| 10 | `Modulo3_CajaTest::test_admin_a_no_puede_abrir_turno_en_caja_de_b` | Espera 404, recibe 403. | El controlador aborta 403 para caja de otro comercio. | No |
| 11 | `Modulo4_PedidosWebTest::test_admin_a_no_puede_actualizar_estado_pedido_de_b` | Espera 404, recibe 403. | La policy responde 403; el test espera 404. | No |
| 12 | `Modulo4_PedidosWebTest::test_admin_a_no_puede_actualizar_pago_pedido_de_b` | Espera 404, recibe 403. | Misma causa. | No |
| 13 | `Modulo7_TransferenciasTest::test_admin_a_no_puede_cancelar_transferencia_ya_despachada` | `BadMethodCallException: Method ...::assertSessionHas does not exist`. | El test llama `$this->assertSessionHas()` en vez de hacerlo sobre la respuesta. | No |
| 14 | `MultiTenantIsolationTest::test_proveedor_usuario_puede_editar_su_propio_proveedor` | Falta la sesión `success`; hay error de validación. | El CUIT `30-55555555-5` no cumple `regex:/^\d{11}$/`. | No |
| 15 | `VentasCanceladasHistorialTest::test_no_toma_ventas_canceladas_de_otra_sucursal` | `assertContains` falla porque la lista está vacía. | `filasCanceladas()` lee `$page->toArray()['ventas']['data']`; `toArray()` devuelve la página completa con `props`. | No |
| 16 | `VentasCanceladasHistorialTest::test_segunda_pagina_muestra_las_siguientes_sin_repetir` | Espera 7 IDs, obtiene 0. | `idsDePagina()` usa el mismo helper roto. | No |
| 17 | `VentasCanceladasHistorialTest::test_la_paginacion_recorre_todas_las_paginas_sin_perder_ni_repetir` | Espera 16 IDs, obtiene 0. | Mismo helper. | No |
| 18 | `VentasCanceladasHistorialTest::test_la_ultima_pagina_trae_solo_los_restantes` | Espera 2 IDs, obtiene 0. | Mismo helper. | No |
| 19 | `VentasCanceladasHistorialTest::test_muestra_total_metodo_de_pago_y_fecha_de_la_venta` | `Undefined array key 0`. | `filasCanceladas()[0]` sobre lista vacía. | No |
| 20 | `VentasCanceladasHistorialTest::test_lee_quien_y_cuando_anulo_desde_el_log_de_auditoria` | `Undefined array key 0`. | Mismo helper. | No |
| 21 | `VentasCanceladasHistorialTest::test_sin_evento_de_auditoria_no_inventa_fecha_ni_usuario` | `Undefined array key 0`. | Mismo helper. | No |
| 22 | `VentasCanceladasHistorialTest::test_el_filtro_de_estado_del_historial_general_sigue_funcionando` | `assertContains` falla porque el array está vacío. | `idsVentasGenerales()` tiene el mismo error de `toArray()`. | No |
| 23 | `WebhookSeguridadTest::test_entrega_pedido_web_crea_movimiento_stock` | `Failed asserting that 0 is greater than 0`. | `$movimientosAntes` cuenta todos los movimientos; `$movimientosDespues` filtra sólo `Pedido Web Entregado`; no encuentra el movimiento de entrega. | No |

**Nota sobre los 8 fallos de `VentasCanceladasHistorialTest`:** el test `test_la_venta_completada_no_aparece_en_la_pantalla_de_canceladas` también usa `filasCanceladas()`, pero pasa porque `assertNotContains` sobre una lista vacía es verdadero. Eso explica por qué son 8 y no 9 los fallos de ese archivo.

**Conclusión del baseline:** los 23 fallos actuales coinciden con los 23 preexistentes de B1 en cantidad, suite y causa; ninguno se origina en B3. No se corrigieron porque están fuera del alcance de B3 y varios son tests que codifican contratos viejos o tienen bugs de test.

### 13.4 Verificación de esta revisión

- Se inspeccionaron todos los archivos del diff B3 y el working tree.
- Se verificaron las líneas de `PagoManualService`, `SuscripcionService`, `GlobalAdminController`, `SuscripcionController`, `VerificarEstadoCuenta`, `Comercio`, `Payment`, la migración, `Facturacion.vue`, `MiPlan.vue` y los tests B3.
- Se revisaron credenciales y props de Inertia: no se encontró ningún token de Mercado Pago expuesto.
- No se ejecutaron tests nuevos en esta revisión porque los contenedores Docker estaban detenidos; la evidencia de la suite sigue siendo la de §7.3. No se ejecutaron migraciones ni se alteró la base.
- El working tree mantiene las correcciones B3 sin commit; esta revisión no las commiteó.

### 13.5 Restricciones respetadas

- [x] No se implementaron funcionalidades nuevas.
- [x] No se modificaron precios.
- [x] No se habilitaron cobros reales.
- [x] No se integró Mercado Pago.
- [x] No se modificó la base de datos ni se ejecutaron migraciones.
- [x] No se inició BUILD B4.
- [x] No se hicieron refactors ajenos a B3.
- [x] Sólo se corrigieron documentación y Skills propias.

### 13.6 Conclusión y recomendación

B3 está funcionalmente completo: los 37 tests B3, los 25 unitarios de suscripción y los 69 de regresión pasan; los 23 fallos de la suite completa son preexistentes y están identificados nombre por nombre.

Antes de habilitar cobros reales hay que resolver **A1** (XSS almacenado) y **M1/M2** (feedback del pago manual y preferencia de Mercado Pago en vuelo). Los hallazgos **B1-B10** son mejoras o decisiones comerciales que no bloquean el cierre de B3, pero conviene resolverlos antes de producción.

No se modificó código de aplicación en esta revisión. Se queda a la espera de aprobación para cerrar B3; no se inicia B4.

---

## 14. Corrección B3.1: A1, M1 y M2 (2026-11-16)

### 14.1 Objetivo y alcance

Corregir exclusivamente los tres hallazgos prioritarios de la revisión §13:

- **A1:** XSS almacenado en el modal de pago manual.
- **M1:** falta de feedback visible al registrar un pago manual.
- **M2:** invalidación incompleta de la preferencia de Mercado Pago en vuelo y pagos aprobados sin conciliación.

**Fuera de alcance:** B1-B10, cambios de precios o duraciones, migraciones, cobros reales, integración/activación de Mercado Pago y BUILD B4.

### 14.2 Archivos creados

- `resources/js/Utils/pagoManual.js`: HTML estático del modal, inicialización segura con `textContent`/`value` y helpers de feedback.
- `resources/js/Utils/pagoManual.test.mjs`: pruebas Node de A1 y M1.

### 14.3 Archivos modificados

- `resources/js/Pages/AdminGlobal/Facturacion.vue`: usa el HTML estático y los helpers; agrega `onSuccess`/`onError`.
- `app/Services/Suscripcion/PagoManualService.php`: limpia el pago en vuelo después del commit sólo cuando el pago manual es nuevo.
- `app/Services/Suscripcion/SuscripcionService.php`: agrega `registrarPagoSuperseded()` idempotente y transaccional.
- `app/Http/Controllers/MercadoPagoNotificacionController.php`: registra pagos aprobados sin `pending_plan_id` como `superseded` y limpia el cache.
- `tests/Feature/BuildB3_FacturacionTest.php`: cinco pruebas nuevas de M1/M2.
- `docs/informes/BUILD-B3.md`: esta sección.

No se modificaron migraciones, precios, duraciones de planes, `MiPlan.vue`, `routes/web.php` ni `GlobalAdminController.php` en B3.1.

### 14.4 Decisiones técnicas

**A1 — XSS almacenado**

- `MODAL_PAGO_MANUAL_HTML` es un string completamente estático con IDs (`b3-comercio-nombre`, `b3-importe`, `b3-metodo`, `b3-fecha`).
- `inicializarModalPagoManual()` completa los valores en `didOpen`:
  - `comercio.nombre` se asigna con `textContent`;
  - importe y fecha con `value`/`max`;
  - métodos con `option.textContent`.
- Se eliminaron del `html` de SweetAlert las interpolaciones de `comercio.nombre`, `comercio.precio_configurado`, `hoy` y las opciones de método.
- No se alteró el dato almacenado de `comercios.nombre`: la defensa se aplica en la salida, que es el punto explotable. Normalizar el dato al escribir queda como defensa adicional opcional.

**M1 — Feedback del pago manual**

- `onSuccess` muestra una alerta de éxito con `mensajeExitoPago(page)`, que usa `page.props.flash.exito` o `page.flash.exito` y tiene fallback.
- `onError` muestra una alerta de error con `mensajeErrorPago(errors)`, que prioriza/une `errors.pago` y los demás errores de validación, con fallback.
- Ambos mensajes se muestran con la opción `text` de SweetAlert, no con `html`, para que un mensaje del servidor no se interprete como HTML.
- Se mantiene el patrón visual de `Solicitudes.vue` (SweetAlert para éxito/error) y se agregó el contrato backend flash/errores en pruebas.

**M2 — Preferencia de Mercado Pago en vuelo**

- `PagoManualService::registrar()` ahora devuelve internamente `['pago' => Payment, 'creado' => bool]`.
- Después del commit, si el pago es nuevo, llama a `SuscripcionService::limpiarPagoEnVuelo($comercio->id)`. Así `MiPlan.vue` deja de ofrecer el checkout viejo.
- `pending_plan_id` ya se limpia dentro de la transacción del pago manual.
- Un reintento idempotente con la misma clave devuelve el pago original y **no** limpia una preferencia nueva generada después.
- Si un webhook aprobado llega sin `pending_plan_id`, `registrarPagoSuperseded()` crea un `Payment` idempotente por `provider + gateway_transaction_id`, con metadata `tipo=suscripcion`, `origen=mercadopago`, `conciliacion=superseded`, `motivo=sin_pending_plan_id`.
- Ese pago no se aplica a la suscripción ni extiende vencimiento: queda para conciliación y auditoría. El webhook limpia el cache y responde `already_processed` para conservar el contrato existente.
- La operación usa `DB::transaction()` y lock sobre el comercio, igual que `aplicarPago()`.

### 14.5 Bugs corregidos

| ID | Corrección |
|---|---|
| A1 | El nombre del comercio y los demás valores dinámicos ya no se interpolan en el HTML del modal. Un nombre malicioso se muestra como texto y no se ejecuta. |
| M1 | El operador recibe confirmación de éxito o el error de negocio/validación después de registrar el pago. |
| M2 | Un pago manual nuevo invalida la preferencia en vuelo; un pago aprobado de una preferencia vieja queda registrado como `superseded` para conciliación en lugar de descartarse. |

B1-B10 siguen abiertos y no se corrigieron en B3.1.

> **Nota de revisión final (§15):** la corrección M2 de esta sección sólo cubre el caso en que `pending_plan_id` ya es `null`. La revisión independiente encontró que un pago de una preferencia vieja con una preferencia nueva pendiente se aplica al plan actual y no queda como `superseded`; ver B31-C1.

### 14.6 Pruebas ejecutadas y resultados

| Verificación | Comando | Resultado |
|---|---|---|
| JS A1/M1 | `node --test resources/js/Utils/pagoManual.test.mjs` | 3/3 pass, 0 fail |
| B3 completo | `docker compose exec -T laravel.test php artisan test tests/Feature/BuildB3_FacturacionTest.php --compact` | 42/42 pass, 241 assertions, 0 fail (42 deprecated preexistentes) |
| Regresión suscripciones | `docker compose exec -T laravel.test php artisan test tests/Feature/Modulo8_SuscripcionesTest.php --compact` | 69/69 pass, 507 assertions, 0 fail (69 deprecated preexistentes) |
| Unit suscripción | `docker compose exec -T laravel.test php artisan test tests/Unit/Suscripcion/SuscripcionServiceTest.php --compact` | 25/25 pass, 56 assertions, 0 fail (25 deprecated preexistentes) |
| Build frontend | `npm run build` | OK, 954 módulos transformados |
| Estilo PHP | `docker compose exec -T laravel.test vendor/bin/pint --test app/Services/Suscripcion/PagoManualService.php app/Services/Suscripcion/SuscripcionService.php app/Http/Controllers/MercadoPagoNotificacionController.php tests/Feature/BuildB3_FacturacionTest.php` | PASS, 4 archivos |
| Sintaxis PHP | `php -l` sobre los PHP modificados | Sin errores |
| Whitespace | `git diff --check` | Sin errores |

Los “deprecated” son el aviso preexistente `Constant PDO::MYSQL_ATTR_SSL_CA is deprecated`; no son fallos de test.

**Incidencia de ejecución:** una corrida intermedia de `BuildB3_FacturacionTest` falló con `Vite manifest not found` porque `npm run build` se ejecutó en paralelo y regeneró `public/build` mientras corría el test. Se repitió después del build y pasó 42/42. No fue un fallo de código.

**Alcance de la corrida:** no se ejecutó la suite completa. Se corrieron las suites afectadas y su regresión directa; la suite completa mantiene los 23 fallos preexistentes de BUILD B1 documentados en §13.3.

### 14.7 Criterios de aceptación

- [x] A1: no queda interpolación de datos del comercio en el `html` del modal; el nombre se asigna con `textContent`.
- [x] A1: una prueba asigna un payload malicioso y verifica que se conserva como texto, sin usar `innerHTML`.
- [x] M1: el éxito usa el flash del backend y los errores usan `errors.pago`, ambos visibles con SweetAlert `text`.
- [x] M1: hay pruebas JS de los helpers y pruebas PHP del flash/errores del controlador.
- [x] M2: un pago manual nuevo limpia `pagoEnVuelo()` y `pending_plan_id`.
- [x] M2: un reintento idempotente no invalida una preferencia nueva.
- [x] M2: un webhook aprobado sin `pending_plan_id` crea un pago `superseded` idempotente, sin extender la suscripción.
- [x] No se modificaron precios, duraciones, migraciones ni estructura de base de datos.
- [x] No se habilitaron cobros reales ni se hicieron llamadas reales a Mercado Pago.
- [x] No se inició BUILD B4 ni se corrigieron B1-B10.

### 14.8 Pendientes

- Resolver B1-B10 en una fase posterior.
- Definir el flujo operativo para aplicar o devolver un pago `superseded`; B3.1 sólo garantiza que quede registrado para conciliación.
- Evaluar normalizar `comercios.nombre` al escribir como defensa adicional (no necesario para cerrar A1).
- La suite completa sigue con los 23 fallos preexistentes de B1; no se tocaron en B3.1.

### 14.9 Conclusión

La implementación de B3.1 se registra en esta sección con cambios acotados, pruebas específicas y sin alterar reglas comerciales ni integrar Mercado Pago. La revisión final independiente de §15 concluye que A1 y M1 son aprobables con observaciones, pero **M2 no es aprobable** en su estado actual por B31-C1, B31-A1 y B31-A2. Se queda a la espera de aprobación o de una nueva fase de corrección; no se inicia otra etapa.

---

## 15. Revisión final independiente de B3.1 (2026-11-16)

### 15.1 Alcance, método y veredicto

Se revisó el diff completo del working tree contra `HEAD`, separando los cambios de B3.1 de las correcciones previas de B3. La revisión se concentró en A1, M1 y M2, con lectura de los flujos de pago manual, generación de preferencias, webhook de Mercado Pago, aplicación de pagos, historial y pruebas.

**Alcance verificado de B3.1:**

- A1: HTML estático del modal y asignación segura de datos dinámicos.
- M1: feedback de éxito/error del pago manual.
- M2: limpieza de la preferencia en vuelo, reintento idempotente y registro `superseded`.
- No se modificaron migraciones, precios, duraciones, integración real de Mercado Pago, flujo de aplicación/devolución de `superseded`, B1-B10 ni BUILD B4.

El diff contra `HEAD` también contiene correcciones de B3 que no pertenecen a B3.1: `GlobalAdminController.php`, `VerificarEstadoCuenta.php`, `routes/web.php`, campos del modal manual y validaciones del servicio manual. No se encontraron cambios de B3.1 fuera de A1, M1 y M2.

**Veredicto por bloque:**

| Bloque | Veredicto | Detalle |
|---|---|---|
| A1 | Aprobable | La implementación es segura; la prueba es unitaria y no cubre la integración real con el componente. |
| M1 | Aprobable con observaciones | El flujo principal de éxito/validación funciona; los errores inesperados no usan el feedback personalizado. |
| M2 | **No aprobable** | Quedan escenarios de dinero real sin resolver: pago viejo aplicado al plan pendiente actual, webhook tardío que libera una preferencia nueva y carrera de limpieza post-commit. |

**Conclusión global: No aprobable en su estado actual.** A1 y M1 pueden darse por cerrados con observaciones menores, pero M2 no cumple el criterio de aceptación. Los hallazgos críticos son preexistentes al cambio B3.1, pero B3.1 asumió M2 y su corrección no los cubre.

### 15.2 Hallazgos clasificados

| ID | Severidad | Hallazgo | Bloquea B3.1 |
|---|---|---|---|
| B31-C1 | **Crítico** | Un pago aprobado de una preferencia vieja se aplica al `pending_plan_id` actual y no se registra como `superseded`. También es explotable por `confirmarUpgrade` con un pago barato y un plan caro. | Sí |
| B31-A1 | **Alto** | Un webhook tardío rechazado/cancelado/expirado de una preferencia vieja libera la preferencia nueva y `pending_plan_id`. | Sí |
| B31-A2 | **Alto** | La limpieza post-commit del pago manual puede borrar una preferencia nueva creada en paralelo. | Sí |
| B31-M1 | **Medio** | Se pierden las referencias de la preferencia invalidada: no queda auditoría local del `plan_id`, checkout ni preference ID. | Sí, para auditoría/conciliación |
| B31-M2 | **Medio** | `registrarPagoSuperseded()` hardcodea `ARS`, no valida moneda/importe, usa `approved_at = now()` y no guarda `gateway_response`. | Sí, para auditoría/conciliación |
| B31-M3 | **Medio** | El historial no distingue visualmente un pago `superseded` de un pago aplicado. | No, pero afecta la operación |
| B31-B1 | **Bajo** | El `firstOrCreate` de `superseded` no verifica que un Payment existente pertenezca al mismo comercio. | No; defensa en profundidad |
| B31-B2 | **Bajo** | M1 no muestra feedback personalizado para errores inesperados/500/403; cae en el modal genérico de Inertia. | No |
| B31-B3 | **Bajo** | Las pruebas de A1/M1 usan DOM simulado y no cubren el componente/SweetAlert real; las pruebas de M2 no cubren los escenarios críticos. | No por sí solo, pero explica la suite verde |

### 15.3 Hallazgos críticos

#### B31-C1 — Pago viejo aplicado al plan pendiente actual

**Archivos y líneas:**

- `app/Http/Controllers/MercadoPagoNotificacionController.php:188-221`: el webhook aprobado usa `Plan::find($comercio->pending_plan_id)` y llama a `aplicarPago()` sin verificar a qué preferencia pertenece el pago.
- `app/Http/Controllers/SuscripcionController.php:149-150`: `generarPreferencia()` setea `pending_plan_id` con el plan de la nueva preferencia.
- `app/Http/Controllers/SuscripcionController.php:178-211`: se crea el checkout y sólo se guarda el `checkout_url`; no se persiste el preference ID ni una intención con plan/importe.
- `app/Http/Controllers/SuscripcionController.php:254-321`: `confirmarUpgrade()` verifica que el pago esté aprobado y que `referenceId` sea el comercio, pero no valida que el pago corresponda al `plan_id` solicitado ni que el importe coincida.
- `app/Services/Suscripcion/SuscripcionService.php:253-325`: `aplicarPago()` aplica el `Plan` recibido y usa el importe del pago sin compararlo con el precio del plan.
- `app/Services/Payment/Gateways/MercadopagoGateway.php:80-93`: la preferencia no envía `metadata` a Mercado Pago; el pago no queda asociado de forma durable al plan.
- `app/Services/Suscripcion/PagoManualService.php:204, 231-232`: el pago manual limpia `pending_plan_id` y el cache local, pero no cancela ni invalida la preferencia en Mercado Pago.

**Reproducción:**

1. Se crea la preferencia P1 para el plan A. Queda `pending_plan_id = A` y el cache con P1.
2. El Administrador Global registra un pago manual, o el usuario usa “Cancelar pago”. Se limpia `pending_plan_id` y el cache local, pero P1 sigue viva en Mercado Pago porque no se cancela en la pasarela.
3. Se crea la preferencia P2 para el plan B. Queda `pending_plan_id = B` y el cache con P2.
4. Se paga P1. El webhook aprobado lee `pending_plan_id = B`, busca el plan B y llama a `aplicarPago(plan B, amount P1)`. P1 se registra como aprobado, se aplica el plan B y no queda como `superseded`.
5. Variante HTTP: un usuario autenticado puede llamar a `confirmarUpgrade` con `plan_id = B` y `payment_id = P1`. El controlador comprueba que P1 está aprobado y que `pending_plan_id = B`, pero no que P1 haya sido generado para B ni que su importe sea el de B. Se aplica B con el importe de P1.
6. Si después se paga P2, el webhook ya no encuentra `pending_plan_id` y la registra como `superseded`. El usuario pagó dos veces y el sistema aplicó un solo período, con el plan equivocado en el primer pago.

**Impacto:** error de entitlement y financiero con dinero real. Un pago barato de una preferencia vieja puede entregar un plan caro; un pago viejo puede aplicarse a un plan que el usuario no contrató; el pago posterior queda para conciliación manual. La afirmación de §14.5 (“un pago aprobado de una preferencia vieja queda registrado como `superseded`”) sólo es cierta cuando `pending_plan_id` ya es `null`; no cubre el caso de una preferencia nueva pendiente.

**Recomendación:**

- Persistir una intención de pago durable con `preference_id`, `plan_id`, importe, moneda y estado, y usar un `external_reference` único por intención (no sólo el `comercio_id`).
- En el webhook y en `confirmarUpgrade`, validar que el pago pertenezca a la intención actual. Si no coincide, registrar `superseded` y no aplicar nada.
- Como mitigación inmediata, validar que el importe del pago coincida con el precio del plan antes de aplicar; no alcanza para resolver todos los casos, pero bloquea el subpago.
- Al reemplazar una preferencia, cancelarla o expirarla en Mercado Pago, o marcarla como inválida y rechazar sus pagos tardíos.
- No usar `pending_plan_id` mutable como única fuente de verdad para decidir qué plan aplicar.

### 15.4 Hallazgos altos

#### B31-A1 — Webhook tardío no aprobado libera una preferencia nueva

**Archivos y líneas:**

- `app/Http/Controllers/MercadoPagoNotificacionController.php:156-167`: para cualquier pago no aprobado con estado `rejected`, `cancelled` o `expired`, resuelve el comercio por `referenceId` y llama a `liberarPagoEnVuelo()` sin correlacionar el pago con la preferencia actual.
- `app/Services/Suscripcion/SuscripcionService.php:500-520`: `liberarPagoEnVuelo()` borra el cache y pone `pending_plan_id = null` para el comercio, sin verificar qué preferencia se está liberando.

**Reproducción:**

1. Se crea P1; el usuario cancela localmente o se registra un pago manual. P1 sigue viva en Mercado Pago.
2. Se crea P2 y queda `pending_plan_id = B`, cache con P2.
3. Llega un webhook tardío de P1 con estado `rejected`, `cancelled` o `expired`. El controlador no sabe que es P1: libera el estado del comercio, borra el cache de P2 y deja `pending_plan_id = null`.
4. El usuario paga P2. El webhook aprobado ya no encuentra plan pendiente y registra P2 como `superseded`. La suscripción no se extiende y el usuario pagó sin recibir el período.

**Impacto:** un pago real queda sin aplicar y requiere conciliación manual. El usuario puede reintentar y generar un tercer pago, con riesgo de doble cobro. No se aplica un plan equivocado, pero se pierde la intención nueva por un evento viejo.

**Recomendación:** correlacionar el webhook con la intención actual antes de liberar. Si el pago no pertenece a la preferencia viva, registrar el evento y no tocar `pending_plan_id` ni el cache. La misma intención durable de B31-C1 resuelve este caso.

#### B31-A2 — La limpieza post-commit puede borrar una preferencia nueva

**Archivos y líneas:**

- `app/Services/Suscripcion/PagoManualService.php:231-232`: después del commit, si el pago es nuevo, llama a `limpiarPagoEnVuelo()` sin tomar el lock de generación de preferencias.
- `app/Http/Controllers/SuscripcionController.php:92-95`: `generarPreferencia()` usa el lock `suscripcion:generar-pago:{comercio_id}`.
- `app/Http/Controllers/SuscripcionController.php:107-115, 149-150, 208-211`: dentro del lock, chequea el cache, setea `pending_plan_id`, crea el checkout y guarda el link.
- `app/Services/Suscripcion/SuscripcionService.php:480-487`: `limpiarPagoEnVuelo()` sólo hace `Cache::forget`.

**Reproducción:**

1. Un pago manual no encuentra cache en vuelo y su transacción commitea.
2. Antes de que se ejecute la limpieza post-commit, otra request de `generarPreferencia` obtiene el lock, ve el cache vacío, setea `pending_plan_id = B`, marca el cache y crea P2.
3. La limpieza del pago manual, que no toma el lock, borra el cache de P2. `pending_plan_id` puede quedar en B o haber sido limpiado por el pago manual, según el orden exacto.
4. P2 sigue viva en Mercado Pago pero el sistema perdió su link. “Reintentar pago” no puede reanudarla; el usuario puede generar P3. Si P2 se paga después, puede aplicarse al plan pendiente actual (B31-C1) o quedar como `superseded`.

**Impacto:** checkout vivo sin seguimiento local, riesgo de doble cobro y de aplicar un plan distinto. La prueba `test_un_reintento_idempotente_no_invalida_una_preferencia_nueva` cubre el reintento posterior a la limpieza, pero no esta carrera entre el commit y la limpieza.

**Recomendación:** ejecutar la limpieza bajo el mismo lock de `generarPreferencia` y con una condición de versión/token: borrar sólo si el cache todavía corresponde a la intención que el pago manual reemplazó. Alternativamente, persistir el estado de la intención y no borrar el cache a ciegas.

### 15.5 Hallazgos medios

#### B31-M1 — Se pierden referencias de la preferencia invalidada

**Archivos y líneas:**

- `app/Services/Suscripcion/PagoManualService.php:204, 207-222, 231-232`: el pago manual limpia `pending_plan_id` y el cache; el activity log no guarda el `plan_id` pendiente anterior, el checkout ni el preference ID.
- `app/Services/Suscripcion/SuscripcionService.php:480-487`: la limpieza sólo borra el cache.
- `app/Services/Suscripcion/SuscripcionService.php:500-520`: la liberación registra `plan_pendiente_id = null`, no el plan que se estaba liberando.
- `app/Http/Controllers/SuscripcionController.php:388-400`: “Cancelar pago” también libera localmente sin conservar la referencia de la preferencia.

**Reproducción:** se crea P1 con `plan_id` y `checkout_url` en cache; un pago manual o “Cancelar pago” borra el cache. Si P1 se paga después, el `Payment` `superseded` guarda payment ID, importe, comercio y metadata, pero no el preference ID, el checkout ni el plan de la intención invalidada. La conciliación local no puede reconstruir qué preferencia se invalidó.

**Impacto:** auditoría y conciliación incompletas. Se cumple que el pago viejo no extiende la suscripción, pero no se cumple “no se borren referencias que todavía sean necesarias para auditoría o conciliación”. El pago queda identificable por payment ID, pero la intención invalidada se pierde.

**Recomendación:** antes de limpiar, persistir un registro de intención/auditoría con `plan_id`, `checkout_url`, `preference_id`, fecha, actor y motivo; o conservar el cache con estado `invalidada` en lugar de borrarlo. Agregar el `preference_id` a la metadata del `Payment`.

#### B31-M2 — El registro `superseded` inventa o pierde datos reales

**Archivos y líneas:**

- `app/Services/Suscripcion/SuscripcionService.php:359-380`: `currency` hardcodeado en `ARS`; `approved_at = now()`; no guarda `gateway_response` ni `provider_reference`; acepta `amount` nulo.
- `app/Services/Payment/Contracts/PaymentStatusResponse.php:7-16`: el contrato no expone moneda ni fecha de aprobación.
- `app/Services/Payment/Gateways/MercadopagoGateway.php:133-139`: la respuesta de Mercado Pago se mapea sin `currency_id` ni `date_approved`; el `raw` sí los contiene, pero no se usa en `registrarPagoSuperseded()`.

**Reproducción:** Mercado Pago devuelve `currency_id = USD`, `transaction_amount = null` o un `date_approved` anterior. El método registra `currency = ARS`, `amount = null` y `approved_at = now()`, sin raw. El pago queda aprobado con datos que no reflejan la pasarela.

**Impacto:** el registro de conciliación no es fiel a la realidad; puede mostrar un importe nulo, una moneda incorrecta o una fecha de aprobación inventada. Se incumplen los criterios de “importe/moneda validados” y “sin inventar información”.

**Recomendación:** extender `PaymentStatusResponse` con `currency` y `dateApproved` (o leerlos del `raw`), validar `amount > 0` y la moneda, guardar `gateway_response`, `provider_reference` y `payment_method` cuando estén disponibles, y usar la fecha real de aprobación o dejarla nula con un `received_at` explícito.

#### B31-M3 — El historial no distingue `superseded` de un pago aplicado

**Archivos y líneas:**

- `app/Http/Controllers/GlobalAdminController.php:324-342`: el historial mapea estado, importe, período, método y referencia; no expone `metadata.conciliacion`.
- `resources/js/Pages/AdminGlobal/Facturacion.vue:348-363`: muestra el pago como “Aprobado” y el período vacío; no hay badge, columna ni filtro de conciliación.

**Reproducción:** un pago `superseded` tiene `status = approved`, `period_start/end = null` y `metadata.conciliacion = superseded`. En el historial se ve igual que un pago aprobado sin período: “Aprobado”, importe, método “—”, período “—”. El administrador no sabe que requiere conciliación.

**Impacto:** el pago puede confundirse con un pago aplicado o contarse como ingreso; no se puede filtrar la cola de conciliación. En la base está distinguido, pero en la pantalla operativa no.

**Recomendación:** exponer `conciliacion`/`motivo` en el historial y mostrar un badge “Superseded / a conciliar” o una columna específica; agregar filtro por estado de conciliación y, si corresponde, un estado propio.

### 15.6 Hallazgos bajos

#### B31-B1 — `firstOrCreate` de `superseded` no valida el comercio existente

**Archivos y líneas:** `app/Services/Suscripcion/SuscripcionService.php:359-363` y `:332-338`.

**Reproducción:** existe un `Payment` con el mismo `provider + gateway_transaction_id` pero `payable_id` de otro comercio. `firstOrCreate` devuelve el existente; el controlador limpia el cache del comercio actual y responde `already_processed`. No se encontró una fuga multi-tenant en el flujo normal, porque Mercado Pago resuelve el comercio por `external_reference` y la firma del webhook está verificada, pero no hay rechazo explícito ante una inconsistencia.

**Impacto:** defensa en profundidad insuficiente ante datos anómalos o un webhook mal formado. No se aplica el pago al comercio actual ni se extiende la suscripción, pero se silencia la inconsistencia.

**Recomendación:** después de `firstOrCreate` (o antes), verificar `payable_type`/`payable_id`; si no coinciden, registrar un error crítico y devolver 409/422 sin tocar el comercio actual. Agregar una prueba cross-tenant.

#### B31-B2 — M1 no cubre errores inesperados/500/403

**Archivos y líneas:** `resources/js/Pages/AdminGlobal/Facturacion.vue:167-175`; `app/Http/Controllers/GlobalAdminController.php:450-452, 465-469`.

**Reproducción:** una excepción de base de datos o un `abort(403)` en `marcarPagado` no genera errores de validación. Inertia no llama a `onError`; muestra su modal genérico de error (o la página de error) en lugar del mensaje “No se pudo registrar el pago” o “cobros deshabilitados”. No hay falsa confirmación, pero el feedback no es el personalizado.

**Impacto:** errores inesperados con un mensaje poco claro o con stack trace en desarrollo. La operación no se confirma falsamente.

**Recomendación:** capturar `Throwable` en el controlador y devolver un error de validación/flash comprensible; o agregar un manejador global de errores de Inertia. Probar 500 y 403.

#### B31-B3 — Cobertura de pruebas insuficiente para A1/M1/M2

**Archivos y líneas:**

- `resources/js/Utils/pagoManual.test.mjs:10-91`: DOM simulado; no renderiza `Facturacion.vue` ni ejecuta SweetAlert.
- `resources/js/Utils/pagoManual.test.mjs:93-130`: prueba los helpers, no el wiring de `onSuccess`/`onError`.
- `tests/Feature/BuildB3_FacturacionTest.php:1021-1272`: cubre limpieza, reintento y `superseded` sin `pending_plan_id`, pero no el pago viejo con preferencia nueva, el webhook tardío no aprobado, la carrera post-commit, moneda/importe/fecha reales ni el historial.

**Reproducción:** si se vuelve a interpolar `comercio.nombre` en el `html` de `Facturacion.vue`, las pruebas JS actuales siguen pasando porque sólo prueban el helper y el string estático. Si se paga una preferencia vieja con una nueva pendiente, no hay prueba que falle; el flujo actual aplica el plan nuevo.

**Impacto:** la suite verde no demuestra que M2 esté cerrado y no protege contra la regresión de A1 en el componente. No es un defecto de seguridad por sí solo, pero explica por qué los hallazgos críticos no se detectaron.

**Recomendación:** agregar una prueba estática o de componente que verifique que `Facturacion.vue` usa `MODAL_PAGO_MANUAL_HTML` sin interpolación y que el feedback está conectado; agregar pruebas de integración para los escenarios B31-C1, B31-A1 y B31-A2, y para moneda/importe/fecha del `superseded`.

### 15.7 Sin observaciones

- **A1 (implementación):** `MODAL_PAGO_MANUAL_HTML` es estático; `inicializarModalPagoManual()` asigna `nombre` con `textContent`, importe/fecha con `value`/`max` y métodos con `option.textContent`; `Facturacion.vue` usa `html: MODAL_PAGO_MANUAL_HTML` y los mensajes SweetAlert usan `text`, no `html`. No se encontró interpolación insegura ni dependencia de que los cobros estén deshabilitados.
- **M1 (flujo principal):** el éxito se muestra en `onSuccess`, después del commit y del redirect del servidor; los errores de validación/negocio se muestran en `onError` con el mensaje del backend. No se encontró falsa confirmación ni mensaje contradictorio en los casos cubiertos.
- **M2 (reintento idempotente en aislamiento):** un reintento con la misma `idempotency_key` devuelve el pago original con `creado = false` y no limpia una preferencia nueva. La prueba `test_un_reintento_idempotente_no_invalida_una_preferencia_nueva` pasa.
- **M2 (duplicados y no extensión):** `registrarPagoSuperseded()` usa `firstOrCreate` y lock sobre el comercio; el webhook duplicado no crea un segundo registro y el pago `superseded` no modifica `plan_id`, vencimiento ni `pending_plan_id`. La prueba `test_un_pago_aprobado_sin_pending_plan_id_se_registra_para_conciliacion` pasa.
- **Multi-tenant (flujo normal):** el comercio se resuelve por `external_reference` de Mercado Pago; `confirmarUpgrade` valida que `referenceId` sea el comercio autenticado; no se encontró fuga entre tenants en el flujo normal. Queda la defensa adicional B31-B1.
- **Alcance:** no se modificaron migraciones, DB, precios, duraciones, integración real de Mercado Pago, flujo de aplicación/devolución de `superseded`, B1-B10 ni BUILD B4. No se iniciaron servicios ni se hicieron llamadas reales a Mercado Pago.

### 15.8 Pruebas ejecutadas

Se reutilizó el entorno Docker ya disponible (`vendar-laravel.test-1`, `vendar-pgsql-1`, `vendar-redis-1`); no se iniciaron servicios.

| Verificación | Comando | Resultado |
|---|---|---|
| B3 completo | `docker compose exec -T laravel.test php artisan test tests/Feature/BuildB3_FacturacionTest.php --compact` | 42 tests, 241 assertions, 0 fallos; 42 “deprecated” preexistentes |
| Regresión suscripciones | `docker compose exec -T laravel.test php artisan test tests/Feature/Modulo8_SuscripcionesTest.php --compact` | 69 tests, 507 assertions, 0 fallos; 69 “deprecated” preexistentes |
| Unit suscripción | `docker compose exec -T laravel.test php artisan test tests/Unit/Suscripcion/SuscripcionServiceTest.php --compact` | 25 tests, 56 assertions, 0 fallos; 25 “deprecated” preexistentes |
| JS A1/M1 | `node --test resources/js/Utils/pagoManual.test.mjs` | 3 tests, 3 pass, 0 fail |
| Sintaxis PHP | `php -l` sobre los PHP de B3.1 | Sin errores |
| Whitespace | `git diff --check` | Sin errores |

Los “deprecated” corresponden al aviso preexistente `Constant PDO::MYSQL_ATTR_SSL_CA is deprecated`; no son fallos de test.

**Límites de la corrida:** no se ejecutó la suite completa. Siguen los 23 fallos preexistentes de BUILD B1 documentados en §13.3. No se agregó una prueba nueva para los escenarios críticos porque esta revisión no debía modificar código; la reproducción de B31-C1, B31-A1 y B31-A2 se deriva del flujo y de las líneas citadas. No se re-ejecutaron `npm run build` ni Pint; §14 registra esas verificaciones y no hubo cambios de código de aplicación durante esta revisión.

### 15.9 Criterios de aceptación revisados

| Criterio | Resultado |
|---|---|
| A1: datos dinámicos fuera del HTML y asignados como texto | Cumple |
| A1: prueba de seguridad real | Parcial: prueba unitaria con DOM simulado, no integración con SweetAlert/componente |
| M1: éxito sólo después de confirmación del servidor | Cumple |
| M1: errores de validación/negocio visibles | Cumple |
| M1: errores inesperados con feedback comprensible | Parcial: cae en el modal genérico de Inertia |
| M2: pago manual nuevo limpia la preferencia anterior | Cumple en el caso simple; falla en la carrera post-commit (B31-A2) |
| M2: reintento idempotente no invalida una preferencia nueva | Cumple en aislamiento |
| M2: webhook tardío no aplica un plan distinto | **No cumple** (B31-C1) |
| M2: webhook tardío no libera una preferencia nueva | **No cumple** (B31-A1) |
| M2: `superseded` identificable, con referencia externa e importe/moneda validados | Parcial: payment ID/comercio/importe, pero moneda hardcodeada, importe no validado y sin raw |
| M2: `superseded` no extiende automáticamente | Cumple |
| M2: `superseded` sin duplicados | Cumple en el mismo comercio; falta defensa cross-tenant (B31-B1) |
| M2: `superseded` distinguible de un pago aplicado | Parcial: metadata en DB, no en el historial (B31-M3) |
| Sin cambios de precios, duraciones, migraciones ni DB | Cumple |
| Sin cobros reales ni llamadas reales a Mercado Pago | Cumple |
| Sin iniciar B4 ni corregir B1-B10 | Cumple |

### 15.10 Conclusión

**B3.1 no es aprobable en su estado actual.**

A1 y M1 están implementados correctamente en lo principal y pueden considerarse cerrados con observaciones menores de cobertura y feedback de errores inesperados. M2, en cambio, no cumple el criterio de aceptación: un pago de una preferencia vieja puede aplicarse al plan pendiente actual y entregar un plan distinto por un importe menor; un webhook tardío no aprobado puede liberar una preferencia nueva; y la limpieza post-commit puede borrar una preferencia nueva por una carrera. Además, el registro `superseded` no conserva moneda/fecha reales ni referencias de la intención invalidada, y el historial no lo distingue visualmente.

Los hallazgos B31-C1, B31-A1 y B31-A2 son preexistentes al cambio B3.1, pero B3.1 asumió M2 y su corrección no los cubre. Por el impacto en dinero real, no corresponde aprobar la etapa sin una corrección posterior. No se modificó código de aplicación, migraciones, DB, precios, duraciones ni integración de Mercado Pago durante esta revisión. Se detiene el trabajo a la espera de aprobación o de una nueva fase de corrección de M2; no se inicia BUILD B4.
