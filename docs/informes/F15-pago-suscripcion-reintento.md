# F15 - Pago de suscripción: reintento seguro, mensajes reales y configuración de desarrollo

## Objetivo

Mejorar el flujo de renovación / cambio de plan desde `/mi-plan` para que:

1. Los errores se expliquen dentro de la pantalla, con el motivo real del backend,
   sin `alert()` nativos ni mensajes genéricos de "Error al conectar con Mercado Pago".
2. Cuando existe una intención de pago sin confirmar, el usuario vea **desde el
   primer momento** las acciones «Reintentar pago» y «Liberar pago pendiente»,
   sin esperar los 90 segundos del polling.
3. «Reintentar pago» reanude **el mismo checkout** de Mercado Pago cuando exista,
   en lugar de generar una segunda preferencia (riesgo de cobro duplicado).
4. El checkout funcione en desarrollo desde `localhost`, `127.0.0.1`, dominios
   `*.test` y la IP de la red local, sin abrir la validación en producción.
5. Quede explícito que el bloqueo de 30 minutos es anti-duplicado y **no**
   significa que se haya cobrado.

No se rediseñó la pantalla ni se tocó la facturación global del Administrador
Global (B3). El plan se sigue aplicando únicamente con la confirmación de
Mercado Pago (webhook o verificación HTTP del pago).

## Causa raíz

- `MiPlan.vue` usaba `alert()` para todos los fallos de `pagarPlan()`. El
  usuario no tenía contexto ni forma de reintentar sin volver a empezar.
- El 409 por pago en vuelo sólo mostraba "Ya hay un pago en proceso", sin
  ofrecer reanudar el checkout ni liberar la intención. Sumado a que la UI
  recién mostraba acciones después del timeout de 90 s, la pantalla quedaba
  bloqueada para quien había elegido un plan por error y no pagó.
- La intención en vuelo sólo guardaba `plan_id` y `expira`; no había forma de
  recuperar el link del checkout para reanudarlo. La única salida era liberar
  y generar otro link, con dos preferencias pagables en paralelo.
- `origin` se validaba contra una lista que en desarrollo no incluía la IP de
  la red local ni otros puertos, y el retorno siempre volvía a `APP_URL`. Si el
  usuario entraba por `192.168.x.x` o por un dominio de desarrollo, MP lo
  devolvía a otro host.
- `MP_PUBLIC_URL` vacío producía URLs relativas (`/api/mercadopago/...`) en el
  `notification_url`, por lo que el webhook no podía llegar.
- La validación de `plan_id` estaba dentro del `try`: un plan inválido caía en
  el `catch` genérico y devolvía 500 "Error general" en vez del motivo real.
- Ante un error de la pasarela se limpiaba la caché pero no `pending_plan_id`,
  dejando el pendiente sucio.

## Archivos creados

- `docs/informes/F15-pago-suscripcion-reintento.md` (este informe).

## Archivos modificados

### `app/Http/Controllers/SuscripcionController.php`

- `generarPreferencia()`:
  - Validación fuera del `try` (422 real) y `plan_id` validado como entero para
    responder con un mensaje accionable si el plan ya no está disponible.
  - Lock de caché por comercio (`suscripcion:generar-pago:{id}`, 15 s) para que
    dos clics simultáneos no generen dos preferencias.
  - El 409 devuelve `pago_en_vuelo` y `init_point` (link del checkout vivo).
  - Back URLs con `origin` codificado para volver al host real del usuario.
  - `notification_url` siempre con base pública (webhook).
  - Guarda el link del checkout con `recordarCheckoutEnVuelo()`.
  - Ante `PaymentException` o error inesperado libera la intención completa
    (caché + `pending_plan_id`) y registra el motivo en el log.
  - Mensajes nuevos y accionables para origen no autorizado, plan inactivo,
    pasarela caída y error inesperado.
- `retorno()`:
  - Vuelve al `origin` validado si está permitido; si no, al host con el que se
    alcanzó `/retorno` y, como último recurso, a `APP_URL`.
- Helpers nuevos:
  - `esOriginDeDesarrollo()`: loopback en cualquier puerto, `*.test`, `*.local`
    e IPs privadas; **sólo fuera de producción**.
  - `basePublica()`: nunca devuelve una base vacía.
  - `baseBackUrls()`: usa el origen del usuario si es un dominio/IP alcanzable;
    para localhost usa la URL pública (MP no acepta localhost en `back_urls`).

### `app/Services/Suscripcion/SuscripcionService.php`

- `marcarPagoEnVuelo()` guarda `checkout_url => null`.
- Nuevo `recordarCheckoutEnVuelo()`: persiste el link de la preferencia sin
  extender la ventana de bloqueo.
