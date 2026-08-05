# Plan Maestro de Implementación — Facturación Electrónica (ARCA)

| Campo | Valor |
|---|---|
| Versión | 1.0 |
| Documento de referencia | `docs/arquitectura-facturacion.md` (v1.0, **CONGELADA**) |
| Estado | **Aprobado para BUILD** |
| Contrato | Este plan es la guía de ejecución del BUILD. No introduce decisiones de arquitectura nuevas: solo ordena y descompone lo ya congelado. |

> Regla: este plan **no modifica** la arquitectura congelada. Cualquier desvío de alcance, orden o componente
> debe tratarse como cambio de arquitectura (nueva versión), no como ajuste silencioso.

---

## 1. Propósito y alcance del plan

Descomponer el módulo de facturación electrónica (Factura A, Factura B, Notas de Crédito; emisores
Responsable Inscripto) en fases ordenadas, con objetivos, entregables, dependencias, criterios de
aceptación, riesgos y pruebas, de modo que:

- se minimice el riesgo sobre los módulos críticos existentes (POS, caja, stock, fiados);
- se mantenga compatibilidad total con el sistema actual durante toda la implementación;
- el núcleo fiscal sea testeable de forma aislada y verificable;
- cada fase tenga un punto de terminación verificable antes de continuar.

**Fuera del alcance de este plan** (ver §21 de la arquitectura): Factura C, Factura M, CAEA, contingencia
avanzada, Libro IVA Digital, exportaciones, WSCDC completo, Nota de Débito, pedidos web, PV por sucursal.

---

## 2. Principios que rigen el BUILD

1. **Núcleo puro primero.** Todo cálculo y regla fiscal vive en `app/Facturacion/Domain` sin dependencias
   de Laravel/Eloquent/SOAP. La lógica crítica se testea primero y en aislamiento.
2. **No romper lo que funciona.** La venta sigue siendo la única responsable de stock y caja
   (invariantes 3, 4, 5). El flujo actual de `store`/`confirmarPago`/`cancelar`/`devolver` se **extiende**,
   nunca se reescribe.
3. **Compatibilidad multi-tenant.** Todo acceso a datos fiscales filtra por `comercio_id`
   (invariante 8). Los comercios sin módulo configurado siguen operando como hoy.
4. **La emisión es obligatoria solo cuando el módulo está activo y `listo_para_facturar`**
   (arquitectura §1.4). Mientras no esté listo, no cambia nada para el comercio.
5. **Integridad transaccional.** El CAE se solicita y persiste dentro de la transacción que completa la
   venta (arquitectura §7.1). No existe venta completada sin comprobante (invariante 1).
6. **La numeración es secuencial, inmutable y protegida por locking e índice único** (arquitectura §18.2).
   La estrategia de cálculo queda a criterio de implementación cumpliendo esas propiedades.
7. **Cada fase termina con pruebas verdes y una revisión** antes de la siguiente.

---

## 3. Mapa general de fases y dependencias

```
 F0 Núcleo de dominio (puro, testeable)
   │
   ▼
 F1 Persistencia y modelos          F4 Correcciones de base del sistema actual
   │                                    │
   ▼                                    ▼
 F2 Adapters ARCA (SOAP) ──────►  F3 Servicios de emisión (casos de uso)
   │                                    │
   ▼                                    ▼
 F7 Wizard de configuración         F5 Integración POS (flujo de cobro)
   │                                    │
   ▼                                    ▼
 F8 Panel de Diagnóstico Fiscal     F6 Notas de Crédito (anular/devolver)
   │                                    │
   └────────────────────┬───────────────┘
                        ▼
             F9 Historial + impresión fiscal
                        │
                        ▼
             F10 Hardening, doble entorno y go-live
```

Dependencias formales:

| Fase | Depende de | Puede iniciar cuando |
|---|---|---|
| F0 | — | inmediato |
| F1 | F0 | F0 terminada (contratos del ledger) |
| F2 | F0 | F0 terminada (interfaces) |
| F3 | F1 + F2 | F1 y F2 terminadas |
| F4 | F1 | F1 terminada (columnas disponibles) |
| F5 | F3 + F4 | F3 y F4 terminadas |
| F6 | F3 + F5 | F3 y F5 terminadas |
| F7 | F1 | F1 terminada |
| F8 | F3 + F7 | F3 y F7 terminadas |
| F9 | F3 + F5 (+ F6) | F3 y F5 terminadas; F6 recomendada |
| F10 | F5 + F6 + F8 + F9 | todas las fases anteriores |

