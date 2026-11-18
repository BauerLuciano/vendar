# PLAN — Auditoría funcional y técnica de Medios de Pago configurables para POS

**Fecha:** 17/11/2026  
**Alcance:** configuración de medios de pago para POS, persistencia, selector del POS, flujo de cobro manual, confirmación, anulación/devolución y etiquetas de caja.  
**Modo:** auditoría de solo lectura. No se implementó ninguna corrección.  
**Estado:** informe de diagnóstico y propuesta. Toda implementación queda para una etapa posterior.

> **Confirmación expresa:** durante esta auditoría no se modificó código, migraciones, base de datos, configuraciones, datos existentes, dependencias, `AGENTS.md` ni skills. Tampoco se ejecutaron ventas, cobros, arqueos, anulaciones ni ninguna otra operación de negocio. El único archivo creado es este informe.

---

## 1. Resumen ejecutivo

El módulo de Medios de Pago para POS existe y funciona de punta a punta para el caso feliz: se configura un método manual, el POS lo muestra, la venta queda pendiente, el cajero confirma y recién ahí se mueve la caja o la cuenta corriente.

La auditoría encontró que el problema no está en el circuito principal, sino en los **bordes del contrato** entre configuración, POS y backend:

1. **Editar no actualiza por ID.** El formulario de edición no envía `editingPmcId`; el backend hace `updateOrCreate` por `comercio_id + metodo_pago + provider`. Si se cambia el proveedor, se crea otra fila y la original queda viva. Esto explica de forma muy probable la observación de “viü” y “Mi” como dos registros separados.
2. **El selector de Configuración tiene 7 opciones y `viüMi` es una sola.** No se pudo reproducir “viü” y “Mi” como dos opciones del `<select>` actual. La evidencia apunta a dos filas de configuración con `provider` distinto, no a dos opciones del enum.
3. **Transferencia puede guardarse vacía.** El backend solo exige `metodo_pago`; todos los `display_data.*` son nullable. El POS muestra el método igual, la venta queda pendiente y el modal puede no tener ningún dato para que el cajero cobre.
4. **Una PMC manual para un método base cambia el backend, pero no la UI del POS.** Si se configura “Efectivo”, “Débito”, “Crédito” o “Cuenta Corriente” como método manual, el POS sigue mostrando el botón base y no avisa que la venta quedará pendiente. El stock se descuenta al crear la venta y la caja no se mueve hasta confirmar.
5. **Hay múltiples fuentes de verdad para el mismo método.** Se permiten varias filas por método con distinto `provider`; el POS elige una, el backend puede elegir otra y el modal agrupa todo sin enviar el ID de la configuración elegida.
6. **La validación de `metodo_pago` en ventas es laxa.** `pagos.*.metodo_pago` solo se valida como `string`; `MetodoPago::fromString()` convierte cualquier valor desconocido en `EFECTIVO`. Un POST directo puede usar métodos que el POS no ofrece.
7. **La trazabilidad histórica depende de la configuración actual.** Las ventas y movimientos de caja guardan el método, pero no el proveedor ni la etiqueta. Si se cambia o elimina la PMC, las ventas y cajas viejas cambian de etiqueta.
8. **No hay tests que cubran `PaymentMethodConfiguration`.** El CRUD, las validaciones condicionales y el flujo manual del POS no tienen cobertura.

**Veredicto corto:** el circuito base es correcto, pero la configuración no tiene un contrato suficientemente estricto. Antes de agregar más funcionalidad hay que cerrar la identidad de la configuración, la completitud mínima de Transferencia, la coherencia de los métodos base y la trazabilidad de lo ya vendido.

---

## 2. Archivos, rutas y métodos relevados

### 2.1 Backend

| Archivo | Método / zona | Rol en el flujo |
|---|---|---|
| `app/Http/Controllers/ConfiguracionController.php` | `index` (`18-65`) | Carga configuraciones, PMCs y opciones del selector. |
| `app/Http/Controllers/ConfiguracionController.php` | `storePaymentMethodConfig` (`178-214`) | Valida y guarda una PMC manual. |
| `app/Http/Controllers/ConfiguracionController.php` | `destroyPaymentMethodConfig` (`216-228`) | Elimina una PMC con control de comercio. |
| `app/Http/Controllers/ConfiguracionController.php` | `update` (`67-176`) | Guarda datos de tienda, `comercios.transferencia_*` y `payment_gateways`. |
| `app/Http/Controllers/PosController.php` | `index` (`31-130`) | Carga PMCs habilitadas de canal manual y arma los métodos del POS. |
| `app/Http/Controllers/VentaController.php` | `store` (`108-567`) | Valida pagos, detecta flujo manual/gateway, crea venta, stock y pagos pendientes. |
| `app/Http/Controllers/VentaController.php` | `confirmarPago` (`576-648`) | Confirma la venta pendiente, mueve caja/cuenta corriente y aprueba el pago. |
| `app/Http/Controllers/VentaController.php` | `loadAllConfigs` (`746-777`) | Indexa configuraciones manuales y gateway por método. |
| `app/Services/VentaOperacionService.php` | `anular` / `devolver` (`38-281`) | Revierte stock, caja, cuenta corriente y pagos pendientes. |
| `app/Services/VentaOperacionService.php` | `loadManualConfigs` (`283-304`) | Vuelve a cargar configuraciones manuales para anular. |
| `app/Services/Payment/PaymentRecorder.php` | `createPending`, `approve`, `cancel` (`15-118`, `198-215`) | Crea, aprueba y cancela pagos pendientes. |
| `app/Services/OnboardingService.php` | `pasoAjustes` (`205-228`) | Marca “Ajustes Globales” como completo con cualquier PMC habilitada. |

### 2.2 Frontend

| Archivo | Zona | Rol |
|---|---|---|
| `resources/js/Pages/Configuracion/Index.vue` | `84-121` | `pmcForm`, edición, reset y submit. |
| `resources/js/Pages/Configuracion/Index.vue` | `575-660` | Listado de PMCs y formulario “Nuevo/Editar”. |
| `resources/js/Pages/Configuracion/Index.vue` | `480-512` | Datos de Transferencia de la pestaña “Tienda Online y Pagos”. |
| `resources/js/Pages/Pos/Terminal.vue` | `114-166` | `METODOS_DISPONIBLES`, deduplicación y agrupación de métodos. |
| `resources/js/Pages/Pos/Terminal.vue` | `821-897` | Payload de `ventas.store` y apertura del modal de confirmación. |
| `resources/js/Pages/Pos/Terminal.vue` | `1543-1625` | Botones y dropdowns de métodos de pago. |
| `resources/js/Components/ConfirmarPagoModal.vue` | `17-63` | Agrupación, visualización y confirmación/cancelación. |
| `resources/js/Components/ConfirmarPagoModal.vue` | `136-181` | Render de `display_data` y acciones. |

### 2.3 Base de datos y rutas

