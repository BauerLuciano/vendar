# F14 - Renovación del plan actual (mismo plan) + ciclo de vida del pago en vuelo

## Objetivo
Habilitar la renovación del **plan que el comercio ya tiene contratado** cuando la suscripción está por vencer o vencida, reutilizando el flujo de Mercado Pago ya existente (`preferencias` → `/retorno` → webhook). El bloqueo era doble: el frontend no mostraba la acción durante la ventana de preaviso, y el backend cobraba sin extender el período. Además se corrigió un fallo de seguridad por el que la rama de "mismo plan" confirmaba el pago **sin verificarlo contra Mercado Pago**.

En una segunda vuelta se validó el **ciclo de vida del pago en vuelo** (el caso "la pantalla se quedó en *Estamos procesando tu pago* después de elegir un plan por error y no pagar").

No se rediseñó la pantalla `/mi-plan`: se reutilizó el botón `Renovar {{ plan }} 🔄` que ya existía y ya llamaba correctamente a `pagarPlan()`.

## Causa raíz
`MiPlan.vue` calculaba `requiereRenovacion` en el cliente:

```js
if (props.comercio.status === 'suspendido') return true;
if (props.comercio.vencimiento_pago) {
    return new Date(props.comercio.vencimiento_pago) < new Date();
}
return false;
```

Con un plan vigente a 8 días de vencer, `requiereRenovacion` daba `false` y la cadena de botones caía en el `v-else`: un `div` inerte con el texto "Este es tu plan actual". La renovación sólo aparecía una vez que la fecha **ya había pasado**.

En paralelo, el backend aplicaba el pago pero no movía la fecha: tanto `SuscripcionController::confirmarUpgrade()` como `MercadoPagoNotificacionController::procesarUpgradePlan()` extendían `vencimiento_pago` únicamente cuando la cuenta estaba suspendida o vencida (`$needsReactivation`). Con la cuenta al día, el comercio pagaba y no recibía nada.

## Archivos creados
- `app/Services/Suscripcion/SuscripcionService.php`
- `tests/Unit/Suscripcion/SuscripcionServiceTest.php`
- `tests/Unit/Payment/MercadopagoGatewayTest.php`
- `docs/informes/F14-renovacion-mismo-plan.md` (este informe)

## Archivos modificados
- `app/Http/Controllers/SuscripcionController.php`
  - Constructor: se inyecta `SuscripcionService`.
  - `miPlan()`: agrega la prop `'suscripcion'` con el estado calculado en servidor.
  - `generarPreferencia()`: anti-duplicado (409 si ya hay una preferencia en curso), devuelve `es_renovacion` / `plan_id` / `estado`, y limpia el marcador de pago en vuelo ante excepción.
  - `confirmarUpgrade()`: reescrito. Verifica el pago con Mercado Pago **antes** de bifurcar, y delega la aplicación en `SuscripcionService::aplicarPago()`.
  - `planActual()`: incluye `suscripcion` (la usa el polling), que ahora trae `pago_en_vuelo`.
  - `cancelarPago()`: nuevo. Libera el pago en vuelo desde la pantalla (ver "Ciclo de vida del pago en vuelo").
  - Se eliminó `registrarPagoRenovacion()` y el `DB::transaction` inline.
- `app/Http/Controllers/MercadoPagoNotificacionController.php`
  - Constructor: se inyecta `SuscripcionService`.
  - `procesarUpgradePlan()`: delega en `aplicarPago()`. Se conserva la verificación de firma y la validación de `external_reference`.
  - Ante un pago no aprobado **terminal** (`rejected`/`cancelled`/`expired`) libera el estado en vuelo. Un `pending` no se toca.
  - Se eliminaron `yaAplicadaRenovacion()` y `registrarPagoPlan()`. Imports `PaymentChannel` y `Payment` que quedaron sin uso.
- `routes/web.php`
  - Nueva ruta `POST /api/mi-plan/cancelar-pago` (`suscripcion.cancelar-pago`), dentro del mismo grupo `auth` + permiso que las demás rutas de suscripción.
- `app/Services/Payment/Gateways/MercadopagoGateway.php`
  - `verifyWebhookSignature()`: el `Log::critical` de producción incluye un hint accionable y el `Log::warning` incluye el environment. Sin cambios de semántica.
  - `normalizeStatus()`: se agrega el caso `'expired' => PaymentStatus::EXPIRED` (ver bug 10).
