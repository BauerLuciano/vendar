# Auditoría de Frontend — VendAR

**Fecha:** 17/11/2026  
**Alcance:** frontend completo (`resources/js`), layouts, componentes compartidos, páginas de backoffice, tienda pública y administración global.  
**Modo:** auditoría de solo lectura. No se modificó código, base de datos, dependencias, reglas de negocio ni configuración.  
**Estado:** informe de diagnóstico. Toda implementación queda para etapas posteriores.

---

## 1. Resumen actual

VendAR tiene una base visual y funcional mejor de lo que sugiere la cantidad de deuda técnica acumulada. Las pantallas críticas —POS, Caja Diaria, MiPlan y tienda pública— tienen decisiones de producto claras: jerarquía de saldos, atajos de teclado, validaciones de stock, estados de pago y una tienda con tokens de color y modo claro/oscuro. No hace falta un rediseño general.

El problema principal no es la estética. Es la **consistencia operativa**:

- El dinero se muestra de cuatro formas distintas, incluyendo `$1234.50`, `$ $ 1.234,56` y montos sin decimales.
- Las fechas “Hoy” usan UTC en Pedidos y Reportes; después de las 21:00 en Argentina pueden apuntar al día siguiente.
- La configuración general puede fallar sin mostrar ningún error.
- La subida de comprobantes de la tienda muestra error después de haber subido el archivo.
- Varias confirmaciones construyen HTML con nombres de productos/clientes sin escapar.
- La accesibilidad de teclado y de formularios es débil, justo en un sistema que se usa de pie, con apuro y muchas veces con lector de pantalla o teclado.
- Hay 46 iconos Remix que no existen en el proyecto; en Caja Diaria eso deja botones de cierre invisibles.

La tienda pública ya resolvió parte de lo que el backoffice necesita: tokens CSS, componentes base, precios formateados y tema claro/oscuro. La recomendación es **reutilizar y ordenar esa base**, no crear un design system nuevo.

**Veredicto corto:** aprobado con observaciones críticas. No es un frontend “terminado”, pero tampoco necesita una reescritura. Necesita una etapa de correcciones de confianza antes de seguir agregando funcionalidad.

---

## 2. Fortalezas actuales

### 2.1 POS: está pensado para operar, no sólo para verse bien

- Atajos de pago y cobro con `F1`–`F9`, con ayuda visible en el header (`resources/js/Pages/Pos/Terminal.vue:908-990`).
- Autofoco del buscador y retorno de foco después de acciones (`Pos/Terminal.vue:581,703,857,880,898,905,1071`).
- Validación de stock y bloqueo de venta sin stock, con respeto de la configuración de stock negativo (`Pos/Terminal.vue:649,685-686,710-711,767-768`).
- Confirmación de pago y manejo de cuenta corriente antes de cerrar la venta.
- La card de producto muestra stock, precio de liquidación y favoritos en el mismo lugar.

Esto es valioso: el POS no es un CRUD con botones grandes; tiene un modelo mental de caja.

### 2.2 Caja Diaria: buena jerarquía de información

- Saldos, sesiones, arqueo y cierre están separados por bloques visuales claros.
- Las tablas principales sí tienen scroll horizontal (`CajaDiaria/Index.vue:715,852,950`).
- Los modales de detalle tienen contenedores con scroll vertical (`:1035,1293`).
- Hay validaciones de arqueo y confirmaciones antes de cerrar.

El problema de Caja no es la estructura; es la ejecución visual de los iconos y algunos detalles de formato.

### 2.3 MiPlan: buen manejo de estados de pago

- Distingue pago en vuelo, pendiente, aprobado y timeout.
- Tiene polling para no depender de recargar la página.
- El banner de pago pendiente conserva una salida visible para reanudar o liberar el checkout.
- Reemplazó los `alert()` nativos por mensajes integrados.

Es una de las pantallas más maduras del sistema en manejo de estados asíncronos.

### 2.4 Tienda pública: la mejor base visual del proyecto

- Tokens CSS para fondo, tarjetas, textos, inputs, navbar, categorías y estados (`StoreLayout.vue:42-80`).
- Tema claro/oscuro persistido en `localStorage`.
- Componentes reutilizables: `BaseButton`, `BaseCard`, `BaseBadge`, `PriceDisplay`.
- La tienda ya tiene una identidad más consistente que el backoffice.

No hay que inventar un sistema nuevo: hay que **extraer** lo que la tienda ya resolvió y aplicarlo con criterio al backoffice.

### 2.5 Patrones correctos que ya existen en el código

- **HTML seguro en SweetAlert:** `AdminGlobal/Facturacion.vue:110-118` usa HTML estático y completa valores con `textContent`/`value`, sin interpolar datos.
- **Fecha local segura:** el mismo archivo usa `props.hoy || new Date().toLocaleDateString('en-CA')` y comenta por qué `toISOString()` falla.
- **Modal base accesible:** `Components/Modal.vue:48-58,78` usa `<dialog>`, Escape y foco nativo.
- **Flip de menús en últimas filas:** `Consumidores/Index.vue:706` y `Roles/Index.vue` ya lo implementan.
- **Paginación compartida:** existe `Components/Pagination.vue`, aunque se usa poco.
- **Empty states:** existe `Components/EmptyState.vue`.

Estos patrones son la mejor guía para corregir sin inventar arquitectura.

### 2.6 Otras fortalezas puntuales

- `Pedidos/Index.vue` usa tarjetas en vez de una tabla ancha; es una buena decisión responsive para un flujo con estados y acciones.
- `Reportes/Index.vue` tiene una estructura clara de filtros, pestañas y resumen; el problema es la fecha, no la organización.
- La tienda tiene `PriceDisplay` y componentes base que ya separan presentación de lógica.
- El backoffice mantiene una paleta reconocible (sky/slate) y un lenguaje de tarjetas redondeadas; la identidad existe, aunque esté fragmentada.

---

## 3. Problemas detectados

### 3.1 Panorama técnico medido

