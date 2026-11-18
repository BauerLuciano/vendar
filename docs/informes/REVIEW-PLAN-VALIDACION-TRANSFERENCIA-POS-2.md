# REVIEW 2 — PLAN VALIDACIÓN OBLIGATORIA DE TRANSFERENCIA (POS)

**Fecha:** 2026-11-18
**Modo:** REVIEWER 2 exclusivo. No se modificó código, tests, migraciones, base de datos, dependencias ni configuraciones. No se ejecutaron tests, builds ni migraciones. No se creó commit.
**Documentos revisados:**
- `docs/informes/PLAN-VALIDACION-TRANSFERENCIA-POS.md` (versión actualizada, 912 líneas).
- `docs/informes/REVIEW-PLAN-VALIDACION-TRANSFERENCIA-POS.md` (primera revisión).

**Base de código:** `f144e18` más los cambios preexistentes del working tree (B3/B3.2, `.opencode/`, `OPENCODE-SKILLS.md`). No se tocó ninguno.
**Entregable:** este documento. Es el único archivo nuevo de esta revisión.

---

## 1. Veredicto

**APROBADO CON OBSERVACIONES.**

El plan actualizado resolvió los hallazgos obligatorios de la primera revisión y mantiene la regla de negocio, el alcance y la estrategia técnica. La normalización con `unset` es exclusiva de Transferencia, la separación CBU/CVU preserva los datos históricos sin reinterpretarlos y la matriz de tests cubre creación, edición, persistencia, regresión y aislamiento multi-tenant.

No se autoriza tal cual por dos correcciones obligatorias y una de contrato de tests:

1. **N-01 (ALTA):** con `display_data.titular => null`, Laravel acumula `required` + `string` (mensaje por defecto en inglés) + `regex` bajo la misma clave. Eso rompe la comparación exacta de `mensajeErrorPmc()` y contradice el mensaje único de `PLAN:228` y el test 11 de `PLAN:688`. Se corrige con `bail` en `display_data.titular`.
2. **N-02 (MEDIA):** `editPmc()` agrega las cinco claves conocidas con `''` para **todos** los métodos. En métodos distintos de Transferencia no hay `unset`, por lo que editar una PMC con `display_data` nulo/parcial persistiría claves vacías y podría mostrar filas vacías en el modal manual, contradiciendo `PLAN:866`. Se corrige condicionando la normalización a Transferencia.
3. **N-03 (MEDIA):** la detección por texto exacto no tiene tests que fijen los textos del backend. Agregar aserciones de mensaje exacto para `display_data.titular` (`required`) y `display_data` (grupo).

Una vez incorporadas N-01, N-02 y N-03, el plan queda **autorizado técnicamente para pasar a BUILD**, sujeto a aprobación del usuario. No hace falta rediseñar la regla ni ampliar el alcance.

---

## 2. Estado de los hallazgos de la primera revisión

