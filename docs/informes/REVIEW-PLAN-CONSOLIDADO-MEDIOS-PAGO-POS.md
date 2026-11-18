# REVIEW — PLAN CONSOLIDADO MEDIOS DE PAGO POS

**Fecha:** 2026-11-17  
**Modo:** REVIEWER exclusivo. No se modificó código, migraciones, base de datos, dependencias, `AGENTS.md` ni skills. No se ejecutaron ventas, cobros, arqueos ni tests.  
**Documento revisado:** `docs/informes/PLAN-CONSOLIDADO-MEDIOS-PAGO-POS.md`  
**Entregable:** revisión independiente del plan, contrastada con el código actual y las skills `vendar-business-rules` y `vendar-multitenancy`.  
**Working tree:** ya tenía modificaciones preexistentes; no se revirtió ninguna.

---

## 1. Veredicto general

**Aprobado con correcciones mayores. No está listo para un BUILD completo y no debe autorizarse como “F1 + F2” en un solo paquete.**

El plan consolidado mejora sustancialmente la auditoría anterior: separa decisiones de producto, propone un resolvedor único, incorpora snapshot histórico y ordena las fases. Sin embargo, todavía tiene cinco problemas estructurales:

1. **F0 es una compuerta monolítica** que exige cerrar `D-01` a `D-15`, pero F1 dice que puede comenzar sin decisiones comerciales. La secuencia `F0 → F1` y la afirmación “F1 puede empezar sin decisiones” no pueden ser verdaderas al mismo tiempo.
2. **F4 y F8 se solapan**: F4 ya promete “persistir `config_id` y snapshot mínimo”, mientras F8 es la fase de snapshot histórico. Además, F4 no declara las decisiones `D-04`, `D-15` ni `D-06`, que sí necesita si persiste snapshot o define identidad.
3. **F6A y F7A no son implementables tal como están aceptadas**: F6A dice “marcar la venta como pendiente” cuando la venta manual ya nace pendiente; F7A exige detectar `display_data` “incompleto” sin que exista `D-03` que defina qué es incompleto.
4. **F9, F10 y F11 están sobredimensionadas y bloqueadas de más**: F9 puede dividirse en una confirmación segura sin `D-05`/F8 y una política de configuración ausente; F10 puede deshabilitar el gateway solo con `D-10`; F11 mezcla onboarding y tienda pública, que son dominios independientes.
5. **Faltan archivos y puntos de escritura** en F1 y F8. F1 no incluye todos los usos de `fromString`/`MetodoPago::from`; F8 no incluye todos los lugares que crean `MovimientoCaja` y cita una ruta de `TicketBuilder` que no existe.

### 1.1. Veredicto sobre la primera fase autorizable

**La primera fase autorizable es F2, sola.** Es la única que no requiere una decisión comercial, no depende de F0 y ya tiene el backend validando los campos que la UI debe mostrar.

- **F1 no puede autorizarse todavía**: necesita una lista de aliases firmada y un mapa completo de call sites. No necesita `D-01` ni `D-04`, pero sí necesita una auditoría de aliases que el plan mete dentro de F0.
- **F6A y F7A no son “primer BUILD” tal como están escritas**: requieren corrección de alcance. F6A necesita definir el conjunto de métodos base y el contrato de advertencia; F7A solo puede detectar “vacío” o “todos los valores en blanco”, no “incompleto”.
- **F3 puede adelantarse con defaults seguros** (método inmutable y roles actuales), pero el plan la bloquea con `D-02` y `D-13`. Si se acepta el default, F3A puede ir después de F2/F1; si no, queda bloqueada.
- **F4 no puede autorizarse** hasta dividirla y resolver `D-01`, `D-12`, `D-15` y la parte de `D-04` que le corresponde.
- **F9A puede adelantarse** si se limita a confirmación segura, idempotente y multi-tenant, sin snapshot ni política de configuración ausente.

---

## 2. Alcance, método y evidencia

La revisión contrastó el plan con:

- El código real de `MetodoPago`, `VentaController`, `ConsumidorController`, `ConfiguracionController`, `PosController`, `CajaDiariaController`, `VentaOperacionService`, `PaymentRecorder`, `PaymentService`, `Venta`, `MovimientoCaja`, `PaymentMethodConfiguration`, `TicketBuilder`, `PedidoWebController` y `OnboardingService`.
- Las vistas `Configuracion/Index.vue`, `Pos/Terminal.vue`, `ConfirmarPagoModal.vue`, `Welcome.vue` y `Cliente/Panel.vue`.
- La matriz `tests/Feature/CrossTenantAttackTest.php` y la ausencia de tests de PMC.
- Las skills `vendar-business-rules` y `vendar-multitenancy`.

**No se ejecutaron tests.** El baseline de 23 fallos preexistentes que menciona el plan no fue re-verificado en esta revisión. No debe tomarse como un dato nuevo ni como una autorización para ocultar fallos.

### 2.1. Evidencia principal

| Tema | Evidencia |
|---|---|
| Normalización actual | `app/Enums/MetodoPago.php:28-52`: elimina todo lo que no sea `A-Z`, no translitera acentos y cae a `EFECTIVO`. |
| Venta manual | `app/Http/Controllers/VentaController.php:144-147`: normaliza con `fromString` sin validar el desconocido antes. |
| Confirmación | `app/Http/Controllers/VentaController.php:596-634`: mueve caja/deuda y recién después intenta aprobar el `Payment`; si no hay configuración, igual mueve caja. |
| Configuración PMC | `app/Http/Controllers/ConfiguracionController.php:183-211`: valida `metodo_pago` y `display_data`, pero todos los campos de `display_data` son nullable. |
| Formulario PMC | `resources/js/Pages/Configuracion/Index.vue:84-121`: `pmcForm` no renderiza errores y envía las cinco claves de `display_data` aunque estén vacías. |
| POS | `app/Http/Controllers/PosController.php:60-84`: carga solo PMCs manuales; los métodos base incluyen `CUENTA_CORRIENTE`. |
| Deduplicación POS | `resources/js/Pages/Pos/Terminal.vue:114-142`: el método base gana y se descarta la metadata de la PMC manual; el grupo “transferencia” incluye `MERCADO_PAGO` y `VIUMI`. |
| Modal | `resources/js/Components/ConfirmarPagoModal.vue:142-147`: `display_data = []` es truthy y renderiza una caja vacía; no filtra valores en blanco. |
| Caja | `app/Http/Controllers/CajaDiariaController.php:28-33`: `clasificarMetodo` usa `MetodoPago::from` y puede lanzar `ValueError`; `:189-213` acepta cualquier `metodo_pago` string. |
| Movimientos de caja | Se crean en `PosController`, `CajaDiariaController`, `VentaController`, `ConsumidorController` y `VentaOperacionService`; F8 no incluye `PosController` ni `VentaOperacionService` en su lista de archivos. |
| Ticket | La clase real es `app/Services/Ticket/TicketBuilder.php`; el plan cita `app/Services/TicketBuilder.php`. |
| Tienda pública | `app/Http/Controllers/PedidoWebController.php:30-40` usa `efectivo`, `transferencia` y proveedores gateway en minúscula; no usa el enum `MetodoPago`. |
| Onboarding | `app/Services/OnboardingService.php:219-228`: los medios de pago ya son opcionales. |
| Multi-tenant | `VentaController.php:578-586`, `:654-661` y `:93-101` solo verifican comercio si `$comercioId` es truthy; si es null, no fallan cerrado. |

