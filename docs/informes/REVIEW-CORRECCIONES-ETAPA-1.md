# REVIEW — Correcciones posteriores al REVIEW de Etapa 1

**Fecha:** 2026-11-17
**Rol:** REVIEWER
**Alcance:** revisión independiente de las correcciones aplicadas después de `REVIEW-ETAPA-1-FRONTEND.md`.
**Base:** `PLAN-ETAPA-1-FRONTEND.md`, `BUILD-ETAPA-1-FRONTEND.md`, `REVIEW-ETAPA-1-FRONTEND.md` y `BUILD-CORRECCIONES-REVIEW-ETAPA-1.md`.
**Modo:** revisión y documentación exclusivas. No se modificó código, dependencias, backend, rutas, API, DB, migraciones, `AGENTS.md` ni skills. No se alteraron cambios preexistentes.
**Veredicto:** **Aprobado con observaciones**.

> Este veredicto aprueba las correcciones puntuales revisadas. **La Etapa 1 no se declara cerrada definitivamente**: las pruebas manuales 5.1–5.7 siguen pendientes y queda una observación media por la validación nativa de campos numéricos ocultos (R-01).

---

## 1. Resumen ejecutivo

Las correcciones buscaban tres cosas concretas: eliminar el bloqueo silencioso del formulario de Configuración por el `required` oculto de `nombre_empresa`, restaurar el cierre por Escape cuando hay un toast de SweetAlert2 y evitar envíos accidentales de los botones de medios de pago.

Las tres correcciones son correctas y no introducen regresiones observables:

- **H-01:** el `required` de `nombre_empresa` ya no existe; el backend lo declara `nullable`, la columna `configuraciones.valor` es nullable y los consumidores del nombre tienen fallback. El payload y la validación backend no cambiaron.
- **H-06:** todos los botones de `Configuracion/Index.vue` tienen `type` explícito; los guardados son `type="submit"` y las acciones Editar/✕ son `type="button"` sin perder sus handlers.
- **H-05:** `haySwalModal()` distingue correctamente toast de confirmación modal y sin SweetAlert; el cierre accidental de modales de Caja no se reproduce.
- **H-02:** se conserva con justificación. Las rutas relativas aceptadas resuelven al mismo origen y los esquemas peligrosos se rechazan. Hay una desviación menor respecto del plan (acepta relativas sin `/` y formas absolutas como `http:evil.com`), pero no habilita XSS ni origen externo no permitido.
- **H-03 y H-04:** se conservan porque no hay un defecto reproducible con el contrato actual; las guardas propuestas son mejoras defensivas, no correcciones de un fallo concreto.

El build pasa y no hay errores de whitespace. La observación R-01 no es una regresión introducida por estas correcciones, pero sí es un riesgo residual de la misma clase que H-01: un campo numérico inválido en una pestaña oculta puede bloquear el submit sin error visible. Además, invalida la alternativa de la prueba 5.3 basada en `mora_dias_gracia = 366`.

---

## 2. Veredicto por hallazgo

| Hallazgo | Veredicto | Resultado |
|---|---|---|
| **H-01** `required` oculto de `nombre_empresa` | **Corregido y aprobado** | Se retiró el `required`; no quedan `required` en el archivo. Backend, payload y fallbacks consistentes. |
| **H-06** botones de `metodos_pago_pos` sin `type` | **Corregido y aprobado** | Todos los botones tienen `type` explícito; Editar/✕ son `type="button"`; los guardados son `type="submit"`. |
| **H-05** cierre por Escape con toast de SweetAlert2 | **Corregido y aprobado** | Toast no bloquea el cierre; confirmación modal sí lo bloquea; sin SweetAlert también cierra. |
| **H-02** relativas sin `/` en `esUrlSegura` | **Conservado y aceptado con observación menor** | Relativas y absolutas HTTP/HTTPS resuelven al mismo origen o a HTTP/HTTPS; esquemas peligrosos y protocol-relative se rechazan. No hay XSS. |
| **H-03** falta de guarda `res.data.success` | **Conservado y aceptado** | El contrato actual sólo devuelve 2xx con `success: true` y `url`; 422/500 son rechazados por axios. La guarda es defensiva, no obligatoria. |
| **H-04** fallo asíncrono de `router.reload` | **Conservado y aceptado** | El fallback es prácticamente inalcanzable en el flujo normal; el fallo de recarga no pierde datos. Es una mejora defensiva opcional. |
| **R-01** validación nativa de numéricos ocultos | **Observación media, no corregida por instrucción** | Un campo con `min`/`max`/`step` inválido en una pestaña oculta bloquea el submit sin error visible. No es una regresión de las correcciones, pero afecta el cierre de la etapa y la prueba 5.3 alternativa. |

