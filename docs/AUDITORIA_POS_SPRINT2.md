# Auditoría POS Sprint 2 — VendAR

> Versión: 1.0 — Junio 2026
> Alcance: Auditoría funcional completa post-Sprint 1, previo a implementaciones Sprint 2
> Base: FASE 8 completada (7 tareas + db:fix-sequences)

---

## Resumen Ejecutivo

Sprint 1 resolvió los 4 bugs críticos y 4 mejoras top del Sprint 1 original. Esta auditoría revisa el estado actual del POS (backend + frontend) para identificar qué queda por hacer en Sprint 2 y determinar prioridades.

**Estado actual:** 61/61 tests pasando. POS funcional para kiosco chico/mediano. Bugs críticos de Sprint 1 resueltos. Queda mejorar UX, escalabilidad y features de pago.

---

## ✅ Sprint 1 — Resuelto

| # | Mejora | Estado | Commit |
|---|---|---|---|
| A1 | Normalizar método de pago | ✅ Enum `MetodoPago`, accessors `metodo_pago_display`, normalizado en VentaController/CajaDiariaController/ConsumidorController | `f17056f` |
| A2 | Búsqueda LIKE en ID | ✅ 6 controladores corregidos: si search es numérico → búsqueda exacta por ID | `f17056f` |
| A3 | Validar cuenta corriente activa | ✅ Resuelto en FASE 5 (`lockForUpdate` + `estado = true`) | `5ac5cef` |
| B1 | Setear `sucursal_id` en turno | ✅ `PosController@abrirTurno` ahora setea `sucursal_id` | `1b16075` |
| C1 | Atajos de teclado | ✅ F1-F8 para métodos de pago, F9=Cobrar, ESC=limpiar | `f17056f` |
| F2 | Vuelto sugerido | ✅ Input "Recibido ($)" + 4 botones sugerencia + cálculo automático | `f17056f` |
| E1 | Quick sale | ✅ Eliminado Swal warning en escaneo, auto-focus garantizado | `f17056f` |
| A4 | Unificar apertura de turno | ✅ `PosController@abrirTurno` completo con `MovimientoCaja` FONDO_INICIAL, `DB::transaction` | `1b16075` |
| — | `db:fix-sequences` | ✅ Nuevo comando Artisan para PostgreSQL | `1b16075` |

---

## 🔴 A — Bugs Críticos (Pendientes)

### A5. Swal loading nunca se cierra en error de venta
| | |
|---|---|
| **Dónde** | `Terminal.vue:282-288` — `finalizarVenta()`, `onError` callback |
| **Problema** | `Swal.showLoading()` se ejecuta al enviar la venta. Si el `router.post` falla, el `onError` llama `Swal.fire(...)` **sin cerrar el loading primero**. El loading spinner queda detrás del error Swal. |
| **Impacto** | 🔴 Crítico — El usuario ve dos Swals superpuestos, no puede cerrar el error limpiamente. |
| **Solución** | Llamar `Swal.close()` antes de mostrar el error. |
| **Dificultad** | 🟢 Baja — 1 línea |
| **Tiempo** | 5min |

### A6. Polling de CajaDiaria inunda de toasts
| | |
|---|---|
| **Dónde** | `CajaDiaria/Index.vue:279` — `checkNuevosMovimientos()` |
| **Problema** | Cada 15s se consultan movimientos nuevos. Si hay N movimientos desde el último check, se lanzan N toasts `Swal.fire` simultáneos. En horas pico (ej: 5 ventas en 15s), aparecen 5 toasts uno encima del otro. |
| **Impacto** | 🔴 Crítico — UX disruptiva, el cajero no puede trabajar con toasts constantes. |
| **Solución** | Acumular detecciones y mostrar un solo toast "X nuevos movimientos". O directamente no mostrar toast y solo refrescar los datos. |
| **Dificultad** | 🟢 Baja |
| **Tiempo** | 30min |

