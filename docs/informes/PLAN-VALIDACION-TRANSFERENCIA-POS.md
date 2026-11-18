# PLAN — Validación obligatoria de datos para Transferencia (Medios de Pago POS)

- **Fecha:** 2026-11-18
- **Revisión:** corrige las observaciones obligatorias de `docs/informes/REVIEW-PLAN-VALIDACION-TRANSFERENCIA-POS.md` y los hallazgos N-01 a N-07 de `docs/informes/REVIEW-PLAN-VALIDACION-TRANSFERENCIA-POS-2.md`.
- **Modo:** PLAN exclusivo. No implementa código, tests, migraciones, cambios de base de datos, dependencias ni configuraciones.
- **Estado:** propuesta corregida para nueva revisión. **No implementado.**
- **Base:** `f144e18` (`feat(frontend): completar Etapa 1 y F2 de medios de pago POS`).
- **Alcance:** configuración de medios de pago POS, únicamente método `TRANSFERENCIA`, al crear y al editar. Sin backfill.

---

## 1. Objetivo

Impedir que se guarde una configuración de medio de pago POS de tipo **Transferencia** sin los datos mínimos para que el cliente pueda pagar, tanto al crear como al editar.

Regla de negocio aprobada:

- `display_data.titular`: obligatorio.
- Al menos uno de `display_data.alias`, `display_data.cbu` o `display_data.cvu`: obligatorio.
- `display_data.banco`: opcional.
- La regla aplica sólo a `TRANSFERENCIA`.
- No se modifican las reglas de Efectivo, Débito, Crédito, Mercado Pago, viüMi, Cuenta Corriente ni de cualquier otro método.
- La regla se aplica a crear y a editar. Una configuración histórica incompleta no puede actualizarse sin completar los datos obligatorios.
- No hay backfill ni reinterpretación automática de datos históricos.

Resultado esperado:

- Validación en frontend y backend.
- Indicación visual de campos obligatorios y del grupo “al menos uno”.
- Si la validación falla: no se persiste, se muestra un mensaje general, errores junto a los campos, se conservan los valores y se enfoca el primer error respetando F2.
- Valores vacíos, cadenas con sólo espacios y `null` se consideran faltantes.
- Para Transferencia, las claves conocidas con valor `null`, vacío o sólo espacios se eliminan con `unset` antes de persistir; no se guardan claves vacías ni `null` explícito.
- Con `bail`, un `display_data.titular` `null` devuelve un único mensaje de titular requerido, sin acumular mensajes de `string`/`regex`.

---

## 2. Comportamiento actual comprobado

### 2.1. Backend

- Archivo: `app/Http/Controllers/ConfiguracionController.php`
- Método: `storePaymentMethodConfig()`

Comportamiento actual:

- Valida `metodo_pago` contra `MetodoPago::values()`.
- `display_data` es `nullable|array`.
- Todos los campos de `display_data` son `nullable`:
  - `display_data.alias`: `nullable|string|max:255`.
  - `display_data.cvu`: `nullable|string|regex:/^[0-9]*$/|max:255`.
  - `display_data.cbu`: `nullable|string|regex:/^[0-9]*$/|max:255`.
  - `display_data.banco`: `nullable|string|regex:/^[\pL\s\.]*$/u|max:255`.
  - `display_data.titular`: `nullable|string|regex:/^[\pL\s\.\,]*$/u|max:255`.
- Persiste con `PaymentMethodConfiguration::updateOrCreate()` usando:
  - `comercio_id`
  - `metodo_pago`
  - `provider`
- Guarda `display_data` como JSON y `channel = MANUAL`.

Consecuencia comprobada: una Transferencia con `display_data` vacío o sólo con titular se guarda sin errores.

### 2.2. Base de datos

Archivo: `database/migrations/2026_06_14_000005_create_payment_method_configurations_table.php`

- `display_data` es `json()->nullable()`.
- No hay restricciones de base de datos sobre el contenido del JSON.
- Existe un unique por `(comercio_id, metodo_pago, provider)`.
- No se necesita migración para esta regla: la validación es de aplicación.

### 2.3. Frontend

Archivo: `resources/js/Pages/Configuracion/Index.vue`

Comportamiento actual:

- `pmcForm` contiene `metodo_pago`, `provider`, `display_data` y `enabled`.
- `display_data` contiene `alias`, `cvu`, `cbu`, `banco` y `titular`.
- El formulario muestra:
  - Alias.
  - Un único campo rotulado **“CVU / CBU”**.
  - Banco.
  - Titular.
- El campo **“CVU / CBU”** está vinculado solamente a `pmcForm.display_data.cvu`.
- `display_data.cbu` existe en el estado del formulario, pero **no tiene input propio**.
- El error de `display_data.cbu` se muestra debajo del mismo input “CVU / CBU”.
- Históricamente, el input compartido “CVU / CBU” guardó CBU bajo `cvu`; ese dato no se puede distinguir automáticamente de un CVU real.
- F2 ya implementó:
  - Errores inline por campo.
  - Resumen de errores.
  - Limpieza de errores al editar.
  - Foco en el primer campo con error.
  - Conservación de valores ante errores del servidor.
  - Prevención de envíos duplicados.
- `guardarPmc()` no hace validación de negocio en frontend: envía y deja que el backend valide.
- `mostrarResumenErroresPmc` sólo muestra el resumen si hay más de un error o si hay un error general.
- `onError` enfoca el primer campo de `camposPmcConErrorInline`, excluyendo `display_data`, y si no encuentra ninguno enfoca `#pmc-error-summary`.

### 2.4. Consumo de `display_data` en POS

Archivos relevantes, sin cambios previstos:

- `app/Http/Controllers/PosController.php`: expone `display_data` de configuraciones habilitadas.
- `app/Http/Controllers/VentaController.php`: lo devuelve para pagos manuales y lo carga en `loadAllConfigs()`.
- `resources/js/Components/ConfirmarPagoModal.vue`: lo muestra en el modal de confirmación.
- `resources/js/Pages/Pos/Terminal.vue`: lo transporta desde la configuración.

Esta regla sólo impide guardar configuraciones nuevas o editadas incompletas. No modifica el flujo de cobro, la venta, el ticket ni el modal. Las configuraciones históricas no se corrigen automáticamente.

### 2.5. Tests existentes

Archivo: `tests/Feature/ConfiguracionMediosPagoTest.php`

Los tests de aislamiento cross-tenant de PMC se ampliarán en el archivo existente `tests/Feature/CrossTenantAttackTest.php` (o en el archivo específico de cross-tenant que ya exista), sin crear infraestructura nueva.

- `test_admin_a_puede_guardar_un_medio_de_pago_valido`: envía Transferencia con alias, CVU, CBU, banco y titular. Debe seguir siendo válido. Si envía `cbu => ''`, el nuevo backend lo elimina; el test debe seguir pasando y puede agregar `assertArrayNotHasKey('cbu', ...)`.
- `test_transferencia_sin_datos_de_display_se_guarda_sin_errores`: **contradice la nueva regla**. Hoy espera éxito con `display_data => []`. En la fase de implementación debe reemplazarse por un test de rechazo.
- `test_metodo_de_pago_es_obligatorio_y_no_persiste`: debe seguir devolviendo sólo el error de `metodo_pago`.
- `test_medio_de_pago_invalido_devuelve_errores_de_campos_y_no_persiste`: debe conservar exactamente los errores actuales, sin agregar errores de Transferencia.
- `test_display_data_invalido_devuelve_error_general_y_no_persiste`: debe seguir devolviendo sólo el error de formato de `display_data`, sin duplicar con titular/grupo.
- `test_cajero_no_puede_guardar_medios_de_pago`: debe seguir devolviendo `403` y no persistir.

### 2.6. Matriz de comportamiento actual

| Caso | Comportamiento actual | Esperado después del cambio |
|---|---|---|
| Transferencia sin datos | Se guarda | Rechazada |
| Transferencia sólo con titular | Se guarda | Rechazada |
| Transferencia con alias + titular | Se guarda | Se guarda |
| Transferencia con CBU + titular | Se guarda por backend | Se guarda bajo `cbu` |
| Transferencia con CVU + titular | Se guarda | Se guarda bajo `cvu` |
| Transferencia con CBU + CVU + titular | Se guarda | Se guardan ambos, cada uno en su clave |
| Transferencia con alias + CBU + CVU + titular | Se guarda | Se guarda |
| Transferencia sin banco | Se guarda | Se guarda |
| Transferencia con titular vacío o sólo espacios | Se guarda | Rechazada |
| Transferencia con CBU/CVU/alias con sólo espacios | Se guarda con valores en blanco | Se rechaza si no hay otro dato; si hay otro, se eliminan las claves vacías |
| Transferencia con CBU y CVU simultáneos | Se guarda | Se guardan ambos |
| Edición de una Transferencia incompleta | Se guarda | Se rechaza hasta completar los datos |
| Otros métodos con `display_data` vacío, ausente o `null` | Se guardan | Sin cambios |
| Otros métodos con cadenas de espacios | Se guardan tal cual | Sin cambios; no se normalizan |
| Usuario sin permisos | `403` | Sin cambios |
| Admin B intenta ver/eliminar/guardar PMC de A | Según tests actuales | Se agregan tests cross-tenant; sin cambios de comportamiento |