---

## 3. H-01 — `required` oculto de `nombre_empresa`

### 3.1 Veredicto

**Corregido correctamente.** El defecto original era que el `required` de `nombre_empresa` quedó dentro del formulario principal con C-02, pero el campo vive en la pestaña General con `v-show`. Al enviar desde POS, Sistema o Tienda, el navegador validaba un control oculto y bloqueaba el submit sin burbuja visible.

### 3.2 Evidencia

- `resources/js/Pages/Configuracion/Index.vue:293` ya no tiene `required`:

  ```html
  <input v-model="form.nombre_empresa" type="text" maxlength="255" ...>
  ```

- `rg -n "required" resources/js/Pages/Configuracion/Index.vue` no devuelve coincidencias. El único `required` que existía era el de `nombre_empresa`.
- `app/Http/Controllers/ConfiguracionController.php:141` declara el campo como `nullable`:

  ```php
  'nombre_empresa' => 'nullable|string|max:255',
  ```

- La columna `configuraciones.valor` es nullable en `database/migrations/2026_04_17_175459_create_configuracions_table.php:15`.
- El payload no cambió: `form.transform` en `Configuracion/Index.vue:184-188` sigue expandiendo `...data` y sólo adapta los booleanos de stock, ticket digital y efectivo.
- La validación backend del resto de los campos sigue intacta (`ConfiguracionController.php:141-153` y `:75-88`).
- `maxlength="255"` y el bloque `form.errors.nombre_empresa` siguen presentes (`:293-294`).
- Los consumidores del nombre tienen fallback:
  - `app/Services/Ticket/TicketBuilder.php:27` → `?? 'VendAR'`.
  - `app/Http/Controllers/ReporteController.php:329-331` → `?? 'VendAR'`.
  - `resources/views/pdf/*.blade.php` → `?? 'MI NEGOCIO'` o `?? $comercio`.

### 3.3 Prueba de validación nativa

Se reprodujo en Chromium con un formulario aislado y un control dentro de un contenedor `display:none`:

| Caso | `validBefore` | `submitted` | Detalle |
|---|---:|---:|---|
| Sin `required`, vacío, oculto | `true` | `true` | El submit pasa. |
| Con `required`, vacío, oculto | `false` | `false` | `valueMissing`; el submit se bloquea. |

El input real no declara `name`; la prueba usó un control sin `name` y el bloqueo con `required` se reproduce igual. Por lo tanto, la eliminación del `required` es la corrección adecuada y no depende de un detalle del mensaje de consola.

### 3.4 Regresiones

No se encontraron regresiones:

- El backend ya aceptaba vacío; no se relajó una regla de negocio.
- Laravel 11 aplica `ConvertEmptyStringsToNull` por defecto, así que el vacío llega como `null` y los fallbacks `??` se activan.
- El nombre puede guardarse vacío, pero los tickets, reportes y PDFs tienen fallback.
- No se alteró el contrato del POST ni la validación del resto de los campos.
- No se perdió el `maxlength` ni la visualización de errores por campo.
- No hay otros `required` ocultos en el archivo.

**Observación:** si el negocio quiere que `nombre_empresa` sea obligatorio, no debe volver a usarse un `required` oculto. Habría que implementar una validación visible, cambiar la pestaña al campo y mostrar el error, o hacerlo obligatorio también en el backend.

---

## 4. R-01 — Validación nativa de campos numéricos ocultos

### 4.1 Descripción

Es la misma clase de problema que H-01, pero con restricciones nativas `min`, `max` y `step` en campos numéricos que viven en pestañas ocultas con `v-show`. Si el usuario ingresa un valor inválido en Sistema o Tienda y luego envía el formulario principal desde otra pestaña, Chromium bloquea el submit antes de que Inertia haga el POST. No hay burbuja visible porque el campo está en un contenedor `display:none`, y tampoco se ejecuta `onError`, porque el backend nunca recibe la petición.

