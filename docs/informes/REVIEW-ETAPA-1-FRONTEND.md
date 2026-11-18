# REVIEW — Etapa 1 Frontend (C-01, C-03, C-02, A-01)

**Fecha:** 2026-11-17  
**Rol:** REVIEWER  
**Alcance:** revisión independiente de C-01, C-03, C-02 y A-01 sobre el código real.  
**Modo:** revisión y documentación exclusivas. No se modificó código de aplicación, backend, rutas, API, migraciones, base de datos, dependencias, `AGENTS.md`, skills ni cambios preexistentes.  
**Veredicto:** **Aprobado con observaciones**. No es aprobación final de la etapa: faltan las pruebas manuales 5.1–5.7 y resolver o aceptar formalmente el hallazgo H-01.

---

## 1. Resumen ejecutivo

La implementación de Etapa 1 está bien encaminada y los cuatro frentes principales quedaron atendidos:

- **C-01:** los ocho bloques `html` de SweetAlert2 quedaron estáticos, sin interpolación de datos, sin `innerHTML` con datos y sin `onerror` inline. Los valores se cargan por `textContent` o por nodos DOM.
- **C-03:** se corrigió `props.pedidos`, se separó la subida del comprobante de la actualización visual y se agregó el fallback `router.reload({ only: ['pedidos'] })`. El falso error posterior a una subida exitosa ya no debería ocurrir.
- **C-02:** el formulario principal envuelve General, POS, Sistema y Tienda Online y Pagos; `metodos_pago_pos` y `storefront` conservan formularios propios y no hay anidamiento. Se agregaron 27 mensajes de error y `onError` con cambio de pestaña.
- **A-01:** se eliminaron los `ri-` de los tres archivos del alcance, se reemplazaron por SVG inline, se agregaron roles y nombres accesibles a los cinco modales de Caja y se enrutó Escape por cierres seguros.

La revisión encontró **un hallazgo de severidad media** y **cinco observaciones de severidad baja**. El hallazgo medio es nuevo desde el punto de vista funcional: el `required` de `nombre_empresa`, que ya existía pero no estaba dentro de un formulario, ahora bloquea el envío desde POS/Sistema/Tienda cuando el campo está vacío y oculto, sin mostrar un error visible.

No se ejecutaron las pruebas manuales 5.1–5.7 contra la aplicación real. Las verificaciones aisladas de esta revisión no reemplazan esas pruebas y no se marcan como aprobadas por inferencia.

---

## 2. Método de revisión

1. Se leyeron completos `AUDITORIA-FRONTEND.md`, `PLAN-ETAPA-1-FRONTEND.md` y `BUILD-ETAPA-1-FRONTEND.md`.
2. Se separaron los cambios de Etapa 1 de los cambios ajenos del working tree con `git status` y `git diff`.
3. Se revisó el diff completo de los 10 archivos frontend de la etapa y se contrastó cada cambio con el plan aprobado.
4. Se revisaron los contratos de backend relevantes para C-02 y C-03 sin modificarlos.
5. Se reprodujeron `npm run build` y `git diff --check`.
6. Se ejecutaron verificaciones estáticas y pruebas aisladas en Chromium/Node para los puntos que no requieren la aplicación levantada.
7. No se levantó la aplicación, no se autenticó un usuario y no se ejecutaron las pruebas manuales 5.1–5.7.

---

## 3. Archivos revisados y separación de cambios

### 3.1 Archivos propios de Etapa 1

| Archivo | Cambio principal |
|---|---|
| `resources/js/Pages/Consumidores/Index.vue` | C-01: tabla de duplicados con HTML estático y filas por DOM/`textContent` |
| `resources/js/Pages/Productos/Componentes/ModalProducto.vue` | C-01: dos modales seguros, imagen validada y placeholder |
| `resources/js/Pages/Ingresos/Index.vue` | C-01: lista de aumentos con HTML estático y `<li>` por DOM |
| `resources/js/Pages/Pos/Terminal.vue` | C-01: modal de cantidad por kg con valores por `textContent` |
| `resources/js/Pages/Transferencias/Index.vue` | C-01: tres confirmaciones con HTML estático y `textContent` |
| `resources/js/Pages/Cliente/Panel.vue` | C-03: `props.pedidos`, subida/actualización separadas, fallback Inertia |
| `resources/js/Pages/Configuracion/Index.vue` | C-02: formulario principal, errores por campo, `onError`, `type="button"` |
| `resources/js/Pages/CajaDiaria/Index.vue` | A-01: SVG inline, roles, cierres accesibles, Escape, snapshots y foco |
| `resources/js/Components/Header.vue` | A-01: reemplazo de iconos Remix por SVG inline |
| `resources/js/Pages/Auth/ElegirSucursal.vue` | A-01: reemplazo de iconos Remix por SVG inline |