| # | Hallazgo | Estado | Evidencia en el plan actualizado |
|---|---|---|---|
| H-01 | `null` no evita filas vacías en POS | **Resuelto** | `PLAN:32`, `PLAN:190`, `PLAN:218-220`, `PLAN:310-318`, `PLAN:634`, test 20 (`PLAN:697`), riesgo (`PLAN:817`), criterio (`PLAN:857`). |
| H-02 | `mensajeResumenPmc` sin definir | **Resuelto** | Textos exactos y condiciones en `PLAN:459-509`; ubicación en `PLAN:507`; QA 1-3 y 17-18 (`PLAN:767-769`, `PLAN:783-784`). |
| H-03 | `cambiarMetodoPagoPmc()` podía perder `metodo_pago` | **Resuelto** | `PLAN:511-530` conserva `limpiarErrorPmc('metodo_pago')`; template (`PLAN:586`); QA 15 (`PLAN:781`); criterio (`PLAN:865`). |
| H-04 | Falta import de `Validator` y reglas completas | **Resuelto** | `PLAN:244-274` enumera todas las reglas; import en `PLAN:252`, `PLAN:336`; `PLAN:622-623`. |
| H-05 | La regla aplica a crear y editar | **Resuelto** | `PLAN:23`, `PLAN:192`, `PLAN:349`, `PLAN:636`, tests 21-23 (`PLAN:705-707`), criterios (`PLAN:852`, `PLAN:859`). |
| H-06 | Faltan tests de actualización y CBU/CVU | **Resuelto** | Tests 21-27 (`PLAN:701-711`); claves desconocidas test 40 (`PLAN:740`); nota F3/C-01 (`PLAN:748`). |
| H-07 | Faltan tests cross-tenant de PMC | **Resuelto** | Tests 37-39 (`PLAN:732-734`); `PLAN:761`; criterio (`PLAN:868`). |
| H-08 | Faltan regresiones de otros métodos y ausencia de errores | **Resuelto** | Tests 28-35 (`PLAN:713-725`); técnica de ausencia (`PLAN:750`); `PLAN:757-759`. |
| H-09 | Faltan opcionales y formatos en Transferencia | **Resuelto con salvedad N-01** | Tests 11-20 (`PLAN:688-697`), 40-44 (`PLAN:736-745`), caso H-09 (`PLAN:699`). El caso `null` del test 11 requiere N-01. |
| H-10 | Ambigüedad histórica CBU/CVU | **Resuelto** | `PLAN:351-359`, `PLAN:385`, QA 13 (`PLAN:779`), riesgo (`PLAN:816`), pendiente 11 (`PLAN:912`). |
| H-11 | `MpQrPanel.vue` no listado | **Resuelto** | Lista de no-touch (`PLAN:165`); riesgo (`PLAN:829`). |
| H-12 | Accesibilidad del error grupal | **Resuelto** | `role="group"` y `aria-describedby` (`PLAN:583`); QA 23 (`PLAN:789`). |
| H-13 | Precedente `node:test` mal descrito | **Resuelto** | `PLAN:765-766` aclara que no se extrae utilidad nueva y se cubre con build/QA. |
| H-14 | Mensajes `string` no personalizados | **Parcial — ver N-01** | `PLAN:347-348` los deja como opcionales; el caso `null` demuestra que el problema sí afecta el contrato de mensajes. |
| H-15 | Claves desconocidas en `display_data` | **Resuelto/documentado** | Test 40 (`PLAN:740`); riesgo (`PLAN:830`). |
| H-16 | El resumen no debe duplicar el error grupal | **Resuelto con matiz N-04** | Error grupal una sola vez junto al grupo (`PLAN:584`), no al pie (`PLAN:606`). Queda la duplicación textual del resumen en casos de un solo requisito (N-04). |
| H-17 | Cambio de resumen para Transferencia | **Resuelto** | `PLAN:492-496`, tabla `PLAN:593-606`, QA 17-18 (`PLAN:783-784`). |
| H-18 | Orden de inputs | **Resuelto** | Orden Alias, CBU, CVU, Banco, Titular (`PLAN:374`, `PLAN:549-563`). |
| H-19 | F7B queda parcial | **Resuelto** | `PLAN:832-837`, criterio (`PLAN:872`), pendiente 8 (`PLAN:908`). |
| H-20 | Informe de BUILD | **Resuelto** | `PLAN:154`, criterio (`PLAN:872`), pendiente 7 (`PLAN:908`). |

**Conclusión:** 19 hallazgos resueltos, 1 parcial (H-14) y 1 con matiz (H-16). Ninguno exige rediseñar la regla.

---

## 3. Nuevos hallazgos

### 3.1. ALTA / obligatorio antes de BUILD

#### N-01. `titular => null` rompe el contrato de mensaje único y la detección por texto exacto

**Evidencia del plan:**
- `PLAN:205`: `display_data.titular` en Transferencia usa `required|string|regex|max`.
- `PLAN:228`: el mensaje de `display_data.titular` es `Ingresá el nombre del titular.`.
- `PLAN:347-348`: `bail` se menciona sólo como opción para un caso “patológico” de 256+ espacios.
- Test 11 (`PLAN:688`): `titular => null` debe devolver “sólo error `display_data.titular`”.
- `PLAN:400-401`, `PLAN:462-470`, `PLAN:506`: la detección del resumen se hace comparando el texto exacto.

**Comportamiento real de Laravel verificado en `vendor/laravel/framework`:**
- `Arr::has()` considera presente una clave con valor `null`; por lo tanto `string` y `regex` se ejecutan.
- Sin `bail`, `required` no detiene las reglas siguientes (`hasNotFailedPreviousRuleIfPresenceRule` sólo aplica a `Unique`/`Exists`).
- Para `titular => null` se acumulan tres mensajes bajo la misma clave:
  1. `required`: `Ingresá el nombre del titular.`
  2. `string`: mensaje por defecto en inglés (`The display data.titular field must be a string.`).
  3. `regex`: `El nombre del titular solo puede contener letras.`
- `mensajeErrorPmc()` une los mensajes con espacio (`Index.vue:124-127`), así que la comparación `=== MENSAJE_TITULAR_PMC` falla.
- Con `titular => ''` o `'   '`, Laravel omite las reglas no implícitas y sólo devuelve `required`; el problema es específico de `null` explícito y de valores no string.