---

## 3. Archivos y métodos afectados

| Archivo | Método / sección | Tipo de cambio previsto |
|---|---|---|
| `app/Http/Controllers/ConfiguracionController.php` | `storePaymentMethodConfig()` | Reglas condicionales para Transferencia, mensaje de titular, error de grupo Alias/CBU/CVU y normalización con `unset` de claves vacías. Aplica a crear y editar. |
| `resources/js/Pages/Configuracion/Index.vue` | Script del formulario PMC | Validación frontend de Transferencia, foco reutilizable, resumen condicionado, limpieza de errores (incluido `metodo_pago`) y separación CBU/CVU. |
| `resources/js/Pages/Configuracion/Index.vue` | Template del formulario PMC | Marcas de obligatorio, ayuda del grupo, inputs independientes Alias/CBU/CVU/Banco/Titular, errores inline y mensaje general. |
| `tests/Feature/ConfiguracionMediosPagoTest.php` | Tests de feature | Reemplazo del test que permite Transferencia vacía y ampliación de la matriz (crear, editar, CBU/CVU, otros métodos, ausencia de claves vacías). |
| `tests/Feature/CrossTenantAttackTest.php` (o archivo existente de cross-tenant) | Tests de aislamiento PMC | Ampliar tests cross-tenant de PMC. No crear infraestructura nueva. |
| `docs/informes/BUILD-VALIDACION-TRANSFERENCIA-POS.md` | Informe de BUILD | Se creará en la fase de implementación; debe registrar que la regla de Transferencia de este plan es la decisión de negocio aprobada por el usuario (D-03/D-05), que los textos de titular/grupo son un contrato compartido entre backend y frontend y el alcance parcial de F7B/C-03. |

### 3.1. Archivos que no se modifican

- `app/Models/PaymentMethodConfiguration.php`.
- `database/migrations/2026_06_14_000005_create_payment_method_configurations_table.php`.
- `routes/web.php` y middleware de permisos.
- `app/Http/Controllers/PosController.php`.
- `app/Http/Controllers/VentaController.php`.
- `resources/js/Components/ConfirmarPagoModal.vue`.
- `resources/js/Components/MpQrPanel.vue` (componente no referenciado; sin impacto actual).
- `resources/js/Pages/Pos/Terminal.vue`.
- `app/Models/Comercio.php` y los campos `transferencia_cbu`, `transferencia_alias`, `transferencia_titular`.
- `app/Services/OnboardingService.php`.
- Checkout público, tienda, Mercado Pago, viüMi y cualquier integración de pago.
- `docs/informes/REVIEW-PLAN-VALIDACION-TRANSFERENCIA-POS.md` (informe de revisión original).
- `docs/informes/REVIEW-PLAN-VALIDACION-TRANSFERENCIA-POS-2.md` (segunda revisión).
- `composer.json`, `package.json`, configuración de entorno o dependencias.

Durante el BUILD no se modifica este plan ni las dos revisiones.

> La tarjeta “Transferencia Bancaria” del tab **Tienda** usa `comercios.transferencia_*` y pertenece al checkout público/onboarding. Queda fuera de alcance. Si se quiere aplicar la misma regla allí, debe planificarse por separado porque afecta otro flujo.

---

## 4. Reglas de validación exactas

### 4.1. Definición de “dato informado”

Se considera faltante cuando el valor es:

- `null`.
- Cadena vacía `''`.
- Cadena compuesta únicamente por espacios, tabs o saltos de línea (`'   '`, `"\t\n"`).
- Campo ausente en `display_data`.

Para el grupo se usa `filled()` en backend y una función equivalente en frontend. `filled()` ya considera `null`, `''` y cadenas con sólo espacios como no informados.

La validación no recorta los valores con contenido. Para Transferencia, los campos conocidos de `display_data` (`alias`, `cbu`, `cvu`, `banco`, `titular`) que sean `null`, cadena vacía o cadena compuesta sólo por espacios se consideran faltantes y, si el guardado es válido, se eliminan con `unset` antes de persistir. No se guardan claves vacías ni `null` explícito para esos campos. Los valores con contenido se guardan tal como se recibieron. Esto no cambia datos existentes ni afecta a otros métodos.

La regla se aplica al crear y al editar. No se hace backfill: las configuraciones históricas incompletas no se modifican automáticamente; sólo se bloquea su actualización hasta que el usuario complete los datos obligatorios.

### 4.2. Backend: reglas por método

| Campo | Otros métodos | `TRANSFERENCIA` |
|---|---|---|
| `metodo_pago` | `required|string|in:...` | Igual |
| `provider` | `nullable|string|max:100` | Igual |
| `display_data` | `nullable|array` | Igual |
| `display_data.alias` | `nullable|string|max:255` | Igual; participa del grupo |
| `display_data.cvu` | `nullable|string|regex:/^[0-9]*$/|max:255` | Igual; participa del grupo |
| `display_data.cbu` | `nullable|string|regex:/^[0-9]*$/|max:255` | Igual; participa del grupo |
| `display_data.banco` | `nullable|string|regex:/^[\pL\s\.]*$/u|max:255` | Igual; **nunca obligatorio** |
| `display_data.titular` | `bail|nullable|string|regex:/^[\pL\s\.\,]*$/u|max:255` | `bail|required|string|regex:/^[\pL\s\.\,]*$/u|max:255` |

Nota: en `TRANSFERENCIA`, `display_data.titular` es `required` sólo si `display_data` está ausente, es `null` o es un array. Si `display_data` no es un array, se mantiene `nullable` para no duplicar el error de formato `display_data.array`.

### 4.3. Regla de grupo Alias/CBU/CVU

Sólo para `TRANSFERENCIA`:

- Si `display_data` está ausente, es `null` o es un array, se evalúa el grupo (`$displayDataEsArray`).
- Si `display_data` tiene otro tipo (por ejemplo, string), no se evalúa el grupo: el error de formato `display_data.array` ya es suficiente y evita mensajes duplicados.
- Si `metodo_pago` no es exactamente `TRANSFERENCIA`, no se evalúa.
- Se considera cumplido si al menos uno de `alias`, `cbu` o `cvu` tiene valor informado según 4.1.
- Si no se cumple, se agrega **un único error** en la clave `display_data`.
- Si un campo del grupo es una cadena compuesta sólo por espacios, se descarta su error de formato (`regex`/`max`), se considera faltante y, si el guardado es válido, se elimina con `unset`. Así, un CVU/CBU con espacios no muestra “solo puede contener números” además del error de grupo ni genera una fila vacía en el POS.
- La normalización con `unset` cubre `alias`, `cbu`, `cvu`, `banco` y `titular`. Para `titular`, el error `required` se conserva; no se descarta.
- Esto evita modificar `ConfirmarPagoModal.vue`: el modal deja de recibir claves vacías en configuraciones nuevas o editadas. Las configuraciones históricas con claves vacías pueden seguir mostrando filas vacías hasta la fase F7A.
- No se agregan reglas `required` individuales a `alias`, `cbu` ni `cvu`.
- No se muestra “Alias es obligatorio” ni “CBU es obligatorio” ni “CVU es obligatorio”. El mensaje es grupal.

### 4.4. Mensajes exactos

| Clave de error | Mensaje |
|---|---|
| `display_data.titular` | `Ingresá el nombre del titular.` |
| `display_data` | `Completá al menos uno de Alias, CBU o CVU.` |
| `display_data.alias.max` | `El alias no debe superar los 255 caracteres.` |
| `display_data.cvu.regex` | `El CVU solo puede contener números.` |
| `display_data.cvu.max` | `El CVU no debe superar los 255 caracteres.` |
| `display_data.cbu.regex` | `El CBU solo puede contener números.` |
| `display_data.cbu.max` | `El CBU no debe superar los 255 caracteres.` |
| `display_data.banco.regex` | `El banco solo puede contener letras.` |
| `display_data.banco.max` | `El banco no debe superar los 255 caracteres.` |
| `display_data.titular.regex` | `El nombre del titular solo puede contener letras.` |
| `display_data.titular.max` | `El nombre del titular no debe superar los 255 caracteres.` |

