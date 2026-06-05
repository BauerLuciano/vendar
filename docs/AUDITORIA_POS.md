# Auditoría Completa del Módulo POS — VendAR

> Versión: 1.0 — Junio 2026
> Alcance: Funcional, UX, Rendimiento, Bugs, Oportunidades de mejora

---

## 🔴 A — Bugs Críticos

### A1. Método de pago sin normalización 🔥
| | |
|---|---|
| **Dónde** | `Terminal.vue` valores hardcodeados: `'Efectivo'`, `'Débito'`, `'Cuenta Corriente'` → `VentaController@store` línea 162: `strtoupper(str_replace(' ', '_', ...))` |
| **Problema** | `'Débito'` se convierte en `'DÉBITO'` pero en otros lados del sistema se usa `'DEBITO'` o `'MERCADO_PAGO'`. No hay normalización. Los reportes de caja mezclan cadenas inconsistentes. |
| **Impacto** | 🔴 Crítico — Reportes de caja incorrectos, conciliación imposible. Ej: una venta puede quedar como `'DÉBITO'` y otra como `'DEBITO'`. |
| **Dificultad** | Baja — crear un enum/normalizador antes de guardar. |
| **Tiempo** | 1h |
| **ROI** | Máximo |

### A2. Búsqueda por ID con LIKE en columna entera 🔥
| | |
|---|---|
| **Dónde** | `VentaController@index` línea 40: `$q->where('id', 'LIKE', "%{$search}%")` |
| **Problema** | Al buscar `"23"` trae ventas con ID 23, 123, 230, 231, 1123, etc. Además PostgreSQL no usa índices con `LIKE '%...%'` en enteros → full table scan. |
| **Impacto** | 🔴 Crítico — Resultados incorrectos + degradación grave con miles de ventas. |
| **Dificultad** | Baja — separar búsqueda: si es numérico exacto filtrar por `id`, si no por texto. |
| **Tiempo** | 1h |
| **ROI** | Máximo |

### A3. Validación de fiado incompleta 🏦
| | |
|---|---|
| **Dónde** | `VentaController@store` líneas 94-112 |
| **Problema** | No se verifica que la `CuentaCorriente` esté activa (`estado = true`). Solo se valida `saldo_deudor <= limite`. Una cuenta desactivada podría seguir usándose. |
| **Impacto** | 🔴 Crítico — Una cuenta desactivada por morosidad puede generar nueva deuda. |
| **Dificultad** | Baja — agregar `->where('estado', true)` al `lockForUpdate`. |
| **Tiempo** | 30min |
| **ROI** | Alto |

### A4. Dos formas de abrir turno con estructuras diferentes 🔥
| | |
|---|---|
| **Dónde** | `PosController@abrirTurno` (web) vs `CajaDiariaController@abrirCaja` (API) |
| **Problema** | El frontend (`AperturaTurno.vue`) llama a la API `/api/sesiones-caja/abrir` que crea un `MovimientoCaja` de apertura. Pero `PosController@abrirTurno` (ruta `pos.abrir_turno`) NO crea el movimiento. Si alguien llama a la ruta web directo, el turno queda sin movimiento de apertura. |
| **Impacto** | 🔴 Alto — Inconsistencia: algunos turnos tienen `FONDO_INICIAL`, otros no. El balance de caja no cierra. |
| **Dificultad** | Media — unificar en una sola ruta/controlador. |
| **Tiempo** | 3h |
| **ROI** | Alto |

---

## 🟡 B — Bugs Medios

### B1. `sucursal_id` no se setea en `TurnoCaja`
| | |
|---|---|
| **Dónde** | `PosController@abrirTurno` y `CajaDiariaController@abrirCaja` |
| **Problema** | La migración agregó `sucursal_id` nullable, pero ningún controlador lo llena. Se obtiene indirectamente de `turno->caja->sucursal_id` pero hay queries que filtran por `turno_cajas.sucursal_id` y está siempre null. |
| **Impacto** | 🟡 Medio — Reportes que usen `turno_cajas.sucursal_id` dan vacío. |
| **Dificultad** | Baja — setear `sucursal_id` al crear el turno, obtenerlo de la caja. |
| **Tiempo** | 30min |
| **ROI** | Alto |

