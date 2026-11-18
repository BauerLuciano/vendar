# BUILD — Validación obligatoria de Transferencia (Medios de Pago POS)

- **Fecha:** 2026-11-18
- **Fase:** BUILD de `docs/informes/PLAN-VALIDACION-TRANSFERENCIA-POS.md`
- **Base:** `f144e18` + working tree con cambios preexistentes de B3/B3.2 y `.opencode/`
- **Alcance:** configuración de medios de pago POS, únicamente método `TRANSFERENCIA`, al crear y al editar. Sin backfill.
- **Estado:** BUILD ejecutado y verificado. **No se declara aprobación final.**

---

## 1. Objetivo

Impedir que se guarde una configuración de medio de pago POS de tipo **Transferencia** sin los datos mínimos para que el cliente pueda pagar:

- `display_data.titular`: obligatorio.
- Al menos uno de `display_data.alias`, `display_data.cbu` o `display_data.cvu`: obligatorio.
- `display_data.banco`: opcional.
- La regla aplica al crear y al editar.
- Los valores `null`, vacíos y compuestos sólo por espacios se consideran faltantes.
- Una configuración histórica incompleta no puede actualizarse sin completar los datos requeridos.
- Los demás métodos de pago conservan su comportamiento actual.
- No hay backfill ni reinterpretación automática de CBU/CVU históricos.

La regla implementada es la **decisión de negocio aprobada por el usuario** en el plan. No se infiere ni se amplía a otros métodos.

---

## 2. Archivos creados

| Archivo | Descripción |
|---|---|
| `docs/informes/BUILD-VALIDACION-TRANSFERENCIA-POS.md` | Este informe de BUILD. |

## 3. Archivos modificados

| Archivo | Cambio |
|---|---|
| `app/Http/Controllers/ConfiguracionController.php` | Validación condicional de Transferencia, error grupal, mensajes exactos, normalización y persistencia de `display_data`. |
| `resources/js/Pages/Configuracion/Index.vue` | Validación previa al POST, resumen de errores, foco accesible, inputs separados de Alias/CBU/CVU, ayudas y marcas de obligatorio. |
| `tests/Feature/ConfiguracionMediosPagoTest.php` | Matriz PMC de 66 tests, incluidos contratos de mensajes exactos y casos de actualización. |
| `tests/Feature/CrossTenantAttackTest.php` | Tests CT11, CT12 y CT13 de aislamiento multi-tenant para PMC. |

### Archivos que NO se modificaron

- `docs/informes/PLAN-VALIDACION-TRANSFERENCIA-POS.md`
- `docs/informes/REVIEW-PLAN-VALIDACION-TRANSFERENCIA-POS.md`
- `docs/informes/REVIEW-PLAN-VALIDACION-TRANSFERENCIA-POS-2.md`
- POS, checkout, `ConfirmarPagoModal.vue`, Mercado Pago, permisos, plan, migraciones, modelo y rutas.
- No se aplicó backfill ni migración de datos.

---

## 4. Implementación backend

Archivo: `app/Http/Controllers/ConfiguracionController.php`, método `storePaymentMethodConfig()`.

### 4.1 Validación condicional

- Se reemplazó `$request->validate(...)` por `Validator::make(...)` + `after()` + `validate()`.
- Se importó `Illuminate\Support\Facades\Validator`.
- Se detecta Transferencia con `MetodoPago::TRANSFERENCIA->value`.
- Se distingue si `display_data` es array o `null` (`$displayDataEsArray`) para no aplicar la regla de titular cuando `display_data` tiene un tipo inválido.
- La regla de `display_data.titular` usa `bail` como primera regla en ambas ramas:
  - Transferencia con `display_data` array/null: `bail|required|string|regex|max`.
  - Cualquier otro caso: `bail|nullable|string|regex|max`.
- `bail` evita la acumulación de `required` + `string` + `regex` cuando el titular es `null`.

### 4.2 Error grupal

- En `after()` se agrega el error `display_data` con el texto exacto:
  - `Completá al menos uno de Alias, CBU o CVU.`
