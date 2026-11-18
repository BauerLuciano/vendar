# PLAN CONSOLIDADO — Medios de Pago POS

**Fecha:** 2026-11-17  
**Modo:** PLAN exclusivo. Este documento no implementa código, no modifica migraciones ni base de datos, no ejecuta operaciones de negocio y no altera `AGENTS.md` ni skills.  
**Fuentes auditadas:** `docs/informes/PLAN-AUDITORIA-MEDIOS-PAGO-POS.md` y `docs/informes/REVIEW-PLAN-AUDITORIA-MEDIOS-PAGO-POS.md`.  
**Veredicto de la revisión independiente:** aprobado con correcciones mayores. El plan original no estaba listo para un BUILD completo.  
**Estado de este documento:** plan técnico consolidado para futuros BUILD independientes, con decisiones de producto pendientes explícitamente separadas.

---

## 1. Resumen ejecutivo

La auditoría original detectó 16 hallazgos en el circuito de Medios de Pago POS. La revisión independiente confirmó la mayoría, corrigió prioridades, descartó varias propuestas que no resolvían la causa raíz y elevó a P0 dos problemas de normalización de métodos (`C-05` y `C-16`).

El estado actual puede resumirse así:

- El flujo manual de POS funciona en los casos felices, pero depende de que la configuración sea única y completa.
- El POS y el backend no siempre seleccionan la misma configuración para un mismo método.
- La edición de configuraciones puede crear duplicados en lugar de actualizar la fila elegida.
- El formulario de configuración no muestra los errores de validación del formulario de medios de pago.
- Las etiquetas históricas de ventas, caja y tickets se resuelven contra la configuración actual, no contra una foto del momento de la venta.
- El gateway de Mercado Pago en POS es deuda latente: hoy falla antes por falta de contexto de comercio, pero el flujo de confirmación manual podría cerrar una venta gateway sin aprobación si se corrigiera solo esa falla.
- La tienda pública no consume `comercios.transferencia_*`; solo ofrece efectivo y Mercado Pago. Esto es una decisión de producto, no un defecto automático.
- El onboarding puede omitir medios de pago. Si eso es aceptable o no también es una decisión de producto.

La estrategia propuesta es ejecutar fases pequeñas, reversibles y verificables. Cada fase tiene un objetivo único, archivos acotados, pruebas automatizadas y una lista de pruebas manuales. Las fases que dependen de una decisión comercial quedan bloqueadas hasta que el responsable del producto responda.

**Bloques de trabajo:**

1. **Contrato y confiabilidad inmediata:** normalización de métodos, errores de formulario y edición por ID.
2. **Identidad y selección de configuraciones:** unicidad, `provider`, canal y métodos base.
3. **Trazabilidad y cierre:** snapshot histórico, confirmación/anulación, gateway latente, onboarding/tienda y regresión final.

**Bloqueos principales:** `D-01` (más de una configuración por método), `D-04` (identificación y conservación histórica), `D-06` (semántica de `provider`) y `D-05` (comportamiento del POS ante configuración incompleta). Sin respuesta a esas decisiones no se autoriza el BUILD completo.

**Qué puede comenzar antes de las decisiones comerciales:** normalización (`F1`), errores de formulario (`F2`) y los avisos P0 de método base/transferencia (`F6A`, `F7A`), siempre que se confirme que los aliases existentes son compatibilidad y no una nueva regla comercial. El resto requiere decisión firmada.

---

## 2. Estado definitivo de los hallazgos

Ningún hallazgo se elimina. Los que la revisión consideró mal formulados se reformulan en la sección 4 y se implementan desde la causa raíz, no desde la propuesta original.

| ID | Estado definitivo | Prioridad | Fase | Evidencia principal |
|---|---|---|---|---|
| C-01 | Confirmado. La edición puede duplicar en lugar de actualizar y no hay edición por ID. | P1 | F3 | `ConfiguracionController.php`, `Configuracion/Index.vue` |
| C-02 | Confirmado. Un método base con configuración manual puede quedar pendiente sin aviso claro. | P0/P1 | F6A (aviso P0), F6B (política P1) | `PosController.php`, `Terminal.vue`, `VentaController.php` |
| C-03 | Confirmado con precisión. El aviso por transferencia incompleta es P0; la obligatoriedad es P1 y depende de producto. | P0/P1 | F7A (aviso P0), F7B (obligatoriedad P1) | `Configuracion/Index.vue`, `ConfirmarPagoModal.vue` |
| C-04 | Parcial. El modal agrupa pagos, no configuraciones PMC. El POS normal impide repetir método. `orderBy` no alinea POS y backend. | P1 | F4 | `ConfirmarPagoModal.vue`, `Terminal.vue`, `VentaController.php` |
| C-05 | Confirmado. La normalización cae silenciosamente en `EFECTIVO` y la validación no usa el mismo contrato. | P0 | F1 | `MetodoPago.php`, `VentaController.php`, `ConsumidorController.php` |
| C-06 | Confirmado. `confirmarPago` puede mover caja/deuda con un `Payment` huérfano o no aprobado. | P2 | F9 | `VentaController.php`, `VentaOperacionService.php` |
| C-07 | Confirmado. Las etiquetas históricas son dinámicas y dependen de la configuración actual. | P2 | F8 | `Venta.php`, `MovimientoCaja.php`, `TicketBuilder.php` |
| C-08 | Confirmado. Los errores del formulario PMC no se renderizan y la edición no envía identidad. | P1 | F2 | `Configuracion/Index.vue` |
| C-09 | Confirmado con matiz. `provider` se usa como etiqueta y como proveedor; la UI no distingue canal ni credenciales. | P1 | F5 | `PaymentMethodConfiguration.php`, `Configuracion/Index.vue` |
| C-10 | Confirmado, pero es decisión de producto. El onboarding puede omitir medios de pago. | P4 | F11 | `OnboardingService.php` |
| C-11 | Confirmado. Faltan tests de regresión y la matriz cross-tenant para endpoints nuevos. | P4 | F12 y cada fase | `tests/Feature/CrossTenantAttackTest.php` |
| C-12 | Parcial y latente. `provider_config` no existe en modelo/migración y `?? null` no emite warning. El gateway del POS falla antes por falta de `PaymentService->forCommerce()`. | P3 | F10 | `VentaController.php`, `PaymentService.php` |
| C-13 | Confirmado y latente. `confirmarPago` puede completar una venta gateway sin aprobación, pero hoy el flujo falla antes. | P3 | F10 | `VentaController.php`, `MpQrPanel.vue` |
| C-14 | Parcial y decisión de producto. La tienda solo ofrece efectivo y Mercado Pago; no consume datos de transferencia. | P4 | F11 | `Welcome.vue`, `PedidoWebController.php`, `Cliente/Panel.vue` |
| C-15 | Confirmado. El unique con `provider` nullable permite múltiples `NULL` y no hay checks de método/canal. Es causa raíz de C-01 y C-04. | P1 | F4 | Migración de `payment_method_configurations` |
| C-16 | Confirmado. `VIÜMI` y `DÉBITO` caen en `EFECTIVO`; las entradas acentuadas del mapa son inalcanzables. | P0 dentro de C-05 | F1 | `MetodoPago.php` |

**Lectura de prioridades:** `C-05` y `C-16` son P0. `C-02` y `C-03` tienen un aviso P0 y una política/obligatoriedad P1. `C-01`, `C-04`, `C-08`, `C-09` y `C-15` son P1. `C-06` y `C-07` son P2. `C-12` y `C-13` son P3. `C-10`, `C-11` y `C-14` son P4 o decisiones de producto. La política de métodos base (`C-02`) depende de `D-07`; la obligatoriedad de datos de transferencia (`C-03`) depende de `D-03`.

---

## 3. Correcciones incorporadas del reviewer

### 3.1. C-04: el modal agrupa pagos, no configuraciones PMC

`ConfirmarPagoModal.vue` agrupa entradas de pago por método (`displayInfo`) y cada `group.configs` es una entrada de pago, no una lista de configuraciones de `payment_method_configurations`. El POS normal impide repetir un método mediante `togglePago` y `agregarPago` en `Terminal.vue`. Por eso no se puede resolver la selección de configuración agregando un `orderBy` ni cambiando el orden del modal.

La causa real es que el POS toma una fila de configuración y el backend (`loadAllConfigs`) toma otra. La solución debe ser una identidad explícita de configuración y un resolvedor único, no un ordenamiento accidental.

### 3.2. C-12: `provider_config` no emite warning

`provider_config` no existe en el modelo ni en la migración de `payment_method_configurations`. La expresión `?? null` no emite warning: simplemente devuelve `null`. Además, el gateway del POS falla antes por falta de `PaymentService->forCommerce()`. Por eso C-12 y C-13 bajan a P3: son deuda latente del gateway, no la causa de los problemas manuales.

### 3.3. C-14: la tienda no consume datos de transferencia

`Welcome.vue` solo ofrece efectivo y Mercado Pago. `PedidoWebController` acepta el valor `transferencia`, pero no usa `comercios.transferencia_*` para mostrar instrucciones. El panel del cliente solo permite subir comprobante de un pedido ya creado. No se debe “arreglar” asumiendo que la tienda ya usa transferencia: es una decisión de producto (`D-08`).

### 3.4. C-15 sube a P1

El unique `(comercio_id, metodo_pago, provider)` con `provider` nullable permite múltiples filas con `NULL`. No hay check constraints para `metodo_pago` ni `channel`. Esto es causa raíz de C-01 y C-04, por eso se trata como P1 y se resuelve antes de estabilizar la selección.

### 3.5. C-05 y C-16 son P0 y se normaliza antes de validar

`fromString('VIÜMI')` cae en `EFECTIVO`; `fromString('DÉBITO')` también. Las entradas acentuadas del mapa son inalcanzables porque la comparación no normaliza acentos. La validación debe normalizar aliases y acentos antes de validar, y debe rechazar lo desconocido en lugar de convertirlo silenciosamente en efectivo. C-16 no es un hallazgo separado de baja prioridad: es parte del mismo contrato P0 de C-05.

### 3.6. Otras correcciones de la revisión

- `C-03`: el aviso es P0; la obligatoriedad de datos es P1 y depende de `D-03`. No se debe bloquear una venta por un campo vacío sin decisión de producto.
- `C-06`: el riesgo de `Payment` huérfano se agrava si se permite cambiar `provider` de una PMC sin preservar la referencia histórica.
- `C-07`: el snapshot debe cubrir ventas, caja y tickets, no solo la etiqueta del POS.
- `C-09`: `provider` tiene dos usos mezclados: etiqueta visible y proveedor de gateway. No se puede validar contra `payment_gateways` sin decidir `D-06`.
- `C-10`: el onboarding incompleto es opcional o defecto según producto. No se asume que deba bloquear.
- `C-11`: el baseline conocido tiene 23 fallos preexistentes. No se ocultan ni se “arreglan” fuera de alcance.
- `C-05`: la validación también aplica al checkout de consumidor (`ConsumidorController`), no solo a `VentaController`.
- `C-15`: el impacto no es solo estético; afecta la selección de POS, la caja y la etiqueta histórica.
- `C-16`: la normalización debe probar acentos, mayúsculas, espacios y aliases.