El diff de estos 10 archivos es de **671 inserciones y 153 eliminaciones** (`1.576` líneas de diff). `git diff --check` pasó sin errores.

### 3.2 Documentos de la etapa

| Archivo | Estado |
|---|---|
| `docs/informes/AUDITORIA-FRONTEND.md` | Insumo de la etapa, sin cambios de implementación |
| `docs/informes/PLAN-ETAPA-1-FRONTEND.md` | Plan aprobado, sin cambios de implementación |
| `docs/informes/BUILD-ETAPA-1-FRONTEND.md` | Informe del BUILD, contrastado contra el código real |

### 3.3 Cambios ajenos a Etapa 1

El working tree contiene cambios que **no** pertenecen a esta etapa y no fueron evaluados funcionalmente:

- `app/Http/Controllers/GlobalAdminController.php`
- `app/Http/Controllers/MercadoPagoNotificacionController.php`
- `app/Http/Middleware/VerificarEstadoCuenta.php`
- `app/Services/Suscripcion/PagoManualService.php`
- `app/Services/Suscripcion/SuscripcionService.php`
- `resources/js/Pages/AdminGlobal/Facturacion.vue`
- `routes/web.php`
- `tests/Feature/BuildB3_FacturacionTest.php`
- `docs/informes/BUILD-B3.md`
- `.opencode/`
- `docs/informes/B3.2.md`
- `docs/informes/OPENCODE-SKILLS.md`
- `resources/js/Utils/pagoManual.js`
- `resources/js/Utils/pagoManual.test.mjs`

No se tocaron backend, rutas, API, migraciones, base de datos, dependencias ni `resources/js/Components/Modal.vue` como parte de Etapa 1.

---

## 4. Resultado por hallazgo

### 4.1 C-01 — Confirmaciones SweetAlert2 seguras

**Estado: cumple, con una desviación menor de especificación (H-02).**

Evidencia revisada:

- Los cinco archivos afectados tienen **8 bloques `html`** de SweetAlert2 y **0 bloques con `${}`** de datos.
- No se encontró `innerHTML` con datos, `onerror` inline ni `javascript:` en los cinco archivos.
- `Consumidores` y `Ingresos` construyen filas y `<li>` con `document.createElement` y `textContent`.
- `Transferencias` usa `setSwalText` por `document.getElementById` y `textContent`; los IDs son únicos.
- `Pos/Terminal.vue` carga nombre y stock por `textContent`.
- `ModalProducto` carga nombre, marca, categoría y código por `textContent`; la imagen se valida antes de asignar `src` y el placeholder se muestra por listener `error`.
- Prueba aislada en Chromium con el patrón `textContent`: `xss=0`, `imgCount=0`, payloads escapados como texto literal.

La desviación H-02 no es un problema de XSS: `esUrlSegura` acepta rutas relativas que no empiezan con `/`, aunque el plan pedía aceptar sólo relativas con `/`.

**Pendiente:** la prueba 5.1 contra la aplicación real, con datos maliciosos en las ocho confirmaciones.

---

### 4.2 C-03 — Comprobante de tienda

**Estado: cumple, con dos observaciones menores de robustez (H-03 y H-04).**

Evidencia revisada:

- La referencia incorrecta `pedidos.find` quedó corregida como `props.pedidos.find`.
- La subida (`axios.post`) está en su propio `try/catch`; un fallo de subida muestra error y retorna.
- El `finally` limpia `subiendoComprobante`, `comprobanteFile` y el input, permitiendo reintentar.
- La actualización visual está separada: si el pedido existe, se asigna `comprobante_transferencia_url`; si no existe, se ejecuta `router.reload({ only: ['pedidos'] })`.
- El toast de éxito se muestra una sola vez y el toast de advertencia es distinto del error de subida.
- El endpoint `POST /api/pedidos/{pedido}/comprobante` responde 2xx con `{ success: true, url }`; el contrato actual es estable.

**Pendiente:** la prueba 5.5 con un pedido real, archivo válido y archivo inválido.

---

### 4.3 C-02 — Configuración general

**Estado: cumple parcialmente. La estructura es correcta, pero hay un hallazgo medio (H-01) y una observación menor (H-06).**

Evidencia revisada:

- Hay exactamente **3 formularios** y no hay anidamiento:
  - Formulario principal: líneas `272-532`.
  - Formulario de `metodos_pago_pos`: líneas `565-617`.
  - Formulario de `storefront`: líneas `622-895`.
- El formulario principal envuelve General, POS, Sistema y Tienda Online y Pagos.
- `metodos_pago_pos` y `storefront` quedaron fuera del formulario principal, cada uno con su propio `<form>`.
- `tabsDelFormPrincipal = ['general', 'pos', 'sistema', 'tienda']` y el footer sólo se muestra en esas pestañas.
- `camposPorTab` contiene **27 campos** y hay **27 mensajes `form.errors.*`**. `logo` está cubierto.
- `onError` detecta la primera pestaña con error, cambia `tabActiva` y muestra un toast genérico sin exponer el error crudo.
- Los **7 botones de navegación** (6 pestañas + Recargos) tienen `type="button"`.
- `form.transform`, `preserveScroll` y el payload actual se conservan.

El hallazgo H-01 es el único que impide declarar C-02 completamente cerrado: el `required` de `nombre_empresa` queda activo dentro del nuevo formulario principal y, si el campo está vacío y se envía el formulario principal desde POS, Sistema o Tienda (con General oculta), el navegador bloquea el envío sin error visible.

**Pendiente:** las pruebas 5.2, 5.3 y 5.4 contra la aplicación real.

---

### 4.4 A-01 — Iconos y cierres de Caja

**Estado: cumple, con una observación menor (H-05).**

Evidencia revisada:

- **0 usos de `ri-`** en `CajaDiaria/Index.vue`, `Header.vue` y `ElegirSucursal.vue`.
- `CajaDiaria` tiene un sprite SVG con **24 símbolos** y **22 usos**; no hay referencias faltantes. Los dos símbolos sólidos no usados de forma estática se referencian de forma dinámica.
- Los **5 diálogos** tienen `role="dialog"`, `aria-modal="true"` y `aria-labelledby` que apunta a un ID existente.
- Los **5 botones de cierre** tienen `aria-label`, `title` y `type="button"`.
- Los **3 `@click.self`** están sólo en modales de lectura; Arqueo y Movimiento Manual no cierran con clic fuera.
- Escape se enruta por detalle global → historial → cierre → gasto → panel global, y no actúa si SweetAlert está visible.
- Los formularios de Arqueo y Movimiento Manual usan snapshots para detectar cambios y piden confirmación antes de descartar.
- `focoStack`, `guardarFoco`, `restaurarFoco` y `enfocarCierre` están implementados; el focus trap general queda diferido a Etapa 3, como indica el plan.

La observación H-05 es menor: `Swal.isVisible()` también devuelve `true` para los toasts, por lo que Escape no cierra un modal mientras hay un toast visible.

**Pendiente:** las pruebas 5.6 y 5.7 con teclado, lector de pantalla y la aplicación real.

---

## 5. Hallazgos por severidad