### 4.2 Campos afectados

| Campo | Pestaña | Restricción nativa | Ejemplo inválido | Resultado |
|---|---|---|---|---|
| `mora_dias_gracia` | Sistema | `min="0" max="365"` + `step` 1 por defecto | `366` | `rangeOverflow`; submit bloqueado |
| `mora_tasa_interes` | Sistema | `step="0.1" min="0" max="100"` | `5.25` o `101` | `stepMismatch` o `rangeOverflow`; submit bloqueado |
| `limite_fiado_defecto` | Sistema | `step="0.01"` | `10.005` | `stepMismatch`; submit bloqueado |
| `costo_delivery_defecto` | Sistema | `step="0.01"` | `10.005` | `stepMismatch`; submit bloqueado |
| `envio_precio_base` | Tienda | `step="0.01"` | `10.005` | `stepMismatch`; submit bloqueado |
| `envio_precio_km` | Tienda | `step="0.01"` | `10.005` | `stepMismatch`; submit bloqueado |
| `envio_radio_km` | Tienda | `step` 1 por defecto | `5.5` | `stepMismatch`; submit bloqueado |

Líneas relevantes: `Configuracion/Index.vue:362`, `:384`, `:397`, `:404`, `:420`, `:428` y `:434`.

### 4.3 Evidencia en Chromium

Se probaron controles dentro de un contenedor `display:none`:

| Caso | `validBefore` | `submitted` | `invalid` |
|---|---:|---:|---|
| `min/max` válido, oculto | `true` | `true` | — |
| `min/max` overflow (`366`), oculto | `false` | `false` | `rangeOverflow` |
| `min/max` underflow (`-1`), oculto | `false` | `false` | `rangeUnderflow` |
| `step="0.1"` válido, oculto | `true` | `true` | — |
| `step="0.1"` mismatch (`5.25`), oculto | `false` | `false` | `stepMismatch` |
| `step="0.01"` mismatch (`10.005`), oculto | `false` | `false` | `stepMismatch` |
| `step="1"` mismatch (`5.5`), oculto | `false` | `false` | `stepMismatch` |

En todos los casos inválidos el submit no se dispara y el navegador registra un `invalid` en el control oculto. El campo visible sí muestra el mensaje nativo, pero el campo oculto no.

### 4.4 ¿Es intencional?

No hay evidencia de que el bloqueo silencioso sea intencional. Los `min`/`max`/`step` son razonables para la edición visible, pero el formulario único con pestañas `v-show` convierte cualquier inválido oculto en un bloqueo global. El backend ya valida los mismos rangos (`ConfiguracionController.php:149-153` y `:78-80`), así que el efecto no protege datos: sólo evita que el usuario reciba el 422 y el toast de error.

### 4.5 Impacto

- El usuario puede completar un valor inválido en Sistema o Tienda, cambiar de pestaña y encontrar que “Guardar Ajustes” no hace nada.
- No hay error visible, ni cambio de pestaña, ni toast.
- El `onError` que mapea errores a pestañas (`Configuracion/Index.vue:201-206`) no se ejecuta porque no hay respuesta del backend.
- Afecta configuraciones comerciales sensibles: mora, límite de fiado, costo de delivery y envío.
- No corrompe datos ni genera una venta incorrecta; el impacto es de configuración y de experiencia, no de integridad transaccional.

### 4.6 Relación con la prueba 5.3

La prueba 5.3 primaria sigue siendo viable: `transferencia_cbu` es `type="text"` sin `pattern`, por lo que letras llegan al backend y Laravel está configurado para responder 422; `onError` cambia a la pestaña Tienda.

La alternativa del plan —forzar `mora_dias_gracia = 366` desde DevTools— **no llega al backend** con el código actual: el `max="365"` nativo bloquea el submit antes del POST. Para ejecutar esa alternativa habría que desactivar la validación nativa del formulario o modificar el test. No es un defecto de las correcciones, pero es una inconsistencia entre el plan de pruebas y el comportamiento actual.

### 4.7 Recomendación

No se corrigió en esta revisión por instrucción expresa. Para una próxima iteración, la opción menos invasiva es agregar un guard en `guardarConfiguracion`:

1. Detectar el primer control inválido del formulario.
2. Cambiar `tabActiva` a la pestaña que lo contiene usando `camposPorTab`.
3. Esperar `nextTick`.
4. Enfocar el control y llamar a `reportValidity()`.

Así se conserva la validación nativa y el usuario ve el error en la pestaña correcta. Alternativas: quitar `min`/`max`/`step` y confiar sólo en el backend, o agregar `novalidate` y manejar toda la validación en Vue. La primera opción es la más consistente con el patrón actual.

---

## 5. H-06 — Botones sin `type` en medios de pago

### 5.1 Veredicto

**Corregido correctamente.** Los botones Editar y ✕ de `metodos_pago_pos` ahora tienen `type="button"`; los botones de guardado conservan `type="submit"`. No se encontraron envíos accidentales.

### 5.2 Evidencia

- `Configuracion/Index.vue:557-558`:

  ```html
  <button type="button" @click="editPmc(pmc)" ...>Editar</button>
  <button type="button" @click="eliminarPmc(pmc)" ...>✕</button>
  ```

- Los submits están en:
  - `:527` — formulario principal.
  - `:606` — formulario de medios de pago POS.
  - `:889` — formulario de storefront.
- Todos los botones del archivo tienen `type` explícito: navegación (`:238`, `:242`, `:246`, `:250`, `:254`, `:258`, `:262`), submit principal (`:527`), Editar/✕ (`:557-558`), submit y cancelar de PMC (`:606`, `:611`), avanzado (`:820`), reset de colores (`:886`) y submit de storefront (`:889`).
- Los handlers siguen intactos: `editPmc(pmc)`, `eliminarPmc(pmc)`, `resetPmcForm`, `resetColors`, `showAdvanced = !showAdvanced`.
- El formulario principal sólo tiene un submit; el formulario PMC tiene submit y un cancelar `type="button"`; el storefront tiene submit, reset y avanzado `type="button"`.

### 5.3 Regresiones

No se encontraron:

- Editar y ✕ siguen ejecutando sus acciones.
- No hay botones sin `type` que puedan submitear por defecto.
- No se agregaron submits nuevos.
- La navegación entre pestañas no envía el formulario.
- El botón de cancelar de PMC no envía.
- El reset de colores y el toggle de avanzado no envían.

---

## 6. H-05 — Cierre por Escape con toast de SweetAlert2

### 6.1 Veredicto

**Corregido correctamente.** `haySwalModal()` permite cerrar el modal de Caja cuando hay un toast visible y lo bloquea cuando hay una confirmación modal. Sin SweetAlert, el cierre funciona.

### 6.2 Evidencia

- `CajaDiaria/Index.vue:708`:

  ```js
  const haySwalModal = () => Swal.isVisible() && !Swal.getPopup()?.classList.contains('swal2-toast');
  ```

- `CajaDiaria/Index.vue:710-711` usa el helper como guarda del cierre por Escape.
- El listener se registra y se retira correctamente en `onMounted`/`onUnmounted` (`:744` y `:749`).
- La versión instalada es SweetAlert2 `11.26.25`; `package.json` declara `^11.26.24` y `package-lock.json` fija `11.26.25`.
- La clase `swal2-toast` existe en la versión instalada y se verificó en Chromium.

### 6.3 Prueba en Chromium

| Estado | `Swal.isVisible()` | Clase del popup | `haySwalModal()` | Escape sobre modal de Caja |
|---|---:|---|---:|---|
| Sin SweetAlert | `false` | — | `false` | Cierra el modal. |
| Toast visible | `true` | `swal2-popup swal2-toast swal2-icon-success swal2-show` | `false` | Cierra el modal. |
| Confirmación modal visible | `true` | `swal2-popup swal2-modal swal2-show` | `true` | No cierra el modal. |

### 6.4 Regresiones y prioridades

No se encontraron regresiones:

- Las confirmaciones de cierre de Caja (`cerrarModalCierre` y `cerrarModalGasto`, con `showCancelButton: true`) siguen teniendo prioridad.
- Si hay un toast visible y el modal de Caja tiene cambios sin guardar, Escape no pierde datos: el cierre sigue pasando por la confirmación correspondiente.
- Los modales de detalle/visor no tienen confirmación y se cierran normalmente.
- No se modificaron los flujos de SweetAlert existentes.

