# Arquitectura de Facturación Electrónica (ARCA) — VendAR

| Campo | Valor |
|---|---|
| Versión | v1.0 |
| Estado | **CONGELADA** |
| Aplica desde | fecha de aprobación |
| Módulo | Facturación electrónica (ex AFIP, ARCA) |
| Alcance | SaaS multi-tenant VendAR |

> **Regla de congelación.** Esta arquitectura es el contrato del BUILD. Ningún cambio posterior puede
> introducirse de forma silenciosa: cualquier modificación, adición o corrección de alcance se documenta
> como una **nueva versión de arquitectura**, con su propia revisión y aprobación.

---

## 1. Objetivo y alcance

Emitir comprobantes fiscales electrónicos (ARCA) para las ventas del POS de VendAR, con CAE en tiempo
real, notas de crédito, reimpresión y PDF, integrado con el flujo de ventas existente.

### 1.1 Comprobantes del MVP

- **Factura A**
- **Factura B**
- **Nota de Crédito** (A y B, total y parcial)

### 1.2 Emisor soportado (DEFINITIVA)

El MVP **solo soporta emisores Responsable Inscripto (RI)**.

- Los monotributistas emiten **Factura C** como comprobante general (régimen simplificado) y la Factura M
  es un comprobante especial (agentes de retención / sujetos de alto riesgo fiscal), **no** el del régimen
  general. Ambos quedan **fuera del MVP** (ver §21).
- **Comportamiento para comercios monotributistas**: el módulo fiscal **no puede activarse**. Si el padrón
  devuelve condición monotributo, el sistema lo informa explícitamente, el módulo queda en estado
  `no_soportado` (🔴 **no posible** en el diagnóstico) y el comercio continúa operando con ticket no fiscal.
  La emisión para monotributistas se habilita en una versión posterior con Factura C (roadmap, §22).
- El sistema nunca permite elegir una letra manualmente.

### 1.3 Determinación de letra (automática)

Para emisores RI (única condición soportada en el MVP), la letra se determina por la condición del receptor
(dato oficial del padrón ARCA):

| Emisor | Receptor | Comprobante |
|---|---|---|
| RI | RI con CUIT válido | Factura A |
| RI | Monotributo o Consumidor Final | Factura B |

### 1.4 Emisión obligatoria

Cuando el módulo fiscal de un comercio RI está **activo y `listo_para_facturar`**, toda venta completada
del POS emite su comprobante fiscal de forma **obligatoria**. El ticket no fiscal solo existe mientras el
módulo no esté configurado o esté deshabilitado.

---

## 2. Principios de diseño

1. **Núcleo testeable sin Laravel.** Las reglas fiscales, los cálculos y la máquina de estados viven en
   un dominio puro en PHP, sin dependencias del framework ni de la base de datos.
2. **Separación de responsabilidades.** Se separan: reglas fiscales, cálculos, emisión y adapters de
   integración SOAP. **No existe un orquestador gigante**; cada capa tiene un único contrato.
3. **Multi-tenant estricto.** Toda entidad y toda consulta respeta `comercio_id`. No hay datos compartidos
   entre comercios.
4. **Integridad del comprobante.** El CAE se obtiene y persiste dentro de la misma transacción que completa
   la venta. No existe venta completada sin comprobante, ni comprobante sin venta.
5. **La venta es la única responsable del movimiento económico.** Stock y caja los afecta exclusivamente
   la venta (y su anulación/devolución). La factura es un documento fiscal que referencia `venta_id` y
   nunca modifica stock ni caja.
6. **El dinero y la norma se tratan con verdad absoluta de ARCA.** El padrón y el WSFE son la fuente de
   verdad; el sistema no inventa ni asume condiciones.

---

## 3. Modelo de dominio y capas

```
app/Facturacion/
├── Domain/                        # Núcleo puro (PHP, sin Laravel)
│   ├── Entities/                  # Emisor, Receptor, ComprobanteFiscal, PuntoVenta, DetalleFiscal
│   ├── ValueObjects/              # Cuit, Alícuota, Importe, EstadoComprobante, Letra, Concepto
│   ├── Rules/                     # Reglas de negocio fiscales (elegibilidad, redondeo, NC)
│   ├── Calculators/               # Desglose neto/IVA, totales
│   ├── Contracts/                 # Interfaces: PadronConsulta, Wsfet, ComprobanteFiscalAdapter
│   └── Services/                  # Orquestación mínima por caso de uso (EmisionService, NcService)
├── Infrastructure/
│   ├── Arca/                      # Adapters SOAP: WSAA, WSFE, Padrón (únicos que tocan XML/SOAP)
│   ├── Persistence/               # Repositorios sobre Eloquent (solo capa de datos)
│   └── Config/                    # Encriptación y almacenamiento de credenciales/certificados
└── Http/
    └── Controllers/               # Wizard, Diagnóstico, endpoints POS (delgados)
```