Los mensajes existentes se conservan. Sólo se agrega `display_data.titular.required` y el mensaje grupal. Con `bail`, el caso `null` devuelve únicamente `display_data.titular.required`; no se agregan `display_data.titular.string` ni `display_data.titular.regex`.

### 4.5. Estrategia de validación backend

Se propone reemplazar `$request->validate(...)` por una instancia de `Validator` para poder acumular en una sola respuesta:

1. El error de titular requerido.
2. El error grupal de Alias/CBU/CVU.

Pseudocódigo propuesto:

```php
use Illuminate\Support\Facades\Validator;

$esTransferencia = $request->input('metodo_pago') === MetodoPago::TRANSFERENCIA->value;
$displayData = $request->input('display_data');
$displayDataEsArray = is_array($displayData) || is_null($displayData);

$reglas = [
    // Reglas actuales: se conservan todas.
    'metodo_pago' => 'required|string|in:' . implode(',', MetodoPago::values()),
    'provider' => 'nullable|string|max:100',
    'display_data' => 'nullable|array',
    'display_data.alias' => 'nullable|string|max:255',
    'display_data.cvu' => 'nullable|string|regex:/^[0-9]*$/|max:255',
    'display_data.cbu' => 'nullable|string|regex:/^[0-9]*$/|max:255',
    'display_data.banco' => 'nullable|string|regex:/^[\pL\s\.]*$/u|max:255',
    'display_data.titular' => $esTransferencia && $displayDataEsArray
        ? ['bail', 'required', 'string', 'regex:/^[\pL\s\.\,]*$/u', 'max:255']
        : ['bail', 'nullable', 'string', 'regex:/^[\pL\s\.\,]*$/u', 'max:255'],
    'enabled' => 'boolean',
];

// $messages: se conserva el array actual de mensajes y se agrega 'display_data.titular.required'.
$validator = Validator::make($request->all(), $reglas, $messages);

$validator->after(function ($validator) use ($request, $esTransferencia, $displayDataEsArray) {
    if (! $esTransferencia || ! $displayDataEsArray) {
        return;
    }

    $data = $request->input('display_data');
    $data = is_array($data) ? $data : [];

    $tieneDatoDeCuenta = filled($data['alias'] ?? null)
        || filled($data['cbu'] ?? null)
        || filled($data['cvu'] ?? null);

    if (! $tieneDatoDeCuenta) {
        $validator->errors()->add(
            'display_data',
            'Completá al menos uno de Alias, CBU o CVU.'
        );
    }

    // Los campos opcionales con sólo espacios se tratan como faltantes.
    // No se descarta el error required de titular.
    foreach (['alias', 'cbu', 'cvu', 'banco'] as $campo) {
        $valor = $data[$campo] ?? null;

        if (is_string($valor) && trim($valor) === '') {
            $validator->errors()->forget('display_data.' . $campo);
        }
    }
});

$validator->validate();

$displayDataParaGuardar = $request->input('display_data');

if ($esTransferencia && is_array($displayDataParaGuardar)) {
    foreach (['alias', 'cbu', 'cvu', 'banco', 'titular'] as $campo) {
        $valor = $displayDataParaGuardar[$campo] ?? null;

        if ($valor === null || (is_string($valor) && trim($valor) === '')) {
            unset($displayDataParaGuardar[$campo]);
        }
    }
}

PaymentMethodConfiguration::updateOrCreate(
    [
        'comercio_id' => $comercioId,
        'metodo_pago' => $request->metodo_pago,
        'provider' => $request->provider,
    ],
    [
        'channel' => PaymentChannel::MANUAL,
        'display_data' => $displayDataParaGuardar,
        'enabled' => $request->boolean('enabled', true),
    ]
);
```

Notas:

- `use Illuminate\Support\Facades\Validator;` debe agregarse al controlador.
- `$messages` es el array actual de mensajes; se le agrega `display_data.titular.required` y se conservan todos los demás.
- `$reglas` conserva todas las reglas actuales; sólo cambia `display_data.titular` (se agrega `bail` en ambas ramas y `required` sólo para Transferencia). No se relajan reglas de otros métodos.
- `filled()` resuelve espacios y `null`.
- Si `display_data` es un string, `$displayDataEsArray` es `false`: no se exige titular ni se agrega el grupo; sólo falla `display_data.array`.
- Si `metodo_pago` falta o es inválido, `$esTransferencia` es `false`: no se agregan reglas de Transferencia.
- Si `display_data` falta o es `null`, se exigen titular y grupo, y se devuelven ambos errores en la misma respuesta.
- `Validator::validate()` conserva el comportamiento de redirección y old input de Inertia.
- La normalización con `unset` ocurre después de validar, así que no altera los valores que Inertia conserva cuando hay errores.
- `unset` sobre una clave ausente es inocuo. No se guardan claves con `null`, `''` ni sólo espacios para los cinco campos conocidos.
- `updateOrCreate()` debe usar `$displayDataParaGuardar` en lugar de `$request->display_data`.
- Para `titular`, el error `required` se conserva. `bail` es obligatorio en ambas ramas: en Transferencia evita que un `null` explícito acumule `required` + `string` (mensaje por defecto en inglés) + `regex` sobre la misma clave y rompa la comparación exacta del frontend; en el resto corta en el primer error de tipo. En cadenas de sólo espacios Laravel ya omite las reglas no implícitas, así que `bail` no se justifica por ese caso.
- Opcional para robustez de payloads manipulados: agregar `display_data.titular.string` con un mensaje en español. No es un caso de UI.
- Si un payload manipulado envía un array en `display_data.titular`, `bail` corta en `string`; se puede agregar un mensaje `*.string` en español. No es un caso de UI.
- La regla se aplica a crear y editar porque `updateOrCreate` se usa en ambos casos.

### 4.6. Datos históricos ambiguos (CBU guardado bajo `cvu`)

- El input compartido “CVU / CBU” anterior escribía siempre en `display_data.cvu`.
- Por eso, algunos registros existentes pueden tener un CBU guardado bajo `cvu`.
- No se puede distinguir automáticamente si ese valor histórico es un CBU o un CVU.
- No se realiza backfill ni reinterpretación automática.
- El valor histórico se preserva en la clave donde está almacenado (`cvu`).
- La corrección manual se realiza desde la edición: el usuario ve el valor en el input CVU y puede moverlo al input CBU si corresponde.
- La separación de inputs no cambia la base de datos ni el contrato de `display_data`.

---

## 5. Cambios propuestos en frontend

### 5.1. Decisión sobre el campo “CVU / CBU”

Hallazgo actual:

- El input “CVU / CBU” escribe siempre en `display_data.cvu`.
- `display_data.cbu` no tiene input propio.
- Un CBU ingresado por el usuario se guarda como CVU.
- Un CBU existente en una configuración no se puede ver ni editar desde el formulario.

Decisión propuesta: **separar el campo en dos inputs independientes**, en este orden: Alias, CBU, CVU, Banco, Titular. Ambos CBU y CVU son opcionales individualmente y ambos participan del grupo que exige al menos uno.

Esto:

- No cambia la base de datos.
- No cambia el modelo ni el cast `array`.
- No cambia el contrato de `display_data`.
- Usa las claves existentes `alias`, `cbu`, `cvu`, `banco`, `titular`.
- Permite que la regla “alias, CBU o CVU” sea coherente con lo que se ve y se guarda.
- Corrige la edición de configuraciones existentes con CBU.
- No modifica las reglas de otros métodos: los campos siguen visibles y sin obligatoriedad para ellos.
- No agrega claves vacías a otros métodos al editar: `editPmc()` sólo normaliza los cinco campos para Transferencia.
- No reinterpreta datos históricos: un CBU histórico guardado bajo `cvu` se muestra en el input CVU y se preserva en esa clave hasta que el usuario lo corrija manualmente desde la edición.

Alternativa descartada: mantener un único campo “CVU / CBU” vinculado a `cvu`. Es menos cambio visual, pero deja `cbu` inaccesible y mantiene la ambigüedad semántica.

### 5.2. Script

Agregar:

- `esTransferenciaPmc`: `computed` que compara `pmcForm.metodo_pago === 'TRANSFERENCIA'`.
- `valorCompletadoPmc(valor)`: equivalente frontend de `filled()`.
- `validarTransferenciaPmc()`: valida titular y grupo sólo para Transferencia.
- `enfocarPrimerErrorPmc()`: extrae la lógica de foco que hoy está dentro de `onError`, para reutilizarla cuando falla la validación frontend.
- `hayErrorRequisitoTransferenciaPmc`: `computed` que detecta sólo errores de requisito mínimo (el `required` exacto de titular o el error grupal exacto), no errores de formato como `display_data.titular.regex` o `display_data.array`.
- `MENSAJE_GRUPO_PMC`: constante con el texto exacto `Completá al menos uno de Alias, CBU o CVU.`, usada para setear y reconocer el error grupal.
- `MENSAJE_TITULAR_PMC`: constante con el texto exacto `Ingresá el nombre del titular.`, usada para setear y reconocer sólo el error `required` de titular (no los errores de formato).
- Los textos de `MENSAJE_TITULAR_PMC` y `MENSAJE_GRUPO_PMC` deben coincidir exactamente con los que devuelve el backend (`display_data.titular.required` y el error grupal), porque la detección se hace por igualdad de texto.
- Se reutiliza `mensajeErrorPmc()` para normalizar el error (string o array) antes de comparar.
- `mensajeResumenPmc`: texto general exacto según qué requisito mínimo falta (titular, grupo o ambos), con el texto F2 genérico para el resto de los errores.
- `mostrarResumenErroresPmc`: se ajusta para no mostrar el resumen específico ante un único error de formato.
- `cambiarMetodoPagoPmc()`: conserva la limpieza actual de `metodo_pago` y agrega la limpieza de todos los errores de `display_data` y sus subcampos.

Pseudocódigo de validación:

```js
const esTransferenciaPmc = computed(
    () => pmcForm.metodo_pago === 'TRANSFERENCIA'
);

const MENSAJE_GRUPO_PMC = 'Completá al menos uno de Alias, CBU o CVU.';
const MENSAJE_TITULAR_PMC = 'Ingresá el nombre del titular.';

const valorCompletadoPmc = (valor) => {
    if (valor === null || valor === undefined) return false;
    if (typeof valor === 'string') return valor.trim().length > 0;
    if (Array.isArray(valor)) return valor.length > 0;
    return true;
};

function validarTransferenciaPmc() {
    if (!esTransferenciaPmc.value) return true;

    pmcForm.clearErrors('display_data', 'display_data.titular');

    let valido = true;
    const data = pmcForm.display_data || {};

    if (!valorCompletadoPmc(data.titular)) {
        pmcForm.setError(
            'display_data.titular',
            MENSAJE_TITULAR_PMC
        );
        valido = false;
    }

    if (![data.alias, data.cbu, data.cvu].some(valorCompletadoPmc)) {
        pmcForm.setError(
            'display_data',
            MENSAJE_GRUPO_PMC
        );
        valido = false;
    }

    return valido;
}
```

`guardarPmc()` debe:

1. Mantener la guardia `pmcForm.processing`.
2. Ejecutar `validarTransferenciaPmc()`.
3. Si falla, no enviar el POST y enfocar el primer error con `nextTick(enfocarPrimerErrorPmc)`.
4. Si pasa, enviar como hoy.
5. En `onError`, reutilizar `enfocarPrimerErrorPmc()`.

Resumen y mensaje general:

```js
const faltaTitularPmc = computed(() =>
    esTransferenciaPmc.value &&
    mensajeErrorPmc('display_data.titular') === MENSAJE_TITULAR_PMC
);

const faltaCuentaPmc = computed(() =>
    esTransferenciaPmc.value &&
    mensajeErrorPmc('display_data') === MENSAJE_GRUPO_PMC
);

const hayErrorRequisitoTransferenciaPmc = computed(() =>
    faltaTitularPmc.value || faltaCuentaPmc.value
);

const mensajeResumenPmc = computed(() => {
    if (faltaTitularPmc.value && faltaCuentaPmc.value) {
        return 'Completá el nombre del titular y al menos uno de Alias, CBU o CVU.';
    }

    if (faltaTitularPmc.value) {
        return 'Faltan los datos del titular de la transferencia.';
    }

    if (faltaCuentaPmc.value) {
        return 'Faltan los datos de la cuenta para la transferencia.';
    }

    return 'Revisá los datos marcados y volvé a intentar.';
});

const mostrarResumenErroresPmc = computed(() => {
    if (!erroresPmc.value.length) return false;
    if (hayErrorRequisitoTransferenciaPmc.value) return true;
    return erroresPmc.value.length > 1 || erroresGeneralesPmc.value.length > 0;
});
```

Esto cumple:

- Los textos exactos del resumen de requisito mínimo son:
  - Titular y grupo faltantes: `Completá el nombre del titular y al menos uno de Alias, CBU o CVU.`
  - Sólo titular faltante: `Faltan los datos del titular de la transferencia.`
  - Sólo grupo faltante: `Faltan los datos de la cuenta para la transferencia.`
  - Otros errores: `Revisá los datos marcados y volvé a intentar.`
- El resumen no repite textualmente el error inline cuando hay un único requisito: usa un texto propio de resumen. El error grupal se muestra una sola vez junto al grupo; el resumen sólo indica el faltante.
- Se muestra sólo cuando `esTransferenciaPmc` es verdadero y existe el error requerido de titular (`mensajeErrorPmc('display_data.titular') === MENSAJE_TITULAR_PMC`) o el error grupal exacto (`mensajeErrorPmc('display_data') === MENSAJE_GRUPO_PMC`). La comparación por texto exacto evita que errores de formato en las mismas claves (`display_data.titular.regex`, `display_data.array`) activen el resumen específico. No se muestra para un único error de formato (por ejemplo, CVU no numérico o titular con números), porque en ese caso `hayErrorRequisitoTransferenciaPmc` es `false` y F2 sólo muestra el resumen si hay más de un error o un error general.
- Se ubica en el segundo `<p>` del bloque de resumen F2 existente, reemplazando sólo el texto fijo; el título `No se pudo guardar el medio de pago.` no cambia. No se crea un componente nuevo.
- No repite textualmente los errores inline de un único requisito: el resumen usa un texto propio; los errores de titular y grupo siguen inline. En el caso de ambos faltantes, el resumen combina los dos requisitos en una sola frase.
- Si hay un error de formato y además un error de requisito, se muestra el mensaje específico porque `hayErrorRequisitoTransferenciaPmc` es verdadero; los errores inline se muestran todos.

`cambiarMetodoPagoPmc()`:

```js
function cambiarMetodoPagoPmc() {
    // Conserva la limpieza actual de F2.
    limpiarErrorPmc('metodo_pago');

    // Agrega la limpieza de Transferencia.
    pmcForm.clearErrors(
        'display_data',
        'display_data.alias',
        'display_data.cbu',
        'display_data.cvu',
        'display_data.banco',
        'display_data.titular'
    );
}
```

El template debe usar `@change="cambiarMetodoPagoPmc()"` en el select de `metodo_pago`, reemplazando el `@change="limpiarErrorPmc('metodo_pago')"` actual.

`editPmc()` debe preparar `display_data` según el método, sin perder claves desconocidas:

- **Transferencia:** cargar los cinco campos independientes para edición.
- **Otros métodos:** conservar el comportamiento actual (`{ ...(pmc.display_data || {}) }`), sin agregar claves vacías nuevas ni normalizar/eliminar claves.

```js
pmcForm.display_data = pmc.metodo_pago === 'TRANSFERENCIA'
    ? {
        ...(pmc.display_data || {}),
        alias: pmc.display_data?.alias ?? '',
        cbu: pmc.display_data?.cbu ?? '',
        cvu: pmc.display_data?.cvu ?? '',
        banco: pmc.display_data?.banco ?? '',
        titular: pmc.display_data?.titular ?? '',
    }
    : { ...(pmc.display_data || {}) };
```

`resetPmcForm()` ya reinicia todas las claves; se mantiene.

`idCampoEnfocablePmc()` debe dejar de redirigir `display_data.cbu` hacia el input de CVU, porque ahora CBU tendrá su propio input. Debe devolver el id propio de cada campo.

`camposPmcConErrorInline` debe respetar el orden visual Alias, CBU, CVU, Banco, Titular:

```js
const camposPmcConErrorInline = [
    'metodo_pago',
    'provider',
    'display_data',
    'display_data.alias',
    'display_data.cbu',
    'display_data.cvu',
    'display_data.banco',
    'display_data.titular',
    'enabled',
];
```

### 5.3. Template

Cambios previstos:

- Mantener el resumen F2 al inicio del formulario.
- Reemplazar el texto fijo del resumen por `mensajeResumenPmc`.
- Mostrar las marcas de obligatorio sólo cuando `esTransferenciaPmc` sea verdadero:
  - `Titular *`.
  - Ayuda del grupo: “Completá al menos uno de Alias, CBU o CVU.”
  - `Banco (opcional)` para dejar clara la regla.