---

## 4. Prerrequisitos de entorno y gestión

Estos no bloquean el desarrollo de F0–F2 (se pueden avanzar con mocks), pero **bloquean las pruebas
reales de F2/F3/F10** y el uso productivo del módulo.

| Prerrequisito | Tipo | Responsable |
|---|---|---|
| Credencial de plataforma de VendAR para padrón (`ws_sr_constancia_inscripcion`) | Gestión externa | VendAR (Admin Global) |
| Certificado `.pfx` de producción del comercio + contraseña | Gestión del comercio | Comercio |
| Certificados de homologación ARCA (para el entorno de pruebas) | Gestión | VendAR / Dev |
| Alta y selección de puntos de venta en ARCA | Gestión del comercio | Comercio |
| `ext-soap` y `ext-openssl` disponibles en la imagen Docker | Infraestructura | Dev |
| Dependencia de generación de QR (p. ej. `simplito/php-qr-code`) | Dependencia Composer | Dev (fase F9) |

---

## 5. Fases de desarrollo

### F0 — Núcleo de dominio (fundación pura)

**Objetivo:** crear el núcleo fiscal testeable sin Laravel que concentre reglas, cálculos y value objects.

**Entregables** (`app/Facturacion/Domain/`):

- Value Objects: `Cuit` (validación mod 11), `Alicuota`, `Importe` (precisión/redondeo), `LetraComprobante`
  (A, B), `Concepto`, `EstadoComprobante`.
- Entities: `Emisor` (condición fiscal), `Receptor` (CUIT, razón social, domicilio, condición),
  `DetalleFiscal` (cantidad, precio, alícuota, neto, IVA), `ComprobanteFiscal` (números, CAE, vencimiento,
  totales), `PuntoVenta`.
- Calculators: `DesgloseIvaCalculator` (back-cálculo desde precio con IVA incluido, arquitectura §4),
  `TotalesFiscalesCalculator`, `RedondeoCalculator` (regla `total = neto + iva` por línea y total).
- Rules: `DeterminacionLetraRule` (tabla emisor/receptor, arquitectura §1.3),
  `ElegibilidadEmisorRule` (solo RI; monotributo → `no_soportado`), `ReglasNotaCredito` (total/parcial).
- Contracts (interfaces): `PadronConsulta`, `Wsfet`, `ComprobanteFiscalAdapter`,
  `ComprobanteFiscalRepository` (para que el dominio no dependa de la implementación).
- Tests de unidad puros (PHPUnit, sin Laravel).

**Criterios de aceptación:**
- `Cuit` valida dígito verificador correctamente.
- Back-cálculo de un ítem con IVA incluido devuelve `neto + iva = precio` sin redondeo perdido.
- Determinación de letra cubre: RI→RI = A; RI→monotributo/consumidor final = B; emisor monotributo = no emite.
- Cero dependencias de Laravel/Eloquent en `Domain/` (verificable por `composer dump-autoload` + revisión).

**Riesgos técnicos:** error de redondeo acumulado en totales (mitigado por regla `iva = total - neto`);
interpretación errónea de la alícuota 0%/exento.

**Pruebas:** tests unitarios de Value Objects, Calculators y Rules. Cobertura ≥ 90% en `Domain/Calculators`.

**Reutiliza / nuevo:** 100% nuevo.

---

### F1 — Persistencia y modelos

**Objetivo:** materializar en PostgreSQL los datos fiscales sin alterar el comportamiento actual de ventas.