### A7. ModalDetalleVenta: `'anulada'` nunca se cumple
| | |
|---|---|
| **Dónde** | `ModalDetalleVenta.vue:47` — `venta?.estado === 'anulada'` |
| **Problema** | El backend usa `'Cancelada'` y `'Completada'` como valores de `ventas.estado` (CHECK constraint en DB). El template revisa `'anulada'` (minúscula). La condición **nunca** se cumple. El badge de anulación no se muestra. |
| **Impacto** | 🔴 Medio — Detalle de venta no muestra correctamente el estado de ventas canceladas. |
| **Solución** | Cambiar `'anulada'` → `'Cancelada'` |
| **Dificultad** | 🟢 Baja |
| **Tiempo** | 5min |

### A8. LectorCamara: fuga de instancias al cambiar cámara
| | |
|---|---|
| **Dónde** | `LectorCamara.vue:37-39` — `iniciarEscaneo()` |
| **Problema** | Cada llamada a `iniciarEscaneo()` crea un nuevo `new Html5Qrcode("lector-codigo")` sin destruir la instancia anterior. Al cambiar de cámara (select `@change="iniciarEscaneo"`), la cámara anterior sigue activa. Múltiples streams de cámara consumen recursos. |
| **Impacto** | 🔴 Medio — Fuga de recursos, posible crash del navegador en dispositivos con poca memoria. |
| **Solución** | Llamar `html5QrCode.value?.stop()` antes de crear nueva instancia. |
| **Dificultad** | 🟢 Baja |
| **Tiempo** | 15min |

### A9. LectorCamara: AudioContext creado en cada escaneo
| | |
|---|---|
| **Dónde** | `LectorCamara.vue:77` — `hacerSonidoBeep()` |
| **Problema** | Cada escaneo exitoso crea un `new AudioContext()`. La Web Audio API recomienda reusar una sola instancia. Múltiples contextos causan memory leaks y glitches de audio en algunos navegadores. |
| **Impacto** | 🔴 Bajo — Degradación progresiva con muchos escaneos. |
| **Solución** | Crear un `AudioContext` singleton, reusarlo en cada escaneo. |
| **Dificultad** | 🟢 Baja |
| **Tiempo** | 15min |

### A10. LectorCamara: sin feedback si la cámara falla
| | |
|---|---|
| **Dónde** | `LectorCamara.vue:28` — catch de `Html5Qrcode.getCameras()` |
| **Problema** | Si el usuario deniega permisos de cámara, la promesa rechaza, el catch captura el error pero no setea `errorCamara.value`. El componente muestra "Iniciando cámara..." indefinidamente. |
| **Impacto** | 🔴 Medio — Usuario no sabe por qué no funciona la cámara, no hay botón de reintentar. |
| **Solución** | Setear `errorCamara` con mensaje descriptivo cuando falla la enumeración/inicio de cámara. |
| **Dificultad** | 🟢 Baja |
| **Tiempo** | 15min |

---

## 🟡 B — Bugs Medios (Pendientes)

### B5. `consumidor_id: ''` en lugar de `null`
| | |
|---|---|
| **Dónde** | `CajaDiaria/Index.vue:47-52` — `formGasto.consumidor_id` |
| **Problema** | El formulario de movimiento manual setea `consumidor_id: ''`. Al enviar, el backend recibe string vacío en lugar de `null`. Si la columna espera `null` o `integer`, PostgreSQL puede rechazar el string vacío o convertirlo incorrectamente. |
| **Impacto** | 🟡 Medio — Potencial error al registrar gastos/ingresos con consumidor opcional. |
| **Solución** | Normalizar `consumidor_id` antes de enviar: si es `''`, convertir a `null`. |
| **Dificultad** | 🟢 Baja |
| **Tiempo** | 15min |

### B6. `permitirStockNegativo` sin null safety
| | |
|---|---|
| **Dónde** | `Terminal.vue:16-18` — `permitirStockNegativo` computed |
| **Problema** | Lee `page.props.empresa?.permitir_stock_negativo`. Si `empresa` es `null` o `undefined` (sesión corrupta, usuario sin empresa asignada), el optional chaining devuelve `undefined`, el `||` lo convierte en `false`. No crashes, pero **el cómputo asume que `empresa` existe**. Si `empresa` no se pasa desde el backend, todas las props hijas de `empresa` son `undefined`. Podría haber un crash antes si se accede a `empresa.id` en otro lado, pero acá está protegido con `?.` |
| **Impacto** | 🟡 Bajo — Seguro por optional chaining, pero oculta un setup incorrecto del backend. |
| **Solución** | Validar en backend que `empresa` siempre se pase en `HandleInertiaRequests.php`. |
| **Dificultad** | 🟢 Baja |
| **Tiempo** | 30min |