### B2. Anulación con motivo `"otro"` sin texto real
| | |
|---|---|
| **Dónde** | `Ventas/Index.vue` línea 89: `inputOptions` incluye `'otro': 'Otro...'` |
| **Problema** | El select de motivos permite `'otro'` como string literal, no como opción para escribir un motivo personalizado. La anulación queda con motivo `"otro"`. |
| **Impacto** | 🟡 Medio — Trazabilidad de anulaciones deficiente. |
| **Dificultad** | Baja — cambiar a input abierto si selecciona "Otro". |
| **Tiempo** | 1h |
| **ROI** | Medio |

### B3. Sin validación de consumidor existente en fiado
| | |
|---|---|
| **Dónde** | `VentaController@store` línea 99-101 |
| **Problema** | Se valida que el `consumidor_id` exista y tenga `limite_cuenta_corriente`. Pero si el consumidor fue desactivado (`estado = false`), igual se puede vender a fiado. |
| **Impacto** | 🟡 Medio — Clientela desactivada puede generar deuda. |
| **Dificultad** | Baja — agregar `->where('estado', true)` al scope. |
| **Tiempo** | 30min |
| **ROI** | Alto |

### B4. `totalVenta` calculado en frontend, no verificado en backend 🔎
| | |
|---|---|
| **Dónde** | `Terminal.vue` línea 59-61 vs `VentaController@store` |
| **Problema** | El total se calcula en el frontend y se envía como parámetro. El backend no recalcula ni verifica que `total == sum(items.precio_venta * items.cantidad)`. Un request manipulado podría crear ventas por montos incorrectos. |
| **Impacto** | 🟡 Medio — Potencial manipulación de montos. |
| **Dificultad** | Media — recalcular total en backend. |
| **Tiempo** | 2h |
| **ROI** | Alto |

---

## 🟢 C — Mejoras UX

### C1. Sin atajos de teclado (Prioridad máxima)
| | |
|---|---|
| **Dónde** | `Terminal.vue` |
| **Problema** | Para cambiar método de pago hay que usar el mouse y clickear un radio button. No hay: F1=Efectivo, F2=Tarjeta, F3=Fiado, F4=Finalizar venta, F8=Buscar cliente. En un kiosco con movimiento, cada clic extra se multiplica por cientos de ventas al día. |
| **Impacto** | 🟢 Alto — Reduce 2-3 segundos por venta. En 200 ventas/día = 10 minutos ahorrados por cajero. |
| **Dificultad** | Baja — agregar `@keydown` handlers. |
| **Tiempo** | 3h |
| **ROI** | Máximo |

### C2. Venta por Kg requiere muchos clics
| | |
|---|---|
| **Dónde** | `Terminal.vue` `clickEnProducto()` línea 115-157 |
| **Problema** | Para vender 0.5kg de manzanas: click en producto → Swal modal → input peso → elegir unidad → click "Agregar" → click "Cobrar". 5 interacciones para una venta que debería ser 2 (escanear peso + cobrar). |
| **Impacto** | 🟢 Alto — En una verdulería o carnicería con muchos productos por Kg, el POS se vuelve tedioso. |
| **Dificultad** | Media — integrar lectura de balanza directa (código PLU ya soportado pero el Swal frena el flujo). |
| **Tiempo** | 8h |
| **ROI** | Alto |

### C3. Sin búsqueda en tiempo real
| | |
|---|---|
| **Dónde** | `Terminal.vue` — `productosFiltrados` es `computed` pero solo se filtra del array precargado |
| **Problema** | La búsqueda filtra en cliente sobre el array completo de productos. Con 10k+ productos, el filtrado JavaScript congela la UI. Debería ser server-side con debounce. Además el input requiere Enter para productos sin código de barras (la búsqueda por nombre no es automática con el tecleo). |
| **Impacto** | 🟢 Alto — Mejora velocidad de búsqueda y escala a catálogos grandes. |
| **Dificultad** | Media — implementar búsqueda server-side con endpoint Ajax + debounce. |
| **Tiempo** | 8h |
| **ROI** | Alto |

