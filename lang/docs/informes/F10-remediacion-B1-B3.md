# F10 — Remediación B1 / B2 / B3

> **Tipo**: Implementación de los bloqueantes confirmados por la auditoría F10 y su refutación.
> **Fecha**: 2026-08-06
> **Alcance**: únicamente B1, B2 y B3 (orden autorizado). Nada de A1–A5, sin refactors.
> **Manuales citados**: `docs/arca/WSAAmanualDev.pdf`, `docs/arca/manual-desarrollador-ARCA-COMPG-v4-0.pdf`, `docs/arca/wsfev1-RG-4291 - Para wsfev1 - R.G. N° 4.291.pdf`.

---

## Re-verificación previa (descartar falsos positivos)

Antes de implementar, cada bloqueante fue releído contra código y documentación oficial:

| ID | Estado re-verificado | Evidencia |
|----|----------------------|-----------|
| B1 | ✅ Confirmado | WSAAmanualDev usa `generationTime` en todos los ejemplos (request y response) y la sección 10.10 exige respetar el nombre del elemento. Código emitía `<generatedAt>`. |
| B2 | ❌ Falso positivo (sin cambio de código) | RG 4291 define `A=APROBADO, R=RECHAZADO, P=PARCIAL`; no existe `'O'`. El ejemplo "aprobado con observaciones" responde `<Resultado>A</Resultado>` con CAE. `CaeMapper` ya acepta esos CAE. No hay rechazo de comprobantes válidos. |
| B3 | ✅ Confirmado (precisado) | COMPG validación **10015** (excluyente): para Factura B individual con monto superior al de RG 4444, DocTipo debe ser un valor de `FEParamGetTiposDoc` (≠ 99) y DocNro debe informarse. El error correcto es 10015 (no 10014, que es solo para pedidos múltiples). Umbral $10.000 según RG 4291/RG 4444. |

Conclusión: se implementa B1 y B3. B2 no requiere código porque el supuesto "Resultado 'O' descartado" no existe en la documentación.

---

## B1 — TRA WSAA: `generationTime`

### Qué cambió
- `app/Facturacion/Infrastructure/Arca/Wsaa/WsaaClient.php:121`:
  ```xml
  - <generatedAt>{$generado->format('c')}</generatedAt>
  + <generationTime>{$generado->format('c')}</generationTime>
  ```
- `tests/Support/FacturacionArca/RespuestasArca.php`: el XML de respuesta del login (fixture) también usa `generationTime`, igual que la respuesta real según WSAAmanualDev.

### Justificación (documentación oficial)
- WSAAmanualDev §5.1 y ejemplos: el TRA se construye con `$TRA->header->addChild('generationTime', ...)` y `<generationTime>` figura en request y response.
- Sección 10.9: "GenerationTime en el futuro o más de 24 horas de antigüedad".
- Sección 10.10: "No se ha podido interpretar el XML contra el schema"; "las mayúsculas y minúsculas del XML deben respetarse" y el formato de `generationTime`/`expirationTime`.
- El schema de WSAA es case-sensitive: `generatedAt` inválido ⇒ rechazo del login antes de la autenticación.

### Tests
- `tests/Unit/FacturacionArca/WsaaClientTest.php` → nuevo `test_tra_del_login_usa_generation_time_segun_el_schema_de_wsaa`: invoca `mensajeLoginTicket` (vía ReflectionMethod) y verifica `<generationTime>...`, ausencia de `generatedAt`, `<service>wsfe</service>` y `<expirationTime>`.

### Resultado
- `tests/Unit/FacturacionArca/WsaaClientTest.php`: **7 tests OK** (1 nuevo, 14 assertions). Deprecaciones: 4, preexistentes de Laravel/PHP 8.5 (PDO constants), ajenas al cambio.

---

## B2 — Resultado 'O': falso positivo, sin cambios

### Qué se verificó
- El informe original sostenía que `CaeMapper.php:20` (`if ($resultado !== 'A')`) descartaría comprobantes "aprobados con observaciones" (presunto `Resultado='O'`).
- La documentación oficial no define `'O'`:
  - RG 4291 (bloque FeCabResp/FeDetResp): "A=APROBADO, R=RECHAZADO, P=PARCIAL".
  - RG 4291, ejemplo de "Aprobación del comprobante con Observaciones": `<Resultado>A</Resultado>` + `<Observaciones><Obs><Code>724</Code>...</Obs></Observaciones>`.
  - COMPG §3.3: "las validaciones no excluyentes aprueban la solicitud pero con observaciones" ⇒ Resultado sigue siendo `'A'` y se asigna CAE.
