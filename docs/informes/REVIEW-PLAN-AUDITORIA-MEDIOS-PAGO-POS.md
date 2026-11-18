# REVIEW — Plan de auditoría de Medios de Pago configurables para POS

**Fecha:** 17/11/2026  
**Rol:** REVIEWER — revisión independiente  
**Documento revisado:** `docs/informes/PLAN-AUDITORIA-MEDIOS-PAGO-POS.md`  
**Alcance:** configuración de medios de pago, persistencia, selector del POS, cobro manual, confirmación, anulación/devolución, etiquetas históricas, multi-tenancy y cobertura de pruebas.  
**Modo:** solo lectura. No se modificó código, migraciones, base de datos, configuraciones, dependencias ni `AGENTS.md`. No se ejecutaron ventas, cobros, arqueos, anulaciones ni ninguna otra operación de negocio.

> **Independencia:** este review no asume correctas las conclusiones del informe original. Cada hallazgo fue contrastado contra el código actual. Cuando el informe original y el código no coinciden, se indica expresamente.

---

## 1. Veredicto general

El plan es **valioso y mayoritariamente correcto en el diagnóstico**, pero **no está listo para un BUILD completo tal como está escrito**.

El circuito feliz del POS manual está bien identificado: la venta queda pendiente, el stock se descuenta al crear la venta, la caja o la cuenta corriente se mueven recién al confirmar, y las etiquetas se resuelven con la configuración actual. También son reales los riesgos de:

- métodos base configurados como manuales sin aviso en el POS;
- transferencias sin datos;
- métodos desconocidos convertidos silenciosamente en efectivo;
- etiquetas históricas mutables;
- ausencia de tests de `PaymentMethodConfiguration`;
- duplicados por `provider = NULL`;
- flujo gateway incompleto.

Sin embargo, el plan contiene **tres afirmaciones materiales que deben corregirse antes de implementar**:

1. **C-04 está mal caracterizado en la parte del modal.** El modal agrupa *pagos*, no *configuraciones PMC*. El POS normal impide repetir el mismo método, por lo que varias PMCs no generan varias tarjetas. La selección múltiple del modal solo aparece ante un POST directo con pagos repetidos, y aun así cada tarjeta confirma la venta completa, no una configuración.
2. **C-12 está mal diagnosticado.** `provider_config` no existe en el modelo ni en la migración, pero `?? null` **no emite warning**. El impacto real no es un warning: es que no hay configuración de gateway por PMC y que el flujo gateway del POS falla antes por otra causa (`PaymentService` no recibe `forCommerce()`).
3. **C-14 contiene una afirmación falsa.** La tienda online **no consume** `comercios.transferencia_*`. El checkout público (`Welcome.vue`) solo ofrece efectivo y Mercado Pago; `PedidoWebController` acepta `transferencia` en la validación, pero no usa datos bancarios. El panel del cliente permite subir un comprobante para un pedido por transferencia ya creado, pero eso no consume `comercios.transferencia_*`. No hay un conflicto runtime confirmado entre ambas fuentes.

Además, la prioridad P0 original incluye `orderBy` como solución a C-04. **`orderBy` por sí solo no alinea POS y backend**: el POS se queda con la primera fila de una colección y `loadAllConfigs()` con la última de un `keyBy`; ordenar ambos con el mismo criterio no cambia que uno toma `first` y el otro toma el último. La solución real es una regla única de selección, una restricción de unicidad o persistir la configuración elegida al crear la venta.

**Veredicto:** **APROBADO CON CORRECCIONES MAYORES**. No autorizo un BUILD completo del plan original. Sí es viable un BUILD acotado y por fases después de:

- corregir C-04, C-12 y C-14;
- reemplazar `orderBy` por una solución de identidad/selección;
- subir C-15 a la fase de identidad;
- cerrar las decisiones de producto de la sección 5;
- agregar los tests de normalización, multi-tenant y snapshot de la sección 7.

---

## 2. Tabla de hallazgos C-01 a C-16

**Estados:** Confirmado / Confirmado con precisión / Parcial / No confirmado / Incorrecto.  
**Prioridad original:** la que figura en el plan.  
**Prioridad review:** prioridad sugerida después de contrastar impacto y dependencias.

