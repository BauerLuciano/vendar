# BUILD Etapa 1 — Frontend (C-01, C-03, C-02, A-01)

**Fecha:** 2026-11-17  
**Alcance:** sólo frontend de los cuatro hallazgos aprobados en `PLAN-ETAPA-1-FRONTEND.md`.  
**Orden ejecutado:** C-01 → C-03 → C-02 → A-01.  
**Estado:** implementado y verificado estáticamente. Las pruebas de runtime que requieren aplicación, base de datos y navegador automatizado quedan pendientes para REVIEWER/QA. **No se declara aprobación definitiva.**

---

## 1. Resumen ejecutivo

Se implementaron los cuatro hallazgos aprobados:

- **C-01:** se eliminó la interpolación de datos dentro de los `html` de SweetAlert2 en los cinco archivos afectados. Los valores se cargan en `didOpen` con `textContent`; no se usa `innerHTML` con datos.
- **C-03:** se corrigió la referencia `pedidos` por `props.pedidos`, se separó la subida de la actualización visual y se agregó el fallback `router.reload({ only: ['pedidos'] })`. Ya no se muestra un falso error después de una subida exitosa.
- **C-02:** el formulario principal ahora envuelve General, POS, Sistema y Tienda Online y Pagos. `storefront` y `metodos_pago_pos` conservan sus formularios propios. Se agregaron errores por campo, `onError`, toast genérico y cambio automático a la primera pestaña con error.
- **A-01:** se reemplazaron los iconos Remix por SVG inline, se agregó semántica de diálogo, Escape con cierre del modal superior, confirmación al descartar formularios modificados, clic fuera selectivo y retorno de foco al disparador.

El build de producción pasa. Las verificaciones estáticas del plan pasan. Se ejecutó además un smoke test aislado en Chromium para el patrón `textContent` y la validación de URL de C-01. No se inició la aplicación ni se ejecutaron las pruebas manuales con datos reales.

---

## 2. Archivos creados y modificados

### 2.1 Archivos creados

| Archivo | Descripción |
|---|---|
| `docs/informes/BUILD-ETAPA-1-FRONTEND.md` | Este informe. |
| `/tmp/opencode/c01-textcontent-smoke.html` | Artefacto temporal de evidencia del smoke test de C-01. No forma parte del repositorio. |
| `/tmp/opencode/svg-sprite-smoke.html` | Artefacto temporal de evidencia del smoke test del sprite SVG de A-01. No forma parte del repositorio. |

### 2.2 Archivos modificados

| Archivo | Hallazgo | +/− |
|---|---|---:|
| `resources/js/Pages/Transferencias/Index.vue` | C-01 | +26 / −6 |
| `resources/js/Pages/Pos/Terminal.vue` | C-01 | +9 / −3 |
| `resources/js/Pages/Ingresos/Index.vue` | C-01 | +24 / −9 |
| `resources/js/Pages/Productos/Componentes/ModalProducto.vue` | C-01 | +76 / −10 |
| `resources/js/Pages/Consumidores/Index.vue` | C-01 | +48 / −15 |
| `resources/js/Pages/Cliente/Panel.vue` | C-03 | +25 / −7 |
| `resources/js/Pages/Configuracion/Index.vue` | C-02 | +80 / −14 |
| `resources/js/Pages/CajaDiaria/Index.vue` | A-01 | +361 / −84 |
| `resources/js/Components/Header.vue` | A-01 | +8 / −2 |
| `resources/js/Pages/Auth/ElegirSucursal.vue` | A-01 | +14 / −3 |

No se crearon archivos de código nuevos. No se modificaron dependencias, backend, rutas, migraciones ni base de datos por este BUILD.

---

## 3. Detalle por hallazgo

### 3.1 C-01 — HTML interpolado en SweetAlert2

#### Implementación

