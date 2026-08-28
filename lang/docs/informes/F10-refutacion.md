# F10 — Refutación de la Auditoría (Abogado del Diablo)

> **Tipo**: Verificación adversarial del informe previo (read-only, sin implementación)
> **Fecha**: 2026-08-06
> **Misión**: demostrar que `F10-certificacion-final.md` está EQUIVOCADO.
> Cada bloqueante fue releído en código y contra `docs/arca/`. Si hay evidencia
> contraria, se cierra como falso positivo; si se sostiene, se reconfirma.
> **Resultado**: 11 de 13 bloqueantes CONFIRMADOS, **1 FALSO POSITIVO (B2)**,
> 1 parcialmente exagerado (B3). El veredicto NO APTO se mantiene.

---

## Resumen de la refutación

| ID | Veredicto | Qué cambió |
|----|-----------|------------|
| B1 | ✅ Confirmado | Manual usa `generationTime`; código genera `generatedAt`. Schema exige mayúsculas/minúsculas exactas (WSAA §10.10). |
| B2 | ❌ **Falso positivo** | El manual NO define `Resultado='O'`. "Aprobado con observaciones" mantiene `Resultado='A'`. El código SÍ acepta esos CAE. |
| B3 | ⚠ Parcial | La RG 4291/4444 y el código 10015 (excluyente) existen y aplican. PERO el umbral NO es fijo: es "monto según RG4444" (dinámico), y la validación 10014 es solo para lotes. El riesgo real es el rechazo por `DocNro=0`, no "10014". |
| B4 | ✅ Confirmado | El ledger no persiste snapshot de emisor/receptor; el QR sí. |
| B5 | ✅ Confirmado | Única ref productiva: docblock `EmisionService.php:23`. Solo test. |
| B6 | ✅ Confirmado | Migración `2026_04_17_175459` global sin `comercio_id`; escritura `ConfiguracionController.php:150-153` (last-writer-wins) y lectura `TicketBuilder.php:18`, `EnviarTicketDigital.php:44`. |
| B7 | ✅ Confirmado | `a4.blade.php:44-48` mezcla `nombre_empresa` GLOBAL (L44) con CUIT per-comercio (L45); `TicketVenta.php:26,51,74-75` usan datos globales. |
| B8 | ✅ Confirmado | `supervisord.conf:7-24` sin queue/scheduler; `QUEUE_CONNECTION=database`; `EnviarTicketDigital::dispatch` en `VentaController.php:500,691`; 6 tareas en `routes/console.php`. |
| B9 | ✅ Confirmado | `2026_08_02_000004:14` y `000005:18` `default('produccion')`; `Seeder:25` hardcodea producción; `.env.example` sin `ARCA_*`. |
| B10 | ✅ Confirmado | `.env` `APP_ENV=local`, `APP_DEBUG=true`, `LOG_LEVEL=debug`; SOAP trace atado a APP_DEBUG (`config/services.php`). |
| B11 | ✅ Confirmado | `bootstrap/app.php:14` `trustProxies(at: '*')`. |
| B12 | ✅ Confirmado | `Caddyfile:1-3` `:80` sin TLS; `compose.yaml` publica `443:443` pero Caddy nunca escucha 443. |
| B13 | ✅ Confirmado | Sin `pg_dump`/spatie; `README.md:35` promete backups sin respaldo. |

---

## B1 — TRA WSAA: `generatedAt` vs `generationTime`

### Intento de refutación
- ¿El código usa la etiqueta correcta? → NO.
- `app/Facturacion/Infrastructure/Arca/Wsaa/WsaaClient.php:121`:
  ```xml
  <generatedAt>{$generado->format('c')}</generatedAt>
  ```
- Manual oficial `docs/arca/WSAAmanualDev.pdf`, sección 5.1 y ejemplos extraídos (L212, L221, L244):
  ```xml
  <generationTime>2019-09-26T10:09:20</generationTime>
  <generationTime>2019-09-26T13:56:14.467-03:00</generationTime>
  <generationTime>2019-09-27T10:11:51.532-03:00</generationTime>
  ```
