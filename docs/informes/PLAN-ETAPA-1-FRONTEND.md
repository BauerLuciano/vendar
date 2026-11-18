# PLAN — Etapa 1: Correcciones críticas de confianza

**Fecha:** 17/11/2026  
**Origen:** `docs/informes/AUDITORIA-FRONTEND.md`  
**Alcance:** C-01, C-02, C-03 y A-01.  
**Modo:** planificación exclusiva. No se modifica código, backend, rutas, API, migraciones, base de datos, dependencias, `AGENTS.md` ni skills.  
**Estado:** plan listo para revisión. No se ejecutaron pruebas ni se inició la aplicación.

---

## 1. Objetivo y criterio de cierre

La Etapa 1 corrige cuatro problemas que afectan la confianza del usuario:

1. Confirmaciones que pueden ejecutar HTML de datos del sistema.
2. Configuración que puede fallar sin mostrar errores.
3. Comprobantes de tienda que muestran error después de subirse.
4. Iconos inexistentes y cierres de Caja sin señal visible ni salida accesible.

**Criterio de cierre de la etapa:**

- Los cuatro hallazgos quedan cerrados.
- Las siete verificaciones de la sección 5 pasan.
- No hay cambios de contrato con el backend.
- No se alteran cálculos, stock, precios, caja, fiados ni permisos.
- No se agregan dependencias.
- `npm run build` sigue funcionando.
- No aparecen errores nuevos en consola.

**Fuera de alcance:**

- Rediseño visual general.
- Formato único de dinero y fechas (Etapa 2).
- Accesibilidad completa y focus trap general (Etapa 3).
- Responsive de tablas y menús (Etapa 4).
- Tokens, Dashboard, Leaflet y lint (Etapa 5).
- Cambios en `Components/Modal.vue` en esta etapa.

---

## 2. Archivos y componentes afectados

### 2.1 C-01 — Confirmaciones SweetAlert2 seguras

| Archivo | Zona | Cambio previsto |
|---|---|---|
| `resources/js/Pages/Consumidores/Index.vue` | `415-475` | Tabla de duplicados con HTML estático y filas construidas con `textContent` |
| `resources/js/Pages/Productos/Componentes/ModalProducto.vue` | `244-260` y `270-293` | Dos modales con HTML estático; imagen por propiedad y sin `onerror` inline |
| `resources/js/Pages/Ingresos/Index.vue` | `61-80` | Lista de aumentos con HTML estático y `<li>` construidos por DOM |
| `resources/js/Pages/Pos/Terminal.vue` | `655-690` | Modal de cantidad por kg con HTML estático y valores por `textContent` |
| `resources/js/Pages/Transferencias/Index.vue` | `14-27`, `46-59`, `78-90` | Tres confirmaciones con HTML estático y valores por `textContent` |

**Referencia obligatoria:** `resources/js/Pages/AdminGlobal/Facturacion.vue:110-118` y `resources/js/Utils/pagoManual.js:1-45`.

### 2.2 C-02 — Configuración general

| Archivo | Zona | Cambio previsto |
|---|---|---|
| `resources/js/Pages/Configuracion/Index.vue` | `18-70` | Conservar el `form` y el payload actuales |
| `resources/js/Pages/Configuracion/Index.vue` | `162-180` | Agregar `onError`, cambio de pestaña con error y feedback |
| `resources/js/Pages/Configuracion/Index.vue` | `201-228` | `type="button"` en todos los botones de navegación |
| `resources/js/Pages/Configuracion/Index.vue` | `235-359` | General/POS/Sistema dentro del formulario principal |
| `resources/js/Pages/Configuracion/Index.vue` | `361-459` | Pestaña “Tienda Online y Pagos” dentro del formulario principal |
| `resources/js/Pages/Configuracion/Index.vue` | `461-546` | `metodos_pago_pos` queda con su formulario propio, sin anidar |
| `resources/js/Pages/Configuracion/Index.vue` | `548-823` | `storefront` queda con su formulario propio, sin anidar |
| `resources/js/Pages/Configuracion/Index.vue` | `825-830` | Footer dentro del formulario principal, visible sólo en tabs del `form` |

**Precisión importante:** en el código actual hay dos pestañas distintas:

- `tienda` — “Tienda Online y Pagos”: usa el `form` principal y se guarda con `guardarConfiguracion`.
- `storefront` — “Storefront”: usa `storefrontForm` y ya tiene su propio `<form>`.

La pestaña que debe quedar separada es **`storefront`**, porque es la que ya tiene formulario propio. La pestaña `tienda` debe seguir dentro del formulario principal para no cambiar los valores enviados ni romper el guardado actual.

### 2.3 C-03 — Comprobante de tienda

| Archivo | Zona | Cambio previsto |
|---|---|---|
| `resources/js/Pages/Cliente/Panel.vue` | `106-123` | Corregir `props.pedidos`, separar subida de actualización visual y actualizar la card |
| `resources/js/Pages/Cliente/Panel.vue` | `1-5` | Importar `router` sólo si se usa el fallback de recarga |
| `resources/js/Pages/Cliente/Panel.vue` | `331-340` | Sin cambios de template: la card ya reacciona a `comprobante_transferencia_url` |

### 2.4 A-01 — Iconos y cierres