- `pagoEnVuelo()` devuelve también `checkout_url` (compatible con intenciones
  viejas que no lo tienen: devuelve `null`).

### `app/Http/Controllers/MercadoPagoNotificacionController.php`

- Log del webhook no aprobado (estado y referencia).
- Log de advertencia cuando llega un pago aprobado sin `pending_plan_id`.
- Sin cambios en la aplicación del pago: sigue delegando en `SuscripcionService`.

### `config/services.php`

- `public_url` cae a `APP_URL` si `MP_PUBLIC_URL` está vacío.
- `allowed_return_origins` combina los defaults con `MP_ALLOWED_RETURN_ORIGINS`
  (antes, definir la variable borraba localhost de la lista).

### `.env` / `.env.example`

- `MP_ALLOWED_RETURN_ORIGINS` documentado con localhost, `127.0.0.1`, puertos
  8000, `vendar-app.test` y la IP de la red local.
- Comentario sobre el túnel ngrok y la URL pública del webhook.
- Sin secretos nuevos ni valores sensibles en el ejemplo.

### `resources/js/Pages/Suscripcion/MiPlan.vue`

- Se eliminaron los `alert()` de inicio y confirmación del pago.
- `mensajeError` y `detalleError` con banners inline para:
  - `error_inicio` (no se pudo generar el link),
  - `error` / `error_fatal` (pago rechazado o no verificable),
  - `verificar` (no se pudo consultar MP).
- `checkoutExistente` guarda el link de la intención viva (desde props, polling
  o respuesta 409).
- El 409 usa el plan real en vuelo (`pago_en_vuelo.plan_id`) para el polling y
  el reintento, no el plan del clic rechazado.
- `reintentarPago()`:
  - reanuda el mismo checkout si existe;
  - si la intención vieja no tiene link, la libera antes de generar otra;
  - si la liberación falla, **no** genera un pago nuevo.
- `cancelarPago()` no afirma que liberó si el backend falló.
- Banner unificado (`pendiente` / `en_curso` / `timeout`) con:
  - explicación de que no se cobró nada si no pagó,
  - minutos de bloqueo anti-duplicado y aclaración de que no implican cobro,
  - «Reintentar pago», «Liberar pago pendiente» y «Consultar estado ahora».
- `onMounted` reconcilia la intención viva con `localStorage` y usa
  `pending_plan_id` del comercio como respaldo.

### `tests/Feature/Modulo8_SuscripcionesTest.php`

- Expectativas actualizadas por:
  - nuevas back URLs con `origin`,
  - nuevo mensaje de origen no autorizado,
  - base de back URLs para `vendar-app.test`,
  - 409 con `init_point` / `pago_en_vuelo.plan_id`.
- Tests nuevos:
  - `/retorno` vuelve al origen permitido.
  - `/retorno` ignora un origen arbitrario.
  - `pago_en_vuelo.checkout_url` persiste y se expone en `plan-actual`.
  - Origen IP privada aceptado en desarrollo.
  - Origen IP privada rechazado en producción.

## Decisiones técnicas

### El bloqueo no es un cobro

La ventana de `MINUTOS_PAGO_EN_VUELO = 30` sólo evita que se genere una segunda
preferencia mientras la primera puede seguir pagándose. El plan se aplica
exclusivamente cuando Mercado Pago confirma el pago. La UI lo dice de forma
explícita para que el usuario no interprete el bloqueo como "ya me cobraron".

### Reanudar antes que regenerar

La intención en vuelo guarda `checkout_url`. Mientras la ventana siga viva,
«Reintentar pago» navega a ese mismo checkout. Sólo si la intención es vieja
(sin link) se libera y se genera una nueva preferencia.

### Orígenes: estricto en producción, tolerante en desarrollo

- La lista explícita (`MP_ALLOWED_RETURN_ORIGINS` + defaults + `APP_URL` +
  `MP_PUBLIC_URL`) se respeta siempre.
- Fuera de producción se aceptan además loopback, `*.test`, `*.local` e IPs
  privadas, para no bloquear el checkout cuando el frontend se sirve desde la
  LAN o desde otro puerto.
- En producción no hay tolerancia: un origen que no está en la lista se rechaza
  con 400 y no se envía nada a Mercado Pago.

### Webhook público, retorno por el navegador

- `notification_url` usa siempre la URL pública (ngrok o dominio), porque MP
  llama desde sus servidores.
- `back_urls` usa el origen real del usuario cuando es alcanzable. Para
  localhost usa la URL pública, porque MP no acepta `localhost` en `back_urls`.
- El `origin` viaja en la query de retorno y `/retorno` vuelve a ese host.

### B3 intacto