| Archivo | Zona | Rol |
|---|---|---|
| `database/migrations/2026_06_14_000005_create_payment_method_configurations_table.php` | `13-24` | Tabla PMC, columnas nullable y unique. |
| `database/migrations/2026_06_14_000004_create_payments_table.php` | `12-36` | Tabla de pagos pendientes. |
| `app/Models/PaymentMethodConfiguration.php` | `11-62` | Fillable, casts y resolución de etiquetas. |
| `app/Models/Venta.php` | `50-72` | Etiquetas de venta y de pagos. |
| `app/Models/MovimientoCaja.php` | `11-29` | Movimiento de caja y etiqueta actual. |
| `routes/web.php` | `174-182` | Rutas de ventas, confirmación, anulación y devolución. |
| `routes/web.php` | `362-377` | Rutas de Configuración y PMC bajo `auth` + rol. |

---

## 3. Flujo completo

### 3.1 Configuración

1. El administrador entra a `GET /configuracion`.
2. `ConfiguracionController@index` carga:
   - `paymentMethodConfigs`: todas las filas del comercio, sin filtrar por canal ni por completitud (`ConfiguracionController.php:43-53`).
   - `metodoPagoOptions`: los 7 valores de `MetodoPago::options()` (`ConfiguracionController.php:62`).
3. La UI muestra el listado y un formulario siempre visible, sin modal. El título cambia entre “Nuevo” y “Editar” (`Configuracion/Index.vue:604-605`).
4. El formulario arranca con `metodo_pago: 'TRANSFERENCIA'`, `provider: ''`, `display_data` vacío y `enabled: true` (`Configuracion/Index.vue:84-95`).
5. Al presionar “Editar”, `editPmc()` copia los datos de la fila y setea `editingPmcId` (`Configuracion/Index.vue:99-105`). El `<select>` de método queda deshabilitado (`Configuracion/Index.vue:610`).
6. Al guardar, `guardarPmc()` hace `pmcForm.post()` a `configuracion.metodo-pago.store` y **no envía `editingPmcId`** (`Configuracion/Index.vue:113-121`).
7. El backend valida y hace `updateOrCreate` por `comercio_id + metodo_pago + provider` (`ConfiguracionController.php:200-211`). Fuerza `channel = MANUAL` (`ConfiguracionController.php:207`).

**Consecuencia directa:** el botón “Editar” no actualiza una fila por ID. Actualiza por la terna natural. Si el usuario cambia `provider`, la terna cambia y se crea otra fila; la anterior queda intacta.

### 3.2 POS

1. El cajero entra al POS con un turno abierto.
2. `PosController@index` carga las PMCs `enabled = true` y `channel = MANUAL` (`PosController.php:60-74`).
3. Siempre agrega los métodos base `EFECTIVO`, `DEBITO`, `CREDITO` y `CUENTA_CORRIENTE` (`PosController.php:76-84`).
4. `Terminal.vue` arma `METODOS_DISPONIBLES` con los base y las PMCs manuales. Deduplica por `metodo_pago` y **el método base gana** (`Terminal.vue:114-137`).
5. El POS muestra:
   - Botones fijos de Efectivo y Cuenta Corriente.
   - Dropdown “Transferencias” con todo método que no sea base: `TRANSFERENCIA`, `MERCADO_PAGO`, `VIUMI` (`Terminal.vue:139-143`, `1589-1598`).
   - Dropdown “Tarjetas” con `DEBITO` y `CREDITO` (`Terminal.vue:144-146`, `1613-1622`).
6. El label visible es `provider` si existe; si no, el label del enum (`PosController.php:65-73`).

### 3.3 Cobro

1. El cajero selecciona el método, asigna el monto y presiona “Cobrar”.
2. `finalizarVenta()` envía `POST /ventas` con `pagos[]` que contienen **solo** `metodo_pago` y `monto`; en tarjetas agrega banco, tipo, cuotas y recargo (`Terminal.vue:821-845`).
3. No se envía el ID de la PMC ni `display_data`.
4. `VentaController@store` normaliza el método con `MetodoPago::fromString()` (`VentaController.php:144-148`).
5. Carga las configuraciones habilitadas (`VentaController.php:229-234`).
6. Si algún pago tiene una PMC manual, la venta se crea como `PENDING` (`VentaController.php:241`, `285-293`).
7. En flujo manual:
   - Se descuenta stock y se registran lotes/movimientos (`VentaController.php:327-402`).
   - **No se crea movimiento de caja ni de cuenta corriente** (`VentaController.php:295-325`).
   - Se crea un `Payment` pendiente por cada pago manual (`VentaController.php:404-418`).
8. El backend responde con `flash.display_info`, que incluye método, label, monto, provider, `display_data`, `config_id` y canal (`VentaController.php:461-482`).
9. El POS abre `ConfirmarPagoModal` (`Terminal.vue:859-862`, `1813-1819`).

### 3.4 Confirmación

1. El cajero confirma en el modal.
2. `ConfirmarPagoModal` hace `POST /ventas/{id}/confirmar-pago` sin enviar el ID de la configuración (`ConfirmarPagoModal.vue:37-41`).
3. `VentaController@confirmarPago`:
   - Bloquea la venta y exige que esté `PENDING` (`VentaController.php:590-594`).
   - Vuelve a cargar **solo** configuraciones manuales habilitadas (`VentaController.php:598`).
   - Por cada pago: crea movimiento de caja o incrementa cuenta corriente (`VentaController.php:600-627`).
   - Si encontró configuración, aprueba el `Payment` (`VentaController.php:629-631`).
   - Marca la venta como `COMPLETED` y despacha el ticket (`VentaController.php:634-636`).
4. Si el cajero cancela, `VentaOperacionService@anular` restaura stock y cancela los pagos pendientes que encuentre con la configuración actual (`VentaOperacionService.php:128-136`).

### 3.5 Diagrama textual

```text
Configuración
  POST /configuracion/metodo-pago
    -> payment_method_configurations (channel = manual)
    -> updateOrCreate por comercio + metodo + provider

POS
  GET /pos
    -> PMCs enabled + manual
    -> base 4 + PMCs no base
    -> deduplicación por método (base gana)

Cobro
  POST /ventas
    -> venta PENDING si hay PMC manual
    -> stock descontado
    -> Payment PENDING
    -> sin movimiento de caja / cuenta corriente
    -> flash.display_info

Confirmación
  POST /ventas/{id}/confirmar-pago
    -> movimiento de caja / cuenta corriente
    -> Payment APPROVED si la PMC sigue existiendo
    -> venta COMPLETED
```

---

## 4. Comportamiento del selector y observación “viü” / “Mi”

### 4.1 Selector de Configuración

El `<select>` de “Método de Pago” se alimenta de `metodoPagoOptions`, que es exactamente `MetodoPago::options()` (`ConfiguracionController.php:62`, `MetodoPago.php:60-66`).

Los 7 valores son:

| Value | Label |
|---|---|
| `EFECTIVO` | Efectivo |
| `DEBITO` | Débito |
| `CREDITO` | Crédito |
| `TRANSFERENCIA` | Transferencia |
| `MERCADO_PAGO` | Mercado Pago |
| `CUENTA_CORRIENTE` | Cuenta Corriente |
| `VIUMI` | viüMi |

`viüMi` es **una sola opción** (`MetodoPago.php:24`). Los assets compilados actuales también contienen `viüMi` como una sola cadena. No se encontró ninguna opción separada “viü” ni “Mi” en el enum ni en el `<select>`.

El selector arranca en `TRANSFERENCIA` y queda deshabilitado al editar (`Configuracion/Index.vue:85`, `610`).

### 4.2 Selector del POS

El POS no usa un `<select>` de métodos. Usa botones y dropdowns:

- Efectivo y Cuenta Corriente: botones fijos.
- Transferencias: dropdown con `TRANSFERENCIA`, `MERCADO_PAGO`, `VIUMI` (todos los no base).
- Tarjetas: dropdown con `DEBITO` y `CREDITO`.

La deduplicación es explícita: si una PMC manual existe para un método base, **el método base gana** y la PMC no agrega una opción nueva (`Terminal.vue:128-134`). Pero el backend sí la tiene en cuenta para decidir si la venta queda pendiente.

### 4.3 Explicación más probable de “viü” y “Mi”

La observación no se explica por el `<select>` actual. La explicación más probable es esta:

1. El listado de PMCs usa `provider` como etiqueta visible: `metodo_pago_label = $pmc->provider ?: MetodoPago::from(...)->label()` (`ConfiguracionController.php:43-53`).
2. Si existen dos filas del método `VIUMI`, una con `provider = "viü"` y otra con `provider = "Mi"`, el listado muestra “viü” y “Mi” como dos filas separadas.
3. Esas dos filas pueden nacer del propio flujo de edición: al cambiar `provider`, el `updateOrCreate` crea una fila nueva y no actualiza la anterior (`ConfiguracionController.php:200-211`).
4. El unique de la tabla incluye `provider`, que es nullable (`migration:23`). PostgreSQL permite múltiples filas con el mismo `comercio_id + metodo_pago` y `provider = NULL`. Por eso el esquema no impide varias configuraciones del mismo método.

**Conclusión:** “viü” y “Mi” son compatibles con dos registros con `provider` distinto, no con dos opciones del selector. Si en runtime se confirma que aparecen dentro del `<select>`, hay que revisar el build/asset servido y los datos reales; el código actual no lo reproduce.

### 4.4 Consecuencia de tener varias filas por método

- `ConfiguracionController@index` lista todas.
- `PosController@index` mapea todas, pero `METODOS_DISPONIBLES` se queda con la primera que ve para ese método (`Terminal.vue:128-134`).
- `VentaController@loadAllConfigs` recorre todas y se queda con la última para ese método (`VentaController.php:759-773`).
- `PaymentMethodConfiguration::labelMap` usa `pluck('provider', 'metodo_pago')`, que también se queda con una (`PaymentMethodConfiguration.php:49-62`).

No hay `orderBy` en ninguna de esas consultas. Por lo tanto, con varias filas del mismo método, **la etiqueta que ve el cajero y la configuración que usa el backend pueden no ser la misma**.

---

## 5. Matriz de validaciones por método

### 5.1 Matriz funcional

| Método | ¿Selector Config? | ¿POS sin PMC? | ¿POS con PMC manual? | Flujo backend con PMC | Datos exigidos | Observación |
|---|---|---|---|---|---|---|
| `EFECTIVO` | Sí | Sí, botón base | Sí, pero el base gana | Manual / `PENDING` | Solo `metodo_pago` | La UI no avisa que quedará pendiente. |
| `DEBITO` | Sí | Sí, dropdown Tarjetas | Sí, pero el base gana | Manual / `PENDING` | Solo `metodo_pago` | El POS sigue pidiendo banco/cuotas/recargo; el backend no exige datos de tarjeta. |
| `CREDITO` | Sí | Sí, dropdown Tarjetas | Sí, pero el base gana | Manual / `PENDING` | Solo `metodo_pago` | Igual que Débito. |
| `TRANSFERENCIA` | Sí | No | Sí, dropdown Transferencias | Manual / `PENDING` | Solo `metodo_pago` | `display_data` es opcional; puede quedar vacío. |
| `MERCADO_PAGO` | Sí | No | Sí, dropdown Transferencias | Manual / `PENDING` | Solo `metodo_pago` | `provider = mercadopago` no activa el gateway; solo cambia la etiqueta. |
| `CUENTA_CORRIENTE` | Sí | Sí, botón base | Sí, pero el base gana | Manual / `PENDING` | Solo `metodo_pago` | El POS exige cliente; si hay PMC manual, la deuda no se registra hasta confirmar. |
| `VIUMI` | Sí | No | Sí, dropdown Transferencias | Manual / `PENDING` | Solo `metodo_pago` | Label base `viüMi`; `provider` puede reemplazar la etiqueta. |

**Nota:** “POS sin PMC” significa que el método no se muestra en el POS. Si se envía por POST directo, el backend puede aceptarlo igual porque `pagos.*.metodo_pago` no se valida contra el enum. En ese caso, si no hay PMC manual, la venta se completa como instantánea.

### 5.2 Validaciones que sí existen

En `storePaymentMethodConfig` (`ConfiguracionController.php:183-198`):

- `metodo_pago`: requerido, string, dentro de `MetodoPago::values()`.
- `provider`: nullable, string, máximo 100.
- `display_data`: nullable, array.
- `display_data.alias`: nullable, string, máximo 255.
- `display_data.cvu`: nullable, string, solo dígitos, máximo 255.
- `display_data.cbu`: nullable, string, solo dígitos, máximo 255.
- `display_data.banco`: nullable, string, solo letras/espacios/puntos, máximo 255.
- `display_data.titular`: nullable, string, solo letras/espacios/puntos/comas, máximo 255.
- `enabled`: boolean.

En `VentaController@store` (`VentaController.php:110-124`):

- `pagos.*.metodo_pago`: requerido si hay pagos, string.
- `pagos.*.monto`: requerido si hay pagos, numérico, mínimo 0.
- Datos de tarjeta: banco, tipo, cuotas y recargo con formato.

### 5.3 Validaciones que faltan o son insuficientes

