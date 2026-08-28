# Informe Técnico — F10.2: Verificación de correcciones de auditoría y UX del campo CUIT

| Campo | Valor |
|---|---|
| Fase | F10.2 (continuación de F10.1 auditoría + F10 pendientes) |
| Alcance | Verificación contra código real de los 5 cambios correctivos de la auditoría fiscal ARCA + mejora de UX del campo CUIT con formato `XX-XXXXXXXX-X` |
| Método | Re-verificación estática de cada punto contra el código + suite de tests del módulo + build Vite |
| Estado | **Terminada** — pendiente prueba real en homologación (requiere Admin Global + Modo Dios) |

## Objetivo

Confirmar que los 5 cambios correctivos de la auditoría fiscal quedaron correctamente aplicados y no
rompieron el flujo de emisión, y mejorar la UX del campo CUIT en el wizard de configuración fiscal
(formato automático `XX-XXXXXXXX-X`, robustez ante pegado/borrado/cursor) sin alterar la validación fiscal.

---

## 1. Verificación de los cambios de la auditoría (F10.1)

### 1.1 Comercio nuevo arranca en homologación — CONFIRMADO

- `app/Facturacion/Application/WizardConfiguracionService.php:228` — `sinDatos()` crea la fila con
  `entorno: 'homologacion'`.
- Migración `2026_08_02_000005_create_configuracion_fiscal_comercios_table.php:18` — default `homologacion`.
- `resources/js/Pages/Facturacion/Wizard.vue` — default de entorno `homologacion` cuando no hay config.

### 1.2 Producción vedada para comercio sin CUIT verificado — CONFIRMADO