---

## 3. Validación específica de F1, F2, F6A y F7A

### 3.1. F1 — Normalización de métodos y aliases

**Veredicto: viable con correcciones. No puede empezar con F0 tal como está definido, y su lista de archivos es incompleta.**

#### Hallazgos

1. **La normalización actual no produce los resultados que el plan exige.** `fromString` hace `preg_replace('/[^A-Z_]/', '', ...)`, por lo que `DÉBITO` queda `DBITO` y `VIÜMI` queda `VIMI`; ambos caen al fallback `EFECTIVO`. Las claves `DÉBITO` y `CRÉDITO` del mapa son inalcanzables. El plan promete `VIÜMI → VIUMI` y `DÉBITO → DEBITO`, pero no define transliteración. Hay que usar una transliteración real (por ejemplo `Str::ascii`) y probarla.
2. **El mapa tiene aliases que el plan no enumera.** `TRANSFER`, `MERCADOPAGO`, `CTA_CTE`, `FIADO`, `TARJETA_CREDITO`, `TARJETA_DEBITO` y `TARJETA` ya existen. `TARJETA` es ambiguo y hoy se mapea a `DEBITO`. La lista de aliases debe ser explícita, firmada y con política para los ambiguos.
3. **El fallback silencioso está en el camino de validación, no solo en display.** `VentaController.php:144-147` normaliza antes de saber si el método existe; un desconocido se convierte en `EFECTIVO`. `ConsumidorController.php:303` hace lo mismo. Cambiar a `tryFromString(): ?self` obliga a validar antes de tocar stock, caja o deuda.
4. **F1 no incluye todos los call sites.** Además de `VentaController` y `ConsumidorController`, `fromString` o `MetodoPago::from` se usan en:
   - `PaymentMethodConfiguration.php:38,58` (`resolveDisplayLabel`, `labelMap`).
   - `PosController.php:65` y `:410`.
   - `CajaDiariaController.php:362` y `clasificarMetodo` en `:28-33`.
   - `ConfiguracionController.php:48`.
   - `Venta.php:55,58,70` a través de `resolveDisplayLabel`.
   - `MovimientoCaja.php:28`.
   - `VentaController.php:465,497,601`.
   Si `tryFromString` devuelve null, cualquiera de estos puntos puede romper con “call to a member function label() on null”. F1 debe actualizarlos o definir un fallback de display seguro.
5. **La caja manual acepta cualquier string.** `CajaDiariaController::crearMovimientoManual` valida `metodo_pago` solo como `string` y lo persiste; después `getBalance`, `getMovimientos` y `getSesionesAbiertasGlobal` clasifican con `MetodoPago::from` o `fromString`. F1 debe incluir este endpoint y decidir si rechaza el desconocido o lo guarda con una etiqueta cruda.
6. **El “checkout” es ambiguo.** `ConsumidorController::cobrarDeuda` es cobro de fiado, no checkout público. El checkout público es `PedidoWebController`, que usa valores lowercase y proveedores gateway. Si F1 incluye “checkout”, debe aclarar si queda dentro o fuera. Si queda dentro, hay que agregar `PedidoWebController` y un contrato distinto; si queda fuera, decirlo explícitamente para no romper la tienda.
7. **Datos históricos.** F1 no modifica filas almacenadas. Si se agrega `tryFromString` null, las filas viejas con `Débito`, `VIÜMI`, `TARJETA` o valores libres deben seguir siendo legibles. El plan habla de “mostrar el valor crudo o Método desconocido”, pero no define la firma de `resolveDisplayLabel` ni el fallback de `labelMap`. Hay que hacerlo antes de tocar el enum.

#### Correcciones exigidas

- Separar **F0A: auditoría read-only y lista de aliases** de **F0B: decisiones de producto**. F1 depende de F0A, no de las 15 decisiones.
- Publicar la tabla exacta de aliases, incluyendo los ambiguos y los que se van a rechazar.
- Agregar al alcance de F1: `PaymentMethodConfiguration`, `PosController`, `CajaDiariaController`, `ConfiguracionController`, `Venta`, `MovimientoCaja` y, si corresponde, `PedidoWebController`.
- Definir `tryFromString(): ?self` y un fallback de display explícito; no permitir que un null llegue a `->label()`.
- Agregar tests de idempotencia, aliases, desconocidos, acentos, display histórico, caja manual y ausencia de venta/movimiento con método inválido.
- Aclarar que F1 **no** backfillea datos y que F4 o una fase de datos se encarga de los valores históricos no canónicos.

### 3.2. F2 — Errores del formulario PMC

**Veredicto: viable, autónoma y la mejor candidata a primer BUILD. Debe ser UI-only y no agregar campos obligatorios.**

#### Hallazgos

1. **El backend ya valida lo que F2 necesita mostrar.** `ConfiguracionController.php:183-198` valida `metodo_pago` contra `MetodoPago::values()`, `display_data` como array y los campos `alias`, `cvu`, `cbu`, `banco` y `titular` con sus regex. No hace falta cambiar el controlador para que los errores existan.
2. **El problema real es que `pmcForm.errors` no se renderiza.** `Configuracion/Index.vue:84-121` define el formulario, pero el template no muestra los errores por campo. F2 puede resolverse solo en Vue, con un resumen y mensajes junto a cada input.
3. **No se deben agregar campos requeridos.** Todos los campos de `display_data` son nullable. `pmcForm` inicializa las cinco claves y las envía aunque estén vacías (`Index.vue:84-95,110`); el controlador las guarda. Por eso “guardar una transferencia sin datos” **hoy no falla**. El test manual del plan (`:401`) y el “feature de campo requerido” (`:400`) no son de F2: pertenecen a `D-03`/F7B.
4. **La prueba de método inválido no se puede hacer desde la UI** si el select solo ofrece valores válidos. Debe hacerse por HTTP directo, con un request manipulado, y verificar que el error se muestre.
5. **No hay colisión de bolsas si se mantiene `pmcForm` separado.** El formulario principal usa `form`; el PMC usa `pmcForm`. F2 debe renderizar `pmcForm.errors` y no mezclar `page.props.errors` del formulario principal.

#### Correcciones exigidas

- Reducir F2 a `resources/js/Pages/Configuracion/Index.vue` y tests de feature de validación; no tocar reglas del controlador salvo que falte un mensaje.
- Eliminar de F2 los criterios “campo requerido” y “transferencia sin datos”. Reemplazarlos por: método inválido, CVU/CBU inválido, banco/titular inválido, guardado válido.
- Verificar que los valores ingresados se conservan tras el error y que el formulario principal no pierde estado.
- Mantener F2 como primer BUILD, sin `D-01`, `D-03`, `D-04` ni migración.