**Impacto:**
- El test 11 no puede afirmar un mensaje único si se implementa sin `bail`.
- El resumen específico no se muestra y el inline muestra un mensaje en inglés, contradiciendo `PLAN:228`.
- La comparación por texto exacto deja de ser confiable para esa clave.

**Corrección obligatoria:**
- Agregar `bail` como primera regla de `display_data.titular` en **ambas** ramas (Transferencia y otros métodos).
- Opcionalmente, agregar `display_data.titular.string` con un mensaje en español para payloads manipulados.
- Actualizar la nota `PLAN:347-348`: `bail` no es para el caso de 256 espacios (esas reglas se omiten), sino para evitar la acumulación de mensajes en `null`/tipos inválidos.
- Agregar un test que afirme que `titular => null` devuelve exactamente `Ingresá el nombre del titular.` y no `display_data.titular.string` ni `display_data.titular.regex`.

### 3.2. MEDIA

#### N-02. `editPmc()` normaliza claves para todos los métodos y puede persistir claves vacías fuera de Transferencia

**Evidencia del plan:**
- `PLAN:532-543`: `editPmc()` asigna siempre `alias`, `cbu`, `cvu`, `banco` y `titular` con `?? ''`, sin condicionar por método.
- `PLAN:139`: los otros métodos no se normalizan.
- `PLAN:866`: criterio “Los demás métodos de pago conservan su comportamiento, incluidos `display_data` ausente, `null` y cadenas de espacios”.
- `ConfirmarPagoModal.vue:142-146` y `:159-163` iteran todas las claves de `display_data`.
- `VentaController.php:230-241` y `:461-482` usan el flujo manual para cualquier PMC con `channel = MANUAL`.

**Impacto:**
- Editar una PMC de Efectivo, Cuenta Corriente u otro método con `display_data` nulo/parcial y guardar sin tocar los campos enviaría las cinco claves con `''`. Como el backend no hace `unset` para esos métodos, se persistirían claves vacías.
- El modal de pago manual podría mostrar filas vacías para esos métodos, y el dato almacenado cambiaría respecto del comportamiento actual.
- Es una contradicción entre el alcance declarado y el cambio de frontend.

**Corrección obligatoria:**
- Condicionar la normalización explícita a Transferencia:
  ```js
  pmcForm.display_data = pmc.metodo_pago === 'TRANSFERENCIA'
      ? { ...(pmc.display_data || {}), alias: ..., cbu: ..., cvu: ..., banco: ..., titular: ... }
      : { ...(pmc.display_data || {}) };
  ```
- Alternativa equivalente: agregar sólo las claves conocidas que ya existen en `pmc.display_data`, sin crear claves nuevas para otros métodos.
- Agregar QA/test: editar una PMC de Efectivo/Cuenta Corriente con `display_data` nulo o parcial y verificar que el payload no incorpora claves vacías.

#### N-03. Faltan tests que fijen los textos exactos que usa la comparación frontend

**Evidencia:**
- `PLAN:400-401` y `PLAN:506` dependen de que `MENSAJE_TITULAR_PMC` y `MENSAJE_GRUPO_PMC` coincidan exactamente con el backend.
- La matriz de `PLAN:674-745` verifica claves de error, pero no afirma los textos exactos del `required` de titular ni del error grupal.
- `assertSessionHasErrors` con un array asociativo permite afirmar el mensaje, pero el plan no lo exige.

**Impacto:**
- Un cambio de copy en el backend o en el frontend rompería el resumen específico sin que ningún test falle.
- Es el riesgo residual principal de la estrategia de comparación por texto.

**Corrección recomendada (obligatoria para el contrato):**
- Agregar aserciones exactas:
  ```php
  $response->assertSessionHasErrors([
      'display_data.titular' => 'Ingresá el nombre del titular.',
      'display_data' => 'Completá al menos uno de Alias, CBU o CVU.',
  ]);
  ```
- Agregar aserciones de ausencia con `assertSessionDoesntHaveErrors` o `session('errors')->has(...)` para los casos 33-35.
- Documentar en el informe de BUILD que los textos son un contrato compartido entre `ConfiguracionController` e `Index.vue`.

### 3.3. BAJA / observaciones

#### N-04. El resumen repite el texto inline en los casos de un solo requisito

**Evidencia:**
- `PLAN:508` afirma “No repite los errores inline”.
- `PLAN:476-490`: para “sólo titular” el resumen devuelve `Ingresá el nombre del titular.` y para “sólo grupo” devuelve `Completá al menos uno de Alias, CBU o CVU.`, exactamente los mismos textos que los errores inline (`PLAN:595-597`).
- `PLAN:606` repite la afirmación de no duplicación.

**Impacto:** el usuario ve la misma frase en el resumen y junto al campo. No es un bug funcional, pero la afirmación del plan es inexacta.