| Archivo | Zona | Cambio previsto |
|---|---|---|
| `resources/js/Pages/CajaDiaria/Index.vue` | `497`, `624-680`, `747-750`, `764`, `806-849`, `903-946`, `983-1000`, `1011-1042`, `1078-1095`, `1119`, `1147`, `1183`, `1260-1298`, `1344-1348` | Reemplazar 41 usos de `<i class="ri-*">` y los 2 `ri-*` dinámicos de `:1147` por SVG inline |
| `resources/js/Pages/CajaDiaria/Index.vue` | `899`, `1007`, `1084`, `1189`, `1255` | Roles, nombres accesibles, cierre por teclado y política de clic fuera |
| `resources/js/Pages/CajaDiaria/Index.vue` | `586-592` | Enrutar Escape por funciones de cierre seguras |
| `resources/js/Pages/CajaDiaria/Index.vue` | `818`, `848` | Abrir modales con snapshot para detectar cambios sin guardar |
| `resources/js/Components/Header.vue` | `18-22` | Reemplazar iconos Remix por SVG inline decorativos |
| `resources/js/Pages/Auth/ElegirSucursal.vue` | `20`, `33`, `39` | Reemplazar iconos Remix por SVG inline decorativos |
| `resources/js/Components/Modal.vue` | `1-120` | Sólo evaluación; no se modifica en esta etapa |

### 2.5 Archivos que no se tocan

- Backend, controladores, rutas, API, migraciones, base de datos y reglas de negocio.
- `resources/js/Utils/pagoManual.js` y `AdminGlobal/Facturacion.vue`: son referencia, no se modifican.
- `resources/js/Components/Modal.vue`: no se modifica en Etapa 1.
- `storefrontForm` y `pmcForm`: conservan su comportamiento.
- Cálculos de caja, arqueo, stock, precios, fiados y permisos.
- `package.json`, `package-lock.json`, `AGENTS.md` y skills.

---

## 3. Cambios concretos previstos

### 3.1 C-01 — Confirmaciones seguras

#### Patrón común

Cada bloque debe quedar así:

1. `html` contiene sólo estructura y clases estáticas, sin `${}` con datos.
2. Los valores dinámicos se completan en `didOpen` con `textContent` o `value`.
3. Las filas de tablas se crean con `document.createElement` y `textContent`, no con strings HTML.
4. Las imágenes se asignan con `img.src = url` después de validar el protocolo; no se usa `onerror` inline.
5. Los valores ausentes se muestran con `—`; no se imprime `undefined`.
6. No se usa `innerHTML` con datos de usuario o de API.
7. Se conservan títulos, iconos, botones, colores, orden y textos funcionales.

**Regla de revisión:** ningún `html` de estos cinco archivos puede contener interpolación de datos. Se puede verificar con una búsqueda de `${` dentro de los bloques `html`.

#### 3.1.1 `Consumidores/Index.vue:415-475`

**Situación actual:** se construye `filas` con `c.nombre`, `c.apellido`, `c.documento`, `c.email`, `c.telefono` y `c.estado`; luego se interpola dentro de `html`. También se interpola `form.nombre`, `form.apellido` y `total`.

**Plan:**

- Definir un HTML estático con:
  - Título con `id="swal-duplicados-titulo"`.
  - Subtítulo con `id="swal-duplicados-nombre"`.
  - Tabla con `<tbody id="swal-duplicados-body">`.
- En `didOpen`:
  - `titulo.textContent = \`Ya existe${total > 1 ? 'n' : ''} ${total} cliente${total > 1 ? 's' : ''} con este nombre\``.
  - `nombre.textContent = \`${form.nombre} ${form.apellido}\``.
  - Crear un `DocumentFragment`; por cada duplicado, crear `tr`, `td` y `span` con `textContent`.
  - Para `documento`, `email` y `telefono`, usar `valor || '—'`.
  - Para estado, crear `span` con clase `text-emerald-600` o `text-rose-500` y texto `Activo`/`Inactivo`.
- Mantener `showCancelButton`, `confirmButtonText`, `cancelButtonText`, colores, `width`, `padding` y `customClass`.

**No cambiar:** la lógica de `checkDuplicadosNombre`, la decisión de crear o no el cliente, ni el texto de los botones.

#### 3.1.2 `Productos/Componentes/ModalProducto.vue:244-260`

**Situación actual:** se interpolan `p.nombre`, `p.marca`, `p.categoria` y `p.codigo_barras`.

**Plan:**

- HTML estático con `id="swal-producto-nombre"`, `id="swal-producto-marca"`, `id="swal-producto-categoria"`, `id="swal-producto-codigo"`.
- En `didOpen`, completar cada nodo con `textContent`.
- Si `marca` o `categoria` no existen, mostrar `—` o la fila correspondiente.
- Mantener `title: 'Producto ya existe en DB'`, `icon: 'info'`, botones y colores.
- No cambiar la redirección a `/productos` cuando el usuario confirma.

#### 3.1.3 `Productos/Componentes/ModalProducto.vue:270-293`

**Situación actual:** se interpola `apiData.imagen` dentro de `src` y se agrega `onerror="this.style.display='none'"`. También se interpolan `apiData.nombre`, `apiData.title`, `apiData.marca` y `apiData.presentacion`.

**Plan:**

- HTML estático con:
  - `id="swal-api-imagen"` para el `<img>`.
  - `id="swal-api-placeholder"` para el bloque “📦”.
  - `id="swal-api-nombre"`, `id="swal-api-marca"`, `id="swal-api-presentacion"`.