| Punto | Situación actual | Riesgo |
|---|---|---|
| Completitud de Transferencia | `display_data` y todos sus campos son nullable | Se puede guardar una Transferencia sin alias, CVU, CBU, banco ni titular. |
| Completitud de Mercado Pago / Viumi | Igual que Transferencia | El modal puede no mostrar ninguna instrucción. |
| Coherencia método ↔ datos | No hay regla condicional por método | Se pueden cargar datos de banco en Efectivo o dejar Transferencia vacía. |
| `metodo_pago` en ventas | Solo `string`, no `in:` | Un valor desconocido se convierte en `EFECTIVO` por `fromString()` (`MetodoPago.php:52`). |
| Método singular de venta | `required_without:pagos|string` | Mismo problema si se usa el fallback sin `pagos`. |
| `provider` | Texto libre, máximo 100 | No se valida contra `payment_gateways`; un typo no activa nada. |
| Canal | Siempre se fuerza `MANUAL` | No se puede crear un canal API/QR/POINT desde esta UI, y una fila existente de otro canal se convertiría a manual al guardar. |
| Errores del formulario PMC | La UI no renderiza `pmcForm.errors` | Un CVU inválido o un método inválido no muestra feedback visible. |
| Unicidad por método | El unique incluye `provider`, nullable | Se permiten varias configuraciones del mismo método. |
| Orden de selección | Sin `orderBy` | Con varias filas, la elegida puede variar. |

### 5.4 Por qué Transferencia puede quedar vacía

El recorrido concreto es este:

1. El formulario PMC no tiene atributos `required` ni validación condicional (`Configuracion/Index.vue:604-658`).
2. El backend solo exige `metodo_pago`; `display_data` y sus hijos son nullable (`ConfiguracionController.php:183-191`).
3. Al enviar campos vacíos, el middleware estándar de Laravel convierte strings vacíos en `null`. El resultado puede ser un JSON con valores `null` o un `display_data` nulo, pero en ningún caso se rechaza.
4. `PosController@index` no filtra por completitud: muestra la PMC igual (`PosController.php:60-74`).
5. El POS permite seleccionar Transferencia y crear la venta pendiente.
6. `VentaController@store` devuelve `display_info` con `display_data` vacío o nulo (`VentaController.php:461-482`).
7. `ConfirmarPagoModal`:
   - Si `display_data` es `null`, no muestra ningún bloque de datos (`ConfirmarPagoModal.vue:142`).
   - Si `display_data` es un array con valores `null`, itera las claves e imprime filas vacías (`ConfirmarPagoModal.vue:143-146`).
8. El cajero no tiene alias, CVU, CBU, banco ni titular para indicarle al cliente. La venta queda pendiente y el stock ya fue descontado.

**Clasificación:** es un defecto funcional confirmado si la intención es que Transferencia sirva para cobrar. Si la intención es que los datos sean opcionales, entonces falta una decisión explícita y un mensaje claro en el POS. Hoy no hay ni una cosa ni la otra.

---

## 6. Hallazgos y clasificación

### 6.1 Tabla de hallazgos

| ID | Severidad | Clasificación | Hallazgo | Evidencia |
|---|---|---|---|---|
| C-01 | Alta | Defecto confirmado | Editar una PMC no actualiza por ID; cambiar `provider` duplica filas. | `Configuracion/Index.vue:99-121`; `ConfiguracionController.php:200-211` |
| C-02 | Alta | Defecto confirmado | Una PMC manual para un método base cambia el backend a `PENDING`, pero el POS sigue mostrando el método base sin aviso. | `Terminal.vue:128-134`; `VentaController.php:229-241`; `PosController.php:76-84` |
| C-03 | Alta | Defecto confirmado / decisión pendiente | Transferencia puede guardarse vacía y el POS la muestra sin instrucciones. | `ConfiguracionController.php:183-191`; `ConfirmarPagoModal.vue:142-146` |
| C-04 | Alta | Defecto confirmado | Varias PMCs por método: POS y backend pueden elegir filas distintas; el modal no envía el ID elegido. | `Terminal.vue:128-134`; `VentaController.php:759-773`; `ConfirmarPagoModal.vue:149-165` |
| C-05 | Alta | Defecto confirmado | `pagos.*.metodo_pago` no se valida contra el enum; un valor desconocido se convierte en Efectivo. | `VentaController.php:115-117`; `MetodoPago.php:28-52` |
| C-06 | Media | Defecto confirmado | Si la PMC se borra o deshabilita antes de confirmar, la venta se completa y el `Payment` queda pendiente. | `VentaController.php:598-631` |
| C-07 | Media | Defecto confirmado | Las etiquetas históricas de ventas y caja dependen de la PMC actual; no hay snapshot. | `Venta.php:50-72`; `MovimientoCaja.php:25-29`; `CajaDiariaController.php:354-362` |
| C-08 | Media | Defecto confirmado | Los errores de validación del formulario PMC no se muestran. | `Configuracion/Index.vue:604-658`; no hay `pmcForm.errors` |
| C-09 | Media | Defecto confirmado / decisión pendiente | “Pasarela (opcional)” es texto libre y siempre guarda canal manual; no activa el gateway. | `Configuracion/Index.vue:617-618`; `ConfiguracionController.php:207` |
| C-10 | Baja | Defecto confirmado | Onboarding marca “Ajustes Globales” completo con cualquier PMC habilitada, aunque esté vacía. | `OnboardingService.php:208-217` |
| C-11 | Baja | Deuda de testing | No hay tests que referencien `PaymentMethodConfiguration`. | Búsqueda en `tests/` sin resultados |
| C-12 | Media | Posible defecto latente | `loadAllConfigs` usa `provider_config`, propiedad que no existe en el modelo ni en la migración. | `VentaController.php:770-771`; `PaymentMethodConfiguration.php:11-24` |
| C-13 | Media | Posible defecto latente | `confirmarPago` puede completar una venta pendiente de gateway sin aprobación real del gateway. | `VentaController.php:598-634` |
| C-14 | Baja | Defecto confirmado | Hay dos lugares distintos para cargar datos de Transferencia: `comercios.transferencia_*` y `display_data` del PMC. | `Configuracion/Index.vue:497-509`; `ConfiguracionController.php:97-101`; `Configuracion/Index.vue:624-637` |
| C-15 | Baja | Deuda de esquema | El unique con `provider` nullable no impide múltiples filas del mismo método. | `migration:23`; PostgreSQL permite múltiples NULL |
| C-16 | Baja | Borde de validación | `fromString('VIÜMI')` normaliza a `VIMI` y cae en `EFECTIVO`; solo `VIUMI` funciona. | `MetodoPago.php:28-52` |

### 6.2 C-01 — La edición no actualiza por ID

`editPmc()` setea `editingPmcId`, pero `guardarPmc()` nunca lo envía (`Configuracion/Index.vue:99-121`). El backend no recibe un ID y usa `updateOrCreate` por `comercio_id + metodo_pago + provider` (`ConfiguracionController.php:200-211`).

Consecuencias:

- Cambiar solo `display_data` o `enabled` funciona si el método y el provider no cambian.
- Cambiar `provider` crea otra fila y deja la anterior.
- El `<select>` deshabilitado es una regla solo de UI. Un POST directo puede enviar otro `metodo_pago`.
- No hay una ruta de actualización por ID para PMC.

**Clasificación:** defecto confirmado. La UI promete “Editar” y el backend hace “crear o actualizar por clave natural”.

### 6.3 C-02 — Métodos base con PMC manual

El backend decide el flujo con `loadAllConfigs()`: si el método tiene una PMC manual habilitada, la venta es `PENDING` (`VentaController.php:229-241`). El POS, en cambio, deduplica y deja ganar al método base (`Terminal.vue:128-134`). Por eso:

- El cajero ve “Efectivo”, “Débito”, “Crédito” o “Cuenta Corriente” como siempre.
- No ve ninguna marca de “confirmación manual”.
- Al cobrar, la venta queda pendiente, el stock se descuenta y la caja no se mueve hasta confirmar.

Esto es especialmente riesgoso en Efectivo: el cajero puede recibir el dinero, cerrar el modal y dejar la venta pendiente sin movimiento de caja.

**Clasificación:** defecto confirmado de coherencia UI/backend. La regla de qué métodos pueden ser manuales no está definida.

### 6.4 C-03 — Transferencia vacía

Ya explicado en 5.4. El punto central es que el sistema acepta una configuración incompleta y la trata como válida en el POS. No hay validación condicional por método ni advertencia en la UI.

**Clasificación:** defecto confirmado si Transferencia debe servir para cobrar; decisión pendiente si los datos son opcionales. En cualquier caso, falta un mensaje explícito en el POS y en el modal.

### 6.5 C-04 — Varias configuraciones por método

El esquema permite varias filas por método porque el unique incluye `provider` y `provider` es nullable (`migration:23`). El flujo de edición puede crearlas (C-01). Luego:

- El POS muestra una etiqueta (primera fila que ve).
- El backend usa otra (última fila que recorre).
- `labelMap` usa otra (la que devuelva `pluck`).
- El modal agrupa por método y, si hay varias configuraciones, muestra “Seleccioná el método que usó el cliente”, pero cada tarjeta llama a `confirmarPago()` sin enviar el ID (`ConfirmarPagoModal.vue:149-165`).
- El backend confirma la venta completa y no sabe qué configuración eligió el cajero (`VentaController.php:600-632`).

**Clasificación:** defecto confirmado. La selección múltiple del modal es decorativa.

### 6.6 C-05 — Validación laxa del método de pago

`pagos.*.metodo_pago` solo se valida como `string` (`VentaController.php:115-117`). Después, `MetodoPago::fromString()` normaliza y, si no reconoce el valor, devuelve `EFECTIVO` (`MetodoPago.php:28-52`).

Ejemplos:

- `metodo_pago = "FOO"` → se guarda como `EFECTIVO`.
- `metodo_pago = "TRANSFERENCIA"` sin PMC → se guarda como transferencia instantánea, sin confirmación.
- `metodo_pago = "VIÜMI"` → se normaliza a `VIMI` y cae en `EFECTIVO`.

**Clasificación:** defecto confirmado. Es una validación de contrato, no una regla de negocio discutible.

### 6.7 C-06 — Confirmación con configuración eliminada o deshabilitada

`confirmarPago` vuelve a cargar solo PMCs manuales habilitadas (`VentaController.php:598`). Si la PMC fue borrada o deshabilitada después de crear la venta:

- El bucle igual crea movimiento de caja o cuenta corriente (`VentaController.php:600-627`).
- No encuentra `$config`, así que no aprueba el `Payment` (`VentaController.php:629-631`).
- La venta se marca `COMPLETED` (`VentaController.php:634`).

Resultado: venta completada, caja movida y `Payment` pendiente huérfano. Lo mismo puede pasar en la anulación: `VentaOperacionService` solo cancela el pago si encuentra la configuración actual (`VentaOperacionService.php:128-136`).

**Clasificación:** defecto confirmado de integridad.

### 6.8 C-07 — Etiquetas históricas mutables

La venta guarda `pagos` con `metodo_pago` y `monto`, pero no guarda `provider`, label ni `display_data` (`VentaController.php:144-168`; `Venta.php:27-31`). La etiqueta se resuelve en tiempo de lectura con `PaymentMethodConfiguration::resolveDisplayLabel()` (`Venta.php:50-72`; `PaymentMethodConfiguration.php:36-47`).

Lo mismo pasa en caja: `MovimientoCaja` guarda `metodo_pago`, y la etiqueta se resuelve con `labelMap` actual (`MovimientoCaja.php:25-29`; `CajaDiariaController.php:354-362`).

Consecuencias:

- Si se cambia el `provider` de “viü” a “Mi”, las ventas y movimientos viejos pasan a mostrarse como “Mi”.
- Si se deshabilita o elimina la PMC, las ventas viejas vuelven al label base.
- El dato subyacente (`metodo_pago`) no cambia, pero la lectura histórica sí.

**Clasificación:** defecto confirmado de trazabilidad. No se modifica la venta, pero se modifica su presentación y la de la caja.

### 6.9 C-08 — Errores PMC invisibles

El formulario PMC no renderiza `pmcForm.errors` en ningún lugar. La búsqueda de `pmcForm.errors` no devuelve resultados. El `onError` genérico de la página pertenece al formulario principal (`Configuracion/Index.vue:242-257`) y solo busca campos de `camposPorTab`; no incluye campos PMC.

Consecuencias:

- Un CVU con letras, un banco con números o un método inválido no muestran feedback.
- El usuario ve que “no pasó nada” y puede volver a guardar.
- El backend sí devuelve errores, pero la UI no los pinta.

**Clasificación:** defecto confirmado de UX.

### 6.10 C-09 — “Pasarela” no es una pasarela

El campo “Pasarela (opcional)” es texto libre (`Configuracion/Index.vue:617-618`). El backend siempre guarda `channel = MANUAL` (`ConfiguracionController.php:207`). No hay vínculo con `payment_gateways` ni validación contra proveedores conocidos.

Por lo tanto:

- `provider = mercadopago` no activa Mercado Pago; solo cambia la etiqueta y el `provider` del pago manual.
- `provider = viumi` no activa Viumi.
- Un typo como `mercadopgo` se guarda como etiqueta.
- Si existiera una fila con canal API/QR/POINT, el formulario la listaría y al guardar la convertiría en manual.

**Clasificación:** defecto confirmado de semántica/UX. El nombre del campo promete algo que no hace.

### 6.11 C-10 — Onboarding optimista

`OnboardingService` marca “Ajustes Globales” como completo si existe cualquier PMC habilitada, sin mirar si tiene datos (`OnboardingService.php:208-217`). Una Transferencia vacía cuenta como ajuste completo.

**Clasificación:** defecto confirmado de baja severidad.

### 6.12 C-11 — Sin tests

No hay referencias a `PaymentMethodConfiguration` en `tests/`. No hay cobertura de:

- Alta y edición de PMC.
- Validaciones de `display_data`.
- Duplicados por método/provider.
- Flujo manual del POS.
- Confirmación con PMC eliminada.
- Cross-tenant en `destroy`.

**Clasificación:** deuda de testing.

### 6.13 C-12 y C-13 — Deuda latente del flujo gateway

`loadAllConfigs` accede a `$cfg->provider_config['user_id']` y `['store_id']` (`VentaController.php:770-771`), pero `provider_config` no existe en el fillable, casts ni migración (`PaymentMethodConfiguration.php:11-24`; `migration:13-24`). En la práctica devuelve `null` y puede emitir un warning.