### C4. Sin vista previa del ticket
| | |
|---|---|
| **Dónde** | `Terminal.vue` línea 250: `window.open(route('ventas.imprimir'), '_blank')` |
| **Problema** | La impresión es inmediata sin vista previa. Si la impresora no responde o el papel se atasca, el ticket se pierde. No hay opción de reimprimir desde el POS (solo desde el historial de ventas). |
| **Impacto** | 🟢 Medio — Pérdida de tickets en fallas de impresión. |
| **Dificultad** | Baja — agregar botón "Reimprimir" en el POS + opción de vista previa. |
| **Tiempo** | 4h |
| **ROI** | Medio |

### C5. Sin shortcuts en input de cantidad
| | |
|---|---|
| **Dónde** | `Terminal.vue` `incrementarCantidad` / `decrementarCantidad` |
| **Problema** | Los botones +/− incrementan en 1 o 0.1. No hay forma de escribir directamente la cantidad sin hacer click en el input y escribir. Para productos unitarios está bien, pero para productos "al peso" donde se necesita una cantidad exacta (ej: 7.350 kg), es tedioso. |
| **Impacto** | 🟢 Bajo — Ahorro marginal. |
| **Dificultad** | Baja — el input ya permite edición directa con `@blur`, solo mejorar UX. |
| **Tiempo** | 2h |
| **ROI** | Medio |

### C6. Sin indicador de turno activo en el header
| | |
|---|---|
| **Dónde** | `AuthenticatedLayout.vue` / header |
| **Problema** | El cajero no ve si tiene un turno abierto hasta que entra al POS. Podría estar en otra sección y no recordar si abrió turno. |
| **Impacto** | 🟢 Bajo — Mejora de conciencia situacional. |
| **Dificultad** | Baja — badge en header con "Turno #123 - Caja X". |
| **Tiempo** | 2h |
| **ROI** | Medio |

### C7. Sin scroll infinito en grilla de productos
| | |
|---|---|
| **Dónde** | `Terminal.vue` — grilla de productos filtrados |
| **Problema** | Todos los productos filtrados se renderizan en el DOM. Con muchos productos, el renderizado se vuelve lento. No hay virtual scroll ni paginación. |
| **Impacto** | 🟢 Medio — Mejora rendimiento percibido en catálogos grandes. |
| **Dificultad** | Media — implementar virtual scroll con librería Vue. |
| **Tiempo** | 6h |
| **ROI** | Medio |

---

## 🔵 D — Mejoras de Rendimiento

### D1. Carga completa de productos en memoria 🔴
| | |
|---|---|
| **Dónde** | `PosController@index` línea 30-45: `Producto::...->get()` |
| **Problema** | Todos los productos activos de la sucursal se cargan en memoria PHP y se pasan completos a Inertia/Vue. Con 10k productos, el payload de Inertia puede superar 5-10MB. La página tarda segundos en cargar. |
| **Impacto** | 🔴 Crítico — Tiempo de carga inicial del POS se degrada linealmente con el catálogo. |
| **Dificultad** | Media — implementar lazy loading: cargar solo los primeros 50 productos, buscar server-side con Ajax. |
| **Tiempo** | 12h |
| **ROI** | Máximo |

### D2. Carga completa de clientes en memoria
| | |
|---|---|
| **Dónde** | `PosController@index` línea 75-78: `Consumidor::...->get()` |
| **Problema** | Mismo problema que D1. Con miles de clientes (ej: supermercado con clientes frecuentes), el payload crece innecesariamente. |
| **Impacto** | 🟡 Medio — Aumenta el payload de Inertia. |
| **Dificultad** | Baja — cargar solo clientes con movimiento reciente o buscar server-side. |
| **Tiempo** | 4h |
| **ROI** | Alto |