- En `didOpen`:
  - Completar nombre con `apiData.nombre || apiData.title || 'Producto'`.
  - Mostrar u ocultar las filas de marca/presentación según existan.
  - Validar `apiData.imagen`: aceptar sólo `http://`, `https://` o rutas relativas que empiecen con `/`. Rechazar `javascript:`, `data:` y cualquier otro esquema.
  - Si es válida, asignar `img.src`; si falla la carga, ocultar la imagen y mostrar el placeholder mediante un listener `error` agregado con `addEventListener`.
  - Si no es válida, mostrar el placeholder directamente.
- No usar `onerror` inline ni interpolar la URL en el HTML.

**No cambiar:** la decisión de cargar el producto, la asignación de nombre/marca y el resto del flujo.

#### 3.1.4 `Ingresos/Index.vue:61-80`

**Situación actual:** se construye `listaProductos` con `p.producto` y `p.porcentaje`, y se interpola en `html`.

**Plan:**

- HTML estático con `<ul id="swal-aumentos-list">`.
- En `didOpen`, crear un `<li>` por producto con dos `<span>`:
  - Producto con `textContent`.
  - Porcentaje con `textContent` y las mismas clases.
- Mantener `title` estático, `icon: 'warning'`, botón y `customClass`.
- No cambiar la condición de `page.props.flash.alertas_inflacion` ni el resto de los flash.

#### 3.1.5 `Pos/Terminal.vue:655-690`

**Situación actual:** se interpolan `producto.nombre` y `producto.stock_actual` en el HTML del modal de cantidad.

**Plan:**

- HTML estático con:
  - `id="swal-peso-producto"`.
  - `id="swal-peso-disponible"`.
  - Los mismos `input` y `select` con `id="swal-peso"` y `id="swal-unidad"`.
- En `didOpen`:
  - Completar nombre y stock con `textContent`.
  - Mantener `document.getElementById('swal-peso').focus()`.
- Mantener `preConfirm`, `Swal.showValidationMessage` y el cálculo de gramos/kilos sin cambios.
- No cambiar el agregado al carrito ni el foco posterior del buscador.

#### 3.1.6 `Transferencias/Index.vue:14-27`, `46-59`, `78-90`

**Situación actual:** las tres confirmaciones interpolan `sugerencia.cantidad`, `sugerencia.producto.nombre`, `sugerencia.origen.nombre`, `item.cantidad`, `item.destino.nombre` y `item.origen.nombre`.

**Plan:**

- Tres HTML estáticos con IDs únicos por acción:
  - Despacho: `swal-desp-cantidad`, `swal-desp-producto`, `swal-desp-origen`.
  - Recepción: `swal-rec-cantidad`, `swal-rec-producto`, `swal-rec-destino`, `swal-rec-origen`.
  - Cancelación: `swal-canc-producto`, `swal-canc-cantidad`.
- En cada `didOpen`, completar con `textContent`.
- Mantener títulos, iconos, botones, colores, `reverseButtons` y las llamadas `router.post` sin cambios.
- No cambiar los mensajes de éxito/error ni las rutas.

#### Criterios de aceptación de C-01

- Ningún `html` de los cinco archivos contiene `${}` con datos.
- Un nombre con `<b>`, `<img onerror=...>`, comillas o `&` se muestra literal.
- La confirmación conserva los mismos datos, botones y comportamiento.
- No se ejecuta JavaScript inyectado.
- No se agregan dependencias ni se reemplaza SweetAlert2.

---

### 3.2 C-02 — Configuración general

#### Estructura actual

El `<form>` de `:825-830` sólo envuelve el botón “Guardar Ajustes”. Los campos de General, POS, Sistema y “Tienda Online y Pagos” están fuera. La pestaña `storefront` tiene su propio `<form>` (`:549-822`), y `metodos_pago_pos` tiene otro (`:492-544`).

#### Estructura objetivo

Se propone esta separación, sin formularios anidados:

```text
<div class="bg-white ...">
  <form v-show="tabsDelFormPrincipal.includes(tabActiva)"
        @submit.prevent="guardarConfiguracion">
    <div v-show="tabActiva === 'general'">...</div>
    <div v-show="tabActiva === 'pos'">...</div>
    <div v-show="tabActiva === 'sistema'">...</div>
    <div v-show="tabActiva === 'tienda'">...</div>

    <footer v-show="tabsDelFormPrincipal.includes(tabActiva)">
      <button type="submit">Guardar Ajustes</button>
    </footer>
  </form>

  <div v-show="tabActiva === 'metodos_pago_pos'">
    ... <form @submit.prevent="guardarPmc">...</form> ...
  </div>

  <div v-show="tabActiva === 'storefront'">
    ... <form @submit.prevent="guardarStorefront">...</form> ...
  </div>
</div>
```

**Decisiones:**

- `tabsDelFormPrincipal = ['general', 'pos', 'sistema', 'tienda']`.
- `metodos_pago_pos` y `storefront` quedan fuera del formulario principal porque ya tienen formularios propios.
- El footer deja de mostrarse en `metodos_pago_pos` y `storefront`; cada uno usa su propio botón.
- Los botones de navegación pasan a `type="button"` para que nunca envíen un formulario, incluso si en el futuro se mueven dentro de uno.
- El botón de “Recargos por Tarjeta” que usa `router.visit` también recibe `type="button"`.

#### Errores por campo

Agregar un mensaje debajo de cada campo del formulario principal:

```vue
<p v-if="form.errors.nombre_empresa"
   class="mt-1 text-xs font-bold text-rose-500">
  {{ form.errors.nombre_empresa }}
</p>
```

Mapa de campos por pestaña:

| Pestaña | Campos |
|---|---|
| General | `nombre_empresa`, `cuit`, `telefono`, `direccion`, `logo` |
| POS | `formato_impresion`, `ticket_mensaje_pie`, `permitir_stock_negativo`, `ticket_digital_auto_email` |
| Sistema | `limite_fiado_defecto`, `moneda_defecto`, `costo_delivery_defecto`, `mora_dias_gracia`, `mora_tasa_interes` |
| Tienda Online y Pagos | `envio_precio_base`, `envio_precio_km`, `envio_radio_km`, `transferencia_cbu`, `transferencia_alias`, `transferencia_titular`, `acepta_efectivo`, `mp_enabled`, `mp_access_token`, `viumi_enabled`, `viumi_client_id`, `viumi_client_secret`, `viumi_environment` |

Para el logo, el backend valida `logo`; mostrar `form.errors.logo` cerca del bloque de carga.

#### Feedback de rechazo

`guardarConfiguracion` conserva `form.transform(...).post(route('configuracion.update'), { preserveScroll: true, ... })` y agrega:

- `onError: (errors) => { ... }`.
- Detección de la primera pestaña con error usando el mapa anterior.
- Cambio automático a esa pestaña para que el usuario vea el campo marcado.
- Un toast genérico: “No se pudieron guardar los ajustes. Revisá los campos marcados.”
- No mostrar el error crudo del backend en el toast; el detalle va debajo de cada campo.

**No cambiar:**

- `form.transform`.
- Los nombres de campos y los valores enviados.
- `preserveScroll`.
- `form.processing`.
- `storefrontForm`, `pmcForm`, `guardarStorefront` y `guardarPmc`.

#### Criterios de aceptación de C-02

- Enter en un campo del formulario principal guarda.
- Un campo requerido vacío bloquea el envío y muestra error.
- Un rechazo del backend muestra error por campo y un toast; no hay éxito falso.
- Si el error está en otra pestaña, la UI cambia a esa pestaña.
- Hacer clic en cualquier tab no dispara POST.
- `storefront` y `metodos_pago_pos` siguen guardando con sus propios formularios.
- Los valores enviados son los mismos que antes.

---

### 3.3 C-03 — Comprobante de tienda

#### Situación actual

En `Cliente/Panel.vue:115`:

```js
const pedido = pedidos.find(p => p.id === pedidoId);
```

`pedidos` no existe en el script; debe ser `props.pedidos`. El `ReferenceError` cae en el `catch` que envuelve la subida y la actualización, por lo que el usuario ve un error después de una subida exitosa.

#### Plan

1. Corregir la referencia a `props.pedidos`.
2. Separar la subida de la actualización local:

```text
try {
  res = await axios.post(...)
} catch (error) {
  toast de error de subida
  return
} finally {
  subiendoComprobante = null
  comprobanteFile = null
}

// La subida ya fue exitosa.
try {
  const pedido = props.pedidos.find(...)
  if (pedido) {
    pedido.comprobante_transferencia_url = res.data.url
  } else {
    router.reload({ only: ['pedidos'] })
  }
} catch (error) {
  toast de advertencia: "El comprobante se subió, pero actualizá la página para verlo."
}

toast de éxito: "Comprobante subido"
```

3. El toast de éxito se muestra una sola vez.
4. Si la actualización visual falla, no se muestra “No se pudo subir el comprobante”; se muestra una advertencia distinta.
5. Si el pedido no está en `props.pedidos`, se recarga sólo `pedidos` con Inertia. No se toca el backend.
6. Mantener el `finally` para limpiar `subiendoComprobante` y `comprobanteFile`.

**Opcional recomendado:** limpiar el valor del `<input type="file">` después de cada intento para permitir volver a subir el mismo archivo. No es obligatorio para cerrar C-03.

**No cambiar:**

- La ruta `/api/pedidos/{id}/comprobante`.
- El `FormData`, el nombre del campo `comprobante` ni el almacenamiento.
- El template de la card; ya usa `pedido.comprobante_transferencia_url`.

#### Criterios de aceptación de C-03

- Una subida exitosa muestra un solo toast de éxito.
- El comprobante aparece sin recargar manualmente.
- No aparece un toast de error después de una subida exitosa.
- Una subida fallida muestra un solo error y permite reintentar.
- No se modifica el backend ni el almacenamiento.

---

### 3.4 A-01 — Iconos y cierres

#### 3.4.1 Reemplazo de iconos Remix

No se instala `remixicon`. Se usan SVG inline con los mismos tamaños y clases.

| Grupo | Iconos Remix | Reemplazo |
|---|---|---|
| Cierre/navegación | `ri-close-line`, `ri-arrow-down-s-line`, `ri-arrow-right-line`, `ri-arrow-go-back-line`, `ri-refresh-line`, `ri-add-line`, `ri-play-circle-line`, `ri-check-double-line` | SVG inline tipo X, chevron, flecha, refresh, plus, play, check doble |
| Estado | `ri-eye-line`, `ri-lock-2-line`, `ri-lock-unlock-line`, `ri-alert-line`, `ri-alert-fill`, `ri-error-warning-line`, `ri-error-warning-fill`, `ri-information-line`, `ri-loader-4-line` | SVG inline de ojo, candado, alerta, información y spinner |
| Dominio | `ri-bank-card-line`, `ri-money-dollar-circle-line`, `ri-bank-line`, `ri-store-2-line`, `ri-store-3-line`, `ri-scales-3-line`, `ri-computer-line`, `ri-printer-line`, `ri-history-line`, `ri-file-list-3-line`, `ri-list-check` | SVG inline equivalentes, ya usados en el resto del proyecto |