| Métrica | Valor | Lectura |
|---|---:|---|
| Archivos Vue/JS en `resources/js` | 139 | Superficie grande para mantener sin lint ni tests |
| Usos de `text-[8px]`, `text-[9px]`, `text-[10px]` | 637 | 25 de 8px, 84 de 9px, 528 de 10px |
| `<label>` | 299 | Sólo 8 usan `for` |
| `aria-label` | 3 | Muy bajo para la cantidad de iconos y controles |
| `aria-expanded` / `aria-controls` / `aria-current` / roles de diálogo o menú | 4 | Prácticamente no hay semántica de componentes interactivos |
| `outline-none` | 86 | Varios sin reemplazo visible de foco |
| `<button>` | 422 | Sólo 37 declaran `type="button"` |
| Overlays `fixed inset-0` | 74 | La mayoría son modales hechos a mano |
| Usos de `<Modal>` | 16 | El modal base existe, pero se usa poco |
| `form.errors` en páginas | 92 | Configuración no tiene ninguna referencia |
| `Swal.fire` con `html` | 53 | Varias interpolan datos de usuario sin escapar |
| Formateadores locales | 36 | Sólo 2 archivos importan `formatearMoneda` |
| Iconos `<i class="ri-*">` | 46 | `remixicon` no está en `package.json` ni en CSS |
| `v-html` | 13 | Mayoría íconos/labels internos; el riesgo principal está en Swal |

No se midió contraste con herramienta automática. Las observaciones de contraste y tamaño son evidencia de código y deben confirmarse visualmente en navegador antes de implementar.

### 3.2 Hallazgos críticos

#### C-01 — XSS almacenado en confirmaciones con HTML

**Tipo:** seguridad / técnico.  
**Módulos:** Fiados, Productos, Ingresos, POS, Transferencias.

El frontend interpola nombres de productos, clientes, sucursales y proveedores dentro de `Swal.fire({ html: ... })` sin escapar. SweetAlert2 inserta ese string como HTML. Si un nombre contiene `<img src=x onerror=...>` o un atributo de evento, se ejecuta en la sesión del usuario que abre la confirmación. No se verificó si el backend sanea esos campos; el frontend no lo hace.

Evidencia:

- `Consumidores/Index.vue:424-458` interpola `c.nombre`, `c.apellido`, `c.documento`, `c.email`, `c.telefono` y `form.nombre`.
- `Productos/Componentes/ModalProducto.vue:248-254` interpola `p.nombre`, `p.marca`, `p.categoria`, `p.codigo_barras`.
- `Ingresos/Index.vue:61-80` interpola `p.producto`.
- `Pos/Terminal.vue:658-668` interpola `producto.nombre` y `producto.stock_actual`.
- `Transferencias/Index.vue:18,50,82` interpola `producto.nombre`, `origen.nombre` y `destino.nombre`.

**Impacto:** ejecución de HTML/JS en la sesión de un usuario del comercio o de administración. Es un riesgo de XSS almacenado, no sólo un problema visual.

**Recomendación:** usar el patrón de `AdminGlobal/Facturacion.vue:110-118`: HTML estático y valores inyectados con `textContent`/`value` en `didOpen`. Si se necesita interpolar, escapar con un helper único y probar nombres con `<`, `>`, `"`, `'` y `&`.

**Criterio de aceptación:** un nombre con HTML se muestra literal, no se ejecuta; la confirmación sigue mostrando los datos correctos.

#### C-02 — Configuración general puede fallar sin feedback

**Tipo:** UX / técnico.  
**Módulo:** Configuración.  
**Usuario:** dueño/administrador.

El `<form>` de `Configuracion/Index.vue:825-830` sólo envuelve el botón “Guardar Ajustes”. Los campos de las pestañas General, POS y Sistema están fuera del formulario. El botón llama a `guardarConfiguracion`, así que guardar por clic funciona, pero:

- Enter no envía el formulario.
- Los `required` del HTML no se validan.
- No hay `form.errors` en todo el archivo.
- `guardarConfiguracion` no tiene `onError` (`:162-180`).

Si el backend rechaza un valor, Inertia llena `form.errors` pero la pantalla no muestra nada. El usuario ve el botón volver a estado normal y cree que guardó.

La pestaña Tienda sí tiene su propio `<form>` correcto (`:812-822`); el problema afecta a General/POS/Sistema.

**Impacto:** cambios de configuración que no se guardan, sin explicación. Es especialmente riesgoso en reglas de POS, impresión, stock negativo y datos del comercio.

**Recomendación:** envolver el contenido de las pestañas afectadas en el `<form>` (o un formulario por pestaña), marcar los botones de tab como `type="button"`, mostrar errores por campo y agregar un toast de error en `onError`.

**Criterio de aceptación:** Enter guarda; un campo requerido vacío muestra error; un rechazo del backend muestra el mensaje; la pestaña Tienda sigue funcionando igual.

#### C-03 — Subida de comprobante en la tienda muestra error después de subir

**Tipo:** UX / bug funcional.  
**Módulo:** Panel del cliente / Pedidos web.  
**Usuario:** cliente de la tienda.

En `Cliente/Panel.vue:106-123`, después de una subida exitosa se ejecuta:

```js
const pedido = pedidos.find(p => p.id === pedidoId);
```

`pedidos` no existe en el componente; debería ser `props.pedidos`. El `ReferenceError` cae en el `catch` que envuelve todo el `try`, por lo que el usuario ve primero el toast de éxito y luego el toast de error “No se pudo subir el comprobante”. Además, la lista local no se actualiza y el comprobante no aparece.

**Impacto:** el cliente cree que el pago no se registró y puede volver a subir el archivo. Es un flujo de dinero real y confianza.

**Recomendación:** usar `props.pedidos` y separar el error de subida del error de actualización local; si la actualización falla, recargar con Inertia o hacer un `router.reload({ only: [...] })`.

**Criterio de aceptación:** una subida exitosa muestra un solo toast de éxito y el comprobante queda visible; una subida fallida muestra un solo error.

### 3.3 Hallazgos altos

#### A-01 — Iconos Remix inexistentes dejan botones de cierre invisibles en Caja

**Tipo:** visual / técnico / accesibilidad.  
**Módulos:** Caja Diaria, Header, Elegir Sucursal.

Hay 46 usos de `<i class="ri-*">` en tres archivos, pero `remixicon` no está en `package.json`, no se carga en `app.blade.php` y `resources/css/app.css` sólo tiene las directivas de Tailwind. Los iconos renderizan vacío.

