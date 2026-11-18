# BUILD — Correcciones puntuales de REVIEW Etapa 1

**Fecha:** 2026-11-17  
**Alcance:** resolver los hallazgos justificables de `REVIEW-ETAPA-1-FRONTEND.md` sin reabrir ni rediseñar la Etapa 1.  
**Base:** `PLAN-ETAPA-1-FRONTEND.md`, `BUILD-ETAPA-1-FRONTEND.md` y `REVIEW-ETAPA-1-FRONTEND.md`.  
**Estado:** correcciones aplicadas y verificadas estáticamente. Las pruebas manuales de la aplicación siguen pendientes. **No se declara aprobada definitivamente la Etapa 1.**

---

## 1. Resumen ejecutivo

| Hallazgo | Decisión | Resultado |
|---|---|---|
| **H-01** `required` oculto de `nombre_empresa` | Corregido | Se retiró el `required` del frontend porque el backend lo declara `nullable` y no hay regla de negocio que lo exija. |
| **H-06** botones de `metodos_pago_pos` sin `type="button"` | Corregido | Se agregó `type="button"` a `Editar` y `✕`; los botones de guardar conservan `type="submit"`. |
| **H-02** relativas sin `/` en `esUrlSegura` | Revisado y conservado | Todas las relativas aceptadas resuelven al mismo origen; no hay riesgo de XSS ni de origen externo. Se documenta la decisión. |
| **H-03** falta de guarda `res.data.success` | Revisado y conservado | No es un defecto reproducible con el contrato actual: el endpoint devuelve `{ success: true, url }` en 2xx y los errores son 4xx/5xx capturados por axios. |
| **H-04** fallo asíncrono de `router.reload` | Revisado y conservado | Es una mejora defensiva, no un defecto del flujo normal. La subida ya fue exitosa y el fallback sigue existiendo. |
| **H-05** Escape bloqueado por un toast | Corregido | Se agregó una condición que distingue toast de diálogo modal; Escape vuelve a cerrar modales con un toast visible. |

Se modificaron **dos archivos de código frontend** y se creó este informe. No se modificaron backend, rutas, API, payloads, migraciones, base de datos, dependencias, `Modal.vue`, `AGENTS.md` ni skills.

---

## 2. Hallazgos revisados

### 2.1 H-01 — Campo `required` oculto en Configuración

**Archivo:** `resources/js/Pages/Configuracion/Index.vue`

**Situación anterior:** el input de `nombre_empresa` tenía `required`. El atributo ya existía en HEAD, pero no estaba dentro de un formulario, por lo que no se validaba. Con el formulario principal de C-02 quedó activo. Si el campo estaba vacío y el usuario enviaba desde POS, Sistema o Tienda, el navegador bloqueaba el submit del campo oculto de General y no mostraba un error visible.

**Investigación:**

- El backend declara `nombre_empresa` como `nullable|string|max:255` en `app/Http/Controllers/ConfiguracionController.php:141`.
- El ticket usa un fallback si el valor es null: `app/Services/Ticket/TicketBuilder.php:27` (`$config['nombre_empresa'] ?? 'VendAR'`).
- El reporte de ventas también usa `?? 'VendAR'` si el valor es null en `app/Http/Controllers/ReporteController.php:329-331`.
- No hay otra regla de negocio que exija el nombre para guardar la configuración.
- En el formulario principal no quedó ningún otro `required`; el único `required` del archivo era el de `nombre_empresa`.
- Los únicos constraints nativos restantes en el formulario principal son `maxlength` y los `min`/`max` de `mora_dias_gracia` y `mora_tasa_interes`; no son campos obligatorios.
- El formulario de `metodos_pago_pos` tiene su propia validación de backend para `metodo_pago`, pero está en un formulario visible e independiente; no se vio afectado.

**Decisión:** retirar el atributo `required` del input. Se mantiene `maxlength="255"`, el mapeo `form.errors.nombre_empresa` y la validación de backend. Así el frontend queda alineado con el contrato `nullable` y no se bloquea el guardado desde otra pestaña por este campo.

**Cambio aplicado:** `resources/js/Pages/Configuracion/Index.vue:293` — input de `nombre_empresa` sin `required`.

**Justificación:** no se elimina una validación de negocio real; se elimina una restricción de HTML que contradecía al backend y que sólo funcionaba de forma invisible. Si en el futuro el nombre pasa a ser obligatorio, la validación debe mostrarse de forma visible y gestionarse antes del submit, no mediante un campo oculto.

---

### 2.2 H-06 — Botones de `metodos_pago_pos`

**Archivo:** `resources/js/Pages/Configuracion/Index.vue`