- `resources/js/Pages/Suscripcion/MiPlan.vue`
  - Nueva prop `suscripcion`.
  - Se elimina `requiereRenovacion`.
  - Computeds `estadoActual`, `diasRestantes`, `puedeRenovar`, `estaSuspendido`, `vencimientoActual`, `textoPlazo`, `clasesPlazo`, `hayPagoEnCurso`.
  - Card del plan actual: bloque "Activo hasta el {fecha}" + plazo ("Vence en N días" / "Vence hoy" / "Vence mañana" / "Vencida hace N días"), con color según estado; variante "Cuenta suspendida".
  - Cadena de botones: `Renovar {{ plan }} 🔄` para `por_vencer` / `vencida` / suspendido; "Este es tu plan actual" + link secundario `Renovar ahora` para `activa`; sin acción para `sin_vencimiento`.
  - Banner nuevo `en_curso` para el 409 de pago duplicado.
  - `pagarPlan()` maneja el 409 y guarda `es_renovacion` en `localStorage`.
  - Estado de pago en vuelo como espejo local (`pagoEnVuelo`), refrescado en cada tick del polling, para no depender de un reload.
  - El polling detecta el pago aplicado **y** el pago liberado; `detenerPolling()` reemplaza el `clearInterval` inline.
  - `cancelarPago()` y `reintentarPago()`.
  - `onMounted` reconcilia el marcador de `localStorage` contra el backend antes de armar el banner.
  - Banners `timeout`, `en_curso` y el nuevo `liberado`, con acciones de reintento y liberación.
  - `esPlanActual()` no se tocó.
- `tests/Feature/Modulo8_SuscripcionesTest.php`
  - Helpers `fakePagoAprobado()` y `prepararComercio()`.
  - P7.2.1 / P7.2.2: se les agrega `Http::fake` (antes no lo necesitaban porque la rama no verificaba el pago).
  - P7.2.3 redefinido: `test_renovar_mismo_plan_con_cuenta_al_dia_extiende_el_vencimiento`.
  - 13 tests nuevos (ver abajo).
- `tests/Feature/Stock/PedidoWebStockTest.php`
  - `simularRechazoWebhook()` pasa las 4 dependencias del constructor del controlador en vez de 2.
- `.env.example` / `.env`
  - `MERCADOPAGO_WEBHOOK_SECRET` documentado como obligatorio en producción.
  - **Pendiente para el usuario**: cargar el valor real desde el panel de Mercado Pago. El valor no se puede obtener desde el repositorio.

## Decisiones técnicas

### Fórmula de prórroga
```
nuevo_vencimiento = (vencimiento_vigente > hoy ? vencimiento_vigente : hoy) + 1 mes
```
Se adoptó la prórroga en vez de la política anterior (cobrar sin extender). Se usa `addMonthNoOverflow()` para que 31/01 + 1 mes sea 28/02 y no 02/03.

Consecuencia: renovar con 20 días restantes deja al comercio con ~50 días. Es intencional — es lo que el usuario percibe al pagar "un mes más".

### Criterio de días restantes
Un solo criterio, el del banner del Dashboard (`now()->startOfDay()->diffInDays($vencimiento->startOfDay(), false)`). Esto hace que el día del vencimiento dé `0` → `por_vencer` y no `vencida`, consistente con `VerificarEstadoCuenta`, que corta con `endOfDay()`.

### Estados expuestos
El backend devuelve `estado` ∈ {`activa`, `por_vencer`, `vencida`, `sin_vencimiento`}, derivado **solo de la fecha**. La suspensión se expone aparte como booleo `suspendido`, y `puede_renovar` combina ambas cosas. No se inventaron estados en DB: `comercios.status` sigue siendo el enum `activo|suspendido|trial`.

### Correlación con `pending_plan_id`
`confirmarUpgrade()` verifica contra Mercado Pago **siempre**, pero solo exige que `pending_plan_id` coincida con el `plan_id` pedido cuando el pago todavía **no** fue aplicado. Si el webhook llegó primero, ya limpió `pending_plan_id`, y exigir la coincidencia rompería la carrera webhook ↔ confirmación del frontend.

### Anti-duplicado de preferencias
Implementado con `Cache` (Redis, `CACHE_STORE=redis`) y una ventana de 30 minutos, en vez de una columna nueva. Motivo: el plan excluía migraciones, y crear filas `Payment` en estado `pending` habría polluted la semántica de `pagoYaRegistrado()` (que hoy significa "el pago ya fue aplicado y registrado"). Con cache no hay filas huérfanas ni cambios de esquema. Se limpia en `aplicarPago()`.