**Entregables:**
- Migraciones:
  - `productos.alicuota_iva` (decimal, **nullable en esta fase**; obligatorio en F5; backfill a alícuota
    general para registros existentes documentado y revisado antes de ejecutar).
  - `detalle_ventas.alicuota_iva` (snapshot al vender; **nullable** para no romper el flujo actual de
    `store` hasta F5).
  - `consumidores`: `cuit`, `tipo_documento`, `razon_social`, `domicilio_fiscal` (todos nullable; el
    índice único de `documento` existente debe respetarse).
  - `configuracion_fiscal_comercios` (por `comercio_id`): CUIT, condición fiscal, entorno
    (`produccion`/`homologacion`), punto de venta activo, estado del módulo, referencia al certificado.
  - `comprobantes_fiscales` (ledger): `venta_id`, `comercio_id`, `punto_venta`, `tipo`, `letra`, `numero`,
    `cae`, `vencimiento_cae`, neto/IVA/total por alícuota, `qr`, `comprobante_original_id` (NC),
    `estado`, timestamps. **Índice único** `(comercio_id, punto_venta, tipo, numero)`.
  - Fila/control de numeración (por `comercio_id`, `punto_venta`, `tipo`) con `lockForUpdate`.
- Modelos Eloquent: `ConfiguracionFiscalComercio`, `ComprobanteFiscal` (relaciones `venta`,
  `comercio`), accesorios necesarios. Extensiones de `fillable`/`casts` en `Producto`, `DetalleVenta`,
  `Consumidor`.
- Seeder de configuración por defecto (si aplica).

**Criterios de aceptación:**
- `migrate` y `migrate:rollback` idempotentes y sin pérdida de datos en BD con datos reales.
- El flujo de venta actual (`store`) sigue insertando `detalle_ventas` sin requerir `alicuota_iva`.
- El ledger no admite duplicados de numeración (índice único efectivo).
- Todo registro del módulo lleva `comercio_id` y las FKs respetan el esquema existente.

**Riesgos técnicos:** agregar columnas a tablas grandes (locking de la tabla); backfill de alícuota;
decisión de precisión decimal (`decimal(12,2)` coherente con precios actuales).

**Pruebas:** migraciones subir/bajar; tests de modelos; script de verificación de integridad del índice
único; smoke del flujo de venta post-migración.

**Reutiliza / nuevo:** reutiliza el patrón de migraciones y los modelos `Producto`, `DetalleVenta`,
`Consumidor`, `Venta` (extensión). Nuevas: las dos tablas del módulo y sus modelos.

---

### F2 — Adapters ARCA (integración SOAP)

**Objetivo:** aislar toda la integración SOAP detrás de las interfaces del dominio.

**Entregables** (`app/Facturacion/Infrastructure/Arca/`):

- `WsaaClient`: autenticación, obtención y **renovación única del token** (cache con `lockForUpdate`,
  arquitectura §14.1).
- `WsfetClient` implementando `Wsfet`: emisión (`FECAECAE`), consulta de estado del comprobante,
  parámetros de puntos de venta y alícuotas. Sobre el servicio vigente (v4/RG 5616/2024), detrás de
  `ComprobanteFiscalAdapter`.
- `PadronClient` implementando `PadronConsulta`: `ws_sr_constancia_inscripcion` con credencial de
  plataforma; devuelve condición fiscal, nombre y estado.
- Encriptación y almacenamiento del certificado (cifrado de aplicación; **el certificado nunca sale del
  servidor**, invariante 9).
- Credencial de plataforma gestionada en Admin Global (solo lectura del padrón, invariante 10).
- Doble entorno productivo/homologación (arquitectura §14.5); homologación restringida (arquitectura §13.1).
- Servicio de conectividad para el botón "Probar conexión" (suite secuencial).

**Criterios de aceptación:**
- `WsaaClient` renueva el TA sin duplicar peticiones concurrentes.
- `WsfetClient` emite y consulta comprobantes en **homologación** con certificados de prueba.
- `PadronClient` consulta contribuyentes con la credencial de plataforma y mapea condición fiscal.
- Cambiar la versión del WSFE implica tocar únicamente esta capa.

**Riesgos técnicos:** cambios de versión de WSFE; tiempos de respuesta/outage de ARCA; expiración de
certificados; manejo de errores SOAP no estructurados; secretos mal protegidos.

**Pruebas:** tests de integración contra homologación (si hay credenciales) o contra fixtures de respuesta
SOAP; tests de renovación de token; tests de manejo de timeout.

