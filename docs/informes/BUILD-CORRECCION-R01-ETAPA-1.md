# BUILD — Corrección R-01: Validación de campos numéricos ocultos

**Fecha:** 2026-11-17
**Rol:** BUILDER
**Alcance:** resolver exclusivamente R-01 en `resources/js/Pages/Configuracion/Index.vue`.
**Base:** `PLAN-ETAPA-1-FRONTEND.md`, `BUILD-ETAPA-1-FRONTEND.md`, `REVIEW-ETAPA-1-FRONTEND.md`, `BUILD-CORRECCIONES-REVIEW-ETAPA-1.md` y `REVIEW-CORRECCIONES-ETAPA-1.md`.
**Estado:** implementado y verificado de forma estática y aislada. Las pruebas integradas con la aplicación levantada siguen pendientes. **No se declara aprobada definitivamente la Etapa 1.**

---

## 1. Resumen ejecutivo

R-01 se producía porque el formulario principal de Configuración usa pestañas con `v-show`. Los controles de las pestañas ocultas siguen siendo parte del formulario y la validación nativa del navegador los valida aunque no sean visibles. Si un campo numérico tenía un valor inválido por `min`, `max` o `step`, el navegador bloqueaba el `submit` antes de que Inertia enviara el POST. El usuario no veía el error porque el campo estaba en una pestaña oculta.

La corrección hace que el formulario principal use `novalidate` y valide explícitamente con la API nativa (`checkValidity`/`reportValidity`) antes de enviar. Si hay un control inválido:

1. Se identifica el primer control inválido en orden de DOM.
2. Se busca su pestaña mediante el atributo `data-tab`.
3. Se activa esa pestaña.
4. Se espera `nextTick` para que el `v-show` actualice el DOM.
5. Se enfoca el control y se muestra el mensaje nativo con `reportValidity()`.

Si el formulario es válido, se ejecuta el mismo `form.transform(...).post(...)` de siempre. No se modificó el payload, el backend ni las reglas de negocio.

Además, se agregó `min="0"` a los cinco campos numéricos que ya tenían `min:0` en el backend, para que los valores negativos ocultos también se detecten antes del POST y no lleguen al backend.

---

## 2. Causa exacta

El formulario principal está en `Configuracion/Index.vue:313` y contiene cuatro pestañas:

- General.
- POS.
- Sistema.
- Tienda Online y Pagos.

Cada pestaña se muestra u oculta con `v-show`. A diferencia de `v-if`, `v-show` sólo aplica `display:none`; los controles siguen existiendo en el DOM y siguen siendo candidatos a validación nativa.

Los campos numéricos tienen restricciones HTML:

- `limite_fiado_defecto`: `step="0.01" min="0"`.
- `costo_delivery_defecto`: `step="0.01" min="0"`.
- `mora_dias_gracia`: `min="0" max="365"` y `step` 1 por defecto.
- `mora_tasa_interes`: `step="0.1" min="0" max="100"`.
- `envio_precio_base`: `step="0.01" min="0"`.
- `envio_precio_km`: `step="0.01" min="0"`.
- `envio_radio_km`: `min="0"` y `step` 1 por defecto.

Cuando el usuario enviaba desde otra pestaña, el navegador recorría todos los controles, encontraba el inválido oculto y bloqueaba el `submit`. Como el control estaba dentro de un contenedor `display:none`, no podía mostrar la burbuja de validación ni enfocarlo. El `onError` de Inertia tampoco se ejecutaba porque el POST nunca se realizaba.

---

## 3. Campos afectados

| Campo | Pestaña | Restricción nativa | Ejemplo inválido | Línea actual |
|---|---|---|---|---|
| `limite_fiado_defecto` | Sistema | `step="0.01" min="0"` | `-1` o `10.005` | `Configuracion/Index.vue:403` |
| `costo_delivery_defecto` | Sistema | `step="0.01" min="0"` | `-1` o `10.005` | `:425` |
| `mora_dias_gracia` | Sistema | `min="0" max="365"` + `step` 1 | `-1` o `366` | `:438` |
| `mora_tasa_interes` | Sistema | `step="0.1" min="0" max="100"` | `-1`, `5.25` o `101` | `:445` |
| `envio_precio_base` | Tienda | `step="0.01" min="0"` | `-1` o `10.005` | `:461` |
| `envio_precio_km` | Tienda | `step="0.01" min="0"` | `-1` o `10.005` | `:469` |
| `envio_radio_km` | Tienda | `min="0"` + `step` 1 | `-1` o `5.5` | `:475` |