Además, `ConfirmarPagoModal` se usa también para pagos pendientes de gateway, pero `confirmarPago` solo carga configuraciones manuales (`VentaController.php:598`). Si una venta queda pendiente por gateway, el botón “Confirmar” puede completarla sin aprobación real del gateway.

Hoy la UI de Configuración siempre crea canal manual, así que este flujo no se activa desde la pantalla. Es un riesgo latente si se reutiliza la tabla o se agregan canales electrónicos.

**Clasificación:** posible defecto latente. No se puede cerrar como bug actual sin un caso de uso que cree PMCs gateway.

### 6.14 C-14 — Dos fuentes para Transferencia

Hay dos formularios distintos:

- “Tienda Online y Pagos” → `comercios.transferencia_cbu`, `transferencia_alias`, `transferencia_titular` (`Configuracion/Index.vue:497-509`; `ConfiguracionController.php:97-101`).
- “POS - Medios de Pago” → `payment_method_configurations.display_data` (`Configuracion/Index.vue:624-637`; `ConfiguracionController.php:200-211`).

Completar uno no completa el otro. El POS usa el segundo; la tienda usa el primero (y, en el relevamiento, no se encontró un consumo claro de `comercios.transferencia_*` en el checkout web). Esto explica una parte importante de la confusión de “Transferencia vacía”.

**Clasificación:** defecto confirmado de UX/arquitectura de datos. No es un problema de seguridad, pero sí de operación.

### 6.15 C-15 y C-16 — Esquema y borde de normalización

- El unique `comercio_id + metodo_pago + provider` con `provider` nullable permite múltiples filas del mismo método con `provider = NULL` en PostgreSQL (`migration:23`).
- No hay check constraints para `metodo_pago` ni `channel`.
- `MetodoPago::fromString('VIÜMI')` normaliza a `VIMI` y cae en `EFECTIVO`; solo `VIUMI` funciona (`MetodoPago.php:28-52`).

**Clasificación:** deuda de esquema / borde de validación.

---

## 7. Reglas existentes vs. recomendaciones

### 7.1 Reglas existentes verificadas

Son reglas del sistema tal como está implementado:

1. Solo se exige `metodo_pago` para crear una PMC; `display_data` es opcional (`ConfiguracionController.php:183-191`).
2. El canal guardado desde Configuración es siempre `MANUAL` (`ConfiguracionController.php:207`).
3. El POS siempre muestra los 4 métodos base (`PosController.php:76-84`).
4. Los métodos no base solo aparecen si tienen una PMC manual habilitada (`PosController.php:60-74`).
5. La deduplicación del POS prioriza el método base (`Terminal.vue:128-134`).
6. Una PMC manual habilitada hace que la venta quede `PENDING` (`VentaController.php:229-241`).
7. En flujo manual, el stock se descuenta al crear la venta y la caja/cuenta corriente se mueve recién al confirmar (`VentaController.php:285-325`, `404-418`, `600-636`).
8. La confirmación no envía el ID de la configuración (`ConfirmarPagoModal.vue:37-41`).
9. Las etiquetas históricas se resuelven con la configuración actual (`Venta.php:50-72`; `MovimientoCaja.php:25-29`).
10. Las rutas PMC están bajo `auth` + `role:SuperAdmin|Administrador Global` (`routes/web.php:362-377`).
11. `destroyPaymentMethodConfig` valida `comercio_id` y responde 403 cross-tenant (`ConfiguracionController.php:216-227`).

### 7.2 Recomendaciones (no implementadas)

1. **Definir la identidad de una PMC.** O una configuración por método, o varias con una clave explícita y una etiqueta separada del `provider`.
2. **Actualizar por ID o prohibir el cambio de identidad.** Si se edita, enviar `id` y validar que pertenezca al comercio. Si no se envía `id`, no permitir cambiar `metodo_pago` ni `provider`.
3. **Exigir completitud mínima para Transferencia.** Al menos uno de alias/CVU/CBU, o un mensaje explícito de “sin datos” en el POS. Es una decisión de producto, no una regla existente.
4. **Definir qué métodos pueden ser manuales.** Si los base no pueden serlo, bloquearlos en la UI y en el backend. Si pueden, mostrar “requiere confirmación” en el POS.
5. **Validar `metodo_pago` contra el enum en ventas.** Tanto en `pagos.*.metodo_pago` como en el campo singular.
6. **Validar `provider` o renombrar el campo.** Si es una pasarela, validar contra `payment_gateways`. Si es una etiqueta, llamarlo “Etiqueta” y no prometer integración.
7. **Mostrar errores PMC en la UI.** Agregar mensajes por campo y un toast de error.
8. **Enviar el ID de configuración al confirmar.** O eliminar la selección múltiple si el backend no la puede respetar.
9. **Guardar snapshot de la configuración usada.** Provider, label y `display_data` al momento de la venta, para que la caja y las ventas históricas no cambien.
10. **Hacer robusta la confirmación/anulación ante PMC eliminadas.** Si no se encuentra la configuración original, bloquear con error o usar el snapshot; no completar silenciosamente.
11. **Corregir el flujo gateway.** `provider_config` no existe; además `confirmarPago` no debería completar una venta gateway sin aprobación del gateway.
12. **Agregar tests de feature.** CRUD, validaciones, duplicados, flujo manual, confirmación, anulación, cross-tenant y etiquetas históricas.

---

## 8. Propuesta priorizada

### P0 — Contención y contrato mínimo

Objetivo: evitar que se sigan creando configuraciones ambiguas o ventas pendientes inesperadas.

1. Validar `metodo_pago` contra `MetodoPago::values()` en `VentaController@store` (singular y `pagos.*`).
2. Mostrar `pmcForm.errors` en la UI.
3. Bloquear o advertir PMCs para métodos base.
4. Exigir al menos un dato de Transferencia o mostrar un mensaje explícito en el POS.
5. Agregar `orderBy` determinístico en las consultas de PMC.

**Archivos:** `VentaController.php`, `Configuracion/Index.vue`, `ConfiguracionController.php`, `PosController.php`, `Terminal.vue`.

### P1 — Identidad y unicidad de la configuración

Objetivo: que “Editar” edite de verdad y que no haya dos configuraciones compitiendo por el mismo método.

1. Enviar `id` al editar y crear una ruta `PUT/PATCH` por ID, o mantener el `POST` pero con `id` validado.
2. Prohibir el cambio de `metodo_pago` y `provider` en una edición, o tratarlo como “crear nueva y eliminar la anterior” con confirmación.
3. Definir si se permite más de una PMC por método. Si no, agregar una restricción única por `comercio_id + metodo_pago` (migración aparte, con revisión de datos existentes).
4. Validar `provider` contra proveedores conocidos o renombrar el campo.

**Archivos:** `ConfiguracionController.php`, `Configuracion/Index.vue`, `PaymentMethodConfiguration.php`, migración nueva si se aprueba.