**Reutiliza / nuevo:** 100% nuevo. Reutiliza el patrón de `config/services.php` para endpoints.

---

### F3 — Servicios de emisión (casos de uso del dominio)

**Objetivo:** orquestar mínimamente los casos de uso reutilizando el dominio puro y los adapters.

**Entregables:**

- `EmisionService` (caso de uso principal, arquitectura §7.1): valida estado del comercio →
  determinación de letra → cálculo de desglose → numeración (`lockForUpdate`) → solicitud de CAE →
  persistencia atómica de venta + detalles + comprobante + movimientos.
- `NumeracionService`: calcula el próximo número cumpliendo las propiedades de §18.2 (secuencial,
  inmutable, locking, índice único). Estrategia concreta decidida aquí, cumpliendo el contrato.
- `CaePerdidoHandler`: ante timeout, consulta estado del comprobante por `(punto_venta, numero, tipo)`
  y adopta o reemite (arquitectura §7.2).
- `EstadoFiscalService`: máquina de estados del módulo (`sin_datos → … → listo_para_facturar`, estados de
  falla y `no_soportado`).
- `ComprobanteFiscalRepository` (implementación Eloquent) y `ConfiguracionFiscalRepository`.
- Persistencia de eventos/auditoría reutilizando `Auditable`.

**Criterios de aceptación:**
- No existe venta completada sin comprobante (invariante 1): si el CAE falla, la venta no se completa.
- Dos emisiones concurrentes del mismo comercio/PV/tipo producen números distintos y sin colisión.
- El estado `no_soportado` (monotributo) nunca emite (invariante de emisor).
- El ledger es inmutable (solo insert) y la numeración nunca retrocede (invariantes 6 y 7).

**Riesgos técnicos:** concurrencia en numeración; CAE emitido pero no persistido; orden de operaciones en
la transacción; desvío silencioso de las invariantes.

**Pruebas:** tests de integración con adapters mockeados (nunca ARCA real en CI); tests de concurrencia de
numeración; tests del flujo CAE perdido; tests de la máquina de estados.

**Reutiliza / nuevo:** reutiliza `Auditable`, el patrón `lockForUpdate` ya usado en `VentaController`.
Nuevos: los servicios del módulo.

---

### F4 — Correcciones de base del sistema actual (prerequisitos de integridad)

**Objetivo:** resolver los defectos existentes que condicionan la emisión fiscal, sin tocar lógica fiscal.

**Entregables (cambios sobre el código actual):**
- **Bug `recargo_monto` en caja** (arquitectura §10, prerequisito documentado): hoy el recargo no entra al
  total, a los pagos ni a los `MovimientoCaja`. Corregir para que el arqueo coincida con lo cobrado y lo
  facturado. Revisar `VentaController::store` (validación de pagos vs total), `Venta::total` y la creación
  de `MovimientoCaja`.
- **Bug `cantidad_devuelta` en `cancelar()`**: `DetalleVenta::fillable` no incluye `cantidad_devuelta`,
  por lo que `update()` en la anulación no persiste (en `devolver()` funciona por `increment()`).
  Agregar al `fillable` y verificar el flujo de anulación.
- **Permisos de anulación/devolución**: las rutas `ventas.cancelar` y `ventas.devolver` solo usan
  `modulo:pos`; se debe aplicar el permiso existente de anulación para restringir quién puede anular
  (relevante para el invariante 2: quién autoriza la NC).
- Validación sintáctica de CUIT/documento del consumidor (requerida para receptores informados).

**Criterios de aceptación:**
- `recargo_monto` queda registrado en caja correctamente (movimiento por el monto efectivamente cobrado).
- La anulación persiste `cantidad_devuelta` y el stock/caja se restauran como hoy.
- Solo usuarios autorizados pueden anular/devolver.
- Sin regresiones en el flujo de venta actual (tests de regresión verdes).

**Riesgos técnicos:** tocar `store` afecta la caja y los fiados (módulo crítico); cambios en totales pueden
descuadrar arqueos existentes; control de permisos puede bloquear operadores legítimos.