### B7. `totalVenta` calculado en frontend, no verificado en backend
| | |
|---|---|
| **Dónde** | `Terminal.vue:59-61` calcula total → `VentaController@store` no lo recalcula |
| **Problema** | El total se calcula en frontend y se envía como parámetro. El backend no verifica que `total == sum(items.precio_venta * items.cantidad)`. Un request manipulado podría crear ventas con montos adulterados. Ya identificado en auditoría Sprint 1 (B4), **aún no resuelto**. |
| **Impacto** | 🟡 Medio — Potencial manipulación de montos. |
| **Solución** | Recalcular total en backend antes de guardar. Ignorar el total enviado. |
| **Dificultad** | 🟡 Media |
| **Tiempo** | 2h |

### B8. Anulación con motivo `"otro"` sin texto real
| | |
|---|---|
| **Dónde** | `Ventas/Index.vue:87-89` — `inputOptions` |
| **Problema** | El select de motivos incluye `'otro'` como opción, pero **no hay input de texto libre** cuando se selecciona. La anulación queda con motivo literal `"otro"`. |
| **Impacto** | 🟡 Medio — Trazabilidad de anulaciones deficiente. |
| **Solución** | Mostrar input de texto cuando se selecciona "Otro...", enviar ese texto como motivo. |
| **Dificultad** | 🟢 Baja |
| **Tiempo** | 1h |

### B9. Sin validación de consumidor activo en fiado
| | |
|---|---|
| **Dónde** | `VentaController@store` |
| **Problema** | Se valida que `consumidor_id` exista y tenga límite, pero **no se verifica `estado = true`** del consumidor. Un consumidor desactivado puede recibir fiado. Ya identificado en Sprint 1 (B3), **aún no resuelto**. |
| **Impacto** | 🟡 Medio — Cliente desactivado puede generar deuda. |
| **Solución** | Agregar `->where('estado', true)` al lookup del consumidor. |
| **Dificultad** | 🟢 Baja |
| **Tiempo** | 30min |

---

## 🟢 C — Mejoras UX

### C8. Sin loading state en Ventas/Index al cambiar filtros
| | |
|---|---|
| **Dónde** | `Ventas/Index.vue` — watcher de `formFiltros` |
| **Problema** | Al cambiar filtros, el debounce de 300ms espera y dispara `router.get` con `preserveState: true`. La tabla se congela hasta que llega la respuesta. No hay spinner ni skeleton. |
| **Impacto** | 🟢 Medio — El usuario no sabe si la página está cargando. |
| **Dificultad** | 🟢 Baja |
| **Tiempo** | 1h |

### C9. Sin foco en modales ni cierre con ESC
| | |
|---|---|
| **Dónde** | `CajaDiaria/Index.vue` (3 modales), `ModalDetalleVenta.vue` |
| **Problema** | Ningún modal tiene focus trap. Se puede tabular fuera del modal e interactuar con elementos de fondo. Tampoco hay handler de tecla ESC para cerrar. |
| **Impacto** | 🟢 Medio — Accesibilidad pobre, riesgo de acciones accidentales en background. |
| **Solución** | Agregar focus trap + `@keydown.escape` handler en cada modal. |
| **Dificultad** | 🟡 Media |
| **Tiempo** | 3h |

### C10. Polling de CajaDiaria no se pausa en background
| | |
|---|---|
| **Dónde** | `CajaDiaria/Index.vue` — `setInterval` polling |
| **Problema** | El intervalo de 15s sigue corriendo aunque el usuario tenga la pestaña en background. Requests innecesarios al servidor. |
| **Impacto** | 🟢 Bajo — Carga innecesaria. |
| **Solución** | Usar Page Visibility API para pausar/reanudar polling. |
| **Dificultad** | 🟢 Baja |
| **Tiempo** | 1h |