### Nombres de actividad
Se preservaron las descripciones existentes (`plan_reactivated`, `plan_upgraded`, `plan_reactivated_via_webhook`, `plan_upgraded_via_webhook`) para no romper trazabilidad ni tests. Se agregó `plan_renewed` / `plan_renewed_via_webhook` para el caso nuevo: mismo plan que no estaba vencido (antes ese caso no generaba log).

## Bugs corregidos
1. **Cobro sin extender.** `confirmarUpgrade()` y `procesarUpgradePlan()` solo extendían `vencimiento_pago` si la cuenta estaba suspendida o vencida. Con la cuenta al día el pago se registraba y el período no se movía.
2. **Pago sin verificar (seguridad).** La rama de mismo plan retornaba `already_upgraded` **antes** de llamar a `getPaymentStatus()`. Un `plan_id` propio más un `payment_id` inventado bastaba para reactivar una cuenta suspendida sin pagar, y además dejaba un `Payment` con `status=approved` y un `gateway_transaction_id` falso.
3. **Doble extensión vía HTTP.** `confirmarUpgrade()` no tenía guarda de idempotencia (el webhook sí la tenía vía `yaAplicadaRenovacion()`). Llamar dos veces con el mismo `payment_id` extendía dos meses. Ahora la guarda está dentro de la transacción, con `lockForUpdate()`.
4. **`amount` en NULL.** `registrarPagoRenovacion()` se llamaba sin `$amount`, así que el camino HTTP dejaba `payments.amount` vacío. Ahora se pasa el importe real de Mercado Pago, con fallback al precio del plan.
5. **Criterios de "días restantes" contradictorios** entre Dashboard (`startOfDay`), middleware (`endOfDay`) y frontend (`new Date()`). Ahora el backend es la única fuente.
6. **Webhook opaco en producción.** Sin `MERCADOPAGO_WEBHOOK_SECRET` el webhook responde 401 y la renovación nunca se aplica. Se documentó la clave y se mejoró el log.

## Ciclo de vida del pago en vuelo

Segunda vuelta, a partir del reporte "la pantalla se quedó en *Estamos procesando tu pago* después de seleccionar un plan por error". La causa no era el banner: era que **el backend nunca decía si había un pago realmente pendiente**, y el frontend deducía todo del `localStorage` del navegador.

### Bugs corregidos (7 a 10)
7. **El `localStorage` era la única fuente de verdad.** `pagarPlan()` guardaba `{plan_id, es_renovacion, ts}` con una ventana de 2 horas. Al volver a `/mi-plan`, `onMounted` armaba el banner solo porque la entrada existía, sin consultar si seguía viva la preferencia. Resultado: el banner volvía en cada navegación de esa ventana, incluso con el pago ya resuelto o nunca realizado.
8. **El polling no tenía salida.** Su única condición de éxito era `plan_id === planId && pending_plan_id === null`, que con un cambio de plan pendiente nunca se cumple. Después de 90 s caía en `timeout` y se quedaba ahí indefinidamente, sin distinguir "Mercado Pago está procesando" de "el usuario se fue sin pagar".
9. **Dead end bloqueado.** El guard anti-duplicado de 30 min no tenía salida: si la preferencia quedaba huérfana, el usuario comía un 409 sin poder reintentar ni cancelar. Tampoco existía una forma de limpiar `pending_plan_id`.
10. **`expired` no existía en el mapeo de estados de MP.** `normalizeStatus()` no tenía ese caso y caía en `default => PENDING`. Un pago expirado se tractaba como "aún completable", así que el estado nunca se liberaba.

### Solución
- `SuscripcionService::pagoEnVuelo()` devuelve `{plan_id, expira_en_minutos}` leyendo el marcador de Redis, y se expone en `suscripcion.pago_en_vuelo` de `/mi-plan` y `/api/mi-plan/plan-actual`. Si la ventana ya venció, se autolimpia: la preferencia huérfana no bloquea al usuario.
- `SuscripcionService::liberarPagoEnVuelo()` descarta el marcador **y** limpia `pending_plan_id`, con traza `plan_payment_released`.
- Se llama desde dos lugares: el webhook ante estado terminal, y el nuevo `POST /api/mi-plan/cancelar-pago`.
- El polling pasa a `liberado` cuando ya no hay preferencia viva **y** `pending_plan_id` está limpio.
- `onMounted` descarta el marcador local si el backend no reporta pago en vuelo y la URL no trae `pago=`.