**Pruebas:** tests de regresión completos del POS/caja/fiados; tests específicos de recargo en caja y de
anulación con `cantidad_devuelta`.

**Reutiliza / nuevo:** 100% reutiliza infraestructura existente (correcciones sobre ella).

---

### F5 — Integración POS (flujo de cobro)

**Objetivo:** que la venta emita el comprobante fiscal cuando el módulo esté activo, sin cambiar el
comportamiento para los comercios sin módulo.

**Entregables (cambios sobre `VentaController::store` y `confirmarPago`, y sobre `Pos/Terminal.vue`):**
- Escritura del snapshot `alicuota_iva` en cada `detalle_venta` al vender (arquitectura §4.3).
- Determinación de letra al momento de cobrar; captura de datos de receptor RI cuando corresponda
  (Factura A) antes de completar (arquitectura §5).
- Invocación de `EmisionService` dentro de la transacción de `store` (venta directa) y de
  `confirmarPago` (venta PENDING), solo si el módulo está `listo_para_facturar`.
- Manejo de UI en POS: indicador de comprobante a emitir, errores de emisión con reintento y mensaje claro
  (contingencia del MVP: solo reintentos y CAE perdido, arquitectura §7.3).
- Campo `alicuota_iva` obligatorio en el formulario/importación de productos (desde esta fase).

**Criterios de aceptación:**
- Comercio sin módulo: flujo POS idéntico al actual (cero cambios visibles).
- Comercio con módulo activo: toda venta completada emite comprobante (invariante 1).
- Factura A exige receptor RI válido (CUIT + razón social + domicilio + validación padrón) antes de cobrar.
- El snapshot del detalle es el que se factura; cambios posteriores del producto no afectan el comprobante
  (invariante 12).
- Fallo de ARCA → la venta no se completa y se informa el motivo (sin venta sin CAE).

**Riesgos técnicos:** latencia de ARCA dentro de la transacción de cobro; errores en el alta rápida de
cliente; regresión en el flujo de pagos múltiples; recargo ya integrado (F4) debe coincidir con lo
facturado.

**Pruebas:** tests de integración del flujo completo de venta con emisión mockeada; tests de determinación
de letra; tests de regresión del POS (stock, caja, pagos múltiples, fiados); pruebas manuales en
homologación.

**Reutiliza / nuevo:** reutiliza `store`, `confirmarPago`, `Terminal.vue`, alta de cliente, `MovimientoCaja`
(existentes, extensión). Nuevo: el uso de `EmisionService` y los componentes UI fiscales del POS.

---

### F6 — Notas de Crédito (anular / devolver)

**Objetivo:** garantizar que ninguna venta con comprobante emitido se cancele o devuelva sin su NC
(invariante 2).

**Entregables (cambios sobre `cancelar()` y `devolver()`):**
- `NcService` (caso de uso): emite NC total (anulación) o parcial (devolución proporcional) dentro de la
  misma transacción, referenciando el comprobante original (`comprobante_original_id`).
- Bloqueo de anulación/devolución si la emisión de la NC falla; pendiente de reintento visible en el Panel
  de Diagnóstico (arquitectura §8).
- La NC **no reafecta** stock ni caja (invariantes 3 y 4; el flujo actual de anulación/devolución sigue
  siendo quien restaura stock y registra egresos).

**Criterios de aceptación:**
- Anulación de venta facturada → NC total emitida; devolución parcial → NC parcial por el monto devuelto.
- Si ARCA falla al emitir la NC, la anulación/devolución no se concreta y queda un pendiente trazable.
- No hay doble impacto: stock y caja se restauran exactamente una vez.

**Riesgos técnicos:** NC parcial con redondeo proporcional; reversión de pagos múltiples; consistencia
entre monto devuelto y NC; bloqueo incorrecto de anulaciones legítimas.

**Pruebas:** tests de NC total/parcial; tests de trazabilidad (invariante 2); tests de regresión de
anulación/devolución con stock y caja.

**Reutiliza / nuevo:** reutiliza `cancelar`/`devolver`, restauración de stock, reversión de pagos.
Nuevo: `NcService` y su integración.

---

### F7 — Wizard de configuración

**Objetivo:** permitir configurar el módulo por comercio, con homologación exclusiva de Admin/Dev.