| ID | Severidad | Hallazgo | Archivo principal |
|---|---|---|---|
| H-01 | **Media** | `required` oculto de `nombre_empresa` bloquea el envío del formulario principal desde POS/Sistema/Tienda sin error visible | `resources/js/Pages/Configuracion/Index.vue` |
| H-02 | Baja | `esUrlSegura` acepta relativas que no empiezan con `/`, contra el plan | `resources/js/Pages/Productos/Componentes/ModalProducto.vue` |
| H-03 | Baja | C-03 eliminó la guarda `res.data.success` y asigna `res.data.url` sin validar | `resources/js/Pages/Cliente/Panel.vue` |
| H-04 | Baja | El fallo asíncrono de `router.reload` no se captura ni muestra advertencia | `resources/js/Pages/Cliente/Panel.vue` |
| H-05 | Baja | `Swal.isVisible()` incluye toasts; Escape queda bloqueado mientras hay un toast | `resources/js/Pages/CajaDiaria/Index.vue` |
| H-06 | Baja | Botones `Editar`/`✕` de `metodos_pago_pos` sin `type="button"` | `resources/js/Pages/Configuracion/Index.vue` |

### H-01 — `required` oculto de `nombre_empresa`

- **Severidad:** Media.
- **Archivo y ubicación:**
  - `resources/js/Pages/Configuracion/Index.vue:272` — formulario principal.
  - `resources/js/Pages/Configuracion/Index.vue:273` — pestaña General oculta con `v-show`.
  - `resources/js/Pages/Configuracion/Index.vue:293` — input con `required`.
  - `app/Http/Controllers/ConfiguracionController.php:141` — backend declara `nombre_empresa` como `nullable`.
- **Descripción:** el atributo `required` ya existía en HEAD, pero no estaba dentro de un formulario, por lo que no se validaba. Con C-02 quedó dentro del formulario principal. Si `nombre_empresa` está vacío y el usuario envía desde POS, Sistema o Tienda, el navegador valida el campo oculto, bloquea el `submit` y registra `An invalid form control with name='nombre_empresa' is not focusable.` en consola. No hay burbuja de validación visible porque el campo está en una pestaña oculta.
- **Impacto:** el usuario no puede guardar la configuración desde POS, Sistema o Tienda y no recibe explicación visible. El backend acepta el campo vacío, así que la restricción es sólo frontend y no está alineada con el contrato.
- **Evidencia:** prueba aislada en Chromium con un formulario equivalente: `checkValidity=false`, `submittedAfterClick=false` y el mensaje de consola citado. La misma estructura de `v-show` sobre la pestaña General se verificó en el código.
- **Recomendación:** elegir una de estas dos opciones:
  1. Si el nombre debe ser obligatorio, agregar una guardia en `guardarConfiguracion` que, si `form.nombre_empresa` está vacío, cambie a `tabActiva = 'general'`, espere el render con `nextTick` y ejecute `reportValidity()` o muestre el error de campo.
  2. Si el nombre no es obligatorio, quitar `required` y alinear el frontend con el backend `nullable`.
  En ambos casos, agregar a la prueba 5.4 un escenario con `nombre_empresa` vacío y envío desde POS, Sistema o Tienda.
- **Referencia:** plan §2.2 y §5.4.

### H-02 — `esUrlSegura` acepta relativas sin `/`

- **Severidad:** Baja (desviación de especificación, sin impacto de XSS).
- **Archivo y ubicación:** `resources/js/Pages/Productos/Componentes/ModalProducto.vue:335-346`, especialmente `:341-344`.
- **Descripción:** el plan §3.1.3 pide aceptar sólo `http://`, `https://` o rutas relativas que empiecen con `/`. La implementación acepta cualquier relativa del mismo origen que no empiece con `//` ni contenga backslashes; por ejemplo, `storage/a.png`, `../a.png` o `producto.png`.
- **Impacto:** no permite ejecutar JavaScript; el navegador resuelve la relativa contra el origen actual y la usa como imagen. El impacto real es una posible solicitud a una ruta no deseada y una diferencia con el plan aprobado.
- **Evidencia:** matriz Node con la misma lógica:
  - Rechaza: vacío, `javascript:`, `data:`, `//evil.com/x`, backslashes.
  - Acepta: `https://cdn.test/a.png`, `http://cdn.test/a.png`, `/storage/a.png`.
  - Acepta además: `storage/a.png`, `../a.png`, `producto.png`.