### Por qué `pending` no libera
Un pago `pending` (o `in_process` / `in_mediation`) puede completarse en cualquier momento, así que liberarlo sería una pérdida de dinero. Solo los estados terminales abren la puerta de salida. `PENDING` es también el `default` del mapeo, así que ante un estado desconocido la postura es conservadora: esperar, no liberar.

## Manejo de errores en la confirmación del pago

`confirmarUpgrade()` en el frontend tenía un `catch {}` **sin parámetro** que ante cualquier fallo llamaba a `iniciarPolling()`. Eso significaba que un pago rechazado por Mercado Pago, un pago ajeno (403) o un fallo de red se mostraban al usuario como *"Estamos procesando tu pago"* durante 90 segundos, y después caían en el banner de timeout. El mensaje real del backend, que el backend ya devolvía, se descartaba.

Se desglosó por código de respuesta, sin tocar el diseño de la pantalla:

| Caso | Estado | Comportamiento |
|---|---|---|
| `ok` / `already_upgraded` | `aprobado` | Recarga, mostrando el plan nuevo |
| 2xx con otro `status` | `procesando` | El webhook va a procesarlo, se espera |
| 400 | `error` | Mensaje real del backend + "Reintentar pago" |
| 403 | `error_fatal` | Mensaje real. Sin botón de reintento: el pago es de otro comercio y reintentar lo expondría |
| 502 | `verificar` | Mensaje real + "Consultar estado". El polling es **manual**, no automático |
| Sin respuesta (red) | `verificar` | Mismo tratamiento: no se afirma que el pago falló |

La distinción entre 400 y 502 es la clave: un 400 significa que el pago no está aprobado y el webhook no va a cambiar nada, mientras que un 502 significa que **no se pudo verificar** — el pago puede estar aprobado perfectamente. Por eso el 502 no ofrece "Reintentar" como acción principal sino "Consultar estado".

Se agregó `mensajeError` (ref) para transportar el texto del backend. `hayPagoEnCurso` no incluye los estados de error: son terminales y el usuario tiene que poder actuar.

## Pruebas ejecutadas
- `docker exec vendar-app-laravel.test-1 php artisan test --filter=SuscripcionServiceTest` — 15 tests OK.
- `docker exec vendar-app-laravel.test-1 php artisan test --filter=Modulo8_SuscripcionesTest` — 44 tests OK.
- `docker exec vendar-app-laravel.test-1 php artisan test --filter=Modulo8_SuscripcionesTest|SuscripcionServiceTest` — 70 tests, 475 assertions, OK.
- `docker exec vendar-app-laravel.test-1 php artisan test --filter=MercadopagoGatewayTest` — 11 tests OK.
- `docker exec vendar-app-laravel.test-1 php artisan test --filter=PedidoWebStockTest` — 18 tests OK (estaba roto, ver bugs corregidos).
- `docker exec vendar-app-laravel.test-1 php artisan test --filter="Modulo8_SuscripcionesTest|SuscripcionServiceTest|MercadopagoGatewayTest|PedidoWebStockTest"` — **102 tests, 537 assertions, 0 fallos**.
- `npm run build` — OK (después de los cambios de banners).
- `php -l` en los 4 archivos PHP tocados — sin errores de sintaxis.
- Suite completa — ver sección "Notas de la suite completa".