### 3.3. F6A — Aviso de método base con configuración manual

**Veredicto: útil como P0, pero el alcance y la aceptación no son implementables como están. No es una fase “sin decisión” en el sentido estricto; necesita un contrato técnico de advertencia.**

#### Hallazgos

1. **La venta manual ya nace pendiente.** `VentaController.php:229-241` marca `esPendiente` si hay una PMC manual para el método. F6A no debe “marcar la venta como pendiente” ni persistir un motivo nuevo; la venta ya está pendiente. Lo que falta es mostrar el motivo. Si F6A escribe en `Venta.pagos`, `Payment.metadata` o agrega columnas, deja de ser una fase de aviso y se solapa con F8/D-04.
2. **El conjunto de métodos base no está definido.** `PosController.php:76-84` incluye `EFECTIVO`, `DEBITO`, `CREDITO` y `CUENTA_CORRIENTE`. `D-07` habla de efectivo/débito/crédito. Si F6A incluye `CUENTA_CORRIENTE`, puede generar falsos positivos y una venta pendiente de cuenta corriente que no es el caso que se quiere advertir. Hay que definir el conjunto exacto y excluir `CUENTA_CORRIENTE` salvo decisión expresa.
3. **La deduplicación del POS descarta la metadata manual.** `Terminal.vue:114-137` conserva el método base y descarta `paymentMethodConfigId`, `provider` y `display_data` de la PMC manual. Para advertir antes de vender, F6A debe fusionar esa metadata en la entrada base o agregar un mapa de advertencias. El plan incluye `Terminal.vue`, pero no define el contrato.
4. **El backend ya tiene la información para el aviso.** `PosController` carga solo PMCs `channel=MANUAL` (`:60-74`) y `VentaController` devuelve `display_info` con `config_id`, `provider` y `display_data` (`:461-482`). El aviso puede derivarse de esos datos sin persistencia.
5. **La dependencia de F2 no se justifica.** F2 es el formulario de configuración; no hace falta para mostrar una advertencia en el POS. F1 solo es necesaria si se quiere normalizar el label con seguridad. F4 es opcional. F6A puede ejecutarse después de F2 o en paralelo con F3, pero no debe declarar F2 como bloqueo lógico.
6. **“No hay pendiente silencioso” no es verificable si el modal se puede cerrar.** La venta sigue pendiente hasta que se confirme. La aceptación debe ser “el POS y el modal muestran el motivo; la venta sigue pendiente; no se mueve caja hasta confirmar”.

#### Correcciones exigidas

- Definir F6A como **aviso no persistente**: sin columnas, sin cambios en `Venta.pagos`, sin `Payment.metadata`.
- Definir explícitamente los métodos base y excluir `CUENTA_CORRIENTE` del aviso salvo que producto lo incluya.
- Especificar el contrato de datos: `warning`/`reason` en `display_info`, o un mapa derivado de `paymentMethods` en `Terminal.vue`.
- Quitar F2 como dependencia; dejar F1 como opcional para canonicalización.
- Cambiar la aceptación a “aviso visible en POS y modal; venta pendiente; caja sin movimiento antes de confirmar”.
- Agregar test de método base sin PMC (sin aviso), método base con PMC (aviso), y regresión de venta base normal.

### 3.4. F7A — Aviso de transferencia sin datos

**Veredicto: solo es viable para “vacío” o “todos los valores en blanco”. “Incompleto” no se puede implementar sin `D-03`. Además, falta `Terminal.vue` y falta definir qué métodos cuentan como transferencia.**

#### Hallazgos

1. **`display_data` vacío y `display_data` con claves vacías son casos distintos.** El formulario envía siempre `alias`, `cvu`, `cbu`, `banco` y `titular`; el backend los guarda como strings vacíos (`Index.vue:84-95,110`; `ConfiguracionController.php:200-210`). Entonces `Object.keys(display_data).length === 0` no detecta el caso real. Hay que filtrar valores en blanco y considerar “sin datos” cuando **todos** los valores están vacíos.
2. **El modal no filtra filas vacías.** `ConfirmarPagoModal.vue:142-147` usa `v-if="... && display_data"`; un array vacío es truthy y un objeto con claves vacías también. Por eso puede renderizar una caja vacía o filas con valores vacíos. F7A debe filtrar las entradas con valor no vacío y mostrar el aviso solo si no queda ninguna.
3. **“Parcial muestra aviso” no es posible sin `D-03`.** No hay contrato de campos obligatorios por método/proveedor. F7A puede mostrar “faltan datos” solo si todos están vacíos, o mostrar únicamente los campos cargados. Declarar “incompleto” sin `D-03` es inventar una regla comercial.
4. **No está definido qué métodos son “transferencia”.** `Terminal.vue:139-142` excluye `EFECTIVO`, `DEBITO`, `CREDITO` y `CUENTA_CORRIENTE`; por lo tanto `MERCADO_PAGO`, `VIUMI` y `TRANSFERENCIA` quedan en el grupo. Si F7A advierte para todo el grupo, una PMC manual de Mercado Pago o viüMi sin `display_data` mostrará “transferencia sin datos” aunque no sea transferencia. Hay que limitar el aviso a `TRANSFERENCIA` o definir un contrato por método.
5. **Falta `Terminal.vue` en la lista de archivos.** El objetivo y las pruebas hablan del POS, pero F7A solo lista `ConfirmarPagoModal.vue`, `Configuracion/Index.vue` y `VentaController`. Si el aviso es antes de la venta, hay que incluir `Terminal.vue`; si es solo en el modal, hay que corregir el objetivo y las pruebas.
6. **F2 no es una dependencia lógica.** El aviso se deriva de `display_data`; no necesita que el formulario muestre errores. F4 es opcional. F7A puede ejecutarse en paralelo con F2/F3, con coordinación de `ConfirmarPagoModal.vue`.

#### Correcciones exigidas

- Renombrar el alcance a **“aviso de transferencia sin datos”** y limitarlo a `TRANSFERENCIA` (o definir explícitamente el contrato por método).
- Implementar detección de “todos los valores en blanco”, no solo `Object.keys`.
- Filtrar filas vacías en `ConfirmarPagoModal.vue`; no renderizar una caja vacía.
- Quitar “parcial muestra aviso” o moverlo a F7B/D-03. La prueba parcial debe verificar que solo se muestran campos cargados, no que se declara incompleto.
- Agregar `Terminal.vue` si el aviso es en el POS antes de la venta; si no, corregir el objetivo.
- Quitar F2 como dependencia.
- Agregar test de transferencia vacía, todos los valores en blanco, parcial con campos cargados, y no regresión de Mercado Pago/viüMi.

---

## 4. Revisión fase por fase