- **Recomendación:** si se mantiene la letra del plan, para las relativas exigir `valor.startsWith('/')` y dejar `http:`/`https:` para las absolutas. Si se decide aceptar relativas sin `/`, documentarlo en el plan o en el informe y agregar el caso a la prueba 5.1.
- **Referencia:** plan §3.1.3.

### H-03 — C-03 sin guarda de `success`/`url`

- **Severidad:** Baja.
- **Archivo y ubicación:** `resources/js/Pages/Cliente/Panel.vue:116` y `:130-134`.
- **Descripción:** el código anterior verificaba `if (res.data.success)` antes de actualizar la card. La implementación nueva asigna `pedido.comprobante_transferencia_url = res.data.url` sin validar `success` ni la existencia de `url`. El endpoint actual siempre responde `{ success: true, url }` en 2xx, por lo que hoy no hay fallo funcional.
- **Impacto:** si el contrato cambia y el backend responde 200 con `success: false` o sin `url`, la UI podría mostrar un éxito falso y dejar la card sin enlace. Es una regresión de robustez, no un bug actual.
- **Evidencia:** diff de C-03 y `app/Http/Controllers/PedidoWebController.php:314-317`.
- **Recomendación:** restaurar una guarda equivalente a `if (res.data?.success && res.data?.url)`; si no se cumple, mostrar la advertencia de actualización y ejecutar el fallback de recarga.
- **Referencia:** plan §2.3.

### H-04 — Fallo asíncrono de `router.reload` sin manejo

- **Severidad:** Baja.
- **Archivo y ubicación:** `resources/js/Pages/Cliente/Panel.vue:130-139`.
- **Descripción:** `router.reload({ only: ['pedidos'] })` es asíncrono y no lanza una excepción capturable por el `try/catch` que lo rodea. El `catch` sólo cubre errores síncronos de `props.pedidos.find` o de la asignación.
- **Impacto:** si la recarga parcial falla, la subida ya fue exitosa y el toast de éxito sigue siendo correcto, pero la card puede quedar sin actualizar y no se muestra la advertencia. El usuario puede creer que el comprobante no se ve por un problema de la subida.
- **Evidencia:** código de C-03 y comportamiento de `router.reload` de Inertia (no devuelve una promesa que el `try/catch` pueda capturar).
- **Recomendación:** pasar `onError`/`onFinish` a `router.reload` o encapsular la recarga para mostrar la advertencia "El comprobante se subió, pero actualizá la página para verlo." cuando falle.
- **Referencia:** plan §2.3.

### H-05 — `Swal.isVisible()` incluye toasts

- **Severidad:** Baja.
- **Archivo y ubicación:** `resources/js/Pages/CajaDiaria/Index.vue:708`.
- **Descripción:** `cerrarModales` retorna si `Swal.isVisible()`. SweetAlert2 devuelve `true` también para un toast, no sólo para un modal de confirmación.
- **Impacto:** mientras hay un toast visible (por ejemplo, 3 segundos), Escape no cierra el modal que esté abierto. Es una molestia menor, no un problema de datos.
- **Evidencia:** prueba aislada con SweetAlert2: `visible=true` y `popupClass="swal2-popup swal2-toast swal2-icon-success swal2-show"` para un toast.
- **Recomendación:** bloquear Escape sólo cuando haya un modal de confirmación, por ejemplo verificando que el popup no tenga la clase `swal2-toast`, o manteniendo un flag propio para las confirmaciones de descarte.
- **Referencia:** plan §5.7.

### H-06 — Botones `Editar`/`✕` sin `type="button"`

- **Severidad:** Baja.
- **Archivo y ubicación:** `resources/js/Pages/Configuracion/Index.vue:557-558`.
- **Descripción:** los botones de editar y eliminar método de pago no declaran `type="button"`. Hoy están fuera de cualquier `<form>`, por lo que no producen envío accidental.
- **Impacto:** ninguno en el estado actual. Es un riesgo de robustez si en el futuro se mueven dentro del formulario de `metodos_pago_pos` o del formulario principal.
- **Evidencia:** estructura de los tres formularios; los botones están en la lista, antes del `<form>` de `metodos_pago_pos` (`565-617`).
- **Recomendación:** agregar `type="button"` a ambos botones, en línea con la política del plan para botones que no deben enviar formularios.
- **Referencia:** plan §2.2 (criterio de botones de navegación/acción).

