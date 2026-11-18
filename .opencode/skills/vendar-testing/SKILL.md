---
name: vendar-testing
description: 'Explica cómo se testea VendAR con PHPUnit 10.5 sobre PostgreSQL, el harness TestCaseMultiTenant, los datos del QATestDataSeeder y la matriz de regresión para POS, caja, stock, fiados, pedidos web, suscripciones y multi-tenancy. Usala al escribir o correr tests, interpretar fallos, agregar casos cross-tenant o validar que un cambio no rompió invariantes. Disparadores: test, PHPUnit, phpunit.xml, TestCaseMultiTenant, QATestDataSeeder, cobertura, regresión, fallo, baseline, cross-tenant, stock invariante.'
compatibility: opencode
metadata:
  version: "1.0.0"
  domain: testing
  scope: implementation
  owner: VendAR
  related-skills: vendar-business-rules, vendar-multitenancy, vendar-architecture, test-master
---

# Testing de VendAR

## Cuándo usar esta Skill

- Antes y después de tocar POS, caja, stock, fiados, compras, pedidos web o suscripciones.
- Para escribir un test nuevo de feature o de service.
- Para agregar la matriz cross-tenant de un endpoint.
- Para interpretar un fallo y decidir si es propio o preexistente.
- Para verificar que un invariante de stock o de caja sigue en pie.

No la uses para diseñar la funcionalidad (usá `vendar-business-rules` y `vendar-architecture`) ni para revisar código en general (usá `code-reviewer`).

## Stack de testing

| Pieza | Valor |
|---|---|
| Framework | PHPUnit `^10.5` |
| Runner | `php artisan test` (wrapper de Laravel) |
| Base de datos | PostgreSQL, base `testing` (`phpunit.xml`) |
| Transacciones | `DatabaseTransactions` en `TestCaseMultiTenant` |
| Seed | `RoleSeeder`, `PlanSeeder`, `QATestDataSeeder` |
| Estilo | Laravel Pint (`vendor/bin/pint`) |
| Frontend | No hay tests JS; se valida por HTTP/Inertia |

No hay Pest. No agregues Pest, Dusk, Cypress ni Vitest sin decisión explícita.

## Comandos

```bash
# Suite completa (tarda ~10 minutos en el baseline conocido)
php artisan test

# Un archivo
php artisan test tests/Feature/Stock/StockInvariantTest.php

# Un grupo por nombre de clase o método
php artisan test --filter=Modulo2_VentasTest
php artisan test --filter=test_admin_a_puede_crear_venta_contado

# Varias clases
php artisan test --filter="Modulo8_SuscripcionesTest|SuscripcionServiceTest|MercadopagoGatewayTest|PedidoWebStockTest"

# Solo unitarios
php artisan test --testsuite=Unit

# Cortar en el primer fallo
php artisan test --stop-on-failure

# Estilo (no modifica)
vendor/bin/pint --test

# Estilo (corrige)
vendor/bin/pint
```

Con Sail o Docker, anteponé el ejecutor que use el equipo:

```bash
./vendor/bin/sail test
docker exec vendar-app-laravel.test-1 php artisan test --filter=SuscripcionServiceTest
```

## Base de datos de test

`phpunit.xml` fuerza:

```xml
<env name="APP_ENV" value="testing"/>
<env name="DB_DATABASE" value="testing"/>
<env name="CACHE_STORE" value="array"/>
<env name="QUEUE_CONNECTION" value="sync"/>
<env name="SESSION_DRIVER" value="array"/>
<env name="MAIL_MAILER" value="array"/>
```

Reglas:

- **Nunca** corras la suite apuntando a `vendar` (desarrollo) ni a producción. Verificá `DB_DATABASE=testing`.
- `QUEUE_CONNECTION=sync` significa que los Jobs se ejecutan en el mismo request durante el test.
- `MAIL_MAILER=array` permite `Mail::fake()` y evita envíos reales.
- La suite toca la base `testing`: no guardes ahí datos que necesites conservar.

## Harness `TestCaseMultiTenant`

`tests/TestCaseMultiTenant.php` es la base de la mayoría de los tests de feature (25 archivos). En cada `setUp()`:

1. `truncateAll()`: trunca todas las tablas de `public` salvo `migrations`, roles/permisos y `planes`, con `session_replication_role = replica`.
2. Siembra `RoleSeeder`, `PlanSeeder` y `QATestDataSeeder`.
3. Resetea las secuencias de PostgreSQL.
4. Expone `adminA`, `adminB`, `userA`, `userB`, `consumidorA`, `consumidorB`.

Helpers disponibles: `actingAsAdminA()`, `actingAsAdminB()`, `actingAsUserA()`, `actingAsUserB()`.

**Cuidado:** el truncado es real, no un rollback. Si un test falla antes de terminar, la base `testing` queda con los datos del seeder y lo que haya escrito. Es esperable; volvé a correr la suite o el test.

### Datos del `QATestDataSeeder`

| Entidad | Comercio A | Comercio B |
|---|---|---|
| Comercio | `1` (Norte) | `2` (Sur) |
| Sucursales | `1`, `2` | `3`, `4` |
| Admin | `admin.a@test.com` | `admin.b@test.com` |
| Cajero | `user.a@test.com` | `user.b@test.com` |
| Consumidores | `11111111` (Juan Pérez, límite 5000), `22222222`, `33333333`, `44444444` | `55555555` (Pedro Sánchez, límite 5000), `66666666`, `77777777`, `88888888`, `99999999` |
| Productos | `1`–`10` | `11`–`20` |
| Cajas | `1`, `2` en sucursal 1; `3` en sucursal 2 | `4` en sucursal 3; `5` en sucursal 4 |
| Turnos | `1` cerrado y `2` abierto (sucursal 1) | `3` cerrado y `4` abierto (sucursal 3) |
| Stock | producto 1–10 en sucursales 1 y 2 (`fisica` 100/50, `reservada` 10/5) | producto 11–20 en sucursales 3 y 4 |
| Lotes | ids `1`–`5` | ids `6`–`8` |

Antes de escribir un test, leé `database/seeders/QATestDataSeeder.php`: los IDs son parte del contrato de los tests existentes. Si necesitás un producto o una caja extra, creala en el `setUp()` del test con `updateOrInsert`, como hacen los tests de stock.

## Anatomía de un test de feature

```php
<?php

namespace Tests\Feature;

use App\Models\Venta;
use Tests\TestCaseMultiTenant;

class ModuloX_MiFeatureTest extends TestCaseMultiTenant
{
    public function test_admin_a_puede_hacer_algo(): void
    {
        $this->actingAsAdminA();

        $response = $this->post('/ventas', [
            'turno_caja_id' => 2,
            'items' => [
                ['id' => 1, 'cantidad' => 2, 'precio_venta' => 800, 'nombre' => 'Coca Cola 500ml'],
            ],
            'total' => 1600,
            'metodo_pago' => 'Efectivo',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertDatabaseHas('ventas', [
            'turno_caja_id' => 2,
            'total' => 1600,
            'estado' => 'Completada',
        ]);
    }
}
```

Convenciones:

- Métodos `test_...` en español, descriptivos y con el resultado esperado.
- Un comportamiento por test; si hay varios asserts, que sean del mismo flujo.
- Usá las rutas HTTP reales (`/ventas`, `/api/sesiones-caja/...`), no el controller directo, salvo tests unitarios de service.
- Para Inertia: `assertInertia(fn ($page) => $page->where(...)->has('...'))`.
- Para JSON: `assertOk()`, `assertJson()`, `assertJsonIsArray()`, `assertJsonValidationErrors()`.
- Para permisos: `assertForbidden()` (`403`) o `assertNotFound()` (`404`) según el contrato del módulo.
- Para base de datos: `assertDatabaseHas`, `assertDatabaseMissing`, `assertDatabaseCount`.
- Para eventos/Jobs: `Queue::fake()`, `Event::fake()`, `Mail::fake()`.
- Para fechas: `$this->travelTo(Carbon::parse('...'))`; no dependas del reloj real.
- No uses `sleep()` ni llamadas HTTP reales.

## Matriz de regresión obligatoria

Si tu cambio toca...

### Ventas / POS