- Se usa `filled()` para considerar informados `alias`, `cbu` o `cvu`.
- El error grupal sólo se agrega para Transferencia cuando `display_data` es array o `null`.
- Los errores de formato de campos opcionales compuestos sólo por espacios se descartan con `forget()`.
- No se descarta el error `required` del titular.

### 4.3 Normalización y persistencia

- Para Transferencia se eliminan con `unset()` las claves `alias`, `cbu`, `cvu`, `banco` y `titular` cuando su valor es `null` o una cadena compuesta sólo por espacios.
- No se persisten claves vacías ni `null` explícito para esos cinco campos.
- Los valores con contenido se guardan exactamente como se recibieron.
- Las claves desconocidas de `display_data` se preservan.
- Para otros métodos no se normaliza `display_data`: se conserva el comportamiento previo.
- Se persiste `$displayDataParaGuardar`, no `$request->display_data` directo.
- Se mantiene `updateOrCreate` por `(comercio_id, metodo_pago, provider)`.

### 4.4 Mensajes exactos

- `display_data.titular.required`: `Ingresá el nombre del titular.`
- Error grupal `display_data`: `Completá al menos uno de Alias, CBU o CVU.`
- Se conservaron los mensajes existentes de formato, longitud, método de pago y pasarela.

### 4.5 Multi-tenant y permisos

- No se modificó la resolución de `comercio_id` a partir del usuario autenticado.
- No se modificaron rutas, policies ni middleware.
- Los tests CT11–CT13 verifican que un admin no vea, elimine ni cree PMC en otro comercio.

---

## 5. Implementación frontend

Archivo: `resources/js/Pages/Configuracion/Index.vue`.

### 5.1 Validación previa al POST

- Se agregó `validarTransferenciaPmc()`, que sólo actúa cuando el método es `TRANSFERENCIA`.
- Valida titular y grupo antes de enviar el formulario.
- Usa `valorCompletadoPmc()` para considerar `null`, `undefined`, cadenas vacías y cadenas sólo con espacios como no completados.
- Si falla, no se hace el POST y se enfoca el primer error.

### 5.2 Resumen de errores

- Se agregaron las constantes `MENSAJE_GRUPO_PMC` y `MENSAJE_TITULAR_PMC` con los textos exactos del backend.
- `faltaTitularPmc` y `faltaCuentaPmc` detectan sólo los errores de requisito mínimo por igualdad de texto, no errores de formato.
- `mensajeResumenPmc` usa textos distintos para evitar repetir el error inline de un único requisito:
  - Ambos faltantes: `Completá el nombre del titular y al menos uno de Alias, CBU o CVU.`
  - Sólo titular: `Faltan los datos del titular de la transferencia.`
  - Sólo cuenta: `Faltan los datos de la cuenta para la transferencia.`
  - Genérico: `Revisá los datos marcados y volvé a intentar.`
- El resumen se muestra si hay error de requisito de Transferencia, o si hay más de un error / un error general.
- Se extrajo `enfocarPrimerErrorPmc()` para reutilizar el foco desde `onError` y desde la validación frontend.

### 5.3 Edición

- `editPmc()` sólo prepara las cinco claves de Transferencia (`alias`, `cbu`, `cvu`, `banco`, `titular`) cuando el método es `TRANSFERENCIA`.
- Para otros métodos conserva `{ ...(pmc.display_data || {}) }` sin agregar claves vacías.
- Una configuración histórica con CBU guardado bajo `cvu` se muestra en el input CVU; no se reinterpreta automáticamente.
- Una configuración histórica incompleta no se puede guardar sin completar titular y al menos uno de Alias/CBU/CVU.

### 5.4 Cambio de método

- `cambiarMetodoPagoPmc()` limpia el error de `metodo_pago` y todos los errores de `display_data` y sus subcampos.
- Se mantiene la limpieza de errores al cambiar de método.

### 5.5 Formulario y accesibilidad