- Todos los ejemplos (request y response) usan `generationTime`. En la sección de programación PHP del propio manual se construye así:
  ```php
  $TRA->header->addChild('generationTime', date('c', date('U')-60));
  ```
- La sección 10.9 titula "GenerationTime en el futuro o más de 24 horas de antigüedad" y la 10.10 "No se ha podido interpretar el XML contra el schema", donde advierte: "las mayúsculas y minúsculas del XML deben respetarse" y "Formato incorrecto de la fecha en los campos generationTime y expirationTime del TRA".

### Conclusión B1
**✅ CONFIRMADO.** No hay forma de refutar: todos los ejemplos oficiales usan `generationTime`, el código genera `generatedAt`, y el schema se valida con mayúsculas/minúsculas exactas. El riesgo de rechazo WSAA es real y es la puerta de entrada a toda emisión.

---

## B2 — `Resultado='O'` descartado como rechazo

### Intento de refutación
El informe original afirmaba que el manual documenta `Resultado='O'` = "aprobado con observaciones, se le asigna el CAE", y que `CaeMapper.php:20` (`if ($resultado !== 'A') return null;`) lo descartaría.

Verificación exhaustiva en ambos manuales:
- En `manual-desarrollador-ARCA-COMPG-v4-0.pdf` (líneas 964-972) el flujo es:
  - "No supera alguna de las validaciones no excluyentes, el comprobante es aprobado con observaciones, se le asigna el CAE con la fecha de vencimiento"
  - "No supere alguna de las validaciones excluyentes, el comprobante no es aprobado"
  - "las validaciones excluyentes ... provocan un rechazo y las validaciones no excluyentes aprueban la solicitud pero con observaciones."
- Es decir, **"aprobado con observaciones" mantiene `Resultado='A'`** (el CAE se asigna igual). El valor `'O'` **no aparece en ningún manual**.
- En `wsfev1-RG-4291...pdf` (líneas 8513-8529) los únicos valores de `Resultado` documentados para lote CAEA son:
  - "Aceptación total ... Resultado será igual A"
  - "Rechazo total ... Resultado será igual a R"
  - "Rechazo parcial ... Resultado será igual a P"
  - La enumeración `A / R / P` es para **lotes** (múltiples comprobantes). El código envía siempre `CantReg=1` (un solo comprobante), por lo que `P` parcial no aplica.
- Ejemplo del manual (línea 8580): "Informa una Factura A ... que no supera alguna de las validaciones No Excluyentes. Genera una Aprobación del comprobante con Observaciones." → la respuesta muestra `Resultado>A</Resultado>` (aprobado) con `<Observaciones>` dentro del `FECAEDetResponse`.

Con el código actual:
```php
if ($resultado !== 'A') { return null; }
```
Un comprobante "aprobado con observaciones" llega con `Resultado='A'` y CAE → el mapper lo acepta y persiste el CAE. **No hay CAE huérfano ni hueco de numeración.**

### Conclusión B2
**❌ FALSO POSITIVO.** La premisa del informe original era incorrecta: no existe `Resultado='O'` en la documentación oficial; "aprobado con observaciones" mantiene `'A'` y el código lo maneja correctamente. (Matiz: el mapper no expone las `Observaciones` de vuelta al usuario — eso ya está cubierto como mejora A7, no como bloqueante.)

---

## B3 — RG 4291/4444: DocTipo/DocNro obligatorios para Factura B