- Separar el campo en cinco inputs independientes, en este orden visual: Alias, CBU, CVU, Banco, Titular.
- Cada input de Alias/CBU/CVU/Banco/Titular debe tener:
  - `id` propio (`pmc-display-data-alias`, `pmc-display-data-cbu`, `pmc-display-data-cvu`, `pmc-display-data-banco`, `pmc-display-data-titular`).
  - `v-model` a la clave existente correspondiente de `display_data`.
  - `aria-invalid`.
  - `aria-describedby`.
  - Mensaje inline propio.
  - `@input="limpiarErrorPmc('display_data.<campo>')"`.
- Envolver el grupo Alias/CBU/CVU en un contenedor con `role="group"` y `aria-describedby` apuntando a la ayuda y al error grupal.
- Mostrar el error grupal `display_data` una sola vez, junto al grupo Alias/CBU/CVU y antes de Banco/Titular. No repetirlo al pie del formulario.
- Mantener el error de titular debajo del input Titular.
- Cambiar el `@change` de `metodo_pago` para llamar a `cambiarMetodoPagoPmc()`, que conserva la limpieza de `metodo_pago` y limpia todos los errores de `display_data` (alias, cbu, cvu, banco, titular y el error grupal) al cambiar de método.
- No agregar `required` ni `pattern` HTML al formulario PMC: el formulario usa `novalidate` y F2 ya centraliza los errores. La validación de negocio se hace en JS y en backend.
- Agregar `aria-required="true"` a Titular cuando sea Transferencia.
- Mantener `role="alert"`, `aria-live` y `tabindex="-1"` del resumen.

### 5.4. Comportamiento de errores y foco

| Situación | Mensaje general | Errores inline | Foco |
|---|---|---|---|
| Transferencia sin titular ni cuenta | Sí: `Completá el nombre del titular y al menos uno de Alias, CBU o CVU.` | Titular + grupo | Titular (primer campo con error; el grupo no es un input) |
| Transferencia con titular, sin cuenta | Sí: `Faltan los datos de la cuenta para la transferencia.` | Sólo grupo | Resumen (`#pmc-error-summary`) |
| Transferencia sin titular, con alias | Sí: `Faltan los datos del titular de la transferencia.` | Sólo titular | Titular |
| Transferencia con CVU inválido y titular válido, único error | No (F2: un solo error inline) | Sólo CVU | CVU |
| Transferencia con CBU inválido y titular válido, único error | No | Sólo CBU | CBU |
| Transferencia con titular con formato inválido y cuenta válida, único error | No | Sólo titular | Titular |
| Transferencia con un error de formato y otro de requisito | Sí, específico (hay requisito) | Formato + titular/grupo | Primer campo con error según orden del DOM |
| Transferencia con un error de formato y otro de formato | Sí, resumen F2 (más de un error) | Ambos formatos | Primer campo con error |
| Otro método con un solo error | Se mantiene el comportamiento F2 actual | Campo correspondiente | Campo correspondiente |
| Otro método con varios errores | Se mantiene el resumen F2 actual | Campos correspondientes | Primer campo correspondiente |

El resumen no repite textualmente los mensajes inline cuando hay un único requisito: usa un texto propio. El mensaje grupal aparece una única vez junto al grupo.

### 5.5. Conservación de valores

- La validación frontend no resetea `pmcForm`.
- No se llama a `resetPmcForm()` en errores.
- Los valores permanecen en el formulario.
- Si el error viene del backend, Inertia conserva los valores como en F2.
- No se modifica `onSuccess`: sigue reseteando sólo cuando el guardado fue exitoso.

---

## 6. Cambios propuestos en backend

### 6.1. `ConfiguracionController::storePaymentMethodConfig()`

- Mantener todas las reglas actuales.
- Agregar `use Illuminate\Support\Facades\Validator;` al controlador.
- Detectar Transferencia con `MetodoPago::TRANSFERENCIA->value`.
- Agregar `bail` como primera regla de `display_data.titular` en ambas ramas.
- Aplicar `required` a `display_data.titular` sólo cuando:
  - `metodo_pago` es Transferencia, y
  - `display_data` está ausente, es `null` o es un array.
- `bail` evita que un `null` acumule `required`, `string` y `regex` sobre la misma clave.
- Agregar el error grupal en `display_data` sólo cuando:
  - `metodo_pago` es Transferencia,
  - `display_data` está ausente, es `null` o es un array, y
  - `alias`, `cbu` y `cvu` están todos vacíos según `filled()`.
- No exigir `banco`.
- No agregar reglas de obligatoriedad ni de formato a otros métodos; sólo se agrega `bail` como modificador de `display_data.titular` en ambas ramas.
- Después de validar, para Transferencia, eliminar con `unset` los campos conocidos de `display_data` (`alias`, `cbu`, `cvu`, `banco`, `titular`) cuando su valor sea `null`, cadena vacía o cadena compuesta sólo por espacios. No se guardan claves vacías ni `null` explícito para esos campos. Los valores con contenido se conservan tal como se recibieron.
- Mantener `updateOrCreate`, `channel`, `enabled` y `provider`; sólo cambiar el valor de `display_data` por el array normalizado.
- La regla se aplica a crear y editar porque `updateOrCreate` se usa en ambos casos. Una configuración histórica incompleta no puede actualizarse sin completar los datos obligatorios.
- No se hace backfill ni se modifican registros existentes.
- No modificar la respuesta: sigue siendo redirect con errores de sesión/Inertia y `success` en el caso válido.

### 6.2. Mensajes

Agregar únicamente:

```php
'display_data.titular.required' => 'Ingresá el nombre del titular.',
```

El mensaje grupal se agrega directamente en el `after()` del validador.

Con `bail`, no se necesita un mensaje `display_data.titular.string` para el caso `null`; si se agrega por robustez ante payloads manipulados, debe ser en español.

### 6.3. Lo que no se toca

- No se agrega una columna ni un check constraint.
- No se modifica el modelo `PaymentMethodConfiguration` ni el cast `array`.
- No se modifica la migración.
- No se modifica el unique.
- No se modifica el route binding ni los permisos.
- No se agrega una policy nueva.
- No se modifica el POS, la venta, la caja, el ticket ni el modal de confirmación.
- No se modifica `ConfirmarPagoModal.vue`; la ausencia de claves vacías evita filas vacías en configuraciones nuevas o editadas.
- No se modifica el checkout público ni la tienda.
- No se agrega backfill ni se reescriben datos históricos.

---

## 7. Tests previstos

### 7.1. Tests automatizados de backend

Archivos:

- `tests/Feature/ConfiguracionMediosPagoTest.php`: reglas, normalización, actualización, CBU/CVU y regresión.
- `tests/Feature/CrossTenantAttackTest.php` (o el archivo existente de cross-tenant): aislamiento de PMC. No se crea infraestructura nueva.

#### 7.1.1. Requisitos mínimos y normalización