Reglas de capa:

- `Domain/` **no importa** nada de Laravel, Eloquent, SOAP ni XML. Se testea con PHPUnit puro.
- `Infrastructure/Arca/` concentra todo lo volátil (cambios de versión de servicios ARCA).
- Los controllers solo traducen HTTP ↔ casos de uso del dominio.
- Ningún cálculo fiscal se realiza fuera de `Domain/Calculators`.

---

## 4. Precios e IVA

### 4.1 Premisa (DEFINITIVA)

`precio_venta` representa el **precio final con IVA incluido**, consistente con el comportamiento esperado
de kioscos, almacenes y minimercados.

### 4.2 Desglose por ítem (back-cálculo)

Para cada ítem con su alícuota:

- `neto = round(precio_unitario * cantidad / (1 + alicuota), 2)`
- `iva = round(precio_unitario * cantidad - neto, 2)`

El total del comprobante se construye por ítem y el redondeo se absorbe en el IVA:
`iva = total_linea - neto`, garantizando `total_linea = neto + iva` en cada línea y en el total.

### 4.3 Alícuota por producto y snapshot (DEFINITIVA)

- Todo producto requiere una **alícuota IVA** (21%, 10.5%, 0%/exento, u otras válidas según normativa).
- Al momento de vender, el `detalle_venta` persiste un **snapshot** de la alícuota aplicada.
- **Nunca** se recalcula el IVA leyendo nuevamente el producto: el comprobante siempre usa el snapshot
  del detalle, garantizando que la factura refleja exactamente lo vendido.

### 4.4 Redondeo

- Redondeo a 2 decimales en cada operación intermedia.
- Validación final: los montos enviados a ARCA deben satisfacer la regla `total = neto + iva`.
  Si una suma de líneas difiere en centavos, el ajuste recae sobre el IVA (nunca sobre el neto).

---

## 5. Clientes

Se agregan campos a `Consumidor`:

| Campo | Tipo | Obligatorio según comprobante |
|---|---|---|
| `cuit` | string (11 díg., válido mod 11) | A (receptor RI) |
| `tipo_documento` | enum (CUIT, DNI, etc.) | B cuando se informa receptor |
| `razon_social` | string | A |
| `domicilio_fiscal` | string | A |

Reglas de validación:

- **Factura A**: receptor RI con `cuit` + `razon_social` + `domicilio_fiscal`, validado contra el padrón
  ARCA (condición RI activa). Sin esto, no se emite A.
- **Factura B**: receptor Consumidor Final sin datos obligatorios. Si se cargan datos, se validan
  sintácticamente (CUIT mod 11, formato de documento).
- El POS conserva el alta rápida actual; cuando el comprobante requiera receptor RI, se exigen los datos
  del receptor antes de cobrar.

---

## 6. Determinación de comprobante

1. Se determina la condición del emisor desde el padrón (ver §14.3).
2. Si el emisor **no es RI** (p. ej. monotributo), el módulo no puede emitir en el MVP: queda en estado
   `no_soportado` y no se factura (§1.2).
3. Para emisores RI, se determina la letra según la tabla de §1.3.
4. Cuando el módulo está activo y `listo_para_facturar`, el POS emite el comprobante **obligatoriamente**.
5. El comprobante se vincula a `venta_id`; `tipo` y `letra` quedan fijos al emitir.

---

## 7. Emisión

### 7.1 Flujo atómico

La emisión ocurre **dentro de la misma transacción** que completa la venta:

1. Validación del estado fiscal del comercio (`listo_para_facturar`) y del receptor según letra.
2. Cálculo del desglose fiscal (§4) sobre los detalles de la venta.
3. Determinación del número de comprobante en el ledger con `lockForUpdate` (§18.2).
4. Solicitud de CAE a ARCA (síncrona).
5. Persistencia de venta + detalles + comprobante fiscal (con CAE) + movimientos de stock y caja.

