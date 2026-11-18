# REVIEW — PLAN VALIDACIÓN OBLIGATORIA DE TRANSFERENCIA (POS)

**Fecha:** 2026-11-17
**Modo:** REVIEWER exclusivo. No se modificó código, tests, migraciones, base de datos, dependencias ni configuraciones. No se ejecutaron tests ni builds. No se creó commit.
**Documento revisado:** `docs/informes/PLAN-VALIDACION-TRANSFERENCIA-POS.md`
**Base de código:** `f144e18` más los cambios preexistentes del working tree (B3/B3.2, `.opencode/`, `OPENCODE-SKILLS.md`). No se tocó ninguno.
**Entregable:** este documento. Es el único archivo nuevo de la revisión.

---

## 1. Veredicto

**APROBADO CON OBSERVACIONES — correcciones obligatorias antes de BUILD.**

La dirección del plan es correcta: la regla de negocio está bien definida, el backend puede implementarse sin migración, la separación CBU/CVU es técnicamente viable y el plan respeta el alcance de F2 y de los demás métodos de pago. Sin embargo, el plan **no está listo para autorizarse tal como está** por cinco motivos:

1. **La normalización a `null` no elimina las filas vacías del POS.** El modal itera todas las claves de `display_data`; una clave con `null` sigue renderizando una fila con valor vacío. El plan afirma lo contrario en cuatro lugares y su test 15 no puede probarlo.
2. **`mensajeResumenPmc` no está definido.** Se lo nombra, pero no tiene texto, condición ni ubicación exacta.
3. **`cambiarMetodoPagoPmc()` puede perder la limpieza del error de `metodo_pago`**, que hoy hace F2.
4. **Falta el import de `Validator`** y el pseudocódigo no explicita que se conservan todas las reglas actuales.
5. **La matriz de tests no cubre actualización válida, separación CBU/CVU ni aislamiento cross-tenant de PMC.**

Ninguna observación exige rediseñar la regla. Todas son corregibles dentro del mismo alcance, salvo la decisión sobre filas vacías históricas, que debe quedar explícitamente fuera de esta fase.

---

## 2. Hallazgos priorizados

### 2.1. Bloqueantes / alta prioridad

#### H-01. `null` no evita filas vacías en el POS

**Evidencia del plan:**
- `PLAN:173`: “se persisten como `null` para no guardar filas vacías”.
- `PLAN:197`: “no genera una fila vacía en el POS”.
- `PLAN:518` (test 15): “`cvu` y `banco` quedan `null`/ausentes; no genera filas vacías”.
- `PLAN:577` (riesgo): “Normalizar a `null` … Test 15”.
- `PLAN:601` (criterio de aceptación): “no se guardan como filas vacías en POS”.

**Evidencia del código:**
- `resources/js/Components/ConfirmarPagoModal.vue:142-146` itera `v-for="(value, key) in group.configs[0].display_data"` sin filtrar `null` ni `''`.
- `resources/js/Components/ConfirmarPagoModal.vue:159-163` hace lo mismo para múltiples configuraciones.
- `app/Http/Controllers/VentaController.php:471` entrega `display_data` sin transformarlo.
- `app/Services/VentaOperacionService.php:298` y `app/Http/Controllers/PosController.php:72` también lo entregan tal cual.

**Conclusión:** `{ "cvu": null }` sigue siendo una clave del objeto. Vue la recorre y renderiza “Cvu: ” con valor vacío. Lo mismo pasa con `''`. El plan no modifica el modal (`PLAN:491`), así que la única forma de cumplir el objetivo sin ampliar alcance es **eliminar la clave** (`unset`) antes de `updateOrCreate`, no asignarle `null`.

**Corrección obligatoria:**
- Para Transferencia, después de validar, recorrer `alias`, `cbu`, `cvu` y `banco`; si el valor es `null`, `''` o una cadena con sólo espacios, hacer `unset($displayDataParaGuardar[$campo])`.
- Si se prefiere conservar `null`, entonces hay que modificar `ConfirmarPagoModal.vue` para filtrar `null`/`''`. Eso es F7A (ver `PLAN-CONSOLIDADO-MEDIOS-PAGO-POS.md:475-487`) y **cambia el alcance**; el plan actual dice que no lo toca.
- Actualizar el test 15 para afirmar `assertArrayNotHasKey('cvu', $pmc->display_data)` y `assertArrayNotHasKey('banco', ...)`, no `assertNull`.
- Actualizar el riesgo `PLAN:577` y el criterio `PLAN:601` para decir “no se persisten claves con valor vacío”, no “se persisten como `null`”.
- Aclarar que las configuraciones **históricas** con `''`/`null` seguirán mostrando filas vacías hasta que se implemente F7A. Este plan sólo corrige nuevas configuraciones y ediciones.

