# REVIEW — Corrección R-01: validación de campos numéricos ocultos

**Fecha:** 2026-11-17
**Rol:** REVIEWER
**Alcance:** revisión independiente de la corrección R-01 en `resources/js/Pages/Configuracion/Index.vue`.
**Base:** `PLAN-ETAPA-1-FRONTEND.md`, `BUILD-ETAPA-1-FRONTEND.md`, `REVIEW-ETAPA-1-FRONTEND.md`, `BUILD-CORRECCIONES-REVIEW-ETAPA-1.md`, `REVIEW-CORRECCIONES-ETAPA-1.md` y `BUILD-CORRECCION-R01-ETAPA-1.md`.
**Modo:** revisión y documentación exclusivas. No se modificó código, backend, API, rutas, migraciones, DB, `AGENTS.md` ni skills. No se instalaron dependencias ni se corrigieron hallazgos.
**Veredicto R-01:** **Aprobado con observaciones**.
**Estado de la Etapa 1:** **No cerrada**. Las pruebas manuales integradas 5.1–5.7 siguen pendientes.

---

## 1. Resumen ejecutivo

R-01 está correctamente resuelto a nivel estático y en el harness aislado:

- El formulario principal usa `novalidate` y valida explícitamente con `checkValidity()` antes del POST.
- Si hay un control inválido, identifica el primero, activa su pestaña mediante `data-tab`, espera `nextTick`, enfoca el control y llama a `reportValidity()`.
- El envío se corta con `return` antes de `form.transform(...).post(...)`.
- Los cinco campos a los que se agregó `min="0"` coinciden con el `min:0` del backend. El cero sigue siendo válido y los negativos se rechazan.
- Los `max` y `step` existentes no fueron eliminados ni relajados.
- No se encontraron caminos normales de envío del formulario principal que eviten el guard.
- El harness Chromium reprodujo los nueve casos con el resultado esperado.
- `npm run build` y `git diff --check` pasan.

Quedan dos observaciones relevantes:

1. **Observación media, preexistente:** varios `step` HTML son más estrictos que el contrato del backend. R-01 no los introdujo, pero el harness los trata como inválidos y el informe BUILD afirma que los siete campos están “alineados con el contrato”, lo cual es impreciso.
2. **Observación de proceso:** el harness es aislado y no monta Vue, Inertia ni el componente real. No reemplaza las pruebas integradas 5.1–5.7 ni la verificación visual de la burbuja nativa.

No se declara aprobada definitivamente la Etapa 1.

---

## 2. Veredicto por aspecto

| Aspecto | Veredicto | Resultado |
|---|---|---|
| Implementación de R-01 | **Aprobada** | `novalidate`, guard, `data-tab`, `nextTick`, foco y `reportValidity()` están implementados y conectados al submit principal. |
| Prevención del envío inválido | **Aprobada** | El guard retorna antes de `form.transform(...).post(...)`; el harness confirma que no se envía. |
| `min="0"` en los cinco campos | **Aprobado** | Coincide con `min:0` del backend; cero válido; negativos rechazados. |
| Conservación de `max` y `step` | **Aprobada** | No se eliminaron ni se relajaron; el diff no muestra sustracciones de esos atributos. |
| Nueve casos del harness | **Aprobados en aislamiento** | Ocho inválidos no envían, activan la pestaña y enfocan; el válido envía. |
| Regresiones estáticas | **Sin regresiones observadas** | Enter, botón, botones que no deben enviar, formularios independientes, pestañas, payload y errores backend revisados. |
| Integración real Vue/Inertia | **Pendiente** | No se levantó la aplicación con autenticación ni datos de prueba. |
| Etapa 1 | **No cerrada** | Pruebas manuales 5.1–5.7 pendientes. |

---

## 3. Revisión de la implementación

### 3.1 `novalidate` sólo en el formulario principal

En `resources/js/Pages/Configuracion/Index.vue:313`:

```html
<form v-show="tabsDelFormPrincipal.includes(tabActiva)" novalidate @submit.prevent="guardarConfiguracion">
```

- `novalidate` está sólo en el formulario principal.
- El formulario de `metodos_pago_pos` (`:606`) y el de Storefront (`:663`) no lo tienen y conservan su validación y su submit independiente.
- El formulario principal contiene las cuatro pestañas del form principal y el footer con el botón “Guardar Ajustes”.
- El formulario principal se oculta cuando `tabActiva` es `metodos_pago_pos` o `storefront`; en esos casos los formularios independientes quedan visibles y son los únicos que pueden enviarse.