| ID | Estado | Prioridad original | Prioridad review | Evidencia principal | Corrección / observación |
|---|---|---|---|---|---|
| **C-01** | Confirmado | Alta | **P1** | `resources/js/Pages/Configuracion/Index.vue:99-121`; `app/Http/Controllers/ConfiguracionController.php:200-211`; `routes/web.php:376-377` | `editingPmcId` se setea pero `guardarPmc()` no lo envía. El backend usa `updateOrCreate` por `comercio_id + metodo_pago + provider`. Si hay varias filas con `provider = NULL`, puede actualizar una fila arbitraria. Cambiar `provider` crea otra fila y deja la original. La solución no es solo “mandar el ID”: hace falta ruta por ID, autorización por comercio y definición de identidad de la PMC. |
| **C-02** | Confirmado | Alta | **P0** | `app/Http/Controllers/PosController.php:60-84`; `resources/js/Pages/Pos/Terminal.vue:114-137`; `app/Http/Controllers/VentaController.php:229-241,404-418` | Una PMC manual habilitada para un método base hace que el backend cree la venta `PENDING`, pero el POS sigue mostrando el botón base sin aviso. El dedup “método base gana” es solo visual; el backend sí tiene en cuenta la PMC. Riesgo real de caja: venta pendiente, stock descontado y cajero que cree que cobró. |
| **C-03** | Confirmado con precisión | Alta | **P0 para aviso / P1 para obligatoriedad** | `app/Http/Controllers/ConfiguracionController.php:183-198`; `resources/js/Pages/Configuracion/Index.vue:497-509`; `app/Http/Controllers/VentaController.php:144-168` | Es cierto que `display_data` y sus hijos son nullable y que se puede guardar una transferencia vacía. Pero la frase “el backend solo exige `metodo_pago`” es imprecisa: el backend **sí valida el enum** y los formatos. Lo que falta es **completitud condicional** para Transferencia. El modal recibe `display_data` con claves nulas y muestra filas vacías. |
| **C-04** | **Parcial** | Alta | **P1** | `app/Http/Controllers/VentaController.php:746-776`; `app/Models/PaymentMethodConfiguration.php:36-62`; `resources/js/Pages/Pos/Terminal.vue:114-137`; `resources/js/Components/ConfirmarPagoModal.vue:17-35,149-165` | **Núcleo confirmado:** varias PMCs del mismo método permiten que POS, `loadAllConfigs`, `labelMap` y `resolveDisplayLabel` elijan filas distintas. **Parte incorrecta:** el modal no agrupa configuraciones PMC; agrupa entradas de pago. El POS normal impide repetir método (`togglePago`, `Terminal.vue:521-524`). La selección múltiple solo se alcanza con un POST directo con pagos repetidos, y cada tarjeta confirma la venta completa. `orderBy` no resuelve esto. |
| **C-05** | Confirmado | Alta | **P0** | `app/Http/Controllers/VentaController.php:108-124,144-168`; `app/Enums/MetodoPago.php:28-52`; `app/Http/Controllers/ConsumidorController.php:270-276` | `pagos.*.metodo_pago` y `metodo_pago` singular solo validan `string`; `fromString()` cae en `EFECTIVO` para desconocidos. También aplica a `ConsumidorController@cobrarDeuda`. **No se puede validar con `in:` directo contra el enum** sin normalizar: los tests envían `Efectivo` y `Cuenta Corriente`, y el mapa acepta aliases como `TARJETA`, `FIADO`, `TRANSFER`, `MERCADO PAGO`. |
| **C-06** | Confirmado | Media | **P2** | `app/Http/Controllers/VentaController.php:598-636`; `app/Services/VentaOperacionService.php:128-136` | `confirmarPago` mueve caja o cuenta corriente y completa la venta aunque la PMC original ya no exista. El `Payment` queda pendiente. También se rompe si cambia el `provider` de la PMC, no solo si se borra o deshabilita, porque `approve()` busca por el provider actual. Falta definir si se bloquea o se usa un snapshot. |
| **C-07** | Confirmado | Media | **P2** | `app/Models/Venta.php:50-72`; `app/Models/MovimientoCaja.php:25-29`; `app/Http/Controllers/CajaDiariaController.php:354-362,419`; `app/Services/Ticket/TicketBuilder.php:42`; `app/Http/Controllers/VentasCanceladasController.php:58` | Las etiquetas se resuelven con la configuración actual. Impacta ventas, caja, tickets, ventas canceladas, movimientos POS y cobros de consumidor. No hay snapshot. Además, `labelMap` y `resolveDisplayLabel` no filtran por canal: una PMC gateway puede alterar la etiqueta de un pago manual. |
| **C-08** | Confirmado | Media | **P1** | `resources/js/Pages/Configuracion/Index.vue:113-121,604-658`; `app/Http/Controllers/ConfiguracionController.php:183-198` | `pmcForm.errors` no se renderiza. El `onError` genérico solo cubre campos del formulario principal. Un CVU con letras o un método inválido no muestran feedback. Es un fix barato y de bajo riesgo; no necesita esperar al resto. |
| **C-09** | Confirmado con matiz | Media | **P1** | `app/Http/Controllers/ConfiguracionController.php:200-211`; `resources/js/Pages/Configuracion/Index.vue:617-618`; `app/Http/Controllers/ConfiguracionController.php:43-53` | “Pasarela (opcional)” es texto libre y el backend siempre fuerza `channel = MANUAL`. La conversión de una fila gateway solo ocurre si coincide método + provider; si no coincide, crea una PMC manual paralela. El plan dice “la convertiría” de forma demasiado general. Además, una fila manual y una gateway del mismo método hacen que `esFlujoManual && esFlujoGateway` rechace la venta. |
| **C-10** | Confirmado, severidad discutible | Baja | **P4 / decisión** | `app/Services/OnboardingService.php:208-217` | Onboarding marca “Ajustes Globales” completo con cualquier PMC habilitada o con `transferencia_cbu`, sin validar completitud. Es comportamiento confirmado, pero el paso es **opcional**; si la regla de producto es “opcional”, el comportamiento actual puede ser aceptable. No lo trataría como defecto P0/P1 sin decisión. |
| **C-11** | Confirmado | Baja | **P4 como deuda, tests en cada fase** | `rg` sin referencias a `PaymentMethodConfiguration` ni `payment_method_configurations` en `tests/`; baseline en `Modulo2_VentasTest`, `StockReservadoTest`, `F4_CorreccionesBaseTest`, `Modulo3_CajaTest`, `BuildB3_FacturacionTest` | No hay tests de PMC ni del flujo manual POS. Sí hay tests de ventas, caja, stock y `Payment` que deben seguir pasando. La cobertura no es “todo el módulo de pagos”: es específicamente PMC/POS manual/confirmación. |
| **C-12** | **Parcial / mal caracterizado** | Media | **P3** | `app/Http/Controllers/VentaController.php:770-771`; `app/Models/PaymentMethodConfiguration.php:11-24`; `app/Services/Payment/PaymentService.php:102-106`; `app/Services/Payment/Gateways/MercadopagoGateway.php:230-236` | `provider_config` no existe en el modelo ni en la migración, pero `$cfg->provider_config['user_id'] ?? null` **no emite warning**; el operador `??` suprime el acceso a offset de null. El impacto real es que no hay `user_id`/`store_id` por PMC y QR cae en la configuración global. Además, el flujo gateway del POS falla antes porque `VentaController` nunca llama `PaymentService->forCommerce()`. |
| **C-13** | Confirmado en código, latente | Media | **P3** | `app/Http/Controllers/VentaController.php:491-558,598-636`; `resources/js/Components/ConfirmarPagoModal.vue:37-63`; `resources/js/Components/MpQrPanel.vue` sin uso | `confirmarPago` puede completar una venta gateway pendiente sin aprobación real del gateway. Hoy el flujo gateway del POS falla antes por falta de `forCommerce`, así que el caso es latente. Si se corrige el gateway, se vuelve alcanzable. Además, el modal no renderiza `presentation`/QR: `MpQrPanel.vue` existe pero no se usa. |
| **C-14** | **Parcial** | Baja | **P4 / decisión** | `app/Http/Controllers/PedidoWebController.php:30,296-318`; `resources/js/Pages/Welcome.vue:591-593`; `resources/js/Pages/Cliente/Panel.vue:348-354`; `resources/js/Pages/Configuracion/Index.vue:497-509`; `app/Services/OnboardingService.php:208` | Existen dos fuentes (`comercios.transferencia_*` y `display_data`), pero el checkout público **no consume** `comercios.transferencia_*`: solo ofrece efectivo y Mercado Pago. `PedidoWebController` acepta `transferencia` en la validación, pero no usa datos bancarios; el panel del cliente solo permite subir un comprobante de un pedido ya creado. No es un conflicto runtime confirmado; es duplicación/UX y una decisión de producto. |
| **C-15** | Confirmado | Baja | **P1** | `database/migrations/2026_06_14_000005_create_payment_method_configurations_table.php:23`; `app/Http/Controllers/ConfiguracionController.php:50`; `app/Http/Controllers/PosController.php:65`; `app/Http/Controllers/CajaDiariaController.php:30` | El unique con `provider` nullable permite múltiples filas del mismo método con `provider = NULL` en PostgreSQL. No hay check constraints para `metodo_pago` ni `channel`. Valores inválidos en DB pueden hacer fallar `MetodoPago::from()` o `$pmc->channel->value` en Configuración, POS y Caja. Es causa raíz de C-01/C-04, por eso no puede quedar en “Baja”. |
| **C-16** | Confirmado y ampliado | Baja | **P0 dentro de C-05** | `app/Enums/MetodoPago.php:28-52` | `fromString('VIÜMI')` normaliza a `VIMI` y cae en `EFECTIVO`; `DÉBITO` normaliza a `DBITO` y también cae en `EFECTIVO`. Las entradas acentuadas del mapa son inalcanzables porque el regex elimina los acentos antes del lookup. La solución debe normalizar acentos (por ejemplo `Str::ascii`) antes del mapa o eliminar el fallback silencioso. No es un P0 separado: se incluye en el fix de C-05. |