- Venta directa (completada en `store`): emite en `store`.
- Venta PENDING (se confirma luego): emite en `confirmar-pago`, al marcarla Completada.

### 7.2 Estados del comprobante

`pendiente_emision → en_proceso → emitido | fallo`

- `emitido`: CAE persistido y válido.
- `fallo`: no se pudo obtener CAE; la venta no se completa (rollback). Se ofrece reintento.
- **CAE perdido** (ARCA emitió pero no llegó la respuesta): se consulta el estado del comprobante por
  `(punto_venta, número, tipo)`; si existe, se adopta; si no, se reemite con nuevo número.

### 7.3 Contingencia del MVP

- **Solo reintentos** y manejo de CAE perdido. No hay CAEA ni contingencia avanzada (§21).
- Si ARCA no responde tras los reintentos, la venta **no se completa** y el vendedor ve el motivo en el POS.

---

## 8. Notas de Crédito

- **NC total** → anulación de venta facturada (emite NC por el total).
- **NC parcial** → devolución parcial de venta facturada (NC por el monto devuelto, proporcional).
- La NC se emite **sincrónicamente dentro de la transacción** de `cancelar`/`devolver`, cuando la venta
  tiene comprobante emitido.
- La NC referencia al comprobante original (`comprobante_original_id`).
- **Invariante de trazabilidad**: no puede existir una venta con comprobante emitido cancelada (total o
  parcialmente) sin su NC asociada. Si la emisión de la NC falla, la anulación/devolución **se bloquea**
  y queda pendiente en el Panel de Diagnóstico para reintento.

---

## 9. Caja y stock

- **La venta es la única responsable** de afectar stock y caja (comportamiento actual confirmado: descuento
  de stock por lotes + `producto_sucursal` + `movimientos_stock`; movimientos de caja por pago).
- **La factura nunca modifica stock ni caja**: es un documento fiscal que referencia `venta_id`.
- Las anulaciones/devoluciones continúan afectando stock y caja por su propio flujo; la NC asociada no
  reafecta ni stock ni caja.

---

## 10. Recargo por tarjeta

- El recargo por tarjeta (`recargo_monto`) **se incluye en el comprobante fiscal**, de modo que el importe
  total facturado coincida con el importe efectivamente cobrado al cliente.
- **El tratamiento impositivo del recargo (alícuota de IVA o condición fiscal aplicable) NO se hardcodea**:
  se determina según la normativa vigente y la naturaleza del recargo, y se modela como regla configurable.
  Este apartado no fija ninguna alícuota como verdad absoluta.
- **Prerequisito documentado**: existe un bug actual donde `recargo_monto` no impacta correctamente en
  caja (no entra al total, a los pagos ni a los movimientos de caja). **Debe corregirse antes de implementar
  la emisión fiscal**, para que el arqueo coincida con lo facturado.

---

## 11. Historial

No se crea un historial nuevo. Se **extiende el historial de ventas existente**, mostrando por venta:

- tipo de comprobante y letra
- número de comprobante
- CAE y vencimiento
- PDF / reimpresión
- acceso a su Nota de Crédito (si la tiene)

---

## 12. Impresión

- Se reutiliza la infraestructura existente (`TicketBuilder` + vistas Blade + controlador multi-tenant).
- Se agregan **vistas fiscales** específicas para:
  - 58 mm
  - 80 mm
  - A4
- Contenido fiscal obligatorio en las vistas: **QR ARCA**, CAE, vencimiento de CAE, desglose de IVA
  (neto/IVA/total por alícuota) y datos fiscales del emisor.
- El número de ticket actual (ID de venta) se mantiene solo para el ticket no fiscal; el comprobante fiscal
  usa la numeración del ledger (§18.2).

---

## 13. Configuración (wizard)

Wizard de 6 pasos, accesible desde el Panel de Diagnóstico Fiscal:

1. **Contribuyente**: CUIT del comercio → botón **Verificar con ARCA** (padrón, autocompleta).
2. **Confirmación**: datos que ARCA no expone (domicilio comercial, etc.).
3. **Certificado**: carga del `.pfx` del comercio + contraseña (encriptada, §17).
4. **Puntos de venta**: consulta y selección de PV habilitados (WSFE).
5. **Probar conexión**: suite secuencial de verificación (§15).
6. **Resumen / Activación**: confirmación final que habilita el módulo.

### 13.1 Homologación