**Entregables:**
- Controller del wizard + páginas Vue (`ConfiguracionFiscal/Wizard.vue` o similar).
- Pasos: 1) CUIT → Verificar con ARCA (padrón, autocompleta); 2) Confirmación de datos; 3) Carga del
  certificado `.pfx` + contraseña (encriptada); 4) Selección de puntos de venta (WSFE); 5) Probar
  conexión; 6) Resumen/Activación (arquitectura §13).
- Persistencia en `configuracion_fiscal_comercios` y avance de la máquina de estados.
- **Homologación** como opción exclusiva de Administración Global / Desarrollo; nunca habilitable para un
  comercio de producción (arquitectura §13.1 y §16).

**Criterios de aceptación:**
- El wizard no puede activar el módulo si el padrón no confirma condición RI (`no_soportado` para
  monotributo).
- El entorno de homologación está vedado a roles comerciales en producción.
- El certificado se almacena encriptado y nunca se expone (invariante 9).

**Riesgos técnicos:** fuga de credenciales; certificados inválidos/vencidos; UI que no expresa el estado
real; intentos de activación en entorno equivocado.

**Pruebas:** tests de validación por paso; tests de roles/entorno; verificación de encriptación del
certificado; pruebas manuales del wizard en homologación.

**Reutiliza / nuevo:** reutiliza patrones de UI existentes (formularios Inertia), `ConfiguracionController`
como referencia. Nuevos: todo el wizard y sus rutas.

---

### F8 — Panel de Diagnóstico Fiscal

**Objetivo:** exponer el estado del módulo y permitir la verificación de conectividad.

**Entregables:**
- Página Vue `DiagnosticoFiscal.vue` + controller.
- Checklist por ítem con estado y acción (CUIT verificado, datos completos, certificado vigente, PV
  habilitados, conectividad, estado del módulo) (arquitectura §15).
- Indicador global 🟢 / 🟡 / 🔴.
- Botón "Probar conexión con ARCA": suite secuencial (conectividad → certificado → WSAA → padrón → WSFE →
  PV); en producción no emite comprobantes de prueba.
- Gestión de pendientes de NC fallidas (reintento desde el panel).

**Criterios de aceptación:**
- El panel refleja fielmente la máquina de estados y cada falla ofrece su acción de remediación.
- El botón de prueba no genera comprobantes en producción.
- El panel es el único punto de entrada al wizard y al reintento de pendientes.

**Riesgos técnicos:** información desactualizada; llamadas lentas a ARCA en el panel; exposición de datos
sensibles del comercio.

**Pruebas:** tests del servicio de diagnóstico; verificación de que la prueba no emite en producción;
pruebas manuales del checklist.

**Reutiliza / nuevo:** reutiliza `EstadoFiscalService` (F3). Nuevos: panel y controller.

---

### F9 — Historial de ventas + impresión fiscal

**Objetivo:** mostrar los comprobantes en el historial existente y emitir las vistas fiscales.

**Entregables:**
- **Historial** (extiende `Ventas/Index.vue`, no crea historial nuevo): columnas de tipo de comprobante,
  letra, número, CAE y vencimiento; acciones de PDF, reimpresión y acceso a la NC (arquitectura §11).
- **Impresión fiscal** (arquitectura §12):
  - Extensión de `TicketBuilder`/`TicketData` para datos fiscales (sin romper el ticket no fiscal actual).
  - Vistas Blade fiscales nuevas: `58mm`, `80mm`, `A4`, con **QR ARCA**, CAE, vencimiento, desglose IVA
    (neto/IVA/total por alícuota) y datos fiscales del emisor.
  - Generación del QR (dependencia nueva de QR) y PDF (reutiliza DomPDF ya presente).
  - El número fiscal proviene del ledger (§18.2); el número de ticket (ID) se mantiene solo para el ticket
    no fiscal.

**Criterios de aceptación:**
- El historial muestra los datos fiscales por venta sin perder funcionalidad actual.
- Las vistas fiscales contienen todos los campos obligatorios y el QR es válido/escaneable.
- El ticket no fiscal existente sigue funcionando sin cambios para comercios sin módulo.
- Reimpresión no altera el ledger (solo lectura).