- `WizardConfiguracionService.php:58` — `if ($entorno === 'produccion' && ($actual === null || $actual->cuit() === null))`
  lanza `FacturacionDomainException`. Cubre tanto comercios nuevos como filas pre-B9 con `entorno=produccion`
  y `cuit=NULL` (comercios #1 y #2 de la BD real).

### 1.3 Confirmación explícita para verificar en producción — CONFIRMADO

- `Wizard.vue` `verificarCuit()` — si `cuitForm.entorno === 'produccion'` muestra `Swal.fire` de advertencia
  con `confirmButtonColor: '#dc2626'` y solo postea con `result.isConfirmed`.

### 1.4 Aislamiento multi-tenant — CONFIRMADO

- `EloquentConfiguracionFiscalRepository.php` — `buscarPorComercio` siempre filtra `where('comercio_id', ...)`.
- Migración — `comercio_id` único.
- Credencial de plataforma global pero solo para consulta de padrón (invariante 10: nunca emite).
- Certificados fiscales por comercio + entorno.

### 1.5 `configuraciones.cuit` ya no es fuente fiscal — CONFIRMADO

- `sincronizarCuitGlobal()` eliminado del controller; verificado sin restos en `app/`.
- `TicketBuilder.php:18` sigue leyendo `configuraciones.cuit` para el encabezado no fiscal del ticket,
  pero el bloque fiscal usa `ConfiguracionFiscalComercio`. Ya no se pisa desde la verificación fiscal.
- Test `ArcaCredencialTest::test_verificar_cuit_no_toca_el_cuit_global_del_ticket` cubre el caso.

### 1.6 Flash de conexión aislado por comercio — CONFIRMADO

- Clave `facturacion.resultado_conexion.{comercioId}` en `WizardConfiguracionFiscalController.php` y
  `DiagnosticoFiscalController.php`. Tests `F8_DiagnosticoFiscalTest` actualizados.

### 1.7 No se rompió el flujo de emisión — CONFIRMADO

- `F10_E2EFlujoFacturacionTest` (venta → emisión → historial → impresión → email → NC): **65 assertions, 0 fallos**.
- `PosController` sigue consultando `estado_modulo = listo_para_facturar` como puerta de emisión.

---

## 2. Mejora UX del campo CUIT (`Wizard.vue`)

### Cambios implementados

- Input de CUIT ahora formatea automáticamente como `XX-XXXXXXXX-X`:
  - Solo dígitos, máx. 11.
  - Guion automático tras el 2.º y el 10.º dígito.
  - Placeholder `20-12345678-6`.
  - Contador `n/11` con indicador verde al completar.
- Robusterz del input:
  - Escritura char por char.
  - Pegado normalizado: con guiones, puntos, espacios, letras o mezcla → se normaliza a 11 dígitos.
  - Borrado con Backspace/Delete y edición en el medio: el cursor se reposiciona correctamente
    vía `setSelectionRange` tras el re-formateo (`onCuitInput`).
- `cuitForm.cuit` siempre almacena los 11 dígitos puros; al backend se envían solo dígitos.
- El formateo es visual: la validación fiscal (dígito verificador) queda en `esCuitValido` + backend
  (no se alteró la regla de negocio).
- `watch` sobre `props.configuracion?.cuit` re-sincroniza el campo formateado al recargar el wizard.

### Función clave

```js
const soloDigitos = (valor) => (valor || '').replace(/\D/g, '').slice(0, 11);
const formatearCuit = (valor) => {
    const d = soloDigitos(valor);
    if (d.length <= 2) return d;
    if (d.length <= 10) return `${d.slice(0, 2)}-${d.slice(2)}`;
    return `${d.slice(0, 2)}-${d.slice(2, 10)}-${d.slice(10)}`;
};
```

---

## 3. Archivos modificados

| Archivo | Cambio |
|---|---|
| `resources/js/Pages/Facturacion/Wizard.vue` | Formato automático `XX-XXXXXXXX-X`, manejo robusto de cursor/pegado/borrado, contador, placeholder, watch de re-sincronización |
| `tests/Unit/FacturacionDomain/CuitTest.php` | +2 tests de pegado con espacios/mezcla y rechazo de >11 dígitos |

No se crearon archivos nuevos de código en esta fase.

---

## 4. Pruebas ejecutadas

| Suite | Resultado |
|---|---|
| `CuitTest` (Unit) | 10 passed (18 assertions) |
| `tests/Feature/Facturacion` completo | 107 tests, 599 assertions, **0 fallos** (solo deprecations PDO preexistentes) |
| `F10_E2EFlujoFacturacionTest` (aislado) | 65 assertions, **0 fallos** |
| `Modulo2_VentasTest` + `Modulo3_CajaTest` | 3 fallos de Caja **preexistentes** (verificado con `git stash`: fallan igual sin los cambios de esta fase, ya documentados en F10 pendientes) |
| `npm run build` (Vite, host) | OK — 30.62s |

---

## 5. Criterios de aceptación

- [x] CUIT se muestra y edita como `XX-XXXXXXXX-X`.
- [x] Se envían los 11 dígitos al backend (sin guiones).
- [x] Pegado con cualquier formato se normaliza.
- [x] Cursor correcto al escribir, borrar y editar en el medio.
- [x] Comercio nuevo arranca en homologación.
- [x] Producción vedada sin CUIT verificado.
- [x] Confirmación explícita antes de verificar en producción.
- [x] `configuraciones.cuit` ya no es fuente de verdad fiscal.
- [x] Flash de conexión aislado por `comercio_id`.
- [x] Suite de facturación verde; emisión E2E intacta.

---

## 6. Pendientes de la siguiente fase

- Prueba real en homologación (WSAA/WSFE/padrón con credencial real) usando Admin Global
  (`adminvendar@gmail.com` / `admin`) + Modo Dios, porque `HabilitadorHomologacion` restringe la
  homologación al rol `Administrador Global`.
- 3 fallos preexistentes de `Modulo3_CajaTest` (ajenos al módulo): derivar al equipo dueño de Caja
  (403/404 y ValidationException en cierre de turno).
- Evaluar hallazgos MEDIA/BAJA de la auditoría F10.1 (N+1 en repositorio de comprobantes, índices
  para `comprobante_original_id`, `CaePerdidoHandler` sin cablear) en una fase posterior.