No se modificaron las reglas backend. Se revisaron sólo para confirmar que los límites HTML siguen siendo coherentes con el contrato:

- `ConfiguracionController.php:149-153`: límites de fiado, delivery, mora y tasa.
- `ConfiguracionController.php:78-80`: límites de envío.

---

## 4. Solución implementada

### 4.1 `novalidate` en el formulario principal

En `Configuracion/Index.vue:313`:

```html
<form v-show="tabsDelFormPrincipal.includes(tabActiva)" novalidate @submit.prevent="guardarConfiguracion">
```

`novalidate` desactiva la validación automática del navegador **sólo en el formulario principal**. No elimina `min`, `max` ni `step`, y no cambia la validación de los formularios independientes de `metodos_pago_pos` ni de Storefront.

La validación se sigue haciendo con la API nativa, pero de forma controlada desde `guardarConfiguracion`.

También se agregó `min="0"` a `limite_fiado_defecto`, `costo_delivery_defecto`, `envio_precio_base`, `envio_precio_km` y `envio_radio_km`. No es una regla nueva: el backend ya tenía `min:0` para esos campos. Se alineó el atributo HTML para que los negativos también sean detectados por el guard y no lleguen al backend.

### 4.2 Marcadores `data-tab`

Se agregó `data-tab` a cada contenedor de pestaña del formulario principal:

```html
<div v-show="tabActiva === 'general'" data-tab="general" class="p-8">
<div v-show="tabActiva === 'pos'" data-tab="pos" class="p-8">
<div v-show="tabActiva === 'sistema'" data-tab="sistema" class="p-8">
<div v-show="tabActiva === 'tienda'" data-tab="tienda" class="p-8">
```

Esto permite ubicar la pestaña de cualquier control inválido con `control.closest('[data-tab]')`, sin duplicar un mapa de campos ni agregar `name` a los inputs.

### 4.3 Guard antes del POST

En `Configuracion/Index.vue:183-259`:

```js
// El formulario usa novalidate para poder activar la pestaña del control
// inválido antes de mostrar el mensaje nativo de un campo oculto por v-show.
const obtenerControlInvalido = (formulario) => {
    return Array.from(formulario.elements).find(
        (control) => control.willValidate
            && typeof control.checkValidity === 'function'
            && !control.checkValidity()
    );
};

const mostrarControlInvalido = (control) => {
    const tab = control.closest('[data-tab]')?.dataset.tab;

    const enfocar = () => {
        control.focus();
        control.reportValidity();
    };

    if (tab && tab !== tabActiva.value) {
        tabActiva.value = tab;
        nextTick(enfocar);
        return;
    }

    enfocar();
};

const guardarConfiguracion = (event) => {
    const formulario = event.currentTarget;

    if (formulario && !formulario.checkValidity()) {
        const controlInvalido = obtenerControlInvalido(formulario);

        if (controlInvalido) {
            mostrarControlInvalido(controlInvalido);
        } else {
            formulario.reportValidity();
        }

        return;
    }

    form.transform((data) => ({
        ...data,
        permitir_stock_negativo: data.permitir_stock_negativo ? '1' : '0',
        ticket_digital_auto_email: data.ticket_digital_auto_email ? '1' : '0',
        acepta_efectivo: data.acepta_efectivo ? 1 : 0
    })).post(route('configuracion.update'), {
        // ...
    });
};
```

El bloque `form.transform` y el `.post(...)` no cambiaron. El guard sólo decide si se llega a ejecutar.

### 4.4 Flujo resultante

1. El usuario hace clic en “Guardar Ajustes” o presiona Enter.
2. Como el formulario tiene `novalidate`, el `submit` llega a `guardarConfiguracion`.
3. `formulario.checkValidity()` valida todos los controles, incluidos los ocultos.
4. Si todo es válido, se ejecuta el POST habitual.
5. Si hay un inválido, se busca el primero, se activa su pestaña, se espera `nextTick` y se llama a `focus()` + `reportValidity()`.
6. No se ejecuta el POST. El usuario ve el campo y el mensaje nativo.

No se duplicaron reglas de negocio: los límites siguen viviendo en los atributos HTML y en el backend. JavaScript sólo coordina la pestaña, el foco y la presentación del error.

---

## 5. Archivos modificados