**Corrección:** usar un texto de resumen distinto (por ejemplo, `Faltan datos obligatorios para la transferencia.`) o documentar explícitamente que el resumen repite el requisito y que lo que se muestra una sola vez es el error grupal junto al grupo.

#### N-05. La justificación de `bail` es incorrecta

**Evidencia:** `PLAN:347` dice que `bail` sirve para evitar un `max` adicional en “256+ espacios”. Para cadenas de sólo espacios, Laravel omite las reglas no implícitas; no se genera `max`. La necesidad real de `bail` es N-01 (`null` y tipos inválidos).

**Corrección:** reescribir la nota y hacer `bail` obligatorio en `display_data.titular`.

#### N-06. El plan se lista a sí mismo como archivo afectado y no incluye esta revisión en no-touch

**Evidencia:** `PLAN:155` incluye `docs/informes/PLAN-VALIDACION-TRANSFERENCIA-POS.md` como “Plan”; `PLAN:157-171` no menciona `REVIEW-PLAN-VALIDACION-TRANSFERENCIA-POS-2.md`.

**Corrección:** durante el BUILD no se modifica el plan ni las revisiones. Si se actualiza el plan, agregar esta revisión a la lista de no-touch.

#### N-07. Dependencias D-03/D-05 de F7B no quedan explícitas

**Evidencia:** `PLAN:16-24` declara la regla como “aprobada”; `PLAN:832-837` declara F7B parcial. El plan consolidado (`PLAN-CONSOLIDADO-MEDIOS-PAGO-POS.md:489-501`) lista F7B dependiente de D-03, D-05, F7A y F4.

**Impacto:** no es un bloqueante porque la regla se declara aprobada, pero el informe de BUILD debería dejar constancia de que D-03/D-05 fueron resueltos por decisión de producto para este alcance y que F7A/F4 siguen pendientes.

**Corrección:** agregar esa constancia en `BUILD-VALIDACION-TRANSFERENCIA-POS.md`.

#### N-08. Claves desconocidas con valor vacío siguen pudiendo generar filas vacías

**Evidencia:** `PLAN:310-318` sólo hace `unset` de las cinco claves conocidas; `PLAN:699` y `PLAN:830` documentan las claves desconocidas como deuda.

**Impacto:** bajo; una clave desconocida con `''` se preserva y el modal podría mostrarla vacía. Está documentado y fuera del alcance de la regla.

**Corrección:** ninguna en este BUILD; dejarlo asentado en el informe.

---

## 4. Evaluación de los tres puntos especiales

### 4.1. Exclusividad de `unset` para Transferencia

**Veredicto: correcta.**

- El `unset` está dentro de `if ($esTransferencia && is_array($displayDataParaGuardar))` (`PLAN:310`).
- `$esTransferencia` se calcula con igualdad exacta contra `MetodoPago::TRANSFERENCIA->value` (`PLAN:254`), y el `in:` de `metodo_pago` sólo acepta los valores del enum.
- El `after()` que descarta errores de formato por espacios también está condicionado a `$esTransferencia` (`PLAN:276-303`).
- Los tests 28-31 y 31b (`PLAN:717-721`) verifican que otros métodos conservan `display_data` vacío, ausente, `null` y cadenas de espacios, y que no reciben errores de Transferencia.
- El criterio `PLAN:866` lo exige explícitamente.

**Salvedad:** la normalización de `editPmc()` (N-02) no es exclusiva de Transferencia, aunque no es `unset`. Corregirla es necesario para que la exclusividad sea real de punta a punta.

### 4.2. Robustez de la detección de errores por texto exacto

**Veredicto: aceptable con `bail` y tests de contrato; no es la estrategia más robusta posible.**

- **Error grupal (`display_data`):** es seguro. La clave sólo recibe el error grupal cuando `display_data` es array/null, y el error de formato `display_data.array` sólo aparece cuando no es array. No pueden coexistir. El texto es único.
- **Error de titular (`display_data.titular`):** es frágil. La misma clave se usa para `required`, `string`, `regex` y `max`. Sin `bail`, un `null` produce tres mensajes y `mensajeErrorPmc()` los une; la igualdad exacta falla. Con `bail`, el caso `null` produce sólo `required` y los casos de formato producen un solo mensaje.
- **Riesgo residual:** cualquier cambio de copy en el backend o en el frontend rompe la detección. Se mitiga con N-03 (tests de mensaje exacto) y con una nota en el informe de BUILD.
- **Alternativa más estable (no obligatoria):** agregar una clave de error dedicada o un código de error en la respuesta. Implicaría tocar el contrato de errores, `camposPmcConErrorInline`, el foco y el resumen; no se justifica para este alcance. La combinación `bail` + tests de contrato es suficiente.