---

## 3. Correcciones de prioridad

### 3.1 P0 revisado

El P0 original mezcla contención de dinero, UX y una solución que no resuelve el problema. Propuesta:

| Orden | Hallazgo | Motivo |
|---|---|---|
| 1 | **C-05 + C-16** | Un método desconocido se convierte en efectivo y contamina venta, caja y reportes. Es el contrato más peligroso. Debe resolverse con normalización previa, no con `in:` directo. |
| 2 | **C-02** | Una PMC manual para un método base crea una venta pendiente sin aviso al cajero. Riesgo directo de caja. |
| 3 | **C-03, solo aviso** | Si no se exige completitud, al menos advertir en POS/modal que la transferencia no tiene datos. La obligatoriedad queda para P1 con decisión de producto. |

**C-08 es un P1 quick win**, no un P0: no mueve dinero, pero es barato y evita que el usuario no pueda corregir datos inválidos. Se puede hacer en la misma fase que C-05/C-02 sin bloquear el resto.

**C-01, C-04 y C-15 no deberían ser P0 separados**, pero sí formar un bloque P1 de identidad/unicidad. Si en producción ya existen duplicados con `provider = NULL`, ese bloque necesita una **contención P0** antes de seguir: auditar duplicados, evitar nuevas altas duplicadas y definir qué fila gana.

### 3.2 Cambios de prioridad concretos