#### H-02. `mensajeResumenPmc` está nombrado pero no definido

**Evidencia:**
- `PLAN:329` lo lista como “texto general específico cuando faltan datos de Transferencia”.
- `PLAN:414` dice “Reemplazar el texto fijo del resumen por `mensajeResumenPmc`”.
- No hay pseudocódigo, texto exacto ni condición de uso.
- `PLAN:382-388` hace que `mostrarResumenErroresPmc` se active con **cualquier** error de Transferencia, incluido un CVU no numérico. Un mensaje del tipo “faltan datos” sería incorrecto para un error de formato.

**Corrección obligatoria:**
- Definir el texto exacto y la condición. Opciones válidas:
  - Un texto genérico para Transferencia: “Revisá los datos de la transferencia y volvé a intentar.”
  - Un texto específico sólo cuando exista el error grupal `display_data` o `display_data.titular`: “Completá el titular y al menos uno de Alias, CBU o CVU.”
- Definir si reemplaza el título (`No se pudo guardar el medio de pago.`), el subtítulo (`Revisá los datos marcados...`) o ambos. Hoy el resumen tiene dos párrafos (`resources/js/Pages/Configuracion/Index.vue:700-701`).
- Agregar QA para los tres casos: sólo grupo, sólo titular, sólo formato de CVU.

#### H-03. `cambiarMetodoPagoPmc()` puede perder la limpieza de `metodo_pago`

**Evidencia:**
- Hoy el select usa `@change="limpiarErrorPmc('metodo_pago')"` (`resources/js/Pages/Configuracion/Index.vue:717`).
- `PLAN:427` propone reemplazar ese handler por `cambiarMetodoPagoPmc()` y sólo dice que limpia errores de `display_data`.
- `PLAN:330` define `cambiarMetodoPagoPmc()` como limpieza de `display_data` y sus subcampos, sin mencionar `metodo_pago`.

**Conclusión:** si se reemplaza el handler sin más, un error de `metodo_pago` queda visible después de cambiar de método. Es una regresión de F2.

**Corrección obligatoria:**
- `cambiarMetodoPagoPmc()` debe llamar también a `limpiarErrorPmc('metodo_pago')`, o el template debe conservar ambos handlers.
- Agregar QA: forzar un error de `metodo_pago`, cambiar el método y verificar que el error desaparece.

#### H-04. Falta el import de `Validator` y la enumeración completa de reglas

**Evidencia:**
- `PLAN:237` usa `Validator::make(...)`.
- `app/Http/Controllers/ConfiguracionController.php:1-14` no importa `Illuminate\Support\Facades\Validator`.
- `PLAN:238` deja “reglas actuales sin cambios” como comentario, pero el pseudocódigo sólo muestra `display_data.titular`.

**Corrección obligatoria:**
- Agregar `use Illuminate\Support\Facades\Validator;`.
- En el BUILD, copiar **todas** las reglas actuales de `storePaymentMethodConfig` (`ConfiguracionController.php:183-208`), no sólo titular. El pseudocódigo es ilustrativo, no reemplaza el bloque completo.
- Verificado: `MessageBag::forget()` existe en la versión instalada (`vendor/laravel/framework/src/Illuminate/Support/MessageBag.php:270`), así que el `after()` propuesto es viable. No hace falta cambiarlo.

#### H-05. La regla también aplica a la edición, no sólo a “nuevos guardados”

**Evidencia:**
- `PLAN:572` dice “La regla aplica sólo a nuevos guardados”.
- El único endpoint es `storePaymentMethodConfig` (`routes/web.php:376`) y usa `updateOrCreate` (`ConfiguracionController.php:210-221`). Editar una PMC es un POST al mismo endpoint.
- Una configuración histórica incompleta (por ejemplo, sólo CBU sin titular) no se podrá volver a guardar sin completar titular y grupo.

**Conclusión:** probablemente es el comportamiento deseado, pero el plan lo describe mal. No hay “nuevos” vs “edición” a nivel de validación: la regla se aplica a **crear y editar**, y no hay backfill.