### C11. Polling de CajaDiaria resetea página de movimientos
| | |
|---|---|
| **Dónde** | `CajaDiaria/Index.vue:263` — `cargarDatosCajaAbierta()` |
| **Problema** | Cada refresh del polling resetea `currentPageMovs` a 1. Si el usuario está viendo la página 3 de movimientos, cada 15s vuelve a página 1. |
| **Impacto** | 🟡 Medio — UX frustrante al navegar el historial de movimientos. |
| **Solución** | No resetear `currentPageMovs` en polling; solo resetear al abrir/cerrar caja. |
| **Dificultad** | 🟢 Baja |
| **Tiempo** | 30min |

### C12. Sin indicador de turno activo en header
| | |
|---|---|
| **Dónde** | `AuthenticatedLayout.vue` / `resources/js/Layouts/` |
| **Problema** | El cajero no ve si tiene turno abierto sin entrar al POS. Puede estar en otra sección y olvidar si abrió turno. Ya identificado en Sprint 1 (C6). |
| **Impacto** | 🟢 Bajo |
| **Solución** | Badge/pill en header global con "Turno #X activo" (consultar endpoint `/api/sesiones-caja/actual`). |
| **Dificultad** | 🟡 Media |
| **Tiempo** | 2h |

### C13. Sin feedback visual de escaneo de código
| | |
|---|---|
| **Dónde** | `Terminal.vue` — manejo de códigos de barra |
| **Problema** | Cuando se escanea un código válido, el producto se agrega al carrito sin feedback visual ni sonoro (aparte de contar con el sonido de LectorCamara si se usa cámara). Si el código no existe, el input simplemente se limpia y refocusa. El usuario no sabe si el escaneo funcionó o no. |
| **Impacto** | 🟢 Medio — En ambiente ruidoso (kiosco/ferretería), sin sonido el cajero no sabe si el código se leyó. |
| **Solución** | Feedback visual breve (flash verde en producto agregado, flash rojo si no encontrado) + sonido de éxito/error. |
| **Dificultad** | 🟢 Baja |
| **Tiempo** | 2h |

---

## 🔵 D — Mejoras de Rendimiento

### D5. `formatearMoneda`/`formatearDinero`/`formatearFecha` duplicados
| | |
|---|---|
| **Dónde** | `CajaDiaria/Index.vue`, `ModalDetalleVenta.vue`, `Ventas/Index.vue` |
| **Problema** | 3 implementaciones distintas de formateo de moneda y 2 de fecha, con lógica inconsistente (una usa `toLocaleString`, otra `Intl.NumberFormat`). |
| **Impacto** | 🔵 Medio — Mantenimiento difícil, inconsistencias visuales potenciales. |
| **Solución** | Extraer a `resources/js/Utils/formatters.js` y compartir. |
| **Dificultad** | 🟢 Baja |
| **Tiempo** | 1h |

### D6. Paginación duplicada 3 veces en CajaDiaria
| | |
|---|---|
| **Dónde** | `CajaDiaria/Index.vue` — 3 bloques de paginación manual (cajas historial, movimientos activos, movimientos historial modal) |
| **Problema** | El mismo patrón (Anterior/Siguiente, currentPage, totalPages, paginated slice) repetido textualmente 3 veces. |
| **Impacto** | 🔵 Medio — Código repetido, difícil de mantener, propenso a bugs. |
| **Solución** | Crear componente `Pagination.vue` reutilizable. |
| **Dificultad** | 🟢 Baja |
| **Tiempo** | 2h |

### D7. `abrirCaja` duplicado en dos componentes
| | |
|---|---|
| **Dónde** | `AperturaTurno.vue` y `CajaDiaria/Index.vue` |
| **Problema** | La misma lógica de apertura de turno existe en dos componentes con APIs distintas (Axios + redirect vs Axios + reinicializar). Pueden divergir. |
| **Impacto** | 🟡 Medio — Riesgo de regresión al modificar una. |
| **Solución** | Composable `useCajaDiaria` o extraer lógica a un store compartido. |
| **Dificultad** | 🟡 Media |
| **Tiempo** | 3h |