### 3.7. Propuestas que no se recuperan

No se reintroducen en las fases: `orderBy` como solución de alineación, `in:` directo sobre `metodo_pago`, “el modal elige la PMC”, “la tienda ya usa transferencia”, “`provider_config` emite warning”, “bloquear confirmación sin snapshot” y “prohibir cambiar `provider`” como regla automática. Cualquier variante de esas ideas queda reemplazada por las decisiones y fases de este documento.

---

## 4. Hallazgos descartados o reformulados

| Propuesta original | Estado | Reemplazo en este plan |
|---|---|---|
| Agregar `orderBy` para que POS y backend elijan la misma configuración. | Descartada. No resuelve la causa raíz y depende del orden accidental. | Identidad explícita de configuración y resolvedor único (`F4`). |
| Validar `metodo_pago` con `in:` directo. | Descartada. No normaliza aliases ni acentos. | `MetodoPago::tryFromString` y normalización previa (`F1`). |
| “El modal debe elegir la PMC”. | Descartada. El modal agrupa pagos, no configuraciones. | Selección en POS/resolvedor y `config_id` en el pago (`F4`). |
| “`provider_config` emite warning”. | Descartada. El campo no existe y `?? null` no advierte. | Deuda del gateway y contexto de comercio (`F10`). |
| “La tienda ya usa transferencia”. | Descartada. Solo ofrece efectivo y Mercado Pago. | Decisión `D-08` y fase `F11`. |
| Convertir todo método desconocido a `EFECTIVO`. | Descartada. Es la causa de C-05/C-16. | Rechazo explícito en validación y display legacy sin fallback silencioso (`F1`). |
| Bloquear toda confirmación si falta la PMC. | Reformulada. Puede romper ventas pendientes legítimas. | Snapshot histórico y política de confirmación (`F8`, `F9`, `D-05`). |
| Prohibir cambiar `provider` de una PMC existente. | Reformulada. Es una regla comercial y de identidad. | Decisión `D-02`/`D-06`; si se permite, preservar referencia histórica. |
| Unique `(comercio_id, metodo_pago)` sin analizar datos. | Reformulada. Depende de si se permiten varias configuraciones. | Decisión `D-01`, auditoría `F0` y migración no destructiva `F4`. |
| Persistir `config_id` solo al confirmar. | Reformulada. La venta puede quedar pendiente y la configuración cambiar. | Persistir identidad y snapshot al crear la venta (`F4`, `F8`). |
| Unificar datos de transferencia de tienda y POS. | Reformulada. Son dominios y flujos distintos. | Decisión `D-08`; no unificar sin estrategia (`F11`). |
| Tratar C-10 como defecto automático. | Reformulada. Puede ser onboarding opcional. | Decisión `D-09` y fase `F11`. |
| Documentar la corrección en `AGENTS.md`. | Reformulada. No se modifica `AGENTS.md` en modo PLAN. | Informe en `docs/informes/` al cerrar cada BUILD (`F12`). |
| C-16 como hallazgo P2 separado. | Reformulada. Es parte del contrato P0 de C-05. | Fase `F1` con pruebas de acentos y aliases. |

---

## 5. Decisiones de producto pendientes

Estas decisiones no se resuelven en este plan. Cada una bloquea una o más fases. Las recomendaciones son técnicas y no reemplazan la decisión del responsable del producto cuando cambian una regla comercial.

### D-01 — ¿Se permite más de una configuración por método?

- **Alternativas:** A) una sola configuración activa por método y comercio; B) varias configuraciones por método con etiqueta, prioridad y una predeterminada; C) varias solo para gateway, una para manual.
- **Comportamiento actual:** el unique `(comercio_id, metodo_pago, provider)` con `provider` nullable permite múltiples `NULL`. El POS toma la primera fila y `loadAllConfigs` toma la última. No hay etiqueta ni prioridad.
- **Consecuencias:** A simplifica el POS y la validación, pero impide dos cuentas del mismo método. B exige identidad visible, selección en POS, `config_id` y snapshot. C exige separar canal y puede confundir al cajero.
- **Recomendación técnica:** A reduce el riesgo y es suficiente para el flujo manual actual. B solo si el negocio necesita varias cuentas o etiquetas por método. No se elige automáticamente.
- **Fases dependientes:** F4, F8, F9; F5 si se separa canal.

### D-02 — ¿Se puede cambiar el método de una PMC existente?

- **Alternativas:** A) inmutable una vez creada; B) editable si no tiene ventas asociadas; C) editable siempre con auditoría y snapshot histórico; D) permitir solo desactivar y crear otra.
- **Comportamiento actual:** la edición no envía identidad de fila y el backend puede terminar creando o actualizando otra configuración. No hay una regla explícita de inmutabilidad del método.
- **Consecuencias:** A evita inconsistencias históricas, pero obliga a crear otra PMC. B es segura, pero requiere verificar uso. C exige auditoría y snapshot. D conserva historia, pero agrega pasos operativos.
- **Recomendación técnica:** A o D para métodos ya usados; B para configuraciones sin uso. La decisión debe incluir qué pasa con ventas pendientes.
- **Fases dependientes:** F3, F4, F8.

### D-03 — ¿Qué información es obligatoria según método y proveedor?

- **Alternativas:** A) mínima por método (por ejemplo, alias/CBU solo para transferencia); B) completa por proveedor (credenciales, CVU, alias, titular, instrucciones); C) solo advertencia, sin bloqueo; D) validación progresiva: warn al guardar, block al usar en POS.
- **Comportamiento actual:** `display_data` es JSON libre. Transferencia puede quedar sin datos y el modal muestra filas vacías. No hay contrato por método ni por proveedor.
- **Consecuencias:** A mejora el caso manual sin frenar la configuración. B reduce errores operativos, pero puede bloquear comercios que no usan todos los campos. C mantiene el riesgo de instrucciones incompletas. D separa configuración de operación, pero requiere dos validaciones.
- **Recomendación técnica:** A + D para transferencia; campos de tarjeta según el flujo actual; credenciales de gateway solo si `D-10` incluye gateway. No definir campos comerciales nuevos sin producto.
- **Fases dependientes:** F7B, F9.

### D-04 — ¿Cómo se identifica y conserva la información histórica?

- **Alternativas:** A) snapshot en el JSON de pago de la venta (`config_id`, método canónico, etiqueta, proveedor, `display_data`); B) tabla normalizada de snapshots; C) ambas, con `Payment.metadata` para gateway; D) no persistir y aceptar que la etiqueta cambie.
- **Comportamiento actual:** `Venta.pagos` guarda método, monto y algunos campos de tarjeta, pero no `config_id` ni etiqueta. `Venta` y `MovimientoCaja` resuelven etiquetas contra la configuración actual. Si se edita o elimina la PMC, cambia el histórico.
- **Consecuencias:** A es aditiva y suficiente para tickets/caja. B facilita consultas, pero agrega complejidad. C cubre gateway y manual, pero duplica datos. D es inaceptable para trazabilidad de dinero.
- **Recomendación técnica:** A como mínimo, con C solo para pagos gateway. Mantener fallback de lectura para ventas antiguas y no borrar datos.
- **Fases dependientes:** F4, F8, F9.

### D-05 — ¿Qué debe hacer el POS ante una configuración incompleta?

- **Alternativas:** A) advertir y permitir confirmar con responsabilidad del cajero; B) bloquear la venta; C) bloquear solo métodos base o gateway; D) usar una configuración predeterminada silenciosa.
- **Comportamiento actual:** el POS muestra la configuración disponible y la venta puede quedar pendiente. No hay un aviso consistente ni una regla backend única. El modal puede mostrar datos vacíos.
- **Consecuencias:** A mantiene operación, pero traslada el riesgo al cajero. B protege la integridad, pero puede frenar ventas reales. C protege los casos críticos sin bloquear todo. D oculta el problema y no es aceptable.
- **Recomendación técnica:** A para métodos manuales no críticos; B o C para base/gateway; nunca D. La decisión debe distinguir “configuración incompleta” de “configuración ausente”.
- **Fases dependientes:** F6B, F7B, F9.

### D-06 — ¿Qué significa `provider` en una PMC?

- **Alternativas:** A) etiqueta visible libre; B) identificador del gateway (`mercadopago`, `viumi`, etc.); C) separar `label` visible de `provider` técnico y `gateway_config_id`; D) `provider` solo aplica a gateway y queda nulo en manual.
- **Comportamiento actual:** `provider` se usa como etiqueta en la UI y como proveedor en pagos. La tabla `payment_gateways` es otra fuente. No hay validación contra proveedores reales.
- **Consecuencias:** A mantiene la flexibilidad, pero impide validar y automatizar. B permite validar, pero rompe etiquetas existentes. C es la más clara, pero requiere migración y UI. D simplifica manual, pero deja gateway sin identidad.
- **Recomendación técnica:** C si se incluye gateway; A o D si el alcance sigue siendo manual. No convertir `provider` en FK sin estrategia de datos.
- **Fases dependientes:** F5, F10.

### D-07 — ¿Los métodos base pueden tener configuración manual?

- **Alternativas:** A) sí, con advertencia y confirmación explícita; B) no, se bloquea la configuración; C) solo se permite si el método no es de cobro inmediato; D) se permite, pero el POS lo trata como venta pendiente normal.
- **Comportamiento actual:** un método base como efectivo puede tener una PMC manual. El POS puede mostrarlo y la venta puede quedar pendiente sin un aviso que explique la consecuencia.
- **Consecuencias:** A conserva flexibilidad, pero exige capacitación. B evita el error, pero puede romper configuraciones existentes. C distingue métodos. D repite el problema actual.
- **Recomendación técnica:** B para efectivo/débito/crédito si el flujo base ya cobra; A solo si el negocio necesita registrar una cuenta manual con confirmación. La decisión debe incluir migración de configuraciones existentes.
- **Fases dependientes:** F6B, F9.

### D-08 — ¿La tienda pública debe ofrecer transferencia?

- **Alternativas:** A) no ofrecerla; B) ofrecerla con datos bancarios de `comercios.transferencia_*` y comprobante manual; C) integrar un gateway de transferencia; D) ofrecerla solo si el comercio completó los datos.
- **Comportamiento actual:** `Welcome.vue` solo ofrece efectivo y Mercado Pago. `PedidoWebController` acepta `transferencia`, pero no usa los datos bancarios. El panel del cliente solo permite subir comprobante de un pedido ya creado.
- **Consecuencias:** A es consistente con la UI actual. B requiere definir datos, comprobante y confirmación. C es otro proyecto. D evita mostrar una opción incompleta.
- **Recomendación técnica:** A si no hay dueño de producto para transferencia; B o D si se quiere habilitar, con validación y estados de pedido claros. No reutilizar sin más el flujo manual del POS.
- **Fases dependientes:** F11.