### Tests nuevos de `Modulo8_SuscripcionesTest`
| Test | Qué cubre |
|---|---|
| `test_renovar_mismo_plan_con_cuenta_al_dia_extiende_el_vencimiento` | P7.2.3 redefinido: prórroga desde el vencimiento vigente + `payments.amount` poblado |
| `test_renovar_plan_proximo_a_vencer_extiende_un_mes` | El caso del reporte (5 días) |
| `test_renovar_mismo_plan_con_pago_de_otro_comercio_es_rechazado` | Regresión del bug de verificación → 403 |
| `test_renovar_mismo_plan_con_pago_rechazado_es_rechazado` | Pago `rejected` → 400 |
| `test_confirmar_dos_veces_el_mismo_pago_no_extiende_dos_meses` | Idempotencia + `assertDatabaseCount('payments', 1)` |
| `test_webhook_y_confirmacion_http_aplican_la_misma_prorroga` | Paridad entre ambos caminos |
| `test_mi_plan_expone_estado_por_vencer_con_vencimiento_visible` | Prop `suscripcion` (5 días) |
| `test_mi_plan_expone_estado_vencida_y_habilita_renovar` | Prop `suscripcion` (vencida + suspendido) |
| `test_mi_plan_expone_estado_activa_sin_habilitar_renovar` | `puede_renovar === false` |
| `test_mi_plan_expone_estado_sin_vencimiento` | `sin_vencimiento` |
| `test_plan_actual_incluye_el_estado_para_el_polling` | `/api/mi-plan/plan-actual` |
| `test_no_se_genera_una_segunda_preferencia_con_un_pago_en_vuelo` | 409 + `Http::assertSentCount(1)` |
| `test_confirmar_el_pago_libera_el_bloqueo_de_pago_en_vuelo` | El 409 se levanta al confirmar |
| `test_generar_preferencia_distingue_renovacion_de_cambio_de_plan` | Flag `es_renovacion` |

### Tests nuevos de `SuscripcionServiceTest` (unitarios, sin DB)
Fórmula `nuevaFechaVencimiento` (vigente / vencido / sin vencimiento / el día del vencimiento / sin drift en meses de 31 días / sin acumulación de meses) y matriz completa de `estadoSuscripcion` (activa, por_vencer, umbral exacto de 10, vencida, día del vencimiento, suspendido con vencimiento lejano, sin vencimiento, suspendido sin vencimiento, comercio inexistente).

### Tests del ciclo de vida del pago en vuelo
| Test | Qué cubre |
|---|---|
| `test_preferencia_creada_deja_el_pago_marcado_en_vuelo` | Escenario 1: `pago_en_vuelo` con plan y minutos, y `pending_plan_id` seteado |
| `test_pago_pendiente_no_modifica_la_suscripcion` | Escenario 2: 3 ticks de polling, plan y vencimiento intactos, sin fila `payments` |
| `test_elegir_otro_plan_con_preferencia_en_vuelo_da_409_sin_pisar_el_pendiente` | Escenario 3: el caso exacto del reporte. `Http::assertSentCount(1)` y `pending_plan_id` sigue siendo el primer plan |
| `test_webhook_aprobado_aplica_el_cambio_y_limpia_el_pendiente` | Escenario 4: plan aplicado, vencimiento extendido, pendiente limpio, y el polling ve la condición de éxito |
| `test_webhook_con_pago_no_aprobado_no_aplica_y_libera_el_pendiente` | Escenario 5, con data provider para `rejected` / `cancelled` / `expired`: nada aplicado + se puede reintentar |
| `test_webhook_con_pago_pendiente_no_libera_el_estado` | `pending` no abre la puerta de salida |
| `test_cancelar_pago_desde_la_pantalla_libera_y_permite_reintentar` | Escenario 5 bis: el endpoint nuevo y la traza |
| `test_sin_preferencia_no_hay_pago_en_vuelo` | El estado inicial no inventa un pago pendiente |
| `test_la_ventana_de_bloqueo_expira_sola` | La preferencia huérfana se autolimpia al vencer el TTL |
| `MercadopagoGatewayTest` (11 casos) | Mapeo de estados de MP, incluida la regresión de `expired` vs `pending` |
| `test_confirmar_con_mp_inaccesible_devuelve_502_y_no_altera_la_suscripcion` | El 502 no existía como test. Verifica que no altera la suscripción |
| `test_los_errores_de_confirmacion_siempre_traen_mensaje_legible` | Contrato con el frontend para 403 |
| `test_el_400_de_pago_no_aprobado_trae_mensaje_legible` | Contrato con el frontend para 400 |

## Resultados
- Renovar el plan propio prorroga el período en los cuatro escenarios (vigente, por vencer, vencido, suspendido) y reactiva la cuenta.
- `confirmarUpgrade()` ya no confía en el `payment_id`: exige pago aprobado y `external_reference` del comercio.
- Webhook y confirmación HTTP aplican exactamente la misma fórmula, con la guarda de idempotencia dentro de la transacción.
- La pantalla muestra fecha de vencimiento y una acción clara en toda la ventana de preaviso.
- No se rediseñó `/mi-plan`, no se tocó `esPlanActual()` y no se agregaron migraciones.
- El backend es la fuente de verdad del pago en vuelo. El frontend ya no decide si algo está procesándose a partir del `localStorage` del navegador.
- Un pago pendiente nunca toca `vencimiento_pago` ni el plan: el polling sigue reportando el estado real y solo cierra por confirmación o por liberación.
- Un pago rechazado, cancelado o expirado no aplica nada, libera `pending_plan_id` y deja al usuario reintentar sin chocar con el 409.