- Se separaron los inputs de **Alias**, **CBU** y **CVU** (antes CVU/CBU compartían un input).
- El grupo Alias/CBU/CVU tiene `role="group"`, `aria-label` y `aria-describedby` hacia la ayuda y el error grupal.
- La ayuda `Completá al menos uno de Alias, CBU o CVU.` se muestra sólo para Transferencia.
- El error grupal se muestra una sola vez, junto al grupo y antes de Banco/Titular. Se eliminó el error duplicado al pie del formulario.
- Titular muestra `*` y `aria-required="true"` sólo para Transferencia.
- Banco muestra `(opcional)` sólo para Transferencia.
- Cada input conserva `aria-invalid`, `aria-describedby`, error inline y limpieza al escribir.
- Se mantiene el comportamiento de foco y el resumen accesible existente.

---

## 6. Decisiones técnicas

1. **Validator + after()**: permite combinar la validación declarativa existente con el error grupal y el descarte de errores de formato de campos con sólo espacios.
2. **`bail` en titular**: evita acumulación de mensajes cuando el valor es `null` o de tipo inválido. No se justifica por cadenas con espacios, porque Laravel ya las trata como ausentes para reglas no implícitas.
3. **Normalización sólo Transferencia con `unset`**: evita persistir claves vacías que `ConfirmarPagoModal.vue` renderiza.
4. **Sin backfill**: no se migran datos históricos ni se reinterpreta un CBU guardado bajo `cvu`.
5. **Sin cambios en otros métodos**: no se altera la validación ni la persistencia de `display_data` para Efectivo, Débito, Crédito, Mercado Pago, Cuenta Corriente o viüMi.
6. **Tests de actualización con el mismo `provider`**: evita la ambigüedad de la unique `(comercio_id, metodo_pago, provider)` cuando `provider` es `null`.
7. **D-03/D-05**: quedan resueltos para este alcance (Transferencia). **F7A/F4 siguen pendientes** y no se abordan en esta fase.

---

## 7. Observaciones de las revisiones atendidas

| Observación | Estado |
|---|---|
| N-01: `bail` en ambas ramas de titular | Implementado. |
| N-02: `editPmc()` sólo normaliza Transferencia | Implementado. |
| N-03: tests de contrato de mensajes exactos | Implementado. |
| N-04: textos de resumen distintos | Implementado. |
| N-05: justificación de `bail` | Documentada: evita acumulación por `null`/tipo inválido, no por espacios. |
| N-06: plan y revisiones no se tocan | Respetado. |
| N-07: informe con decisión de negocio y pendientes | Documentado en este informe. |

---

## 8. Bugs y hallazgos corregidos

1. **Titular `null` acumulaba mensajes**: se corrigió con `bail`.
2. **`editPmc()` agregaba claves vacías a otros métodos**: se corrigió con la rama condicional.
3. **Error grupal duplicado al pie del formulario**: se eliminó y se dejó junto al grupo.
4. **Un solo input para CVU/CBU**: se separaron los campos.
5. **El resumen repetía el error inline de un único requisito**: se usaron textos de resumen distintos.
6. **Errores de `display_data` no se limpiaban al cambiar de método**: se agregó la limpieza completa.
7. **Configuraciones históricas incompletas podían guardarse sin completar datos**: ahora se rechazan hasta completar titular y grupo.

---

## 9. Pruebas ejecutadas y resultados

### 9.1 Tests PMC

Comando:

```bash
docker compose exec -T laravel.test php artisan test tests/Feature/ConfiguracionMediosPagoTest.php --compact
```

Resultado:

```text
Tests: 66 deprecated (325 assertions)
Duration: 201.23s
```

- **66/66 tests pasan.**
- **325 assertions.**
- Los 66 avisos de deprecación son por `PDO::MYSQL_ATTR_SSL_CA` en el entorno de test; no son fallos.
- No se eliminaron ni debilitaron tests para hacerlos pasar.

Cobertura principal:

- Transferencia sin datos, sin `display_data`, con `display_data: null`.
- Sólo titular / sólo banco + titular.
- Alias + titular, CBU + titular, CVU + titular, CBU + CVU + titular, todos los datos.
- Titular vacío, sólo espacios y `null`.
- Grupo vacío, sólo espacios y `null`.
- CBU/CVU/banco/titular con formato inválido.
- Campos de cuenta con sólo espacios sin errores de formato.
- Eliminación de claves vacías y conservación de valores válidos.
- Claves desconocidas.
- Actualización válida, inválida y de configuración histórica incompleta.
- Cambio CBU → CVU, CVU → CBU y CBU + CVU simultáneos.
- Otros métodos con `display_data` vacío, ausente o `null`.
- Otros métodos con cadenas de espacios.
- `display_data` string, método ausente y método inválido.
- Cajero sin permiso.
- Contratos de mensajes exactos.
- Límites de longitud 255/256.
- Valores `"0"` y titulares con comas/puntos.

### 9.2 Tests cross-tenant

Comando:

```bash
docker compose exec -T laravel.test php artisan test tests/Feature/CrossTenantAttackTest.php
```

Resultado:

- **CT11, CT12 y CT13 pasan.**
- **CT10 sigue fallando por un problema preexistente y ajeno a PMC**: el endpoint de cierre de turno espera `saldo_final_transferencias_real` y `saldo_final_tarjetas_real`, campos de caja que no se tocaron en esta fase.
- No se modificó caja para “hacer pasar” CT10.

Tests agregados:

| Test | Verificación |
|---|---|
| `test_ct11_index_configuracion_no_muestra_pmc_de_otro_comercio` | El admin B no ve PMC del comercio A en `configuracion.index`. |
| `test_ct12_eliminar_pmc_de_otro_comercio_da_403` | El admin B no puede eliminar un PMC del comercio A. |
| `test_ct13_store_pmc_crea_en_el_comercio_del_admin_autenticado` | El admin B crea el PMC en su propio comercio y no modifica el de A. |

### 9.3 Build frontend

Comando:

```bash
npm run build
```

Resultado:

```text
✓ 951 modules transformed.
✓ built in 4.90s
```

Sin errores de compilación.

### 9.4 Verificación de diff

Comando:

```bash
git diff --check
```

Resultado: sin errores de whitespace.

### 9.5 Pint en modo test

Comando:

```bash
docker compose exec -T laravel.test vendor/bin/pint --test \
  app/Http/Controllers/ConfiguracionController.php \
  tests/Feature/ConfiguracionMediosPagoTest.php \
  tests/Feature/CrossTenantAttackTest.php
```

Resultado:

- `tests/Feature/ConfiguracionMediosPagoTest.php`: pasa.
- `tests/Feature/CrossTenantAttackTest.php`: pasa.
- `app/Http/Controllers/ConfiguracionController.php`: falla **sólo por deuda de estilo preexistente**:
  - orden de imports `Comercio` / `Configuracion`;
  - `!empty(...)` en código previo;
  - alineación de arrays en bloques previos.
- Las líneas nuevas de esta fase no agregan errores de Pint.
- No se aplicó auto-fix masivo ni se mezcló esa deuda preexistente con este cambio.

---

## 10. Hallazgos relevantes

### 10.1 El caso 31b del plan no es reproducible como estaba escrito

El plan pedía que, para `EFECTIVO`, un CBU/CVU compuesto sólo por espacios devolviera errores de regex. Eso **no ocurre** con Laravel: `presentOrRuleIsImplicit()` trata las cadenas con `trim($value) === ''` como ausentes para reglas no implícitas, por lo que `regex` y `max` no se ejecutan. Producir esos errores requeriría cambiar el comportamiento de todos los métodos, lo que violaría el requisito de conservar el comportamiento actual.

Decisión tomada:

- No se cambió el comportamiento de otros métodos.
- El test se corrigió para verificar el comportamiento real: no se agregan errores de Transferencia y el controlador no normaliza los valores de otros métodos.
- Este hallazgo queda documentado; no se ocultó ni se eliminó cobertura.

### 10.2 Middleware global y espacios

En HTTP real, `TrimStrings` y `ConvertEmptyStringsToNull` convierten las cadenas de sólo espacios en `null` antes del controlador. Para verificar que `ConfiguracionController` no normaliza otros métodos, dos tests desactivan explícitamente esos middleware con `withoutMiddleware([...])`. Esto aísla el comportamiento del controlador y no modifica la aplicación.