En Caja Diaria los botones de cierre son sólo icono:

- `CajaDiaria/Index.vue:912` — cerrar visor global.
- `:1013` — cerrar detalle de caja global.
- `:1089` — cerrar arqueo/cierre.
- `:1193` — cerrar movimiento manual.
- `:1262` — cerrar detalle de historial.

Esos overlays tampoco tienen `@click.self` ni manejo de Escape. El usuario ve un botón vacío, sin texto ni `aria-label`, y no tiene una salida obvia.

En Header los iconos son decorativos y el texto de sucursal sigue visible (`Header.vue:18-22`), pero la señal visual se pierde. En `Auth/ElegirSucursal.vue:20,33,39` también son decorativos.

**Recomendación:** reemplazar por SVG inline (ya es el patrón dominante) o incorporar una librería de iconos de forma explícita. Agregar `aria-label` a los botones icon-only y migrar los modales de Caja al `Modal` base o agregar Escape/click fuera.

**Criterio de aceptación:** todos los cierres son visibles y accesibles por teclado; no queda ningún `<i class="ri-*">` sin CSS.

#### A-02 — POS no permite seleccionar productos con teclado

**Tipo:** accesibilidad / UX.  
**Módulo:** POS.  
**Usuario:** cajero.

Las cards de producto son `div` con `@click`, sin `tabindex`, `role="button"` ni `@keydown.enter`/`space` (`Pos/Terminal.vue:1173-1177`; la card está repetida en `:1221-1249` y `:1275-1309`). El POS tiene atajos F1–F9 y autofoco de búsqueda, pero un cajero que trabaja con teclado no puede agregar un producto sin mouse.

Además, el botón de favorito es de 28×28 px (`:1185-1187`), por debajo del mínimo táctil recomendado de 44×44 px.

**Impacto:** rompe el flujo “teclado primero” que el propio POS promete. Es una barrera concreta para usuarios con movilidad reducida o para operación rápida sin mouse.

**Recomendación:** convertir la card en `<button>` o agregar `role="button"`, `tabindex="0"` y manejo de Enter/Espacio; mantener el layout actual. Aumentar el área táctil de favoritos sin agrandar el icono.

**Criterio de aceptación:** se puede buscar, seleccionar, agregar y cobrar un producto usando sólo teclado; el foco es visible.

#### A-03 — Formularios sin etiquetas asociadas

**Tipo:** accesibilidad.  
**Módulos:** transversal.

Hay 299 `<label>` y sólo 8 con `for`. El patrón dominante es un `<label>` hermano del input, sin `id` ni asociación. Ejemplos:

- `Configuracion/Index.vue:800-801` — label “Descripción para Google” + input sin `id`.
- `CajaDiaria/Index.vue:918-919` — label “Sucursal” + select sin `id`.
- `Consumidores/Index.vue:629` — selects de filtros sin label asociada.

A esto se suma que sólo hay 3 `aria-label` en todo `resources/js`, y 4 atributos de estado/rol de componentes interactivos. Los botones icon-only del header y de los menús no tienen nombre accesible confiable.

**Impacto:** lectores de pantalla anuncian “cuadro de edición” o “botón” sin contexto; control por voz no puede apuntar al campo; formularios largos se vuelven difíciles de completar.

**Recomendación:** asociar `for`/`id` o envolver el input dentro del label; agregar `aria-label` a botones icon-only; agregar `aria-expanded` a menús y secciones colapsables. No hace falta reescribir todos los formularios de una vez: priorizar POS, Caja, Configuración y Fiados.

**Criterio de aceptación:** cada campo de los módulos priorizados tiene nombre accesible; los botones icon-only se anuncian con una acción.

#### A-04 — Modales hechos a mano sin foco, Escape ni semántica

**Tipo:** accesibilidad / UX / técnico.  
**Módulos:** transversal.

Hay 74 apariciones de `fixed inset-0` frente a 16 usos de `<Modal>`. No todos los `fixed inset-0` son modales, pero la proporción confirma que la mayoría se implementa a mano. Ejemplos:

- `Consumidores/Index.vue:763,817,1041`.
- `Productos/Componentes/ModalProducto.vue:400`.
- `Usuarios/Componentes/ModalUsuario.vue:270`.
- `Sucursales/Componentes/ModalSucursal.vue:155`.
- `Roles/Componentes/ModalRol.vue:55`, `DetalleRol.vue:7`, `ModalPermiso.vue:47`, `DetallePermiso.vue:7`.
- `CajaDiaria/Index.vue:899,1007,1084,1189,1255`.

La mayoría no tiene `role="dialog"`, `aria-modal`, focus trap, Escape, restauración de foco ni bloqueo de scroll del body. El `Modal` base ya resuelve parte de esto con `<dialog>` y Escape (`Components/Modal.vue:48-58,78`).

**Impacto:** usuarios de teclado pueden tabular detrás del modal y modificar la pantalla de fondo; usuarios de lector de pantalla no saben que se abrió un diálogo; en móvil el scroll de fondo compite con el del modal.

**Recomendación:** migrar los modales de mayor uso al `Modal` base; para los que necesiten layout propio, extraer un wrapper accesible común. Priorizar Caja, Fiados, Productos y Usuarios.

**Criterio de aceptación:** Escape cierra, el foco queda atrapado y vuelve al disparador, el fondo no scrollea y el diálogo tiene nombre accesible.

#### A-05 — Fechas “Hoy” con UTC corrido

**Tipo:** UX / técnico.  
**Módulos:** Pedidos, Reportes.

`toISOString().slice(0, 10)` devuelve la fecha en UTC. En Argentina (UTC−3), después de las 21:00 devuelve el día siguiente. Eso hace que “Hoy” apunte a mañana y que los filtros muestren cero o datos incompletos.

Evidencia:

- `Pedidos/Index.vue:84` — `const hoy = new Date().toISOString().slice(0, 10)`.
- `Pedidos/Index.vue:87` — “Facturado hoy” filtra `created_at` contra esa fecha.
- `Reportes/Index.vue:76-79` — presets “Hoy”, “Ayer”, “Este Mes” y “Mes Pasado”.