### D8. Valores hardcodeados de moneda y formato
| | |
|---|---|
| **Dónde** | `ModalDetalleVenta.vue` (`currency: 'ARS'`), `Terminal.vue` (`sugerencias` base ARS), hardcode de `padStart(6, '0')` |
| **Problema** | Tenants con otras monedas (USD, UYU) no pueden cambiar el formato sin modificar código. El padding de ticket a 6 dígitos puede no ser configurable por tenant. |
| **Impacto** | 🟢 Bajo — Solo afecta si hay multi-moneda. |
| **Solución** | Pasar config desde backend (`HandleInertiaRequests.php`) y usar en componentes. |
| **Dificultad** | 🟢 Baja |
| **Tiempo** | 4h |

---

## 💰 E — Features (Sprint 2 Scope)

### PARTE B — Pago múltiple por venta (E2 de auditoría original)
| | |
|---|---|
| **Problema** | Solo se puede elegir un método de pago. En la práctica, clientes pagan con efectivo + tarjeta, o efectivo + mercadopago, etc. |
| **Solución** | Permitir dividir el total en N métodos de pago con montos parciales. Cada método genera su `MovimientoCaja`. |
| **Impacto** | 🟡 Alto — Necesidad real en comercios. |
| **Dificultad** | 🔴 Alta — Impacta VentaController, Venta model, MovimientoCaja, Terminal.vue, CajaDiaria, reportes. |
| **Tiempo** | ~20h |
| **ROI** | Alto |

### PARTE C — Ticket digital (E3 de auditoría original)
| | |
|---|---|
| **Problema** | Solo se imprime ticket en papel. Cliente no recibe copia digital. |
| **Solución** | Si el cliente tiene email/teléfono, enviar ticket (PDF o texto) al finalizar. WhatsApp y/o Email. |
| **Impacto** | 🟡 Medio — Diferenciador, ahorro papel, trazabilidad. |
| **Dificultad** | 🟡 Media — Integrar API de WhatsApp/Email. |
| **Tiempo** | ~12h |

### PARTE D — Historial rápido de caja en POS
| | |
|---|---|
| **Problema** | El cajero debe salir del POS e ir a CajaDiaria para ver movimientos del turno. Debería poder verlos sin salir. |
| **Solución** | Mini panel colapsable en Terminal.vue que muestre movimientos del turno actual. |
| **Impacto** | 🟢 Alto — Reduce fricción, el cajero ve su caja en tiempo real. |
| **Dificultad** | 🟡 Media |
| **Tiempo** | ~8h |

### PARTE E — Server-side search (C3/D1 de auditoría original)
| | |
|---|---|
| **Problema** | Productos y clientes se cargan completos en memoria. Con 10k+ productos, el payload de Inertia supera 5-10MB y la UI se congela. |
| **Solución** | Endpoint Ajax con búsqueda server-side + debounce en frontend. Cargar solo primeros 50 resultados. |
| **Impacto** | 🔴 Crítico — Escalabilidad. Sin esto, POS no sirve para catálogos medianos/grandes. |
| **Dificultad** | 🟡 Media |
| **Tiempo** | ~12h |
| **ROI** | Máximo |

---

## 📊 Priorización Sprint 2

### Bugs críticos (urgencia)
| # | Item | Tipo | Tiempo | Prioridad |
|---|---|---|---|---|
| 1 | A5 — Swal loading sin cerrar en error | Bug 🔴 | 5min | ★★★★★ |
| 2 | A7 — ModalDetalle `'anulada'` nunca se cumple | Bug 🔴 | 5min | ★★★★★ |
| 3 | A8 — LectorCamara fuga de instancias | Bug 🔴 | 15min | ★★★★★ |
| 4 | A9 — AudioContext por escaneo | Bug 🔴 | 15min | ★★★★☆ |
| 5 | A10 — Cámara falla sin feedback | Bug 🔴 | 15min | ★★★★☆ |
| 6 | A6 — Polling inunda de toasts | Bug 🔴 | 30min | ★★★★★ |