| Fase | Veredicto | Observación principal |
|---|---|---|
| **F0** | Correcta como auditoría, incorrecta como compuerta única | Exige cerrar `D-01` a `D-15`; F1/F2/F6A/F7A no deberían esperar todas. Dividir en F0A (auditoría/aliases read-only) y F0B (decisiones). |
| **F1** | Viable con correcciones mayores | No puede empezar con F0 tal como está; falta mapa de call sites; la normalización actual no translitera; el checkout público es ambiguo. Ver 3.1. |
| **F2** | Viable y autónoma | UI-only; no agregar campos requeridos; “transferencia sin datos” no es un error actual. Ver 3.2. |
| **F3** | Viable con defaults seguros; dependencias excesivas | Edición por ID y aislamiento se pueden hacer con método inmutable y roles actuales. `D-02`/`D-13` solo bloquean ampliar esas reglas; F2 no es dependencia dura. |
| **F4** | Sobrecargada y con dependencias ocultas | Incluye snapshot que pertenece a F8; necesita `D-04`, `D-15` y `D-06` además de `D-01`/`D-12`; unique/check puede fallar con datos históricos. Dividir. |
| **F5** | Viable después de `D-06`; orden con F8 riesgoso | Cambiar la semántica de `provider` antes del snapshot puede alterar etiquetas históricas. Conservar el `provider` visible o ejecutar después de F8. `D-13` no está en 12.2. |
| **F6A** | Útil, alcance ambiguo | “Marcar pendiente” ya ocurre; no persistir; definir métodos base y contrato de aviso; F2 no es dependencia. Ver 3.3. |
| **F6B** | Correcta, pero puede acotarse | `D-07` es el bloqueo real; F4/F5 solo si se usan identidad/canal. No necesita más que F6A y la política. |
| **F7A** | Útil solo para vacío/todos en blanco | “Incompleto” requiere `D-03`; falta `Terminal.vue`; el grupo transferencia incluye Mercado Pago/viüMi; F2 no es dependencia. Ver 3.4. |
| **F7B** | Correcta | `D-03` y `D-05`; F7A; F4 solo si se usa identidad. No bloquear retroactivamente configuraciones existentes. |
| **F8** | Necesaria, pero archivos incompletos | Ruta errónea de `TicketBuilder`; faltan `VentaOperacionService` y `PosController`; hay que cubrir todos los `MovimientoCaja`; `D-15`; solapamiento con F4. |
| **F9** | Crítica; dividir | F9A: confirmación segura/idempotente/multi-tenant sin F8/D-05. F9B: política de configuración ausente con `D-05`/F8. Hoy mueve caja antes de aprobar y deja `Payment` huérfano. |
| **F10** | Condicional; dividir | F10A: deshabilitar/ocultar gateway con `D-10` solamente. F10B: gateway completo con `D-06`/`D-11`/`D-14` y F4/F5. El POS ya solo carga PMCs manuales. |
| **F11** | Dos dominios independientes | F11A onboarding (`D-09`); F11B tienda (`D-08`). F4/F5 son condicionales, no requisitos de toda la fase. |
| **F12** | Correcta como cierre | El baseline de 23 fallos no fue re-verificado; hay que registrarlo antes de cada fase y no ocultar fallos. |

### 4.1. F0 — La compuerta monolítica

F0 mezcla dos cosas distintas:

- **Auditoría read-only y lista de aliases**, que es lo único que F1 necesita.
- **Decisiones de producto `D-01` a `D-15`**, que bloquean F3 a F11.

El plan dice en `:370` que F0 es la puerta de entrada para F3 a F11, excepto F6A/F7A, y en `:384` que F1 depende de F0 para confirmar aliases. Pero F0 exige “cerrar las decisiones D-01 a D-15” (`:365`). Eso contradice `:782` (“F1 puede autorizarse sin decisiones comerciales”) y `:11.4` (“primer BUILD F1 + F2”). La corrección es separar F0A/F0B y hacer que F1 dependa solo de F0A.

### 4.2. F3 — Edición por ID y aislamiento

El plan la bloquea con `D-02` y `D-13`, pero el código actual ya tiene un default seguro parcial:

- La UI deshabilita el select de método en edición (`Index.vue:610`: `:disabled="!!editingPmcId"`).
- Las rutas de PMC están dentro de `role:SuperAdmin|Administrador Global` (`routes/web.php:362`).
- El borrado ya valida `comercio_id` (`ConfiguracionController.php:216-223`).

Por lo tanto, **F3A puede implementar edición por ID, no duplicar filas y aislamiento cross-tenant sin esperar `D-02`/`D-13`**, siempre que:

- El método siga inmutable (default actual de la UI).
- Se mantenga el rol actual y se agregue una verificación de comercio que falle cerrado.
- `D-02`/`D-13` se reserven para ampliar la edición del método o los permisos.

F2 no es una dependencia dura de F3; sirve para mostrar errores, pero la edición por ID puede implementarse sin ella. Si se quiere una UX completa, F3 va después de F2.

### 4.3. F4 — Unicidad, identidad y snapshot

F4 tiene cuatro problemas:

1. **Se solapa con F8.** F4 dice “persistir `config_id` y snapshot mínimo al crear la venta” (`:424`), mientras F8 es “snapshot histórico de etiquetas y datos” (`:503-515`). Hay que decidir: F4 persiste solo `config_id`/método canónico; F8 persiste etiqueta, `provider`, `channel` y `display_data`. Si F4 persiste todo, F8 queda duplicada.
2. **No declara `D-04`.** Si F4 persiste snapshot, necesita `D-04`; sus dependencias (`:426`) solo dicen `D-01`, `D-12`, F3 y F1. Es una dependencia oculta.
3. **No declara `D-15`.** La resolución de duplicados y el archivado/desactivación de filas dependen de qué pasa con PMC con ventas o pendientes. `D-15` bloquea F4 según `:827`, pero no está en `:426`.
4. **No declara `D-06`.** F4 asume que `provider` no es parte de la identidad (`:302`), pero `D-06` puede decidir lo contrario. Si `D-06` convierte `provider` en identificador técnico, F4 puede necesitar rehacer la identidad o adelantar F5.

Además, el unique/check de F4 puede fallar si hay filas históricas no canónicas (`Débito`, `VIÜMI`, `TARJETA`) o duplicados con `provider` null. F1 no modifica datos almacenados. F4 necesita un backfill no destructivo o postergar el constraint; el plan lo menciona en `10.3`, pero no lo incluye en los cambios de F4.

**Recomendación:** dividir F4 en:

- **F4A:** resolvedor único, `config_id`, identidad y validación de comercio/método/canal; sin snapshot de etiqueta.
- **F4B:** unicidad, duplicados, archivo lógico, backfill y constraints, después de `D-12`/`D-15`.

### 4.4. F5 — Semántica de `provider`, canal y credenciales

F5 está bien ubicada después de F4, pero antes de F8 es riesgosa: si cambia `provider` de etiqueta visible a identificador técnico, todas las ventas y movimientos históricos que resuelven la etiqueta contra la PMC actual cambiarán de texto. El plan reconoce el riesgo (`:441`) pero no lo resuelve. Opciones:

- Conservar el `provider` visible original y agregar un campo separado para el proveedor técnico.
- Ejecutar F5 después de F8, cuando el histórico ya tenga snapshot.
- Si F5 cambia `provider`, migrar las etiquetas históricas antes de cambiar la semántica, con snapshot previo.