**Reglas:**

- Los iconos decorativos llevan `aria-hidden="true"` y `focusable="false"`.
- Los botones icon-only llevan `aria-label`.
- Se conservan las clases de tamaño (`text-xs`, `text-xl`, `text-3xl`) y los contenedores.
- `confirmButtonText: '<i class="ri-printer-line"></i> Imprimir Reporte'` en `CajaDiaria/Index.vue:497` pasa a texto plano `Imprimir Reporte` o a un SVG estático. No se interpola nada.

**Archivos:**

- `CajaDiaria/Index.vue`: 41 usos de `<i class="ri-*">` y 2 `ri-*` dinámicos en `:1147`.
- `Header.vue`: 2 usos decorativos.
- `Auth/ElegirSucursal.vue`: 3 usos decorativos.
- Total del alcance: 46 `<i class="ri-*">` más los 2 dinámicos de Caja.

#### 3.4.2 Cierres visibles en Caja

En `CajaDiaria/Index.vue`, los cierres icon-only están en:

- `:912` — Visor de Cajas.
- `:1013` — Detalle de caja global.
- `:1089` — Arqueo y Cierre.
- `:1193` — Movimiento Manual.
- `:1262` — Detalle de historial.

**Plan:**

- Reemplazar cada `<i class="ri-close-line">` por un SVG X visible.
- Agregar `type="button"`, `aria-label` y `title`:
  - “Cerrar visor de cajas”.
  - “Cerrar detalle de caja”.
  - “Cerrar arqueo y cierre”.
  - “Cerrar movimiento manual”.
  - “Cerrar detalle de historial”.
- Agregar foco visible: `focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-400 focus-visible:ring-offset-2`.
- Mantener el tamaño del botón y del icono para no romper el header.

#### 3.4.3 Salida por teclado y clic fuera

**Estado actual:** `cerrarModales` (`:586-592`) ya escucha Escape y cierra cuatro modales, pero no incluye `mostrarPanelGlobal` y cierra los formularios sin confirmación.

**Plan:**

- Crear funciones de cierre:
  - `cerrarPanelGlobal()`.
  - `cerrarDetalleGlobal()`.
  - `cerrarDetalleHistorial()`.
  - `cerrarModalCierre()`.
  - `cerrarModalGasto()`.
- `cerrarModales` debe cerrar primero el modal superior:
  - Si `mostrarModalDetalleGlobal` está abierto, cerrar ese.
  - Si `mostrarModalDetalleHistorial` está abierto, cerrar ese.
  - Si `mostrarModalCierre` está abierto, cerrar ese.
  - Si `mostrarModalGasto` está abierto, cerrar ese.
  - Si `mostrarPanelGlobal` está abierto, cerrar ese.
- `mostrarPanelGlobal` se suma al manejo de Escape.

**Clic fuera:**

- Visor de Cajas, detalle global y detalle de historial: `@click.self` cierra.
- Arqueo/Cierre y Movimiento Manual: **no** cierran con clic fuera.
- Los formularios con datos sin guardar sólo se cierran con el botón visible, “Cancelar” o Escape + confirmación.

**Protección de datos sin guardar:**

- Al abrir Arqueo/Cierre, guardar un snapshot de `formCierre`.
- Al abrir Movimiento Manual, resetear `formGasto` y guardar un snapshot.
- Calcular `cierreModificado` y `gastoModificado`.
- Si el formulario fue modificado, `cerrarModalCierre` y `cerrarModalGasto` muestran una confirmación:
  - “¿Descartar los cambios sin guardar?”
  - “Seguir editando” / “Descartar”.
- Si no fue modificado, cierran directamente.
- El cierre exitoso de `cerrarCaja` y `registrarGastoManual` sigue cerrando el modal sin confirmación, porque ya se guardó.

#### 3.4.4 Semántica y foco

- Cada overlay interno recibe:
  - `role="dialog"`.
  - `aria-modal="true"`.
  - `aria-labelledby` apuntando al `id` del título.
- Agregar `id` a los títulos:
  - `caja-panel-global-title`.
  - `caja-detalle-global-title`.
  - `caja-cierre-title`.
  - `caja-gasto-title`.
  - `caja-historial-title`.
- Al abrir, enfocar el botón de cierre o el primer campo.
- Al cerrar, devolver el foco al botón que abrió el modal.
- El focus trap completo se difiere a la Etapa 3. En Etapa 1 se garantiza salida por teclado, nombre accesible y foco inicial/retorno.

#### 3.4.5 Evaluación de `Modal.vue`

**Resultado:** no migrar los modales de Caja en Etapa 1.

Motivos:

- `Modal.vue` sólo soporta `maxWidth` de `sm` a `2xl`; Caja usa `max-w-4xl`, `max-w-6xl` y `max-h-[90vh]`.
- `Modal.vue` cierra con clic fuera de forma predeterminada; `closeable=false` también desactiva Escape. Eso no sirve para formularios con datos sin guardar.
- Migrar exigiría modificar el componente compartido y afectar otras páginas.

**Decisión:** mantener los overlays manuales, agregarles semántica, Escape, clic fuera selectivo y foco. Evaluar una migración en Etapa 3, cuando se aborde el focus trap y se pueda extender `Modal.vue` con `closeOnOutside` y tamaños adicionales.

#### Criterios de aceptación de A-01

- No queda ningún `<i class="ri-*">` en Caja, Header ni ElegirSucursal.
- Todos los cierres son visibles y tienen nombre accesible.
- Escape cierra los modales de lectura.
- Escape en formularios modificados pide confirmación.
- El clic fuera no cierra formularios con datos.
- El foco vuelve al disparador.
- No se agregan dependencias.