- **C-15: Baja → P1.** Es la causa raíz de C-01/C-04. Mientras el unique permita múltiples `NULL`, cualquier `updateOrCreate` puede actualizar una fila arbitraria y cualquier selector puede elegir otra.
- **C-16: Baja → incluido en C-05 (P0).** No se prioriza aparte porque el arreglo de normalización de C-05 debe cubrir acentos y aliases. Si se deja C-16 para después, el fix de C-05 queda incompleto.
- **C-04: Alta → P1, no P0.** El núcleo es real, pero el modal no es el problema que el plan describe. La solución es identidad/selección, no `orderBy`.
- **C-12 y C-13: Media → P3.** Son deuda latente del gateway. Hoy el POS no puede iniciar un pago gateway por falta de `forCommerce`; no compiten con C-02/C-05.
- **C-14: Baja → P4 / decisión.** No hay conflicto runtime confirmado. El checkout público no usa `comercios.transferencia_*`.
- **C-10: Baja → P4 / decisión.** El paso es opcional; el comportamiento puede ser correcto según producto.
- **C-09: Media → P1.** La semántica de `provider` define si hay que migrar datos, si se valida contra `payment_gateways` y si el campo se renombra. Debe decidirse antes de tocar identidad.
- **C-08: Media → P1.** No es dinero, pero es un fix barato que evita datos inválidos y reclamos.
- **C-06 y C-07: Media → P2.** Integridad y trazabilidad; dependen de la decisión de snapshot.
- **C-11: Baja → P4 como deuda**, pero los tests deben escribirse **en cada fase**, no al final.

### 3.3 P0 que debe eliminarse

- **`orderBy` como solución P0 a C-04.** No alinea POS y backend. Como mucho es defensa en profundidad después de una regla única de selección.
- **Validación `in:` directa contra `MetodoPago::values()`.** Rompe aliases y tests existentes. Debe ir precedida de normalización o de un `tryFromString()` que acepte aliases documentados.
- **“Documentar en `AGENTS.md`” como primer paso.** `AGENTS.md` es gobernanza; para documentación funcional conviene `docs/informes/` o documentación del código, salvo pedido explícito.

---

## 4. Propuestas del plan a revisar o descartar

### 4.1 Descartar o reformular

| Propuesta original | Decisión review | Motivo |
|---|---|---|
| “Agregar `orderBy` determinístico” como P0 | **Descartar como solución principal** | POS toma la primera fila y `loadAllConfigs()` la última; `labelMap` y `resolveDisplayLabel` también eligen distinto. Ordenar no unifica el criterio. |
| “Validar `metodo_pago` contra `MetodoPago::values()`” | **Reformular** | Debe normalizar aliases/acentos primero. Los tests envían `Efectivo` y `Cuenta Corriente`; el mapa acepta `TARJETA`, `FIADO`, `TRANSFER`, `MERCADO PAGO`. |
| “Corregir `provider_config`” | **Reformular** | Antes hay que decidir si `provider_config` existe como concepto. Hoy no está en modelo/migración y el gateway falla antes por `forCommerce`. No agregar una columna solo para satisfacer un `?? null`. |
| “Unificar los datos de Transferencia de la tienda y del POS” | **Reformular** | La tienda no consume `comercios.transferencia_*`. Primero decidir si la transferencia en tienda es una funcionalidad real; si no, no unificar por inercia. |
| “Prohibir cambiar `metodo_pago` y `provider` al editar” | **Revisar** | Es un parche. Si hay edición por ID, cambiar `provider` puede ser legítimo. Prohibirlo sin necesidad complica la UX. Si `provider` es identidad, documentarlo y validar; si es etiqueta, permitir el cambio. |
| “Enviar `config_id` al confirmar” | **Reformular** | El modal no elige configuraciones PMC. Si se permiten varias PMCs por método, la elección debe ocurrir en el POS y persistirse en `Venta.pagos` al crear la venta. Confirmar después no debería “adivinar”. |
| “Bloquear la confirmación si la PMC no existe” | **Revisar** | Si el cajero ya recibió el dinero, bloquear puede dejar stock/caja inconsistentes. Mejor usar snapshot; bloquear solo si no hay snapshot y con un procedimiento de resolución. |

### 4.2 Mantener, con ajustes

- **Edición por ID con ruta `PUT/PATCH`:** correcto, pero debe validar `comercio_id`, usar policy/authorize y decidir si `provider` es identidad o metadato.
- **Unicidad por comercio + método:** correcto **solo si** el producto decide una PMC por método. Si se permiten varias, la unicidad debe ser por identidad explícita (por ejemplo `comercio_id + metodo_pago + label`), no por `provider` nullable.
- **Advertir o bloquear métodos base manuales:** mantener como decisión de producto. Mi recomendación es **advertir en POS** y permitirlo solo con confirmación explícita; bloquear en backend sin migración puede romper filas existentes.
- **Exigir datos de Transferencia:** mantener, pero definir el mínimo. “Al menos un dato” es ambiguo: un alias solo puede ser suficiente para transferir, pero un CBU sin titular no. Definir campos obligatorios por tipo.
- **Snapshot de etiquetas:** mantener, pero el snapshot en la venta no alcanza para caja/tickets. Hay que definir `MovimientoCaja` y `Payment` como fuentes o agregar columnas de snapshot.
- **Tests de PMC y cross-tenant:** mantener y ampliar según la sección 7.

---

## 5. Decisiones de negocio pendientes

Sin estas decisiones, el BUILD va a implementar una interpretación arbitraria. No son “detalles técnicos”.