### Intento de refutación
- ¿Existe la regla? → SÍ, confirmada en `manual-desarrollador-ARCA-COMPG-v4-0.pdf`.
  - Validación **10015** (tabla de **Validaciones Excluyentes** de `FECAEDetRequest`):
    "Si el campo DocTipo es distinto de 80, 86 u 87, deberá verificarse que se ingrese uno de los valores devueltos por el método FEParamGetTiposDoc y que se informe el campo DocNro."
  - "Para pedidos individuales (CbteDesde igual a CbteHasta) tipo B con montos superiores a monto en pesos resultante según RG4444 el campo DocTipo deberá ser igual a algunos de los valores devueltos por el método FEParamGetTiposDoc excepto 99 y deberá informar el campo DocNro."
  - El historial del manual confirma: "Se modifican las validaciones de los códigos 1417, 1418, 1419, 1422, 10014, 10015 con el fin de dar soporte a todos los comprobantes B > a $10000 según RG 4291" y luego "...según Resolución General 4444/2019".
- El código (`FECAERequestBuilder.php:214-224`) siempre envía `DocTipo=96`/`DocNro=0` cuando no hay CUIT de receptor. Para una Factura B individual con importe total mayor al monto RG4444, **10015 es excluyente** → ARCA rechaza (no aprueba con observaciones).

### Correcciones al informe original
- El umbral NO es un valor fijo de $10.000 en el código de ARCA vigente: el manual lo refiere como "monto en pesos resultante según RG4444" (dinámico). $10.000 fue el valor original de RG 4291 que RG 4444/2019 modificó. El punto sigue siendo que **existe un umbral** sobre el cual se exige identificación.
- El informe original citaba el código de error **10014**. Ese código es para **pedidos múltiples** (`CbteHasta ≠ CbteDesde`); como VendAR siempre emite comprobantes individuales, el error aplicable es **10015**, no 10014.

### Conclusión B3
**⚠ PARCIALMENTE CORRECTO (se mantiene como bloqueante, con precisión).**
- ✅ Correcto: existe una regla RG 4291/4444; el código no la implementa; para una Factura B > umbral sin identificación, ARCA rechaza con **10015 (excluyente)**.
- ❌ Incorrecto: citar "rechazo 10014" (no aplica a comprobantes individuales) y presentar $10.000 como monto fijo vigente (es dinámico "según RG4444").
- El bloqueante se mantiene: hoy VendAR no puede emitir Factura B sobre el umbral sin riesgo de rechazo.

---

## B4 — Sin snapshot de emisor/receptor en el ledger

### Intento de refutación
- Se revisó `database/migrations/2026_08_02_000006_create_comprobantes_fiscales_table.php` completo: las columnas son `venta_id, comercio_id, punto_venta, tipo, letra, numero, cae, vencimiento_cae, neto, iva, total, qr, comprobante_original_id, estado, motivo_fallo`.
- **No existen columnas** `emisor_cuit`, `emisor_razon_social`, `receptor_cuit`, `receptor_nombre`, etc.
- `EloquentComprobanteFiscalRepository.php:160` reconstruye el emisor llamando `emisorDesdeConfig()` que lee la config ACTUAL (`:190-205`); el receptor se reconstruye desde la venta actual (`:207-229`).
- El único snapshot persistido es el QR (`:171-173` y columna `qr`) y el desglose (`:69,238-248`).
- Impacto: si el comercio cambia CUIT/razón social/condición o el consumidor sus datos, la reimpresión/PDF históricos divergen del QR/CAE ya persistidos. Esto es un problema de trazabilidad real.

### Conclusión B4
**✅ CONFIRMADO.** No existe evidencia que refute. El ledger guarda QR y desglose, pero no emisor/receptor.

---

## B5 — `CaePerdidoHandler` sin cablear

### Intento de refutación
- Búsqueda de `CaePerdidoHandler` en todo el repo: 32 matches.
- Única referencia en producción: docblock `EmisionService.php:23` ("La resolución de CAE perdido queda en CaePerdidoHandler").
- Instanciado solo en `tests/Unit/FacturacionDomain/CaePerdidoHandlerTest.php:41`.
- Sin binding en `AppServiceProvider`, sin ruta/controller, sin dispatch.