También hay una inconsistencia: `D-13` aparece como dependencia de F5 en `:236`, pero no en `12.2` (`:786`) ni en `12.4` (`:825`). Aclarar si F5 necesita permisos o no.

### 4.5. F6B y F7B

- **F6B** depende de `D-07`, F6A y, según el plan, F4/F5. Si la política es solo bloquear/confirmar métodos base, no necesita F4/F5; solo necesita saber qué método base y qué configuración manual existe. F4/F5 son necesarios si se agrega identidad/canal, no para la política mínima.
- **F7B** depende de `D-03`/`D-05`, F7A y F4. F4 solo es necesario si se usa `config_id`/identidad; la validación de campos de `display_data` puede hacerse sin F4. No se deben bloquear configuraciones existentes de forma retroactiva sin decisión.

### 4.6. F8 — Snapshot histórico

F8 es necesaria, pero la lista de archivos está incompleta:

- `app/Services/TicketBuilder.php` no existe; la clase real es `app/Services/Ticket/TicketBuilder.php`.
- Falta `app/Services/VentaOperacionService.php`, que crea `MovimientoCaja` en anulación y devolución (`:116`, `:269`). Si F8 agrega un snapshot a `MovimientoCaja`, esos movimientos también deben poblarlo o copiarlo del snapshot original.
- Falta `app/Http/Controllers/PosController.php`, que crea el `MovimientoCaja` de fondo inicial (`:166`).
- `CajaDiariaController` crea fondo inicial y movimientos manuales (`:156`, `:167`, `:206`); está incluido, pero hay que cubrir los tres puntos.
- `ConsumidorController` crea el movimiento de cobro de deuda (`:305`); está incluido.
- `VentaController` crea movimientos en venta y confirmación (`:315`, `:619`); está incluido.

Además, F8 depende de `D-15` según `12.4`, pero no lo declara en `:510`. Y el snapshot de `Venta.pagos` debe tener un contrato explícito para no romper `Venta::getPagosDisplayAttribute` ni `VentaOperacionService::anular`, que hoy leen `pagos_display` y resuelven etiquetas contra la configuración actual.

### 4.7. F9 — Confirmación y anulación

F9 es la fase más crítica y la que más se beneficia de una división:

- **F9A — Confirmación segura, idempotente y multi-tenant.** No necesita F8 ni `D-05`. Debe:
  - Bloquear si la venta no pertenece al comercio, incluso cuando `$comercioId` es null (hoy se saltea la verificación).
  - Verificar que exista un `Payment` válido, del comercio, con monto/canal/proveedor/referencia correctos y estado aprobado cuando el flujo lo exija.
  - No mover caja ni deuda antes de validar el pago.
  - Ser idempotente: una segunda confirmación no duplica movimientos.
  - No aprobar “el último pendiente del proveedor” a ciegas; `PaymentRecorder::approve` busca por proveedor y `latest()` (`:198-215`), lo que puede aprobar el intento equivocado.
- **F9B — Política de configuración ausente/desactivada.** Necesita `D-05` y, si se usa snapshot, F8. Aquí sí se decide si se bloquea o se permite con snapshot.

El código actual confirma el problema: `confirmarPago` mueve caja/deuda en `:619-627` y recién después llama a `approve` en `:629-631`; si no hay configuración, `$config` es null y no aprueba nada, pero la caja ya se movió. Además, `approve` devuelve null sin excepción, así que la venta queda completada con un `Payment` huérfano.

### 4.8. F10 — Gateway latente

F10 mezcla dos alternativas incompatibles:

- **F10A — Deshabilitar/ocultar gateway de forma segura.** Solo necesita `D-10` (decisión de sacar el gateway de alcance). El POS ya carga únicamente PMCs manuales (`PosController.php:60-74`), así que el gateway está latente; deshabilitarlo es una guarda backend y una limpieza de UI, no requiere `D-06`, `D-11`, `D-14`, F4 ni F5.
- **F10B — Completar gateway.** Necesita `D-10` (incluirlo), `D-06`, `D-11`, `D-14`, F4/F5, credenciales, webhook idempotente y pruebas sandbox/fake.

El plan condiciona incluso el modo deshabilitado a todas las decisiones y a F4/F5 (`:538`). Eso bloquea innecesariamente una salida segura. Separar F10A/F10B.

### 4.9. F11 — Onboarding y tienda pública

F11 mezcla dos dominios:

- **F11A — Onboarding (`D-09`).** `OnboardingService.php:219-228` ya marca los medios de pago como opcionales. Si la decisión es mantenerlo opcional, F11A puede ser solo un aviso de completitud; si es exigir, se valida en servidor. No necesita F4/F5.
- **F11B — Tienda pública (`D-08`).** `Welcome.vue:590-595` solo ofrece efectivo y Mercado Pago; `PedidoWebController.php:30-40` acepta `transferencia` igual. Si `D-08=A`, hay que dejar de aceptar `transferencia` o mantenerla solo para pedidos existentes; si `D-08=B/D`, hay que mostrar datos de `comercios.transferencia_*` y comprobante. No necesita F4/F5 salvo que se reutilice identidad de PMC.

El plan declara F5 como dependencia de F11 (`:552`) y `12.2` también (`:794`), pero F5 no es necesaria para onboarding ni para la tienda si se usan los datos de `comercios.transferencia_*`. Es una dependencia condicional que no debe bloquear la fase completa.

---

## 5. Matriz de dependencias, decisiones y bloqueos reales

| Fase | Lo que dice el plan | Bloqueo real mínimo | Ajuste recomendado |
|---|---|---|---|
| F0 | `D-01` a `D-15`; puerta de entrada | F0A: auditoría/aliases read-only; F0B: decisiones | Dividir; F1 depende solo de F0A. |
| F1 | F0 para aliases; no `D-01`/`D-04` | F0A + lista de aliases + mapa de call sites | No esperar F0B ni todas las decisiones. |
| F2 | Ninguna | Ninguna | Primer BUILD. UI-only. |
| F3 | `D-02`, `D-13`, F1, F2 | F1 + route binding/tenant; `D-02` solo si se permite cambiar método; `D-13` solo si se amplían roles | F3A con método inmutable y roles actuales; F2 no es dura. |
| F4 | `D-01`, `D-12`, F3, F1 | `D-01`, `D-12`, `D-15`; `D-04` si persiste snapshot; `D-06` si `provider` es identidad | Dividir F4A/F4B; mover snapshot a F8. |
| F5 | `D-06`, `D-11`, `D-14`, F4, F1 | `D-06`; `D-11`/`D-14` solo si hay gateway/canal; F4; F8 si cambia etiquetas históricas | Ejecutar después de F8 o conservar `provider` visible. |
| F6A | F1/F2; F4 opcional | Ninguna decisión comercial; contrato de aviso; F1 opcional | Quitar F2; definir métodos base; no persistir. |
| F6B | `D-07`, F6A, F4, F5 | `D-07`, F6A; F4/F5 solo si identidad/canal | Acotar a política de métodos base. |
| F7A | F2; F4 opcional | Ninguna decisión; definir “sin datos” y método | Solo vacío/todos en blanco; agregar `Terminal.vue` si aplica. |
| F7B | `D-03`, `D-05`, F7A, F4 | `D-03`, `D-05`, F7A; F4 solo si identidad | No bloquear configuraciones existentes retroactivamente. |
| F8 | `D-04`, F4, F5 | `D-04`, `D-15`, F4; F5 si `provider`/canal | Incluir todos los `MovimientoCaja`; corregir ruta de `TicketBuilder`. |
| F9 | F8, `D-05`, F4/F5 | F9A: sin F8/`D-05`; F9B: `D-05` + F8 + `D-15` | Dividir; validar `Payment` antes de mover caja. |
| F10 | `D-10`, `D-11`, `D-14`, F4/F5 | F10A: `D-10`; F10B: `D-10`, `D-06`, `D-11`, `D-14`, F4/F5 | Dividir modo deshabilitado de gateway completo. |
| F11 | `D-08`, `D-09`, F5, F4 | F11A: `D-09`; F11B: `D-08`; F4/F5 condicionales | Dividir onboarding y tienda. |
| F12 | Todas | Fases autorizadas y baseline actualizado | No introduce reglas nuevas. |