**Corrección obligatoria:**
- Reescribir `PLAN:572` como: “La regla se aplica al crear y al editar; no hay backfill ni migración de datos históricos.”
- Agregar un test de edición de una configuración incompleta (ver T-07).
- Comunicar en QA que una configuración histórica incompleta queda bloqueada hasta completarla.

### 2.2. Media prioridad

#### H-06. Faltan tests de actualización válida y de separación CBU/CVU

La matriz sólo tiene el caso inválido sobre configuración existente (`PLAN:516`, test 13). Faltan:

- Actualización válida de una Transferencia existente, con el mismo `provider`, sin crear una fila nueva.
- Actualizar con CBU y borrar CVU (y al revés), verificando que el otro dato no se pisa.
- Actualización de una configuración incompleta.
- Preservación de claves desconocidas de `display_data`.

**Riesgo asociado (F3/C-01):** `updateOrCreate` usa `(comercio_id, metodo_pago, provider)` (`ConfiguracionController.php:210-215`). En PostgreSQL, `NULL` y `''` son distintos en el unique. Si la fila existente tiene `provider = null` y el formulario envía `provider = ''` (`pmcForm.provider` se inicializa en `''`, `Index.vue:86`; `editPmc` hace `pmc.provider || ''`, `Index.vue:161`), se crea una fila nueva en lugar de actualizar. El plan reconoce F3/C-01 (`PLAN:582`), pero no menciona el caso `null` vs `''`. Los tests de actualización deben usar el mismo `provider` que la fila existente y aclarar el caso en QA.

#### H-07. Faltan tests de aislamiento cross-tenant de PMC

El plan sólo cubre cajero 403 (`PLAN:517`, test 14). Faltan:

- Admin B no ve las configuraciones de A (`ConfiguracionController@index`, `ConfiguracionController.php:43-53`; usar `assertInertia`).
- Admin B no puede eliminar una PMC de A (`ConfiguracionController@destroy`, `ConfiguracionController.php:226-233`; ya devuelve 403, pero no hay test).
- Guardar como B crea la PMC con `comercio_id` de B y no modifica la de A (`storePaymentMethodConfig`, `ConfiguracionController.php:180-221`).

El plan dice “No se tocan rutas, middleware ni consultas” (`PLAN:583`), lo cual es correcto, pero la matriz debe **probar** el aislamiento existente. Se pueden agregar a `tests/Feature/ConfiguracionMediosPagoTest.php` o a `tests/Feature/CrossTenantAttackTest.php` (que ya tiene el patrón, `CrossTenantAttackTest.php:1-108`).

#### H-08. Faltan casos de regresión para otros métodos y para la ausencia de errores

El test 11 (`PLAN:514`) sólo usa `display_data => []`. Faltan:

- Otros métodos con `display_data` ausente y con `null` (sin errores, sin reglas de Transferencia).
- Otros métodos con `alias`/`cbu`/`cvu`/`banco`/`titular` con sólo espacios: **no** se normalizan y **no** se agregan errores de Transferencia. Esto prueba que la normalización es exclusiva de Transferencia.
- Método ausente o inválido con `display_data` vacío: no deben aparecer `display_data` ni `display_data.titular`.
- `display_data` string: además del error `display_data`, no deben aparecer titular ni grupo.

**Técnica:** `assertSessionHasErrors([...])` sólo prueba que las claves indicadas existen; no prueba que no haya otras. Para verificar ausencia, usar `session('errors')->has('display_data')` / `->has('display_data.titular')`, o `assertSessionDoesntHaveErrors` si está disponible en la versión de PHPUnit/Laravel. El plan dice “verificar” en `PLAN:526-528`, pero no especifica cómo.

#### H-09. Faltan casos de opcionales y formatos en Transferencia

Agregar, como mínimo:

- `banco` + `titular`, sin alias/CBU/CVU → error de grupo (banco no satisface la regla).
- `alias` válido + `cbu` no numérico → error de regex de CBU, **sin** error de grupo (el alias ya satisface el grupo).
- `alias` válido + `cvu` no numérico → error de regex de CVU, **sin** error de grupo.
- `alias` válido + `banco` inválido → error de regex de banco.
- `alias` válido + `titular` inválido → error de regex de titular.
- `display_data` ausente y `display_data => null` en Transferencia → titular + grupo, **sin** `display_data.array`.
- `display_data` con sólo claves desconocidas → titular + grupo.
- Valores con contenido se conservan exactamente (por ejemplo, `'Juan Pérez'`, `'mi.alias'`, 22 dígitos).
- Whitespace en `alias`, `cbu` y `cvu` (no sólo `cvu`/`banco`) se elimina/`null`/ausente.
- `'0'` como alias, CBU o CVU se considera informado (edge case de `filled()`).