El patrón correcto ya está en `AdminGlobal/Facturacion.vue:110-118`:

```js
const hoy = props.hoy || new Date().toLocaleDateString('en-CA');
```

**Impacto:** el dueño o encargado puede ver métricas diarias en cero durante la noche, justo cuando hace el cierre.

**Recomendación:** centralizar `fechaLocalISO()` y, cuando el backend pueda enviar la fecha del comercio, priorizar ese valor. Probar explícitamente entre 21:00 y 23:59.

**Criterio de aceptación:** “Hoy” coincide con la fecha local del comercio a cualquier hora; “Ayer” y “Este Mes” no se corren.

#### A-06 — Formato de dinero inconsistente en toda la app

**Tipo:** visual / UX / técnico.  
**Módulos:** POS, Ventas, Caja, Productos, Ingresos, Dashboard, tienda.

`Utils/formatters.js` ya tiene `formatearMoneda`, que devuelve `$ 1.234,56`, y `formatearDinero`, que usa `Intl.NumberFormat` con ARS. Pero la app no los usa de forma consistente:

- POS muestra totales con `toFixed(2)`: `$1234.50`, sin separador de miles (`Pos/Terminal.vue:1321,1517,1529,1696,1702,1706,1712`).
- Ventas muestra `${{ v.total }}` sin formatear (`Ventas/Index.vue:225`).
- Caja agrega un `$` delante de `formatearMoneda`, que ya devuelve `$ `, produciendo `$ $ 1.234,56` (`CajaDiaria/Index.vue:908`).
- Productos usa `toLocaleString` sin decimales fijos (`Productos/Index.vue:213`).
- Ingresos muestra `${{ i.total_costo }}` y `${{ det.costo_unitario }}` crudos (`Ingresos/Index.vue:205`, `DetalleIngreso.vue:83,95`).
- Hay 36 formateadores locales; sólo 2 archivos importan `formatearMoneda`.

**Impacto:** el mismo monto se lee distinto según la pantalla. En POS y caja, donde se maneja dinero real, eso erosiona confianza y puede inducir errores de lectura.

**Recomendación:** una única función de presentación (`formatearMoneda`) y reemplazar sólo la capa visual; no tocar cálculos ni valores enviados al backend. Eliminar el `$` manual de Caja y los `toFixed(2)` de plantilla.

**Criterio de aceptación:** todos los montos visibles usan el mismo formato `$ 1.234,56`; no queda ningún `$ $`; POS y Ventas coinciden.

#### A-07 — Tablas anchas sin scroll horizontal en mobile

**Tipo:** responsive.  
**Módulos:** Ventas, Fiados, Proveedores, Usuarios, Sucursales, Categorías, Órdenes de Compra.

Varias tablas usan `overflow-visible` para que los menús de acciones no queden recortados, pero eso elimina el scroll horizontal y hace que la página desborde en mobile.

Evidencia:

- `Ventas/Index.vue:185` — `overflow-visible` sin wrapper de scroll.
- `Consumidores/Index.vue:651-653` — `overflow-visible` + `relative`.
- `Proveedores/Index.vue:152-154` — `overflow-visible`.
- `Usuarios/Index.vue:159-161` — `overflow-visible`.
- `Sucursales/Index.vue:155-157` — `overflow-visible`.
- `Categorias/Index.vue:150-151` — `overflow-visible`.
- `OrdenesCompra/Index.vue:265-267` — `overflow-visible`.
- `Productos/Index.vue:119-120` — sí tiene `overflow-x-auto`, pero el menú absoluto de `:237-238` queda recortado por ese contenedor.
- `Roles/Index.vue:162-164` — el comentario “Sin overflow-hidden para que el dropdown no quede cortado” muestra que el problema ya se conoce y se resolvió quitando el scroll.

**Impacto:** en 360–390 px el usuario no puede llegar a las acciones sin desplazar toda la página; en Productos el menú se recorta en las últimas filas o con pocos resultados.

**Recomendación:** un wrapper compartido de tabla con `overflow-x-auto` y `min-w`, y menús en un popover/teleport con flip vertical y horizontal. No usar `overflow-visible` como solución global.

**Criterio de aceptación:** la tabla scrollea dentro de su tarjeta; el menú de la última fila se ve completo; la página no scrollea horizontalmente.

### 3.4 Hallazgos medios

#### M-01 — Dashboard: banners antes de los KPIs y padding duplicado

**Tipo:** UX / visual.  
**Módulo:** Dashboard.  
**Usuario:** dueño/encargado.

El Dashboard muestra, en orden, banner de suscripción (`Dashboard.vue:193-208`), onboarding (`:210-254`) y órdenes pendientes (`:256-273`) antes de la grilla de KPIs (`:275`). En un comercio con onboarding incompleto y suscripción por vencer, el dueño ve tres bloques de alerta antes de “ventas de hoy” o “estado de caja”.

Además hay padding anidado: `AuthenticatedLayout.vue:42` aplica `p-4 lg:p-8` y `Dashboard.vue:178` aplica `py-6 px-4 sm:px-6 lg:px-8`. En pantallas de 1366 px se pierden hasta 128 px de ancho útil.

**Impacto:** la primera pantalla operativa se llena de mensajes y deja los números principales abajo.

**Recomendación:** poner los KPIs primero o convertir las alertas en una franja compacta; dejar una sola capa de padding horizontal. No es un rediseño: es ordenar jerarquía.

**Criterio de aceptación:** en 1366×768 se ven al menos los KPIs principales sin scroll; las alertas siguen accesibles.

#### M-02 — Tipografía operativa demasiado pequeña

**Tipo:** visual / accesibilidad.  
**Módulos:** transversal.

Hay 637 usos de 8/9/10 px: 25 de 8 px, 84 de 9 px y 528 de 10 px. Los 10 px en mayúsculas y tracking ancho son un recurso de estilo, pero los 8/9 px aparecen en datos operativos:

- `Pedidos/Index.vue:272,284` — labels “ORDEN” y “PAGO” a 8 px.
- `Pos/Terminal.vue:1552` — atajos de pago a 8 px.
- `CajaDiaria/Index.vue` — múltiples micro-labels de saldos y estados.