| # | Decisión | Opciones | Recomendación REVIEW | Impacto si no se decide |
|---|---|---|---|---|
| D-01 | ¿Se permite más de una PMC por método? | (a) Una sola; (b) varias con etiqueta/prioridad; (c) varias solo para gateway | Definir primero. Si es (a), unique por `comercio_id + metodo_pago` y `provider` pasa a metadato. Si es (b), agregar identidad explícita y selección persistida. | C-01/C-04/C-15 quedan sin solución coherente; el unique actual permite duplicados `NULL`. |
| D-02 | ¿Los métodos base pueden ser manuales? | (a) No; (b) Sí con aviso; (c) Sí sin aviso | (b) con aviso y confirmación explícita. | C-02 sigue creando ventas pendientes silenciosas. |
| D-03 | ¿Transferencia exige datos? | (a) Opcional; (b) Al menos alias o CVU/CBU; (c) Alias/CVU/CBU + titular | (c) si se usa para cobrar; (b) como mínimo. | C-03 sigue permitiendo transferencias sin instrucciones. |
| D-04 | ¿`provider` es pasarela o etiqueta? | (a) Identificador de gateway; (b) Etiqueta libre; (c) Ambos | (a) si se valida contra `payment_gateways`; (b) si se renombra a `label` y no se usa como provider de `Payment`. | C-09 sigue mezclando etiqueta y gateway; los pagos manuales quedan con `provider = "mercadopago"` sin serlo. |
| D-05 | ¿La tienda online usa transferencia? | (a) Sí; (b) No por ahora; (c) Solo efectivo/MP | (b) o (c) hasta implementar datos bancarios. | C-14 queda como duplicación sin consumo en el checkout; no hay que “unificar” a ciegas. |
| D-06 | ¿“Ajustes Globales” del onboarding es obligatorio? | (a) Opcional; (b) Obligatorio con completitud | (a) si se mantiene el texto actual; (b) si se exige configuración real. | C-10 sigue marcando completo con una PMC vacía. |
| D-07 | ¿Dónde se guarda el snapshot histórico? | (a) `Venta.pagos` JSON; (b) `Payment.metadata`; (c) columnas en `MovimientoCaja`; (d) combinación | (a) + (c) para venta/caja; `Payment` para gateway. | C-07 no se resuelve solo con la venta; caja y tickets siguen dinámicos. |
| D-08 | ¿El POS usa gateway o solo manual? | (a) Solo manual por ahora; (b) Gateway completo con webhook; (c) Gateway oculto/experimental | (a) con guardas explícitas, o (b) con `forCommerce`, QR y webhook. | C-12/C-13 quedan latentes y el flujo gateway puede crear ventas pendientes huérfanas. |
| D-09 | ¿Se permite mezclar PMC manual y gateway del mismo método? | (a) No; (b) Sí con canal explícito | (a) por ahora. | `esFlujoManual && esFlujoGateway` rechaza la venta; el unique actual lo permite si cambia `provider`. |
| D-10 | ¿Se puede confirmar manualmente una venta gateway? | (a) No; (b) Solo con aprobación del gateway; (c) Solo un supervisor | (b) o (c). | C-13 puede completar una venta sin aprobación real. |

---

## 6. Riesgos de regresión

1. **Validación estricta de métodos.** `Modulo2_VentasTest` envía `Cuenta Corriente` y `StockReservadoTest` envía `Efectivo`; `StockInvariantTest` usa `efectivo`. Un `in:` directo contra el enum rompe esos tests y clientes legacy. Hay que normalizar antes de validar.
2. **Cambio de comportamiento de métodos base.** Si se bloquean PMCs manuales para EFECTIVO/DEBITO/CREDITO/CUENTA_CORRIENTE, las ventas existentes con esas PMCs pueden quedar sin poder confirmarse. Hay que migrar/decidir qué pasa con las filas existentes.
3. **Migración de unicidad.** Si se agrega un unique por `comercio_id + metodo_pago`, la migración falla si ya hay duplicados. Hay que auditar, definir ganador y backfill antes de crear el índice. No borrar filas sin trazabilidad.
4. **Snapshot de etiquetas.** Cambiar `Venta`, `MovimientoCaja`, `TicketBuilder`, `VentasCanceladasController` y `ConsumidorController` puede alterar tickets reimpresos, reportes de caja y ventas históricas. Definir si el snapshot aplica solo a nuevos registros o si hay backfill.
5. **Forma de `Venta.pagos`.** Agregar `config_id`, `provider`, `label` o `display_data` cambia el JSON que consumen `VentaOperacionService`, tickets, caja y frontend. Las ventas viejas no tendrán esas claves; la confirmación/anulación necesita fallback.
6. **`labelMap` y `resolveDisplayLabel` son usados en muchos lugares.** No es solo POS: ventas, caja diaria, tickets, ventas canceladas, movimientos de caja y cobros de consumidor. Un cambio de selección puede mover etiquetas en todos esos lugares.
7. **`MetodoPago::from()` y `channel->value` pueden lanzar excepción.** Si se agregan check constraints o datos inválidos, Configuración/POS/Caja pueden devolver 500. Hay que decidir si se valida en DB, se defiende en código o ambas.
8. **Multi-tenant en confirmación/anulación.** `confirmarPago`, `cancelar` y `devolver` solo refuerzan el scope si `auth()->user()->branch?->comercio_id` no es null. Cualquier ruta nueva de edición/borrado de PMC debe usar `comercio_id` y policy, no solo el ID. Los tests cross-tenant son obligatorios.
9. **Gateway.** Habilitar `forCommerce`, `provider_config` o `presentation` sin webhook/idempotencia puede crear ventas pendientes, pagos duplicados o confirmaciones sin aprobación. No mezclar este cambio con el fix manual.
10. **Onboarding.** Si se cambia la regla de “Ajustes Globales”, los usuarios existentes pueden ver el paso incompleto y perder el flujo de alta. Definir transición.
11. **Frontend.** `Configuracion/Index.vue` y `Terminal.vue` ya tienen cambios previos en el working tree. Cualquier BUILD debe partir de ese estado, no de una versión limpia, y correr `npm run build` además de PHPUnit.
12. **Tests de regresión.** El baseline incluye `Modulo2_VentasTest`, `StockReservadoTest`, `F4_CorreccionesBaseTest`, `Modulo3_CajaTest`, `BuildB3_FacturacionTest`, `StockConsistencyTest`, `StockInvariantTest` y tests de `Payment`. Hay que ejecutarlos antes y después de cada fase.