### 5.1. Dependencias incorrectas o faltantes

**Incorrectas o excesivas:**

- **F1 → F0 completo.** F1 solo necesita la lista de aliases, no las 15 decisiones. La contradicción con `11.4` es la más grave del plan.
- **F6A → F2.** F2 no tiene relación con el aviso del POS.
- **F7A → F2.** F7A se deriva de `display_data`; no necesita el formulario de errores.
- **F3 → F2.** F3 puede editar por ID sin F2; F2 mejora la UX, no es bloqueo técnico.
- **F4 → F3.** La edición por ID es útil, pero el resolvedor/identidad puede implementarse antes; F3 no es estrictamente necesaria para F4A.
- **F10 → F4/F5/`D-11`/`D-14` para el modo deshabilitado.** Deshabilitar gateway no requiere completar su semántica.
- **F11 → F5/F4.** Onboarding y tienda no necesitan F5/F4 salvo que se reutilice identidad de PMC.

**Faltantes:**

- **F4 → `D-04`** si persiste snapshot; **F4 → `D-15`** para archivo/desactivación; **F4 → `D-06`** si `provider` puede ser identidad.
- **F8 → `D-15`** para el ciclo de vida de PMC con ventas/pendientes.
- **F9 → `D-15`** para anulación/devolución de PMC desactivadas.
- **F5 → `D-13`** si la fase toca permisos de edición (hoy aparece en `:236` pero no en `12.2`/`12.4`).
- **F1 → `CajaDiariaController`, `PaymentMethodConfiguration`, `PosController`, `ConfiguracionController`, `Venta`, `MovimientoCaja`**; y `PedidoWebController` si “checkout” incluye tienda pública.
- **F8 → `VentaOperacionService` y `PosController`** para todos los `MovimientoCaja`.
- **F9 → `Payment`/`PaymentRecorder`** con identidad de pago (id, referencia, monto, canal), no solo proveedor.

### 5.2. Inconsistencias entre secciones

- `D-03`: `:156` dice que bloquea F7B y F9; `:815` dice solo F7B. F9 no usa `D-03` en sus dependencias (`:524`); aclarar.
- `D-13`: `:236` dice que bloquea F3 y F5; `:786` no incluye `D-13` en F5; `:825` dice solo F3. Aclarar.
- `D-15`: `:252` dice que bloquea F4, F8 y F9; `:426` no incluye `D-15` en F4; `:524` no lo incluye en F9; `:827` sí. Agregar o quitar.
- `D-04`: `:164` dice que bloquea F4, F8 y F9; `:426` no incluye `D-04` en F4; `:816` dice F8 y F9. Si F4 persiste snapshot, falta; si no, quitar el snapshot de F4.
- `D-06`: `:180` dice que bloquea F5 y F10; F4 asume `provider` como metadata (`:302`), pero no declara `D-06`. Documentar la asunción o agregar la dependencia.
- `D-01`: `:140` dice que bloquea F4, F8 y F9; F8/F9 dependen de F4, así que es indirecto. No es un error grave, pero conviene distinguir dependencia directa/indirecta.
- `F4/F8`: ambos prometen snapshot. Definir cuál es la fuente y cuál consume.
- `F9/F8`: `11.2` dice que no se paralelizan; F9A podría paralelizarse con F8 si no usa snapshot. La regla debe aplicar solo a F9B.
- `F10/F11`: `11.2` dice que pueden paralelizarse si las decisiones están cerradas; si F10A es solo deshabilitar, puede ir antes de F4/F5.

---

## 6. Hallazgos transversales

### 6.1. Multi-tenancy

- **`VentaController@store`, `confirmarPago`, `cancelar`, `devolver` y `pdf` usan `if ($comercioId)` y no fallan cerrado cuando es null.** En `store`, el `turno_caja_id` se busca con `when($comercioId, ...)` y luego `findOrFail`; si no hay comercio, no se filtra. En `confirmarPago` y `cancelar`, la verificación de pertenencia se saltea si `$comercioId` es null. Esto contradice la skill de multi-tenancy (“si no se puede demostrar, se falla cerrado”). F9 debe corregir confirmación; F1/F3 deben corregir los endpoints que tocan. Si se considera fuera de alcance, debe quedar como deuda explícita, no como “cubierto”.
- **PMC:** `storePaymentMethodConfig` deriva `comercio_id` del usuario y `destroy` valida pertenencia (`:216-223`). Falta la ruta de update; F3 debe usar route model binding con verificación y no aceptar `comercio_id` del request.
- **Tests:** `CrossTenantAttackTest.php` no tiene casos de PMC ni de `payment_method_configurations`. F3/F4 deben agregarlos antes de cerrar; no alcanza con la matriz existente de productos/ventas.
- **Roles:** las rutas de PMC están dentro de `role:SuperAdmin|Administrador Global` (`routes/web.php:362`). No hay policy específica. `D-13` debe definir si se mantiene, se amplía o se separa lectura/edición; mientras tanto, el default actual es restrictivo y sirve para F3A.

### 6.2. Ventas pendientes y confirmación

- La venta manual ya nace `PENDING` cuando hay PMC manual (`VentaController.php:229-241`). F6A no debe cambiar el estado; solo mostrar el motivo.
- `confirmarPago` no verifica el estado del `Payment` antes de mover caja/deuda; solo intenta aprobar si encuentra una PMC manual vigente. Si la PMC fue borrada, mueve caja y deja el `Payment` pendiente/huérfano. F9A debe invertir el orden: validar pago, luego mover caja, luego marcar venta.
- `PaymentRecorder::approve` busca el último `PENDING` por proveedor (`:198-215`), sin `reference`, monto ni canal. Con múltiples intentos, puede aprobar el pago equivocado. F9 debe usar el `Payment` correcto (id/referencia/monto/canal) y fallar si no existe.
- La confirmación debe ser idempotente. Hoy el `lockForUpdate` y el estado `PENDING` ayudan, pero no alcanza si el `Payment` quedó huérfano o si hay dos requests concurrentes; F9 debe cubrir doble confirmación.