No se modificó la facturación global del Administrador Global ni sus banderas
(`precios_confirmados` / `cobros_habilitados` siguen en `false`). El lock y los
cambios de origen viven sólo en el autoservicio de suscripción.

## Bugs corregidos

1. `alert()` genéricos que no explicaban el error real.
2. Pantalla bloqueada 90 s antes de ofrecer reintentar o liberar.
3. 409 sin forma de reanudar el checkout vivo.
4. Posible cobro duplicado al reintentar generando una segunda preferencia.
5. Origen de desarrollo rechazado por lista incompleta.
6. Retorno a `APP_URL` aunque el usuario hubiera entrado por otro host.
7. `notification_url` relativa cuando `MP_PUBLIC_URL` estaba vacío.
8. 500 "Error general" ante un `plan_id` inválido.
9. `pending_plan_id` sucio cuando fallaba la pasarela.
10. Mensaje de pago rechazado que en realidad era un error de origen o de red.

## Pruebas ejecutadas

### Suite de suscripciones

```bash
docker exec vendar-app-laravel.test-1 php artisan test tests/Feature/Modulo8_SuscripcionesTest.php
```

Resultado: **69 tests, 507 assertions, 0 fallos** (los avisos `DEPR` son el
warning preexistente de `PDO::MYSQL_ATTR_SSL_CA`, no fallos).

### Regresión

```bash
docker exec vendar-app-laravel.test-1 php artisan test \
  tests/Feature/BuildB1_UsuariosSeguridadTest.php \
  tests/Feature/BuildB2_CajasUnicidadTest.php \
  tests/Feature/BuildB3_FacturacionTest.php \
  tests/Unit/Suscripcion/SuscripcionServiceTest.php
```

Resultado: **86 tests, 334 assertions, 0 fallos** (B1 28, B2 13, B3 20 y Unit 25).

### Build de frontend

```bash
npm run build
```

Resultado: **OK**, `954 modules transformed`, build en `28.19 s`.

### Configuración efectiva

```bash
php artisan tinker --execute="echo json_encode(config('services.mercadopago.allowed_return_origins'));"
```

Devuelve localhost, 127.0.0.1, `vendar-app.test`, puertos 8000, `https` de
`vendar-app.test` y la IP de la red local. `public_url` resuelve al túnel ngrok.

## Resultados

- Flujo de suscripciones: **69/69 tests OK**.
- Regresión B1/B2/B3 y Unit: **86/86 tests OK** (334 assertions).
- Build de producción: **OK**.
- Caché de pago en vuelo del comercio 1: sin intención viva al momento de la
  verificación (la ventana anterior expiró), por lo que no bloquea la prueba
  manual.

## Criterios de aceptación

- [x] No quedan `alert()` en el flujo de pago de `/mi-plan`.
- [x] Los errores de inicio y confirmación muestran el motivo real inline.
- [x] Con intención viva, «Reintentar pago» y «Liberar pago pendiente» se ven
      desde el primer momento.
- [x] Reintentar reanuda el mismo checkout cuando existe.
- [x] No se genera una segunda preferencia mientras hay una en vuelo.
- [x] El plan sólo se aplica con confirmación de Mercado Pago.
- [x] El checkout funciona desde localhost y desde la red local en desarrollo.
- [x] Un origen arbitrario se rechaza y no se envía nada a MP.
- [x] En producción no se aceptan orígenes de desarrollo.
- [x] `/retorno` vuelve al host correcto y no a un origen arbitrario.
- [x] B3 no se modificó.
- [x] `npm run build` OK.

## Pendientes

1. **Prueba manual end-to-end con Mercado Pago** (sandbox): elegir plan →
   preferencia → checkout → volver sin pagar → reanudar / liberar → pagar →
   webhook aprobado → verificar plan, funcionalidades y vencimiento.
   - Requiere túnel ngrok activo y `MP_PUBLIC_URL` apuntando al dominio vigente.
   - Si el túnel cambia, actualizar `MP_PUBLIC_URL` y reiniciar la app.
2. **Rotación del túnel ngrok**: el dominio actual es de desarrollo; en
   producción debe usarse el dominio real de la app.
3. **Preferencias creadas antes de este cambio**: no tienen `checkout_url`
   guardado. Se resuelven liberando antes de generar una nueva; la ventana es
   acotada (30 minutos).
4. **Cancelación remota de preferencias**: Mercado Pago no ofrece un endpoint
   directo para invalidar una preferencia ya creada. Liberar en VendAR limpia
   el bloqueo local y `pending_plan_id`; si el usuario paga un link viejo, el
   webhook lo registra pero no aplica un plan sin `pending_plan_id` (queda el
   warning en el log para auditoría).