| Archivo | Cambio |
|---|---|
| `Transferencias/Index.vue` | Tres confirmaciones con HTML estático e IDs únicos. `didOpen` completa cantidad, producto, origen y destino con `textContent` mediante `setSwalText`. |
| `Pos/Terminal.vue` | Modal de cantidad por kg con IDs `swal-peso-producto` y `swal-peso-disponible`. `didOpen` completa nombre y stock con `textContent` y mantiene el foco en el input. |
| `Ingresos/Index.vue` | Lista de aumentos con `<ul id="swal-aumentos-list">` vacío. `didOpen` crea los `<li>` con `createElement`, `textContent` y `DocumentFragment`. |
| `Productos/Componentes/ModalProducto.vue` | Dos modales con IDs. El modal de producto de API valida la URL de imagen con `esUrlSegura`: sólo permite rutas relativas del mismo origen, `http:` y `https:`; rechaza `javascript:`, `data:`, URLs protocol-relative, rutas con backslash y valores inválidos. Si la imagen falla, muestra el placeholder. |
| `Consumidores/Index.vue` | Título, nombre y tabla de duplicados se construyen con `textContent` y nodos DOM. No se interpola ningún dato en el `html`. |

#### Decisiones técnicas

- No se creó un helper compartido: cada archivo resuelve su caso con funciones locales, tal como pedía el plan.
- Se conservaron títulos, iconos, botones, colores, `reverseButtons` y las llamadas `router.post`.
- En `ModalProducto` se agregaron listeners `load` y `error` antes de asignar `src`, para que el placeholder funcione incluso si la imagen falla rápido.
- No se reemplazó SweetAlert2 ni se agregaron dependencias.

#### Verificación estática

- 8 bloques `html` en los cinco archivos.
- 0 interpolaciones `${}` dentro de esos bloques.
- 0 usos de `innerHTML` en los cinco archivos.
- `ModalProducto`: `esUrlSegura` presente, sin `onerror=` inline, protocolos permitidos sólo `http:`/`https:` y rutas relativas del mismo origen.
- Smoke test aislado en Chromium: `xss=0`, `imgSrc` vacío para `javascript:alert(1)`, payloads mostrados como texto literal y matriz de URLs con `javascript:`, `data:`, protocol-relative y backslash rechazadas.

#### Pendiente de runtime

- Probar en la aplicación real con nombres que contengan `<b>`, `<img onerror=...>`, comillas, `&` y `<script>`.
- Probar la imagen externa inválida en el modal real de producto de API.

---

### 3.2 C-03 — Comprobante de tienda

#### Implementación

En `Cliente/Panel.vue`:

- Se reemplazó `pedidos.find(...)` por `props.pedidos.find(...)`.
- La subida con `axios.post` quedó en su propio `try/catch/finally`.
- La actualización visual quedó en un segundo `try/catch`, fuera del catch de subida.
- Si el pedido existe en `props.pedidos`, se actualiza `pedido.comprobante_transferencia_url` en forma local.
- Si no existe, se ejecuta `router.reload({ only: ['pedidos'] })`.
- Si la actualización visual falla, se muestra una advertencia distinta: “El comprobante se subió, pero actualizá la página para verlo.”
- El toast de éxito se muestra una sola vez, después de la actualización visual.
- El `finally` limpia `subiendoComprobante` y `comprobanteFile`.
- Se limpia también el valor del `<input type="file">` para permitir volver a subir el mismo archivo.

#### Decisiones técnicas

- No se tocó la ruta `/api/pedidos/{id}/comprobante`, el `FormData`, el nombre del campo `comprobante` ni el almacenamiento.
- No se cambió el template de la card: ya usa `pedido.comprobante_transferencia_url`.
- No se agregó un estado global nuevo; la actualización visual sigue el patrón existente de mutación de props.

#### Verificación estática

- `props.pedidos.find` presente.
- No queda `pedidos.find` sin `props.`.
- `router.reload({ only: ['pedidos'] })` presente.
- `finally` limpia `subiendoComprobante` y `comprobanteFile`.
- Reset del input presente.
- Un solo camino de toast de éxito y un camino separado de advertencia.
- El catch de subida no envuelve la actualización de `props.pedidos`.

#### Pendiente de runtime

- Subida exitosa con JPG/PNG/PDF válido menor a 5 MB.
- Subida fallida por extensión o tamaño.
- Reintento con el mismo archivo.
- Verificar que la card cambia sin recargar y que no aparece un toast de error falso.

---

### 3.3 C-02 — Configuración general

#### Implementación

En `Configuracion/Index.vue`:

- `tabsDelFormPrincipal = ['general', 'pos', 'sistema', 'tienda']`.
- El `<form>` principal ahora envuelve las pestañas General, POS, Sistema y Tienda Online y Pagos.
- El footer “Guardar Ajustes” quedó dentro del formulario principal y sólo se muestra en esas cuatro pestañas.
- `metodos_pago_pos` y `storefront` quedaron fuera del formulario principal, cada uno con su formulario propio.
- Los tabs y el botón “Recargos por Tarjeta” tienen `type="button"`.
- Se agregó `camposPorTab` con 27 campos.
- Se agregó un mensaje `form.errors.<campo>` debajo de cada campo del formulario principal, incluido `logo`.
- Se agregó `onError` a `form.transform(...).post(...)`:
  - detecta la primera pestaña con error según el orden del mapa;
  - cambia `tabActiva` a esa pestaña;
  - muestra el toast “No se pudieron guardar los ajustes. Revisá los campos marcados.”;
  - no muestra el error crudo del backend en el toast.

#### Decisiones técnicas

- Se respetó la precisión vinculante del plan: `tienda` (“Tienda Online y Pagos”) sigue dentro del formulario principal porque se guarda con `guardarConfiguracion`. La pestaña que se mantiene separada es `storefront`.
- No se modificó `form.transform`.
- No se modificaron nombres de campos, valores enviados, `preserveScroll` ni `form.processing`.
- No se modificaron `storefrontForm`, `pmcForm`, `guardarStorefront` ni `guardarPmc`.

#### Verificación estática

- 3 formularios abiertos y 3 cerrados; sin anidamiento.
- 6 tabs con `type="button"`; 0 tabs sin `type="button"`.
- 27 campos en el mapa y 27 mensajes de error; sin faltantes ni sobrantes.
- `onError`, toast genérico y cambio de pestaña presentes.
- `form.transform` y `preserveScroll` presentes.
- Footer dentro del formulario principal.
- `metodos_pago_pos` y `storefront` fuera del formulario principal.

#### Pendiente de runtime

- Guardar con botón y con Enter.
- Rechazo 422 con `transferencia_cbu` inválido.
- Verificar cambio automático a la pestaña del error.
- Verificar que los tabs no disparan POST.
- Verificar que `storefront` y `metodos_pago_pos` guardan con sus propios formularios.
- Comparar el payload real contra el anterior.

---

### 3.4 A-01 — Iconos y cierres

#### Implementación

En `CajaDiaria/Index.vue`:

- Se agregó un sprite SVG local con `<symbol>` y `<use>`.
- Se agregó la clase `.caja-icon` y `.caja-icon--solid` para conservar tamaños por `1em`.
- Se reemplazaron todos los iconos Remix por SVG inline o por `<use>` del sprite local.
- Se reemplazó el icono dinámico de diferencia por `<use :href="...">`.
- Se pasó `confirmButtonText` de impresión a texto plano, sin `<i>`.
- Los cinco modales tienen `role="dialog"`, `aria-modal="true"` y `aria-labelledby` con IDs existentes.
- Los cinco botones de cierre tienen `aria-label`, `title`, foco visible y `type="button"`.
- Escape cierra sólo el modal superior y respeta `Swal.isVisible()` para no chocar con confirmaciones de SweetAlert2.
- Los formularios de Arqueo y Movimiento Manual guardan snapshots y piden confirmación si fueron modificados.
- El clic fuera sólo cierra los modales de lectura: Visor de Cajas, detalle global y detalle de historial. No cierra Arqueo/Cierre ni Movimiento Manual.
- Se guarda el disparador en `focoStack` y se devuelve el foco al cerrar.
- Al abrir Movimiento Manual se resetea `formGasto` y se guarda el snapshot.

En `Header.vue` y `Auth/ElegirSucursal.vue`:

- Se reemplazaron los iconos Remix por SVG inline con `aria-hidden="true"` y `focusable="false"`.

#### Decisiones técnicas

- No se migraron los modales de Caja a `Modal.vue`.
- No se agregó `remixicon` ni ninguna dependencia.
- No se implementó un focus trap completo: se dejó para Etapa 3, tal como indica el plan.
- No se modificaron cálculos, endpoints ni reglas de caja; los cambios son de presentación, cierre y foco.
- Se mantuvieron los overlays manuales y sus tamaños.

#### Verificación estática