#### H-10. La separación CBU/CVU deja datos históricos ambiguos

El plan lo reconoce (`PLAN:573`), pero no define remediación. No es bloqueante para la regla, pero sí lo es para la afirmación “un CBU existente se ve en el input CBU” (`PLAN:544`, QA 10): eso sólo es cierto si el CBU se guardó bajo la clave `cbu`. Un CBU ingresado por el input compartido quedó guardado bajo `cvu` y se verá en el input CVU. Ver sección 3.

#### H-11. `MpQrPanel.vue` es un consumidor no listado

`resources/js/Components/MpQrPanel.vue:24` usa `displayData?.alias || displayData?.cvu` y `:133-149` muestra `cvu`. No está referenciado en el proyecto (búsqueda sin resultados), por lo que es código muerto. El plan enumera consumidores y no lo menciona. No bloquea, pero conviene declararlo “componente no referenciado; sin impacto actual” o dejarlo para otra fase. No tocarlo en este BUILD.

#### H-12. Accesibilidad del error grupal

El plan mueve el error `display_data` junto al grupo (`PLAN:425`), pero no define `role="group"` ni `aria-describedby` para asociar el error/ayuda con los tres inputs. Los inputs individuales tienen su propio `aria-describedby` (`Index.vue:745`, `:757`, `:772`, `:784`), pero el error grupal no. Recomendación: envolver el grupo en un contenedor con `role="group"` y `aria-describedby` apuntando a la ayuda/error grupal, o agregar el id del error grupal al `aria-describedby` de alias/CVU/CBU. No es bloqueante, pero es coherente con F2.

#### H-13. No hay test automatizado de la validación frontend

El plan dice “No hay infraestructura de tests JS en el proyecto” (`PLAN:533`). Es cierto para componentes Vue, pero existe precedente de `node:test` para utilidades (`resources/js/Utils/pagoManual.test.mjs`, ejecutado con `node --test`). Se podría extraer `valorCompletadoPmc`/la regla de grupo a una utilidad pura y testearla sin agregar dependencias. Es opcional; si no se hace, el QA manual debe cubrir toda la tabla `PLAN:434-440`. Aclarar la frase del plan.

### 2.3. Baja prioridad / observaciones

- **H-14. Mensajes `string` no personalizados.** Para payloads manipulados con `titular`/`alias`/`cbu`/`cvu`/`banco` como arrays u otros tipos, Laravel puede devolver el mensaje por defecto en inglés para `string`. No es un caso de UI. Si se quiere robustez, agregar `bail` o mensajes `*.string`. No bloqueante.
- **H-15. Claves desconocidas en `display_data`.** Se siguen guardando y el modal las muestra. Es comportamiento existente. El plan preserva claves desconocidas en `editPmc` (`PLAN:392-403`), lo cual es correcto para no perder datos, pero conviene documentarlo como deuda.
- **H-16. El resumen no lista el error grupal.** `display_data` está en `camposPmcConErrorInline` (`Index.vue:112-122`), así que `erroresGeneralesPmc` lo excluye. El plan dice que se muestra inline; verificar que no quede duplicado al pie (hoy está en `Index.vue:793-795`).
- **H-17. El cambio de resumen para Transferencia es intencional pero debe testearse.** `mostrarResumenErroresPmc` pasa a mostrarse con cualquier error de Transferencia (`PLAN:382-388`). Aceptable, pero es un cambio de F2 y necesita QA.
- **H-18. Orden de inputs.** La tabla `PLAN:434-440` asume CVU antes de CBU. Mantener el orden actual (alias, CVU, CBU, banco, titular) y separar en dos inputs. No es bloqueante.
- **H-19. F7B queda parcial.** El plan consolidado define F7B con dependencias de D-03, D-05, F7A y F4 (`PLAN-CONSOLIDADO-MEDIOS-PAGO-POS.md:489-501`). Este plan implementa una parte de F7B sin F7A ni F4. El BUILD debe declarar explícitamente que F7B no queda cerrada y que las filas vacías históricas siguen pendientes hasta F7A.
- **H-20. Informe de BUILD.** `AGENTS.md` exige informe técnico por fase. El BUILD debe crear `docs/informes/BUILD-VALIDACION-TRANSFERENCIA-POS.md` y dejar constancia del alcance parcial de F7B/C-03.

---