### D-09 — ¿El onboarding debe exigir medios de pago?

- **Alternativas:** A) opcional, con recordatorio; B) exigir al menos uno; C) exigir todos los métodos activos; D) wizard omitible con advertencia.
- **Comportamiento actual:** el onboarding puede terminar sin medios de pago y el POS queda sin configuración. No hay un aviso único de completitud.
- **Consecuencias:** A reduce fricción, pero permite cuentas incompletas. B asegura operación mínima. C puede bloquear comercios que no usan todos los métodos. D es un intermedio.
- **Recomendación técnica:** B o D; no C salvo que producto lo justifique. La validación debe ser de servidor y mostrar qué falta.
- **Fases dependientes:** F11.

### D-10 — ¿El gateway de POS está en alcance?

- **Alternativas:** A) fuera de alcance: ocultar/deshabilitar gateway y evitar ventas pendientes huérfanas; B) completar gateway manual + QR + confirmación por aprobación; C) dejar solo deuda documentada; D) posponer a un proyecto separado.
- **Comportamiento actual:** el gateway del POS falla antes por falta de `PaymentService->forCommerce()`. `MpQrPanel.vue` existe pero no se usa. `confirmarPago` podría cerrar una venta gateway sin aprobación si se corrigiera solo el contexto.
- **Consecuencias:** A es seguro y acota el alcance. B requiere credenciales, webhook, idempotencia y pruebas de sandbox. C mantiene una trampa latente. D es equivalente a A con seguimiento.
- **Recomendación técnica:** A o D para este plan; B como fase separada con dueño y entorno de prueba. No autorizar B sin credenciales y sin prueba de aprobación.
- **Fases dependientes:** F10.

### D-11 — ¿Pueden coexistir manual y gateway para el mismo método?

- **Alternativas:** A) no, un método tiene un solo canal activo; B) sí, con selección explícita de canal en POS; C) gateway solo para métodos que no sean base; D) manual y gateway en pantallas separadas.
- **Comportamiento actual:** la UI configura manual; las filas gateway se crean por otro flujo. El unique no expresa canal y permite ambigüedad.
- **Consecuencias:** A simplifica selección y validación. B da flexibilidad, pero exige UI y snapshot de canal. C protege métodos base. D separa responsabilidades, pero duplica configuración.
- **Recomendación técnica:** A para el alcance actual; B solo si `D-01` y `D-10` lo requieren. La regla debe ser explícita en backend, no solo en UI.
- **Fases dependientes:** F5, F10.

### D-12 — ¿Cómo se resuelven los duplicados existentes?

- **Alternativas:** A) conservar la fila más antigua; B) conservar la más reciente; C) revisión manual caso por caso; D) archivar todas y crear una nueva; E) fusionar `display_data` en una fila.
- **Comportamiento actual:** puede haber varias filas con `provider` nulo para el mismo método. POS y backend pueden elegir distintas. No hay criterio de desempate.
- **Consecuencias:** A/B son deterministas, pero pueden conservar datos incorrectos. C es segura, pero requiere tiempo. D evita elegir, pero pierde continuidad si no se archiva. E puede mezclar datos incompatibles.
- **Recomendación técnica:** C con auditoría read-only, archivo lógico de las filas no elegidas y snapshot antes de cambiar. No borrar filas con ventas asociadas.
- **Fases dependientes:** F0, F4.

### D-13 — ¿Quién puede editar medios de pago?

- **Alternativas:** A) solo dueño/administrador del comercio; B) rol con permiso específico; C) cualquier usuario con acceso a Configuración; D) solo soporte global con auditoría.
- **Comportamiento actual:** no hay una política explícita documentada para PMC; el acceso depende del middleware y del rol general.
- **Consecuencias:** A es restrictivo y simple. B permite delegar. C es riesgoso porque afecta dinero. D centraliza, pero quita autonomía al comercio.
- **Recomendación técnica:** B, con permiso de edición separado del de lectura y auditoría de cambios. No implementar sin policy y tests cross-tenant.
- **Fases dependientes:** F3, F5.

### D-14 — ¿Se puede editar o exponer `channel`?

- **Alternativas:** A) siempre manual y oculto; B) seleccionable con permiso; C) solo lectura; D) pantallas separadas por canal.
- **Comportamiento actual:** no hay edición explícita de canal en el formulario; las filas gateway se gestionan por otro flujo. El unique no incluye canal.
- **Consecuencias:** A evita configuraciones inválidas. B da flexibilidad, pero requiere validar credenciales y coexistencia. C es seguro, pero impide corregir datos. D ordena la UI, pero duplica formularios.
- **Recomendación técnica:** A o C hasta que `D-10` y `D-11` estén resueltos; B solo con validación de gateway y permisos.
- **Fases dependientes:** F5, F10.

### D-15 — ¿Qué pasa al eliminar o desactivar una PMC con ventas o pendientes?

- **Alternativas:** A) soft delete/desactivar y conservar; B) borrar solo si no tiene uso; C) borrar siempre con snapshot previo; D) bloquear si hay ventas o pendientes.
- **Comportamiento actual:** el borrado no tiene una estrategia histórica explícita; las etiquetas se resuelven contra la configuración actual. Una venta pendiente puede quedar sin configuración.
- **Consecuencias:** A conserva trazabilidad y permite ocultar del POS. B es seguro, pero puede dejar basura. C exige snapshot y auditoría. D es conservador, pero puede impedir limpiar configuraciones mal creadas.
- **Recomendación técnica:** A + C: desactivar para el POS y conservar la fila; si se borra, exigir snapshot y bloquear si hay pendientes. No borrar datos históricos.
- **Fases dependientes:** F4, F8, F9.

---

## 6. Arquitectura objetivo propuesta

La arquitectura objetivo no se implementa en este documento. Se describe como contrato para que cada fase tenga un destino claro, sin elegir por producto las decisiones de la sección 5.

### 6.1. Principios

1. **Un solo normalizador de métodos.** Todo método que entra por HTTP, POS, checkout o webhook pasa por el mismo contrato de normalización y validación.
2. **Una sola resolución de configuración.** POS, backend, caja y etiquetas usan el mismo resolvedor; no se elige por `orderBy`, por posición de array ni por `pluck` accidental.
3. **Identidad explícita.** Cada pago debe poder referenciar la configuración que se usó, aunque la configuración cambie o se desactive después.
4. **Snapshot histórico.** La etiqueta, el proveedor y los datos de presentación se guardan al crear la venta; el histórico no se recalcula contra la configuración actual.
5. **Separación manual/gateway.** El flujo manual y el gateway tienen estados, validaciones y fuentes de credenciales distintas. No se mezclan por accidente.
6. **Aislamiento multi-tenant.** Toda lectura o escritura de PMC, venta, pago o caja demuestra pertenencia al comercio y a la sucursal cuando corresponde.
7. **Migraciones aditivas.** No se eliminan columnas, filas ni datos históricos sin estrategia explícita, auditoría y aprobación.
8. **Parametrización comercial.** Etiquetas, datos de presentación, campos obligatorios y políticas de POS son configurables; no se hardcodean reglas comerciales nuevas.

### 6.2. Componentes objetivo

| Componente | Responsabilidad | Estado |
|---|---|---|
| `App\Enums\MetodoPago` | Canonicalizar, normalizar acentos/aliases y exponer `tryFromString` para validación. | A modificar en F1. |
| `PaymentMethodConfiguration` | Representar la configuración de un método para un comercio, con identidad, canal y datos de presentación. | A modificar según D-01/D-06. |
| `PaymentMethodResolver` (nombre propuesto) | Resolver configuraciones por comercio, método, canal e ID; devolver DTO de presentación y snapshot. | Nuevo en F4. |
| `PaymentMethodSnapshot` (DTO propuesto) | Transportar `config_id`, método canónico, etiqueta, proveedor, canal y `display_data` al pago. | Nuevo en F8, usado por F4/F9. |
| `Venta` | Persistir pagos con snapshot y exponer etiquetas históricas con fallback legacy. | A modificar en F8. |
| `MovimientoCaja` | Conservar la etiqueta/categoría usada al momento del movimiento. | A modificar en F8. |
| `PaymentService` | Inicializar contexto de comercio y resolver gateway; no debe fallar por falta de `forCommerce` en POS. | A modificar en F10, si se autoriza. |
| `PaymentRecorder` | Registrar pagos manuales/gateway con estado, proveedor y snapshot. | A modificar en F4/F5/F9/F10. |
| `VentaOperacionService` | Confirmar, anular y devolver con caja/cuenta corriente de forma transaccional. | A modificar en F9. |
| `PedidoWebController` / `Welcome.vue` | Checkout público y datos de transferencia, solo si D-08 lo autoriza. | A modificar en F11. |

### 6.3. Flujo objetivo del POS manual

1. El backend carga las configuraciones del comercio mediante el resolvedor, no con consultas duplicadas en `PosController`.
2. El POS recibe solo configuraciones activas y del canal correspondiente, con identidad estable y etiqueta visible.
3. El cajero elige método y, si D-01 lo permite, configuración. El payload envía método canónico y `config_id` cuando corresponda.
4. El backend normaliza el método, valida que la configuración pertenezca al comercio, esté activa y sea del canal correcto.
5. La venta se crea con estado pendiente y con un snapshot de la configuración usada.
6. Al confirmar, se usa el snapshot y/o `config_id`; no se vuelve a resolver la etiqueta contra la configuración actual.
7. Caja, ticket y consultas históricas leen el snapshot. Si no existe, usan un fallback legacy explícito y no silencioso.

### 6.4. Variantes según D-01

Ninguna variante se implementa por defecto. La elección corresponde a D-01 (y a D-11 para el canal). Las variantes describen el destino técnico de cada alternativa para que F4 pueda planificarse una vez firmada la decisión.

**Variante A — una configuración activa por método**

- Identidad: `comercio_id + metodo_pago` (con `provider` como metadata, no como parte de la unicidad).
- POS: no muestra selección de configuración; el resolvedor devuelve la única activa.
- Payload: método canónico; `config_id` se persiste igual para trazabilidad.
- Migración: auditar duplicados, archivar/desactivar los no elegidos según D-12, luego aplicar unicidad. No borrar filas con uso.

**Variante B — varias configuraciones por método**

- Identidad: `comercio_id + metodo_pago + etiqueta/código` o la clave que defina producto; `provider` no puede quedar ambiguo.
- POS: muestra etiqueta y, si corresponde, prioridad/predeterminada; el cajero elige.
- Payload: método canónico + `config_id` obligatorio; backend rechaza una configuración de otro comercio, método o canal.
- Migración: agregar etiqueta/código/prioridad de forma aditiva; backfill asistido; unique después de resolver duplicados.

**Variante C — canal separado**

- Se separa explícitamente manual y gateway, con unicidad por canal.
- Solo se adopta si D-10/D-11 lo requieren; no se implementa por defecto.

