# F13 - Rediseño visual del POS (Terminal.vue)

## Objetivo
Rediseñar la pantalla POS de VendAR para el uso en cajeros: invertir la distribución a ~65-70% de superficie para productos (buscador y grilla más grandes) y ~30-35% para el panel de venta, compactar el carrito y los métodos de pago, fijar Total + COBRAR siempre visible, agregar información del turno en pantalla y eliminar código muerto, **sin modificar ninguna regla de negocio** (ventas, stock, promociones, pagos, recargos, cuentas corrientes, facturación, tickets). Sin refactor gigantesco e implementación directa.

## Archivos creados
- `docs/informes/F13-rediseno-visual-pos.md` (este informe)

## Archivos modificados
- `resources/js/Pages/Pos/Terminal.vue`
  - **Script**: eliminado código muerto (`mostrarMovimientos`, `ventasPendientesPago`, `movimientosTurno`, `cargandoMovimientos`, `intervaloMovimientos`, `resumenCaja`, `fetchMovimientosTurno`, `iniciarPollingMovimientos`, `detenerPollingMovimientos`, `fetchVentasPendientesPago` y sus llamadas en `onPagoConfirmado`/`onPagoCancelado`/`onMounted`/`onUnmounted`).
  - **Script**: guard de stock en `clickEnProducto` (antes de la rama Kg): si no se permite stock negativo y `stock_actual <= 0`, muestra flash de error y corta.
  - **Script**: helpers nuevos `fmtMonto` (formato es-AR), `turnoDesdeLabel` (formatea `fecha_apertura` a "dd/MM HH:mm"), `productosVisibles` (unifica buscador/categoría/tabs/frecuentes en una sola grilla). `teclaDeMetodo` y `mostrarConfigTarjeta` ya existían; se mantienen.
  - **Template**: reemplazo completo. Cabecera del turno con chips (Turno #, Caja, Sucursal, Cajero, "Abierto {fecha}"). Buscador grande (h-14) con botón de cámara y badge ENTER. Cards de producto unificadas (imagen `aspect-square`, -% descuento, favorito, stock con colores, badge ×cantidad, sin stock deshabilitado). Grid `2/3/4/5` columnas según viewport. Panel de venta con cabecera fija (total + cantidad de productos), carrito con scroll interno, cliente con dropdown y modal de alta, estado fiscal (Factura A/B), métodos de pago (Efectivo, Cta. Corriente, dropdowns de Transferencias y Tarjetas), montos por método, configuración de tarjeta (banco/cuotas/recargo), recibido/vuelto/sugerencias, barra de progreso, asignar restante, y Total + COBRAR fijos al fondo.

## Decisiones técnicas
- Layout: `lg:col-span-7 xl:col-span-8` para productos y `lg:col-span-5 xl:col-span-4` para el panel de venta (invertido respecto del 5/12–7/12 anterior).
- Panel de venta: `h-[calc(100vh-140px)] sticky top-4` con columna flex; cabecera y pie (Total + COBRAR) `shrink-0`, zona media con scroll (`flex-1 min-h-0`) para que COBRAR nunca desaparezca.
- Se respetó la API real del script: `togglePago`, `pagos[]`, `seleccionarTransferencia`/`seleccionarTarjeta`, `bancoSeleccionado`/`cuotasSeleccionadas`/`totalConRecargo`, `montoRecibido`/`vuelto`/`sugerencias`, `esUnicoEfectivo`/`esUnicaTarjeta`/`restante`/`totalAsignado`/`esPagoCompleto`, `finalizarVenta` con `puedeCobrar` (mantiene los mensajes condicionales del botón), `LectorCamara` (`@escaneado`/`@cerrar`) y `ConfirmarPagoModal` (`:show`, `:venta-id`, `:display-info`, `@close`, `@confirmed`).
- Stock sin stock: p/sin stock se muestra deshabilitado (`opacity-55 grayscale`) y `clickEnProducto` corta el agregado; productos por peso (Kg) conservan su flujo de SweetAlert.
- `permitirStockNegativo` se lee de `empresa.permitir_stock_negativo` (mismo origen que antes).
- Datos del turno del header: `turno.caja.nombre`, `turno.sucursal.nombre`, `turno.id`, `turno.fecha_apertura` (via `turnoDesdeLabel`) y `page.props.auth.user.name`, todos con optional chaining para no romper si faltan.
- Precios display con `fmtMonto` (toLocaleString es-AR) manteniendo el cálculo con `pago.monto` (dinero real) intacto.
- No se agregaron dependencias; se usan utilidades Tailwind ya presentes (incl. `scrollbar-thin`, `line-clamp` nativas).

## Bugs corregidos
- Template anterior referenciaba estados/helpers inexistentes en el script (restos de una propuesta previa: `metodosSeleccionados`, `totalAConfirmar`, `quitarMetodo`, `payTypeFor`, etc.). El template final se reconstruyó verificando **cada identificador** contra el script real (cross-check automático, 0 faltantes).
- Polling de movimientos (`setInterval`) eliminado: consumía recursos y no se usaba en la UI.
- Productos sin stock quedaban clickeables (solo tenían un indicador visual); ahora se bloquea el agregado con flash de error cuando corresponde.

## Pruebas ejecutadas
- `npm run build`
  - Resultado: OK, `✓ built in 20.37s` (956 módulos, chunk `Terminal-CJTOYg7_.js`).
- Validación estructural del template: conteo balanceado de `<div>`/`</div>` (130/130), `<template>` raíz única, `</template>` consistentes con los `<template v-if>` internos (18/18), aperturas/cierres de `AuthenticatedLayout`, `Teleport`, `LectorCamara`, `ConfirmarPagoModal` correctos.
- Cross-check de identificadores: todos los tokens usados en bindings del template existen en el script (los `CHECK` del escáner fueron propiedades de objetos, clases CSS y literales).
- `php artisan`/backend sin cambios → no se ejecutaron tests de backend (no aplican).

## Resultados
- POS con layout apaisado optimizado para caja: productos protagonistas, panel compacto, COBRAR fijo al fondo.
- Código muerto eliminado, stock protegido, única grilla de productos unificada.
- Todos los flujos de pago/facturación intactos al nivel del template (mismos handlers que la versión anterior).

## Criterios de aceptación
VERIFICACIÓN MANUAL PENDIENTE (en navegador, resoluciones 1366×768 y 1920×1080):
- [ ] El buscador tiene autofocus al entrar; escanear un código o escribir y ENTER agrega el producto con beep y badge ×cantidad.
- [ ] Producto sin stock: se ve deshabilitado (gris) y al hacer clic muestra flash "Sin stock: ..." sin agregar.
- [ ] Producto por Kg: abre el modal de peso (Gramos/Kg) y respeta el stock disponible.
- [ ] Promoción/en liquidación: muestra el precio rebajado y el badge del %.
- [ ] Favoritos: el ❤️ togglea sin disparar el click del producto.
- [ ] Panel: el total y COBRAR quedan fijos al fondo aunque el carrito sea largo; el carrito scrollea solo.
- [ ] Cantidades: −/+ funcionan, validar en blur, no se permite stock por encima del límite salvo `permitir_stock_negativo`.
- [ ] Cliente: dropdown con búsqueda por nombre/documento, "Consumidor Final", alta rápida y aviso de Factura A cuando faltan datos fiscales.
- [ ] Efectivo: muestra Recibido, sugerencias, y Vuelto; COBRAR se habilita con monto completo (mensajes "Ingresá el monto recibido"/"Faltan $...").
- [ ] Pago mixto: se pueden sumar métodos (máx. 6), montos asignados, remover uno, barra de progreso y "Asignar restante".
- [ ] Tarjeta: elegir banco → cuotas según recargo configurado → resumen Subtotal/Recargo/Total y "Cuotas" con monto por cuota; sin banco, COBRAR avisa "Seleccioná un banco".
- [ ] Cuenta corriente: avisa "Seleccioná un cliente para fiarle", muestra crédito disponible y bloquea por saldo insuficiente.
- [ ] Transferencia: dropdown con métodos (Mercado Pago, Viumi, etc.) y estado "esperando acreditación".
- [ ] F9 dispara COBRAR; F1-F8 cambian métodos (según config); Esc limpia el monto recibido; cámara (botón) escanea y agrega.
- [ ] ConfirmarPagoModal sigue funcionando para ventas pendientes (Mercado Pago) con `@confirmed`/`@close`.
- [ ] Venta cobrada: resetea carrito/cliente/montos y vuelve el foco al buscador; edición de cantidad respeta promociones re-fetched.
- [ ] El turno en pantalla muestra Nro, Caja, Sucursal, Cajero y "Abierto {dd/MM HH:mm}".

## Pendientes de la siguiente fase
- Ejecutar la verificación manual E2E anterior en navegador.
- (Opcional) Soporte de cierre de turno desde el propio POS (hoy disponible solo en Caja Diaria), en fase separada.