### 4.3. Preservación histórica de CBU/CVU

**Veredicto: correcta.**

- `PLAN:351-359` reconoce que el input compartido guardaba CBU bajo `cvu`, que no se puede distinguir automáticamente y que no hay backfill ni reinterpretación.
- `editPmc()` conserva las claves existentes mediante el spread y muestra cada valor en su input (`PLAN:532-543`).
- Un CBU histórico bajo `cvu` se muestra en el input CVU y se preserva en esa clave hasta que el usuario lo mueva manualmente a CBU.
- QA 12-13 (`PLAN:778-779`) y el riesgo `PLAN:816` lo documentan.
- No se modifica la base, el modelo, el cast `array` ni el contrato de `display_data`.

**Salvedad:** la normalización de `editPmc()` de N-02 debe condicionarse a Transferencia para no alterar los datos históricos de otros métodos.

---

## 5. Verificaciones adicionales

### 5.1. Reglas de validación existentes

**Correcto.** `PLAN:258-271` conserva:

- `metodo_pago`: `required|string|in:...`.
- `provider`: `nullable|string|max:100`.
- `display_data`: `nullable|array`.
- `display_data.alias`: `nullable|string|max:255`.
- `display_data.cvu`: `nullable|string|regex:/^[0-9]*$/|max:255`.
- `display_data.cbu`: idem CVU.
- `display_data.banco`: `nullable|string|regex:/^[\pL\s\.]*$/u|max:255`.
- `display_data.titular`: `nullable|string|regex:/^[\pL\s\.\,]*$/u|max:255`, con `required` sólo para Transferencia cuando `display_data` es array/null/ausente.
- `enabled`: `boolean`.

Los mensajes existentes se conservan (`PLAN:226-240`, `PLAN:644-646`) y sólo se agrega `display_data.titular.required` y el error grupal. `updateOrCreate`, `channel`, `enabled` y `provider` no cambian (`PLAN:635`). El cambio de `$request->validate()` a `Validator::make()` + `validate()` conserva la redirección, el old input y el formato de errores de Inertia.

### 5.2. Alcance de F7B/C-03

**Correcto y explícito.** `PLAN:832-837` declara:

- Se implementa sólo la validación y la separación CBU/CVU de F7B.
- F7B no queda cerrada: faltan F7A y F4.
- Las configuraciones históricas con claves vacías pueden seguir mostrando filas vacías hasta F7A.
- C-03 no se marca como resuelto en su totalidad.
- El informe de BUILD debe dejarlo asentado.

No se introducen cambios en `VentaController`, `PosController`, `Terminal.vue`, `ConfirmarPagoModal.vue` ni en el checkout público. Coherente con el alcance parcial.

### 5.3. Cobertura de tests

**Cobertura completa en backend, con los agregados N-01/N-02/N-03.**

- **Creación:** tests 1-20 (`PLAN:676-699`).
- **Edición y persistencia:** tests 21-27 (`PLAN:703-711`).
- **Regresión de otros métodos y formatos:** tests 28-35 (`PLAN:715-725`).
- **Permisos y multi-tenant:** tests 36-39 (`PLAN:729-734`).
- **Robustez:** tests 40-44 (`PLAN:738-744`).
- **Ausencia de errores:** el plan indica usar `assertSessionDoesntHaveErrors` o `session('errors')->has(...)` (`PLAN:750`), lo cual es correcto.
- **Frontend:** no hay tests de componentes; se cubre con `npm run build` y QA manual (`PLAN:765-789`). Es aceptable para esta fase, pero el QA debe incluir los casos nuevos N-01/N-02/N-03.

**Gaps a cubrir:**
- Mensajes exactos de `required` y grupo (N-03).
- `titular => null` con `bail` (N-01).
- Edición de PMC de otro método con `display_data` nulo/parcial (N-02).

### 5.4. Consistencia entre alcance, criterios y tests

**Mayormente consistente.** Se detectan estas inconsistencias:

1. **N-02:** el alcance dice que otros métodos no se tocan, pero `editPmc()` los normaliza. Corregir.
2. **N-04:** el plan afirma que el resumen no repite los inline, pero en los casos de un solo requisito usa el mismo texto. Aclarar o cambiar el texto.
3. **N-05:** la nota de `bail` describe un caso que Laravel no produce (256 espacios). Corregir.
4. **N-06:** el plan se lista como archivo afectado; durante el BUILD no debe modificarse.
5. **N-08:** las claves desconocidas vacías quedan fuera de la garantía de “sin filas vacías”; está documentado, pero el informe debe ser explícito.

Fuera de eso, la matriz de tests, los criterios de aceptación y el alcance coinciden.

---