### 6.5. Snapshot histórico

El snapshot mínimo debe incluir:

- `config_id` de la PMC usada, si existe.
- `metodo_pago` canónico.
- Etiqueta visible al momento de la venta.
- `provider` y `channel` usados.
- `display_data` relevante para el ticket o la caja.
- Fecha/origen del snapshot y versión del contrato.

Ubicación recomendada: dentro del JSON de `Venta.pagos`, con extensión opcional a `Payment.metadata` para gateway. La ubicación exacta y el backfill dependen de D-04. Las ventas antiguas sin snapshot deben seguir mostrando una etiqueta razonable, pero no se debe reescribir su historia con la configuración actual.

### 6.6. Límite del gateway

- El gateway debe inicializar `PaymentService` con el comercio antes de cualquier operación.
- La fuente de credenciales es `payment_gateways`; `provider_config` solo se usa si D-06/D-10 lo aprueban y existe en el modelo/migración.
- Una venta gateway no se considera cobrada hasta que el pago esté aprobado. La confirmación manual no puede sustituir la aprobación.
- Si el gateway queda fuera de alcance, el POS debe ocultar o bloquear configuraciones gateway y evitar pendientes huérfanos.
- Los webhooks deben ser idempotentes y validar comercio, pago y estado antes de mover caja o cuenta corriente.

### 6.7. Multi-tenant y autorización

- Las rutas de edición/borrado de PMC usan route model binding con verificación de `comercio_id` o una consulta equivalente que falle cerrado.
- Las policies distinguen lectura, edición y borrado; D-13 define los roles.
- Los endpoints nuevos se agregan a la matriz de `tests/Feature/CrossTenantAttackTest.php`.
- Nunca se acepta `comercio_id` desde el request para seleccionar la configuración; se deriva del usuario autenticado.
- Las ventas y movimientos de caja se filtran por comercio y sucursal según la cadena de pertenencia.

### 6.8. Compatibilidad y no destrucción

- Columnas nuevas: nullable o con default; sin `NOT NULL` inmediato sobre datos existentes.
- Unique/check constraints: solo después de auditar y resolver duplicados; evaluar índices parciales o archivo lógico.
- `provider` existente: conservar valores; no convertir a FK sin backfill y decisión D-06.
- `display_data`: preservar claves desconocidas; no descartar datos al normalizar.
- Ventas y movimientos históricos: no se recalculan ni se borran.
- Rollback: cada fase debe poder revertirse con `git revert`; las migraciones deben ser aditivas y las columnas nuevas no deben ser la única fuente hasta completar el backfill.

---

## 7. Fases BUILD ordenadas por dependencias

Cada fase tiene un objetivo único y un entregable verificable. Las fases que comparten UI o migración se ejecutan en el orden indicado, salvo que se acuerde una rama de integración. Las dependencias de producto se indican como bloqueos, no como supuestos.

### FASE 0 — Decisiones, auditoría read-only y contrato de datos (pre-BUILD)

- **Objetivo:** cerrar las decisiones D-01 a D-15, auditar los datos existentes sin modificarlos y aprobar el contrato de identidad/snapshot antes de tocar código.
- **Hallazgos incluidos:** todos, con foco en C-01, C-04, C-15 y las decisiones C-10/C-14.
- **Archivos y componentes:** `docs/informes/` (registro de decisiones); consultas SQL de solo lectura; no se modifica código de aplicación.
- **Cambios esperados:** registrar cada decisión con alternativa elegida, responsable y fecha; producir conteos de duplicados por método, filas con `provider` nulo, aliases usados, configuraciones incompletas y ventas/pendientes afectados; definir criterio de desempate D-12.
- **Reglas de negocio:** ninguna regla se cambia en F0. La auditoría no ejecuta ventas, no mueve caja y no borra datos.
- **Dependencias:** ninguna. Es la puerta de entrada para las fases que dependen de decisiones (F3 a F11), excepto los avisos P0 F6A/F7A, que pueden adelantarse sin decisión comercial.
- **Riesgos de regresión:** decidir sin datos; tocar producción con consultas pesadas. Mitigación: solo lectura, horario controlado y revisión del plan de consultas.
- **Pruebas automatizadas:** no aplica. Si se agrega un comando de auditoría, debe ser de solo lectura y probarse con datos de test; no se ejecuta en producción.
- **Pruebas manuales:** el responsable de producto firma las decisiones; el responsable de datos revisa duplicados y configuraciones incompletas.
- **Criterios verificables:** D-01 a D-15 tienen respuesta o estado “bloqueante” explícito; existe conteo de duplicados; existe criterio de desempate; existe plan de migración no destructiva; no se modificó código ni DB.
- **Reversibilidad:** no aplica; no hay cambios de datos.

### FASE 1 — Normalización de métodos y aliases (C-05, C-16)

- **Objetivo:** unificar la entrada de métodos, aceptar aliases documentados y rechazar lo desconocido sin convertir silenciosamente a `EFECTIVO`.
- **Hallazgos incluidos:** C-05 y C-16.
- **Archivos y componentes:** `app/Enums/MetodoPago.php`; `app/Http/Controllers/VentaController.php`; `app/Http/Controllers/ConsumidorController.php`; puntos de validación de pagos en caja/checkout que usen `fromString`; tests de feature/unit.
- **Cambios esperados:** agregar normalización de mayúsculas, espacios, guiones y acentos; agregar `tryFromString` con retorno nulo para desconocidos; usar el mismo contrato en venta manual, venta gateway y checkout; mantener una tabla de aliases compatibles; dejar de usar el fallback silencioso en validación; en display legacy, mostrar el valor crudo o “Método desconocido”, no `Efectivo`.
- **Reglas de negocio:** el método determina caja y cuenta corriente; el servidor es la fuente de verdad; no se puede cambiar el método de una venta existente; los aliases no crean métodos nuevos.
- **Dependencias:** F0 para confirmar la lista de aliases compatibles. No requiere D-01 ni D-04.
- **Riesgos de regresión:** ventas existentes con valores no canónicos; tests que esperan `Efectivo`; clientes que envían `Débito` o `VIÜMI`; checkouts que dependen del fallback.
- **Pruebas automatizadas:** unitarias de `normalize`/`tryFromString` con acentos, mayúsculas, espacios, guiones, aliases y desconocidos; feature de venta manual con alias; feature de checkout con alias; rechazo 422 de método desconocido; verificación de que no se crea movimiento de caja con método inválido; regresión de `Cuenta Corriente`.
- **Pruebas manuales:** vender con efectivo, débito y cuenta corriente; verificar caja y ticket; intentar un método inválido desde el flujo que permita texto libre y confirmar el rechazo; verificar que una venta histórica con valor raro no cambie de método.
- **Criterios verificables:** `VIÜMI` se normaliza a `VIUMI`; `DÉBITO` a `DEBITO`; `Cuenta Corriente` a `CUENTA_CORRIENTE`; un desconocido devuelve error y no crea venta; no hay fallback a `EFECTIVO` en validación; tests nuevos pasan y no aumentan los fallos del baseline.
- **Reversibilidad:** revertir código; no hay migración.

### FASE 2 — Errores del formulario PMC (C-08)

- **Objetivo:** que el usuario vea por qué no se guarda una configuración de medio de pago y pueda corregirla sin perder el formulario.
- **Hallazgos incluidos:** C-08.
- **Archivos y componentes:** `resources/js/Pages/Configuracion/Index.vue`; `app/Http/Controllers/ConfiguracionController.php`; mensajes de validación; tests de feature de validación.
- **Cambios esperados:** renderizar `pmcForm.errors` por campo; mapear errores de `display_data` y de método; conservar los valores ingresados; mostrar un resumen de error si hay varios; evitar que el error quede solo en la sesión o en la consola.
- **Reglas de negocio:** no cambia reglas comerciales; la validación de servidor sigue siendo la fuente de verdad.
- **Dependencias:** ninguna para la UI; F1 puede aportar el mensaje canónico de método.
- **Riesgos de regresión:** colisión de bolsas de error con el formulario principal; pérdida de estado al re-renderizar; mensajes duplicados.
- **Pruebas automatizadas:** feature que envía una PMC inválida y verifica errores de sesión/Inertia; feature de campo requerido; feature de `display_data` inválido; prueba de que un guardado válido no muestra errores.
- **Pruebas manuales:** guardar una transferencia sin datos; guardar una PMC con método inválido si la UI lo permite; verificar que el mensaje aparece junto al campo y que los datos siguen cargados.
- **Criterios verificables:** todo error de validación relevante es visible en la UI; no hay fallo silencioso; un guardado válido funciona; no se rompe el formulario principal de configuración.
- **Reversibilidad:** revertir cambios de Vue/controlador; no hay migración.

### FASE 3 — Edición por ID y aislamiento de PMC (C-01)

- **Objetivo:** editar exactamente la configuración seleccionada, sin crear otra fila y sin permitir acceso cruzado entre comercios.
- **Hallazgos incluidos:** C-01; parte de C-08 (identidad enviada por la UI).
- **Archivos y componentes:** `routes/web.php`; `app/Http/Controllers/ConfiguracionController.php`; `resources/js/Pages/Configuracion/Index.vue`; `app/Models/PaymentMethodConfiguration.php`; policy o middleware de autorización; `tests/Feature/CrossTenantAttackTest.php`.
- **Cambios esperados:** agregar ruta de actualización por ID; enviar el ID de la PMC en edición; actualizar solo esa fila; validar que la PMC pertenece al comercio autenticado; aplicar D-02 sobre si `metodo_pago` es editable; aplicar D-13 sobre permisos; el borrado también debe ser por ID y con la misma verificación.
- **Reglas de negocio:** multi-tenant estricto; una configuración no puede cambiar de comercio; el método no se cambia si D-02 lo prohíbe; no se duplican filas al editar.
- **Dependencias:** D-02 y D-13 resueltas; F1 para método canónico; F2 para mostrar errores.
- **Riesgos de regresión:** route model binding sin scope; mass assignment de `comercio_id`; cambio de `provider` que afecte pagos existentes; enlaces viejos que esperan el comportamiento anterior.
- **Pruebas automatizadas:** editar una PMC y verificar que solo cambia esa fila; intentar editar una PMC de otro comercio y obtener 403/404; intentar borrar una PMC de otro comercio; cambiar método según D-02; validar que no se crea una fila nueva; prueba de autorización por rol.
- **Pruebas manuales:** crear dos PMC del mismo método si D-01 lo permite; editar la segunda; verificar que la primera no cambió; recargar y confirmar persistencia; intentar editar una PMC de otro comercio solo en entorno de test.
- **Criterios verificables:** la edición actualiza una sola fila; no se crea una nueva; el aislamiento cross-tenant falla cerrado; los errores se muestran; la regla D-02 se cumple.
- **Reversibilidad:** revertir ruta/UI/controlador; no hay migración.