- Venta contado: total recalculado, estado `Completada`, movimiento de caja `INGRESO`.
- Venta con pago que no coincide: rechazada con error.
- Venta con recargo de tarjeta: recargo sumado al pago y al total.
- Venta fiada: consumidor activo del comercio, límite respetado, cargo en cuenta corriente.
- Venta fiada sin consumidor o con consumidor de otro comercio: rechazada.
- Venta manual: estado `Pendiente`, `Payment` pendiente, sin movimiento de caja hasta confirmar.
- Venta gateway: estado `Pendiente`, sin llamada real a la pasarela (fake del service).
- `confirmarPago`: pasa a `Completada`, impacta caja/CC, no duplica si se repite.
- Mezcla manual + gateway: rechazada.
- Anulación: restaura stock/lotes, revierte caja/CC, estado `Cancelada`, idempotente.
- Devolución: solo `Completada`, no supera `cantidad − cantidad_devuelta`, revierte proporcionalmente.

### Caja

- Abrir turno en caja propia: OK.
- Abrir turno en caja de otro comercio: `404`/`403` según contrato.
- Abrir dos veces la misma caja: `409`.
- Cerrar turno abierto: OK; cerrar uno ya cerrado: `400`.
- Cerrar turno ajeno: `403`/`404`.
- Balance: `INGRESO − EGRESO` por categoría (efectivo, transferencias, tarjetas).
- Movimiento manual sin turno abierto: rechazado.
- Caja con turnos no se elimina; se inactiva.

### Stock

- Venta descuenta `cantidad_fisica` y lotes FIFO; escribe `movimientos_stock`.
- Anulación restaura lote y producto.
- Devolución restaura lote y producto.
- Overselling: rechazado si `permitir_stock_negativo = false`; permitido y marcado si es `true`.
- Ingreso manual: PPP, `historico_costos`, lote, movimiento.
- Recepción de OC: PPP, lote, movimiento, estado `Recibida`/`Parcialmente Recibida`.
- Reserva web: incrementa `cantidad_reservada`, no `cantidad_fisica`.
- Entrega de pedido: descuenta ambas y consume lotes.
- Cancelación pre-entrega: libera reserva; post-entrega: repone físico y lotes.
- **Invariante**: `SUM(lotes.stock_actual) == producto_sucursal.cantidad_fisica` cuando el producto maneja lotes.

### Fiados / cuenta corriente

- Cobro dentro del límite de deuda: descuenta saldo y registra abono.
- Cobro mayor a la deuda: rechazado.
- Métodos repetidos en un mismo cobro: rechazado.
- Cobro de consumidor de otro comercio: `403`.
- Cobro sin turno abierto: descuenta deuda, no impacta caja (comportamiento actual).
- Anulación/devolución de venta fiada: acredita la cuenta.

### Pedidos web

- Sucursal de otro comercio: `422`.
- Producto sin stock o inactivo: rechazado.
- Reserva mayor al disponible: rechazada.
- Envío: base + km; sin km, solo base.
- Pago pendiente: `confirmarPago` idempotente.
- Pago no aprobado o de otro pedido: rechazado.

### Suscripciones

- `estadoSuscripcion`: `activa`, `por_vencer` (≤ 10 días), `vencida`, `sin_vencimiento`.
- Renovación del mismo plan: solo con ≤ 30 días.
- Upgrade: no limitado por la ventana.
- Renovación anticipada: prorroga desde el vencimiento vigente, incluido el día de vencimiento.
- Vencida o sin vencimiento: arranca en la **fecha actual**. Una `fecha_pago` pasada sólo afecta `approved_at`; no retrasa el inicio ni regala/quita días.
- `aplicarPago` (Mercado Pago): idempotente por `paymentId`.
- `PagoManualService::registrar` (manual): idempotente por `idempotency_key` y rechaza un segundo pago aprobado del mismo `period_end`.
- Pago en vuelo: no genera segunda preferencia dentro de 30 minutos.

### Multi-tenancy

Todo endpoint nuevo necesita, como mínimo:

1. Usuario A lista: no ve datos de B.
2. Usuario A modifica/borra/status de B: `403` (o `404` si el contrato es por ruta anidada).
3. Payload con `comercio_id`/`sucursal_id`/`branch_id`/`consumidor_id` de B: rechazado.
4. Recurso con `comercio_id = null`: falla cerrado.
5. Admin Global: puede, y queda auditado.

Agregá los casos en `tests/Feature/CrossTenantAttackTest.php` o en el test del módulo, siguiendo el código esperado que ya usa ese módulo.