## 3. Evaluación de la separación CBU/CVU

### 3.1. Problema real

Confirmado en código:

- El input único “CVU / CBU” está en `resources/js/Pages/Configuracion/Index.vue:753-759` y escribe siempre en `pmcForm.display_data.cvu`.
- `display_data.cbu` no tiene input propio, pero sí existe en el estado (`Index.vue:89-90`), en el backend (`ConfiguracionController.php:189`, `:201-202`) y en el listado (`Index.vue:681-682`).
- `editPmc` preserva `cbu` porque hace spread de `pmc.display_data` (`Index.vue:162`), pero no lo muestra ni permite editarlo.
- Un CBU tipeado por el usuario se guarda como `cvu`.
- El POS modal muestra la clave tal cual: `cvu` o `cbu` (`ConfirmarPagoModal.vue:143-146`).
- `VentaController` y `VentaOperacionService` pasan `display_data` sin transformarlo (`VentaController.php:471`, `:762`; `VentaOperacionService.php:298`).
- `PosController` también lo pasa sin transformarlo (`PosController.php:72`).

### 3.2. ¿Hace falta migración?

**No.** La conclusión del plan es correcta:

- `payment_method_configurations` sólo tiene una columna `json('display_data')` (`database/migrations/2026_06_14_000005_create_payment_method_configurations_table.php:19`).
- No hay columnas separadas para CBU/CVU ni índices sobre ellas.
- `PaymentMethodConfiguration` castea `display_data` a `array` (`app/Models/PaymentMethodConfiguration.php:20-23`).
- El backend ya valida ambas claves (`ConfiguracionController.php:188-189`).
- La separación en dos inputs usa claves JSON que ya existen. No cambia el esquema, el modelo, el unique ni el contrato de `display_data`.

### 3.3. Impacto en creación, edición, listado y POS

- **Creación:** mejora. El usuario elige CVU o CBU y se guarda en la clave correcta.
- **Edición:** mejora para configuraciones con `cbu` guardado bajo `cbu`; el valor aparece en el input CBU. `editPmc` debe seguir preservando claves desconocidas y agregar las conocidas con `?? ''` (`PLAN:392-403`). Correcto.
- **Listado:** el listado ya muestra `CVU` y `CBU` por separado (`Index.vue:681-682`). La separación alinea el formulario con el listado. No hay cambio de contrato.
- **POS:** el modal itera claves; separar inputs no cambia el consumo. El problema de filas vacías sigue vigente (H-01). Si se eliminan las claves vacías, el modal no las muestra; si se guardan como `null`, sí las muestra.
- **Ventas manuales:** `VentaController` y `VentaOperacionService` no inspeccionan `cvu`/`cbu`; sólo pasan el objeto. No hay regresión por la separación.
- **`MpQrPanel.vue`:** usa `alias || cvu`, no usa `cbu`. Está sin referencias. Si se reactivara, una config con sólo CBU no generaría QR. No es parte de este cambio; documentarlo.

### 3.4. Datos históricos

Un CBU ingresado por el input compartido quedó guardado como `cvu`. No se puede distinguir automáticamente de un CVU real. El plan acierta al no proponer una migración automática (`PLAN:573`). Recomendaciones:

- No hacer backfill ni inferencia automática.
- Agregar una nota de rollout: revisar manualmente las configuraciones de Transferencia existentes; si el valor parece un CBU (22 dígitos) y está en `cvu`, el usuario puede moverlo al input CBU al editar.
- No afirmar en QA que “un CBU existente se ve en el input CBU” sin aclarar que eso aplica sólo a datos guardados bajo la clave `cbu`. Para datos históricos bajo `cvu`, se verá en CVU.
- Si se quiere remediar en masa, planificar una auditoría separada con revisión de datos; no en este BUILD.

### 3.5. Conclusión CBU/CVU

La separación es **correcta y no requiere migración**. Es un cambio de frontend justificado, con un riesgo de semántica histórica que debe quedar documentado y fuera del alcance de la regla. No es un bloqueante para la validación, pero sí debe incluirse en el QA y en el informe de BUILD.

---

## 4. Correcciones obligatorias antes de BUILD