**Observación menor:** el helper depende de una clase interna de SweetAlert2 (`swal2-toast`). Está alineado con la versión fijada, pero conviene repetir la prueba si se actualiza a una versión mayor.

---

## 7. H-02 — Relativas sin `/` en `esUrlSegura`

### 7.1 Veredicto

**Conservado y aceptado con observación menor.** No hay un defecto de seguridad reproducible. La implementación acepta más formas relativas que el texto literal del plan, pero todas las relativas aceptadas resuelven al mismo origen y los esquemas peligrosos se rechazan.

### 7.2 Implementación revisada

`resources/js/Pages/Productos/Componentes/ModalProducto.vue:335-346`:

```js
const esUrlSegura = (url) => {
    if (typeof url !== 'string') return false;
    const valor = url.trim();
    if (!valor || valor.startsWith('//') || valor.includes('\\')) return false;
    try {
        const parsed = new URL(valor, window.location.origin);
        if (valor.startsWith('/')) {
            return parsed.origin === window.location.origin;
        }
        return parsed.protocol === 'http:' || parsed.protocol === 'https:';
    } catch {
        return false;
    }
};
```

### 7.3 Matriz de pruebas

Se ejecutaron 30 casos con `origin = https://vendar.test` y, para verificar la resolución real del documento, con `docBase = https://vendar.test/productos`.

**Aceptados y resueltos al mismo origen:**

- `imagen.jpg` → `https://vendar.test/imagen.jpg`
- `../imagen.jpg` → `https://vendar.test/imagen.jpg`
- `./imagen.jpg` → `https://vendar.test/imagen.jpg`
- `/imagen.jpg` → `https://vendar.test/imagen.jpg`

**Aceptados como HTTP/HTTPS absolutos:**

- `https://cdn.test/a.png`
- `http://cdn.test/a.png`

**Rechazados:**

- `javascript:alert(1)` y `JaVaScRiPt:alert(1)`
- `data:text/html,...`
- `vbscript:msgbox(1)`
- `//evil.test/a.png` y `///evil.test/a.png`
- Backslashes: `..\imagen.jpg`, `\evil.test\a.png`
- Vacío y sólo espacios
- `blob:https://vendar.test/uuid`
- `file:///etc/passwd`
- `about:blank`
- `mailto:a@b.com`
- `tel:123`
- `https://` (URL inválida)
- `C:/imagen.jpg` y `C:\imagen.jpg`

**Casos límite documentados:**

- `http:evil.com` se acepta y resuelve a `http://evil.com/`. Es una URL absoluta HTTP, permitida por la política actual; no es XSS.
- `https:evil.com` se acepta y resuelve al mismo origen como path `/evil.com`; no sale del origen.
- `%2f%2fevil.test/a.png` se acepta como path codificado del mismo origen; el navegador no lo interpreta como protocol-relative.

No se encontró ningún caso aceptado que resuelva a un origen externo distinto de HTTP/HTTPS permitido, ni ningún esquema ejecutable.

### 7.4 Justificación de conservación

- La validación usa `window.location.origin`, no una base incorrecta.
- Para relativas sin `/`, la resolución puede variar entre el origen y la URL del documento, pero **el origen siempre es el mismo**; la frontera de seguridad no depende de la ruta.
- El `<img>` real usa la base del documento; aun si la ruta resultante difiere, no puede salir del mismo origen con las formas relativas aceptadas.
- Los protocol-relative (`//`) y los backslashes se rechazan antes de parsear.
- No se reproduce XSS.

**Observación de contrato:** el plan pedía aceptar relativas con `/` y absolutas `http://`/`https://`. La implementación acepta además relativas sin `/` y formas absolutas como `http:evil.com`. No es un riesgo de seguridad, pero es una desviación del contrato. Si el plan se considera normativo, se puede endurecer en una próxima iteración; no es necesario para esta corrección.

---

## 8. H-03 — Falta de guarda `res.data.success`

### 8.1 Veredicto

**Conservado y aceptado.** No hay un defecto reproducible con el contrato actual.

### 8.2 Evidencia

- `app/Http/Controllers/PedidoWebController.php:296-318` devuelve en éxito:

  ```php
  return response()->json([
      'success' => true,
      'url' => $path,
  ]);
  ```