### Conclusión B5
**✅ CONFIRMADO.** Código muerto de producción: el handler existe y está testeado, pero ninguna acción operativa lo invoca. Ante un CAE otorgado y respuesta perdida (timeout), no hay recovery.

---

## B6 — Configuración global sin `comercio_id`

### Intento de refutación
- Migración `2026_04_17_175459_create_configuracions_table.php:12-19`: la tabla `configuraciones` tiene `id, clave (unique), valor, tipo, grupo` — **sin `comercio_id`**.
- `ConfiguracionController.php:128-155` escribe por `updateOrCreate(['clave' => ...])` global (last-writer-wins entre tenants). Se confirmó la whitelist de claves (L130-144) y el bucle de escritura (L148-155).
- `TicketBuilder.php:18` y `EnviarTicketDigital.php:44` leen con `Configuracion::pluck('valor','clave')` global.
- `ticket_digital_auto_email` (clave global) decide si se envía el email con el comprobante fiscal de TODOS los comercios (L44-47 de EnviarTicketDigital).

### Conclusión B6
**✅ CONFIRMADO.** Contaminación multi-tenant R/W real. Un comercio pisa la config de todos y el envío de tickets fiscales se gobierna por una clave global.

---

## B7 — Identidad mezclada entre tenants en ticket/PDF/email

### Intento de refutación
- `resources/views/facturacion/a4.blade.php:44-48`:
  - L44: `<h1>{{ $ticket['empresa']['nombre'] }}</h1>` → proviene de `Configuracion::pluck` GLOBAL (`TicketBuilder.php:18-30`).
  - L45: `CUIT: {{ $ticket['fiscal']['emisor']['cuit_formateado'] }}` → proviene del comprobante reconstruido desde `ConfiguracionFiscalComercio` PER-comercio.
  - L47: `domicilio_fiscal` per-comercio con fallback al global.
- `Mail/TicketVenta.php:26,51,74-75` usa `ticket['empresa']['nombre']` (global) en subject/header/footer.

### Conclusión B7
**✅ CONFIRMADO.** En un mismo comprobante fiscal conviven datos de dos fuentes: encabezado global y CUIT por comercio. Riesgo de comprobante fiscal legal con identidad incorrecta.

---

## B8 — Sin worker de cola ni scheduler

### Intento de refutación
- `docker/8.5/supervisord.conf:7-24`: solo `php-fpm` y `nginx`. Sin `queue:work` ni `schedule:work`.
- `.env`: `QUEUE_CONNECTION=database`.
- `EnviarTicketDigital` implementa `ShouldQueue` (`:17`) y se despacha en `VentaController.php:500,691`.
- `routes/console.php:15-30`: 6 tareas programadas (transferencias, compras, mora, lotes, promos, pedidos web).

### Conclusión B8
**✅ CONFIRMADO.** Sin worker, los jobs de tickets digitales (con CAE/PDF/QR) quedan pendientes indefinidamente; sin scheduler, no corren las tareas programadas.

---

## B9 — Defaults a `produccion` + `.env.example` sin ARCA

### Intento de refutación
- `2026_08_02_000004_create_certificados_fiscales_table.php:14`: `enum('entorno',['produccion','homologacion'])->default('produccion')`.
- `2026_08_02_000005_create_configuracion_fiscal_comercios_table.php:18`: `default('produccion')`.
- `ConfiguracionFiscalComerciosSeeder.php:25`: `'entorno' => 'produccion'` hardcodeado.
- `.env.example` (35 líneas): sin ninguna variable `ARCA_*`; `config/services.php` espera `ARCA_WSAA_WSDL_PRODUCCION/HOMOLOGACION`, `ARCA_WSFE_*`, `ARCA_PADRON_*`, `ARCA_SOAP_TIMEOUT`, etc.

### Conclusión B9
**✅ CONFIRMADO.** Cualquier comercio recién dado de alta o deploy nuevo apunta a producción; y el template de entorno no documenta los endpoints ARCA.