1. **H-01:** reemplazar la normalización a `null` por `unset`/clave ausente para Transferencia, o autorizar F7A para modificar el modal. Actualizar test 15, riesgo `PLAN:577` y criterio `PLAN:601`. Aclarar el histórico.
2. **H-02:** definir `mensajeResumenPmc` (texto exacto, condición y si reemplaza título/subtítulo).
3. **H-03:** `cambiarMetodoPagoPmc()` debe limpiar también `metodo_pago`, o conservar el handler actual.
4. **H-04:** agregar `use Illuminate\Support\Facades\Validator;` y copiar todas las reglas actuales, no sólo titular.
5. **H-05:** aclarar que la regla aplica a crear y editar; no hay backfill. Ajustar `PLAN:572`.
6. **H-06:** agregar tests de actualización válida, separación CBU/CVU, configuración incompleta y preservación de claves desconocidas.
7. **H-07:** agregar tests cross-tenant de `index`, `destroy` y `store` de PMC.
8. **H-08/H-09:** ampliar la matriz de regresión y de formatos/opcionales.
9. **H-10:** documentar la ambigüedad histórica CBU-as-CVU y el alcance del QA.
10. **H-19:** declarar que F7B queda parcial (sin F7A/F4) y que las filas vacías históricas siguen hasta F7A.
11. **H-20:** crear el informe de BUILD y actualizar el estado de C-03/F7B.
12. **Recomendado H-12/H-13:** accesibilidad del grupo y, si se puede, test de utilidad frontend con `node:test`.

---

## 5. Tests adicionales recomendados

### 5.1. Backend (`tests/Feature/ConfiguracionMediosPagoTest.php`)

**Alta prioridad:**

- **T-01. Actualización válida:** crear una Transferencia válida (mismo `provider`), hacer POST con nuevos datos válidos y afirmar `assertDatabaseCount(..., 1)`, `comercio_id` y `display_data` actualizado. Evitar el caso `provider` null vs `''`.
- **T-02. Actualización CBU↔CVU:** crear con `cvu`, actualizar con `cbu` y `cvu` vacío; afirmar que `cbu` se guarda y `cvu` no queda con el valor viejo (clave ausente). Repetir al revés.
- **T-03. Cross-tenant delete:** admin B intenta eliminar una PMC de A → 403 y la fila de A sigue existiendo.
- **T-04. Cross-tenant index:** admin B no ve la PMC de A en `paymentMethodConfigs` (usar `assertInertia`).
- **T-05. Cross-tenant store:** admin B guarda una Transferencia; se crea con `comercio_id` de B y la PMC de A no cambia.
- **T-06. `display_data` ausente/null en Transferencia:** ambos casos → `display_data.titular` + `display_data`, sin `display_data.array`; no persiste.
- **T-07. Configuración incompleta editada:** crear una PMC histórica incompleta (por ejemplo, sólo CBU sin titular); POST sin titular → error y la fila original no cambia. POST con titular + alias → se guarda.
- **T-08. `display_data` string:** además de `display_data`, no deben aparecer `display_data.titular` ni `display_data`. Usar aserción de ausencia.
- **T-09. Otros métodos con `display_data` ausente/null:** sin errores y persiste.
- **T-10. Otros métodos con whitespace:** `alias`/`cbu`/`cvu`/`banco` con sólo espacios no se normalizan y no agregan errores de Transferencia.
- **T-11. `banco` solo + titular:** error de grupo; no persiste.
- **T-12. Alias válido + CBU no numérico:** error de regex de CBU, sin error de grupo.
- **T-13. Alias válido + CVU no numérico:** error de regex de CVU, sin error de grupo.
- **T-14. Whitespace en alias/cbu/cvu:** con otro dato válido, las claves vacías no se persisten (`assertArrayNotHasKey`).
- **T-15. No persistencia de claves vacías:** afirmar que `display_data` no contiene `cvu`/`cbu`/`banco`/`alias` con `null`/`''` cuando están vacíos. Este test reemplaza la ambigüedad del test 15 actual.

**Media prioridad:**

- **T-16. Claves desconocidas preservadas:** enviar una clave extra en `display_data` y afirmar que se guarda (comportamiento actual).
- **T-17. Valores con contenido exactos:** `'Juan Pérez'`, `'mi.alias'`, 22 dígitos, banco con punto; afirmar `assertSame`.
- **T-18. `'0'` como alias/CBU/CVU:** se considera informado y guarda.
- **T-19. Límites:** alias/CVU/CBU/banco/titular de 255 y 256 caracteres.
- **T-20. Método ausente/inválido con `display_data` vacío:** no aparecen errores de Transferencia (aserción de ausencia).
- **T-21. Cajero con payload válido:** 403 y no persiste (aislar el middleware).
- **T-22. `titular` con comas/puntos:** válido según la regex actual.