- El entorno de **homologación es exclusivo de Administración Global / Desarrollo**.
- **Nunca** puede quedar habilitado accidentalmente para un comercio en producción.
- Se expone como opción explícita y restringida por rol; producción usa el entorno productivo de ARCA.

---

## 14. Integración ARCA

### 14.1 WSAA

- Autenticación SOAP con certificado y clave privada del comercio; obtención y renovación del token
  (con `lockForUpdate`/cache para una única renovación concurrente).

### 14.2 WSFE

- Emisión y consulta de comprobantes, PV y alícuotas.
- Se implementa sobre el **servicio vigente** (v4 / RG 5616/2024) **detrás de la interfaz
  `ComprobanteFiscalAdapter`**: un cambio de versión de ARCA impacta solo en `Infrastructure/Arca`,
  nunca en el dominio.

### 14.3 Padrón (consulta de contribuyentes)

- Se usa `ws_sr_constancia_inscripcion` (ex A5), RG 4162/17, con **credencial de plataforma de VendAR**
  (solo lectura de datos públicos, sin autorización del contribuyente consultado).
- Determina: condición fiscal del emisor, validación de receptores RI (Factura A) y consistencia de datos.

### 14.4 Credencial de plataforma

- **Solo puede consultar el padrón. Nunca emite comprobantes.**
- Se configura en Administración Global, se encripta y **nunca se expone al comercio**.

### 14.5 Doble entorno

- Entorno productivo ARCA y entorno de homologación (este último restringido a Admin/Dev, §13.1).

---

## 15. Panel de Diagnóstico Fiscal

- Checklist por ítem con estado individual y acción asociada:

| Ítem | Fuente |
|---|---|
| CUIT del emisor verificado | padrón |
| Datos del emisor completos | wizard paso 2 |
| Certificado vigente | WSAA |
| Puntos de venta habilitados | WSFE |
| Conectividad con ARCA | health check |
| Estado del módulo | máquina de estados |

- Indicador global: 🟢 **listo para facturar** / 🟡 **incompleto** / 🔴 **no posible**.
- Botón **"Probar conexión con ARCA"**: suite secuencial (conectividad → certificado → WSAA → padrón →
  WSFE → PV). En producción no emite comprobantes de prueba.

### 15.1 Máquina de estados del módulo

`sin_datos → datos_cargados → datos_validados → cert_cargado → pv_habilitado → listo_para_facturar`

Estados de falla: `cuit_inactivo`, `condicion_discrepante`, `certificado_vencido`,
`desincronizado_arca`, `error_integracion`. Cada falla tiene acción de remediación en el diagnóstico.

Estado de exclusión: `no_soportado` (emisor monotributista en el MVP, §1.2). No admite remediación dentro
del alcance; el diagnóstico lo muestra como 🔴 **no posible**.

---

## 16. Homologación (reafirmación)

Ver §13.1. La homologación es opción exclusiva de administración o desarrollo y no puede quedar habilitada
para un comercio de producción en ninguna circunstancia.

---

## 17. Multi-tenant y seguridad

- Toda entidad del módulo lleva `comercio_id`; todas las consultas filtran por el comercio autenticado.
- **Policies** de acceso por rol/perfil; el módulo fiscal es configurable por comercio.
- El certificado `.pfx` y su clave se almacenan encriptados (cifrado de aplicación), con acceso restringido.
- **El certificado privado nunca sale del servidor**.
- La credencial de plataforma (§14.4) se mantiene fuera del alcance de los comercios.

---

## 18. Persistencia

### 18.1 Migraciones

- `productos`: agregar `alicuota_iva` (decimal, obligatoria).
- `detalle_ventas`: agregar `alicuota_iva` (snapshot al vender).
- `consumidores`: agregar `cuit`, `tipo_documento`, `razon_social`, `domicilio_fiscal`.
- Nueva tabla `configuracion_fiscal_comercios` (por `comercio_id`): CUIT, condición, entorno,
  punto de venta activo, estados del módulo.
- Nueva tabla `comprobantes_fiscales` (ledger): venta, comercio, punto de venta, tipo, letra, número,
  CAE, vencimiento CAE, neto/IVA/total por alícuota, QR, `comprobante_original_id` (para NC),
  estado, timestamps.

### 18.2 Numeración y ledger

- `comprobantes_fiscales` es un **ledger inmutable**: solo se inserta, nunca se actualiza/borra un
  comprobante emitido.