---

## 7. Cobertura de pruebas faltante

No hay tests que referencien `PaymentMethodConfiguration` ni `payment_method_configurations`. La cobertura propuesta debe cubrir, como mínimo:

### 7.1 Unitarias

| ID | Caso | Resultado esperado |
|---|---|---|
| U-01 | `MetodoPago::fromString('EFECTIVO')`, `'Efectivo'`, `'efectivo'` | `EFECTIVO` |
| U-02 | `fromString('CUENTA CORRIENTE')`, `'Cuenta Corriente'`, `'FIADO'` | `CUENTA_CORRIENTE` |
| U-03 | `fromString('DÉBITO')`, `'Débito'`, `'DEBITO'`, `'TARJETA'` | `DEBITO` |
| U-04 | `fromString('CRÉDITO')`, `'Crédito'`, `'CREDITO'` | `CREDITO` |
| U-05 | `fromString('TRANSFER')`, `'TRANSFERENCIA'` | `TRANSFERENCIA` |
| U-06 | `fromString('MERCADO PAGO')`, `'MERCADO_PAGO'` | `MERCADO_PAGO` |
| U-07 | `fromString('VIUMI')`, `'VIÜMI'`, `'viüMi'` | `VIUMI` |
| U-08 | `fromString('FOO')` | Debe fallar explícitamente o devolver null; **no** `EFECTIVO` |
| U-09 | `resolveDisplayLabel` con PMC manual, gateway y ninguna | Etiqueta determinística y filtrada por canal |
| U-10 | `labelMap` con múltiples filas del mismo método | Misma fila que `resolveDisplayLabel` y que POS, o error de configuración |

### 7.2 Feature — PMC

| ID | Caso | Resultado esperado |
|---|---|---|
| F-01 | Crear PMC manual válida | 201/redirect, fila con `comercio_id`, `channel = MANUAL` |
| F-02 | Editar PMC por ID cambiando `display_data` | Actualiza **esa** fila, no otra |
| F-03 | Editar PMC cambiando `provider` | Actualiza la misma fila; no crea duplicado |
| F-04 | Editar PMC cambiando `metodo_pago` | Según decisión: rechazo claro o actualización controlada |
| F-05 | Crear dos PMCs del mismo método con `provider = NULL` | Rechazo por unicidad o selección explícita; nunca dos filas silenciosas |
| F-06 | Transferencia sin datos | Según decisión: 422 o advertencia persistida |
| F-07 | Transferencia con CVU inválido | 422 y error visible en `pmcForm.errors` |
| F-08 | `display_data` con claves nulas | No renderiza filas vacías en el modal; muestra “sin datos” |
| F-09 | Eliminar PMC | No rompe ventas históricas ni confirmaciones pendientes |
| F-10 | Cross-tenant: editar/borrar PMC de otro comercio | 403/404; no se modifica nada |
| F-11 | Cross-tenant: crear PMC con `comercio_id` ajeno | 403/422; no se crea |

### 7.3 Feature — POS y venta manual

| ID | Caso | Resultado esperado |
|---|---|---|
| P-01 | PMC manual para `EFECTIVO` habilitada | POS muestra aviso/bloqueo; no hay venta pendiente silenciosa |
| P-02 | PMC manual para `DEBITO`/`CREDITO`/`CUENTA_CORRIENTE` | Mismo comportamiento que P-01 |
| P-03 | POS con PMC manual de Transferencia | Botón/dropdown muestra etiqueta y datos correctos |
| P-04 | POS con dos PMCs del mismo método | Una sola opción o selección explícita; POS y backend eligen la misma |
| P-05 | Venta manual | `Venta` queda `PENDING`, stock descontado, caja/CC sin mover, `Payment` pendiente |
| P-06 | Confirmar venta manual | Caja/CC se mueve una sola vez; `Payment` aprobado; venta `COMPLETED` |
| P-07 | Confirmar dos veces | No duplica caja/CC ni pago |
| P-08 | PMC borrada antes de confirmar | Según decisión: snapshot o error claro; nunca caja movida con `Payment` huérfano |
| P-09 | PMC deshabilitada antes de confirmar | Igual que P-08 |
| P-10 | PMC con `provider` cambiado antes de confirmar | Igual que P-08; no dejar `Payment` huérfano |
| P-11 | Anular venta manual pendiente | Restaura stock y cancela `Payment` pendiente |
| P-12 | Devolver venta manual completada | Restaura stock, caja/CC y `Payment` según corresponda |
| P-13 | Venta con método desconocido | 422; no se guarda como `EFECTIVO` |
| P-14 | Venta con `pagos` repetidos por POST directo | Rechazo por `distinct` o manejo explícito; el modal no debe simular selección de PMC |

### 7.4 Feature — Histórico y etiquetas