| # | Test | Payload principal | Resultado esperado |
|---|---|---|---|
| 1 | Transferencia sin datos | `display_data => []` | Errores `display_data.titular` y `display_data`; no persiste |
| 2 | Transferencia con `display_data` ausente | sin clave `display_data` | Errores titular y grupo; no aparece `display_data.array`; no persiste |
| 3 | Transferencia con `display_data => null` | `display_data => null` | Errores titular y grupo; no aparece `display_data.array`; no persiste |
| 4 | Transferencia sólo con titular | `titular => 'Juan Pérez'` | Error `display_data`; no persiste |
| 5 | Transferencia con alias + titular | `alias`, `titular`, sin banco | Sin errores; persiste; `cbu` y `cvu` ausentes |
| 6 | Transferencia con CBU + titular | `cbu` numérico, `titular` | Sin errores; persiste; `cbu` con el valor; `cvu` ausente |
| 7 | Transferencia con CVU + titular | `cvu` numérico, `titular` | Sin errores; persiste; `cvu` con el valor; `cbu` ausente |
| 8 | Transferencia con CBU + CVU + titular | ambos + `titular` | Sin errores; persiste; ambos con sus valores |
| 9 | Transferencia con todos los datos | `alias`, `cbu`, `cvu`, `banco`, `titular` | Sin errores; persiste; todos los valores |
| 10 | Transferencia sin banco | `alias` + `titular` | Sin errores; persiste; `banco` ausente |
| 11 | Titular vacío / sólo espacios / `null` | `alias` válido + `titular => ''`, `'   '`, `null` | Sólo error `display_data.titular`; con `null`, `bail` garantiza únicamente el mensaje `required` (sin `string` ni `regex`); no persiste. El contrato exacto de `null` se cubre en el test 48. |
| 12 | Grupo vacío / sólo espacios / `null` (con titular válido) | `titular` válido + `alias`, `cbu`, `cvu` en `''`, `'   '`, `null` | Sólo error `display_data`; sin regex de CVU/CBU; no persiste |
| 13 | Banco + titular sin cuenta | `banco`, `titular` | Error `display_data` (banco no cuenta); no persiste |
| 14 | Alias válido + CBU no numérico | `alias`, `cbu => 'abc'`, `titular` | Sólo error `display_data.cbu.regex`; sin `display_data`; no persiste |
| 15 | Alias válido + CVU no numérico | `alias`, `cvu => 'abc'`, `titular` | Sólo error `display_data.cvu.regex`; sin `display_data`; no persiste |
| 16 | Alias válido + banco inválido | `alias`, `banco => '123'`, `titular` | Sólo error `display_data.banco.regex`; sin `display_data`; no persiste |
| 17 | Alias válido + titular inválido | `alias`, `titular => '123'` | Sólo error `display_data.titular.regex`; sin `display_data`; no persiste |
| 18 | Whitespace en alias/CBU/CVU/banco sin otro dato | `titular` + `alias => '   '`, `cbu => '   '`, `cvu => '   '`, `banco => '   '` | Sólo error `display_data`; sin regex de CVU/CBU; no persiste |
| 19 | Whitespace en un campo con otro válido | `titular`, `alias => '   '`, `cvu => '123'` | Sin errores; persiste; `alias` ausente; `cvu` con valor |
| 20 | Ausencia de claves vacías | `titular`, `alias`, `cvu` válidos, `cbu => ''`, `banco => '   '` | Sin errores; persiste; `cbu`/`banco` ausentes; `alias`/`cvu`/`titular` presentes; no `null` |

Caso adicional (H-09): `display_data` con sólo claves desconocidas (por ejemplo `['foo' => 'bar']`) debe devolver errores `display_data.titular` y `display_data`, sin persistir.

#### 7.1.2. Actualización y separación CBU/CVU

| # | Test | Payload principal | Resultado esperado |
|---|---|---|---|
| 21 | Actualización válida | PMC válida con `provider` fijo + POST válido con el mismo `provider` | `assertDatabaseCount(..., 1)`; `display_data` actualizado; `comercio_id` igual |
| 22 | Actualización inválida | PMC válida + POST sin titular/grupo con el mismo `provider` | Errores; `display_data` original sin cambios; count 1 |
| 23 | Actualización de configuración incompleta | PMC histórica incompleta + POST sin titular | Error; original sin cambios; luego POST con titular+alias -> se guarda |
| 24 | Actualización CBU→CVU | PMC con `cbu` + POST con `cvu`, `titular` y `cbu => ''` | `cvu` con el valor; `cbu` ausente |
| 25 | Actualización CVU→CBU | PMC con `cvu` + POST con `cbu`, `titular` y `cvu => ''` | `cbu` con el valor; `cvu` ausente |
| 26 | CBU y CVU simultáneos | POST con ambos + titular | Ambos con sus valores |
| 27 | Edición conserva y muestra | PMC con `cbu` y `cvu` + POST con ambos + `titular` | Backend: ambos valores; frontend manual: cada uno en su input |

#### 7.1.3. Regresión de otros métodos y ausencia de errores

| # | Test | Payload principal | Resultado esperado |
|---|---|---|---|
| 28 | Otros métodos con `display_data` vacío | `EFECTIVO`, `DEBITO`, `CREDITO`, `MERCADO_PAGO`, `CUENTA_CORRIENTE`, `VIUMI` + `[]` | Sin errores; persiste |
| 29 | Otros métodos con `display_data` ausente | idem sin clave | Sin errores; persiste |
| 30 | Otros métodos con `display_data => null` | idem `null` | Sin errores; persiste |
| 31 | Otros métodos con cadenas de espacios permitidas por su regex | `EFECTIVO` + `alias => '   '`, `banco => '   '`, `titular => '   '` | Sin errores; persiste; las cadenas se conservan tal cual (no se normalizan) |
| 31b | Otros métodos con CBU/CVU de espacios | `EFECTIVO` + `cbu => '   '`, `cvu => '   '` | Errores existentes `display_data.cbu.regex` y `display_data.cvu.regex`; sin `display_data` ni `display_data.titular`; no persiste |
| 32 | Otro método con CVU inválido | `EFECTIVO` + `cvu => 'abc'` | Sólo `display_data.cvu.regex`; sin errores de Transferencia; no persiste |
| 33 | `display_data` string en Transferencia | `display_data => 'no-es-un-array'` | Sólo `display_data.array`; sin `display_data.titular` ni grupo; no persiste |
| 34 | Método ausente | sin `metodo_pago`, `display_data => []` | Sólo `metodo_pago`; sin errores de Transferencia; no persiste |
| 35 | Método inválido | `metodo_pago => 'NO_EXISTE'`, campos inválidos | Errores actuales de campos; sin errores de Transferencia; no persiste |

#### 7.1.4. Permisos y multi-tenant

| # | Test | Payload principal | Resultado esperado |
|---|---|---|---|
| 36 | Cajero sin permisos | Transferencia válida | `403`; no persiste |
| 37 | Cross-tenant index | Admin B GET configuración | No ve PMC de A (`assertInertia`) |
| 38 | Cross-tenant delete | Admin B DELETE PMC de A | `403`; PMC de A sigue |
| 39 | Cross-tenant store | Admin B POST Transferencia | PMC creada con `comercio_id` de B; PMC de A sin cambios |

#### 7.1.5. Robustez (recomendados, no bloqueantes)

| # | Test | Resultado esperado |
|---|---|---|
| 40 | Claves desconocidas en `display_data` | Se preservan |
| 41 | Valores con contenido exactos | `assertSame` de alias/titular/banco/CBU/CVU |
| 42 | `'0'` como alias/CBU/CVU | Se considera informado; persiste |
| 43 | Límites 255/256 | `max` en el campo correspondiente |
| 44 | `titular` con comas/puntos | Válido según regex actual |

Los casos 11, 12 y 18-20 pueden implementarse con `#[DataProvider]` para no duplicar código. No se agrega Pest ni una infraestructura nueva.

Nota sobre actualización: para probar actualización se debe usar el mismo `provider` que la fila existente. Si la fila tiene `provider = null` y el POST envía `provider = ''`, `updateOrCreate` puede crear otra fila por el unique de PostgreSQL. Es la deuda F3/C-01 y no se corrige en esta fase.

Nota sobre ausencia de errores: `assertSessionHasErrors` no prueba que un error no exista. Para verificar ausencia usar `session('errors')->has('display_data')` o `assertSessionDoesntHaveErrors([...])` según la versión de Laravel.

#### 7.1.6. Contrato de mensajes (obligatorio)

Estos tests fijan los textos exactos que el frontend usa para reconocer el error de titular y el error grupal. Si cambia un texto, deben actualizarse backend, frontend y estos tests.

| # | Test | Resultado esperado |
|---|---|---|
| 45 | Titular faltante: mensaje exacto | Transferencia con alias válido y sin titular: `assertSessionHasErrors(['display_data.titular' => 'Ingresá el nombre del titular.'])`; no persiste |
| 46 | Grupo faltante: mensaje exacto | Transferencia con titular válido y sin alias/CBU/CVU: `assertSessionHasErrors(['display_data' => 'Completá al menos uno de Alias, CBU o CVU.'])`; no persiste |
| 47 | Ambos faltantes: ambos mensajes exactos | `display_data => []`: `display_data.titular` con `Ingresá el nombre del titular.` y `display_data` con `Completá al menos uno de Alias, CBU o CVU.`; no persiste |
| 48 | `titular => null` no acumula mensajes | `titular => null` + alias válido: exactamente `display_data.titular` con `Ingresá el nombre del titular.`; sin `display_data.titular.string` ni `display_data.titular.regex`; no persiste |

Además, en los casos 33-35 se debe afirmar la ausencia de errores de Transferencia con `assertSessionDoesntHaveErrors` o `session('errors')->has(...)`; no alcanza con que el test pase.

### 7.2. Ajustes a tests existentes