### FASE 4 — Unicidad, identidad y selección consistente (C-15, C-04)

- **Objetivo:** impedir duplicados ambiguos y garantizar que POS, backend, caja y etiquetas usen la misma configuración.
- **Hallazgos incluidos:** C-15 y C-04; parte de C-01.
- **Archivos y componentes:** nueva migración aditiva; `app/Models/PaymentMethodConfiguration.php`; `app/Http/Controllers/PosController.php`; `app/Http/Controllers/VentaController.php`; `resources/js/Pages/Pos/Terminal.vue`; `resources/js/Components/ConfirmarPagoModal.vue`; resolvedor de configuración propuesto; tests de POS, venta y cross-tenant.
- **Cambios esperados:** implementar la variante que defina D-01; agregar identidad explícita y, si corresponde, etiqueta/código/prioridad; reemplazar selecciones accidentales por el resolvedor único; enviar `config_id` desde POS cuando D-01 lo requiera; validar en backend que la configuración pertenece al comercio, método y canal; persistir `config_id` y snapshot mínimo al crear la venta; resolver duplicados existentes según D-12 sin borrar datos con uso.
- **Reglas de negocio:** una venta no puede quedar asociada a una configuración de otro comercio; el método y la configuración deben ser coherentes; la caja y el ticket deben mostrar la misma etiqueta que el POS; no se permite ambigüedad silenciosa.
- **Dependencias:** D-01 y D-12 resueltas; F3 completada; F1 completada.
- **Riesgos de regresión:** migración falla por duplicados; el POS muestra una configuración y el backend otra; ventas pendientes existentes; el modal agrupa pagos y no configuraciones; cambios en etiquetas usadas por caja.
- **Pruebas automatizadas:** unicidad según la variante elegida; POS y backend resuelven la misma fila; venta con `config_id` válido; rechazo de `config_id` de otro comercio/método/canal; duplicados existentes resueltos sin pérdida; snapshot persistido; regresión de POS con una y varias configuraciones; cross-tenant.
- **Pruebas manuales:** configurar el método según D-01; abrir POS y verificar la etiqueta; vender y confirmar; revisar ticket y caja; editar la configuración y verificar que la venta nueva usa la nueva etiqueta y la vieja conserva la histórica.
- **Criterios verificables:** no se pueden crear duplicados ambiguos; POS/backend/caja/ticket coinciden; toda venta nueva tiene identidad de configuración; los duplicados existentes quedaron resueltos o archivados de forma no destructiva; tests pasan.
- **Reversibilidad:** columnas/índices aditivos reversibles; si se archivaron filas, conservar los datos originales y documentar el rollback.

### FASE 5 — Semántica de `provider`, canal y credenciales (C-09)

- **Objetivo:** separar la etiqueta visible del proveedor técnico y dejar explícito qué configuraciones son manuales y cuáles gateway.
- **Hallazgos incluidos:** C-09; parte de C-12 y C-15.
- **Archivos y componentes:** `app/Models/PaymentMethodConfiguration.php`; `app/Http/Controllers/ConfiguracionController.php`; `resources/js/Pages/Configuracion/Index.vue`; `app/Services/Payment/PaymentRecorder.php`; `app/Http/Controllers/VentaController.php`; `app/Services/Payment/PaymentService.php`; migración aditiva si D-06 lo requiere; tests.
- **Cambios esperados:** aplicar D-06 (etiqueta, proveedor técnico o campos separados); aplicar D-11 sobre coexistencia manual/gateway; aplicar D-14 sobre edición/visibilidad de `channel`; validar `provider` contra `payment_gateways` solo si es proveedor técnico; no enviar una PMC manual al gateway; registrar en `Payment` el proveedor/canal correctos; migrar etiquetas existentes sin perder texto visible.
- **Reglas de negocio:** un pago manual no invoca gateway; un pago gateway requiere credenciales y estado de aprobación; no se mezclan canales para el mismo método sin decisión; el histórico no se reescribe.
- **Dependencias:** D-06, D-11 y D-14 resueltas; F4 completada para identidad; F1 para método canónico.
- **Riesgos de regresión:** valores de `provider` usados como etiqueta; configuraciones manuales con `provider = mercadopago`; validaciones nuevas que bloqueen datos existentes; UI que confunde canal con proveedor.
- **Pruebas automatizadas:** validación de `provider` según D-06; PMC manual no llama gateway; PMC gateway requiere configuración; `channel` correcto en `Payment`; migración de etiquetas; cross-tenant.
- **Pruebas manuales:** crear una PMC manual con proveedor “Mercado Pago” y verificar que no se invoca gateway; si D-10 lo permite, crear una gateway y verificar el canal; revisar que la etiqueta visible no cambió.
- **Criterios verificables:** la semántica de `provider` está documentada y aplicada; no hay pagos manuales con proveedor gateway; no hay coexistencia ambigua; los datos existentes se conservan.
- **Reversibilidad:** campos nuevos aditivos; conservar `provider` original; rollback documentado.

### FASE 6A — Aviso de método base con configuración manual (C-02, P0)

- **Objetivo:** que un método base con PMC manual nunca quede pendiente de forma silenciosa; esta subfase solo advierte y no decide si se bloquea.
- **Hallazgos incluidos:** C-02.
- **Archivos y componentes:** `app/Http/Controllers/PosController.php`; `resources/js/Pages/Pos/Terminal.vue`; `app/Http/Controllers/VentaController.php`; `ConfirmarPagoModal.vue` si muestra el aviso; tests de POS y venta.
- **Cambios esperados:** detectar método base con configuración manual; mostrar advertencia persistente en POS y en el resumen de pago; marcar la venta como pendiente con motivo visible; el backend devuelve un indicador/motivo de advertencia; no se implementa todavía la política de bloqueo D-07.
- **Reglas de negocio:** efectivo/débito/crédito tienen flujo base; una venta manual queda pendiente hasta confirmar; la caja no se mueve dos veces; el cajero no puede ignorar que el método no cobra al instante.
- **Dependencias:** F1/F2; F4 es opcional para la identidad, no bloquea el aviso. No requiere D-07.
- **Riesgos de regresión:** falsos positivos; configuraciones base existentes; advertencias ignoradas; ventas pendientes.
- **Pruebas automatizadas:** POS muestra advertencia con método base + PMC manual; la venta no mueve caja antes de confirmar; el backend devuelve el motivo; método base sin PMC no muestra advertencia; regresión de venta base normal.
- **Pruebas manuales:** configurar efectivo con PMC manual; abrir POS y verificar el aviso; vender y confirmar que no queda pendiente silenciosa; revisar caja.
- **Criterios verificables:** el aviso es visible en UI y backend; no hay pendiente silencioso; no hay movimiento de caja antes de confirmar; tests pasan.
- **Reversibilidad:** revertir aviso/indicador; no hay migración.

### FASE 6B — Política de bloqueo o confirmación de métodos base (C-02, P1)

- **Objetivo:** aplicar D-07 y decidir si el método base con PMC manual se bloquea o se permite con confirmación explícita.
- **Hallazgos incluidos:** C-02.
- **Archivos y componentes:** los mismos de F6A, más la validación backend y los mensajes de política; tests de POS, venta y caja.
- **Cambios esperados:** si D-07 bloquea, impedir creación/uso en UI y backend con mensaje claro; si permite, exigir confirmación explícita y registrar el motivo; migrar/comunicar configuraciones base existentes; no dejar una venta pendiente silenciosa.
- **Reglas de negocio:** la política D-07; caja y pendientes; no doble cobro; la decisión del cajero debe quedar registrada.
- **Dependencias:** D-07 resuelta; F6A; F4 para identidad; F5 para canal.
- **Riesgos de regresión:** configuraciones base existentes; cajeros acostumbrados al flujo actual; ventas pendientes; doble cobro.
- **Pruebas automatizadas:** caso bloqueado rechazado en UI y backend; caso permitido exige confirmación; venta pendiente no mueve caja; confirmación mueve una sola vez; regresión de venta base normal.
- **Pruebas manuales:** configurar efectivo con PMC manual; intentar vender; verificar bloqueo o confirmación según D-07; revisar caja.
- **Criterios verificables:** la política D-07 se cumple en UI y backend; no hay pendientes silenciosos; no hay doble movimiento de caja; tests pasan.
- **Reversibilidad:** revertir guardas/UI; no hay migración.

### FASE 7A — Aviso de transferencia sin datos (C-03, P0)

- **Objetivo:** que una transferencia sin datos no llegue al POS como una fila vacía y que el cajero vea el faltante; no define todavía qué campos son obligatorios.
- **Hallazgos incluidos:** C-03 (parte P0).
- **Archivos y componentes:** `resources/js/Components/ConfirmarPagoModal.vue`; `resources/js/Pages/Configuracion/Index.vue` si muestra el estado; `app/Http/Controllers/VentaController.php` si devuelve el indicador; tests de modal/POS.
- **Cambios esperados:** detectar `display_data` vacío o incompleto para transferencia; mostrar “sin datos” o un aviso equivalente; impedir filas de presentación completamente vacías; no bloquear la venta ni imponer campos nuevos.
- **Reglas de negocio:** la transferencia requiere instrucciones para que el cliente pague; la venta no se considera cobrada hasta confirmar; no se cambia la obligatoriedad comercial.
- **Dependencias:** F2 para mostrar el aviso; F4 es opcional. No requiere D-03 ni D-05.
- **Riesgos de regresión:** falsos positivos por formatos de `display_data`; configuraciones existentes incompletas; avisos ignorados.
- **Pruebas automatizadas:** transferencia vacía muestra aviso; transferencia parcial muestra aviso; modal no renderiza filas vacías; venta válida sigue funcionando; regresión de otros métodos.
- **Pruebas manuales:** crear transferencia vacía y parcial; abrir POS y modal; verificar el aviso; confirmar una venta y revisar caja.
- **Criterios verificables:** no se muestran filas vacías; el cajero ve el faltante; la venta no se bloquea por esta subfase; tests pasan.
- **Reversibilidad:** revertir aviso/UI; conservar datos.

### FASE 7B — Datos obligatorios por método y proveedor (C-03, P1)

- **Objetivo:** aplicar D-03 y D-05 para definir y validar los campos mínimos de transferencia y otros métodos.
- **Hallazgos incluidos:** C-03 (parte P1).
- **Archivos y componentes:** `app/Http/Controllers/ConfiguracionController.php`; `resources/js/Pages/Configuracion/Index.vue`; `app/Http/Controllers/VentaController.php` si valida uso; tests de validación y POS.
- **Cambios esperados:** definir campos mínimos por método/proveedor; validar al guardar y/o al usar según la alternativa elegida; aplicar bloqueo o advertencia según D-05; conservar configuraciones existentes; no borrar datos.
- **Reglas de negocio:** la transferencia requiere instrucciones; la venta no se considera cobrada hasta confirmar; los datos bancarios no son una regla de stock.
- **Dependencias:** D-03 y D-05 resueltas; F7A; F4 para identidad.
- **Riesgos de regresión:** comercios que usan transferencia sin CBU/alias; configuraciones existentes incompletas; ventas pendientes; bloqueo excesivo.
- **Pruebas automatizadas:** validación de campos mínimos; transferencia incompleta según política; venta con datos completos; regresión de otros métodos; cross-tenant.
- **Pruebas manuales:** crear transferencia vacía, parcial y completa; verificar mensajes; abrir POS y modal; confirmar una venta; revisar ticket/caja.
- **Criterios verificables:** la política D-03/D-05 se cumple; no se muestran filas vacías; el cajero ve qué falta; no se rompen configuraciones existentes.
- **Reversibilidad:** revertir validación/UI; conservar datos.