| ID | Caso | Resultado esperado |
|---|---|---|
| H-01 | Cambiar `provider` de una PMC | Ventas, tickets y caja históricos conservan la etiqueta original |
| H-02 | Deshabilitar/eliminar PMC | Etiquetas históricas no cambian |
| H-03 | Reimprimir ticket viejo | Misma etiqueta que al momento de la venta |
| H-04 | Caja diaria con movimientos viejos | Etiquetas y totales por método no cambian |
| H-05 | Ventas canceladas | Etiqueta histórica, no la actual |
| H-06 | Cobro de deuda de consumidor | Etiqueta y método históricos correctos |

### 7.5 Feature — Gateway (si se mantiene en alcance)

| ID | Caso | Resultado esperado |
|---|---|---|
| G-01 | PMC gateway sin `forCommerce` | Error controlado; no venta pendiente huérfana |
| G-02 | PMC gateway con `forCommerce` y `provider_config` | `user_id`/`store_id` correctos; QR/presentation disponible |
| G-03 | `confirmarPago` sobre venta gateway | No completa sin aprobación del gateway |
| G-04 | Webhook aprobado | `Payment` aprobado; venta completada una sola vez |
| G-05 | Webhook duplicado | Idempotencia; no duplica caja |
| G-06 | PMC manual + gateway del mismo método | Rechazo claro o canal explícito; no `esFlujoManual && esFlujoGateway` |
| G-07 | Modal gateway | Renderiza `presentation`/QR; no solo datos de transferencia |

### 7.6 Migración y regresión

| ID | Caso | Resultado esperado |
|---|---|---|
| M-01 | Migración con duplicados `provider = NULL` | Falla controlada o backfill documentado; no pérdida de datos |
| M-02 | Check constraints de `metodo_pago`/`channel` | Datos inválidos rechazados; Configuración/POS no devuelven 500 |
| R-01 | `Modulo2_VentasTest` | Sigue pasando con aliases |
| R-02 | `StockReservadoTest` | Sigue pasando con `Efectivo` |
| R-03 | `F4_CorreccionesBaseTest` | Recargos y pagos siguen pasando |
| R-04 | `Modulo3_CajaTest` | Arqueo y movimientos siguen pasando |
| R-05 | `BuildB3_FacturacionTest` | `Payment` y anulación/devolución siguen pasando |
| R-06 | `StockConsistencyTest` / `StockInvariantTest` | Stock consistente en ventas manuales y pendientes |
| R-07 | `npm run build` | Sin errores de compilación Vue/Inertia |

---

## 8. Orden recomendado de implementación

### Fase 0 — Decisiones y corrección del plan (sin código)

1. Cerrar D-01 a D-10 de la sección 5.
2. Corregir en el plan C-04, C-12 y C-14.
3. Reemplazar `orderBy` por la estrategia de identidad/selección.
4. Definir el alcance: ¿gateway entra o queda fuera?
5. Definir el mínimo de datos de Transferencia.

**Salida:** plan actualizado y aprobado. Sin esta fase, el BUILD implementa supuestos.

### Fase 1 — Contrato y contención

1. **C-05 + C-16:** normalización de métodos, aliases y acentos; `tryFromString()` o equivalente; rechazo explícito de desconocidos.
2. **C-08:** render de `pmcForm.errors` y errores por campo.
3. **C-02:** aviso/bloqueo de PMC manual para métodos base.
4. **C-03:** advertencia de Transferencia sin datos (la obligatoriedad se decide en Fase 0).

**Tests:** U-01 a U-08, F-06 a F-08, P-01 a P-03, P-13, R-01/R-02.

### Fase 2 — Identidad y unicidad

1. **C-01:** ruta `PUT/PATCH` por ID, autorización por comercio, edición real.
2. **C-15:** auditar duplicados, definir unicidad, migración con backfill.
3. **C-04:** una sola regla de selección o `config_id` persistido en `Venta.pagos`.
4. **C-09:** semántica de `provider`; renombrar o validar contra gateway.

**Tests:** F-01 a F-05, F-10/F-11, P-04, M-01.

### Fase 3 — Trazabilidad y confirmación robusta

1. **C-07:** snapshot de etiqueta/datos en venta y caja; tickets y ventas canceladas.
2. **C-06:** confirmación/anulación con snapshot o error claro; no dejar `Payment` huérfano.
3. Definir backfill de históricos.

**Tests:** P-05 a P-12, H-01 a H-06, R-03/R-04/R-05.

### Fase 4 — Gateway o guarda explícita

1. **C-12:** `forCommerce`, configuración real por PMC, `provider_config` solo si se decide.
2. **C-13:** no confirmar ventas gateway sin aprobación; renderizar `presentation`; webhook idempotente.
3. Si gateway no entra en alcance: ocultar/guardar el flujo y evitar ventas pendientes huérfanas.

**Tests:** G-01 a G-07.

### Fase 5 — Producto y documentación

1. **C-10:** onboarding según decisión.
2. **C-14:** transferencia en tienda según decisión.
3. Documentar semántica de `provider`, `display_data`, `channel` y snapshot.
4. Limpiar textos de UI (“Pasarela” vs “Etiqueta”).

### Fase 6 — Regresión final

1. Suite PHPUnit completa.
2. `npm run build`.
3. Revisión de `git diff` para confirmar que no se tocaron archivos ajenos.
4. Informe técnico de la fase.

---

## 9. Observaciones adicionales y errores del informe original