- La numeración es **secuencial e inmutable** dentro de `(comercio_id, punto_venta, tipo)`, y está
  protegida por **locking** (fila de control de numeración, `lockForUpdate`) y un **índice único** sobre
  `(comercio_id, punto_venta, tipo, numero)`.
- **La numeración nunca retrocede.**
- La estrategia concreta para calcular el próximo número (p. ej. `max(numero) + 1` u otro esquema
  atómico) queda **abierta a la implementación del BUILD**, siempre que cumpla las propiedades anteriores:
  secuencial, inmutable, sin retrocesos, sin duplicados y segura bajo concurrencia.

---

## 19. Invariantes del sistema

Reglas que **nunca podrán romperse** bajo ninguna implementación:

1. Toda venta facturada debe tener **exactamente un** comprobante fiscal válido.
2. Una venta con comprobante emitido **nunca** puede cancelarse sin generar la Nota de Crédito
   correspondiente (total o parcial).
3. La factura **nunca modifica stock**.
4. La factura **nunca modifica caja**.
5. La venta es la **única responsable** del movimiento económico (stock y caja).
6. El ledger de comprobantes es **inmutable**: un comprobante emitido no se modifica ni se borra.
7. La numeración **nunca retrocede**.
8. Todo comprobante pertenece **exactamente a un `comercio_id`**.
9. El certificado privado **nunca sale del servidor**.
10. La credencial de plataforma **solo puede consultar el padrón** y **nunca emitir comprobantes**.
11. El desglose fiscal cumple siempre `total = neto + iva` (por línea y por total).
12. El IVA de un detalle se toma del **snapshot** de su venta; nunca se recalcula desde el producto.

---

## 20. Riesgos y mitigaciones (cerrados)

| Riesgo | Mitigación (decisión definitiva) |
|---|---|
| Sin IVA por producto (C1) | Alícuota obligatoria en producto + snapshot en detalle (§4.3) |
| Cambio de CUIT post-activación (C2) | Revalidación vía padrón y diagnóstico; el historial conserva la condición vigente al emitir |
| Receptor sin CUIT para A (C3) | Validación de receptor RI con padrón antes de emitir A (§5) |
| Venta facturada cancelada sin NC (C4) | NC síncrona obligatoria (§8, invariante 2) |
| Redondeo/back-cálculo de IVA (C5) | Regla de redondeo cerrada y validada contra ARCA (§4.4) |
| Backup/restore y numeración (C6) | Ledger inmutable + `lockForUpdate` + índice único (§18) |
| CAE perdido por timeout | Consulta de estado por (PV, número, tipo) y adopción/reemisión (§7.2) |
| Recargo fuera de caja | Prerequisito: corregir bug de `recargo_monto` antes de la emisión (§10) |
| Emisor monotributista | Módulo `no_soportado`, sin activación, continúa con ticket no fiscal; Factura C en roadmap (§1.2) |

---

## 21. Fuera de alcance del MVP

Deliberadamente **NO** se implementa en v1.0:

- Factura C (comprobante general del monotributo)
- Factura M (comprobante especial: agentes de retención / alto riesgo fiscal)
- Emisión para comercios monotributistas (requiere Factura C)
- CAEA (código de autorización electrónico anticipado)
- Contingencia avanzada (emisión offline / documento de respaldo, excepto reintentos y CAE perdido)
- Libro IVA Digital
- Exportaciones y despacho de importación
- WSCDC completo (consultas complejas de comprobantes de terceros)
- Nota de Débito
- Facturación de pedidos web (e-commerce)
- Múltiples puntos de venta por sucursal (1 PV por comercio en MVP)
- Moneda extranjera

---

## 22. Roadmap post-MVP

- CAEA y contingencia formal
- Libro IVA Digital
- Factura C y emisión para comercios monotributistas
- Factura M (agentes de retención / alto riesgo fiscal)
- Exportaciones
- WSCDC completo
- Nota de Débito
- PV por sucursal
- Facturación de pedidos web

---

## Glosario

- **CAE**: Código de Autorización Electrónico.
- **CAEA**: Código de Autorización Electrónico Anticipado (ventas en contingencia).
- **WSFE**: Web Service de Facturación Electrónica.
- **WSAA**: Web Service de Autenticación y Autorización.
- **Padrón**: `ws_sr_constancia_inscripcion` (consulta de contribuyentes).
- **Ledger**: registro inmutable de comprobantes emitidos.