### FASE 8 — Snapshot histórico de etiquetas y datos (C-07)

- **Objetivo:** conservar la información de la configuración usada en cada venta y movimiento, sin depender de la configuración actual.
- **Hallazgos incluidos:** C-07; parte de C-04 y C-06.
- **Archivos y componentes:** `app/Models/Venta.php`; `app/Http/Controllers/VentaController.php`; `app/Models/MovimientoCaja.php`; `app/Http/Controllers/CajaDiariaController.php`; `app/Services/TicketBuilder.php`; `app/Http/Controllers/VentasCanceladasController.php`; `app/Http/Controllers/ConsumidorController.php`; migración aditiva si se requieren columnas; tests.
- **Cambios esperados:** aplicar D-04; persistir snapshot al crear la venta/pago; usar snapshot en etiquetas de venta, ticket, caja y consultas; agregar fallback legacy para ventas sin snapshot; no recalcular etiquetas históricas contra la PMC actual; documentar el contrato del snapshot.
- **Reglas de negocio:** trazabilidad de dinero; una venta no se modifica sin registro; caja y ticket deben coincidir con lo cobrado; el histórico no se reescribe.
- **Dependencias:** D-04 resuelta; F4 completada para `config_id`; F5 para proveedor/canal.
- **Riesgos de regresión:** JSON existente con formas distintas; tickets y caja que esperan etiquetas dinámicas; ventas antiguas; backfill costoso.
- **Pruebas automatizadas:** snapshot al crear venta; etiqueta histórica inmutable tras editar/eliminar PMC; ticket y caja usan snapshot; venta legacy sin snapshot usa fallback; regresión de anulación/devolución.
- **Pruebas manuales:** vender; cambiar la etiqueta de la PMC; reimprimir ticket y abrir caja; verificar que la venta vieja conserva la etiqueta; crear una venta nueva y ver la nueva etiqueta.
- **Criterios verificables:** la venta histórica no cambia al editar la PMC; caja y ticket coinciden; las ventas nuevas tienen snapshot; las viejas siguen legibles; tests pasan.
- **Reversibilidad:** columnas/JSON aditivos; no borrar datos; rollback conserva snapshot.

### FASE 9 — Confirmación y anulación robustas (C-06)

- **Objetivo:** impedir que una confirmación mueva caja o deuda con un pago huérfano, no aprobado o de otro comercio.
- **Hallazgos incluidos:** C-06; parte de C-13.
- **Archivos y componentes:** `app/Http/Controllers/VentaController.php`; `app/Services/VentaOperacionService.php`; `app/Services/Payment/PaymentRecorder.php`; `resources/js/Components/ConfirmarPagoModal.vue`; tests de venta, caja, cuenta corriente y anulación.
- **Cambios esperados:** usar `config_id`/snapshot para confirmar; si la configuración ya no existe, aplicar D-05 (bloquear con error claro o permitir con snapshot, según decisión); cancelar el `Payment` correcto usando el proveedor histórico; hacer la confirmación idempotente; validar comercio/sucursal; no mover caja si el pago no está aprobado cuando el flujo lo exige.
- **Reglas de negocio:** una venta pendiente no afecta caja/deuda hasta confirmar; la confirmación es única; la anulación revierte exactamente lo aplicado; no se puede confirmar un pago de otro comercio.
- **Dependencias:** F8 completada; D-05 resuelta; F4/F5 para identidad/proveedor.
- **Riesgos de regresión:** ventas pendientes existentes; pagos manuales ya cobrados; doble confirmación; anulación de ventas viejas; cuenta corriente.
- **Pruebas automatizadas:** confirmación feliz; confirmación con PMC eliminada/desactivada; pago no aprobado; pago de otro comercio; doble confirmación idempotente; anulación revierte caja y CC; regresión de venta manual.
- **Pruebas manuales:** crear venta pendiente; desactivar/eliminar la PMC; intentar confirmar y verificar el comportamiento definido; revisar caja y cuenta corriente; anular y verificar reversión.
- **Criterios verificables:** no queda `Payment` huérfano; no se mueve caja sin pago válido; la confirmación es idempotente; la anulación revierte lo correcto; tests pasan.
- **Reversibilidad:** código; snapshot y datos se conservan.

### FASE 10 — Gateway latente de POS (C-12, C-13)

- **Objetivo:** o completar el gateway de POS con contexto de comercio y aprobación real, o deshabilitarlo de forma segura y explícita.
- **Hallazgos incluidos:** C-12 y C-13; parte de C-09.
- **Archivos y componentes:** `app/Http/Controllers/VentaController.php`; `app/Services/Payment/PaymentService.php`; `app/Services/Payment/PaymentRecorder.php`; `app/Services/Payment/MercadopagoGateway.php`; `resources/js/Components/MpQrPanel.vue`; `resources/js/Components/ConfirmarPagoModal.vue`; controladores de webhook/notificación; tests con gateway fake.
- **Cambios esperados:** aplicar D-10, D-11 y D-14; inicializar `PaymentService->forCommerce()` antes de cualquier operación; usar la fuente de credenciales aprobada; si se completa, mostrar QR/presentación y exigir aprobación antes de confirmar; si no se completa, ocultar/bloquear configuraciones gateway y evitar ventas pendientes huérfanas; webhooks idempotentes y con validación de comercio/pago.
- **Reglas de negocio:** un pago gateway no se considera cobrado sin aprobación; no se mezcla manual y gateway sin decisión; no se mueve caja con estado incierto; los webhooks no duplican movimientos.
- **Dependencias:** D-10, D-11 y D-14 resueltas; F4/F5 completadas; entorno de prueba/credenciales si se implementa B.
- **Riesgos de regresión:** integración externa; credenciales; doble confirmación; webhook duplicado; ventas pendientes; exposición de QR; tests que no deben llamar a la API real.
- **Pruebas automatizadas:** gateway sin contexto falla de forma controlada; con contexto usa el comercio correcto; confirmación manual rechazada sin aprobación; webhook idempotente; QR/presentación según decisión; modo deshabilitado no crea pendientes; cross-tenant.
- **Pruebas manuales:** solo en entorno de prueba con credenciales/fake; generar una venta gateway, aprobar/rechazar y verificar caja; si no hay entorno, validar el modo deshabilitado y documentar que B no se autoriza.
- **Criterios verificables:** no hay gateway sin contexto; no se confirma sin aprobación; webhook idempotente; el modo deshabilitado es seguro; tests pasan sin llamadas reales.
- **Reversibilidad:** si se implementa B, feature acotada y reversible; si se deshabilita, conservar datos y configuración.

### FASE 11 — Onboarding y tienda pública (C-10, C-14)

- **Objetivo:** alinear el onboarding y el checkout público con las decisiones de producto, sin asumir que la tienda ya usa transferencia.
- **Hallazgos incluidos:** C-10 y C-14.
- **Archivos y componentes:** `app/Services/OnboardingService.php`; `app/Http/Controllers/PedidoWebController.php`; `resources/js/Pages/Welcome.vue`; `resources/js/Pages/Cliente/Panel.vue`; `resources/js/Pages/Configuracion/Index.vue`; tests de onboarding y pedidos web.
- **Cambios esperados:** aplicar D-08 y D-09; si la tienda ofrece transferencia, mostrar datos de `comercios.transferencia_*`, permitir comprobante y definir estados; si no, quitar la opción y no aceptar el método; el onboarding exige o advierte según la decisión; no duplicar la fuente de datos del POS.
- **Reglas de negocio:** el pedido web no mueve caja del POS; la transferencia del checkout no es el flujo manual del POS; el onboarding no debe dejar una cuenta inoperante sin aviso.
- **Dependencias:** D-08 y D-09 resueltas; F5 si se comparte semántica de métodos; F4 si se reutiliza identidad.
- **Riesgos de regresión:** comercios con onboarding incompleto; pedidos con `transferencia` ya guardados; panel del cliente; datos bancarios sensibles.
- **Pruebas automatizadas:** onboarding según política; checkout sin transferencia; checkout con transferencia y datos completos/incompletos; comprobante; pedido no mueve caja; cross-tenant.
- **Pruebas manuales:** completar onboarding con y sin medios; abrir tienda y verificar métodos; si D-08 lo permite, hacer un pedido por transferencia y subir comprobante; revisar panel del cliente.
- **Criterios verificables:** la tienda solo ofrece métodos decididos; no hay datos bancarios hardcodeados; el onboarding cumple la política; los pedidos existentes siguen legibles.
- **Reversibilidad:** UI/servicio; no borrar pedidos ni datos bancarios.

### FASE 12 — Regresión, documentación y cierre

- **Objetivo:** verificar que las fases anteriores no rompieron POS, caja, stock, fiados, pedidos web ni multi-tenancy, y dejar el informe técnico.
- **Hallazgos incluidos:** C-11 y validación transversal de todos.
- **Archivos y componentes:** `tests/Feature/`; `tests/Unit/`; `docs/informes/`; no se modifican reglas nuevas.
- **Cambios esperados:** ejecutar suite completa, pruebas de fase, Pint y build; comparar contra baseline; documentar decisiones, archivos, migraciones, pruebas y pendientes; actualizar la matriz cross-tenant.
- **Reglas de negocio:** no se aceptan regresiones en dinero, stock, caja, deuda ni aislamiento.
- **Dependencias:** todas las fases autorizadas; F0 con decisiones cerradas.
- **Riesgos de regresión:** fallos preexistentes que se confunden con nuevos; tests lentos; build de frontend; cambios fuera de alcance.
- **Pruebas automatizadas:** `php artisan test`; filtros por POS, ventas, caja, fiados, pedidos web, cross-tenant; `vendor/bin/pint --test`; `npm run build`.
- **Pruebas manuales:** checklist de aceptación de la sección 9; una venta manual completa, una transferencia, una anulación y una consulta histórica.
- **Criterios verificables:** no hay fallos nuevos respecto del baseline; Pint y build pasan; el informe está en `docs/informes/`; no quedan decisiones críticas sin documentar.
- **Reversibilidad:** no aplica; es cierre.

---

## 8. Archivos afectados por fase

La lista es orientativa y debe ajustarse al código real al abrir cada BUILD. No autoriza a modificar archivos fuera de alcance. Los nombres de clases nuevas son propuestos y deben confirmarse en el plan de implementación de la fase.