**Baja prioridad:**

- **T-23. Non-breaking spaces:** definir si se consideran faltantes; si no, documentarlo.
- **T-24. Payloads con arrays anidados:** robustez de mensajes `string` (opcional, agregar `bail`/mensajes).
- **T-25. `display_data` con sólo claves desconocidas:** titular + grupo.

### 5.2. Frontend

No hay infraestructura de tests de componentes Vue. Opciones:

- **F-01 (recomendada, opcional):** extraer `valorCompletadoPmc` y la regla de grupo a una utilidad pura (por ejemplo, `resources/js/Utils/mediosPago.js`) y testearla con `node --test`, siguiendo el precedente de `resources/js/Utils/pagoManual.test.mjs`. Casos: `null`, `''`, `'   '`, `'0'`, `0`, `false`, array vacío, array con datos, string con contenido.
- **F-02 (mínima):** QA manual de toda la tabla `PLAN:434-440` y de los 16 puntos de `PLAN:535-550`, con especial atención a:
  - filas vacías en el modal para configs nuevas/editadas;
  - edición de una config con CBU guardado en `cbu`;
  - edición de una config con CBU histórico guardado en `cvu` (debe aparecer en CVU, no en CBU);
  - cambio de método después de un error (incluido `metodo_pago`);
  - resumen con un solo error de formato vs un solo error de grupo.
- **F-03:** verificar que el error grupal no se duplica al pie del formulario (`Index.vue:793-795`).

---

## 6. Riesgos y criterios de aceptación

### 6.1. Riesgos

| Riesgo | Impacto | Mitigación |
|---|---|---|
| `null` no elimina filas vacías en el POS | Falsa sensación de corrección; el modal sigue mostrando datos en blanco | `unset` de claves vacías o F7A; QA en el modal, no sólo en la DB. |
| Configuraciones históricas incompletas | Siguen mostrando filas vacías y no se corrigen solas | Documentar; F7A/F7B; no backfill en este plan. |
| CBU histórico guardado como `cvu` | El usuario lo ve en el input CVU; posible confusión | Nota de rollout; revisión manual; no migración automática. |
| Edición de configs incompletas bloqueada | El usuario no puede guardar cambios menores sin completar titular/grupo | Es intencional; comunicarlo en QA y en el mensaje del formulario. |
| F3/C-01: `provider` null vs `''` | Duplica filas al editar | Tests con el mismo `provider`; no corregir F3/C-01 en esta fase; aclarar en QA. |
| `cambiarMetodoPagoPmc` no limpia `metodo_pago` | Regresión de F2 | Incluir `limpiarErrorPmc('metodo_pago')`. |
| `mensajeResumenPmc` indefinido | Mensaje incorrecto para errores de formato | Definir texto y condición. |
| Tests que sólo usan `assertSessionHasErrors` | No prueban ausencia de errores de Transferencia | Agregar aserciones de ausencia. |
| Sin test automatizado de frontend | Regresiones de foco/resumen/errores | QA manual exhaustivo o utilidad con `node:test`. |
| F7B parcial sin F7A/F4 | Expectativa de cierre total de C-03 | Declarar alcance parcial en el informe de BUILD. |

### 6.2. Criterios de aceptación corregidos

Se considerará implementada la fase cuando:

- [ ] Transferencia sin titular y sin alias/CBU/CVU es rechazada y no persiste.
- [ ] Transferencia sólo con titular es rechazada y no persiste.
- [ ] Transferencia con titular + al menos uno de alias/CBU/CVU se guarda.
- [ ] Banco sigue siendo opcional.
- [ ] Titular vacío, sólo espacios o `null` es rechazado.
- [ ] Alias/CBU/CVU vacíos, sólo espacios o `null` son rechazados si no hay otro dato de cuenta.
- [ ] Los campos vacíos/sólo espacios de Transferencia **no se persisten como claves con valor vacío** y el modal no muestra filas vacías para configuraciones nuevas o editadas.
- [ ] Se documenta que las configuraciones históricas con `''`/`null` pueden seguir mostrando filas vacías hasta F7A.
- [ ] Editar una Transferencia válida actualiza la misma fila (mismo `provider`) y no pisa el otro dato de CBU/CVU.
- [ ] Editar una configuración incompleta exige completar titular/grupo y no pierde el dato original si falla.
- [ ] Admin B no ve, no elimina y no modifica configuraciones de A.
- [ ] Cajero sigue recibiendo 403.
- [ ] Otros métodos conservan su comportamiento, incluido `display_data` ausente/null y whitespace.
- [ ] El formulario muestra Titular como obligatorio y aclara “al menos uno de Alias/CBU/CVU”.
- [ ] El resumen y los errores inline no se contradicen ni se duplican.
- [ ] El foco respeta F2, incluido el nuevo input CBU y el resumen cuando sólo falla el grupo.
- [ ] No se modifica la estructura de la base de datos, no se agregan migraciones y no se alteran POS, checkout público, Mercado Pago ni permisos.
- [ ] Pasan los tests backend, `npm run build` y el QA manual.
- [ ] El informe de BUILD declara el alcance parcial de F7B/C-03.
- [ ] No se introducen cambios ajenos en el working tree.