| Archivo | Cambio |
|---|---|
| `resources/js/Pages/Configuracion/Index.vue` | `novalidate` en el form principal, `data-tab` en las cuatro pestañas, guard de validez, helpers de enfoque y `min="0"` alineado con backend en cinco campos. |
| `docs/informes/BUILD-CORRECCION-R01-ETAPA-1.md` | Este informe. |

No se modificaron backend, controladores, modelos, servicios, rutas, payload, dependencias, otros módulos, `AGENTS.md` ni skills.

---

## 6. Verificaciones ejecutadas

### 6.1 Build y diff

| Verificación | Comando | Resultado |
|---|---|---|
| Build de producción | `npm run build` | OK, 951 módulos, 4.63 s, sin warnings. |
| Whitespace | `git diff --check` | OK, sin errores. |

### 6.2 Verificaciones estáticas

| Verificación | Resultado |
|---|---|
| `min`, `max` y `step` conservados | OK; no se eliminó ninguno de los campos de Sistema y Tienda. |
| `min="0"` alineado con backend | OK; se agregó a los cinco campos que ya tenían `min:0` en backend. |
| Sin `required` agregados | OK; `rg "required"` no devuelve coincidencias en el archivo. |
| `novalidate` sólo en el formulario principal | OK; los forms de PMC y Storefront no lo tienen. |
| Payload sin cambios | OK; `form.transform` conserva `...data` y las adaptaciones de booleanos. |
| Backend sin cambios | OK; no se tocaron controladores, modelos, servicios ni rutas. |
| Sin dependencias nuevas | OK; `package.json` y `package-lock.json` no cambiaron por esta corrección. |

### 6.3 Prueba aislada en Chromium

Se creó un harness en `/tmp/opencode/r01-validation.html` con la misma estructura de pestañas, los mismos atributos HTML y la misma lógica de guard, `nextTick` y `reportValidity`.

| Caso | Pestaña inicial | Pestaña final | Control enfocado | POST simulado | Válido | Pestaña visible |
|---|---|---|---:|---:|---:|---:|
| `mora_dias_gracia=366` | POS | Sistema | `mora_dias_gracia` | No | No | Sí |
| `envio_precio_base=10.005` | General | Tienda | `envio_precio_base` | No | No | Sí |
| `mora_tasa_interes=5.25` | Tienda | Sistema | `mora_tasa_interes` | No | No | Sí |
| `envio_radio_km=5.5` | Sistema | Tienda | `envio_radio_km` | No | No | Sí |
| `mora_dias_gracia=366` | Sistema | Sistema | `mora_dias_gracia` | No | No | Sí |
| `limite_fiado_defecto=-1` | POS | Sistema | `limite_fiado_defecto` | No | No | Sí |
| `envio_precio_base=-1` | General | Tienda | `envio_precio_base` | No | No | Sí |
| `envio_radio_km=-1` | Sistema | Tienda | `envio_radio_km` | No | No | Sí |
| Valores válidos, incluido `0` | POS | POS | — | Sí | Sí | Sí |

La prueba confirma que:

- El campo inválido oculto ya no bloquea silenciosamente.
- La pestaña correcta se activa antes de mostrar el error.
- El control queda enfocado.
- El POST no se ejecuta con datos inválidos.
- Los valores válidos siguen enviando.

No se pudo probar la burbuja nativa de forma visual en headless; sí se comprobó que `reportValidity()` se ejecuta sobre un control visible y enfocado. La comprobación visual queda para la prueba integrada.

---

## 7. Comportamiento logrado

| Escenario | Comportamiento |
|---|---|
| Guardar desde POS con inválido en Sistema | Cambia a Sistema, enfoca el campo y muestra el error; no envía. |
| Guardar desde Sistema con inválido en Tienda | Cambia a Tienda, enfoca el campo y muestra el error; no envía. |
| Guardar desde Tienda con inválido en Sistema | Cambia a Sistema, enfoca el campo y muestra el error; no envía. |
| Guardar desde la misma pestaña del inválido | No cambia de pestaña; enfoca y muestra el error. |
| Corregir el valor y volver a guardar | El formulario vuelve a ser válido y se envía. |
| `mora_dias_gracia=366` | No llega al backend; se muestra el límite nativo. |
| Valores negativos en campos con `min="0"` | No llegan al backend; se activa la pestaña, se enfoca el campo y se muestra el mínimo nativo. |
| Formularios PMC y Storefront | No cambian; conservan su validación y su submit independiente. |