**Impacto:** para usuarios de kiosco, muchas veces mayores o mirando una pantalla a distancia, 8 px es ilegible. Aumenta errores de lectura y fatiga.

**Recomendación:** reservar 10 px para ayudas no esenciales; usar 11–12 px como mínimo en labels operativos. No cambiar toda la escala de una vez: empezar por POS, Caja, Pedidos y Fiados.

**Criterio de aceptación:** los labels de estado/pago se leen a 100% de zoom en una notebook de 14” y en un monitor de 21”.

#### M-03 — Productos carga todo y pagina en el cliente

**Tipo:** UX / rendimiento.  
**Módulo:** Productos/Stock.

`ProductoController.php:43-47` carga todos los productos con `categoria`, `marca`, `sucursales` y `proveedor`. `Productos/Index.vue:590-632` filtra y pagina en el cliente, con 7 ítems por página.

**Impacto:** en catálogos grandes, el payload inicial y el DOM crecen; los filtros sólo operan sobre lo ya cargado; 7 ítems por página obliga a muchas páginas. Si los 7 ítems son un requisito comercial, mantenerlos, pero la paginación debería resolverse en servidor.

**Recomendación:** confirmar si 7 sigue siendo el requisito. Si no, paginación server-side con filtros; si sí, mantener 7 pero mover filtrado/paginación al backend o virtualizar.

**Criterio de aceptación:** con 2.000 productos, la página inicial no tarda más que hoy y los filtros encuentran productos fuera de la primera página.

#### M-04 — MiPlan bloquea los botones de planes después de un timeout

**Tipo:** UX / bug.  
**Módulo:** Suscripción.  
**Usuario:** dueño.

`MiPlan.vue:211-216` dice en el comentario que `timeout` no debe bloquear, pero el computed `hayPagoEnCurso` incluye `'timeout'`. Eso deshabilita los botones de planes. El banner de pago pendiente conserva “Reintentar pago” (`:737-773`), así que hay una salida, pero la elección de plan queda bloqueada sin necesidad.

**Impacto:** después de un timeout, el usuario no puede cambiar de plan aunque no haya una intención de pago viva.

**Recomendación:** quitar `'timeout'` de `hayPagoEnCurso` o separar “pago en vuelo” de “pago a reintentar”. Mantener el botón de reintento.

**Criterio de aceptación:** con estado `timeout`, los planes se pueden elegir y el banner sigue ofreciendo reintentar/liberar.

#### M-05 — Banner de onboarding siempre al 100%

**Tipo:** visual / UX.  
**Módulo:** Onboarding.

`Onboarding/Banner.vue:29` fija `width: '100%'` sin importar el paso. El Dashboard, en cambio, usa el porcentaje real (`Dashboard.vue:235-238`). La misma información se contradice.

**Impacto:** el usuario cree que terminó la configuración cuando no es así.

**Recomendación:** recibir el porcentaje real como prop o eliminar la barra del banner y dejar el progreso sólo en el Dashboard/Wizard.

**Criterio de aceptación:** el banner y el Dashboard muestran el mismo porcentaje.

#### M-06 — Pedidos muta props con `v-model`

**Tipo:** UX / técnico.  
**Módulo:** Pedidos.

`Pedidos/Index.vue:273,285` usan `v-model="pedido.estado_pedido"` y `v-model="pedido.estado_pago"` sobre objetos que vienen de `props.pedidos`. El `@change` dispara el PATCH, pero el select ya cambió visualmente. Si el backend rechaza o falla la red, la UI queda mostrando un estado que no se guardó.

Además, la anulación usa `confirm()` nativo (`:104`), distinto del resto de la app.

**Impacto:** el encargado puede creer que un pedido está pagado o entregado cuando el servidor no lo aceptó.

**Recomendación:** usar `:value` + `@change` con estado local, o un draft por pedido; aplicar el cambio sólo con la respuesta del servidor y revertir en error. Reemplazar `confirm()` por SweetAlert2 o el patrón de confirmación existente.

**Criterio de aceptación:** si el PATCH falla, el select vuelve al estado anterior y se muestra un error.

#### M-07 — Identidad visual fragmentada y layouts duplicados

**Tipo:** visual / técnico.  
**Módulos:** backoffice, AdminGlobal, tienda.

- Backoffice: header oscuro (`Header.vue:2`) y acentos sky/slate.
- AdminGlobal: header blanco y paleta `#00adef` (`GlobalAdminLayout.vue:145-164`), con navegación y logout duplicados (`GlobalAdminLayout.vue:45-60` vs `Sidebar.vue:135-150`).
- Tienda: tokens CSS y tema claro/oscuro (`StoreLayout.vue:42-80`).
- `tailwind.config.js:16-20` sólo extiende Figtree; no hay tokens de color, radio o tipografía.
- `resources/css/app.css:1-3` sólo tiene las tres directivas Tailwind.

**Impacto:** el producto se percibe como varias apps pegadas. La duplicación de navegación y logout aumenta el costo de cada cambio.

**Recomendación:** extraer tokens compartidos (colores, radios, tipografía, estados) y un layout base; reutilizar la navegación en AdminGlobal. No crear un design system nuevo: formalizar lo que ya existe.

**Criterio de aceptación:** backoffice y AdminGlobal comparten tokens y un solo patrón de navegación; el cambio de un color base no requiere tocar decenas de archivos.

#### M-08 — Leaflet se carga globalmente en todas las páginas

**Tipo:** rendimiento / técnico.  
**Módulos:** global.

`resources/views/app.blade.php:12-13` carga CSS y JS de Leaflet desde unpkg en el `<head>`, sin `defer`. Sólo lo usan `StoreMap.vue` y `ModalSucursal.vue`.

**Impacto:** todas las páginas del backoffice pagan una dependencia externa que no usan; el script bloquea el render.

**Recomendación:** importar Leaflet de forma lazy dentro de los componentes que lo usan, o cargarlo sólo en las páginas de tienda/sucursales.

**Criterio de aceptación:** el backoffice no descarga Leaflet; la tienda y el modal de sucursal siguen funcionando.

#### M-09 — Sin lint, formato ni tests de frontend

**Tipo:** técnico.  
**Módulos:** global.