**Situación anterior:** los botones `Editar` y `✕` de cada método de pago no declaraban `type`. Estaban fuera de todo formulario, por lo que hoy no producían envíos accidentales, pero quedaban frágiles ante un futuro movimiento dentro de un `<form>`.

**Decisión:** agregar `type="button"` a ambos botones. No se tocaron sus handlers ni sus clases.

**Cambios aplicados:**

- `resources/js/Pages/Configuracion/Index.vue:557` — `Editar` con `type="button"`.
- `resources/js/Pages/Configuracion/Index.vue:558` — `✕` con `type="button"`.

**Verificación:** los tres botones que deben enviar formularios conservan `type="submit"`:

- `Configuracion/Index.vue:527` — Guardar Ajustes del formulario principal.
- `Configuracion/Index.vue:606` — Guardar del formulario de `metodos_pago_pos`.
- `Configuracion/Index.vue:889` — Guardar del formulario de `storefront`.

No se alteraron las acciones `editPmc`, `eliminarPmc`, `guardarPmc` ni `resetPmcForm`.

---

### 2.3 H-02 — URLs relativas en `esUrlSegura`

**Archivo:** `resources/js/Pages/Productos/Componentes/ModalProducto.vue:335-346`

**Situación:** `esUrlSegura` acepta `http://`, `https://` y rutas relativas del mismo origen. Para las relativas que empiezan con `/` compara el origen; para el resto acepta cualquier resultado con protocolo `http:` o `https:`. Eso incluye `imagen.jpg`, `./imagen.jpg` y `../imagen.jpg`, que el plan no mencionaba explícitamente.

**Prueba ejecutada:** matriz Node con la misma lógica, usando `https://vendar.test` como origen:

| Entrada | Aceptada | Resolución |
|---|---:|---|
| `imagen.jpg` | Sí | `https://vendar.test/imagen.jpg` |
| `../imagen.jpg` | Sí | `https://vendar.test/imagen.jpg` |
| `./imagen.jpg` | Sí | `https://vendar.test/imagen.jpg` |
| `/imagen.jpg` | Sí | `https://vendar.test/imagen.jpg` |
| `https://dominio-valido.com/imagen.jpg` | Sí | `https://dominio-valido.com/imagen.jpg` |
| `http://dominio-valido.com/imagen.jpg` | Sí | `http://dominio-valido.com/imagen.jpg` |
| `javascript:alert(1)` | No | — |
| `data:text/html,...` | No | — |
| `//dominio-externo.com/imagen.jpg` | No | — |
| `..\imagen.jpg` | No | — |
| `\dominio-externo.com\imagen.jpg` | No | — |
| vacío / espacios | No | — |
| `blob:...` | No | — |
| `file:///etc/passwd` | No | — |
| `HTTPS://dominio-valido.com/imagen.jpg` | Sí | `https://dominio-valido.com/imagen.jpg` |

**Conclusión:** todas las relativas aceptadas resuelven al mismo origen. El uso previsto es un `<img>` y la URL se valida antes de asignar `src`; no se ejecuta JavaScript ni se permite un origen externo por una relativa. La aceptación de relativas sin `/` es segura y compatible con el uso previsto.

**Decisión:** no modificar `esUrlSegura`. No se relaja la validación: se mantienen los rechazos de esquemas peligrosos, URLs protocol-relative y backslashes. La diferencia con la letra del plan queda documentada en este informe y en `REVIEW-ETAPA-1-FRONTEND.md`; no hay riesgo real que justifique un cambio de comportamiento.

---

### 2.4 H-03 y H-04 — Robustez de comprobantes

**Archivo:** `resources/js/Pages/Cliente/Panel.vue`

**Situación revisada:**

- La subida y la actualización visual están separadas.
- Los errores de subida se capturan y muestran un toast de error.
- El `finally` limpia el estado y el input, permitiendo reintentar.
- El fallback `router.reload({ only: ['pedidos'] })` sigue presente.
- El contrato actual del endpoint es `{ success: true, url }` en 2xx (`app/Http/Controllers/PedidoWebController.php:314-317`).

**H-03 — falta de guarda `res.data.success`:**

No se reproduce como defecto con el contrato actual. El endpoint sólo devuelve 2xx con `success: true` y `url`; los errores de validación son 422 y los errores internos son 500, ambos rechazados por axios y capturados por el `catch`. Además, axios envía `Accept: application/json`, por lo que una sesión vencida en la ruta `auth:consumidor` se resuelve como 401 JSON, no como un HTML 200 de login. Restaurar la guarda sería una mejora defensiva, no una corrección de un defecto reproducible.

**H-04 — fallo asíncrono de `router.reload`:**