## 6. Alcance exacto autorizado para BUILD

### 6.1. Autorizado

**`app/Http/Controllers/ConfiguracionController.php`**
- Sólo `storePaymentMethodConfig()`.
- Agregar `use Illuminate\Support\Facades\Validator;`.
- Copiar todas las reglas actuales y mensajes actuales.
- `display_data.titular` con `bail` y `required` condicional para Transferencia cuando `display_data` es array/null/ausente.
- `after()` para el error grupal `display_data` y para descartar errores de formato de campos con sólo espacios.
- `unset` de `alias`, `cbu`, `cvu`, `banco` y `titular` vacíos/sólo espacios, sólo para Transferencia, antes de `updateOrCreate`.
- `updateOrCreate` con el array normalizado; sin cambios en `channel`, `enabled`, `provider` ni en la respuesta.
- Sin cambios en `index`, `update`, `destroyPaymentMethodConfig` ni en las reglas de otros métodos.

**`resources/js/Pages/Configuracion/Index.vue`**
- Script del formulario PMC: `esTransferenciaPmc`, `valorCompletadoPmc`, `validarTransferenciaPmc`, `enfocarPrimerErrorPmc`, `hayErrorRequisitoTransferenciaPmc`, `MENSAJE_TITULAR_PMC`, `MENSAJE_GRUPO_PMC`, `mensajeResumenPmc`, `mostrarResumenErroresPmc`, `cambiarMetodoPagoPmc`, `editPmc` (condicionado a Transferencia por N-02), `idCampoEnfocablePmc`, `camposPmcConErrorInline`, `guardarPmc`.
- Template del formulario PMC: inputs independientes Alias, CBU, CVU, Banco y Titular; marcas de obligatorio; ayuda del grupo; error grupal una sola vez junto al grupo; `aria-required` en Titular; `role="group"` y `aria-describedby`; resumen con `mensajeResumenPmc`.
- Sin cambios en otras secciones de la página.

**`tests/Feature/ConfiguracionMediosPagoTest.php`**
- Reemplazar `test_transferencia_sin_datos_de_display_se_guarda_sin_errores`.
- Implementar la matriz 1-44 del plan.
- Agregar N-01 (`titular => null` con `bail`), N-02 (payload de otros métodos al editar) y N-03 (mensajes exactos).

**`tests/Feature/CrossTenantAttackTest.php`** (o el archivo existente de cross-tenant)
- Tests 37-39 de PMC, sin crear infraestructura nueva.

**`docs/informes/BUILD-VALIDACION-TRANSFERENCIA-POS.md`**
- Crear el informe de BUILD con alcance parcial de F7B/C-03, decisión D-03/D-05 y pendientes F7A/F4.

**`docs/informes/PLAN-CONSOLIDADO-MEDIOS-PAGO-POS.md`**
- Sólo si el BUILD lo considera necesario, actualizar el estado de C-03/F7B dejando claro que es parcial.

### 6.2. No autorizado

- Migraciones, columnas, checks, cambios de modelo, cast, unique o rutas.
- `resources/js/Components/ConfirmarPagoModal.vue`.
- `resources/js/Components/MpQrPanel.vue`.
- `resources/js/Pages/Pos/Terminal.vue`, `PosController`, `VentaController`, `VentaOperacionService`.
- POS, ventas, caja, ticket, checkout público, tienda, `comercios.transferencia_*`, `OnboardingService`, Mercado Pago, viüMi.
- Middleware, permisos, policies.
- F3/C-01, F4, F7A, refactors masivos, dependencias, `composer.json`, `package.json`.
- Cambios ajenos de B3/B3.2, `.opencode/`, `OPENCODE-SKILLS.md`.
- `docs/informes/PLAN-VALIDACION-TRANSFERENCIA-POS.md` y las dos revisiones.
- Commits automáticos.

### 6.3. Condición de autorización

El BUILD puede comenzar cuando el plan incorpore:

1. **N-01:** `bail` en `display_data.titular` y corrección de la nota.
2. **N-02:** `editPmc()` condicionado a Transferencia.
3. **N-03:** tests de mensajes exactos y de ausencia.

N-04 a N-08 son ajustes de documentación/QA y no bloquean la implementación, pero deben quedar reflejados en el informe de BUILD.

---

## 7. Tests y criterios de aceptación definitivos

### 7.1. Backend — `tests/Feature/ConfiguracionMediosPagoTest.php`

**Creación y normalización (1-20):**