`package.json:4-7` sólo tiene `dev` y `build`. No hay ESLint, Prettier ni tests de componentes. Eso explica que pasen cosas como `LandingPage.vue:1` (`cle` antes de `<script setup>`) o `Cliente/Panel.vue:115` (`pedidos` inexistente). Un lint con `no-undef` habría detectado el segundo.

**Impacto:** los errores se descubren en producción o en revisión manual; el costo de mantener 139 archivos sin red de seguridad crece.

**Recomendación:** agregar ESLint con reglas de Vue y `no-undef`, Prettier y un smoke test de frontend (aunque sea de los flujos críticos). No hace falta TypeScript.

**Criterio de aceptación:** `npm run lint` falla con un `pedidos` indefinido; `npm run build` sigue siendo el gate de producción.

### 3.5 Hallazgos bajos

#### B-01 — Texto residual en `LandingPage.vue`

`LandingPage.vue:1` empieza con `cle<script setup>`. El parser SFC lo ignora, pero es deuda técnica visible y evidencia de falta de lint/format. **Prioridad:** baja.

#### B-02 — Polling fijo cada 30 s en el header

`Header.vue:287` consulta `/api/sesiones-caja/actual` cada 30 s en todas las páginas autenticadas. Es útil para mostrar el turno activo, pero no en todas las pantallas ni con la pestaña oculta. **Prioridad:** baja. Recomendación: pausar con `visibilitychange` o limitar a Caja/POS.

#### B-03 — Paginación compartida subutilizada

Existe `Components/Pagination.vue`, pero varias páginas implementan su propia paginación. Eso produce labels, tamaños y comportamientos distintos. **Prioridad:** baja/media. Recomendación: unificar cuando se toque cada módulo, no en una etapa aparte.

#### B-04 — Card de producto del POS triplicada

La misma card está repetida casi literalmente en `Pos/Terminal.vue:1173-1213`, `:1221-1249` y `:1275-1309`. Un cambio de presentación exige tocar tres bloques y puede dejar variantes inconsistentes. **Prioridad:** media técnica. Recomendación: extraer `ProductoCard.vue` con props y slots.

---

## 4. Mejoras justificadas

No toda mejora visual justifica una etapa. Estas son las que tienen impacto directo en dinero, confianza, velocidad operativa o accesibilidad.

| Mejora | Por qué | Criterio de aceptación |
|---|---|---|
| Confirmaciones seguras sin HTML interpolado | Evita XSS almacenado en sesiones de dueños/empleados | Nombres con HTML se muestran literales; no se ejecuta nada |
| Configuración con formulario completo y errores visibles | Evita cambios que no se guardan y el usuario no sabe | Enter guarda; error de backend visible; campos requeridos validan |
| Subida de comprobante sin falso error | El cliente confía en que su pago quedó registrado | Un solo toast; comprobante visible al instante |
| Un solo formato de dinero | Evita leer mal montos en POS, caja y ventas | Todos los montos usan `$ 1.234,56` |
| Fechas locales seguras | Evita métricas diarias en cero de noche | “Hoy” coincide con la fecha del comercio a las 23:59 |
| Accesibilidad de POS y formularios | Permite operar con teclado y lector de pantalla | Producto seleccionable con Enter; campos con nombre accesible |
| Modales con foco y Escape | Evita editar el fondo sin darse cuenta | Escape cierra; foco atrapado; vuelve al disparador |
| Tablas y menús responsive | Permite usar el sistema desde el celular del local | Tabla scrollea dentro de la tarjeta; menú de última fila visible |
| Iconos consistentes | Evita botones vacíos y refuerza la identidad | No queda ningún `ri-*` sin CSS; cierres visibles |
| Layout y tokens compartidos | Reduce duplicación y percepción de “varias apps” | Backoffice/AdminGlobal comparten tokens y navegación |

### Lo que no se recomienda

- No hacer un rediseño general de VendAR en esta instancia.
- No cambiar la arquitectura de navegación ni el modelo de datos.
- No introducir TypeScript sólo por esta auditoría.
- No reemplazar SweetAlert2 por otra librería; el problema es el uso de `html`, no la librería.
- No convertir todas las tablas en cards; el problema es el overflow y los menús.
- No tocar cálculos, precios, stock, caja ni reglas de negocio para “acomodar” la UI.

---

## 5. Etapas independientes

Las etapas están ordenadas por riesgo y valor. Cada una puede implementarse y revisarse sola.

### Etapa 1 — Correcciones críticas de confianza

**Objetivo:** eliminar los tres problemas que pueden hacer que el usuario crea que algo se guardó, se cobró o se subió cuando no fue así.

**Incluye:**

1. C-01: reemplazar HTML interpolado en Swal por HTML estático + `textContent`/`value`.
2. C-02: formulario completo en Configuración, errores visibles y `onError`.
3. C-03: corregir `props.pedidos` y el estado local del comprobante.
4. A-01: reemplazar los iconos Remix de Caja/Header o cargar la librería; agregar `aria-label` a cierres.
5. A-01: agregar Escape/click fuera a los modales de Caja mientras se migran al `Modal` base.

**Archivos principales:** `Consumidores/Index.vue`, `Productos/Componentes/ModalProducto.vue`, `Ingresos/Index.vue`, `Pos/Terminal.vue`, `Transferencias/Index.vue`, `Configuracion/Index.vue`, `Cliente/Panel.vue`, `CajaDiaria/Index.vue`, `Header.vue`.

**Riesgo:** bajo/medio. No cambia reglas de negocio ni cálculos. El mayor cuidado está en no romper el flujo de confirmación de Caja y en no alterar el formulario de Tienda.

**No incluye:** rediseño visual, cambios de tabla, accesibilidad completa.

### Etapa 2 — Dinero y fechas consistentes

**Objetivo:** que el mismo monto y la misma fecha se lean igual en toda la app.

**Incluye:**

1. A-06: centralizar `formatearMoneda` y eliminar `toFixed(2)` de plantilla, `${{ total }}` y `$ $`.
2. A-05: crear `fechaLocalISO()` y reemplazar UTC en Pedidos/Reportes.
3. Tests manuales de borde: 21:00–23:59, cambio de mes, montos con miles y decimales.
4. Opcional: unificar `formatearDinero` y `formatearMoneda` en una sola API.