### D3. Sin índice en movimientos_stock para consultas frecuentes
| | |
|---|---|
| **Dónde** | Migración `movimientos_stock` |
| **Problema** | La tabla de auditoría solo tiene PK en `id`. Las consultas frecuentes son por `producto_id + sucursal_id` o `producto_id + created_at`. Sin índices compuestos, estas queries escalan mal. |
| **Impacto** | 🟡 Medio — Degradación en reportes de auditoría con muchos movimientos. |
| **Dificultad** | Baja — agregar índices compuestos. |
| **Tiempo** | 1h |
| **ROI** | Alto |

### D4. Polling fijo de 15 segundos en CajaDiaria
| | |
|---|---|
| **Dónde** | `CajaDiaria/Index.vue` línea ~289 |
| **Problema** | El polling a `checkNuevosMovimientos` cada 15 segundos puede ser excesivo cuando no hay actividad. En horas de baja demanda, genera requests innecesarios. |
| **Impacto** | 🟢 Bajo — Carga innecesaria en el servidor. |
| **Dificultad** | Baja — polling adaptativo (aumentar intervalo si no hay cambios) o WebSockets. |
| **Tiempo** | 4h |
| **ROI** | Medio |

---

## 💰 E — Features que Aumenten Ventas

### E1. Venta en 1 clic (Quick Sale)
| | |
|---|---|
| **Problema** | Escanear código de barras → producto se agrega al carrito pero requiere Enter o click. En sistemas como Square POS, el escaneo agrega directo al carrito + se puede configurar para cobrar automáticamente. |
| **Solución** | Modo "escaneo continuo": cada código escaneado suma al carrito sin presionar Enter. Botón "Cobrar y seguir" que cobra y limpia el carrito para la siguiente venta. |
| **Impacto** | 🔴 Alto — Reduce 50% del tiempo por transacción. |
| **Dificultad** | Baja |
| **Tiempo** | 6h |
| **ROI** | Máximo |

### E2. Múltiples métodos de pago en una venta
| | |
|---|---|
| **Problema** | Solo se puede elegir un método de pago por venta. En ferreterías y supermercados es común pagar parte en efectivo y parte con tarjeta. |
| **Solución** | Permitir dividir el total en varios métodos: $5000 efectivo + $3000 tarjeta. Registrar movimientos de caja separados. |
| **Impacto** | 🟡 Alto — Necesidad real en comercios. |
| **Dificultad** | Alta — impacta en Venta, MovimientoCaja, reportes. |
| **Tiempo** | 20h |
| **ROI** | Alto |

### E3. Ticket digital (WhatsApp / Email)
| | |
|---|---|
| **Problema** | El ticket solo se imprime en papel. Se pierde, se rompe, no es ecológico. |
| **Solución** | Si el cliente tiene email o teléfono, enviar ticket digital al finalizar la venta. |
| **Impacto** | 🟡 Medio — Diferenciador competitivo, ahorro en papel, traza digital. |
| **Dificultad** | Media — integrar WhatsApp API o mail. |
| **Tiempo** | 12h |
| **ROI** | Medio |

### E4. Productos frecuentes / Favoritos
| | |
|---|---|
| **Problema** | En un kiosco, el 80% de las ventas son sobre el 20% de los productos (Ley de Pareto). El cajero busca esos productos cada vez. |
| **Solución** | Sección "Más vendidos" o "Frecuentes" en la grilla del POS, calculado automáticamente. |
| **Impacto** | 🟢 Alto — Reduce tiempo de búsqueda en cada venta. |
| **Dificultad** | Media — query de productos más vendidos de la sucursal, cachear resultado. |
| **Tiempo** | 6h |
| **ROI** | Alto |