### 6.3. Caja

- `CajaDiariaController::crearMovimientoManual` acepta cualquier `metodo_pago` string y lo persiste (`:189-213`). F1 debe validarlo; si se permite un valor libre, `clasificarMetodo` debe manejarlo sin `ValueError`.
- `clasificarMetodo` usa `MetodoPago::from` (`:28-33`), no `fromString`; un valor histórico inválido puede romper `getBalance` y `getSesionesAbiertasGlobal`. F1 debe cubrir este punto o F8 debe tolerarlo.
- Los movimientos de caja se crean en cinco archivos/controladores. Cualquier snapshot de F8 debe cubrir todos o documentar por qué algunos no lo necesitan. Los fondos iniciales (`PosController`, `CajaDiariaController`) y las anulaciones (`VentaOperacionService`) son los más fáciles de olvidar.

### 6.4. Datos históricos

- F1 no modifica filas; F4/F8 deben manejar valores no canónicos. Un check constraint de `metodo_pago` en F4 puede fallar si quedan `Débito`, `VIÜMI`, `TARJETA` o valores libres. El plan debe elegir: backfill no destructivo antes del constraint, o constraint postergado.
- F3 edita `provider` antes de F8; como `resolveDisplayLabel` usa el `provider` actual, editar una PMC cambia las etiquetas de ventas y movimientos históricos. F3 debe bloquear la edición de `provider` hasta F8 o conservar el valor visible original.
- F5 cambia la semántica de `provider` antes de F8; mismo riesgo. Conservar el `provider` visible o ejecutar F5 después de F8.
- F8 debe definir el contrato del snapshot y el fallback legacy. `Venta::getPagosDisplayAttribute` y `VentaOperacionService::anular` hoy resuelven contra la configuración actual; si el snapshot no está, deben seguir funcionando sin recalcular historia.

### 6.5. Gateway y checkout

- `PaymentService` no se inicializa con `forCommerce()` en el flujo POS (`VentaController.php:510`), y `provider_config` no existe en el modelo/migración; `$cfg->provider_config['user_id'] ?? null` no emite warning pero siempre da null (`:770-771`). F10B debe corregirlo; F10A solo debe deshabilitar.
- `confirmarPago` podría cerrar una venta gateway sin aprobación real si se corrige solo el contexto. F9 debe exigir `PaymentStatus::APPROVED` para gateway y no permitir que la confirmación manual sustituya la aprobación.
- El checkout público usa lowercase (`efectivo`, `transferencia`, proveedores) y no el enum. F1 no debe mezclarlo sin un contrato explícito; F11 es el lugar para la tienda.
- `Welcome.vue` no ofrece transferencia, pero `PedidoWebController` la acepta. Si `D-08=A`, F11B debe dejar de aceptarla o restringirla a pedidos existentes; si `D-08=B/D`, debe mostrar datos y comprobante. No se debe romper la lectura de pedidos viejos.

### 6.6. Onboarding y permisos

- `OnboardingService` ya trata medios de pago como opcionales (`:219-228`). `D-09` no debe asumir que hoy es obligatorio; la decisión es si se exige, se advierte o se deja igual.
- No hay policy de PMC. F3 debe agregar la verificación de comercio y, si `D-13` lo pide, una policy de lectura/edición/borrado. Mientras no haya decisión, el default actual (rol global + tenant check) es aceptable para F3A.
- Los roles `SuperAdmin|Administrador Global` son globales; si un usuario no tiene comercio/sucursal, el endpoint debe fallar cerrado, no crear una PMC con `comercio_id` null.

---

## 7. Riesgos de regresión

| Riesgo | Impacto | Fase | Mitigación exigida |
|---|---|---|---|
| `tryFromString` devuelve null y algún display hace `->label()` | Fatal en caja, POS o configuración | F1 | Actualizar todos los call sites y definir fallback de display; tests de valores históricos. |
| F1 rechaza un alias que hoy se acepta | Ventas o cobros dejan de funcionar | F1 | Lista de aliases firmada; tests de aliases y de clientes actuales. |
| F1 cambia el fallback a `EFECTIVO` y no valida antes | Venta con método desconocido se cobra como efectivo | F1 | Validar antes de stock/caja/deuda; test de rechazo 422 y de no movimiento. |
| F2 agrega campos requeridos | Bloquea configuraciones existentes y cambia una regla comercial | F2 | F2 UI-only; campos obligatorios solo en F7B/D-03. |
| F3 edita `provider` | Cambian etiquetas históricas de ventas/caja | F3 | Bloquear edición de `provider` hasta F8 o conservar etiqueta visible. |
| F4 agrega unique/check con datos no canónicos | Migración falla o se pierden duplicados | F4 | Auditoría F0A, backfill no destructivo, constraints después de limpiar. |
| F4 persiste snapshot y F8 lo repite | Retrabajo y contratos incompatibles | F4/F8 | F4 solo `config_id`/método; F8 etiqueta/provider/channel/display_data. |
| F6A incluye `CUENTA_CORRIENTE` | Falsos positivos y pendientes de CC | F6A | Definir métodos base; excluir CC salvo decisión. |
| F6A persiste el motivo | Se solapa con F8/D-04 y cambia datos | F6A | Aviso no persistente; solo `display_info`/UI. |
| F7A detecta solo `Object.keys` | No detecta el caso real (claves vacías) | F7A | Filtrar valores en blanco; test de todas las claves vacías. |
| F7A advierte Mercado Pago/viüMi como transferencia | Aviso incorrecto | F7A | Limitar a `TRANSFERENCIA` o contrato por método. |
| F8 no cubre todos los `MovimientoCaja` | Caja histórica inconsistente | F8 | Incluir `VentaOperacionService`, `PosController`, `CajaDiariaController`, `VentaController`, `ConsumidorController`. |
| F9 mueve caja antes de validar el pago | Venta cerrada sin cobro; `Payment` huérfano | F9A | Validar `Payment` primero; no mover caja si no hay pago válido; idempotencia. |
| F9 aprueba el último `PENDING` del proveedor | Pago equivocado | F9A | Usar `Payment` id/referencia/monto/canal; no aprobar por proveedor a ciegas. |
| F10 completo sin credenciales/fake | Llamadas reales, doble cobro | F10B | F10A deshabilitar; F10B solo con entorno de prueba y aprobación. |
| F11 elimina `transferencia` sin preservar pedidos viejos | Pedidos existentes ilegibles | F11B | No borrar datos; mostrar pedidos viejos; validar nuevos. |
| Baseline de 23 fallos no re-verificado | No se distingue regresión nueva | F12 | Registrar baseline por fase; no ocultar fallos. |

---

## 8. Orden recomendado y primera fase autorizable

### 8.1. Orden recomendado