**Riesgos técnicos:** rotura del ticket actual al extender `TicketBuilder`; QR inválido por datos
incorrectos; rendimiento de PDF con DomPDF; columnas nuevas en un historial paginado.

**Pruebas:** tests de renderizado de vistas fiscales; validación del QR; tests de regresión del ticket;
pruebas manuales de impresión en 58/80/A4.

**Reutiliza / nuevo:** reutiliza `TicketBuilder`, `TicketData`, vistas `tickets/*`, `Ventas/Index.vue`,
DomPDF. Nuevos: vistas fiscales, generación de QR, extensiones de datos.

---

### F10 — Hardening, doble entorno y go-live

**Objetivo:** estabilizar el módulo, validarlo en homologación y preparar el lanzamiento controlado.

**Entregables:**
- Revisión completa de invariantes (arquitectura §19) con tests que las protejan.
- Plan de despliegue: migraciones, dependencia QR, configuración de entorno (endpoints ARCA,
  credencial de plataforma), variables de entorno.
- Checklist de go-live: credencial de plataforma operativa, certificado de producción de al menos un
  comercio piloto, PV activos, pruebas end-to-end en homologación, plan de rollback.
- Documentación operativa breve y formación del equipo de soporte.

**Criterios de aceptación:**
- Todos los tests verdes en CI.
- Un comercio piloto completa el ciclo completo en homologación: wizard → diagnóstico → venta con CAE →
  NC → reimpresión → PDF.
- Rollback documentado y ejecutable (las migraciones no destructivas y el módulo desactivable por comercio).

**Riesgos técnicos:** fallas descubiertas en producción por datos reales; dependencia de tiempos de ARCA
en horario pico; errores de configuración de entorno.

**Pruebas:** suite completa; end-to-end en homologación; verificación del checklist de go-live.

**Reutiliza / nuevo:** reutiliza la infraestructura de CI/CD existente. Nuevos: configs de despliegue del
módulo.

---

## 6. Qué se puede desarrollar en paralelo

| Grupo paralelo | Fases | Restricción |
|---|---|---|
| Grupo A | F0 → F1 → F2 → F3 | Cadena crítica; no paralelizable entre sí |
| Grupo B | F4 | Depende solo de F1; paralela a F2/F3 |
| Grupo C | F7 | Depende solo de F1; paralela a F2/F3/F4 |
| Grupo D | F5 | Requiere F3 + F4 |
| Grupo E | F6 | Requiere F3 + F5 |
| Grupo F | F8 | Requiere F3 + F7 |
| Grupo G | F9 | Requiere F3 + F5 (F6 recomendada) |
| Grupo H | F10 | Requiere D + E + F + G |

**Recomendación de asignación (2 flujos):**
- **Flujo 1 (núcleo):** F0 → F1 → F2 → F3 → F5 → F6 → F10.
- **Flujo 2 (periferia):** F4 → F7 → F8 → F9, integrando con el Flujo 1 cuando sus dependencias estén
  listas (F4 y F7 pueden arrancar apenas F1 termine).

---

## 7. Reutilización vs nuevo (matriz)

| Componente actual | Reutilización en el módulo | Fase |
|---|---|---|
| `VentaController::store` | Se extiende para emitir dentro de la transacción | F5 |
| `VentaController::confirmarPago` | Idem para ventas PENDING | F5 |
| `VentaController::cancelar` / `devolver` | Se extienden para emitir NC | F6 |
| `MovimientoCaja` / `MovimientoCuentaCorriente` / restauración de stock | Sin cambios: la factura no los toca (invariantes 3-5) | F5/F6 (solo corrección de recargo en F4) |
| `TicketBuilder` / `TicketData` / vistas `tickets/*` | Base para vistas fiscales | F9 |
| `Ventas/Index.vue` | Se extiende (no se crea historial nuevo) | F9 |
| `Producto` / `DetalleVenta` / `Consumidor` | Se extienden (columnas fiscales) | F1 |
| `Configuracion` (key-value) | Datos de empresa existentes (CUIT, nombre, dirección) como fuente inicial del wizard | F7 |
| `Auditable` (spatie/activitylog) | Auditoría de comprobantes y eventos del módulo | F3 |
| Patrón `lockForUpdate` en transacciones | Numeración segura del ledger | F3 |
| `config/services.php` | Nuevo bloque `arca` (endpoints, credenciales de plataforma) | F2 |
| `AppServiceProvider` | Registro de bindings de interfaces del dominio | F2/F3 |
| Middleware `modulo:*` y `role:*` | Protección de rutas del módulo | F5-F8 |
| DomPDF | PDF de comprobantes | F9 |
| Patrón `paymentRecorder`/`EnviarTicketDigital` | Referencia de jobs; no se reutiliza tal cual | F5/F6 |