## Tests unitarios

- Van en `tests/Unit` y extienden `Tests\TestCase`.
- No tocan base de datos.
- Ideales para fórmulas puras: `SuscripcionService`, cálculos de PPP, formatters de backend, normalización de métodos de pago.
- Para services con dependencias, usá `Mockery` o instancias falsas; no golpees la DB.

## Fallos preexistentes (baseline)

El baseline histórico documentado en `docs/informes/BUILD-B1.md` es:

```
Tests: 376 deprecated, 23 failed, 1 risky, 1 passed (1415 assertions)
```

Después de BUILD B3, con tests nuevos pero los mismos 23 fallos preexistentes:

```
Tests: 431 deprecated, 23 failed, 1 risky, 1 passed (1709 assertions)
```

No alcanza con comparar la cantidad: verificá nombres, mensajes y causa de cada fallo. Las causas de los 23 fallos actuales están detalladas en `docs/informes/BUILD-B3.md` (§7.3 y §13).

Distribución de los 23 fallos:

| Suite | Fallos |
|---|---:|
| `Auth/PasswordResetTest` | 4 |
| `Auth/RegistrationTest` | 1 |
| `CrossTenantAttackTest` (`ct10`) | 1 |
| `Modulo1_ProductosTest` | 1 |
| `Modulo3_CajaTest` | 3 |
| `Modulo4_PedidosWebTest` | 2 |
| `Modulo7_TransferenciasTest` | 1 |
| `MultiTenantIsolationTest` | 1 |
| `VentasCanceladasHistorialTest` | 8 |
| `WebhookSeguridadTest` | 1 |
| **Total** | **23** |

Reglas:

- Los `DEPR` por `PDO::MYSQL_ATTR_SSL_CA` en PHP 8.5 son aviso del entorno, no fallo funcional.
- **No ocultes fallos**: no borres tests, no los marques `skip`, no cambies asserts para que pasen, no bajes el nivel de error.
- Si tu cambio agrega un fallo nuevo, es tuyo: arreglalo.
- Si tu cambio “arregla” un fallo preexistente, documentalo en el informe.
- Para comparar contra el baseline, corré el mismo filtro con y sin tus cambios (`git stash` ida y vuelta, con restauración verificada).

## Qué NO hacer

- Correr la suite contra la base de desarrollo.
- Usar `RefreshDatabase` en tests que extienden `TestCaseMultiTenant` (ya usa `DatabaseTransactions` + truncado).
- Depender de IDs que no estén en el seeder sin crearlos en el `setUp()`.
- Hacer llamadas reales a Mercado Pago, viüMi, ARCA o cualquier API externa.
- Testear la UI con snapshots o herramientas nuevas.
- Cambiar `phpunit.xml` para saltear tests.
- Declarar “todo verde” mirando solo tu filtro: la suite completa tiene fallos preexistentes conocidos.

## Checklist antes de entregar

- [ ] ¿Agregué tests para el camino feliz y para el error?
- [ ] ¿Cubrí el caso cross-tenant si hay endpoint nuevo?
- [ ] ¿Cubrí el invariante de stock/caja/deuda si toqué dinero o inventario?
- [ ] ¿Los tests usan datos del seeder o crean los suyos?
- [ ] ¿No hay llamadas externas reales ni `sleep()`?
- [ ] ¿Corrí el filtro afectado y la suite completa cuando correspondía?
- [ ] ¿Comparé contra el baseline y documenté fallos preexistentes?
- [ ] ¿`vendor/bin/pint --test` pasa?
- [ ] ¿El informe de fase registra comandos, resultados y pendientes?

## Referencias

- `phpunit.xml`
- `tests/TestCase.php`
- `tests/TestCaseMultiTenant.php`
- `tests/Feature/CrossTenantAttackTest.php`
- `tests/Feature/Stock/StockInvariantTest.php`
- `tests/Feature/Stock/StockConsistencyTest.php`
- `tests/Feature/Modulo2_VentasTest.php`
- `tests/Feature/Modulo3_CajaTest.php`
- `tests/Unit/Suscripcion/SuscripcionServiceTest.php`
- `database/seeders/QATestDataSeeder.php`
- `docs/informes/BUILD-B1.md` (§10.4)
- `docs/informes/F14-renovacion-mismo-plan.md`