---

## 4. Dependencias entre cambios

| Cambio | Depende de | Puede ir en paralelo con | Observación |
|---|---|---|---|
| C-01 | Ninguno | C-03, C-02, A-01 | Cinco archivos independientes; no requiere helper compartido |
| C-02 | Ninguno | C-01, C-03, A-01 | Sólo `Configuracion/Index.vue` |
| C-03 | Ninguno | C-01, C-02, A-01 | Sólo `Cliente/Panel.vue` |
| A-01 | Ninguno | C-01, C-02, C-03 | Sólo Caja, Header y ElegirSucursal |

No hay dependencias bloqueantes. La única precaución es no introducir un helper compartido en C-01 que después obligue a tocar otros archivos; el plan propone HTML estático local por archivo.

---

## 5. Verificaciones requeridas

> Estas verificaciones se ejecutan en BUILD, en un entorno de pruebas. En esta tarea de planificación no se ejecutan ni se modifican datos.

### 5.1 Nombres con HTML y atributos de evento en confirmaciones

**Objetivo:** demostrar que C-01 no ejecuta HTML.

**Pasos:**

1. En entorno de pruebas, usar un cliente, producto o sucursal con nombre:
   - `<b>Prueba</b>`
   - `<img src=x onerror="window.__xss=1">`
   - `"Comillas" & <script>`
2. Abrir cada confirmación afectada:
   - Duplicados de Consumidores.
   - Producto existente en ModalProducto.
   - Producto de API en ModalProducto.
   - Alerta de aumentos en Ingresos.
   - Cantidad por kg en POS.
   - Despacho, recepción y cancelación en Transferencias.
3. Revisar que el texto se muestre literal y que `window.__xss` siga indefinido.
4. En ModalProducto, probar una URL de imagen `javascript:alert(1)` y verificar que se muestre el placeholder.

**Resultado esperado:** ningún script se ejecuta; no hay errores de consola; los datos correctos se ven en pantalla.

**Criterio técnico adicional:** buscar `${` dentro de los bloques `html` de los cinco archivos y confirmar que no hay interpolación de datos.

### 5.2 Guardado de Configuración con valores válidos

**Pasos:**

1. Abrir Configuración.
2. Guardar sin cambios con el botón y luego con Enter.
3. Cambiar un valor seguro en entorno de pruebas (por ejemplo, `nombre_empresa` o `mora_dias_gracia`).
4. Confirmar el POST y recargar.

**Resultado esperado:** un solo POST, toast de éxito, valores persistidos, sin errores de consola.

### 5.3 Rechazo de Configuración por validación del backend

**Pasos:**

1. En la pestaña “Tienda Online y Pagos”, escribir letras en `transferencia_cbu` (el backend exige sólo números y el input no tiene `pattern` HTML).
2. Enviar el formulario.
3. Alternativa: forzar `mora_dias_gracia = 366` desde DevTools si se necesita probar una pestaña del form principal.

**Resultado esperado:**

- El backend responde 422.
- El campo muestra `form.errors.transferencia_cbu` (o el error correspondiente).
- La UI cambia a la pestaña que contiene el error.
- Aparece el toast “No se pudieron guardar los ajustes…”.
- No aparece toast de éxito.
- No se guarda el valor inválido.

### 5.4 Navegación entre pestañas sin envíos accidentales

**Pasos:**

1. Abrir Configuración.
2. Completar un campo.
3. Hacer clic en todas las pestañas: General, POS, Sistema, Tienda Online y Pagos, POS - Medios de Pago, Storefront.
4. Revisar la pestaña Network.
5. Probar Enter dentro de un campo de cada formulario.

**Resultado esperado:**

- Cambiar de pestaña no dispara POST.
- No aparece toast de guardado.
- Enter en el form principal guarda el form principal.
- Enter en `metodos_pago_pos` guarda `pmcForm`.
- Enter en `storefront` guarda `storefrontForm`.
- El footer “Guardar Ajustes” no aparece en `metodos_pago_pos` ni en `storefront`.

### 5.5 Subida exitosa y fallida de comprobantes

**Éxito:**

1. Usar un pedido de prueba con pago por transferencia pendiente.
2. Subir un JPG/PNG/PDF válido menor a 5 MB.
3. Revisar toasts, card y Network.

**Resultado esperado:** un solo toast de éxito; la card “Comprobante subido” aparece sin recargar; no aparece error; el botón de subida desaparece.

**Fallo:**

1. Subir un archivo con extensión no permitida o mayor a 5 MB.
2. Revisar toasts y estado del botón.

**Resultado esperado:** un solo toast de error; la card sigue mostrando “Subir comprobante”; `subiendoComprobante` vuelve a `null`; se puede reintentar.

**Revisión de código:** confirmar que el `catch` de `axios` no envuelve la actualización de `props.pedidos` y que el fallo de actualización visual muestra una advertencia distinta.

### 5.6 Apertura y cierre de todos los modales de Caja

**Pasos:**

1. Abrir “Visor de Cajas”.
2. Abrir el detalle de una caja global.
3. Abrir “Arqueo y Cierre”.
4. Abrir “Movimiento Manual”.
5. Abrir “Detalle de Caja” del historial.
6. En cada uno:
   - Usar el botón X.
   - Usar el botón de cancelar/cerrar del footer.
   - Usar Escape.
   - Hacer clic fuera del contenido.