### E5. Venta pendiente / Apartado
| | |
|---|---|
| **Problema** | Cliente quiere comprar pero no tiene el dinero completo hoy. El cajero no tiene forma de apartar los productos. |
| **Solución** | Permitir guardar el carrito como "venta pendiente" (con tiempo de expiración). Al abrirse, restaurar el carrito. |
| **Impacto** | 🟡 Medio — Reduce pérdida de ventas por falta de efectivo momentáneo. |
| **Dificultad** | Alta — requiere persistir carrito, manejar expiración de stock reservado. |
| **Tiempo** | 24h |
| **ROI** | Medio |

---

## 🛡️ F — Features que Reduzcan Errores del Cajero

### F1. Confirmación de venta con resumen audible
| | |
|---|---|
| **Problema** | El cajero puede cobrar de más o de menos sin darse cuenta. No hay confirmación sonora del total. |
| **Solución** | Antes de finalizar, mostrar resumen en pantalla con total en letras grandes + opción de sonido. Confirmación con doble clic o mantener presionado. |
| **Impacto** | 🟡 Alto — Reduce errores de cobro. |
| **Dificultad** | Baja |
| **Tiempo** | 4h |
| **ROI** | Alto |

### F2. Validación de vuelto sugerido
| | |
|---|---|
| **Problema** | El cajero recibe $5000 por una compra de $3200. Debería mostrar "Vuelto: $1800" para evitar errores de cálculo. |
| **Solución** | Si método de pago es Efectivo, mostrar input "Con cuanto paga?" y calcular vuelto automático. |
| **Impacto** | 🟢 Alto — Evita errores de vuelto, el error más común en cajeros. |
| **Dificultad** | Baja |
| **Tiempo** | 3h |
| **ROI** | Máximo |

### F3. Alerta de producto duplicado en carrito
| | |
|---|---|
| **Problema** | Si el cajero escanea dos veces el mismo producto, se incrementa la cantidad. El comportamiento actual es correcto (incrementa en lugar de duplicar), pero no hay feedback visual claro. |
| **Solución** | Animación/sonido cuando se incrementa un producto existente vs cuando se agrega uno nuevo. |
| **Impacto** | 🟢 Bajo — Reduce confusión. |
| **Dificultad** | Baja |
| **Tiempo** | 2h |
| **ROI** | Medio |

### F4. Modo entrenamiento / sandbox
| | |
|---|---|
| **Problema** | Los cajeros nuevos aprenden en el sistema real donde los errores cuestan dinero. No hay un modo de práctica. |
| **Solución** | Botón "Modo Práctica" que simula ventas sin guardar nada. |
| **Impacto** | 🟢 Alto — Reduce errores de cajeros nuevos. |
| **Dificultad** | Media — flag en sesión que evita writes. |
| **Tiempo** | 8h |
| **ROI** | Alto |

---

## 🏪 Escenarios Reales — Análisis por Tipo de Comercio

### Kiosco
| Característica | Bien | Mal |
|---|---|---|
| Velocidad | Escaneo + Enter funciona | Cada clic extra cuenta. Sin atajos de teclado. |
| Variedad | Cientos de productos, todos visibles en grilla | Sin "favoritos" para los más vendidos (cigarrillos, bebidas, snacks). |
| Fiado | Crédito por cliente | Sin alerta de vencimiento de deuda. |
| **Conclusión** | Aceptable para kiosco chico. Para uno con mucho movimiento, faltan atajos y quick-sale. |

### Ferretería
| Característica | Bien | Mal |
|---|---|---|
| Productos por Kg | Soporta códigos de balanza | 5 clics para vender por Kg, tedioso con clavos/tornillos. |
| Precios | Precio venta fijo | Sin descuentos por volumen (ej: "10% off en 100+ unidades"). |
| Fiado | Cuenta corriente | Constructor paga a 30 días, necesita reporte de deuda agrupado por obra. |
| **Conclusión** | Funcional pero lento para productos fraccionables. Sin descuentos por cantidad. |