---

## 7. Alcance exacto autorizable para BUILD

### 7.1. Autorizado

- `app/Http/Controllers/ConfiguracionController.php`
  - `storePaymentMethodConfig()`: validación condicional para `TRANSFERENCIA`, import de `Validator`, error grupal, `unset` de campos vacíos, `updateOrCreate` con el array normalizado.
  - Sin cambios en `index`, `update` (Tienda), `destroyPaymentMethodConfig` ni en las reglas de otros métodos.
- `resources/js/Pages/Configuracion/Index.vue`
  - `esTransferenciaPmc`, `valorCompletadoPmc`, `validarTransferenciaPmc`, `enfocarPrimerErrorPmc`, `mensajeResumenPmc` (definido), `cambiarMetodoPagoPmc` (incluye `metodo_pago`), `mostrarResumenErroresPmc`, `editPmc`, `idCampoEnfocablePmc`, `guardarPmc`.
  - Template: dos inputs CVU/CBU, marcas de obligatorio, ayuda del grupo, error grupal una sola vez, `aria-required` en Titular, accesibilidad del grupo.
- `tests/Feature/ConfiguracionMediosPagoTest.php`: reemplazo del test contradictorio y matriz ampliada.
- `tests/Feature/CrossTenantAttackTest.php` (o el mismo archivo): tests cross-tenant de PMC.
- `docs/informes/BUILD-VALIDACION-TRANSFERENCIA-POS.md`: informe de BUILD.
- Actualización de estado de C-03/F7B en `docs/informes/PLAN-CONSOLIDADO-MEDIOS-PAGO-POS.md` si el BUILD lo considera necesario.

### 7.2. No autorizado en este BUILD

- Migraciones, columnas, checks, cambios de modelo o del unique.
- `resources/js/Components/ConfirmarPagoModal.vue` (salvo que se autorice F7A; en ese caso, el alcance y los tests cambian).
- POS, ventas, caja, ticket, checkout público, tienda, `comercios.transferencia_*`, `OnboardingService`.
- Rutas, middleware, permisos, policies.
- Dependencias, `composer.json`, `package.json`.
- Refactors masivos de Pint, F3/C-01, F4/F5, F7A.
- Cambios ajenos de B3/B3.2, `.opencode/`, `OPENCODE-SKILLS.md`.
- Commits automáticos.

### 7.3. Condición de autorización

El BUILD puede autorizarse **sólo después** de que el plan incorpore las correcciones H-01 a H-05 y amplíe la matriz de tests con H-06 a H-09. Las correcciones H-10 a H-13 y H-19/H-20 son obligatorias de documentación/QA, no de rediseño.

---

## 8. Confirmación de no modificación

Esta revisión fue exclusivamente de análisis.

- No se modificó código de producción.
- No se modificaron tests.
- No se crearon ni modificaron migraciones.
- No se modificó la estructura de la base de datos ni se ejecutaron migraciones.
- No se modificaron dependencias.
- No se modificaron configuraciones.
- No se modificaron rutas, permisos, policies, POS, ventas, caja, checkout público ni integraciones de pago.
- No se modificó el plan original `docs/informes/PLAN-VALIDACION-TRANSFERENCIA-POS.md`.
- No se modificó `AGENTS.md`, `.opencode/` ni `OPENCODE-SKILLS.md`.
- No se tocaron los cambios preexistentes de B3/B3.2 ni archivos relacionados.
- No se ejecutaron tests ni builds.
- No se creó ningún commit.
- El único archivo nuevo de esta tarea es este documento: `docs/informes/REVIEW-PLAN-VALIDACION-TRANSFERENCIA-POS.md`.

La autorización de BUILD queda sujeta a las correcciones obligatorias de la sección 4 y a la ampliación de tests de la sección 5.