1. Transferencia sin datos (`display_data => []`) → `display_data.titular` + `display_data`; no persiste.
2. `display_data` ausente → titular + grupo; sin `display_data.array`; no persiste.
3. `display_data => null` → titular + grupo; sin `display_data.array`; no persiste.
4. Sólo titular → error grupal; no persiste.
5. Alias + titular → persiste; `cbu`/`cvu` ausentes.
6. CBU + titular → persiste bajo `cbu`; `cvu` ausente.
7. CVU + titular → persiste bajo `cvu`; `cbu` ausente.
8. CBU + CVU + titular → ambos con sus valores.
9. Todos los datos → todos los valores.
10. Sin banco → persiste; `banco` ausente.
11. Titular `''`, `'   '` o `null` → sólo `display_data.titular`; con N-01, sólo el mensaje `required`; no persiste.
12. Grupo `''`, `'   '` o `null` con titular válido → sólo `display_data`; sin regex de CBU/CVU; no persiste.
13. Banco + titular sin cuenta → error grupal; no persiste.
14. Alias válido + CBU no numérico → sólo `display_data.cbu.regex`.
15. Alias válido + CVU no numérico → sólo `display_data.cvu.regex`.
16. Alias válido + banco inválido → sólo `display_data.banco.regex`.
17. Alias válido + titular inválido → sólo `display_data.titular.regex`.
18. Alias/CBU/CVU/banco con sólo espacios y sin otro dato → sólo error grupal; sin regex; no persiste.
19. Alias con espacios + CVU válido + titular → persiste; `alias` ausente; `cvu` presente.
20. CBU `''` + banco `'   '` + alias/CVU/titular válidos → persiste; `cbu`/`banco` ausentes; sin `null`.

**Edición (21-27):**

21. Actualización válida con el mismo `provider` → `assertDatabaseCount(..., 1)`; `display_data` actualizado.
22. Actualización inválida → errores; fila original sin cambios; count 1.
23. Configuración histórica incompleta → error; original sin cambios; luego válida → se guarda.
24. CBU → CVU → `cvu` presente; `cbu` ausente.
25. CVU → CBU → `cbu` presente; `cvu` ausente.
26. CBU + CVU simultáneos → ambos.
27. Edición conserva y muestra ambos (backend + QA manual de inputs).

**Regresión (28-35):**

28. Otros métodos con `display_data => []` → sin errores; persiste.
29. Otros métodos sin `display_data` → sin errores; persiste.
30. Otros métodos con `display_data => null` → sin errores; persiste.
31. Otros métodos con `alias`/`banco`/`titular` de espacios → sin errores; se conservan.
31b. Otros métodos con `cbu`/`cvu` de espacios → errores de regex existentes; sin errores de Transferencia.
32. Otro método con CVU inválido → sólo `display_data.cvu.regex`.
33. `display_data` string en Transferencia → sólo `display_data.array`.
34. Método ausente → sólo `metodo_pago`; sin errores de Transferencia.
35. Método inválido → errores actuales de campos; sin errores de Transferencia.

**Permisos y multi-tenant (36-39):**

36. Cajero → `403`; no persiste.
37. Cross-tenant index → Admin B no ve PMC de A (`assertInertia`).
38. Cross-tenant delete → `403`; PMC de A sigue.
39. Cross-tenant store → PMC con `comercio_id` de B; PMC de A sin cambios.

**Robustez (40-44):**

40. Claves desconocidas preservadas.
41. Valores con contenido exactos (`assertSame`).
42. `'0'` como alias/CBU/CVU se considera informado.
43. Límites 255/256 → `max` en el campo correspondiente.
44. Titular con comas/puntos válido.

**Nuevos (45-47):**

45. Mensajes exactos: `display_data.titular => 'Ingresá el nombre del titular.'` y `display_data => 'Completá al menos uno de Alias, CBU o CVU.'`.
46. `titular => null` con `bail`: sólo el mensaje `required`; sin `display_data.titular.string` ni `display_data.titular.regex`.
47. Ausencia de errores de Transferencia en los casos 32-35 con `assertSessionDoesntHaveErrors` o `session('errors')->has(...)`.

### 7.2. Frontend — QA manual

Además de los 23 puntos de `PLAN:765-789`:

24. Verificar que los textos de `MENSAJE_TITULAR_PMC` y `MENSAJE_GRUPO_PMC` coinciden con los del backend (N-03).
25. Editar una PMC de Efectivo/Cuenta Corriente con `display_data` nulo o parcial y confirmar que no se agregan claves vacías (N-02).
26. Confirmar si el resumen repite o no el texto inline en los casos de un solo requisito y ajustar la documentación/UX (N-04).
27. Verificar que el error grupal no se duplica al pie del formulario.
28. Verificar foco en titular, CBU, CVU, banco y resumen según la tabla `PLAN:593-606`.
29. Verificar que un error de formato único no muestra el resumen específico.
30. Verificar que un error de formato + requisito muestra el resumen específico y todos los inline.