**Archivos principales:** `Pos/Terminal.vue`, `Ventas/Index.vue`, `CajaDiaria/Index.vue`, `Productos/Index.vue`, `Ingresos/Index.vue`, `Pedidos/Index.vue`, `Reportes/Index.vue`, `Utils/formatters.js`.

**Riesgo:** medio por cantidad de archivos, bajo por lógica. No tocar los valores que se envían al backend; sólo la presentación.

**No incluye:** cambios de moneda, redondeos de negocio ni recálculos.

### Etapa 3 — Accesibilidad operativa

**Objetivo:** que POS, Caja, Configuración y Fiados se puedan usar con teclado y lector de pantalla.

**Incluye:**

1. A-02: cards de POS seleccionables con teclado.
2. A-03: labels asociados en módulos priorizados; `aria-label` en icon-only; `aria-expanded` en menús.
3. A-04: migrar modales de mayor uso al `Modal` base o a un wrapper accesible.
4. Drawer móvil con botón de cierre, Escape y foco.
5. Revisar foco visible en botones con `outline-none`.

**Riesgo:** medio. Requiere prueba manual con teclado y, idealmente, con un lector de pantalla.

**No incluye:** auditoría WCAG completa ni rediseño de formularios.

### Etapa 4 — Responsive de tablas y menús

**Objetivo:** que el backoffice sea usable en celular y en notebooks chicas sin scroll horizontal de página.

**Incluye:**

1. A-07: wrapper compartido de tabla con scroll interno.
2. Menús con teleport/popover y flip vertical/horizontal.
3. Revisar `Pedidos` en 360 px (fila de acciones).
4. Revisar `Productos` con pocos resultados y última fila.
5. Revisar `Caja` en mobile: tablas ya tienen scroll, pero los modales deben caber.

**Riesgo:** medio. El cambio de overflow puede recortar menús si no se hace con popover; probar última fila, primera fila y mobile.

**No incluye:** convertir tablas a cards ni cambiar la navegación.

### Etapa 5 — Consistencia visual y higiene técnica

**Objetivo:** reducir la sensación de “varias apps” y evitar que los errores vuelvan.

**Incluye:**

1. M-07: tokens compartidos y layout base; reutilizar navegación en AdminGlobal.
2. M-01: ordenar Dashboard y eliminar padding duplicado.
3. M-02: subir el mínimo tipográfico en módulos operativos.
4. M-08: lazy load de Leaflet.
5. M-09: ESLint/Prettier y smoke tests.
6. B-04: extraer `ProductoCard.vue`.
7. B-01/B-02/B-03: limpieza de deuda menor.

**Riesgo:** medio. Es la etapa más amplia; conviene dividirla en PRs pequeños por módulo.

**No incluye:** cambio de identidad de marca ni rediseño de la tienda.

---

## 6. Riesgos y regresiones

| Riesgo | Dónde | Mitigación |
|---|---|---|
| Escapar HTML rompe el formato de confirmaciones | Swal en Fiados/Productos/Ingresos/POS/Transferencias | Usar HTML estático y llenar nodos; probar nombres con `<`, `>`, `&`, comillas |
| Envolver Configuración en `<form>` hace que los tabs envíen sin querer | `Configuracion/Index.vue` | `type="button"` en tabs; formulario por pestaña o `@submit.prevent` |
| Cambiar formato de dinero altera tests o PDFs | POS/Ventas/Caja/Ingresos | Cambiar sólo plantillas; no tocar cálculos ni payloads; revisar impresiones |
| Fecha local difiere de la fecha del backend | Pedidos/Reportes | Priorizar `props.hoy` del backend; probar 21:00–23:59 y cambio de mes |
| Agregar `overflow-x-auto` recorta menús | Tablas con acciones | Menús en popover/teleport, no dentro del contenedor con scroll |
| Migrar modales rompe flujos con SweetAlert anidado | Caja/Fiados/Productos | Un modal a la vez; probar apertura/cierre y foco |
| Labels con `id` duplicados | Formularios repetidos en listas | IDs únicos por fila o `aria-labelledby` |
| Iconos SVG cambian layout | Header/Caja | Mantener tamaño con `w/h`; revisar alineación y botones icon-only |
| Lint estricto frena el build | Todo el frontend | Adoptar reglas gradualmente; primero `no-undef` y Vue esenciales |
| Cambios de accesibilidad alteran atajos del POS | POS | Probar F1–F9, búsqueda, Enter y foco después de cada acción |

**Regla de seguridad para todas las etapas:** no modificar `comercio_id`, permisos, precios, stock, caja, fiados, planes ni reglas de negocio. La auditoría es de presentación y experiencia; cualquier cambio de contrato con el backend requiere un plan aparte.

---

## 7. Veredicto

**Aprobado con observaciones críticas.**

VendAR tiene una base de producto sólida y pantallas críticas bien pensadas. El POS y la Caja no necesitan un rediseño: necesitan correcciones de formato, accesibilidad e iconografía. La tienda pública ya tiene el mejor sistema visual del proyecto y puede servir de referencia para ordenar el backoffice.

Pero hoy hay tres problemas que impiden considerar el frontend “terminado”:

1. Confirmaciones que pueden ejecutar HTML de usuarios.
2. Configuración que puede fallar sin mostrar error.
3. Comprobantes de tienda que muestran error después de subirse.

A eso se suman problemas altos de fechas, dinero, tablas, labels y modales que afectan la operación diaria. Ninguno exige una reescritura; todos exigen priorización.

**Recomendación:** no avanzar con nuevas funcionalidades visuales grandes hasta cerrar la Etapa 1. Después, resolver dinero/fechas y accesibilidad en paralelo, y dejar el orden visual para el final.

---

## 8. Primera etapa recomendada

**Implementar la Etapa 1 — Correcciones críticas de confianza.**

Es la etapa con mejor relación impacto/riesgo:

- No requiere decisiones de diseño.
- No cambia reglas de negocio.
- Corrige flujos de dinero y confianza.
- Deja el sistema en condiciones de seguir iterando sin arrastrar errores silenciosos.