- 0 usos de `ri-` en Caja, Header y ElegirSucursal.
- 5 `role="dialog"`, 5 `aria-modal="true"` y 5 `aria-labelledby`; todos los IDs referenciados existen.
- 5 botones de cierre con `aria-label` y `title`.
- 3 `@click.self`: Visor de Cajas, detalle global y detalle de historial.
- Sin `@click.self` en Arqueo/Cierre ni Movimiento Manual.
- `Swal.isVisible()` presente en el manejador de Escape.
- `focoStack`, `guardarFoco`, `restaurarFoco` y `enfocarCierre` presentes.

#### Pendiente de runtime

- Abrir y cerrar los cinco modales con X, botón de footer, Escape y clic fuera.
- Verificar que Escape pide confirmación en formularios modificados.
- Verificar que cancelar la confirmación mantiene el modal y los datos.
- Verificar el retorno de foco al disparador.
- Recorrer con Tab y activar cierres con Enter/Espacio.
- Revisar visualmente Header y ElegirSucursal a 100% de zoom.

---

## 4. Hallazgos corregidos

| Hallazgo | Corrección |
|---|---|
| C-01 | Se eliminó la ejecución de HTML/JS inyectado en confirmaciones de SweetAlert2. |
| C-02 | El formulario principal ahora contiene todos los campos que guarda `guardarConfiguracion`; los errores ya no quedan ocultos en pestañas. |
| C-03 | Se eliminó el `ReferenceError` que convertía una subida exitosa en un falso error. |
| A-01 | Se eliminaron iconos Remix no disponibles, se agregaron nombres accesibles a los cierres y se corrigió el cierre indiscriminado por Escape. |

---

## 5. Verificaciones del plan (5.1 a 5.7)

> Las verificaciones de runtime requieren aplicación levantada, usuario autenticado, datos de prueba y navegador. En este BUILD no se inició la aplicación ni se modificó la base de datos. Se ejecutó la parte estática de cada verificación y se documenta lo pendiente.

| # | Verificación | Estado | Evidencia |
|---|---|---|---|
| 5.1 | Nombres con HTML y atributos de evento en confirmaciones | **Parcial** | 8 `html` sin `${}`, 0 `innerHTML`, smoke test aislado en Chromium con `xss=0`. Falta prueba integrada con datos reales. |
| 5.2 | Guardado de Configuración con valores válidos | **No ejecutada en runtime** | Estructura del formulario, `form.transform` y `preserveScroll` verificados estáticamente. |
| 5.3 | Rechazo de Configuración por validación del backend | **No ejecutada en runtime** | Mapa de 27 campos con 27 mensajes, `onError`, toast y cambio de pestaña verificados estáticamente. |
| 5.4 | Navegación entre pestañas sin envíos accidentales | **No ejecutada en runtime** | 3 formularios sin anidamiento, 6 tabs con `type="button"`, footer sólo en el formulario principal. |
| 5.5 | Subida exitosa y fallida de comprobantes | **No ejecutada en runtime** | `props.pedidos.find`, separación de catch, fallback `router.reload`, limpieza y toasts verificados estáticamente. |
| 5.6 | Apertura y cierre de todos los modales de Caja | **No ejecutada en runtime** | 5 diálogos, 5 cierres accesibles, 3 `click.self` selectivos, Escape con guardia de SweetAlert2 y snapshots verificados estáticamente. |
| 5.7 | Navegación con teclado y visibilidad de cierres | **Parcial** | 0 `ri-`, 5 cierres con `aria-label`/`title`, `aria-labelledby` resueltos. Falta recorrido real con Tab/Enter/Espacio y revisión visual. |

### 5.1 Detalle

**Ejecutado:**

- Búsqueda de `${` dentro de los bloques `html:` de los cinco archivos: 0.
- Búsqueda de `innerHTML` en los cinco archivos: 0.
- Smoke test aislado en Chromium con payloads:
  - `<b>Prueba</b>`
  - `<img src=x onerror="window.__xss=1">`
  - `"Comillas" & <script>window.__xss=2</script>`
- Resultado: `xss=0`, payloads escapados y visibles como texto literal.
- Validación de URL de imagen: `javascript:alert(1)` rechazada; `imgSrc` vacío. La matriz adicional rechazó `javascript:`, `data:`, `//evil.example/x.png` y `/\\evil.example/x.png`; aceptó `/storage/producto.png` y `https://cdn.example/producto.png`.