- Los errores de validación son 422 y los errores internos son 500; axios los rechaza y el `catch` de `Cliente/Panel.vue:115-119` muestra el toast de error.
- La ruta está protegida con `auth:consumidor` (`routes/web.php:150-152`).
- Axios envía `Accept: application/json`; Laravel `expectsJson()` devuelve 401 JSON para una sesión vencida, no un HTML 200 de login.
- El flujo normal sólo llega al bloque de éxito si la respuesta es 2xx con `success: true` y `url`.

### 8.3 Justificación

Agregar `if (!res.data.success || !res.data.url)` sería una mejora defensiva, pero no corrige un fallo reproducible. Si el contrato cambia, la guarda evitaría un éxito falso; mientras el endpoint mantenga el contrato actual, no es obligatoria. Se conserva para no agregar complejidad sin evidencia.

---

## 9. H-04 — Fallo asíncrono de `router.reload`

### 9.1 Veredicto

**Conservado y aceptado.** No hay un defecto concreto en el flujo normal; es una mejora defensiva de borde.

### 9.2 Evidencia

- `Cliente/Panel.vue:134` llama a `router.reload({ only: ['pedidos'] })` dentro de un `try/catch`.
- `router.reload` es asíncrono y no lanza una excepción capturable por ese `try/catch`. Es cierto.
- El fallback `else` es prácticamente inalcanzable porque el input de archivo sólo se renderiza dentro de `v-for="pedido in pedidos"` (`:294` y `:358`), y `subirComprobante` recibe un `pedido.id` de esa misma lista.
- El único caso es una actualización de `props.pedidos` durante la subida; en ese caso el `reload` es un refresco best-effort.
- Si el `reload` falla, la subida ya fue exitosa y el toast de éxito sigue siendo correcto; el riesgo es que la card quede desactualizada hasta una recarga manual. No hay pérdida de datos.
- Inertia puede mostrar su manejo global de errores de red; no es un fallo silencioso garantizado.

### 9.3 Justificación

Agregar `onError`/`onFinish` a `router.reload` o una guarda de `success/url` sería una mejora de robustez, pero no corrige un defecto reproducible. Se conserva para no cambiar el timing de los toasts ni agregar complejidad sin evidencia.

---

## 10. Regresiones y efectos secundarios

No se encontraron regresiones en las correcciones:

- **H-01:** no se cambió el payload, el backend ni la DB; no se relajó una regla de negocio; los fallbacks cubren el vacío.
- **H-06:** no se alteraron handlers ni se agregaron submits; los botones siguen haciendo lo mismo.
- **H-05:** no se alteró la prioridad de las confirmaciones; el toast no permite perder cambios sin confirmación.
- **H-02/H-03/H-04:** no se modificaron; no hay cambios que puedan introducir regresiones.
- **Build:** `npm run build` pasa sin warnings.
- **Whitespace:** `git diff --check` no reporta errores.

La única observación con impacto funcional es R-01, que ya existía como clase de problema y no fue introducida por estas correcciones. Sin embargo, debe decidirse antes de cerrar la etapa porque afecta la prueba 5.3 alternativa y puede producir un botón de guardado “muerto” sin explicación.

---

## 11. Pruebas ejecutadas

| Prueba | Método | Resultado |
|---|---|---|
| Build de producción | `npm run build` | OK, 951 módulos, ~4.15 s, sin warnings. |
| Whitespace del diff | `git diff --check` | OK, sin errores. |
| Búsqueda de `required` en Configuración | `rg -n "required" resources/js/Pages/Configuracion/Index.vue` | 0 coincidencias. |
| Tipos de botones | `rg -n "<button"` y revisión de los 15 botones | Todos con `type` explícito. |
| Estructura de formularios | `rg -n "<form|</form>"` y revisión de límites | 3 formularios; submits correctos. |
| Validación nativa H-01 | Chromium aislado, control oculto | Sin `required` envía; con `required` bloquea. |
| Validación nativa R-01 | Chromium aislado, `min`/`max`/`step` ocultos | Inválidos bloquean el submit sin error visible. |
| `haySwalModal()` | Chromium + SweetAlert2 11.26.25 | Sin SweetAlert `false`; toast `false`; modal `true`. |
| Matriz `esUrlSegura` | Node, 30 casos, origen y base de documento | 0 fallos; peligrosos rechazados; relativas al mismo origen. |
| Contrato de comprobantes | Revisión de controlador, ruta, axios y Laravel | 2xx con `success/url`; 422/500 rechazados; 401 JSON con `Accept: application/json`. |
| Fallback de nombre | Revisión de `TicketBuilder`, `ReporteController` y Blade | Fallbacks presentes. |
| Nullability de DB | Revisión de migración y controlador | `configuraciones.valor` nullable; `nombre_empresa` nullable. |