- `CaeMapper` acepta el CAE correctamente. No hay hueco de numeración ni CAE huérfano.

### Acción
- **Ninguna modificación de código.** Se descarta el bloqueante tal como fue formulado. El único matiz (no exponer `<Observaciones>` de vuelta al operador) queda registrado como mejora opcional (A7) y NO es un impedimento para emitir.

---

## B3 — Umbral RG 4444: identificación del receptor en Factura B

### Qué cambió
- `app/Facturacion/Infrastructure/Arca/Wsfe/FECAERequestBuilder.php`:
  - Constructor: `__construct(int $tipoDocConsumidorFinal = 96, float $montoMaximoB = 10000.0)` — umbral parametrizable.
  - Nuevo método `validarFacturaBSobreUmbral(ComprobanteFiscal, float $impTotal)` invocado en `construir()` antes de armar el detalle: si el comprobante es clase **B**, el total supera `montoMaximoB` y no hay CUIT de receptor, lanza `InvalidArgumentException` con mensaje que cita RG 4291/4444.

### Justificación (documentación oficial)
- COMPG v4, tabla de validaciones excluyentes, bloque `<CbteTipo>/<DocTipo>/<DocNro>`, código **10015**:
  - "Si el campo DocTipo es distinto de 80, 86 u 87, deberá verificarse que se ingrese uno de los valores devueltos por el método FEParamGetTiposDoc y que se informe el campo DocNro."
  - "Para pedidos individuales (CbteDesde igual a CbteHasta) tipo B con montos superiores a monto en pesos resultante según RG4444 el campo DocTipo deberá ser igual a algunos de los valores devueltos por el método FEParamGetTiposDoc excepto 99 y deberá informar el campo DocNro."
- Historial del manual (versión 2.16/2.17, 01/11/2019 y 26/02/2020): "Se modifican las validaciones ... 10014, 10015 con el fin de dar soporte a todos los comprobantes B > a $10000 según Resolución General 4444/2019. La validación de $10000, será la resultante de importe total * cotización. En caso de mon_id = PES la cotización siempre es =1."
- El código mandaba siempre `DocTipo=96, DocNro=0` (consumidor final sin identificación): sobre el umbral, ARCA rechaza con 10015 (excluyente). Ahora la emisión falla en firme antes de llegar a ARCA, con un mensaje claro, en lugar de rechazarse.

### Notas
- Se aplica a comprobantes clase B (Factura B y NC B) individuales; el MVP siempre emite comprobantes individuales (CbteDesde == CbteHasta), por lo que 10014 (pedidos múltiples) no aplica.
- Si el receptor tiene CUIT → `DocTipo=80, DocNro=CUIT`, que cumple la validación. Comportamiento bajo el umbral: sin cambios.

### Tests
- `tests/Unit/FacturacionArca/FECAERequestBuilderTest.php`:
  - `test_factura_b_sobre_el_umbral_rg4444_sin_receptor_lanza`
  - `test_factura_b_sobre_el_umbral_rg4444_con_receptor_es_valida`
  - `test_factura_b_sobre_el_umbral_con_umbral_configurable` (parametrización)
  - `test_factura_a_sobre_el_umbral_no_aplica_la_regla_de_clase_b`

### Resultado
- `tests/Unit/FacturacionArca/FECAERequestBuilderTest.php`: **13 tests OK** (4 nuevos, 54 assertions).

---

## Regresiones

### Suites ejecutadas
| Suite | Resultado |
|-------|-----------|
| `tests/Unit/FacturacionArca` | **48 tests OK** (131 assertions) |
| `tests/Feature/Facturacion` + `FacturacionArca` + `Unit/FacturacionDomain` | **195 tests OK** (815 assertions) |
| Suite global (`vendor/bin/phpunit`) | 467 tests: **4 errores + 13 fallos + 1 risky**, todos en módulos ajenos a Facturación |

### Análisis de la suite global
- Los 17 fallos/errores corresponden a `Modulo3_CajaTest`, `Modulo7_TransferenciasTest`, `Stock/PedidoWebStockTest`, `Auth/PasswordResetTest`, `Auth/RegistrationTest`, `CrossTenantAttackTest`, `Modulo1_ProductosTest`, `Modulo4_PedidosWebTest`, `MultiTenantIsolationTest`, `WebhookSeguridadTest`, `StockReservadoTest`.
- Ninguno referencia Facturacion/ARCA/WSAA/FECAERequestBuilder (búsqueda en log sin resultados).
- Son **preexistentes**, en áreas con cambios sin commitear del working tree (`PedidoWebController.php`, `SuscripcionController.php`, `MercadopagoGateway.php`).
- **Sin regresiones** causadas por B1/B2/B3.