| Componente nuevo | Ubicación | Fase |
|---|---|---|
| Núcleo de dominio puro | `app/Facturacion/Domain/*` | F0 |
| Adapters SOAP | `app/Facturacion/Infrastructure/Arca/*` | F2 |
| Servicios de emisión/NC/estado | `app/Facturacion/Domain/Services` (orquestación mínima) + `Infrastructure/Persistence` | F3/F6 |
| Repositorios | `app/Facturacion/Infrastructure/Persistence/*` | F3 |
| Migraciones y modelos del módulo | `database/migrations/*`, `app/Models/*` | F1 |
| Wizard y Diagnóstico (controller + Vue) | `app/Http/Controllers/Facturacion/*`, `resources/js/Pages/Facturacion/*` | F7/F8 |
| Vistas fiscales 58/80/A4 + QR | `resources/views/facturacion/*` | F9 |
| Bloque `services.arca` | `config/services.php` | F2 |

---

## 8. Estrategia de pruebas

| Tipo | Fase(s) | Detalle |
|---|---|---|
| Unitarias puras (sin Laravel) | F0 | Value Objects, Calculators, Rules |
| Migraciones / modelos | F1 | Subir/rollback, integridad de índices, smoke del flujo de venta |
| Integración con adapters mockeados | F2/F3 | Nunca ARCA real en CI; fixtures SOAP; concurrencia de numeración |
| Integración contra homologación | F2/F10 | Solo con credenciales/certificados de homologación |
| Regresión del sistema actual | F4/F5/F6 | POS, caja, stock, fiados, pagos múltiples, tickets |
| Invariantes (arquitectura §19) | F3/F5/F6/F10 | Tests que fallan si se viola una invariante |
| End-to-end | F10 | Ciclo completo en homologación (wizard → venta → NC → reimpresión) |

**Regla de CI:** el módulo no se considera terminado si no pasan las pruebas de la fase correspondiente
y las de regresión de las fases anteriores.

---

## 9. Definition of Done global

1. Todos los entregables de las fases F0–F10 existentes, revisados y con pruebas verdes.
2. Ninguna invariante de la arquitectura (§19) quedó sin cobertura de test.
3. Comercios sin módulo configurado operan exactamente como hoy (cero regresiones).
4. Un comercio piloto RI completa el ciclo completo en homologación.
5. El módulo es desactivable por comercio y el rollback de migraciones es no destructivo.
6. Documentación operativa mínima disponible para soporte.

---

## 10. Referencias

- `docs/arquitectura-facturacion.md` — arquitectura congelada v1.0 (contrato).
- `app/Http/Controllers/VentaController.php` — puntos de integración: `store` (L85-521),
  `confirmarPago` (L530-593), `cancelar` (L595-713), `devolver` (L715-853).
- `app/Services/Ticket/TicketBuilder.php` y `TicketData.php` — base de impresión.
- `resources/js/Pages/Pos/Terminal.vue` y `resources/js/Pages/Ventas/Index.vue` — UI a extender.
- `routes/web.php` — rutas del POS (L108-163) y zona de configuración (L308-330).
- `app/Models/Producto.php`, `DetalleVenta.php`, `Consumidor.php`, `Venta.php`, `Configuracion.php` —
  modelos a extender.
- Migraciones base: `create_productos_table`, `create_detalle_ventas_table`, `create_ventas_table`,
  `create_configuracions_table`, `add_cantidad_devuelta_to_detalle_ventas`.