### P2 — Trazabilidad y confirmación robusta

Objetivo: que una venta vieja no cambie de etiqueta y que la confirmación no deje pagos huérfanos.

1. Guardar snapshot de provider/label/display_data en la venta o en `payments`.
2. Usar el snapshot para ventas y caja históricas.
3. Enviar `config_id` al confirmar o eliminar la selección múltiple.
4. Si la configuración original no existe, bloquear la confirmación con un error claro.
5. Revisar `VentaOperacionService@anular` para cancelar por snapshot y no por configuración actual.

**Archivos:** `VentaController.php`, `Venta.php`, `MovimientoCaja.php`, `CajaDiariaController.php`, `ConfirmarPagoModal.vue`, `VentaOperacionService.php`, migración nueva si se agregan campos.

### P3 — Gateway

Objetivo: separar el flujo manual del electrónico.

1. Corregir `provider_config` o eliminarlo y usar la configuración real del gateway.
2. No permitir que `confirmarPago` complete ventas gateway sin aprobación.
3. Definir el ciclo de vida de `Payment` gateway: pending → approved/rejected por webhook.

**Archivos:** `VentaController.php`, `PaymentMethodConfiguration.php`, `PaymentService.php`, `PaymentRecorder.php`.

### P4 — Tests y documentación

1. Tests de feature para PMC y flujo manual.
2. Tests cross-tenant.
3. Documentar en `AGENTS.md` o en un informe la semántica de `provider`, `display_data` y `channel`.
4. Documentar que los datos de Transferencia de la tienda y del POS son distintos, o unificarlos.

---

## 9. Casos de prueba propuestos

| ID | Caso | Pasos | Resultado esperado | Resultado actual probable |
|---|---|---|---|---|
| CP-01 | Transferencia vacía | Crear PMC `TRANSFERENCIA` sin datos; abrir POS; cobrar. | Rechazo al guardar o advertencia clara en POS/modal. | Se guarda, se muestra y el modal no tiene datos. |
| CP-02 | Transferencia completa | Crear PMC con alias/CVU; cobrar; confirmar. | POS muestra datos; venta pendiente; al confirmar se mueve caja. | Funciona el caso feliz. |
| CP-03 | Editar provider | Editar una PMC y cambiar `provider`; guardar. | Se actualiza la misma fila o se pide confirmación. | Se crea otra fila; la original queda. |
| CP-04 | POST directo con método inválido | Enviar `pagos[0].metodo_pago = FOO`. | 422 de validación. | Se guarda como `EFECTIVO`. |
| CP-05 | PMC para Efectivo | Crear PMC manual para `EFECTIVO`; cobrar en POS. | O no se permite, o el POS avisa “requiere confirmación”. | El POS muestra Efectivo normal; la venta queda pendiente. |
| CP-06 | Dos PMCs para Transferencia | Crear dos filas con providers distintos; cobrar. | Una sola configuración efectiva o selección determinística. | POS y backend pueden usar filas distintas. |
| CP-07 | PMC eliminada antes de confirmar | Crear venta pendiente; borrar PMC; confirmar. | Error claro o uso de snapshot; sin pago huérfano. | Venta completada, caja movida, `Payment` pendiente. |
| CP-08 | PMC deshabilitada antes de anular | Crear venta pendiente; deshabilitar PMC; anular. | Pago cancelado o error consistente. | El pago pendiente puede quedar sin cancelar. |
| CP-09 | Etiqueta histórica | Vender con provider “viü”; cambiar a “Mi”; ver venta/caja. | La venta vieja sigue mostrando “viü”. | Muestra “Mi” o el label base. |
| CP-10 | Error de CVU | Ingresar CVU con letras; guardar. | Mensaje inline visible. | El error no se muestra. |
| CP-11 | Cross-tenant destroy | Intentar borrar una PMC de otro comercio. | 403. | 403 (correcto). |
| CP-12 | Onboarding con PMC vacía | Crear PMC vacía; ver onboarding. | “Ajustes Globales” incompleto o advertencia. | Se marca completo. |
| CP-13 | Venta gateway pendiente | Simular PMC gateway; confirmar desde el modal. | No completar sin aprobación del gateway. | Puede completarse y mover caja. |
| CP-14 | Selector `viüMi` | Abrir el `<select>` de Configuración. | Una sola opción `viüMi`. | Una sola opción según código y assets. |

---

## 10. Archivos a modificar si se aprueba la implementación

| Archivo | Cambio propuesto | Riesgo |
|---|---|---|
| `app/Http/Controllers/ConfiguracionController.php` | Validación condicional, edición por ID, no forzar canal en filas existentes, validar provider. | Alto: cambia el contrato de guardado. |
| `resources/js/Pages/Configuracion/Index.vue` | Enviar `id`, errores PMC, campos requeridos condicionales, advertencias. | Medio: puede romper el guardado actual. |
| `app/Http/Controllers/PosController.php` | Filtrar PMCs incompletas, orden determinístico, marcar manuales. | Medio: puede ocultar métodos que hoy se usan. |
| `resources/js/Pages/Pos/Terminal.vue` | Indicador de confirmación manual, envío de `config_id`, manejo de métodos base. | Alto: toca el cobro. |
| `app/Http/Controllers/VentaController.php` | Validar enum, confirmar por configuración, manejar PMC ausente, gateway. | Alto: toca ventas, caja y pagos. |
| `resources/js/Components/ConfirmarPagoModal.vue` | Filtrar nulls, mensaje “sin datos”, enviar `config_id`. | Medio: cambia la confirmación. |
| `app/Services/VentaOperacionService.php` | Anulación robusta ante PMC eliminada, snapshot. | Alto: toca stock, caja y fiados. |
| `app/Models/PaymentMethodConfiguration.php` | Resolución de etiquetas, scopes, snapshot. | Medio. |
| `app/Models/Venta.php` y `app/Models/MovimientoCaja.php` | Usar snapshot para etiquetas históricas. | Medio: cambia visualización histórica. |
| `app/Services/OnboardingService.php` | Completitud real de ajustes. | Bajo. |
| `database/migrations/...` | Unique por método, columnas de snapshot, check constraints. | Alto: requiere revisar datos existentes. |
| `tests/Feature/...` | Nuevos tests de PMC, POS manual, confirmación, anulación y cross-tenant. | Bajo. |

---

## 11. Riesgos de regresión