**Pendiente:**

- Repetir con datos reales en Consumidores, ModalProducto, Ingresos, POS y Transferencias.
- Verificar que no hay errores de consola en la aplicación real.

### 5.2 Detalle

**Ejecutado estáticamente:**

- El formulario principal contiene General, POS, Sistema y Tienda.
- El botón de guardado es `type="submit"` y el footer está dentro del formulario.
- `form.transform` conserva los mismos tres ajustes de payload.
- `preserveScroll: true` se mantiene.

**Pendiente:**

- Guardar sin cambios con botón y con Enter.
- Cambiar un valor seguro y confirmar persistencia.
- Verificar un solo POST y un solo toast de éxito.

### 5.3 Detalle

**Ejecutado estáticamente:**

- `onError` detecta la primera pestaña con error.
- `tabActiva` cambia a esa pestaña.
- Toast genérico sin error crudo del backend.
- Los 27 campos del mapa tienen su mensaje `form.errors.<campo>`.

**Pendiente:**

- Enviar `transferencia_cbu` con letras y verificar 422, error por campo, cambio de pestaña y ausencia de toast de éxito.
- Probar también un error en una pestaña del formulario principal.

### 5.4 Detalle

**Ejecutado estáticamente:**

- 3 formularios: principal, `pmcForm` y `storefrontForm`.
- Sin formularios anidados.
- 6 tabs con `type="button"`; 0 tabs sin `type="button"`.
- El footer “Guardar Ajustes” sólo se muestra en `general`, `pos`, `sistema` y `tienda`.

**Pendiente:**

- Hacer clic en todas las pestañas y revisar Network.
- Probar Enter en cada uno de los tres formularios.
- Verificar que no aparece el footer en `metodos_pago_pos` ni en `storefront`.

### 5.5 Detalle

**Ejecutado estáticamente:**

- `props.pedidos.find` presente; no queda `pedidos.find` sin `props.`.
- La subida y la actualización visual están en bloques `try/catch` separados.
- Fallback `router.reload({ only: ['pedidos'] })`.
- `finally` limpia `subiendoComprobante` y `comprobanteFile`.
- Reset del input para reintentar el mismo archivo.
- Un toast de éxito y una advertencia distinta si falla la actualización visual.

**Pendiente:**

- Subida válida real.
- Subida inválida real.
- Reintento con el mismo archivo.
- Verificar que la card se actualiza sin recargar y que no aparece un error falso.

### 5.6 Detalle

**Ejecutado estáticamente:**

- 5 modales con `role="dialog"`, `aria-modal="true"` y `aria-labelledby`.
- 5 cierres con `aria-label` y `title`.
- Escape cierra sólo el modal superior.
- `Swal.isVisible()` evita conflicto con confirmaciones de SweetAlert2.
- `@click.self` sólo en Visor de Cajas, detalle global y detalle de historial.
- Snapshots `cierreSnapshot`/`gastoSnapshot` y confirmación al descartar.

**Pendiente:**

- Abrir y cerrar cada modal con X, footer, Escape y clic fuera.
- Modificar un campo de Arqueo y Movimiento Manual antes de cerrar.
- Verificar que cancelar la confirmación mantiene el modal abierto con los datos.
- Verificar el retorno de foco al disparador.

### 5.7 Detalle

**Ejecutado estáticamente:**

- 0 usos de `ri-` en Caja, Header y ElegirSucursal.
- 5 cierres con nombre accesible.
- `aria-labelledby` resueltos.
- Iconos de Header y ElegirSucursal reemplazados por SVG inline.
- Smoke test aislado de sprite SVG en Chromium: un `<use>` que referencia un `<symbol>` dentro de un `<svg class="hidden">` renderiza un `bbox` de 16×16.

**Pendiente:**

- Recorrer con Tab hasta cada cierre.
- Activar con Enter y con Espacio.
- Revisar el anillo de foco.
- Revisar el árbol de accesibilidad.
- Revisar visualmente Header y ElegirSucursal a 100% de zoom.

---

## 6. Pruebas ejecutadas