`router.reload` es asíncrono y no lanza una excepción capturable por el `try/catch` que lo rodea. Si la recarga falla, la subida ya fue exitosa y el toast de éxito sigue siendo correcto; el riesgo es que la card quede desactualizada hasta que el usuario recargue. Es una mejora defensiva de borde, no un defecto del flujo normal. El fallback actual se conserva.

**Decisión:** no modificar `Cliente/Panel.vue`. Se evita agregar complejidad o cambiar el timing de los toasts sin un defecto reproducible. Si en el futuro el contrato de la API cambia o se quiere cubrir el fallo de la recarga, se puede agregar una guarda de `success/url` y un `onError` en `router.reload` como mejora separada.

---

### 2.5 H-05 — Escape con toast visible

**Archivo:** `resources/js/Pages/CajaDiaria/Index.vue`

**Situación anterior:** `cerrarModales` retornaba si `Swal.isVisible()`. SweetAlert2 devuelve `true` también para un toast, por lo que Escape no cerraba un modal mientras había un toast visible.

**Decisión:** agregar un helper que sólo considera bloqueante un diálogo modal, no un toast:

```js
// Bloquea Escape para diálogos modales; un toast no debe impedir cerrar un modal.
const haySwalModal = () => Swal.isVisible() && !Swal.getPopup()?.classList.contains('swal2-toast');
```

Luego, el guard de Escape usa `haySwalModal()` en lugar de `Swal.isVisible()`.

**Cambios aplicados:**

- `resources/js/Pages/CajaDiaria/Index.vue:707-708` — helper `haySwalModal`.
- `resources/js/Pages/CajaDiaria/Index.vue:711` — guard de `cerrarModales`.

**Verificación aislada con SweetAlert2:**

| Estado | `Swal.isVisible()` | Clase del popup | `haySwalModal()` |
|---|---:|---|---:|
| Toast visible | `true` | `swal2-popup swal2-toast swal2-icon-success swal2-show` | `false` |
| Modal de confirmación visible | `true` | `swal2-popup swal2-modal swal2-show` | `true` |

**Resultado:** con un toast visible, Escape ya no se bloquea y puede cerrar el modal de Caja. Con una confirmación o diálogo modal visible, Escape sigue bloqueado y no se cierran modales por debajo. No se modificaron las confirmaciones existentes ni la prioridad de cierre.

---

## 3. Archivos modificados

| Archivo | Hallazgo | Cambio |
|---|---|---|
| `resources/js/Pages/Configuracion/Index.vue` | H-01 | Se retiró `required` del input de `nombre_empresa`. |
| `resources/js/Pages/Configuracion/Index.vue` | H-06 | Se agregó `type="button"` a `Editar` y `✕`. |
| `resources/js/Pages/CajaDiaria/Index.vue` | H-05 | Se agregó `haySwalModal` y se cambió el guard de Escape. |
| `docs/informes/BUILD-CORRECCIONES-REVIEW-ETAPA-1.md` | — | Este informe. |

No se modificaron:

- `resources/js/Pages/Cliente/Panel.vue` (H-03/H-04 conservados).
- `resources/js/Pages/Productos/Componentes/ModalProducto.vue` (H-02 conservado y documentado).
- Backend, controladores, modelos, servicios, rutas, API, payloads, migraciones, base de datos, dependencias, `Modal.vue`, `AGENTS.md` ni skills.

**Nota:** el working tree ya contenía cambios ajenos a esta tarea (backend, rutas y otros archivos de Etapa 1). Este BUILD no los modificó; los archivos de la tabla anterior son los únicos tocados por esta corrección.

---

## 4. Pruebas ejecutadas

| Prueba | Comando / método | Resultado |
|---|---|---|
| Build de producción | `npm run build` | **OK**. 951 módulos transformados; build final en 4,54 s; sin errores ni warnings. |
| Whitespace / diff | `git diff --check` | **OK**, sin errores. |
| H-01 estático | Búsqueda de `required` en `Configuracion/Index.vue` | **OK**: 0 coincidencias. |
| H-01 contrato | Lectura de `ConfiguracionController.php` | **OK**: `nombre_empresa` es `nullable`. |
| H-06 estático | Búsqueda de botones y `type` en `Configuracion/Index.vue` | **OK**: `Editar` y `✕` con `type="button"`; los 3 submits conservan `type="submit"`. |
| H-02 matriz de URLs | Node con la misma lógica de `esUrlSegura` | **OK**: relativas aceptadas resuelven al mismo origen; esquemas peligrosos, `//` y backslashes rechazados. |
| H-05 toast vs modal | Chromium headless con SweetAlert2 | **OK**: toast → `haySwalModal=false`; modal → `haySwalModal=true`. |
| H-03/H-04 | Revisión estática del flujo y del contrato | **Sin cambios**: no se reprodujo un defecto; se conserva la implementación. |
| Suite frontend | `package.json` | **No disponible**: no hay runner de tests frontend configurado. |
| Pruebas manuales de la app | No se levantó el entorno | **No ejecutadas**. |