No se ejecutaron pruebas destructivas ni se alteraron datos reales.

---

## 12. Pruebas pendientes

Las pruebas manuales de la aplicación siguen pendientes. No se marcan como aprobadas por inferencia.

| Prueba | Estado | Motivo |
|---|---|---|
| **5.1** HTML/atributos de eventos en confirmaciones | Pendiente | Requiere aplicación levantada y flujos interactivos. |
| **5.2** Guardar sin cambios y con un valor seguro | Pendiente | Requiere autenticación, DB de pruebas y verificación de persistencia. |
| **5.3** Rechazo por validación del backend | Pendiente | La vía primaria (`transferencia_cbu` con letras) es viable; la alternativa `mora_dias_gracia = 366` queda bloqueada por R-01. |
| **5.4** Navegación entre pestañas sin envíos accidentales | Pendiente | Sólo se verificó estáticamente; falta la prueba con Enter y Network. |
| **5.5** Subida exitosa y fallida de comprobantes | Pendiente | Requiere pedido de prueba, archivos válidos/inválidos y consumidor autenticado. |
| **5.6** Apertura y cierre de todos los modales de Caja | Pendiente | Sólo se probó el helper de Escape de forma aislada. |
| **5.7** Teclado, foco y accesibilidad | Pendiente | Requiere navegación real con Tab/Enter/Espacio y árbol de accesibilidad. |

No hay suite frontend configurada en `package.json` y no hay Playwright/Puppeteer instalados. Sí hay `/usr/bin/chromium`, que se usó para las pruebas aisladas. Las pruebas aisladas validan la lógica y la versión instalada, pero no reemplazan la prueba integrada con la aplicación, la sesión y los datos reales.

---

## 13. Puntos positivos

- La corrección de H-01 está alineada con el contrato real del backend, en lugar de agregar una validación frontend artificial.
- H-06 se resolvió con el cambio mínimo y sin tocar handlers ni estructura.
- H-05 tiene un helper pequeño, legible y con un comentario que explica la intención.
- No se hicieron refactors innecesarios ni cambios en H-02/H-03/H-04 sin evidencia.
- El build y el diff se mantienen limpios.
- La decisión de conservar H-02/H-03/H-04 está documentada y es defendible.

---

## 14. Decisiones abiertas

1. **R-01:** ¿se corrige antes de cerrar la Etapa 1 o se acepta con seguimiento? Si se corrige, el guard de submit con cambio de pestaña y `reportValidity()` es la opción menos invasiva.
2. **Prueba 5.3:** ¿se ajusta la alternativa para no depender de `mora_dias_gracia = 366`, o se desactiva la validación nativa durante esa prueba?
3. **H-02:** ¿se acepta formalmente la desviación de relativas sin `/` y `http:evil.com`, o se prefiere endurecer a `^https?://` para absolutas y exigir `/` para relativas?
4. **`nombre_empresa`:** ¿el negocio quiere que sea obligatorio? Si sí, debe implementarse una validación visible y consistente con el backend; no volver al `required` oculto.

---

## 15. Conclusión

Las correcciones de H-01, H-05 y H-06 son correctas, están alineadas con el contrato del backend y no introducen regresiones observables. H-02, H-03 y H-04 se conservan con justificación técnica: no hay un defecto reproducible y las guardas propuestas son mejoras defensivas.

El veredicto es **Aprobado con observaciones** por R-01: la validación nativa de campos numéricos ocultos puede bloquear el guardado sin error visible y hace inviable la alternativa de la prueba 5.3 con `mora_dias_gracia = 366`. Esta observación no invalida las correcciones, pero debe resolverse o aceptarse explícitamente antes de cerrar la etapa.

**La Etapa 1 no se declara cerrada definitivamente.** Las pruebas manuales 5.1–5.7 siguen pendientes y deben ejecutarse con la aplicación levantada, autenticación y datos de prueba.