- **Reemplazar** `test_transferencia_sin_datos_de_display_se_guarda_sin_errores` por `test_transferencia_sin_datos_es_rechazada_y_no_persiste`.
- **Agregar** los tests 45-48 de contrato de mensajes, con los textos exactos de titular y grupo y la ausencia de mensajes acumulados ante `titular => null`.
- **Reemplazar** el test 15 del plan anterior (que sólo verificaba normalización a `null`) por los casos 19-20, que afirman con `assertArrayNotHasKey` que `alias`, `cbu`, `cvu` y `banco` vacíos no quedan persistidos. No se usa `assertNull` para estos campos. El caso 18 cubre el rechazo cuando todos los datos de cuenta son sólo espacios.
- Mantener `test_admin_a_puede_guardar_un_medio_de_pago_valido`; si envía `cbu => ''`, el nuevo backend lo elimina. Agregar `assertArrayNotHasKey('cbu', $pmc->display_data)` para cubrir la normalización.
- Mantener `test_metodo_de_pago_es_obligatorio_y_no_persiste`; verificar que no aparezcan errores de Transferencia cuando falta `metodo_pago`.
- Mantener `test_medio_de_pago_invalido_devuelve_errores_de_campos_y_no_persiste`; verificar que no aparezcan errores de Transferencia cuando el método es inválido.
- Mantener `test_display_data_invalido_devuelve_error_general_y_no_persiste`; verificar que un `display_data` string no dispare además titular/grupo.
- Mantener `test_cajero_no_puede_guardar_medios_de_pago`; puede usarse un payload válido para aislar el `403` del middleware.
- Agregar los tests cross-tenant de PMC al archivo existente de cross-tenant, sin duplicar el setup de tenants.

### 7.3. Pruebas manuales de frontend

No hay infraestructura de tests de componentes Vue. Existe el precedente de `node:test` para utilidades (`resources/js/Utils/pagoManual.test.mjs`), pero esta fase no extrae una utilidad nueva; la validación visual se cubre con build y QA manual:

1. Transferencia sin datos: el submit no envía; aparece el resumen `Completá el nombre del titular y al menos uno de Alias, CBU o CVU.`, error de titular y error de grupo; los valores quedan; foco en titular.
2. Transferencia sólo con titular: error de grupo; resumen `Faltan los datos de la cuenta para la transferencia.`; foco en el resumen.
3. Transferencia con alias y titular vacío: error de titular; resumen `Faltan los datos del titular de la transferencia.`; foco en titular.
4. Transferencia con alias + titular: guarda; no aparecen claves vacías; el modal del POS no muestra filas vacías.
5. Transferencia con CBU + titular: guarda; el CBU se ve en el input CBU y en el listado; `cvu` ausente.
6. Transferencia con CVU + titular: guarda; el CVU se ve en el input CVU; `cbu` ausente.
7. Transferencia con CBU + CVU + titular: guarda; ambos se ven en sus inputs.
8. Transferencia con todos los datos: guarda.
9. Transferencia sin banco: guarda; `banco` ausente.
10. Titular con sólo espacios: bloquea y muestra error.
11. Alias/CBU/CVU con sólo espacios: bloquea y muestra sólo el error de grupo, sin mensajes de “solo números”.
12. Editar una configuración existente con CBU guardado bajo `cbu`: el valor aparece en el input CBU y se puede guardar.
13. Editar una configuración histórica con CBU guardado bajo `cvu`: el valor aparece en el input CVU; el usuario puede moverlo manualmente al input CBU. No hay reinterpretación automática.
14. Editar una configuración histórica incompleta: no se puede guardar sin completar titular y al menos uno de Alias/CBU/CVU.
15. Cambiar de Transferencia a Efectivo después de un error: se limpian los errores de `display_data` y también el error de `metodo_pago`; Efectivo con `display_data` vacío se guarda.
16. Efectivo, Débito, Crédito, Mercado Pago, viüMi y Cuenta Corriente: no muestran marcas de obligatorio de Transferencia y conservan su comportamiento; las cadenas de espacios se preservan.
17. Error de formato único (CVU, CBU o titular): se muestra inline y el foco respeta F2; no aparece el resumen específico de requisitos mínimos.
18. Error de formato + error de requisito: se muestra el resumen específico y todos los errores inline; el foco va al primer campo con error.
19. Error del servidor con valores cargados: los valores se conservan.
20. Usuario cajero: sigue recibiendo `403`.
21. Transferencia con alias + titular + CVU/Banco con sólo espacios: guarda; no aparecen filas vacías en el listado ni en el modal.
22. Configuración histórica con claves vacías: el modal puede seguir mostrando filas vacías hasta F7A; está documentado y no se corrige automáticamente.
23. Accesibilidad: el grupo Alias/CBU/CVU tiene `role="group"` y `aria-describedby`; Titular tiene `aria-required` cuando es Transferencia.
24. Editar una PMC de Efectivo/Cuenta Corriente con `display_data` nulo o parcial: no se agregan claves vacías y el payload conserva el comportamiento actual.
25. Verificar que el resumen de un único requisito no repite textualmente el error inline.
26. Verificar que los textos de `MENSAJE_TITULAR_PMC` y `MENSAJE_GRUPO_PMC` coinciden con los que devuelve el backend.

### 7.4. Comandos previstos para la fase de implementación

```bash
php artisan test tests/Feature/ConfiguracionMediosPagoTest.php
php artisan test tests/Feature/CrossTenantAttackTest.php
npm run build
vendor/bin/pint --test tests/Feature/ConfiguracionMediosPagoTest.php
vendor/bin/pint --test tests/Feature/CrossTenantAttackTest.php
vendor/bin/pint --test app/Http/Controllers/ConfiguracionController.php
git diff --check
```

Si los tests cross-tenant se agregan finalmente a `ConfiguracionMediosPagoTest.php`, ajustar el comando para no duplicar la ejecución.

No se ejecutaron estos comandos en esta fase de planificación.

Nota: `ConfiguracionController.php` ya tiene deuda de estilo preexistente (ver `BUILD-F2-MEDIOS-PAGO-POS.md`). No se debe aplicar un auto-fix masivo ni mezclar ese diff con esta fase. Se debe verificar que las líneas nuevas no agreguen errores nuevos y que el test pase Pint.

---

## 8. Riesgos y posibles regresiones

| Riesgo | Impacto | Mitigación |
|---|---|---|
| Configuraciones existentes con Transferencia vacía | Siguen existiendo y el POS puede mostrarlas incompletas | La regla aplica a crear y editar; no se hace backfill ni migración. La configuración histórica incompleta no puede actualizarse hasta completar los datos. Si se quiere limpiar datos existentes, debe ser una tarea separada con revisión de datos (F7A). |
| CBU histórico guardado bajo `cvu` | Datos históricos con semántica ambigua | Separar CVU y CBU en el formulario; `editPmc` muestra cada clave. No se migran ni se reinterpretan datos automáticamente. La corrección es manual desde la edición. |
| Claves vacías persistidas generan filas vacías en POS | El modal muestra datos en blanco | Para Transferencia, `unset` de `alias`, `cbu`, `cvu`, `banco` y `titular` cuando son `null`, vacíos o sólo espacios. No se modifica `ConfirmarPagoModal.vue`. Tests 19-20; el caso 18 verifica el rechazo cuando todos los datos de cuenta son sólo espacios. |
| `titular => null` acumula mensajes | El resumen específico no se muestra y aparece un mensaje por defecto en inglés | `bail` en `display_data.titular`; tests 11 y 48. |
| `editPmc()` agrega claves vacías a otros métodos | Se persisten claves vacías nuevas y el modal puede mostrar filas vacías | `editPmc()` sólo normaliza los cinco campos para Transferencia; QA 24. |
| Resumen duplica el error inline de un único requisito | El usuario ve el mismo texto dos veces | Textos de resumen propios para titular y grupo; QA 25. |
| Configuraciones históricas con claves vacías | El modal puede seguir mostrando filas vacías | Documentado; no se corrigen automáticamente. Queda para F7A o limpieza manual. |
| Validación frontend distinta del backend | El usuario podría ver un error que el backend no devuelve, o al revés | Duplicar sólo la regla mínima aprobada; el backend sigue siendo la fuente de verdad. Tests de feature cubren todas las combinaciones. |
| Mensajes contradictorios en el grupo | El usuario podría creer que Alias, CBU y CVU son obligatorios a la vez | Un único error en `display_data`; no se agregan `required` individuales; ayuda visual “al menos uno”. |
| `display_data` no es un array | Se podrían duplicar errores de formato, titular y grupo | No evaluar titular/grupo cuando `display_data` no es array; el error de formato queda solo. Test 33. |
| Resumen específico aparece para errores de formato | El usuario ve un mensaje de requisitos mínimos que no corresponde | `hayErrorRequisitoTransferenciaPmc` sólo detecta el `required` exacto de titular (`mensajeErrorPmc('display_data.titular') === MENSAJE_TITULAR_PMC`) o el error grupal exacto (`mensajeErrorPmc('display_data') === MENSAJE_GRUPO_PMC`); un único error de formato como `display_data.titular.regex` o `display_data.array` no muestra el resumen específico. Tests 14-17, 32 y 33. |
| Foco en un campo inexistente | El foco se pierde o no se mueve | `idCampoEnfocablePmc` devuelve el id propio de CBU y CVU; el grupo no se trata como input y se enfoca el resumen. |
| Errores de Transferencia quedan al cambiar de método | El usuario ve errores que ya no aplican | `cambiarMetodoPagoPmc()` conserva `limpiarErrorPmc('metodo_pago')` y limpia todos los errores de `display_data` y sus subcampos. |
| Cambios en otros medios de pago | Regresión funcional | Las reglas condicionales sólo se activan con `TRANSFERENCIA`; tests para todos los demás métodos, incluidos `display_data` ausente/null/espacios. |
| Edición de PMC y `updateOrCreate` | El formulario no envía ID; cambiar `provider` puede crear otra fila | Es deuda preexistente de F3/C-01. Este plan no la modifica. Los tests de actualización usan el mismo `provider` para no mezclar alcances. |
| Permisos y multi-tenant | Fuga o acceso indebido | No se tocan rutas, middleware ni consultas. Se mantiene el test de cajero con `403` y se agregan tests cross-tenant de PMC. |
| Checkout público y onboarding | Cambios no deseados en tienda | No se tocan `comercios.transferencia_*`, `OnboardingService`, tienda ni checkout. |
| Sin tests JS automatizados | La validación visual no queda cubierta por CI | No hay infraestructura de tests de componentes Vue. Existe `node:test` para utilidades, pero esta fase no extrae una utilidad nueva; se cubre con build y QA manual. No se agrega un framework nuevo. |
| Claves desconocidas en `display_data` | El modal puede mostrarlas | Se preservan por compatibilidad; es deuda existente y no se modifica en esta fase. |