### Bugs medios + housekeeping
| # | Item | Tipo | Tiempo | Prioridad |
|---|---|---|---|---|
| 7 | B7 — Validar total en backend | Bug 🟡 | 2h | ★★★★☆ |
| 8 | B9 — Validar consumidor activo en fiado | Bug 🟡 | 30min | ★★★★☆ |
| 9 | B5 — `consumidor_id: ''` en gasto | Bug 🟡 | 15min | ★★★☆☆ |
| 10 | B8 — Anulación motivo "otro" sin texto | Bug 🟡 | 1h | ★★★☆☆ |
| 11 | B6 — Null safety `permitirStockNegativo` | Bug 🟡 | 30min | ★★☆☆☆ |

### Refactors (habilitantes)
| # | Item | Tipo | Tiempo | Prioridad |
|---|---|---|---|---|
| 12 | D5 — Unificar formatters en Utils/ | Refactor 🔵 | 1h | ★★★★☆ |
| 13 | D6 — Pagination.vue reutilizable | Refactor 🔵 | 2h | ★★★★☆ |
| 14 | D7 — Composable `useCajaDiaria` | Refactor 🔵 | 3h | ★★★☆☆ |
| 15 | C9 — Focus trap + ESC en modales | UX 🟢 | 3h | ★★★☆☆ |
| 16 | D8 — Config moneda desde backend | Refactor 🔵 | 4h | ★★☆☆☆ |

### UX restante
| # | Item | Tipo | Tiempo | Prioridad |
|---|---|---|---|---|
| 17 | C11 — No resetear página en polling | UX 🟡 | 30min | ★★★★☆ |
| 18 | C10 — Pausar polling en background | UX 🟢 | 1h | ★★★☆☆ |
| 19 | C8 — Loading en Ventas/Index | UX 🟢 | 1h | ★★★☆☆ |
| 20 | C13 — Feedback visual escaneo | UX 🟢 | 2h | ★★★☆☆ |
| 21 | C12 — Indicador turno activo header | UX 🟢 | 2h | ★★☆☆☆ |

### Features Sprint 2
| # | Item | Tipo | Tiempo | Prioridad |
|---|---|---|---|---|
| 22 | PARTE E — Server-side search | Feature 🔵 | 12h | ★★★★★ |
| 23 | PARTE D — Historial caja en POS | Feature 💰 | 8h | ★★★★☆ |
| 24 | PARTE C — Ticket digital | Feature 💰 | 12h | ★★★☆☆ |
| 25 | PARTE B — Pago múltiple | Feature 💰 | 20h | ★★★☆☆ |

---

## 📋 Resumen de Hallazgos Sprint 2

| Categoría | Cantidad | Rojo 🔴 | Amarillo 🟡 | Verde 🟢 | Azul 🔵 |
|---|---|---|---|---|---|
| Bugs críticos | 6 | 6 | 0 | 0 | 0 |
| Bugs medios | 5 | 0 | 5 | 0 | 0 |
| Mejoras UX | 6 | 0 | 1 | 5 | 0 |
| Refactors | 5 | 0 | 0 | 0 | 5 |
| Features (Sprint 2) | 4 | 0 | 2 | 0 | 2 |
| **Total** | **26** | **6** | **8** | **5** | **7** |

### Estado General del POS post-Sprint 1
- ✅ Bugs críticos originales: **resueltos** (A1-A4)
- 🟡 Bugs críticos nuevos: **6 pendientes** (A5-A10, todos frontend, todos rápidos)
- 🟡 Bugs medios: **5 pendientes** (B5-B9)
- 🟢 UX: **6 mejoras pendientes**
- 🔵 Refactors: **5 pendientes** (formatters, pagination, composable)
- 💰 Features: **4 pendientes** (B-E)

### Acción recomendada inmediata
1. Arreglar los 6 bugs críticos frontend (A5-A10) — ~1.5h total
2. Arreglar bugs medios (B7, B9, B5, B8) — ~4h total
3. Refactors habilitantes (D5, D6, D7) — ~6h total
4. Features por orden de ROI: Server-side search (E) → Historial caja (D) → Ticket digital (C) → Pago múltiple (B)