| Fase | Archivos/componentes principales | Tipo |
|---|---|---|
| F0 | `docs/informes/`; consultas SQL de solo lectura; sin código de aplicación. | Documentación/auditoría |
| F1 | `app/Enums/MetodoPago.php`; `app/Http/Controllers/VentaController.php`; `app/Http/Controllers/ConsumidorController.php`; tests de métodos/ventas/checkout. | Modificación |
| F2 | `resources/js/Pages/Configuracion/Index.vue`; `app/Http/Controllers/ConfiguracionController.php`; tests de validación. | Modificación |
| F3 | `routes/web.php`; `app/Http/Controllers/ConfiguracionController.php`; `resources/js/Pages/Configuracion/Index.vue`; `app/Models/PaymentMethodConfiguration.php`; policy/middleware; `tests/Feature/CrossTenantAttackTest.php`. | Modificación |
| F4 | Nueva migración aditiva; `PaymentMethodConfiguration`; `PosController`; `VentaController`; `Terminal.vue`; `ConfirmarPagoModal.vue`; resolvedor propuesto; tests de POS/venta/cross-tenant. | Nuevo + modificación |
| F5 | `PaymentMethodConfiguration`; `ConfiguracionController`; `Configuracion/Index.vue`; `PaymentRecorder`; `VentaController`; `PaymentService`; migración aditiva si D-06 lo exige; tests. | Modificación (+ migración condicional) |
| F6A | `PosController`; `Pos/Terminal.vue`; `VentaController`; `ConfirmarPagoModal.vue`; tests de POS/venta. | Modificación |
| F6B | `PosController`; `Pos/Terminal.vue`; `VentaController`; mensajes de política; tests de POS/venta/caja. | Modificación |
| F7A | `ConfirmarPagoModal.vue`; `Configuracion/Index.vue` si muestra estado; `VentaController` si devuelve indicador; tests de modal/POS. | Modificación |
| F7B | `ConfiguracionController`; `Configuracion/Index.vue`; `VentaController` si valida; tests de validación/POS. | Modificación |
| F8 | `Venta`; `VentaController`; `MovimientoCaja`; `CajaDiariaController`; `TicketBuilder`; `VentasCanceladasController`; `ConsumidorController`; migración aditiva; tests. | Modificación (+ migración) |
| F9 | `VentaController`; `VentaOperacionService`; `PaymentRecorder`; `ConfirmarPagoModal.vue`; tests de caja/CC/anulación. | Modificación |
| F10 | `VentaController`; `PaymentService`; `PaymentRecorder`; `MercadopagoGateway`; `MpQrPanel.vue`; `ConfirmarPagoModal.vue`; webhook/notificación; tests con fake. | Modificación condicional |
| F11 | `OnboardingService`; `PedidoWebController`; `Welcome.vue`; `Cliente/Panel.vue`; `Configuracion/Index.vue`; tests. | Modificación |
| F12 | `tests/Feature/`; `tests/Unit/`; `docs/informes/`. | Tests + documentación |

**Archivos que no se tocan en este plan:** `AGENTS.md`, skills, precios/planes, funcionalidades ajenas a medios de pago, migraciones históricas sin estrategia aprobada y cambios preexistentes del working tree.

---

## 9. Pruebas y criterios de aceptación

### 9.1. Estrategia de testing

- Framework: PHPUnit 10.5 con `php artisan test`.
- Base: PostgreSQL de testing; `TestCaseMultiTenant` y `QATestDataSeeder` para los casos de negocio.
- No hay tests JS. La UI se valida por HTTP/Inertia y pruebas manuales; no se agrega Vitest/Cypress/Dusk sin decisión explícita.
- Cada endpoint nuevo o modificado debe tener prueba cross-tenant.
- Cada fase debe registrar el baseline antes de empezar y comparar después. El baseline conocido tiene 23 fallos preexistentes; no se ocultan ni se corrigen fuera de alcance.
- Comandos de cierre: `php artisan test`, `vendor/bin/pint --test`, `npm run build`.

### 9.2. Matriz de pruebas por fase

| Fase | Pruebas automatizadas mínimas | Pruebas manuales mínimas |
|---|---|---|
| F1 | Unitarias de normalización/aliases/acentos; feature de venta manual y checkout; rechazo de desconocido; no fallback a efectivo; regresión de cuenta corriente. | Venta con efectivo, débito, cuenta corriente y un método inválido; revisar caja/ticket. |
| F2 | Feature de PMC inválida con errores visibles; guardado válido; `display_data` inválido. | Guardar transferencia incompleta y ver el mensaje; corregir y guardar. |
| F3 | Edición por ID; no duplicar; cross-tenant 403/404; permisos; cambio de método según D-02. | Crear dos PMC, editar una y verificar la otra; recargar. |
| F4 | Unicidad según D-01; POS/backend misma fila; `config_id` válido/inválido; duplicados resueltos; snapshot; cross-tenant. | Configurar método, vender, confirmar, revisar ticket/caja; editar etiqueta y ver histórico. |
| F5 | `provider` según D-06; manual no invoca gateway; canal correcto; migración de etiquetas. | PMC manual con proveedor “Mercado Pago” no debe abrir gateway; revisar etiqueta. |
| F6A | Método base con PMC manual muestra aviso; no mueve caja antes de confirmar; backend devuelve motivo. | Configurar efectivo manual, abrir POS, verificar aviso, vender y revisar caja. |
| F6B | Política D-07: bloqueo o confirmación explícita; backend rechaza/bloquea; caja no se mueve dos veces. | Configurar efectivo manual, intentar vender, verificar política y caja. |
| F7A | Transferencia vacía/parcial muestra aviso; modal sin filas vacías; venta no bloqueada por esta subfase. | Crear transferencia vacía/parcial, abrir POS/modal, verificar aviso. |
| F7B | Campos mínimos D-03; obligatoriedad/aviso D-05; venta válida; regresión. | Crear transferencia vacía, parcial y completa; verificar mensajes y POS. |
| F8 | Snapshot al vender; etiqueta histórica inmutable; ticket/caja; legacy; anulación/devolución. | Vender, cambiar/eliminar PMC, reimprimir ticket y abrir caja. |
| F9 | Confirmación feliz; PMC eliminada; pago no aprobado; cross-tenant; doble confirmación; anulación. | Venta pendiente, desactivar PMC, confirmar según política, revisar caja/CC. |
| F10 | Gateway sin contexto; con contexto; aprobación; webhook idempotente; modo deshabilitado; fake. | Solo en entorno de prueba/fake; si no hay, validar modo deshabilitado. |
| F11 | Onboarding según D-09; checkout sin/con transferencia según D-08; comprobante; cross-tenant. | Onboarding con y sin medios; pedido por transferencia si está habilitado. |
| F12 | Suite completa; filtros POS/caja/fiados/pedidos; Pint; build. | Checklist de aceptación integral. |

### 9.3. Casos de regresión obligatorios

- Venta manual en efectivo: total, caja y ticket.
- Venta con débito/crédito: recargo y pago manual.
- Venta con cuenta corriente: deuda y movimiento.
- Venta pendiente: no mueve caja ni deuda hasta confirmar.
- Confirmación: una sola vez, con pago válido.
- Anulación/devolución: revierte caja, stock y cuenta corriente según corresponda.
- Edición/borrado de PMC: no rompe ventas históricas.
- Cross-tenant: no se puede leer, editar, borrar ni usar una PMC de otro comercio.
- Pedido web: no mueve caja del POS.
- Onboarding: no deja una cuenta inoperante sin aviso según la política elegida.

### 9.4. Criterios globales de aceptación

1. Cada fase tiene tests nuevos o actualizados que fallan sin el cambio y pasan con él.
2. No se agregan fallos nuevos respecto del baseline registrado.
3. `vendor/bin/pint --test` pasa en los archivos tocados.
4. `npm run build` pasa si se modificó frontend.
5. No hay migraciones destructivas ni pérdida de datos históricos.
6. No hay fugas cross-tenant en endpoints nuevos.
7. Las decisiones de producto aplicadas están citadas en el informe de la fase.
8. El usuario ejecutó las pruebas manuales indicadas y dejó resultado.

---

## 10. Riesgos y estrategia de compatibilidad

### 10.1. Principios de compatibilidad

- Los datos históricos no se borran ni se reescriben para “corregir” etiquetas.
- Las columnas nuevas son nullable o tienen default; no se impone `NOT NULL` sobre datos existentes sin backfill.
- Los cambios de unicidad se aplican después de auditar duplicados y definir el criterio D-12.
- Los valores desconocidos de `provider` o `display_data` se conservan; no se descartan al normalizar.
- Las ventas antiguas sin snapshot siguen siendo legibles con un fallback explícito.
- Cada fase debe poder revertirse sin pérdida de datos; si una migración agrega una columna, el rollback no debe eliminar datos históricos.

### 10.2. Matriz de riesgos

| Riesgo | Impacto | Fase | Mitigación |
|---|---|---|---|
| Duplicados existentes impiden aplicar unicidad. | Alto: migración falla o se elige una PMC incorrecta. | F0, F4 | Auditoría read-only, criterio D-12, archivo/desactivación no destructiva, migración en pasos. |
| POS y backend siguen eligiendo configuraciones distintas. | Alto: etiqueta, caja y ticket inconsistentes. | F4 | Resolvedor único, `config_id` validado, tests de coincidencia. |
| Cambiar una PMC altera ventas históricas. | Alto: trazabilidad de dinero. | F8 | Snapshot y lectura histórica; no recalcular etiquetas. |
| `provider` se usa como etiqueta y como gateway. | Alto: pagos manuales podrían invocar gateway o validar mal. | F5, F10 | D-06/D-11, separación de campos, pruebas de no invocación. |
| Gateway sin contexto o sin aprobación. | Crítico: venta cerrada sin cobro real. | F10 | `forCommerce`, estado aprobado, idempotencia, modo deshabilitado seguro. |
| Transferencia incompleta llega al POS. | Medio: cliente sin instrucciones, cajero confundido. | F7A, F7B | Aviso P0 sin decisión, validación D-03, modal sin filas vacías. |
| Onboarding deja cuenta inoperante. | Medio: comercio sin medios de pago. | F11 | D-09, advertencia/validación y mensaje de completitud. |
| Tests preexistentes fallan y se confunden con regresiones. | Medio: no se distingue lo nuevo. | Todas | Baseline de 23 fallos, comparación por archivo, no ocultar. |
| Edición/borrado cross-tenant. | Crítico: fuga entre comercios. | F3, F4 | Route binding con scope, policy, `CrossTenantAttackTest`. |
| Cambios preexistentes del working tree se mezclan. | Medio: diff sucio, rollback difícil. | Todas | Revisar `git status`, trabajar en rama/worktree acordado, no revertir cambios ajenos. |

### 10.3. Estrategia de migración y backfill