| Prueba | Comando / método | Resultado |
|---|---|---|
| Build de producción | `npm run build` | **OK**. 954 módulos transformados; build final en 5,28 s; sin errores. |
| Baseline de build | `npm run build` antes de los cambios | **OK** (según línea base registrada al inicio del BUILD). |
| Verificación de whitespace | `git diff --check -- <archivos>` | **OK**, sin errores. |
| C-01 estático | Script Python sobre los 5 archivos | **OK**: 8 `html`, 0 `${}`, 0 `innerHTML`. |
| C-02 estático | Script Python sobre Configuración | **OK**: 3 forms, 6 tabs `type="button"`, 27/27 campos con error. |
| C-03 estático | Búsquedas sobre Panel.vue | **OK**: `props.pedidos.find`, reload, limpieza y toasts separados. |
| A-01 estático | Búsquedas y parser sobre Caja/Header/ElegirSucursal | **OK**: 0 `ri-`, 5 diálogos, 5 cierres accesibles, 3 `click.self`. |
| C-01 smoke test | Chromium headless sobre HTML aislado | **OK**: `xss=0`, payloads literales, `javascript:`/`data:`/protocol-relative/backslash rechazados, relativas del mismo origen y HTTPS aceptados. |
| A-01 sprite SVG | Chromium headless sobre HTML aislado | **OK**: `<use>` desde sprite oculto renderiza `bbox` 16×16. |
| Dependencias | `git diff -- package.json package-lock.json composer.json composer.lock` | **OK**: 0 líneas; no se agregaron dependencias. |
| Suite frontend | `package.json` no define script de test | **No disponible**. No hay runner frontend configurado. |
| Pruebas manuales de la aplicación | No se inició la aplicación | **No ejecutadas**. Requieren entorno de pruebas, usuario y datos. |

### 6.1 Limitaciones de las pruebas

- No se levantó Laravel ni la base de datos.
- No se usó un navegador automatizado contra la aplicación real.
- No se hicieron capturas visuales antes/después.
- No se probaron flujos con datos reales de clientes, productos, pedidos ni cajas.
- El smoke test de C-01 valida el patrón `textContent` y la validación de URL, no la integración completa de cada página.

---

## 7. Criterios de aceptación

| ID | Criterio | Estado |
|---|---|---|
| C-01 | Ningún `html` interpolado con datos | **Cumplido estáticamente**; prueba integrada pendiente. |
| C-01 | Confirmaciones conservan contenido y botones | **Cumplido por revisión de código**; revisión visual pendiente. |
| C-01 | Imagen externa no ejecuta `onerror` inline | **Cumplido estáticamente y en smoke test**; prueba integrada pendiente. |
| C-02 | Formulario principal envuelve General/POS/Sistema/Tienda | **Cumplido**. |
| C-02 | `storefront` y `metodos_pago_pos` conservan su form | **Cumplido**. |
| C-02 | Errores por campo y toast de rechazo | **Cumplido estáticamente**; runtime pendiente. |
| C-02 | Tabs no envían | **Cumplido estáticamente**; runtime pendiente. |
| C-02 | Payload sin cambios | **Cumplido**: `form.transform` y `preserveScroll` intactos. |
| C-03 | `props.pedidos` y actualización visual | **Cumplido estáticamente**; runtime pendiente. |
| C-03 | Sin falso error después de subir | **Cumplido estáticamente**; runtime pendiente. |
| C-03 | Reintento tras fallo | **Cumplido estáticamente** con reset del input; runtime pendiente. |
| A-01 | Sin `ri-*` en los tres archivos | **Cumplido**. |
| A-01 | Cierres visibles y accesibles | **Cumplido estáticamente**; teclado/árbol pendiente. |
| A-01 | Escape y clic fuera según política | **Cumplido estáticamente**; runtime pendiente. |
| A-01 | Formularios con datos no se cierran accidentalmente | **Cumplido estáticamente**; runtime pendiente. |
| A-01 | Foco inicial y retorno | **Cumplido estáticamente**; runtime pendiente. |
| Global | Sin cambios de backend/DB/dependencias | **Cumplido por este BUILD**. |
| Global | Build sin errores | **Cumplido**. |

---

## 8. Pendientes