1. **C-04 — modal:** el informe dice que el modal agrupa configuraciones PMC. En realidad agrupa `displayInfo` por método; `group.configs` son entradas de pago. El POS normal impide repetir método. La selección múltiple solo se alcanza con un POST directo con pagos repetidos, y cada tarjeta confirma la venta completa. Es un error de diagnóstico, no un detalle menor.
2. **C-04 — `orderBy`:** el informe propone orden determinístico, pero POS y backend siguen tomando extremos distintos. `labelMap` y `resolveDisplayLabel` tampoco usan el mismo criterio. La solución debe ser una función única de selección o unicidad.
3. **C-12 — warning:** `$cfg->provider_config['user_id'] ?? null` no emite warning; el `??` suprime el acceso a offset de null. El impacto real es ausencia de configuración por PMC y el fallo previo por `forCommerce`.
4. **C-12 — gateway:** `VentaController` nunca llama `PaymentService->forCommerce()`. `initiatePosPayment()` termina en “No hay comercio seleccionado” (`PaymentService.php:102-106`). Cualquier análisis de `provider_config` debe reconocer este bloqueo anterior.
5. **C-13 — alcanzabilidad:** el código permite confirmar una venta gateway sin aprobación, pero hoy el flujo gateway del POS falla antes. Es latente, no un bug runtime activo. Si se corrige el gateway, se vuelve alcanzable.
6. **C-13 — QR:** `ConfirmarPagoModal` no renderiza `presentation`; `MpQrPanel.vue` existe pero no se usa. El informe no lo menciona.
7. **C-14 — tienda:** el informe dice “la tienda usa el primero”. Es falso: el checkout público (`Welcome.vue`) solo ofrece efectivo y Mercado Pago; `PedidoWebController` acepta `transferencia` pero no usa datos bancarios; el panel del cliente solo permite subir un comprobante de un pedido ya creado. No hay conflicto runtime confirmado.
8. **C-03 — redacción:** “el backend solo exige `metodo_pago`” es impreciso. El backend valida enum y formatos; lo que falta es completitud condicional. La conclusión es correcta, la justificación no.
9. **C-09 — conversión:** el informe dice que una fila gateway “se convertiría” en manual. Solo ocurre si coincide método + provider; si no, se crea una PMC manual paralela. Además, manual + gateway del mismo método hace que `esFlujoManual && esFlujoGateway` rechace la venta.
10. **C-15 — impacto:** el informe no menciona que valores inválidos en DB pueden hacer fallar `MetodoPago::from()` y `$pmc->channel->value` en Configuración, POS y Caja. Es un riesgo adicional a la duplicación.
11. **C-16 — alcance:** el informe solo menciona `VIÜMI`. También fallan `DÉBITO`/`CRÉDITO` y cualquier variante acentuada; las entradas acentuadas del mapa son inalcanzables. La solución debe normalizar acentos antes del lookup.
12. **C-05 — segundo punto de entrada:** `ConsumidorController@cobrarDeuda` valida `pagos.*.metodo_pago` solo como string y usa `fromString()`. El plan no lo menciona; comparte el riesgo de conversión silenciosa a efectivo.
13. **C-07 — alcance:** el informe no menciona tickets (`TicketBuilder`), ventas canceladas (`VentasCanceladasController`) ni cobros de consumidor. El impacto histórico es más amplio que ventas/caja.
14. **C-06 — variante:** no solo borrar/deshabilitar; cambiar `provider` también deja el `Payment` huérfano porque `approve()` busca por el provider actual.
15. **C-11 — baseline:** el informe dice “no hay tests de pagos” de forma amplia. Hay tests de `Payment`, ventas, caja y stock; lo que falta es cobertura de PMC y del flujo manual POS. La distinción importa para no romper el baseline.
16. **C-10 — severidad:** el paso de onboarding es opcional. Marcarlo como defecto sin decisión de producto puede llevar a un cambio innecesario.
17. **Multi-tenant:** `confirmarPago`, `cancelar` y `devolver` solo refuerzan el scope si `branch?->comercio_id` no es null. El plan no lo menciona; cualquier ruta nueva de PMC debe usar `comercio_id` + policy y tests cross-tenant.
18. **Working tree:** el repositorio ya tenía cambios previos en `Configuracion/Index.vue`, `Terminal.vue`, `routes/web.php`, tests y otros archivos. El BUILD debe partir de ese estado y no revertirlo.

---

## 10. Veredicto final sobre BUILD

**No está listo para un BUILD completo tal como está el plan.**

Está listo para iniciar un **BUILD acotado por fases** si y solo si se cumplen estas condiciones:

1. **Corregir el plan:** C-04 (modal y `orderBy`), C-12 (warning/impacto) y C-14 (tienda) deben quedar reescritos.
2. **Cerrar decisiones:** D-01 (múltiples PMCs), D-02 (métodos base), D-03 (datos de Transferencia), D-04 (`provider`), D-05 (tienda), D-07 (snapshot) y D-08 (gateway).
3. **Reordenar prioridades:** C-05/C-16 y C-02 primero; C-01/C-04/C-15 como bloque de identidad; C-12/C-13 al final o fuera de alcance.
4. **Agregar tests antes de tocar producción:** normalización de métodos, edición por ID, duplicados, cross-tenant, snapshot histórico, confirmación con PMC eliminada y regresión de ventas/caja/stock.
5. **No implementar `orderBy` como solución única** ni validar `in:` directo sin normalización.

Con esas correcciones, el plan es una buena base para un BUILD incremental y seguro. Sin ellas, el riesgo es implementar una solución que ordena filas pero no resuelve la identidad de la configuración, que confunde pagos con configuraciones en el modal y que declara resuelto un gateway que hoy ni siquiera tiene contexto de comercio.