1. **POS y cobro:** cambiar la detección de flujo manual puede dejar ventas instantáneas que deberían ser pendientes, o al revés. Probar pagos combinados (Transferencia + Efectivo).
2. **Caja:** la confirmación crea movimientos de caja. Si se cambia el flujo, evitar doble movimiento o movimientos faltantes. Probar venta pendiente, confirmada y anulada.
3. **Stock:** el stock se descuenta al crear la venta, antes de confirmar. Cualquier cambio debe preservar la restauración por lote y por `producto_sucursal`.
4. **Fiados:** Cuenta Corriente en flujo manual no registra deuda hasta confirmar. Si se cambia, evitar cargar dos veces o no cargar.
5. **Multi-tenant:** todas las consultas nuevas deben filtrar por `comercio_id`; `destroy` ya lo hace. Agregar tests cross-tenant.
6. **Etiquetas históricas:** un snapshot cambia la visualización de ventas y caja viejas. Definir si se backfillea o se usa solo para nuevas.
7. **Tienda:** `comercios.transferencia_*` y `display_data` son fuentes distintas. No unificar sin revisar checkout web.
8. **Onboarding:** cambiar la completitud puede hacer retroceder el progreso de comercios existentes.
9. **Build:** cambios en Vue requieren `npm run build`. No se agregaron dependencias en esta auditoría.
10. **Datos existentes:** puede haber filas duplicadas o PMCs vacías. Cualquier migración de unicidad debe auditar y resolver duplicados antes de aplicar.

---

## 12. Verificaciones ejecutadas y resultados

| Verificación | Método | Resultado |
|---|---|---|
| Referencias a `PaymentMethodConfiguration` en tests | `rg` sobre `tests/` | Sin referencias. No hay cobertura. |
| Label `viüMi` | Revisión de `MetodoPago.php` y assets compilados | Una sola cadena `viüMi`; no se encontraron dos opciones. |
| `provider_config` | Búsqueda en modelo, migración y controlador | No existe en el modelo ni en la migración; solo se usa en `loadAllConfigs`. |
| Rutas PMC | Revisión de `routes/web.php` | Bajo `auth` + `role:SuperAdmin|Administrador Global`. |
| `destroy` cross-tenant | Revisión de `ConfiguracionController` | Valida `comercio_id` y responde 403. |
| Estado del repo | `git status --porcelain=v1` | Había cambios previos en varios archivos; no se revirtieron ni se modificaron durante esta auditoría. |
| Pruebas automatizadas | No ejecutadas | No se corrió PHPUnit ni la app; la auditoría fue estática. |
| Base de datos | No consultada | No se ejecutaron lecturas ni escrituras. |
| Operaciones de negocio | No ejecutadas | No se crearon ventas, pagos, arqueos ni anulaciones. |

**Limitaciones:**

- No hay app levantada con autenticación y datos para probar el flujo completo en runtime.
- No se puede confirmar el valor real almacenado en `display_data` sin una consulta de lectura.
- No se puede confirmar si “viü” y “Mi” se vieron en el listado o en el `<select>` sin una captura o reproducción en runtime.
- Los assets compilados pueden estar desactualizados respecto del código fuente.

---

## 13. Criterios de aceptación del plan

La implementación posterior se considerará correcta si:

1. Una PMC se puede editar sin duplicar filas.
2. No se pueden crear dos configuraciones ambiguas para el mismo método, o la selección es determinística y visible.
3. Transferencia no se puede guardar vacía, o el POS muestra una advertencia clara y el modal explica que no hay datos.
4. Los métodos base no cambian de flujo sin un aviso explícito en el POS.
5. `metodo_pago` se valida contra el enum en todas las entradas de venta.
6. Los errores de validación PMC se ven en la UI.
7. Confirmar y anular funcionan aunque la PMC haya sido eliminada o deshabilitada.
8. Las ventas y movimientos históricos conservan la etiqueta con la que se cobraron.
9. El flujo gateway no se puede completar sin aprobación del gateway.
10. Hay tests de feature y cross-tenant.
11. No se rompen stock, caja, fiados, POS ni onboarding.
12. `npm run build` y la suite de PHPUnit siguen pasando.

---

## 14. Conclusión

El módulo está bien encaminado: la separación entre configuración manual, venta pendiente y confirmación es clara y el caso feliz funciona. El problema es que la configuración no tiene un contrato estricto y el POS no siempre refleja lo que el backend va a hacer.

Los tres cierres más importantes antes de seguir son:

1. **Identidad de la PMC:** editar por ID o prohibir cambios de identidad.
2. **Completitud y coherencia:** Transferencia no puede quedar vacía sin advertencia; los métodos base no pueden cambiar de flujo sin aviso.
3. **Trazabilidad:** la venta y la caja deben conservar la etiqueta y los datos con los que se cobraron.

Con esos tres puntos resueltos, el resto son mejoras de validación, UX y tests.

---

## Anexo A — Evidencia principal

- Selector de 7 opciones: `app/Enums/MetodoPago.php:5-25`, `55-66`; `ConfiguracionController.php:62`.
- Edición sin ID: `resources/js/Pages/Configuracion/Index.vue:99-121`; `app/Http/Controllers/ConfiguracionController.php:200-211`.
- Validación laxa PMC: `app/Http/Controllers/ConfiguracionController.php:183-198`.
- POS base + manual: `app/Http/Controllers/PosController.php:60-84`; `resources/js/Pages/Pos/Terminal.vue:114-143`.
- Payload sin PMC: `resources/js/Pages/Pos/Terminal.vue:821-845`.
- Flujo manual y pendiente: `app/Http/Controllers/VentaController.php:229-241`, `285-325`, `404-418`, `461-482`.
- Confirmación: `app/Http/Controllers/VentaController.php:576-648`; `resources/js/Components/ConfirmarPagoModal.vue:37-41`.
- Anulación: `app/Services/VentaOperacionService.php:97-136`, `283-304`.
- Etiquetas históricas: `app/Models/Venta.php:50-72`; `app/Models/MovimientoCaja.php:25-29`; `app/Http/Controllers/CajaDiariaController.php:354-362`.
- Esquema: `database/migrations/2026_06_14_000005_create_payment_method_configurations_table.php:13-24`.
- Rutas y permisos: `routes/web.php:362-377`.
- Onboarding: `app/Services/OnboardingService.php:208-217`.

## Anexo B — Búsquedas de control sugeridas para el BUILD

```bash
# No debe quedar un POST de PMC sin id cuando se edita
rg -n "editingPmcId|metodo-pago.store|pmcForm.post" resources/js/Pages/Configuracion/Index.vue

# Debe existir validación contra el enum en ventas
rg -n "pagos\.\*\.metodo_pago|MetodoPago::values|MetodoPago::fromString" app/Http/Controllers/VentaController.php

# No debe quedar provider_config inexistente
rg -n "provider_config" app

# Debe haber tests de PMC
rg -n "PaymentMethodConfiguration|payment_method_configurations" tests

# Debe haber feedback de errores PMC
rg -n "pmcForm.errors|pmcForm.hasErrors" resources/js/Pages/Configuracion/Index.vue
```

## Anexo C — Archivos creados y modificados

- **Creado:** `docs/informes/PLAN-AUDITORIA-MEDIOS-PAGO-POS.md`.
- **Modificados:** ninguno.
- **Migraciones:** ninguna.
- **Base de datos:** ninguna.
- **Dependencias:** ninguna.
- **Operaciones de negocio:** ninguna.
- **Cambios previos del working tree:** no se revirtieron ni se tocaron.