1. Ejecutar en entorno de pruebas las verificaciones 5.1 a 5.7 completas.
2. Probar C-01 con payloads reales en cada confirmación.
3. Probar C-02 con guardado válido, rechazo 422 y navegación entre tabs.
4. Probar C-03 con subida válida, subida inválida y reintento.
5. Probar A-01 con teclado, foco, Escape, clic fuera y confirmación de descarte.
6. Revisar visualmente Caja, Configuración, Header y ElegirSucursal.
7. Evaluar en Etapa 3 el focus trap completo y una eventual migración de los modales de Caja a `Modal.vue`.

### Observaciones fuera de alcance

- `CajaDiaria/Index.vue` conserva una interpolación preexistente en un `html` de `registrarGastoManual` (usa `formatearMoneda` y un nombre de método fijo). No es parte de C-01 y no se modificó.
- `Consumidores/Index.vue` e `Ingresos/Index.vue` conservan `v-html` preexistentes para las etiquetas de paginación. No son datos de usuario y no forman parte de C-01.
- `Header.vue` conserva botones icon-only preexistentes (menú y ayuda) sin `aria-label`; no forman parte de A-01 y no se modificaron.
- El focus trap completo de los modales de Caja no se implementó en esta etapa; queda para Etapa 3.

---

## 9. Riesgos y regresiones posibles

| Riesgo | Dónde | Mitigación / estado |
|---|---|---|
| No se detectan regresiones visuales por falta de prueba manual | Caja, Configuración, Panel | Pendiente de revisión visual y QA. |
| El wrapper del formulario cambia el layout de Configuración | C-02 | Build OK; revisión visual pendiente. |
| El cambio de pestaña por error no se ve en un caso real | C-02 | Lógica estática verificada; prueba 5.3 pendiente. |
| La mutación de `props.pedidos` no actualiza la card en algún caso | C-03 | Fallback `router.reload({ only: ['pedidos'] })`; prueba 5.5 pendiente. |
| El foco no vuelve al disparador en algún navegador | A-01 | `focoStack` + `isConnected`; prueba 5.6/5.7 pendiente. |
| El sprite SVG oculto no renderiza en un navegador antiguo | A-01 | Patrón estándar `<symbol>`/`<use>`; revisión cross-browser pendiente. |
| Escape sigue cerrando un formulario con datos | A-01 | Snapshots y confirmación implementados; prueba 5.6 pendiente. |
| El modal permite tabular fuera del diálogo | A-01 | Focus trap diferido a Etapa 3; no es una regresión nueva. |
| Queda HTML interpolado preexistente en Caja | Caja | Fuera de C-01; usa valores formateados/fijos, no datos de usuario directos. |

---

## 10. Confirmación de restricciones

- **Este BUILD no modificó backend:** controladores, modelos, servicios, requests, policies, middleware, rutas, API ni migraciones.
- **Este BUILD no modificó la base de datos:** sin migraciones nuevas, sin cambios de esquema y sin escritura de datos.
- **Este BUILD no agregó dependencias:** `package.json`, `package-lock.json`, `composer.json` y `composer.lock` sin cambios.
- **Este BUILD no modificó roles, permisos ni multitenancy.**
- **Este BUILD no modificó reglas de negocio de precios, stock, ventas, caja, fiados, planes ni Mercado Pago.** En Caja sólo se cambió presentación, foco, cierre y confirmación de descarte.
- **Este BUILD no modificó `Modal.vue`.**
- **Este BUILD no modificó `AGENTS.md` ni skills.**
- **No se amplió el alcance:** sólo C-01, C-03, C-02 y A-01.
- El working tree contiene cambios preexistentes ajenos a esta tarea en backend, rutas, tests, `AdminGlobal/Facturacion.vue` y `docs/informes/BUILD-B3.md`. No fueron tocados por este BUILD.

---

## 11. Veredicto para REVIEWER

**Listo para revisión de código.** No se declara aprobación definitiva.

Solicito a REVIEWER:

1. Revisar el diff de los 10 archivos frontend listados.
2. Confirmar que no hay cambios de backend, DB, dependencias ni reglas de negocio.
3. Ejecutar las pruebas manuales 5.1 a 5.7 en entorno de pruebas.
4. Revisar visualmente Caja, Configuración, Header y ElegirSucursal.
5. Validar el comportamiento de foco, Escape, clic fuera y confirmación de descarte en Caja.

Si las pruebas manuales pasan, la etapa queda en condiciones de ser aprobada. Hasta entonces, el estado es **implementado y verificado estáticamente, con runtime pendiente**.