7. En Arqueo y Movimiento Manual, modificar un campo antes de cerrar.

**Resultado esperado:**

- Todos los cierres son visibles y funcionan.
- Los modales de lectura cierran con Escape y clic fuera.
- Los formularios no cierran con clic fuera.
- Si el formulario fue modificado, Escape pide confirmación.
- Si se cancela la confirmación, el modal sigue abierto con los datos.
- El foco vuelve al botón que abrió el modal.

### 5.7 Navegación con teclado y visibilidad de los botones de cierre

**Pasos:**

1. Abrir cada modal de Caja.
2. Recorrer con Tab hasta el botón de cierre.
3. Activarlo con Enter y con Espacio.
4. Revisar el anillo de foco.
5. Revisar el nombre accesible en el árbol de accesibilidad.
6. Revisar Header y ElegirSucursal a 100% de zoom.
7. Buscar `ri-` en los tres archivos.

**Resultado esperado:**

- Todos los cierres son alcanzables por teclado.
- El foco es visible.
- El nombre accesible es correcto.
- Los iconos se ven en Header y ElegirSucursal.
- La búsqueda de `ri-` no devuelve usos en esos archivos.

---

## 6. Orden recomendado de implementación

### Paso 0 — Línea base segura

Antes de tocar código:

- Confirmar que `npm run build` pasa.
- Guardar evidencia visual de Caja, Configuración y Panel del cliente.
- Ejecutar búsquedas de control:
  - `ri-` en los tres archivos.
  - `html:` con `${` en los cinco archivos de C-01.
  - `<form` y `</form>` en Configuración.

No se ejecutan pruebas que modifiquen datos.

### Paso 1 — C-01, de menor a mayor complejidad

Orden sugerido dentro de C-01:

1. `Transferencias/Index.vue` — tres confirmaciones simples con spans.
2. `Pos/Terminal.vue` — un modal con dos valores y `preConfirm`.
3. `Ingresos/Index.vue` — una lista dinámica.
4. `Productos/Componentes/ModalProducto.vue` — dos modales, uno con imagen externa.
5. `Consumidores/Index.vue` — tabla dinámica, el caso más complejo.

**Por qué:** permite validar el patrón en casos simples antes de la tabla y la imagen.

### Paso 2 — C-03

Es el cambio más pequeño y de mayor impacto inmediato en confianza. Sirve para validar el flujo de build y la reacción de la UI antes de los cambios más grandes.

### Paso 3 — C-02

Reestructurar el formulario y agregar errores. Requiere prueba manual de tabs, Enter y rechazo del backend. No mezclar con C-01.

### Paso 4 — A-01

Reemplazar iconos y ordenar cierres. Puede hacerse en paralelo con C-01 porque no comparte archivos, pero se recomienda revisarlo después para concentrar la atención visual en Caja.

### Paralelización posible

- C-01 y A-01: sí, no comparten archivos.
- C-03 y C-02: sí, no comparten archivos.
- C-01 y C-02: sí, no comparten archivos.
- C-03 y A-01: sí.

**Recomendación para un solo implementador:** C-01 → C-03 → C-02 → A-01.  
**Recomendación para dos implementadores:** una persona hace C-01 + C-03; otra hace C-02 + A-01, con revisión cruzada.

---

## 7. Riesgos y regresiones posibles

| Riesgo | Dónde | Impacto | Mitigación |
|---|---|---|---|
| Queda alguna interpolación `${}` dentro de `html` | C-01 | XSS persiste | Búsqueda de `${` en los bloques `html`; prueba con payloads |
| Se pierde un dato en la tabla de duplicados | Consumidores | El usuario no ve toda la información | Probar con 1, 2 y varios duplicados; comparar antes/después |
| La imagen externa no carga o se rompe el layout | ModalProducto | Modal vacío o roto | Placeholder, validación de URL y listener `error` |
| El HTML estático cambia el aspecto | C-01 | Confusión visual | Conservar clases, tamaños, botones y colores; comparar capturas |
| Se anidan formularios en Configuración | C-02 | HTML inválido y submits cruzados | Mantener `storefront` y `pmcForm` fuera del form principal |
| El footer guarda el formulario equivocado | C-02 | Se guardan datos incorrectos | Footer sólo en `general`, `pos`, `sistema`, `tienda` |
| Los tabs envían sin querer | C-02 | POST accidental | `type="button"` en todos los tabs |
| El error de backend queda en una pestaña oculta | C-02 | El usuario no ve el error | Cambiar a la primera pestaña con error |
| Se altera el payload de Configuración | C-02 | Backend recibe otros valores | No tocar `form.transform` ni nombres de campos |
| `props.pedidos` no es reactivo al mutar | C-03 | La card no se actualiza | Probar subida real; fallback `router.reload({ only: ['pedidos'] })` |
| Se muestra éxito y error juntos | C-03 | El cliente desconfía | Separar subida de actualización; un solo toast por resultado |
| El archivo queda seleccionado y no se puede resubir | C-03 | Reintento bloqueado | Limpiar `input.value` después del intento (opcional) |
| El SVG cambia el tamaño del header | A-01 | Layout desplazado | Mantener `w/h` y clases; revisar Caja/Header/ElegirSucursal |
| Escape cierra un formulario con datos | A-01 | Pérdida de arqueo o movimiento | Confirmación si `cierreModificado`/`gastoModificado` |
| Clic fuera cierra un formulario | A-01 | Pérdida de datos | Sin `@click.self` en Cierre/Gasto |
| El foco no vuelve al disparador | A-01 | Desorientación | Guardar `document.activeElement` y restaurar |
| Se modifica `Modal.vue` y afecta otras páginas | A-01 | Regresión global | No migrar en Etapa 1; evaluar en Etapa 3 |
| Se agrega una dependencia de iconos | A-01 | Peso y mantenimiento | SVG inline; no instalar `remixicon` |
| Se toca lógica de caja/stock sin querer | A-01 | Regla de negocio alterada | Cambios sólo de presentación y cierre; no tocar cálculos |