---

## 6. Verificaciones ejecutadas

| Verificación | Método | Resultado |
|---|---|---|
| Build de producción | `npm run build` | **OK**, exit 0, 951 módulos, 4.53 s, sin warnings |
| Espacios/errores de diff | `git diff --check` en los 10 archivos y en todo el working tree | **OK**, exit 0 |
| C-01 estático | Búsqueda de `${}` dentro de bloques `html`, `innerHTML`, `onerror` inline y `javascript:` | **OK**: 8 bloques, 0 con interpolación; 0 `innerHTML` con datos; 0 `onerror` inline; 0 `javascript:` |
| C-01 aislado | Smoke test en Chromium con `textContent` y payloads HTML | **OK**: `xss=0`, `imgCount=0`, payloads escapados |
| C-02 estructura | Conteo de `<form>` y anidamiento; mapa de campos y mensajes | **OK**: 3 formularios, sin anidamiento; 27 campos y 27 mensajes; 7 botones de navegación con `type="button"` |
| C-02 validación oculta | Prueba aislada en Chromium con `required` dentro de `display:none` | **Falla**: el submit se bloquea y la consola registra `not focusable` (H-01) |
| C-03 estático | Lectura de `Panel.vue` y del endpoint de comprobante | **OK**: `props.pedidos`, subida/actualización separadas, `finally`, fallback y endpoint `{ success: true, url }` |
| A-01 estático | Conteo de `ri-`, símbolos/usos SVG, roles, `aria-label`, `@click.self` | **OK**: 0 `ri-`; 24 símbolos/22 usos sin faltantes; 5 diálogos y 5 cierres correctos; 3 `@click.self` sólo en lectura |
| A-01 toast | Prueba aislada con SweetAlert2 | **Observación**: `Swal.isVisible()` es `true` para un toast (H-05) |
| URL de imagen | Matriz Node de `esUrlSegura` | **Parcial**: rechaza esquemas peligrosos; acepta relativas sin `/` (H-02) |
| Cambios de código | `git status` antes/después de la revisión | **OK**: no se modificó código de aplicación; sólo se creó este informe |

Nota: el BUILD informó 954 módulos y esta revisión reprodujo 951. La diferencia no es funcional y no afecta el resultado del build; el working tree contiene cambios ajenos que pueden alterar el grafo de módulos.

---

## 7. Pruebas manuales 5.1–5.7

**Estado general: no ejecutadas contra la aplicación real.** No se levantó la app, no se autenticó un usuario y no se usaron datos de prueba. Las verificaciones aisladas de la sección 6 son evidencia parcial, pero **no** reemplazan estas pruebas y no se marcan como aprobadas por inferencia.

| Prueba | Estado | Requisito para ejecutarla | Evidencia esperada |
|---|---|---|---|
| **5.1** Nombres con HTML y atributos de evento | **Pendiente** (smoke aislado parcial) | App levantada, datos de prueba con `<b>Prueba</b>`, `<img src=x onerror=...>` y `"Comillas" & <script>` | Texto literal, `window.__xss` indefinido, sin errores de consola, placeholder para `javascript:` en ModalProducto |
| **5.2** Guardado de Configuración con valores válidos | **Pendiente** | App, usuario autenticado, acceso a Configuración | Un solo POST, toast de éxito, valores persistidos, sin errores de consola |
| **5.3** Rechazo por validación del backend | **Pendiente** | Pestaña Tienda Online y Pagos; CBU con letras o `mora_dias_gracia=366` forzado | 422, error bajo el campo, cambio a la pestaña con error, toast de error, sin toast de éxito |
| **5.4** Navegación entre pestañas sin envíos accidentales | **Pendiente** (estructura estática revisada) | Las 6 pestañas y los 3 formularios | Cambiar de pestaña no dispara POST; Enter guarda el formulario correcto; footer oculto en `metodos_pago_pos` y `storefront`; probar también con `nombre_empresa` vacío (H-01) |
| **5.5** Subida exitosa y fallida de comprobantes | **Pendiente** | Pedido de prueba con transferencia pendiente; archivo válido y archivo inválido | Un solo toast por caso; card actualizada sin recargar; botón de subida oculto tras éxito; reintento posible tras fallo |
| **5.6** Apertura y cierre de todos los modales de Caja | **Pendiente** | Caja con sesión abierta y datos de movimientos | X, footer, Escape y clic fuera funcionan según el tipo de modal; confirmación al descartar cambios; foco vuelve al disparador |
| **5.7** Navegación con teclado y botones de cierre | **Pendiente** (búsqueda estática de `ri-` hecha) | App, teclado, lector de pantalla y zoom 100% | Cierres alcanzables, anillo de foco visible, nombre accesible correcto, iconos visibles en Header/ElegirSucursal, sin `ri-` |