### 3.2 Guard explícito antes del POST

En `Configuracion/Index.vue:183-223`:

```js
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

La secuencia es correcta:

1. `formulario.checkValidity()` evalúa todos los controles, incluidos los de pestañas ocultas por `v-show`.
2. `obtenerControlInvalido()` recorre `form.elements` y devuelve el primer control con `willValidate` y `checkValidity() === false`.
3. `mostrarControlInvalido()` obtiene la pestaña con `closest('[data-tab]')`.
4. Si la pestaña no es la activa, cambia `tabActiva`, espera `nextTick` y recién después enfoca y llama a `reportValidity()`.
5. Si la pestaña ya es la activa, enfoca y muestra el mensaje nativo directamente.
6. Si hay un control inválido, el `return` impide llegar a `form.transform(...).post(...)`.

No se duplican reglas de negocio en JavaScript: se usan los atributos HTML existentes y la API nativa del navegador.

### 3.3 Marcadores `data-tab`

Los cuatro contenedores del formulario principal tienen el marcador:

- `Configuracion/Index.vue:314` → `data-tab="general"`.
- `:356` → `data-tab="pos"`.
- `:395` → `data-tab="sistema"`.
- `:454` → `data-tab="tienda"`.

Los valores coinciden con `tabsDelFormPrincipal` y con los valores de `tabActiva`. Todos los controles con restricciones del formulario principal están dentro de alguno de esos cuatro contenedores. El footer sólo contiene el botón de submit y no tiene controles con restricciones.

### 3.4 Caminos de envío revisados

Se buscaron referencias a `guardarConfiguracion`, `.post(`, `.submit(`, `requestSubmit` y botones:

- `guardarConfiguracion` se define en `:210` y se usa sólo en el submit del formulario principal (`:313`).
- No hay llamadas a `form.submit()` ni a `requestSubmit()` dentro del componente.
- El formulario principal tiene un solo `type="submit"`: el botón del footer (`:568`).
- Los botones de navegación entre pestañas, “Recargos por Tarjeta”, editar/eliminar medios de pago, reset de colores y avanzado tienen `type="button"`.
- El formulario de `metodos_pago_pos` tiene su propio `@submit.prevent="guardarPmc"` y su propio submit.
- El formulario de Storefront tiene su propio `@submit.prevent="guardarStorefront"` y su propio submit.
- Los formularios de PMC y Storefront están fuera del formulario principal; no hay formularios anidados.

**Conclusión:** no se encontraron caminos normales de envío del formulario principal que eviten la validación explícita. Una llamada programática externa a `form.submit()` podría saltarse el evento `submit`, pero no existe en el componente y no es un flujo de usuario.

---

## 4. Validación de campos

### 4.1 Campos con `min="0"`

| Campo | Atributos HTML actuales | Regla backend | Resultado |
|---|---|---|---|
| `limite_fiado_defecto` | `type="number" step="0.01" min="0"` | `nullable\|numeric\|min:0` | Negativos rechazados; cero válido; `step` preexistente. |
| `costo_delivery_defecto` | `type="number" step="0.01" min="0"` | `nullable\|numeric\|min:0` | Negativos rechazados; cero válido; `step` preexistente. |
| `envio_precio_base` | `type="number" step="0.01" min="0"` | `nullable\|numeric\|min:0` | Negativos rechazados; cero válido; `step` preexistente. |
| `envio_precio_km` | `type="number" step="0.01" min="0"` | `nullable\|numeric\|min:0` | Negativos rechazados; cero válido; `step` preexistente. |
| `envio_radio_km` | `type="number" min="0"` + `step` 1 por defecto | `nullable\|numeric\|min:0` | Negativos rechazados; cero válido; `step` preexistente. |

Los cinco `min="0"` agregados son compatibles con el backend: el backend ya rechazaba negativos. No se introdujo una restricción nueva de negocio; se alineó el atributo HTML con una regla existente.

### 4.2 Campos con `max` y `step` existentes

| Campo | HTML actual | Backend | Coincidencia |
|---|---|---|---|
| `mora_dias_gracia` | `min="0" max="365"` + `step` 1 por defecto | `integer\|min:0\|max:365` | `min`, `max` y `step` coherentes. |
| `mora_tasa_interes` | `step="0.1" min="0" max="100"` | `numeric\|min:0\|max:100` | `min` y `max` coherentes; `step` más estricto que backend. |

No se eliminaron `max` ni `step`. El diff acumulado no muestra sustracciones de esos atributos; las líneas de los campos conservan los valores existentes y sólo agregan `min="0"` donde correspondía.

### 4.3 Observación: `step` preexistente vs. backend

Hay una diferencia entre la validación HTML y el contrato del backend que **no fue introducida por R-01**, pero que el harness y el informe BUILD tratan como si estuviera alineada:

| Campo | Valor backend-válido | Resultado HTML | Motivo |
|---|---:|---|---|
| `mora_tasa_interes` | `5.25` | Inválido | `step="0.1"` |
| `limite_fiado_defecto` | `10.005` | Inválido | `step="0.01"` |
| `costo_delivery_defecto` | `10.005` | Inválido | `step="0.01"` |
| `envio_precio_base` | `10.005` | Inválido | `step="0.01"` |
| `envio_precio_km` | `10.005` | Inválido | `step="0.01"` |
| `envio_radio_km` | `5.5` | Inválido | `step` 1 por defecto |

El backend declara esos campos como `numeric` sin restricción de decimales. Por lo tanto, el HTML es más estricto que el backend. R-01 hace visible el bloqueo, pero no resuelve esta diferencia de contrato.

El informe `BUILD-CORRECCION-R01-ETAPA-1.md` afirma en la sección 9 que “los siete campos numéricos tienen `min`, `max` o `step` alineados con el contrato”. Esa afirmación es imprecisa: el `min="0"` y los `max` de mora están alineados; varios `step` preexistentes no lo están. La formulación correcta sería: “se agregó `min="0"` alineado con el backend; los `step` preexistentes se conservaron sin cambios”.

**Impacto:** un usuario no puede guardar, por ejemplo, un interés de mora de `5.25%` o un radio de `5.5 km`, aunque el backend los aceptaría. No es una regresión de R-01, pero es una decisión de producto pendiente. Recomendación: definir en una corrección separada si se relajan los `step` o si se documenta que la UI limita la precisión. No corresponde corregirlo dentro de esta revisión.

---

## 5. Casos de prueba reproducidos

Se ejecutó el harness `/tmp/opencode/r01-validation.html` con Chromium headless. La salida se guardó en `/tmp/opencode/r01-review-out.html`.

| Caso | Pestaña inicial | Pestaña final | Control enfocado | POST | Válido | Pestaña visible |
|---|---|---|---|---|---:|---:|
| `mora_dias_gracia=366` | POS | Sistema | `mora_dias_gracia` | No | No | Sí |
| `envio_precio_base=10.005` | General | Tienda | `envio_precio_base` | No | No | Sí |
| `mora_tasa_interes=5.25` | Tienda | Sistema | `mora_tasa_interes` | No | No | Sí |
| `envio_radio_km=5.5` | Sistema | Tienda | `envio_radio_km` | No | No | Sí |
| `mora_dias_gracia=366` | Sistema | Sistema | `mora_dias_gracia` | No | No | Sí |
| `limite_fiado_defecto=-1` | POS | Sistema | `limite_fiado_defecto` | No | No | Sí |
| `envio_precio_base=-1` | General | Tienda | `envio_precio_base` | No | No | Sí |
| `envio_radio_km=-1` | Sistema | Tienda | `envio_radio_km` | No | No | Sí |
| Valores válidos, incluido cero | POS | POS | — | Sí | Sí | Sí |

Resultados:

- Los ocho casos inválidos no envían el formulario.
- En los casos con pestaña distinta, la pestaña correcta queda activa y visible.
- En el caso de la misma pestaña, no hay cambio de pestaña.
- El control inválido queda enfocado.
- El caso válido, con `limite_fiado_defecto=0`, `costo_delivery_defecto=0`, `envio_radio_km=0` y los demás valores válidos, envía.
- El harness confirma que el guard corta antes del envío.

### 5.1 Limitaciones del harness

El harness es una aproximación, no una prueba integrada:

- Usa `requestSubmit()`; no simula un Enter real ni un clic real, aunque ambos terminan en el evento `submit` del formulario.
- Usa `queueMicrotask()` en lugar de `nextTick()` de Vue; reproduce la espera al DOM, pero no el ciclo exacto de Vue.
- Usa `style.display` directo en lugar de `v-show`.
- No monta el componente Vue, no usa `useForm`, no ejecuta `form.transform`, no pasa por Inertia y no prueba `onError`.
- No verifica visualmente la burbuja nativa en headless; sólo verifica pestaña visible, foco y ejecución de la misma lógica de `reportValidity()`.

Por lo tanto, el harness respalda la lógica, pero **no garantiza el comportamiento integrado con Vue e Inertia**.

---

## 6. Regresiones revisadas

### 6.1 Envío con Enter

El formulario principal es un `<form>` real con `@submit.prevent`. Presionar Enter en un campo de texto o numérico del formulario dispara el submit implícito, que pasa por el mismo evento `submit` que usa el guard. `event.currentTarget` es el formulario principal, por lo que `checkValidity()` evalúa el formulario correcto.

No se observa una regresión estática. La prueba real de Enter en la aplicación integrada sigue pendiente en 5.2 y 5.4.

### 6.2 Envío mediante botón

El botón “Guardar Ajustes” es `type="submit"` y está dentro del formulario principal (`:568`). Su clic dispara el submit y pasa por el guard. Si el formulario es inválido, no se envía; si es válido, se ejecuta el POST habitual.

### 6.3 Botones que no deben enviar

- Las pestañas General, POS, Sistema, Tienda, Medios de Pago y Storefront son `type="button"`.
- “Recargos por Tarjeta” es `type="button"`.
- Editar y eliminar de medios de pago son `type="button"`.
- Reset de colores y avanzado de Storefront son `type="button"`.
- El cancelar de edición de PMC es `type="button"`.

No se encontraron botones sin `type` dentro del formulario principal que pudieran enviarlo por accidente.

### 6.4 Formularios independientes

- `metodos_pago_pos` tiene su propio `<form @submit.prevent="guardarPmc">` y su propio submit.
- Storefront tiene su propio `<form @submit.prevent="guardarStorefront">` y su propio submit.
- Ambos están fuera del formulario principal y no tienen `novalidate`.
- El formulario principal se oculta cuando la pestaña activa es `metodos_pago_pos` o `storefront`; el footer con “Guardar Ajustes” también queda oculto.

No se observa una regresión en la separación de formularios.

### 6.5 Cambio entre pestañas

Los botones de pestaña tienen `type="button"` y sólo cambian `tabActiva`. No disparan submit. El guard no se ejecuta al cambiar de pestaña.

### 6.6 Campos deshabilitados o condicionales

- No hay inputs con restricciones deshabilitados de forma permanente.
- El único `disabled` relevante es el botón de submit mientras `form.processing` es verdadero.
- Los `v-if` del formulario principal corresponden a mensajes de error y estados, no a campos con restricciones.
- Los campos de viüMi y Mercado Pago están siempre renderizados; no hay un `v-if` que los saque del formulario.

No se encontraron controles inválidos inalcanzables por esta causa.

### 6.7 Errores del backend

`onError` no fue modificado por R-01. Sigue:

- Buscando la primera pestaña con error en `camposPorTab`.
- Activando esa pestaña.
- Mostrando el toast de error.

Los mensajes por campo agregados en Etapa 1 siguen presentes. `onError` no enfoca el campo con error; eso es preexistente y no forma parte de R-01. No se observa una regresión.

### 6.8 Payload

`form.transform` conserva `...data` y las adaptaciones de `permitir_stock_negativo`, `ticket_digital_auto_email` y `acepta_efectivo`. R-01 sólo agrega el guard anterior; no cambia el payload.

### 6.9 Mantenimiento de `data-tab`

La solución depende de que todo control con restricciones del formulario principal esté dentro de un contenedor con `data-tab`. Hoy se cumple. Si en el futuro se agrega una pestaña nueva o un control con restricciones fuera de los cuatro contenedores, el guard podría enfocar un control sin cambiar de pestaña. Es una observación de mantenibilidad, no un defecto actual.

---

## 7. Verificaciones técnicas ejecutadas

| Verificación | Comando / método | Resultado |
|---|---|---|
| Build de producción | `npm run build` | OK, 951 módulos, 4.55 s. |
| Whitespace | `git diff --check` | OK, sin errores. |
| Diff del componente | `git diff -- resources/js/Pages/Configuracion/Index.vue` | Revisado; no hay eliminación de `max`/`step`; se agregaron los cinco `min="0"`; `novalidate` sólo en el formulario principal. |
| Búsqueda de `required` | `rg -n "required" resources/js/Pages/Configuracion/Index.vue` | Sin coincidencias. |
| Búsqueda de `novalidate` | `rg -n "novalidate" ...` | Sólo el formulario principal. |
| Búsqueda de `data-tab` | `rg -n "data-tab" ...` | General, POS, Sistema y Tienda. |
| Búsqueda de envíos alternativos | `rg -n "guardarConfiguracion|\\.post\\(|\\.submit\\(|requestSubmit|type=\\"submit\\"" ...` | Sólo el formulario principal usa `guardarConfiguracion`; PMC y Storefront tienen sus propios handlers. |
| Harness Chromium | `/tmp/opencode/r01-validation.html` | 9 casos con resultado esperado. |
| Suite frontend | `package.json` | No hay Vitest, Playwright, Cypress ni Jest; sólo `dev` y `build`. |

El working tree contiene cambios preexistentes ajenos a esta revisión. No se modificaron ni se revirtieron. La revisión sólo creó este informe.

---

## 8. Pruebas integradas pendientes

No se pudo levantar la aplicación con autenticación y datos de prueba. Las pruebas manuales 5.1–5.7 siguen pendientes:

- **5.1** Nombres con HTML y atributos de evento en confirmaciones.
- **5.2** Guardado de Configuración con valores válidos, con botón y con Enter.
- **5.3** Rechazo de Configuración por validación del backend. Usar la vía primaria de `transferencia_cbu` con letras; la alternativa de `mora_dias_gracia = 366` ya no llega al backend por el `max` nativo.
- **5.4** Navegación entre pestañas sin envíos accidentales y Enter dentro de cada formulario.
- **5.5** Subida exitosa y fallida de comprobantes.
- **5.6** Apertura y cierre de todos los modales de Caja.
- **5.7** Navegación con teclado y visibilidad de los botones de cierre.

Para R-01 en particular, la prueba integrada debe confirmar:

1. Un valor inválido en una pestaña oculta cambia a la pestaña correcta.
2. El campo queda visible y enfocado.
3. La burbuja nativa se muestra en el navegador real.
4. No hay POST en Network.
5. Al corregir el valor, el POST se ejecuta y aparece el toast de éxito.
6. Los formularios de PMC y Storefront siguen enviándose de forma independiente.
7. Los errores 422 del backend siguen mostrando el mensaje por campo y cambiando de pestaña.

---

## 9. Restricciones y confirmación

| Restricción | Cumplimiento |
|---|---|
| No modificar código | Cumplido. |
| No modificar backend, API, rutas, migraciones ni DB | Cumplido. |
| No modificar `AGENTS.md` ni skills | Cumplido. |
| No instalar dependencias | Cumplido. |
| No tocar cambios preexistentes | Cumplido. |
| No corregir hallazgos durante la revisión | Cumplido. |
| Crear `docs/informes/REVIEW-CORRECCION-R01-ETAPA-1.md` | Cumplido. |
| No declarar cerrada la Etapa 1 | Cumplido. |

El único archivo creado por esta revisión es este informe. No se modificó ningún archivo de código ni de configuración.

---

## 10. Conclusión

R-01 está **aprobado con observaciones**:

- La implementación es correcta y acotada.
- La validación HTML sigue funcionando, pero ahora es coordinada para que el error no quede oculto.
- Los `min="0"` agregados coinciden con el backend y no introducen restricciones nuevas incompatibles.
- Los `max` y `step` existentes se conservan.
- El harness aislado reproduce los nueve casos esperados.
- No se encontraron regresiones estáticas en Enter, botón, botones que no deben enviar, formularios independientes, pestañas, campos deshabilitados/condicionales, errores backend ni payload.

Las observaciones son:

1. **`step` preexistente vs. backend:** varios valores que el backend acepta son rechazados por el HTML. R-01 no lo introduce, pero el informe BUILD no debería afirmar que todos los límites están alineados. Es una decisión de producto pendiente.
2. **Pruebas integradas:** el harness no monta Vue/Inertia ni verifica visualmente la burbuja nativa. Las pruebas 5.1–5.7 siguen pendientes.

**La Etapa 1 no se declara cerrada.** R-01 queda aprobado para continuar, pero el cierre definitivo requiere las pruebas manuales integradas y la definición sobre la precisión de los `step`.