### 4.1 Limitaciones

- No se levantó Laravel, la base de datos ni la aplicación.
- No se autenticó un usuario ni se usaron datos reales.
- No se ejecutaron las pruebas manuales 5.1–5.7 del plan.
- Las verificaciones de H-02 y H-05 son aisladas, con la misma lógica y la misma versión de SweetAlert2, pero no prueban la integración completa de las páginas.

---

## 5. Qué puede comprobarse visualmente

1. **Configuración → General / POS / Sistema / Tienda:** dejar `nombre_empresa` vacío, cambiar a POS, Sistema o Tienda y presionar **Guardar Ajustes**. El formulario debe enviarse y mostrar el toast de éxito; ya no debe quedar bloqueado por un campo oculto. Si el backend rechaza otro campo, el error debe seguir apareciendo en su pestaña.
2. **Configuración → Medios de Pago POS:** presionar **Editar** y **✕** en un método existente. No debe producirse un envío de formulario ni una recarga; las acciones deben seguir funcionando igual.
3. **Caja Diaria:** abrir un modal y provocar un toast (por ejemplo, registrar un movimiento). Mientras el toast esté visible, presionar Escape: el modal debe poder cerrarse. Abrir una confirmación de descarte y presionar Escape: la confirmación debe seguir bloqueando el cierre.
4. **Comprobantes de tienda:** el flujo visual no cambió; sigue mostrando un solo toast de éxito y actualizando la card. No se modificó el contrato de la API.
5. **ModalProducto:** el comportamiento visual no cambió; la validación de URL se conserva y se documenta como segura para relativas del mismo origen.

---

## 6. Riesgos y pendientes

- **H-01:** el nombre de la empresa puede quedar vacío; el ticket usa el fallback `VendAR`. Si el negocio decide que el nombre debe ser obligatorio, se debe implementar una validación visible y coordinada con backend, no un `required` oculto.
- **H-01 (riesgo residual):** los campos numéricos `mora_dias_gracia` y `mora_tasa_interes` de Sistema conservan `min`/`max`. Si un valor queda fuera de rango y se envía desde otra pestaña, el navegador podría bloquear el submit sin mostrar el error. No se modificaron en esta tarea porque el hallazgo era el `required` de `nombre_empresa` y el backend ya valida esos rangos; si se quiere cubrir también ese caso, agregar un guard de submit o quitar `min`/`max` en una próxima iteración.
- **H-02:** queda una diferencia con la letra del plan (relativas sin `/`), pero no hay riesgo de origen externo ni de ejecución de JavaScript. Si se quiere cumplir el plan al pie de la letra, se puede exigir `startsWith('/')` para relativas en una próxima iteración.
- **H-03/H-04:** no se aplicaron mejoras defensivas. Si cambia el contrato de la API o se quiere cubrir el fallo de `router.reload`, agregar guardas de `success/url` y `onError` en una tarea separada.
- **H-05:** el helper depende de la clase `swal2-toast` de SweetAlert2. La versión instalada fue verificada; si se actualiza la librería, repetir la prueba.
- **Pruebas manuales:** siguen pendientes las verificaciones 5.1–5.7 del plan. No se declaran aprobadas por inferencia.

---

## 7. Cumplimiento de restricciones

- No se modificó backend, controladores, modelos, servicios ni rutas.
- No se modificó la API ni el payload.
- No se modificaron migraciones ni base de datos.
- No se agregaron dependencias.
- No se alteraron reglas de negocio.
- No se migraron modales a `Modal.vue`.
- No se inició Etapa 2 ni Etapa 3.
- No se modificaron `AGENTS.md` ni skills.
- No se tocaron cambios preexistentes ajenos a esta tarea.
- Sólo se modificaron los dos archivos frontend indicados y se creó este informe.

---

## 8. Conclusión

Las correcciones puntuales de H-01, H-06 y H-05 quedaron aplicadas y verificadas estáticamente. H-02 se revisó y se conservó por ser seguro; H-03 y H-04 se revisaron y se conservaron por no ser defectos reproducibles con el contrato actual.

El build de producción pasa y no se modificó ningún contrato de backend. **La Etapa 1 no se declara aprobada definitivamente**: queda pendiente la ejecución de las pruebas manuales 5.1–5.7 en un entorno con aplicación, autenticación y datos de prueba.