La alternativa de la prueba 5.3 basada en `mora_dias_gracia = 366` ya no llega al backend, que es el comportamiento correcto: el valor está fuera de rango. La prueba 5.3 debe usar la vía primaria de `transferencia_cbu` con letras para provocar un 422 del backend.

---

## 8. Riesgos y limitaciones

- **Pruebas integradas pendientes:** no se levantó la aplicación con autenticación y datos de prueba. Las pruebas 5.1–5.7 siguen pendientes.
- **Sin suite frontend:** `package.json` sólo tiene `dev` y `build`; no hay Vitest/Playwright/Puppeteer configurados. La verificación se hizo con Chromium aislado.
- **Burbuja nativa:** el mensaje exacto depende del navegador y su idioma. La lógica garantiza que el control esté visible y enfocado antes de `reportValidity()`.
- **Orden de campos:** si hay varios inválidos, se resuelve el primero en orden de DOM. Después de corregirlo, el siguiente submit muestra el próximo. Es el comportamiento estándar de la validación nativa.
- **Mantenimiento de `data-tab`:** si se agrega una pestaña nueva al formulario principal, su contenedor debe incluir `data-tab` para que el guard pueda activarla.
- **`novalidate`:** la validación depende de que el JavaScript del componente se ejecute. Es consistente con una aplicación Inertia, donde la pantalla ya requiere JavaScript.
- **No se modificó el backend:** los límites siguen siendo los mismos; no se agregó una segunda fuente de verdad.

---

## 9. Cumplimiento de restricciones

| Restricción | Cumplimiento |
|---|---|
| No eliminar `min`, `max` ni `step` | Cumplido. |
| No permitir valores fuera de rango al backend | Cumplido: el guard corta antes del POST y los siete campos numéricos tienen `min`, `max` o `step` alineados con el contrato. |
| No modificar reglas de negocio | Cumplido. |
| No modificar backend, controladores, modelos, servicios ni rutas | Cumplido. |
| No modificar el payload de `guardarConfiguracion` | Cumplido. |
| No cambiar formularios de Storefront ni `metodos_pago_pos` | Cumplido; `novalidate` está sólo en el form principal. |
| No introducir dependencias | Cumplido. |
| No modificar otros módulos | Cumplido. |
| No iniciar Etapa 2 ni Etapa 3 | Cumplido. |
| No modificar `AGENTS.md` ni skills | Cumplido. |
| Solución sencilla y consistente | Cumplido: se usan atributos nativos y un guard pequeño. |
| No duplicar validaciones de negocio en JS | Cumplido: se usa `checkValidity()`/`reportValidity()` sobre los atributos existentes. |

---

## 10. Qué puede comprobarse visualmente

Con la aplicación levantada y un usuario de prueba:

1. Abrir **Configuración**.
2. Ir a **Reglas del Sistema** y escribir `366` en **Días de Gracia**.
3. Ir a **Punto de Venta (POS)** y hacer clic en **Guardar Ajustes**.
4. Verificar que:
   - La interfaz cambia a **Reglas del Sistema**.
   - El campo **Días de Gracia** queda visible y enfocado.
   - Aparece el mensaje nativo del navegador indicando el máximo de 365.
   - No hay POST en Network.
5. Corregir el valor a `30` y guardar.
6. Verificar que el POST se ejecuta y aparece el toast de éxito.
7. Repetir con:
   - `envio_precio_base = 10.005` en Tienda, guardando desde General.
   - `mora_tasa_interes = 5.25` en Sistema, guardando desde Tienda.
   - `envio_radio_km = 5.5` en Tienda, guardando desde Sistema.
   - `limite_fiado_defecto = -1` en Sistema, guardando desde POS.
   - `envio_precio_base = -1` en Tienda, guardando desde General.
8. Verificar que en cada caso se activa la pestaña correcta, el campo queda enfocado y no se envía el POST.
9. Verificar que los formularios **POS - Medios de Pago** y **Storefront** siguen funcionando de forma independiente.

---

## 11. Conclusión

R-01 quedó resuelto en `Configuracion/Index.vue` con un cambio acotado: el formulario principal valida de forma explícita, activa la pestaña del primer control inválido y muestra el error nativo antes de enviar. No se relajaron límites: se conservaron los existentes y se alineó `min="0"` con el backend. No se alteró el payload, ni el backend, ni los formularios independientes.

El cambio está listo para una revisión independiente del REVIEWER. **No se declara aprobada definitivamente la Etapa 1**: las pruebas integradas 5.1–5.7 siguen pendientes.