1. **Auditoría previa (F0):** contar duplicados por comercio/método, filas con `provider` nulo, valores no canónicos y ventas/pendientes afectados.
2. **Decisión de desempate (D-12):** elegir fila, archivar/desactivar las demás y conservar datos originales.
3. **Migración aditiva:** agregar columnas/índices nuevos; no renombrar ni eliminar en el mismo paso.
4. **Backfill asistido:** completar snapshot o etiquetas solo para datos nuevos o para casos aprobados; no reescribir historia sin decisión.
5. **Constraints:** aplicar unique/check después de la limpieza; evaluar índices parciales para activos y archivo lógico.
6. **Rollback:** documentar cómo revertir índice/columna sin borrar filas; si hay archivo lógico, mantener las filas originales.
7. **Verificación:** comparar conteos antes/después y ejecutar tests de integridad de caja, stock y cuenta corriente.

### 10.4. Compatibilidad con datos existentes

- **Ventas:** `Venta.pagos` puede tener formas anteriores; el lector debe aceptar la ausencia de `config_id` y snapshot.
- **Caja:** `MovimientoCaja` puede no tener snapshot; usar la etiqueta actual solo como fallback y documentarlo.
- **PMC:** `provider` nulo y `display_data` libre deben seguir siendo legibles; no convertir a FK sin backfill.
- **Pagos:** `Payment` puede tener proveedor/canal históricos; no se recalculan.
- **Pedidos web:** pedidos con `transferencia` existentes no se eliminan; se muestran según la política D-08.
- **Onboarding:** cuentas ya creadas sin medios no se bloquean retroactivamente sin decisión.

### 10.5. Seguridad y multi-tenant

- Las rutas nuevas de PMC deben fallar cerrado si no pueden demostrar pertenencia al comercio.
- No se acepta `comercio_id` desde el request para resolver una configuración.
- Las policies deben cubrir lectura, edición y borrado; D-13 define roles.
- Los tests cross-tenant se agregan antes de cerrar la fase, no al final.
- Los webhooks validan comercio, pago, estado y firma/credencial según el proveedor; no confían en el payload solo.

---

## 11. Orden recomendado de ejecución

### 11.1. Secuencia

1. **F0** — decisiones, auditoría y contrato. Sin código.
2. **F1** — normalización de métodos. Puede empezar con F2.
3. **F2** — errores de formulario PMC. Puede empezar en paralelo con F1.
4. **F6A** — aviso P0 de método base con PMC manual. No requiere D-07; puede ir en paralelo con F3.
5. **F7A** — aviso P0 de transferencia sin datos. No requiere D-03; puede ir en paralelo con F3.
6. **F3** — edición por ID y aislamiento. Requiere D-02 y D-13.
7. **F4** — unicidad, identidad y selección. Requiere D-01, D-12 y F3.
8. **F5** — `provider`, canal y credenciales. Requiere D-06, D-11, D-14 y F4.
9. **F6B** — política de métodos base. Requiere D-07, F6A y F4.
10. **F7B** — obligatoriedad de datos. Requiere D-03, D-05, F7A y F4.
11. **F8** — snapshot histórico. Requiere D-04 y F4.
12. **F9** — confirmación/anulación. Requiere F8 y D-05.
13. **F10** — gateway latente. Requiere D-10, D-11, D-14 y F4/F5.
14. **F11** — onboarding/tienda. Requiere D-08, D-09 y F5.
15. **F12** — regresión, documentación y cierre.

### 11.2. Paralelización posible

- **F1 y F2** pueden ejecutarse en paralelo si no comparten archivos.
- **F6A y F7A** pueden ejecutarse en paralelo con F3, con coordinación de `Terminal.vue` y `ConfirmarPagoModal.vue`; si hay conflicto, hacerlas antes de F4.
- **F6B y F7B** pueden ejecutarse en paralelo después de F4, con coordinación de UI de configuración.
- **F10 y F11** pueden ejecutarse en paralelo si las decisiones están cerradas y no comparten archivos.
- **F3 y F4** no deben paralelizarse: F4 necesita la edición por ID para resolver duplicados.
- **F8 y F9** no deben paralelizarse: F9 consume el snapshot de F8.

### 11.3. Camino crítico

`F0 → F3 → F4 → F8 → F9 → F12`

F1 debe estar antes de F3/F4 para que el método canónico sea estable. F2 puede ir en paralelo. F6A/F7A son avisos P0 que pueden adelantarse sin decisión de producto. F5, F6B, F7B y F11 son ramas que dependen de F4 y de sus decisiones. F10 es una rama condicional que puede quedar fuera de alcance.

### 11.4. Primer BUILD recomendado

El primer BUILD debería ser **F1 + F2**, porque:

- Corrige los P0 de normalización sin cambiar reglas comerciales.
- Mejora la configuración sin migración.
- Permite validar el flujo de trabajo, el baseline y la calidad de los tests antes de tocar identidad y datos.
- No requiere D-01, D-04 ni D-06.

El segundo BUILD recomendado es **F6A + F7A**, porque eliminan los pendientes silenciosos P0 sin exigir una decisión comercial. Después se recomienda **F3 → F4**, porque estabilizan la identidad y la selección, que son la base de todo lo demás.

---

## 12. Condiciones necesarias para autorizar cada BUILD

### 12.1. Condiciones generales

Un BUILD solo se autoriza cuando se cumplen todas estas condiciones:

1. **Decisión de producto firmada** para las dependencias de la fase, o declaración explícita de que la fase no toca esa decisión.
2. **Alcance cerrado:** archivos, endpoints, migraciones y pruebas listados; nada fuera de medios de pago.
3. **Baseline registrado:** resultado de `php artisan test` antes de empezar, con los 23 fallos preexistentes identificados.
4. **Pruebas definidas:** casos automatizados y manuales acordados antes de implementar.
5. **Migración revisada:** si hay DB, estrategia aditiva, backfill, constraints y rollback aprobados.
6. **Rollback posible:** código reversible; datos históricos conservados.
7. **Aislamiento verificado:** tests cross-tenant para endpoints nuevos o modificados.
8. **Working tree controlado:** no se revierten cambios preexistentes; se acuerda rama/worktree y se revisa `git status` antes/después.
9. **Informe de fase:** se genera en `docs/informes/` con objetivo, archivos, decisiones, pruebas y pendientes.
10. **Sin operaciones de negocio reales:** no se ejecutan ventas, cobros, arqueos ni migraciones en producción durante el BUILD.

### 12.2. Condiciones por fase

| Fase | Decisiones requeridas | Precondiciones técnicas | Migración | Autorización |
|---|---|---|---|---|
| F0 | D-01 a D-15 | Auditoría read-only y responsable de producto disponible. | No | Solo después de firmar decisiones o marcar bloqueos. |
| F1 | Lista de aliases compatibles | Baseline registrado y tests de normalización definidos. | No | Puede autorizarse sin decisiones comerciales. |
| F2 | Ninguna | Baseline registrado y contrato de errores de UI. | No | Puede autorizarse sin decisiones comerciales. |
| F3 | D-02, D-13 | F1/F2; policy y route binding revisados. | No | Solo con permisos y regla de método definidos. |
| F4 | D-01, D-12 | F3; auditoría de duplicados; resolvedor definido. | Sí, aditiva | Solo con criterio de desempate y rollback aprobados. |
| F5 | D-06, D-11, D-14 | F4; fuente de credenciales y semántica de `provider`. | Condicional | Solo si la semántica y el canal están cerrados. |
| F6A | Ninguna | F1/F2; aviso de método base. | No | Puede autorizarse sin decisión comercial. |
| F6B | D-07 | F6A y F4; política de métodos base. | No | Solo con política UI/backend acordada. |
| F7A | Ninguna | F2; aviso de transferencia incompleta. | No | Puede autorizarse sin decisión comercial. |
| F7B | D-03, D-05 | F7A y F4; campos mínimos por método. | No | Solo con obligatoriedad/aviso definidos. |
| F8 | D-04 | F4; contrato de snapshot y fallback legacy. | Sí, aditiva | Solo con ubicación de snapshot y backfill aprobados. |
| F9 | D-05 | F8; política de configuración ausente. | No | Solo con snapshot operativo. |
| F10 | D-10, D-11, D-14 | F4/F5; entorno de prueba o decisión de deshabilitar. | Condicional | Solo con credenciales/fake y prueba de aprobación; si no, autorizar solo modo deshabilitado. |
| F11 | D-08, D-09 | F5; datos de tienda y onboarding definidos. | No | Solo con política de checkout/onboarding. |
| F12 | Todas las anteriores | Fases autorizadas completadas; baseline actualizado. | No | Solo al cerrar; no introduce reglas nuevas. |

### 12.3. No autorizar si ocurre alguno de estos casos

- No hay decisión firmada para una fase que modifica reglas comerciales.
- La migración requiere borrar filas, columnas o datos históricos sin estrategia aprobada.
- No se puede demostrar aislamiento cross-tenant del endpoint nuevo.
- El gateway se va a probar contra producción sin credenciales de prueba y sin idempotencia.
- El baseline de tests no está registrado o se pretende ocultar un fallo preexistente.
- El cambio incluye precios, planes, stock, fiados o funcionalidades ajenas a medios de pago.
- Se intenta modificar `AGENTS.md`, skills o revertir cambios preexistentes del working tree.

### 12.4. Decisiones que necesitan respuesta antes de empezar

**Pueden comenzar sin decisión comercial:** F1 (con lista de aliases confirmada), F2, F6A y F7A.

**Bloquean el resto del plan:**

- `D-01` — más de una configuración por método. Bloquea F4.
- `D-02` — cambio de método de una PMC. Bloquea F3.
- `D-03` — datos obligatorios por método/proveedor. Bloquea F7B.
- `D-04` — identificación y conservación histórica. Bloquea F8 y F9.
- `D-05` — comportamiento del POS ante configuración incompleta. Bloquea F6B, F7B y F9.
- `D-06` — semántica de `provider`. Bloquea F5 y F10.
- `D-07` — métodos base manuales. Bloquea F6B.
- `D-08` — transferencia en tienda. Bloquea F11.
- `D-09` — onboarding obligatorio/opcional. Bloquea F11.
- `D-10` — alcance del gateway. Bloquea F10.
- `D-11` — coexistencia manual/gateway. Bloquea F5 y F10.
- `D-12` — resolución de duplicados existentes. Bloquea F4.
- `D-13` — permisos de edición. Bloquea F3.
- `D-14` — edición/visibilidad de `channel`. Bloquea F5 y F10.
- `D-15` — ciclo de vida de PMC con ventas/pendientes. Bloquea F4, F8 y F9.

**Orden mínimo para destrabar el plan:** D-01, D-04 y D-06 primero; luego D-02, D-03, D-05, D-07 y D-12; después D-08 a D-11 y D-13 a D-15.

---

**Fin del plan consolidado.** Este documento no implementa ninguna corrección. Cualquier BUILD debe abrirse con la fase, la decisión firmada y las condiciones de la sección 12.