### 10.3 Deprecaciones de PHPUnit

Los 66 tests PMC se reportan como `deprecated` por `PDO::MYSQL_ATTR_SSL_CA`. Es un aviso del entorno, no un fallo funcional. No se modificó configuración de PHP/PDO en esta fase.

### 10.4 CT10

La falla de CT10 es de caja y preexistente al alcance de PMC. No se tocó caja, turnos ni sus validaciones.

---

## 11. QA manual recomendada

1. Entrar como admin del comercio A a **Configuración → Medios de pago**.
2. Crear una Transferencia con sólo titular:
   - debe bloquear;
   - debe mostrar el error de titular y el error grupal;
   - debe enfocar el primer campo con error.
3. Completar Alias y guardar:
   - debe guardar;
   - en el listado debe verse Alias y Titular;
   - no deben aparecer CBU/CVU vacíos.
4. Repetir con CBU y con CVU por separado:
   - cada valor debe aparecer en su input correcto;
   - el otro campo no debe guardarse.
5. Editar una configuración histórica incompleta:
   - no debe permitir guardar sin completar titular y grupo;
   - al completar Alias debe guardar.
6. Editar una configuración histórica con CBU guardado bajo `cvu`:
   - el valor debe verse en el input CVU;
   - no debe reinterpretarse automáticamente;
   - el usuario puede moverlo manualmente a CBU.
7. Provocar un error de Transferencia y cambiar el método a Efectivo:
   - deben limpiarse los errores de `display_data`.
8. Probar en mobile:
   - Alias, CBU y CVU deben apilarse correctamente;
   - el resumen y los errores inline deben ser legibles.
9. Verificar accesibilidad:
   - el grupo Alias/CBU/CVU debe anunciar la ayuda;
   - Titular debe anunciar obligatoriedad;
   - el resumen debe recibir foco.
10. Entrar como admin del comercio B:
    - no debe ver los PMC del comercio A;
    - no debe poder eliminarlos;
    - al crear uno, debe quedar en el comercio B.

---

## 12. Criterios de aceptación

- [x] Transferencia exige titular.
- [x] Transferencia exige al menos uno de Alias, CBU o CVU.
- [x] Banco sigue siendo opcional.
- [x] `null`, vacío y sólo espacios se consideran faltantes.
- [x] La regla aplica al crear y al editar.
- [x] Una configuración histórica incompleta no se puede actualizar sin completar datos.
- [x] Otros métodos conservan su comportamiento.
- [x] No se persisten claves vacías ni `null` explícito para los cinco campos de Transferencia.
- [x] No hay backfill ni reinterpretación automática de CBU/CVU.
- [x] Se mantiene el aislamiento multi-tenant.
- [x] Se mantienen permisos y policies.
- [x] Tests PMC en verde.
- [x] Tests cross-tenant nuevos en verde.
- [x] Build frontend en verde.
- [x] `git diff --check` limpio.
- [x] Pint sin errores nuevos en las líneas de esta fase.
- [ ] Aprobación final: **no declarada en este informe**.

---

## 13. Pendientes

- **F7A/F4:** siguen pendientes y fuera del alcance de esta fase.
- **F7B/C-03:** sin cambios en esta fase.
- **CT10:** falla preexistente de caja; requiere una fase propia si se quiere abordar.
- **Deuda de estilo preexistente** en `ConfiguracionController.php`: no se corrigió para no mezclar diffs ajenos.
- **Revisión final:** queda a cargo del REVIEWER.

---

## 14. Estado del working tree

- No se crearon commits.
- No se hizo push.
- Se preservaron los cambios preexistentes de B3/B3.2 y `.opencode/`.
- Archivos de esta fase:
  - `app/Http/Controllers/ConfiguracionController.php`
  - `resources/js/Pages/Configuracion/Index.vue`
  - `tests/Feature/ConfiguracionMediosPagoTest.php`
  - `tests/Feature/CrossTenantAttackTest.php`
  - `docs/informes/BUILD-VALIDACION-TRANSFERENCIA-POS.md`
- El working tree sigue sin commit, con los cambios de esta fase y los preexistentes.