### Supermercado pequeño
| Característica | Bien | Mal |
|---|---|---|
| Lotes y vencimiento | FIFO tracking casi perfecto | Sin alerta de productos próximos a vencer en el POS. |
| Balanzas | Códigos PLU soportados | Flujo lento para verdulería/carnicería. |
| Variedad (10k+ productos) | Arquitectura multi-sucursal | Sin lazy loading → POS no escala a 10k productos. |
| **Conclusión** | DONDE MÁS DUELE: no escala a catálogos grandes. El POS se volvería inusable con 5000+ productos. |

### Farmacia
| Característica | Bien | Mal |
|---|---|---|
| Tracking de lotes | FIFO perfecto, registro en detalle_venta_lote | CRÍTICO: Sin número de lote/trazabilidad en el ticket. Para farmacias es obligatorio por ANMAT. |
| Obras sociales | — | No soportado. |
| **Conclusión** | No usable para farmacia sin tracking de lote en ticket y soporte de obras sociales. |

### Tienda de ropa
| Característica | Bien | Mal |
|---|---|---|
| Variantes | — | Sin talle/color. Cada variante requiere un producto separado. |
| Temporada | Liquidación por lote existente | Sin gestión de colecciones/temporadas. |
| **Conclusión** | No apta para indumentaria sin soporte de variantes (talle/color). La liquidación por lote ayuda para cambio de temporada. |

---

## 📊 Comparativa contra POS Modernos

| Feature | VendAR | Odoo POS | Loyverse | Square POS | Shopify POS |
|---|---|---|---|---|---|
| Venta rápida (1 clic) | ❌ | ✅ | ✅ | ✅ | ✅ |
| Atajos de teclado | ❌ | ⚠️ Parcial | ✅ F1-F12 | ✅ | ✅ |
| Búsqueda server-side | ❌ | ✅ | ✅ | ✅ | ✅ |
| Múltiples métodos de pago | ❌ | ✅ | ✅ | ✅ | ✅ |
| Ticket digital | ❌ | ✅ | ✅ | ✅ | ✅ |
| Impresión térmica 58/80mm | ✅ | ✅ | ✅ | ✅ | ✅ |
| Códigos de balanza | ✅ | ✅ | ✅ | ✅ | ✅ |
| Lector de cámara | ✅ | ❌ | ❌ | ❌ | ❌ |
| Tracking FIFO por lote | ✅ | ✅ | ❌ | ❌ | ❌ |
| Stock negativo configurable | ✅ | ✅ | ❌ | ❌ | ✅ |
| Ventas pendientes / apartados | ❌ | ✅ | ⚠️ | ❌ | ❌ |
| Variantes (talle/color) | ❌ | ✅ | ✅ | ❌ | ✅ |
| Descuento por línea | ❌ | ✅ | ✅ | ✅ | ✅ |
| Vuelto sugerido | ❌ | ✅ | ✅ | ✅ | ✅ |
| Obras sociales | ❌ | ⚠️ | ❌ | ❌ | ❌ |
| Offline-first | ❌ | ❌ | ✅ | ✅ | ⚠️ |
| Multi-tienda offline | ❌ | ❌ | ✅ | ✅ | ⚠️ |
| App mobile POS | ❌ | ✅ | ✅ | ✅ | ✅ |
| Programa de fidelidad | ❌ | ✅ | ✅ | ✅ | ✅ |
| **Fortaleza VendAR** | ✅ Multi-tenant + ✅ FIFO lotes + ✅ Auditoría completa |
| **Debilidad VendAR** | ❌ UX lenta + ❌ Sin escalabilidad + ❌ Sin features avanzados |

---

## 🏆 Top 20 Priorizadas por Relación Impacto/Esfuerzo