## Criterios de aceptación
- [x] Con el plan por vencer, `/mi-plan` muestra la acción de renovar.
- [x] Renovar el mismo plan extiende `vencimiento_pago` (antes no lo hacía).
- [x] La cuenta suspendida o vencida se reactiva al pagar.
- [x] Un `payment_id` falso, ajeno o no aprobado no altera la suscripción.
- [x] El mismo pago no prorroga dos veces, venga por HTTP o por webhook.
- [x] No se pueden generar dos preferencias simultáneas para el mismo comercio.
- [x] `/mi-plan` y `/api/mi-plan/plan-actual` exponen el estado calculado en servidor.
- [x] Una preferencia abierta se refleja como pago en vuelo en backend y frontend, y deshabilita los botones.
- [x] Mientras el pago está pendiente, el polling no lo da por aplicado ni modifica `vencimiento_pago`.
- [x] Elegir otro plan con una preferencia en vuelo da 409 y no pisa `pending_plan_id`.
- [x] Un pago aprobado aplica el plan, extiende el vencimiento y cierra el estado pendiente.
- [x] Un pago rechazado, cancelado o expirado no aplica nada y libera el estado, permitiendo reintentar.
- [x] La pantalla nunca queda en "Estamos procesando tu pago" sin una acción para salir.
- [x] Un 400/403/502 muestra el mensaje real del backend en vez de caer en polling.
- [x] El 403 no ofrece reintento, porque el pago pertenece a otro comercio.
- [x] El 502 ofrece "Consultar estado" en vez de afirmar que el pago falló.
- [x] Sin migraciones, sin cambios en el esquema, sin rediseño de la pantalla.
- [x] Tests del módulo, unitarios del servicio y build en verde.

## Pendientes
- **Cargar `MERCADOPAGO_WEBHOOK_SECRET` en `.env` / producción.** La clave está declarada y vacía. Sin eso el webhook responde 401 y ninguna renovación se aplica. Es el paso que bloquea el flujo en producción y requiere ir al panel de Mercado Pago: no se puede obtener desde el repositorio. Acción del usuario.
- Verificación manual E2E del retorno post-pago vía `/retorno` + webhook con una cuenta real de Mercado Pago.
- `confirmarUpgrade()` ya no traga los errores: el frontend distingue 400, 403, 502 y error de red, y muestra el mensaje real. Lo que queda como mejora futura es mostrar ese mensaje **también** en la pantalla de `pagarPlan()` (el redirect a Mercado Pago), que hoy usa un `alert()`.
- No hay infraestructura de tests de JavaScript en el proyecto (no hay Vitest ni Jest en `package.json`), así que el manejo de errores del frontend quedó verificado solo por build y por los tests de backend del contrato. Si se quiere cobertura real, hay que agregar esa herramienta.
- No se restringió el rol `Encargado` en `/mi-plan` (no estaba pedido).
- **Nada commiteado.** Todo el trabajo está en el working tree.

## Notas de la suite completa
Baseline medido con `git stash` sobre las 9 clases afectadas: **15 tests fallidos sin los cambios de esta fase**, con mensajes típicos de roles/permisos (`403 is identical to 404`, `302 is identical to 200`, `false is true`). Son preexistentes y ajenos al módulo de suscripciones: ningún archivo que esta fase toca participa de esas aserciones.

Antes de esta fase la suite total marcaba 17 fallos. Dos eran propios y quedaron corregidos:
- `Modulo8_SuscripcionesTest` (pago sin verificar) — corregido.
- `PedidoWebStockTest` (`ArgumentCountError` al instanciar `MercadoPagoNotificacionController`) — preexistente de todas formas: el constructor viejo ya pedía 3 dependencias y el test pasaba 2. Se le restituyeron las 4 y la clase completa pasó (18/18).

Los 15 restantes se arrastran y no se tocan en esta fase: son de infraestructura de permisos (seeders de roles, `403` vs `404` en verificación de acceso multi-tenant) y ajenos al módulo.