---

## Archivos modificados

| Archivo | Cambio |
|---------|--------|
| `app/Facturacion/Infrastructure/Arca/Wsaa/WsaaClient.php` | B1: `<generationTime>` en el TRA |
| `app/Facturacion/Infrastructure/Arca/Wsfe/FECAERequestBuilder.php` | B3: umbral RG 4444 parametrizable + validación 10015 |
| `tests/Unit/FacturacionArca/WsaaClientTest.php` | B1: test del TRA |
| `tests/Unit/FacturacionArca/FECAERequestBuilderTest.php` | B3: 4 tests del umbral |
| `tests/Support/FacturacionArca/RespuestasArca.php` | B1: fixture de respuesta WSAA con `generationTime` |

## Pendiente
- **Esperar aprobación** antes de continuar con B4 → B13.
- B2 queda descartado como bloqueante (falso positivo). El manejo de `<Observaciones>` hacia el operador queda anotado como mejora A7.

---

# Anexo: B9 — Entorno por defecto en homologación

> **Agregado**: 2026-08-06. Tarea autorizada tras la verificación previa de que no existe un camino de emisión a producción por error.

## Verificación previa (read-only)
- El flujo de emisión (ventas `EmisionVentaService` y NC `NcService`) lee el entorno desde `configuracion_fiscal_comercios.entorno` vía `WsfetResolverPorComercio:26` y `ConectividadResolverPorComercio:34`; no hay hardcodes de `EntornoArca::PRODUCCION` en `app/` (solo en tests).
- Jobs y comandos del módulo no emiten a ARCA.
- Los únicos puntos que fijaban `produccion` como default eran: migraciones `2026_08_02_000004` y `2026_08_02_000005`, el seeder `ConfiguracionFiscalComerciosSeeder` y los fallbacks del wizard.
- El wizard ya restringe homologación a rol "Administrador Global" (`HabilitadorHomologacion`) y valida `entorno` con `in:produccion,homologacion`.

Conclusión: **B9 es el mínimo necesario y completo para probar homologación sin riesgo de emitir a producción por error**; ningún camino no verificado quedó fuera.

## Cambios aplicados
| Archivo | Cambio |
|---------|--------|
| `database/migrations/2026_08_02_000004_create_certificados_fiscales_table.php` | default de `entorno` → `homologacion` |
| `database/migrations/2026_08_02_000005_create_configuracion_fiscal_comercios_table.php` | default de `entorno` → `homologacion` |
| `database/seeders/ConfiguracionFiscalComerciosSeeder.php` | `'entorno' => 'homologacion'` |
| `app/Facturacion/Application/WizardConfiguracionService.php` | fallback de `sinDatos` → `homologacion` |
| `app/Http/Controllers/Facturacion/WizardConfiguracionFiscalController.php:193` | `?? 'produccion'` → `?? 'homologacion'` |
| `.env.example` | documentadas las variables `ARCA_WSAA_*`, `ARCA_WSFE_*`, `ARCA_PADRON_*`, `ARCA_SOAP_TIMEOUT` |
| `tests/Feature/Facturacion/ConfiguracionFiscalComercioTest.php` | seeder espera `homologacion`; nuevo test del default en la migración |

## Notas
- La DB de desarrollo y de tests ya estaba migrada con el default anterior; el default de columna solo afecta inserciones que omiten `entorno` (no ocurre en el código: el seeder y el wizard siempre fijan el valor). El nuevo default queda garantizado por las migraciones para despliegues nuevos/`migrate:fresh`.
- El test del seeder valida el comportamiento real de alta (un comercio nuevo vía seeder queda en `homologacion`); el test de la migración captura la regresión del default sin depender del estado de la DB local.

## Resultado
- `tests/Feature/Facturacion/ConfiguracionFiscalComercioTest.php` + `F7_WizardConfiguracionFiscalTest.php` + `F8_DiagnosticoFiscalTest.php`: **30 tests OK**.
- Suite Facturación completa (`tests/Unit/FacturacionArca` + `tests/Feature/Facturacion`): **146 tests OK** (672 assertions). Sin regresiones.
- Deprecaciones: 4, preexistentes (Laravel/PHP 8.5), ajenas a estos cambios.

## Pendiente tras B9
- Aprobación para probar contra **homologación real**. Requiere: certificado PFX de ARCA + alias WSAA, y completar el wizard fiscal en el comercio de prueba.
- Con B9 validado, **B4 → B13** quedan liberados para su ejecución si el usuario lo autoriza.