### Orden sugerido dentro de la Etapa 1

1. **C-01 — Seguridad de confirmaciones.**  
   Crear un helper de escape o, preferentemente, replicar el patrón de `AdminGlobal/Facturacion.vue`. Revisar los cinco módulos con `Swal.fire({ html })`.

2. **C-02 — Configuración.**  
   Envolver los campos de General/POS/Sistema en el formulario, mostrar `form.errors`, agregar `onError` y asegurar que los tabs no envíen.

3. **C-03 — Comprobante de tienda.**  
   Corregir `pedidos` por `props.pedidos`; separar error de subida de error de actualización; verificar que el comprobante aparezca.

4. **A-01 — Iconos y cierres de Caja.**  
   Reemplazar Remix por SVG inline o cargar la librería; agregar `aria-label`; agregar Escape/click fuera o migrar al `Modal` base.

5. **Verificación manual mínima.**  
   - Vender un producto en POS y cobrar.  
   - Abrir y cerrar Caja, arqueo y movimiento manual.  
   - Guardar Configuración con un campo válido y uno inválido.  
   - Subir un comprobante desde la tienda y verlo reflejado.  
   - Abrir una confirmación con un nombre que contenga `<b>prueba</b>` y verificar que se muestre literal.

### Qué no hacer en la Etapa 1

- No cambiar la estética general.
- No reemplazar SweetAlert2.
- No tocar tablas ni responsive.
- No cambiar cálculos de dinero ni fechas.
- No agregar dependencias nuevas sin justificar (la excepción es la librería de iconos, si se decide cargarla en lugar de usar SVG).

---

## Anexo A — Referencias visuales para verificar en navegador

No se generaron capturas para no ejecutar la app ni alterar datos. Estas referencias permiten ubicar el efecto visual exacto antes de implementar.

| Referencia | Qué mirar | Efecto esperado |
|---|---|---|
| `CajaDiaria/Index.vue:908` | Total del visor global | Se ve `$ $ 1.234,56` |
| `CajaDiaria/Index.vue:912` | Cierre del visor global | Botón circular vacío |
| `CajaDiaria/Index.vue:1013,1089,1193,1262` | Cierres de modales | Iconos invisibles, sin texto |
| `Pos/Terminal.vue:1321` | Total de la venta | Se ve `$1234.50`, no `$ 1.234,50` |
| `Ventas/Index.vue:225` | Total en historial | Se ve `$1234.5` o el valor crudo |
| `Productos/Index.vue:237-238` | Menú de acciones | Puede recortarse contra el contenedor con scroll |
| `Ventas/Index.vue:185` | Tabla en 390 px | La página desborda horizontalmente |
| `Configuracion/Index.vue:825` | Formulario General | Sólo el botón está dentro del `<form>` |
| `Pedidos/Index.vue:272,284` | Labels “ORDEN” y “PAGO” | Texto de 8 px |
| `Dashboard.vue:193-275` | Primer pantallazo | Banners antes de los KPIs |
| `StoreLayout.vue:42-80` | Tienda | Referencia de tokens y tema claro/oscuro |
| `AdminGlobal/Facturacion.vue:110-118` | Confirmación de pago | Referencia de HTML seguro y fecha local |

---

## Anexo B — Criterios de prioridad usados

- **Crítica:** puede afectar dinero, deuda, stock, seguridad o hacer que el usuario crea que una operación se completó cuando no fue así. Debe resolverse antes de seguir agregando funcionalidad.
- **Alta:** fricción importante en una tarea frecuente, barrera de accesibilidad o error visible en un módulo crítico. Debería resolverse en el corto plazo.
- **Media:** ineficiencia, inconsistencia o deuda que tiene workaround. Se puede planificar.
- **Baja:** pulido, limpieza o mejora de mantenimiento sin impacto operativo inmediato.

**Tipos usados:** visual, UX, responsive, accesibilidad, técnico y seguridad. Un mismo hallazgo puede tener más de un tipo; se clasificó por su impacto principal.

---

## Anexo C — Archivos y zonas revisadas

- Layouts: `AuthenticatedLayout.vue`, `GlobalAdminLayout.vue`, `StoreLayout.vue`, `GuestLayout.vue`.
- Navegación: `Sidebar.vue`, `Header.vue`.
- Componentes base: `Modal.vue`, `Pagination.vue`, `EmptyState.vue`, `BaseButton.vue`, `BaseCard.vue`, `BaseBadge.vue`, `PriceDisplay.vue`.
- POS: `Pos/Terminal.vue`.
- Caja: `CajaDiaria/Index.vue`, `Cajas/Index.vue`, `Cajas/CierreDiario.vue`.
- Ventas: `Ventas/Index.vue`, `Ventas/Canceladas.vue`.
- Catálogo: `Productos/Index.vue`, `Productos/Componentes/ModalProducto.vue`, `Productos/Componentes/DetalleProducto.vue`, `Categorias`, `Marcas`, `Lotes`.
- Compras y stock: `Ingresos`, `Transferencias`, `Reposicion`, `OrdenesCompra`.
- Fiados: `Consumidores/Index.vue`.
- Proveedores: `Proveedores/Index.vue`.
- Pedidos: `Pedidos/Index.vue`.
- Tienda: `Cliente/Panel.vue`, `Welcome.vue`, `Components/Tienda/*`, `StoreLayout.vue`.
- Configuración: `Configuracion/Index.vue`.
- Suscripción: `Suscripcion/MiPlan.vue`.
- Admin SaaS: `AdminGlobal/*`, `GlobalAdminLayout.vue`.
- Seguridad/roles: `Usuarios`, `Roles`, `Sucursales`, `Auditoria`.
- Reportes: `Reportes/Index.vue`, `Dashboard.vue`.
- Onboarding: `Onboarding/Banner.vue`, `Onboarding/Wizard` (referencia de flujo).
- Infra frontend: `package.json`, `tailwind.config.js`, `vite.config.js`, `resources/css/app.css`, `resources/views/app.blade.php`, `Utils/formatters.js`.

---

**Fin del informe.**  
No se modificó código de aplicación, base de datos, dependencias, `AGENTS.md` ni skills. Las referencias son evidencia de código y deben confirmarse visualmente antes de implementar.