1. **F2 — ahora.** UI-only, sin decisiones, sin migración. Es la primera fase autorizable.
2. **F0A + F1 — después de la lista de aliases.** F0A es auditoría read-only; F1 normaliza con `tryFromString`, actualiza todos los call sites y no toca datos. No esperar `D-01` a `D-15`.
3. **F6A — aviso no persistente.** Definir métodos base y contrato de advertencia; sin F2.
4. **F7A — aviso de vacío/todos en blanco.** Definir que es solo `TRANSFERENCIA`; agregar `Terminal.vue` si aplica; sin “parcial”.
5. **F3A — edición por ID y aislamiento.** Método inmutable, roles actuales, tenant fail-closed. Si producto quiere cambiar método o ampliar roles, esperar `D-02`/`D-13` y hacer F3B.
6. **F4A — resolvedor, `config_id` e identidad.** Después de `D-01`/`D-12`/`D-15`; sin snapshot de etiqueta. **F4B** — unicidad, duplicados y backfill.
7. **F5 — `provider`/canal.** Después de `D-06`; después de F8 o conservando el `provider` visible.
8. **F8A — snapshot al crear venta.** **F8B — caja/ticket/consultas y todos los `MovimientoCaja`.** Después de `D-04`/`D-15`.
9. **F9A — confirmación segura/idempotente/multi-tenant.** Puede ir antes de F8B si no usa snapshot. **F9B — configuración ausente/desactivada** después de `D-05`/F8.
10. **F10A — deshabilitar/ocultar gateway.** Solo `D-10`. **F10B — gateway completo** después de `D-06`/`D-11`/`D-14`/F4/F5.
11. **F11A — onboarding.** **F11B — tienda pública.** Después de `D-09`/`D-08`; F4/F5 solo si se reutiliza identidad.
12. **F12 — regresión y cierre.**

### 8.2. Primera fase autorizable: F2

**F2 es la única fase que puede autorizarse hoy sin una decisión comercial y sin una dependencia técnica pendiente.**

Condiciones para autorizarla:

- Alcance limitado a `resources/js/Pages/Configuracion/Index.vue` y tests de validación.
- No agregar campos obligatorios ni cambiar reglas de `display_data`.
- Renderizar `pmcForm.errors` por campo y un resumen.
- Probar método inválido por HTTP, CVU/CBU inválido, banco/titular inválido y guardado válido.
- No tocar `ConfiguracionController` salvo que falte un mensaje; no tocar la base.
- Baseline de tests registrado antes de empezar.

**No autorizar F1 + F2 juntas** hasta que exista la lista de aliases y el mapa de call sites. **No autorizar F6A/F7A como “sin decisión”** hasta corregir su alcance y aceptación. **No autorizar F3/F4** hasta resolver los defaults/dependencias indicados.

---

## 9. Correcciones obligatorias antes de BUILD

### 9.1. Correcciones del plan

| ID | Corrección | Fase afectada |
|---|---|---|
| C-01 | Dividir F0 en F0A (auditoría/aliases read-only) y F0B (decisiones). F1 depende de F0A, no de todas las decisiones. | F0/F1 |
| C-02 | Publicar la lista exacta de aliases, incluidos `TRANSFER`, `MERCADOPAGO`, `CTA_CTE`, `FIADO`, `TARJETA_CREDITO`, `TARJETA_DEBITO`, `TARJETA`; definir los ambiguos. | F1 |
| C-03 | Agregar a F1 todos los call sites de `fromString`/`from`, incluidos `PaymentMethodConfiguration`, `PosController`, `CajaDiariaController`, `ConfiguracionController`, `Venta` y `MovimientoCaja`; definir fallback de display. | F1 |
| C-04 | Aclarar si el checkout público (`PedidoWebController`) entra en F1. Si entra, agregarlo con su contrato lowercase; si no, excluirlo explícitamente. | F1 |
| C-05 | Reducir F2 a UI-only; quitar “campo requerido” y “transferencia sin datos” de sus pruebas. | F2 |
| C-06 | F3A con método inmutable y roles actuales; `D-02`/`D-13` solo para ampliar. Agregar tenant fail-closed y tests de PMC en `CrossTenantAttackTest`. | F3 |
| C-07 | Dividir F4 en F4A (resolvedor/identidad/`config_id`) y F4B (unicidad/duplicados/backfill). Mover snapshot de etiqueta a F8. Declarar `D-04`, `D-15` y `D-06` según corresponda. | F4/F8 |
| C-08 | F5 después de F8 o conservando el `provider` visible; aclarar `D-13` en F5. | F5 |
| C-09 | F6A como aviso no persistente; definir métodos base y excluir `CUENTA_CORRIENTE` salvo decisión; quitar F2 como dependencia. | F6A |
| C-10 | F7A solo para vacío/todos en blanco; filtrar valores vacíos en el modal; limitar a `TRANSFERENCIA`; agregar `Terminal.vue` si aplica; quitar “parcial”. | F7A |
| C-11 | F8: corregir ruta a `app/Services/Ticket/TicketBuilder.php`; agregar `VentaOperacionService` y `PosController`; cubrir todos los `MovimientoCaja`; declarar `D-15`. | F8 |
| C-12 | F9: dividir F9A/F9B; validar `Payment` antes de mover caja; usar id/referencia/monto/canal; idempotencia; tenant fail-closed. | F9 |
| C-13 | F10: dividir F10A (deshabilitar, solo `D-10`) y F10B (gateway completo con `D-06`/`D-11`/`D-14`/F4/F5). | F10 |
| C-14 | F11: dividir onboarding (`D-09`) y tienda (`D-08`); preservar pedidos existentes; F4/F5 condicionales. | F11 |
| C-15 | Corregir la matriz de dependencias y las inconsistencias entre secciones 5, 11.4, 12.2 y 12.4. | Transversal |
| C-16 | Registrar el baseline real antes de cada fase; no usar el “23 fallos” como dato verificado si no se corrió. | Transversal |

### 9.2. Criterios de aceptación para autorizar el próximo BUILD

1. La fase tiene una decisión firmada o una declaración explícita de que no toca esa decisión.
2. El alcance de archivos está completo y no incluye nombres de clase/ruta inexistentes.
3. Los tests nuevos fallan sin el cambio y pasan con el cambio.
4. Hay test cross-tenant para endpoints nuevos o modificados; PMC incluida.
5. No hay migración destructiva ni constraint sobre datos no auditados.
6. No se revierten cambios preexistentes del working tree.
7. No se ejecutan ventas, cobros, arqueos ni migraciones en producción.
8. El informe de fase queda en `docs/informes/` con decisiones, archivos, pruebas y pendientes.

### 9.3. Conclusión operativa

El plan es una buena base, pero todavía no es un plan de ejecución cerrado. **F2 puede autorizarse ahora.** F1 debe esperar la lista de aliases y la corrección de call sites. F6A/F7A deben corregir alcance antes de BUILD. F3/F4/F9/F10/F11 deben dividirse para no bloquear trabajo independiente y para evitar estados intermedios inconsistentes.

No se modificó código, base de datos, migraciones, dependencias, `AGENTS.md` ni skills durante esta revisión.