### 8.1. Alcance parcial de F7B

- Esta fase implementa sólo la validación y la separación CBU/CVU de F7B.
- F7B no queda cerrada: no incluye F7A (limpieza/filtrado de filas vacías históricas) ni F4.
- Las configuraciones históricas con claves vacías pueden seguir mostrando filas vacías en el POS hasta F7A.
- C-03 no se marca como resuelto en su totalidad; el informe de BUILD debe dejar constancia del alcance parcial.
- La regla de Transferencia definida en este plan es la decisión de negocio aprobada por el usuario para este alcance: D-03/D-05 quedan resueltos por decisión de producto; F7A y F4 siguen pendientes. El informe de BUILD debe registrarlo junto con el alcance parcial.

---

## 9. Criterios de aceptación

Se considerará implementada la fase cuando:

- [ ] Transferencia sin ningún dato es rechazada y no persiste.
- [ ] Transferencia sólo con titular es rechazada y no persiste.
- [ ] Transferencia con alias + titular se guarda.
- [ ] Transferencia con CBU + titular se guarda bajo `cbu`.
- [ ] Transferencia con CVU + titular se guarda bajo `cvu`.
- [ ] Transferencia con CBU + CVU + titular guarda ambos en sus claves.
- [ ] Editar una Transferencia válida actualiza la misma fila (mismo `provider`) y no pisa el otro dato de CBU/CVU.
- [ ] Editar una configuración incompleta exige completar titular/grupo y no pierde el dato original si falla.
- [ ] Transferencia con alias + CBU + CVU + titular se guarda.
- [ ] Transferencia sin banco se guarda.
- [ ] Titular vacío, sólo espacios o `null` es rechazado.
- [ ] `titular => null` devuelve sólo el mensaje `required` de titular, sin acumular `string` ni `regex`.
- [ ] Los textos exactos de `display_data.titular` requerido y del error grupal están cubiertos por tests.
- [ ] Editar una PMC de otro método no agrega claves vacías ni normaliza su `display_data`.
- [ ] El resumen de un único requisito no repite textualmente el error inline.
- [ ] Alias/CBU/CVU vacíos, sólo espacios o `null` son rechazados si no hay ningún otro dato de cuenta.
- [ ] Para Transferencia, no se persisten claves `alias`, `cbu`, `cvu`, `banco` ni `titular` con `null`, `''` o sólo espacios; se usa `unset`.
- [ ] Los campos opcionales con sólo espacios no se guardan como filas vacías en POS para configuraciones nuevas o editadas.
- [ ] Una configuración histórica incompleta no puede actualizarse sin completar los datos obligatorios.
- [ ] El formulario muestra Titular como obligatorio y aclara que se necesita al menos uno de Alias/CBU/CVU.
- [ ] El formulario tiene inputs independientes Alias, CBU, CVU, Banco y Titular.
- [ ] El resumen específico sólo aparece ante errores de requisito mínimo, no ante un único error de formato.
- [ ] El formulario muestra errores inline sin contradicciones y el foco respeta F2, incluido el nuevo input CBU y el resumen cuando sólo falla el grupo.
- [ ] Los valores ingresados se conservan ante error.
- [ ] Al cambiar de método se limpian los errores de `display_data` y se conserva la limpieza de `metodo_pago`.
- [ ] Los demás métodos de pago conservan su comportamiento, incluidos `display_data` ausente, `null` y cadenas de espacios.
- [ ] El usuario sin permisos sigue recibiendo `403`.
- [ ] Admin B no ve, no elimina y no modifica configuraciones de A; los tests cross-tenant de PMC pasan.
- [ ] No se modifica la estructura de la base de datos ni se agregan migraciones, y no se alteran el POS, el checkout público, Mercado Pago ni los permisos.
- [ ] Pasan los tests de `ConfiguracionMediosPagoTest` y de cross-tenant.
- [ ] Pasa `npm run build`.
- [ ] El informe de BUILD declara el alcance parcial de F7B/C-03, que la regla de Transferencia es la decisión de negocio aprobada (D-03/D-05), que los textos de titular/grupo son un contrato compartido y que las filas vacías históricas quedan para F7A.
- [ ] No se introducen cambios ajenos en el working tree.

---

## 10. Confirmación de que no se implementó ningún cambio

Esta tarea fue exclusivamente de planificación y corrección del documento.

- No se modificó código de producción.
- No se modificaron tests.
- No se crearon ni modificaron migraciones.
- No se modificó la estructura de la base de datos ni se ejecutaron migraciones.
- No se modificaron dependencias.
- No se modificaron configuraciones.
- No se modificaron rutas, permisos, policies, POS, ventas, caja, checkout público ni integraciones de pago.
- No se modificó `ConfirmarPagoModal.vue`, el modelo `PaymentMethodConfiguration` ni la migración.
- No se modificó `AGENTS.md`, `.opencode/` ni `OPENCODE-SKILLS.md`.
- No se modificó `docs/informes/REVIEW-PLAN-VALIDACION-TRANSFERENCIA-POS.md`.
- No se modificó `docs/informes/REVIEW-PLAN-VALIDACION-TRANSFERENCIA-POS-2.md`.
- No se ejecutaron tests ni builds.
- No se creó ningún commit.
- Se preservaron los cambios ajenos que ya estaban en el working tree (B3/B3.2, `.opencode/`, `OPENCODE-SKILLS.md` y archivos relacionados).
- El único archivo modificado en esta tarea es este documento.

La implementación queda pendiente de aprobación de este plan corregido.

---

## 11. Pendientes para la siguiente fase

1. Implementar los cambios de backend y frontend descriptos.
2. Reemplazar el test que permite Transferencia vacía y ampliar la matriz de tests.
3. Agregar los tests cross-tenant de PMC al archivo existente.
4. Ejecutar la matriz de tests de feature y cross-tenant.
5. Ejecutar `npm run build` y la matriz manual de frontend.
6. Revisar que no haya regresiones en F2 (resumen, foco, limpieza de errores).
7. Crear `docs/informes/BUILD-VALIDACION-TRANSFERENCIA-POS.md` con el detalle de la implementación, el alcance parcial de F7B/C-03, la decisión de negocio aprobada para Transferencia (D-03/D-05) y el estado pendiente de F7A/F4.
8. Definir, por separado, qué hacer con configuraciones históricas incompletas y con claves vacías (F7A).
9. Definir, por separado, si la misma regla debe aplicar al checkout público (`comercios.transferencia_*`).
10. Mantener fuera de alcance la edición por ID y la identidad de PMC (F3/C-01), salvo que se autorice otra fase.
11. Mantener fuera de alcance la reinterpretación automática de CBU histórico guardado bajo `cvu`; la corrección es manual desde la edición.
12. Mantener el contrato de mensajes exactos entre backend y frontend; cualquier cambio de copy debe actualizar ambos lados y los tests 45-48.