### 7.3. Criterios de aceptación definitivos

- [ ] Transferencia sin ningún dato es rechazada y no persiste.
- [ ] Transferencia sólo con titular es rechazada y no persiste.
- [ ] Transferencia con alias + titular se guarda.
- [ ] Transferencia con CBU + titular se guarda bajo `cbu`.
- [ ] Transferencia con CVU + titular se guarda bajo `cvu`.
- [ ] Transferencia con CBU + CVU + titular guarda ambos en sus claves.
- [ ] Editar una Transferencia válida actualiza la misma fila (mismo `provider`) y no pisa el otro dato.
- [ ] Editar una configuración incompleta exige completar titular/grupo y no pierde el dato original si falla.
- [ ] Transferencia con alias + CBU + CVU + titular se guarda.
- [ ] Transferencia sin banco se guarda.
- [ ] Titular `''`, `'   '` o `null` es rechazado; con `bail`, `null` devuelve sólo el mensaje `required`.
- [ ] Alias/CBU/CVU `''`, `'   '` o `null` son rechazados si no hay otro dato de cuenta.
- [ ] Para Transferencia, no se persisten claves `alias`, `cbu`, `cvu`, `banco` ni `titular` con `null`, `''` o sólo espacios; se usa `unset`.
- [ ] Los campos opcionales con sólo espacios no generan filas vacías en POS para configuraciones nuevas o editadas.
- [ ] Una configuración histórica incompleta no puede actualizarse sin completar los datos obligatorios.
- [ ] El formulario muestra Titular como obligatorio y aclara que se necesita al menos uno de Alias/CBU/CVU.
- [ ] El formulario tiene inputs independientes Alias, CBU, CVU, Banco y Titular.
- [ ] El resumen específico sólo aparece ante errores de requisito mínimo, no ante un único error de formato.
- [ ] El formulario muestra errores inline sin contradicciones y el foco respeta F2, incluido el nuevo input CBU y el resumen cuando sólo falla el grupo.
- [ ] Los valores ingresados se conservan ante error.
- [ ] Al cambiar de método se limpian los errores de `display_data` y se conserva la limpieza de `metodo_pago`.
- [ ] Los demás métodos de pago conservan su comportamiento, incluidos `display_data` ausente, `null` y cadenas de espacios.
- [ ] Editar una PMC de otro método con `display_data` nulo/parcial no agrega claves vacías (N-02).
- [ ] Los mensajes exactos de titular requerido y grupo están cubiertos por tests (N-03).
- [ ] El usuario sin permisos sigue recibiendo `403`.
- [ ] Admin B no ve, no elimina y no modifica configuraciones de A; los tests cross-tenant pasan.
- [ ] No se modifica la estructura de la base de datos ni se agregan migraciones, y no se alteran POS, checkout público, Mercado Pago ni permisos.
- [ ] Pasan `ConfiguracionMediosPagoTest`, los tests cross-tenant, `npm run build` y el QA manual.
- [ ] El informe de BUILD declara el alcance parcial de F7B/C-03, la decisión D-03/D-05 y que las filas vacías históricas quedan para F7A.
- [ ] No se introducen cambios ajenos en el working tree.

---

## 8. Confirmación de no modificación

Esta revisión fue exclusivamente de análisis.

- No se modificó código de producción.
- No se modificaron tests.
- No se crearon ni modificaron migraciones.
- No se modificó la estructura de la base de datos ni se ejecutaron migraciones.
- No se modificaron dependencias ni configuraciones.
- No se modificaron rutas, permisos, policies, POS, ventas, caja, checkout público ni integraciones de pago.
- No se modificó `ConfirmarPagoModal.vue`, `MpQrPanel.vue`, `Configuracion/Index.vue` ni `ConfiguracionController.php`.
- No se modificó el plan `docs/informes/PLAN-VALIDACION-TRANSFERENCIA-POS.md`.
- No se modificó la primera revisión `docs/informes/REVIEW-PLAN-VALIDACION-TRANSFERENCIA-POS.md`.
- No se modificó `AGENTS.md`, `.opencode/` ni `OPENCODE-SKILLS.md`.
- No se tocaron los cambios preexistentes de B3/B3.2 ni archivos relacionados.
- No se ejecutaron tests, builds ni migraciones.
- No se creó ningún commit.
- El único archivo nuevo de esta tarea es este documento: `docs/informes/REVIEW-PLAN-VALIDACION-TRANSFERENCIA-POS-2.md`.

**Conclusión final:** el plan está técnicamente encaminado y no requiere rediseño. Queda **autorizado técnicamente para BUILD una vez incorporadas N-01, N-02 y N-03**, sujeto a aprobación del usuario.