---

## 8. Criterios de aceptación y veredicto

### 8.1 Checklist de aceptación

| ID | Criterio | Verificación |
|---|---|---|
| C-01 | Ningún `html` interpolado con datos | Búsqueda + prueba con payloads |
| C-01 | Confirmaciones conservan contenido y botones | Revisión visual de los 8 modales |
| C-01 | Imagen externa no ejecuta `onerror` inline | Prueba con URL inválida |
| C-02 | Formulario principal envuelve General/POS/Sistema/Tienda | Revisión de DOM |
| C-02 | `storefront` y `metodos_pago_pos` conservan su form | Revisión de DOM y guardado |
| C-02 | Errores por campo y toast de rechazo | Prueba 5.3 |
| C-02 | Tabs no envían | Prueba 5.4 |
| C-02 | Payload sin cambios | Diff de `form.transform` y request |
| C-03 | `props.pedidos` y actualización visual | Prueba 5.5 |
| C-03 | Sin falso error después de subir | Prueba 5.5 |
| C-03 | Reintento tras fallo | Prueba 5.5 |
| A-01 | Sin `ri-*` en los tres archivos | Búsqueda |
| A-01 | Cierres visibles y accesibles | Prueba 5.7 |
| A-01 | Escape y clic fuera según política | Prueba 5.6 |
| A-01 | Formularios con datos no se cierran accidentalmente | Prueba 5.6 |
| A-01 | Foco inicial y retorno | Prueba 5.6/5.7 |
| Global | Sin cambios de backend/DB/dependencias | `git diff --stat` |
| Global | Build sin errores | `npm run build` |

### 8.2 Veredicto

**Aprobado para BUILD.**

El plan es ejecutable sin cambios de backend, sin migraciones, sin dependencias nuevas y con verificaciones acotadas. Los cuatro hallazgos se pueden cerrar en cambios independientes y revisables.

**Dos precisiones vinculantes para el BUILD:**

1. La pestaña con formulario propio que se mantiene separada es **`storefront`**. La pestaña **`tienda`** (“Tienda Online y Pagos”) sigue dentro del formulario principal porque sus campos se guardan con `guardarConfiguracion`; sacarla cambiaría el comportamiento actual.
2. Los modales de Caja **no se migran** a `Modal.vue` en esta etapa. Se mantienen overlays manuales con semántica, Escape, clic fuera selectivo y foco. La migración se evalúa en la Etapa 3, cuando se aborde el focus trap y se pueda extender el componente compartido sin riesgo para otras páginas.

Si el equipo esperaba que `tienda` quedara fuera del formulario principal, el plan requiere un ajuste antes de BUILD. Con la interpretación actual —basada en el código— el plan está aprobado.

---

## Anexo A — Matriz de verificación por hallazgo

| Hallazgo | Verificaciones |
|---|---|
| C-01 | 5.1 |
| C-02 | 5.2, 5.3, 5.4 |
| C-03 | 5.5 |
| A-01 | 5.6, 5.7 |
| Global | Build, consola, `git diff --stat` |

---

## Anexo B — Búsquedas de control para el BUILD

Ejecutar después de implementar, sin modificar datos:

```bash
# No deben quedar iconos Remix en los tres archivos
rg -n 'ri-[a-z0-9-]+' \
  resources/js/Pages/CajaDiaria/Index.vue \
  resources/js/Components/Header.vue \
  resources/js/Pages/Auth/ElegirSucursal.vue

# Revisar que los html de C-01 no tengan interpolación de datos
rg -n 'html:' \
  resources/js/Pages/Consumidores/Index.vue \
  resources/js/Pages/Productos/Componentes/ModalProducto.vue \
  resources/js/Pages/Ingresos/Index.vue \
  resources/js/Pages/Pos/Terminal.vue \
  resources/js/Pages/Transferencias/Index.vue

# Revisar estructura de formularios en Configuración
rg -n '<form|</form>|type="submit"|type="button"' \
  resources/js/Pages/Configuracion/Index.vue

# Revisar que no quede la referencia incorrecta
rg -n '\bpedidos\.find' resources/js/Pages/Cliente/Panel.vue
```

**Nota:** `rg` puede mostrar los `html:` aunque no tengan `${}`. La revisión manual debe confirmar que el contenido es estático.

---

## Anexo C — Resumen de archivos por cambio

| Cambio | Archivos |
|---|---|
| C-01 | `Consumidores/Index.vue`, `Productos/Componentes/ModalProducto.vue`, `Ingresos/Index.vue`, `Pos/Terminal.vue`, `Transferencias/Index.vue` |
| C-02 | `Configuracion/Index.vue` |
| C-03 | `Cliente/Panel.vue` |
| A-01 | `CajaDiaria/Index.vue`, `Header.vue`, `Auth/ElegirSucursal.vue` |

---

**Fin del plan.**  
No se modificó código de aplicación, backend, rutas, API, migraciones, base de datos, dependencias, `AGENTS.md` ni skills. No se ejecutaron pruebas que alteren datos.