---

## 8. Regresiones, riesgos y elementos fuera de alcance

### 8.1 Sin regresiones detectadas en lógica crítica

- No se modificaron cálculos de caja, arqueo, stock, precios, fiados ni permisos.
- No se modificaron backend, rutas, API, migraciones, base de datos ni dependencias.
- No se modificó `resources/js/Components/Modal.vue`.
- El build de producción sigue funcionando.
- No se observaron errores nuevos en el build; la consola en runtime no se pudo verificar sin la app.

### 8.2 Riesgos menores a verificar en QA

- **H-01:** bloqueo silencioso de guardado desde POS, Sistema o Tienda con `nombre_empresa` vacío.
- **H-02:** rutas relativas sin `/` aceptadas como imagen.
- **H-03/H-04:** robustez de C-03 ante cambios de contrato o fallo de la recarga parcial.
- **H-05:** Escape bloqueado mientras hay un toast visible.
- **H-06:** botones de acción sin `type="button"` en `metodos_pago_pos`.

### 8.3 Fuera de alcance / preexistente

- `Header.vue` conserva botones icon-only preexistentes sin `aria-label` (hamburguesa y campana). No fueron introducidos por A-01.
- `CajaDiaria/Index.vue:673` conserva una interpolación preexistente en un `html` de `registrarGastoManual` (montos formateados por el sistema). No fue modificada por C-01.
- `Consumidores/Index.vue` e `Ingresos/Index.vue` conservan `v-html` preexistentes para paginación. No fueron modificados por C-01.
- El focus trap general y la accesibilidad completa quedan diferidos a Etapa 3, como indica el plan.
- No se evaluaron los cambios ajenos del working tree listados en §3.3.

---

## 9. Confirmación de no modificación

Durante esta revisión:

- No se modificó código de aplicación, backend, rutas, API, migraciones, base de datos, dependencias, `AGENTS.md`, skills ni cambios preexistentes.
- No se implementaron correcciones de los hallazgos.
- Sólo se creó este informe en `docs/informes/REVIEW-ETAPA-1-FRONTEND.md`.
- `npm run build` generó assets en `public/build` (ignorado por git) y no cambió archivos versionados.
- `git status` no muestra cambios nuevos atribuibles a la revisión.

---

## 10. Veredicto

**Aprobado con observaciones.**

Fundamento:

- C-01, C-03 y A-01 cumplen el plan en lo sustancial y no presentan regresiones de seguridad ni de datos.
- C-02 cumple la estructura, el mapeo de errores y la separación de formularios, pero tiene un hallazgo medio (H-01) que debe resolverse o aceptarse formalmente antes de cerrar la etapa.
- Las observaciones H-02 a H-06 son menores y no bloquean por sí solas, pero conviene atenderlas para dejar el código consistente con el plan y más robusto.
- Las pruebas manuales 5.1–5.7 siguen pendientes. **Este veredicto no es aprobación final de la etapa**; es aprobación de la revisión de código para pasar a QA con observaciones.

Condiciones para el cierre de Etapa 1:

1. Resolver H-01 o registrar una aceptación formal con seguimiento.
2. Ejecutar 5.1–5.7 en un entorno con app, autenticación y datos de prueba.
3. Si alguna prueba manual falla, reabrir la revisión y actualizar este informe.
4. Opcionalmente, atender H-02 a H-06 antes o durante la próxima iteración.

---

**Fin del informe.**