| # | Mejora | Tipo | Impacto | Dificultad | Tiempo | ROI |
|---|---|---|---|---|---|---|
| 1 | **A1 — Normalizar método de pago** | Bug 🔴 | 🔴 Crítico | 🟢 Baja | 1h | ★★★★★ |
| 2 | **A2 — Fix búsqueda LIKE en ID** | Bug 🔴 | 🔴 Crítico | 🟢 Baja | 1h | ★★★★★ |
| 3 | **F2 — Vuelto sugerido** | Feature 🛡️ | 🟢 Alto | 🟢 Baja | 3h | ★★★★★ |
| 4 | **C1 — Atajos de teclado** | UX 🟢 | 🟢 Alto | 🟢 Baja | 3h | ★★★★★ |
| 5 | **E1 — Quick sale / escaneo continuo** | Feature 💰 | 🔴 Alto | 🟢 Baja | 6h | ★★★★★ |
| 6 | **D1 — Lazy loading productos** | Rendimiento 🔴 | 🔴 Crítico | 🟡 Media | 12h | ★★★★★ |
| 7 | **A3 — Validar cuenta corriente activa** | Bug 🔴 | 🔴 Crítico | 🟢 Baja | 30min | ★★★★☆ |
| 8 | **A4 — Unificar apertura de turno** | Bug 🔴 | 🔴 Alto | 🟡 Media | 3h | ★★★★☆ |
| 9 | **B4 — Validar total en backend** | Bug 🟡 | 🟡 Medio | 🟡 Media | 2h | ★★★★☆ |
| 10 | **C2 — Mejorar flujo venta por Kg** | UX 🟢 | 🟢 Alto | 🟡 Media | 8h | ★★★★☆ |
| 11 | **B1 — Setear sucursal_id en turno** | Bug 🟡 | 🟡 Medio | 🟢 Baja | 30min | ★★★★☆ |
| 12 | **D3 — Índices en movimientos_stock** | Rendimiento 🟡 | 🟡 Medio | 🟢 Baja | 1h | ★★★★☆ |
| 13 | **E4 — Productos frecuentes** | Feature 💰 | 🟢 Alto | 🟡 Media | 6h | ★★★★☆ |
| 14 | **F4 — Modo entrenamiento** | Feature 🛡️ | 🟢 Alto | 🟡 Media | 8h | ★★★☆☆ |
| 15 | **C3 — Búsqueda server-side** | UX 🟢 | 🟢 Alto | 🟡 Media | 8h | ★★★☆☆ |
| 16 | **D2 — Lazy loading clientes** | Rendimiento 🟡 | 🟡 Medio | 🟢 Baja | 4h | ★★★☆☆ |
| 17 | **F1 — Confirmación con resumen** | Feature 🛡️ | 🟡 Alto | 🟢 Baja | 4h | ★★★☆☆ |
| 18 | **E2 — Múltiples métodos de pago** | Feature 💰 | 🟡 Alto | 🔴 Alta | 20h | ★★☆☆☆ |
| 19 | **E3 — Ticket digital** | Feature 💰 | 🟡 Medio | 🟡 Media | 12h | ★★☆☆☆ |
| 20 | **E5 — Ventas pendientes** | Feature 💰 | 🟡 Medio | 🔴 Alta | 24h | ★★☆☆☆ |

---

## 📋 Resumen de Hallazgos

| Categoría | Cantidad | Rojo 🔴 | Amarillo 🟡 | Verde 🟢 |
|---|---|---|---|---|
| Bugs críticos | 4 | 4 | 0 | 0 |
| Bugs medios | 4 | 0 | 4 | 0 |
| Mejoras UX | 7 | 0 | 0 | 7 |
| Mejoras rendimiento | 4 | 1 | 2 | 1 |
| Features ventas | 5 | 1 | 4 | 0 |
| Features errores | 4 | 0 | 2 | 2 |
| **Total** | **28** | **6** | **12** | **10** |

### Acción recomendada inmediata (Sprint 1 — 1 semana)
1. A1 — Normalizar método de pago (1h)
2. A2 — Fix búsqueda LIKE en ID (1h)
3. A3 — Validar cuenta corriente activa (30min)
4. B4 — Validar total en backend (2h)
5. C1 — Atajos de teclado básicos (3h)
6. F2 — Vuelto sugerido (3h)
7. D3 — Índices en movimientos_stock (1h)
8. B1 — Setear sucursal_id en turno (30min)

**Total Sprint 1: ~12 horas — 8 mejoras con ROI más alto.**
