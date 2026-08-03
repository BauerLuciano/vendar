# Envío de emails con Resend

VendAR usa [Resend](https://resend.com) como proveedor de correo transaccional.
La integración se hace con el paquete oficial `resend/resend-laravel`, que registra
un transporte `resend` en Symfony Mailer (Laravel Mail).

## Archivos clave

- `config/mail.php` → define el mailer `resend` (transport `resend`).
- `config/services.php` → `resend.key` lee `RESEND_KEY` del `.env`.
- `.env.example` → variables de referencia (sin secretos).
- `app/Console/Commands/MailTest.php` → `php artisan mail:test {email}`.

## Variables de entorno

```env
MAIL_MAILER=resend
RESEND_KEY=
MAIL_FROM_ADDRESS="pedidos@tudominio.com"
MAIL_FROM_NAME="${APP_NAME}"
```

- `MAIL_MAILER=resend` → usa Resend como mailer por defecto.
- `RESEND_KEY` → API key (formato `re_xxxx...`). Nunca commitees este valor.
- `MAIL_FROM_ADDRESS` → dominio verificado en Resend.
- `MAIL_FROM_NAME` → nombre visible del remitente.

> El paquete Resend lee `RESEND_KEY` vía `config('services.resend.key')`.
> NO uses `RESEND_API_KEY` (variable interna del paquete); VendAR estandariza `RESEND_KEY`.

### Otros mailers (compatibilidad)

`config/mail.php` mantiene los mailers `smtp`, `sendmail`, `ses`, `postmark`,
`log`, `array` y `failover`. Para desarrollo podés probar con `log`:

```env
MAIL_MAILER=log
```

## Setup paso a paso

### 1. Crear cuenta en Resend

1. Entrá a https://resend.com y registrate (Google o email).
2. Verificá tu cuenta desde el mail que te envían.

### 2. Generar la API Key

1. En el dashboard, andá a **API Keys** → **Create API Key**.
2. Permisos: `Sending access`.
3. Copiá la key (solo se muestra una vez). Formato: `re_xxxxxxxxxxxxxxxxxxxxxxxx`.
4. Agregala al `.env` local y a la configuración de producción:

```env
RESEND_KEY=re_xxxxxxxxxxxxxxxxxxxxxxxx
```

### 3. Verificar un dominio

Para enviar desde un dominio propio:

1. **Domains** → **Add Domain** → ingresá tu dominio (ej: `vendar.com.ar`).
2. Según el tipo (web/SPF), agregá en tu DNS los registros que te dan:
   - `SPF`, `DKIM` (dos registros `CNAME` con `host.sendgrid.net` es de otro proveedor;
     en Resend son `CNAME` a `sendgrid.net` / `sendgrid.net` según el plan, seguí el
     asistente que te muestra los valores exactos) y `MX` (opcional, para recibir).
3. Esperá la propagación DNS (minutos a horas). Resend marca el dominio como
   **verified** cuando detecta los registros.
4. `MAIL_FROM_ADDRESS` debe usar un email en ese dominio verificado.

### 4. Probar con el sandbox de Resend

Sin verificar un dominio podés usar el **sandbox** (solo a tu propio email):

1. En **Domains** existe `onboarding@resend.dev` (sandbox).
2. Configurá temporalmente:

```env
MAIL_FROM_ADDRESS="onboarding@resend.dev"
```

3. Enviá el correo de prueba a tu propia casilla (Resend solo permite enviar al
   email de tu cuenta en sandbox):

```bash
php artisan mail:test tu@email.com
```

4. Verificá la bandeja de entrada. Si llega, la integración está OK.

> Ojo: desde sandbox los emails pueden ir a spam y solo se envía a tu cuenta.

### 5. Producción

- [ ] Dominio verificado en Resend.
- [ ] `MAIL_FROM_ADDRESS` con el dominio verificado (no `resend.dev`).
- [ ] `RESEND_KEY` seteada (secret manager / CI secrets), nunca en el repo.
- [ ] `php artisan config:cache` después de cambiar env vars.
- [ ] Probá `php artisan mail:test destinatario@real.com`.
- [ ] (Opcional) Webhooks: el paquete registra `POST /resend/webhook` para
      entregas/rebotes. Configurá `RESEND_WEBHOOK_SECRET` si lo usás.

## Probar el envío

```bash
php artisan mail:test correo@gmail.com
```

Mensajes posibles:

- `Falta la API Key de Resend...` → definí `RESEND_KEY`.
- `No se pudo enviar el correo: ...` → revisá la key y que el dominio de origen
  esté verificado. El detalle del error se muestra en pantalla.
- `Correo de prueba enviado a ...` → éxito.

## Probar el flujo de verificación de email

1. Registrate en `/register` (elegí un plan + nombre de negocio).
2. El sistema crea el negocio y envía el mail de verificación vía Resend.
3. Abrí el link firmado del mail → `email_verified_at` queda seteado.
4. Volvé a ingresar a `/verify-email` → debe redirigir al dashboard.
5. Para reenviar: `POST /email/verification-notification` (botón "reenviar" de la vista).

## Probar recuperación de contraseña

1. `/forgot-password` → ingresá tu email.
2. Resend envía el `ResetPasswordNotification` (mailer por defecto).
3. Abrí el link `reset-password/{token}` y cambiá la contraseña.
4. Iniciá sesión con la nueva clave.

## Mails existentes en el sistema

Todos usan el mailer por defecto (`resend` en producción):

| Flujo                | Origen                                    |
|----------------------|-------------------------------------------|
| Verificación email   | `User::sendEmailVerificationNotification` |
| Reset de contraseña  | `ResetPasswordNotification` (Laravel)      |
| Ticket digital       | `App\Mail\TicketVenta`                    |
| Pre-orden proveedor  | `App\Mail\PreOrdenProveedor`              |
| Orden confirmada     | `App\Mail\OrdenConfirmadaProveedor`       |

## Checklist para producción

- [ ] `resend/resend-laravel` en `composer.json` (`^1.4`).
- [ ] `railsware/mailtrap-php` removido (código muerto).
- [ ] `config/mail.php` incluye mailer `resend` y mantiene los demás.
- [ ] `config/services.php` incluye `resend.key`.
- [ ] `.env.example` con `MAIL_MAILER=resend`, `RESEND_KEY`, `MAIL_FROM_*`.
- [ ] Dominio verificado en Resend y usado en `MAIL_FROM_ADDRESS`.
- [ ] `RESEND_KEY` seteada en producción (no hardcodeada).
- [ ] `php artisan config:cache` en deploy.
- [ ] `php artisan mail:test` OK en staging y producción.
- [ ] Revisar bandeja spam con sandbox (`onboarding@resend.dev`).