---

## B10 — `.env` de desarrollo en el stack

### Intento de refutación
- `.env:2,4,21`: `APP_ENV=local`, `APP_DEBUG=true`, `LOG_LEVEL=debug`.
- `config/services.php`: `'trace' => env('APP_DEBUG', false)`.
- El comando `inspire` de `routes/console.php:10-12` es `hourly()` (nunca se definió frecuencia para el comando, que es irrelevante).

### Conclusión B10
**✅ CONFIRMADO.** Si algo falla, stack traces y vars de entorno al cliente (SOAP trace con credenciales del certificado en memoria).

---

## B11 — `trustProxies(at: '*')`

### Intento de refutación
- `bootstrap/app.php:14`: `$middleware->trustProxies(at: '*');`
- Sin restricción a la IP del proxy real (Caddy/nginx/cloud).

### Conclusión B11
**✅ CONFIRMADO.** Cualquier cliente puede enviar `X-Forwarded-For` y falsificar IP (rate limiting, auditoría, geolocalización).

---

## B12 — Sin TLS

### Intento de refutación
- `docker/caddy/Caddyfile:1-3`:
  ```
  :80 {
      reverse_proxy laravel.test:80
  }
  ```
  Escucha solo `:80`, sin dominio, sin bloque `tls`.
- `compose.yaml`: el servicio nginx publica `${APP_PORT:-80}:80` y el servicio Caddy publica `443:443`, pero Caddy escucha `:80` → el 443 nunca se usa.

### Conclusión B12
**✅ CONFIRMADO.** No hay TLS funcional; credenciales de facturación viajan en claro.

---

## B13 — Sin backups

### Intento de refutación
- Búsqueda de `pg_dump`/`spatie/laravel-backup` en app/, config/, routes/, database/, composer.json: sin resultados.
- `README.md:35`: "Datos en la nube - Backups automáticos diarios." sin respaldo técnico.
- `compose.yaml`: solo volumen persistente `sail-pgsql` (retención local, sin copia off-site).

### Conclusión B13
**✅ CONFIRMADO.** No existe mecanismo de backup; el claim comercial no está soportado.

---

## Re-cálculo del estado final

### Veredicto
**🔴 NO APTO para producción. Se MANTIENE.**

### Bloqueantes
- ✅ **Confirmados (11)**: B1, B4, B5, B6, B7, B8, B9, B10, B11, B12, B13.
- ❌ **Falsos positivos (1)**: B2 (`Resultado='O'` no existe en la doc; aprobado con observaciones mantiene `'A'`).
- ⚠ **Parciales (1)**: B3 (regla confirmada, código de error 10015 no 10014; umbral dinámico "según RG4444").

### Impacto en el cumplimiento legal ARCA (los 3 que más importaban)
- B1: **confirmado** → es el verdadero bloqueante de emisión (puerta de entrada WSAA).
- B2: **falso positivo** → NO había que "corregir" el mapper para un `'O'` que no existe; se ahorra una corrección innecesaria.
- B3: **confirmado con precisión** → se debe implementar el umbral RG4444 y manejar el código 10015.

### Puntaje por área ajustado
- Facturación ARCA: de 55 → 58 (B2 falso positivo alivia un ítem, pero B1/B3/B4/B5 siguen).
- Producción: 18 → **20** (dos ítems menos de los 13 bloqueantes).
- El resto de áreas no cambia: siguen 11 🔴 de infraestructura/multi-tenant.

### Estimación corregida
- Antes: 3–4 semanas. Ahora (con B2 fuera del camino): **3–4 semanas** sigue siendo válido porque B1, B3, B4, B5 y el stack (B6-B13) no se tocaron. La corrección de B2 no formaba parte del camino crítico de tiempo, así que el plazo total no mejora.

---

*Informe de refutación generado por auditoría read-only F10. Ningún archivo de código fue modificado.*
