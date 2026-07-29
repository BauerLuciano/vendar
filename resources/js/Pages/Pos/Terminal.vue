<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import LectorCamara from '@/Components/LectorCamara.vue';
import ConfirmarPagoModal from '@/Components/ConfirmarPagoModal.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({
    turno: Object,
    productos: Array,
    clientes: Array,
    frecuentes: Array,
    paymentMethods: Array,
    metodosBase: Array,
    recargos: Object,
    bancosDisponibles: Array,
});

const page = usePage();

const permitirStockNegativo = computed(() => {
    const val = page.props.empresa?.permitir_stock_negativo;
    return val === '1' || val === 1 || val === true;
});

const buscar = ref('');
const carrito = ref([]);

const clienteSeleccionado = ref(null); 
const busquedaCliente = ref('');
const mostrarDropdownClientes = ref(false);
const inputBusqueda = ref(null);

const mostrarEscaner = ref(false);

const mostrarMovimientos = ref(false);
const tabCajaPend = ref('caja');
const tabPendientes = ref('carritos');
const mostrarPendientes = ref(false);
const ventasPendientes = ref([]);
const ventasPendientesPago = ref([]);
const guardandoCarrito = ref(false);
const restaurandoCarrito = ref(false);

const confirmarPagoModal = ref(false);
const confirmarVentaId = ref(null);
const confirmarDisplayInfo = ref([]);
const movimientosTurno = ref([]);
const cargandoMovimientos = ref(false);
let intervaloMovimientos = null;

const resumenCaja = computed(() => {
    const ingresos = movimientosTurno.value
        .filter(m => m.tipo === 'INGRESO')
        .reduce((acc, m) => acc + m.monto, 0);
    const egresos = movimientosTurno.value
        .filter(m => m.tipo === 'EGRESO')
        .reduce((acc, m) => acc + m.monto, 0);
    return { ingresos, egresos, saldo: ingresos - egresos };
});

async function fetchMovimientosTurno() {
    cargandoMovimientos.value = true;
    try {
        const response = await fetch('/pos/movimientos-turno');
        if (response.ok) {
            movimientosTurno.value = await response.json();
        }
    } catch (e) {
        console.error('Error al cargar movimientos:', e);
    } finally {
        cargandoMovimientos.value = false;
    }
}

function iniciarPollingMovimientos() {
    fetchMovimientosTurno();
    intervaloMovimientos = setInterval(fetchMovimientosTurno, 30000);
}

async function guardarCarrito() {
    if (carrito.value.length === 0) return;
    guardandoCarrito.value = true;
    try {
        const items = carrito.value.map(i => ({
            id: i.id,
            nombre: i.nombre,
            cantidad: i.cantidad,
            precio_venta: i.precio_venta,
        }));
        const response = await fetch('/pos/guardar-carrito', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': page.props.csrf_token },
            body: JSON.stringify({
                items,
                consumidor_id: clienteActivoObj.value?.id || null,
            }),
        });
        if (response.ok) {
            carrito.value = [];
            clienteSeleccionado.value = null;
            montoRecibido.value = null;
            pagos.value = [{ metodo_pago: 'EFECTIVO', monto: null }];
            await fetchPendientes();
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Carrito guardado', showConfirmButton: false, timer: 2000 });
        }
    } catch (e) {
        console.error('Error al guardar carrito:', e);
    } finally {
        guardandoCarrito.value = false;
    }
}

async function fetchPendientes() {
    try {
        const response = await fetch('/pos/listar-pendientes');
        if (response.ok) {
            ventasPendientes.value = await response.json();
        }
    } catch (e) {}
}

async function restaurarPendiente(p) {
    restaurandoCarrito.value = true;
    try {
        const response = await fetch(`/pos/recuperar-carrito/${p.id}`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': page.props.csrf_token },
        });
        if (response.ok) {
            const data = await response.json();
            carrito.value = data.items.map(i => ({
                ...i,
                precio_venta: Number(i.precio_venta),
                cantidad: Number(i.cantidad),
            }));
            if (data.consumidor_id) {
                const c = props.clientes.find(c => c.id === data.consumidor_id);
                if (c) clienteSeleccionado.value = c;
            }
            ventasPendientes.value = ventasPendientes.value.filter(v => v.id !== p.id);
            mostrarPendientes.value = false;
            debouncedFetchPrecios();
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Carrito restaurado', showConfirmButton: false, timer: 2000 });
        }
    } catch (e) {
        console.error('Error al restaurar:', e);
    } finally {
        restaurandoCarrito.value = false;
    }
}

async function eliminarPendiente(p) {
    try {
        const response = await fetch(`/pos/eliminar-pendiente/${p.id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': page.props.csrf_token },
        });
        if (response.ok) {
            ventasPendientes.value = ventasPendientes.value.filter(v => v.id !== p.id);
        }
    } catch (e) {}
}

function detenerPollingMovimientos() {
    if (intervaloMovimientos) {
        clearInterval(intervaloMovimientos);
        intervaloMovimientos = null;
    }
}

const METODOS_DISPONIBLES = computed(() => {
    const base = (props.metodosBase || []).map(m => ({
        value: m.value,
        label: m.label,
    }));

    const manual = (props.paymentMethods || []).map(pm => ({
        value: pm.metodo_pago,
        label: pm.label,
        paymentMethodConfigId: pm.id,
        provider: pm.provider,
        display_data: pm.display_data,
    }));

    const seen = new Set(base.map(m => m.value));
    for (const m of manual) {
        if (!seen.has(m.value)) {
            base.push(m);
            seen.add(m.value);
        }
    }

    return base;
});

const EXCLUIDOS_GRUPO_TRANSFERENCIA = ['EFECTIVO', 'DEBITO', 'CREDITO', 'CUENTA_CORRIENTE'];

const transferMethods = computed(() =>
    METODOS_DISPONIBLES.value.filter(m => !EXCLUIDOS_GRUPO_TRANSFERENCIA.includes(m.value))
);
const tarjetaMethods = computed(() =>
    METODOS_DISPONIBLES.value.filter(m => ['DEBITO', 'CREDITO'].includes(m.value))
);
const showTransferDropdown = ref(false);
const showTarjetaDropdown = ref(false);

function seleccionarTransferencia(metodo) {
    showTransferDropdown.value = false;
    togglePago(metodo);
}
function seleccionarTarjeta(metodo) {
    showTarjetaDropdown.value = false;
    togglePago(metodo);
}

function tieneTransferenciaActiva() {
    return pagos.value.some(p => !EXCLUIDOS_GRUPO_TRANSFERENCIA.includes(p.metodo_pago));
}
function tieneTarjetaActiva() {
    return pagos.value.some(p => ['DEBITO', 'CREDITO'].includes(p.metodo_pago));
}

const pagos = ref([{ metodo_pago: 'EFECTIVO', monto: null }]);

const montoRecibido = ref(null);

// ─── Recargos por tarjeta ────────────────────────────────────
const bancoSeleccionado = ref('');
const cuotasSeleccionadas = ref(1);

const tieneTarjetaSeleccionada = computed(() => {
    return pagos.value.some(p => p.metodo_pago === 'DEBITO' || p.metodo_pago === 'CREDITO');
});

const esUnicaTarjeta = computed(() => {
    return pagos.value.length === 1 && ['DEBITO', 'CREDITO'].includes(pagos.value[0].metodo_pago);
});

const tipoTarjetaSeleccionado = computed(() => {
    const pago = pagos.value.find(p => p.metodo_pago === 'DEBITO' || p.metodo_pago === 'CREDITO');
    return pago ? pago.metodo_pago : null;
});

// Only show cuotas configured for the selected banco+tipo
const cuotasDisponibles = computed(() => {
    if (!tieneTarjetaSeleccionada.value) return [];
    const banco = bancoSeleccionado.value;
    const tipo = tipoTarjetaSeleccionado.value;
    if (!banco || !tipo) return [];
    const result = [];
    for (const [key, config] of Object.entries(props.recargos?.[banco] || {})) {
        if (key.startsWith(tipo + '_') && config.enabled) {
            const cuotas = parseInt(key.split('_')[1]);
            if (!isNaN(cuotas)) {
                result.push({ cuotas, porcentaje: parseFloat(config.porcentaje) || 0 });
            }
        }
    }
    return result.sort((a, b) => a.cuotas - b.cuotas);
});

const sinRecargosConfigurados = computed(() => {
    if (!tieneTarjetaSeleccionada.value || !bancoSeleccionado.value) return false;
    return cuotasDisponibles.value.length === 0;
});

// Reset cuotas when bank changes
watch(bancoSeleccionado, () => {
    cuotasSeleccionadas.value = 1;
});

function onBancoChange() {
    cuotasSeleccionadas.value = 1;
}

const recargoConfig = computed(() => {
    if (!tieneTarjetaSeleccionada.value || !bancoSeleccionado.value) return null;
    const banco = bancoSeleccionado.value;
    const tipo = tipoTarjetaSeleccionado.value;
    const cuotas = cuotasSeleccionadas.value;
    const key = `${tipo}_${cuotas}`;
    const config = props.recargos?.[banco]?.[key];
    if (config && config.enabled) return config;
    return null;
});

const recargoPorcentaje = computed(() => {
    return recargoConfig.value ? parseFloat(recargoConfig.value.porcentaje) : 0;
});

const recargoMonto = computed(() => {
    if (!tieneTarjetaSeleccionada.value || !bancoSeleccionado.value) return 0;
    const pago = pagos.value.find(p => p.metodo_pago === 'DEBITO' || p.metodo_pago === 'CREDITO');
    if (!pago) return 0;
    const montoBase = Number(pago.monto) || 0;
    return montoBase * (recargoPorcentaje.value / 100);
});

const montoTarjeta = computed(() => {
    if (!tieneTarjetaSeleccionada.value) return 0;
    const pago = pagos.value.find(p => p.metodo_pago === 'DEBITO' || p.metodo_pago === 'CREDITO');
    return pago ? (Number(pago.monto) || 0) : 0;
});

const totalConRecargo = computed(() => {
    return montoTarjeta.value + recargoMonto.value;
});

const montoPorCuota = computed(() => {
    if (!tieneTarjetaSeleccionada.value || cuotasSeleccionadas.value <= 1) return 0;
    return totalConRecargo.value / cuotasSeleccionadas.value;
});

const totalDisplay = computed(() => {
    if (esUnicaTarjeta.value) return totalConRecargo.value;
    return totalVenta.value;
});

function resetRecargoData() {
    bancoSeleccionado.value = '';
    cuotasSeleccionadas.value = 1;
}

const productosBusqueda = ref([]);
const buscandoProductos = ref(false);
const productosIniciales = computed(() => props.productos || []);
const totalProductos = computed(() => props.totalProductos || props.productos?.length || 0);

// Show search results only when user types, not the full grid
const productosFiltrados = computed(() => {
    if (buscar.value.length < 1) return [];
    if (productosBusqueda.value.length > 0) return productosBusqueda.value;
    return productosIniciales.value.filter(p =>
        p.nombre.toLowerCase().includes(buscar.value.toLowerCase()) ||
        (p.codigo_barras && p.codigo_barras.includes(buscar.value))
    );
});

let timeoutBusqueda = null;
function onBuscarInput() {
    if (timeoutBusqueda) clearTimeout(timeoutBusqueda);
    if (buscar.value.length >= 2) {
        timeoutBusqueda = setTimeout(() => {
            buscarProductosAjax(buscar.value);
        }, 300);
    } else {
        productosBusqueda.value = [];
    }
}

async function buscarProductosAjax(q) {
    buscandoProductos.value = true;
    try {
        const response = await fetch(`/pos/buscar-productos?q=${encodeURIComponent(q)}`);
        if (response.ok) {
            productosBusqueda.value = await response.json();
        }
    } catch (e) {
        console.error('Error al buscar productos:', e);
    } finally {
        buscandoProductos.value = false;
    }
}

const clientesFiltradosSelect = ref([]);
const buscandoClientes = ref(false);

let timeoutCliente = null;
function onBuscarClienteInput() {
    if (timeoutCliente) clearTimeout(timeoutCliente);
    if (busquedaCliente.value.length < 2) {
        clientesFiltradosSelect.value = [];
        return;
    }
    timeoutCliente = setTimeout(() => {
        buscarClientesAjax(busquedaCliente.value);
    }, 300);
}

async function buscarClientesAjax(q) {
    buscandoClientes.value = true;
    try {
        const response = await fetch(`/pos/buscar-clientes?q=${encodeURIComponent(q)}`);
        if (response.ok) {
            clientesFiltradosSelect.value = await response.json();
        }
    } catch (e) {
        console.error('Error al buscar clientes:', e);
    } finally {
        buscandoClientes.value = false;
    }
}

const seleccionarCliente = (cliente) => {
    clienteSeleccionado.value = cliente ? cliente.id : null;
    mostrarDropdownClientes.value = false;
    busquedaCliente.value = ''; 
};

const clienteActivoObj = computed(() => {
    if (!clienteSeleccionado.value) return null;
    return props.clientes.find(c => c.id === clienteSeleccionado.value);
});

const disponibleCliente = computed(() => {
    if (!clienteActivoObj.value) return 0;
    const limite = parseFloat(clienteActivoObj.value.limite_cuenta_corriente) || 0;
    const deuda = clienteActivoObj.value.cuenta_corriente ? parseFloat(clienteActivoObj.value.cuenta_corriente.saldo_deudor) : 0;
    return limite - deuda;
});

const totalVenta = computed(() => {
    return carrito.value.reduce((acc, item) => acc + (item.precio_venta * item.cantidad), 0);
});

const totalAsignado = computed(() => {
    return pagos.value.reduce((acc, p) => acc + (Number(p.monto) || 0), 0);
});

const restante = computed(() => {
    return totalVenta.value - totalAsignado.value;
});

// Auto-assign monto: full total when sole payment, remaining when split
watch([() => totalVenta.value, tieneTarjetaSeleccionada, esUnicaTarjeta], () => {
    if (!tieneTarjetaSeleccionada.value) return;
    const pago = pagos.value.find(p => p.metodo_pago === 'DEBITO' || p.metodo_pago === 'CREDITO');
    if (!pago) return;
    if (esUnicaTarjeta.value) {
        pago.monto = totalVenta.value;
    } else {
        pago.monto = restante.value > 0.01 ? restante.value : 0;
    }
}, { immediate: true });

const esPagoCompleto = computed(() => {
    if (totalVenta.value <= 0) return false;
    if (esUnicoEfectivo.value) return Number(montoRecibido.value) >= totalVenta.value;
    return Math.abs(restante.value) < 0.01;
});

const esUnicoEfectivo = computed(() => {
    return pagos.value.length === 1 && pagos.value[0].metodo_pago === 'EFECTIVO';
});

const tieneCuentaCorriente = computed(() => {
    return pagos.value.some(p => p.metodo_pago === 'CUENTA_CORRIENTE');
});

const vuelto = computed(() => {
    if (!esUnicoEfectivo.value) return null;
    if (montoRecibido.value === null || montoRecibido.value === '' || Number(montoRecibido.value) < totalVenta.value) return null;
    return Number(montoRecibido.value) - totalVenta.value;
});

const sugerencias = computed(() => {
    if (!esUnicoEfectivo.value) return [];
    const t = totalVenta.value;
    const montos = [];
    const base = [100, 200, 500, 1000, 2000, 5000, 10000, 20000];
    for (const m of base) {
        const sug = Math.ceil(t / m) * m;
        if (sug > t && !montos.includes(sug)) montos.push(sug);
    }
    return montos.slice(0, 4);
});

const bloqueoPorSaldo = computed(() => {
    if (tieneCuentaCorriente.value && clienteActivoObj.value) {
        const montoCC = pagos.value
            .filter(p => p.metodo_pago === 'CUENTA_CORRIENTE')
            .reduce((acc, p) => acc + (Number(p.monto) || 0), 0);
        return montoCC > disponibleCliente.value;
    }
    return false;
});

const puedeCobrar = computed(() => {
    if (carrito.value.length === 0) return false;
    if (!esPagoCompleto.value) return false;
    if (bloqueoPorSaldo.value) return false;
    if (tieneCuentaCorriente.value && !clienteActivoObj.value) return false;
    if (tieneTarjetaSeleccionada.value && !bancoSeleccionado.value) return false;
    return true;
});

function togglePago(metodo) {
    const idx = pagos.value.findIndex(p => p.metodo_pago === metodo);
    if (idx >= 0) {
        removerPago(idx);
        // Reset recargo data if no more card payments
        if (pagos.value.every(p => p.metodo_pago !== 'DEBITO' && p.metodo_pago !== 'CREDITO')) {
            resetRecargoData();
        }
    } else {
        agregarPago(metodo);
    }
}

function agregarPago(metodo) {
    if (pagos.value.length >= 6) return;
    if (pagos.value.some(p => p.metodo_pago === metodo)) return;
    const esPrimerEfectivo = pagos.value.length === 0 && metodo === 'EFECTIVO';
    const montoSugerido = esPrimerEfectivo ? totalVenta.value : (restante.value > 0.01 ? restante.value : null);
    pagos.value.push({ metodo_pago: metodo, monto: montoSugerido });
}

function removerPago(idx) {
    pagos.value.splice(idx, 1);
}

function autoCompletarRestante() {
    if (pagos.value.length === 0) return;
    const ultimo = pagos.value[pagos.value.length - 1];
    ultimo.monto = (Number(ultimo.monto) || 0) + Math.max(0, restante.value);
}

const procesarBusquedaEnter = async () => {
    if (timeoutBusqueda) clearTimeout(timeoutBusqueda);
    const query = buscar.value.trim();
    if (!query) return;

    if (query.length === 13 && query.startsWith('20')) {
        const pluBalanza = parseInt(query.substring(2, 6), 10).toString();
        const pesoGramos = parseInt(query.substring(7, 12), 10);
        const pesoKilos = pesoGramos / 1000;

        const productoBalanza = productosIniciales.value.find(p => p.codigo_barras === pluBalanza)
            || (await buscarExacto(pluBalanza));
        
        if (productoBalanza) {
            agregarItemAlCarrito(productoBalanza, pesoKilos);
            buscar.value = '';
            return;
        }
    }

    const exactMatch = productosIniciales.value.find(p => p.codigo_barras === query)
        || (await buscarExacto(query));
    if (exactMatch) {
        clickEnProducto(exactMatch);
    } else if (productosFiltrados.value.length === 1) {
        clickEnProducto(productosFiltrados.value[0]);
    } else {
        mostrarFlash('error', 'Producto no encontrado');
        buscar.value = '';
        nextTick(() => { if (inputBusqueda.value) inputBusqueda.value.focus(); });
    }
};

async function buscarExacto(q) {
    try {
        const response = await fetch(`/pos/buscar-productos?q=${encodeURIComponent(q)}`);
        if (response.ok) {
            const results = await response.json();
            return results.find(p => p.codigo_barras === q) || results[0] || null;
        }
    } catch (e) {}
    return null;
}

// ─── Feedback visual y sonoro ────────────────────────────────────
const flashFeedback = ref(null);
let flashTimeout = null;

const mostrarFlash = (tipo, mensaje) => {
    flashFeedback.value = { tipo, mensaje };
    if (flashTimeout) clearTimeout(flashTimeout);
    flashTimeout = setTimeout(() => { flashFeedback.value = null; }, 2000);
};

const productAddedFeedback = ref(null);
let productAddedTimeout = null;

let audioCtx = null;
function hacerSonidoBeep() {
    try {
        if (!audioCtx) {
            audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        }
        if (audioCtx.state === 'suspended') {
            audioCtx.resume();
        }
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.frequency.value = 1200;
        osc.type = 'sine';
        gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.12);
        osc.start(audioCtx.currentTime);
        osc.stop(audioCtx.currentTime + 0.12);
    } catch (e) {}
}

function mostrarProductoAgregado(producto, cantidad) {
    productAddedFeedback.value = { nombre: producto.nombre, cantidad };
    if (productAddedTimeout) clearTimeout(productAddedTimeout);
    productAddedTimeout = setTimeout(() => { productAddedFeedback.value = null; }, 900);
}

const cantidadEnCarrito = (productId) => {
    const item = carrito.value.find(i => i.id === productId);
    return item ? item.cantidad : 0;
};

const manejarCodigoEscaneado = (codigo) => {
    mostrarEscaner.value = false;
    buscar.value = codigo;
    procesarBusquedaEnter();
};

const clickEnProducto = async (producto) => {
    if (producto.unidad_medida === 'Kg') {
        const { value: formValues } = await Swal.fire({
            title: 'Ingresar Cantidad',
            html: `
                <div class="mb-4 text-slate-500 font-bold text-sm">Estás vendiendo: <span class="text-sky-600">${producto.nombre}</span></div>
                <div class="text-[10px] text-amber-600 font-black mb-2 uppercase tracking-widest">Stock Disponible: ${producto.stock_actual} kg</div>
                <div class="flex flex-col sm:flex-row gap-3 justify-center items-center">
                    <input id="swal-peso" type="number" step="0.001" min="0.001" class="w-32 border-slate-300 rounded-xl text-center text-xl font-black text-slate-800 focus:ring-sky-500 focus:border-sky-500" placeholder="Ej: 250">
                    <select id="swal-unidad" class="w-32 border-slate-300 rounded-xl text-slate-700 font-bold text-lg bg-slate-50 focus:ring-sky-500 focus:border-sky-500">
                        <option value="Gramos" selected>Gramos</option>
                        <option value="Kg">Kilos</option>
                    </select>
                </div>
            `,
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: 'Agregar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#0284c7',
            didOpen: () => { document.getElementById('swal-peso').focus() },
            preConfirm: () => {
                const peso = parseFloat(document.getElementById('swal-peso').value);
                const unidad = document.getElementById('swal-unidad').value;
                const cantCalculada = unidad === 'Gramos' ? peso / 1000 : peso;

                if (!peso || peso <= 0) {
                    Swal.showValidationMessage('Ingresá una cantidad válida');
                    return false;
                }
                
                if (cantCalculada > producto.stock_actual && !permitirStockNegativo.value) {
                    Swal.showValidationMessage(`Stock insuficiente (Disponible: ${producto.stock_actual}kg)`);
                    return false;
                }

                return { cantCalculada };
            }
        });

        if (formValues) {
            agregarItemAlCarrito(producto, formValues.cantCalculada);
            buscar.value = '';
        }
    } else {
        agregarItemAlCarrito(producto, 1);
        buscar.value = '';
    }
    
    nextTick(() => { if (inputBusqueda.value) inputBusqueda.value.focus(); });
};

const agregarItemAlCarrito = (producto, cantidadAgregada) => {
    const existe = carrito.value.find(item => item.id === producto.id);
    const nuevaCantidad = existe ? existe.cantidad + cantidadAgregada : cantidadAgregada;

    if (nuevaCantidad > producto.stock_actual && !permitirStockNegativo.value) {
        Swal.fire('Stock Insuficiente', `Solo hay ${producto.stock_actual} disponibles.`, 'warning');
        return;
    }

    if (existe) {
        existe.cantidad = nuevaCantidad;
    } else {
        const precioCobrar = producto.en_liquidacion ? producto.precio_rebajado : producto.precio_venta;
        
        carrito.value.push({ 
            ...producto, 
            cantidad: cantidadAgregada,
            precio_original: producto.precio_venta, 
            precio_venta: precioCobrar 
        });
    }

    hacerSonidoBeep();
    mostrarProductoAgregado(producto, nuevaCantidad);
    mostrarFlash('success', `${producto.nombre} agregado`);
    debouncedFetchPrecios();
};

let _preciosTimer = null;

async function fetchPreciosEfectivos() {
    if (!carrito.value.length) return;
    try {
        const items = carrito.value.map(item => ({ id: item.id, cantidad: item.cantidad }));
        const { data } = await axios.post(route('pos.precios'), { items });
        for (const precio of data.items) {
            const item = carrito.value.find(i => i.id === precio.id);
            if (!item) continue;
            item.precio_venta = precio.precio_unitario;
            item.descuento_promo = precio.descuento_aplicado;
            item.tipo_descuento = precio.tipo_descuento;
        }
    } catch (e) {
        console.error('Error fetching effective prices:', e);
    }
}

function debouncedFetchPrecios() {
    clearTimeout(_preciosTimer);
    _preciosTimer = setTimeout(fetchPreciosEfectivos, 300);
}

const prevenirNegativo = (e) => {
    if (['-', 'e', 'E', '+'].includes(e.key)) e.preventDefault();
};

const incrementarCantidad = (index) => {
    const item = carrito.value[index];
    const isKg = item.unidad_medida === 'Kg';
    const incremento = isKg ? 0.1 : 1;
    
    if (item.cantidad + incremento > item.stock_actual && !permitirStockNegativo.value) {
        Swal.fire({ title: 'Límite de Stock', text: `No podés agregar más de ${item.stock_actual}`, icon: 'info', timer: 1500, showConfirmButton: false });
        return;
    }
    
    item.cantidad += incremento;
    debouncedFetchPrecios();
};

const decrementarCantidad = (index) => { 
    const isKg = carrito.value[index].unidad_medida === 'Kg';
    const resta = isKg ? 0.1 : 1;
    if (carrito.value[index].cantidad > resta) {
        carrito.value[index].cantidad -= resta;
        debouncedFetchPrecios();
    }
};

const validarCantidad = (index) => { 
    const item = carrito.value[index];
    if (!item.cantidad || item.cantidad <= 0) {
        item.cantidad = item.unidad_medida === 'Kg' ? 0.1 : 1; 
    } 

    if (item.cantidad > item.stock_actual && !permitirStockNegativo.value) {
        item.cantidad = item.stock_actual;
        Swal.fire('Stock Ajustado', 'Se ajustó a la disponibilidad máxima.', 'warning');
    }
    debouncedFetchPrecios();
};

const eliminarDelCarrito = (index) => carrito.value.splice(index, 1);

const finalizarVenta = () => {
    if (!puedeCobrar.value) return;
    
    if (tieneCuentaCorriente.value && !clienteSeleccionado.value) {
        Swal.fire('Falta Cliente', 'Tenés que seleccionar a quién le vas a fiar.', 'warning');
        return;
    }

    Swal.fire({
        title: 'Procesando cobro...',
        text: 'Registrando salida de stock...',
        didOpen: () => { Swal.showLoading() },
        allowOutsideClick: false
    });

    const pagosData = pagos.value.map(p => {
        const pagoData = {
            metodo_pago: p.metodo_pago,
            monto: esUnicoEfectivo.value ? totalVenta.value : (Number(p.monto) || 0),
        };

        // Add recargo data for card payments
        if (p.metodo_pago === 'DEBITO' || p.metodo_pago === 'CREDITO') {
            pagoData.banco = bancoSeleccionado.value || null;
            pagoData.tipo_tarjeta = p.metodo_pago;
            pagoData.cuotas = cuotasSeleccionadas.value;
            pagoData.recargo_porcentaje = recargoPorcentaje.value;
            pagoData.recargo_monto = recargoMonto.value;
        }

        return pagoData;
    });

    router.post(route('ventas.store'), {
        turno_caja_id: props.turno.id, 
        consumidor_id: clienteSeleccionado.value,
        items: carrito.value,
        total: totalVenta.value,
        recargo_monto: recargoMonto.value,
        pagos: pagosData,
    }, {
        onSuccess: (page) => {
            Swal.close();

            const ventaId = page.props.flash.venta_id;
            const esPendiente = page.props.flash.es_pendiente;

            carrito.value = [];
            clienteSeleccionado.value = null;
            buscar.value = '';
            montoRecibido.value = null;
            pagos.value = [{ metodo_pago: 'EFECTIVO', monto: null }];

            if (esPendiente) {
                confirmarDisplayInfo.value = page.props.flash.display_info || [];
                confirmarVentaId.value = ventaId;
                confirmarPagoModal.value = true;
                fetchVentasPendientesPago();
                nextTick(() => { if (inputBusqueda.value) inputBusqueda.value.focus(); });
                return;
            }

            let html = `Venta #${ventaId} registrada correctamente.`;
            if (clienteActivoObj.value?.email) {
                html += `<br><span class="text-xs text-slate-500">Ticket enviado a ${clienteActivoObj.value.email}</span>`;
            }

            Swal.fire({
                icon: 'success',
                title: '¡Venta Registrada!',
                html,
                showCancelButton: true,
                confirmButtonText: 'Imprimir Ticket',
                cancelButtonText: 'Cerrar',
                confirmButtonColor: '#0284c7',
            }).then((result) => {
                if (result.isConfirmed && ventaId) {
                    window.open(route('ventas.imprimir', ventaId), 'print_ticket');
                }
            });

            nextTick(() => { if (inputBusqueda.value) inputBusqueda.value.focus(); });
        },
        onError: (errors) => {
            Swal.close();
            Swal.fire({
                icon: 'error',
                title: 'Error al cobrar',
                text: errors.error || 'Verificá que haya stock suficiente y reintentá.',
                confirmButtonColor: '#ef4444'
            });
        }
    });
};

function onPagoConfirmado() {
    confirmarPagoModal.value = false;
    confirmarVentaId.value = null;
    confirmarDisplayInfo.value = [];
    fetchVentasPendientesPago();
    fetchMovimientosTurno();
}

function onPagoCancelado() {
    confirmarPagoModal.value = false;
    confirmarVentaId.value = null;
    confirmarDisplayInfo.value = [];
    fetchVentasPendientesPago();
}

async function fetchVentasPendientesPago() {
    try {
        const response = await fetch('/ventas/pendientes');
        if (response.ok) {
            ventasPendientesPago.value = await response.json();
        }
    } catch (e) {}
}

const atajosDisponibles = computed(() => {
    const disponibles = new Set(METODOS_DISPONIBLES.value.map(m => m.value));
    const mapa = {
        F1: 'EFECTIVO',
        F2: 'DEBITO',
        F3: 'CREDITO',
        F4: 'TRANSFERENCIA',
        F5: 'MERCADO_PAGO',
        F6: 'VIUMI',
        F8: 'CUENTA_CORRIENTE',
    };
    const result = {};
    for (const [key, value] of Object.entries(mapa)) {
        if (disponibles.has(value)) {
            result[key] = value;
        }
    }
    return result;
});

const teclaDeMetodo = (metodo) => {
    return Object.keys(atajosDisponibles.value).find(k => atajosDisponibles.value[k] === metodo) || '';
};

const handleKeydown = (e) => {
    const key = e.key;

    if (key.startsWith('F') && atajosDisponibles.value[key]) {
        e.preventDefault();
        togglePago(atajosDisponibles.value[key]);
        return;
    }
    if (key === 'F9') {
        e.preventDefault();
        finalizarVenta();
        return;
    }
    if (key === 'Escape') {
        montoRecibido.value = null;
        return;
    }

    if (e.ctrlKey && key === 'z') {
        e.preventDefault();
        if (carrito.value.length > 0) {
            const removed = carrito.value.pop();
            mostrarFlash('removed', `Eliminado: ${removed.nombre}`);
        }
        return;
    }

    if (key === 'Backspace' && buscar.value.length === 0 && document.activeElement === inputBusqueda.value) {
        e.preventDefault();
        if (carrito.value.length > 0) {
            const removed = carrito.value.pop();
            mostrarFlash('removed', `Eliminado: ${removed.nombre}`);
        }
        return;
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
    iniciarPollingMovimientos();
    fetchPendientes();
    fetchVentasPendientesPago();
});
onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
    detenerPollingMovimientos();
});
</script>

<template>
    <Head title="Terminal POS - VendAR" />

    <AuthenticatedLayout>
        <div class="py-4 px-4 sm:px-6 lg:px-8 bg-gradient-to-br from-slate-50 to-slate-100 min-h-screen" @click="mostrarDropdownClientes = false; showTransferDropdown = false; showTarjetaDropdown = false">

            <div class="grid grid-cols-12 gap-4 lg:gap-6">

                <!-- ─── COLUMNA IZQUIERDA: Búsqueda + Resultados ─── -->
                <div class="col-span-12 lg:col-span-5 flex flex-col gap-2">

                    <!-- Buscador compacto -->
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 focus-within:border-sky-500 focus-within:ring-2 focus-within:ring-sky-500/20 transition-all overflow-hidden">
                        <div class="relative flex items-center">
                            <span class="absolute left-4 text-slate-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                            </span>
                            <input
                                ref="inputBusqueda"
                                v-model="buscar"
                                @input="onBuscarInput"
                                @keyup.enter="procesarBusquedaEnter"
                                type="text"
                                placeholder="Escaneá código o buscá por nombre..."
                                class="w-full pl-12 pr-28 h-10 bg-transparent border-none focus:ring-0 text-base font-bold text-slate-800 placeholder-slate-400"
                                autofocus
                            />
                            <div v-if="buscandoProductos" class="absolute right-28 top-1/2 -translate-y-1/2">
                                <svg class="animate-spin h-4 w-4 text-sky-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            </div>
                            <div class="absolute right-2 flex items-center gap-1.5">
                                <span class="hidden sm:block px-1.5 py-0.5 bg-slate-100 rounded text-[9px] font-bold text-slate-400 uppercase border border-slate-200">
                                    ENTER ↵
                                </span>
                                <button
                                    @click="mostrarEscaner = true"
                                    class="bg-sky-100 text-sky-600 p-1.5 rounded-lg hover:bg-sky-500 hover:text-white transition-all shadow-sm border border-sky-200 flex items-center gap-1 group"
                                    title="Escanear con Cámara"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Producto agregado (feedback animado) -->
                    <div v-if="productAddedFeedback" class="fixed top-24 left-1/2 -translate-x-1/2 z-50 animate-in fade-in zoom-in-95 slide-in-from-top-2 duration-200">
                        <div class="px-5 py-3 bg-emerald-50 border-2 border-emerald-300 rounded-2xl shadow-xl flex items-center gap-3">
                            <div class="w-8 h-8 bg-emerald-100 rounded-full flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                            </div>
                            <div>
                                <div class="font-bold text-emerald-800 text-sm">{{ productAddedFeedback.nombre }}</div>
                                <div class="text-xs text-emerald-600 font-bold">Cantidad: ×{{ productAddedFeedback.cantidad }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Flash feedback (error / removed) -->
                    <div v-if="flashFeedback && flashFeedback.tipo !== 'success'" class="fixed top-20 right-4 z-50">
                        <div class="px-4 py-2.5 rounded-xl shadow-lg border text-sm font-bold flex items-center gap-2"
                            :class="flashFeedback.tipo === 'removed' ? 'bg-amber-50 border-amber-200 text-amber-700' : 'bg-rose-50 border-rose-200 text-rose-700'">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            {{ flashFeedback.mensaje }}
                        </div>
                    </div>

                    <!-- Productos frecuentes (solo cuando no hay búsqueda) -->
                    <div v-if="buscar.length < 1 && frecuentes && frecuentes.length > 0">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-xs font-black text-slate-500 uppercase tracking-widest">Más vendidos</span>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-2">
                            <div
                                v-for="p in frecuentes" :key="'freq-' + p.id"
                                @click="clickEnProducto(p)"
                                class="bg-amber-50 border border-amber-200 hover:border-amber-400 hover:shadow-md rounded-xl px-3 py-2.5 cursor-pointer transition-all"
                            >
                                <div class="text-sm font-bold text-amber-800 truncate">{{ p.nombre }}</div>
                                <div class="text-base font-black text-amber-900 mt-0.5">
                                    ${{ Number(p.precio_rebajado || p.precio_venta).toLocaleString() }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Resultados de búsqueda (solo cuando se escribe) -->
                    <div v-if="buscar.length >= 1 && productosFiltrados.length > 0"
                         class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-2">
                        <div
                            v-for="p in productosFiltrados" :key="p.id"
                            @click="clickEnProducto(p)"
                            class="bg-white p-3 rounded-xl border border-slate-200 hover:border-sky-500 hover:shadow-md transition-all cursor-pointer relative group"
                        >
                            <div v-if="p.en_liquidacion" class="absolute -top-1 -left-1 px-1.5 py-0.5 bg-rose-500 text-white rounded-lg text-[9px] font-black z-10 leading-none">
                                -{{ p.porcentaje_descuento }}%
                            </div>

                            <div v-if="p.stock_actual <= 0" class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-red-500 rounded-full z-10"></div>
                            <div v-else-if="p.stock_actual <= (p.stock_minimo || 5)" class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-amber-400 rounded-full z-10"></div>

                            <div class="flex items-center gap-2.5">
                                <div class="w-9 h-9 bg-slate-50 rounded-lg overflow-hidden flex items-center justify-center border border-slate-100 shrink-0">
                                    <img v-if="p.imagen" :src="'/storage/' + p.imagen" class="w-full h-full object-cover" />
                                    <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="font-bold text-slate-800 text-xs leading-tight truncate">{{ p.nombre }}</p>
                                    <div class="flex items-baseline gap-1 mt-0.5">
                                        <p v-if="p.en_liquidacion" class="text-rose-600 font-black text-sm">${{ p.precio_rebajado }}</p>
                                        <p :class="p.en_liquidacion ? 'text-slate-400 line-through text-[10px]' : 'text-slate-800 font-black text-sm'">${{ p.precio_venta }}</p>
                                        <span v-if="p.unidad_medida === 'Kg'" class="text-[9px] text-slate-400">/kg</span>
                                    </div>
                                </div>
                                <div v-if="cantidadEnCarrito(p.id)" class="shrink-0 bg-sky-100 text-sky-700 text-[10px] font-black px-1.5 py-0.5 rounded-full">
                                    {{ cantidadEnCarrito(p.id) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sin resultados -->
                    <div v-if="buscar.length >= 1 && productosFiltrados.length === 0 && !buscandoProductos"
                         class="flex flex-col items-center justify-center py-16 text-slate-300">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        <p class="font-bold text-base">Sin resultados</p>
                        <p class="text-sm">Probá con otro término de búsqueda</p>
                    </div>

                    <!-- Estado inicial (sin búsqueda, sin frecuentes) -->
                    <div v-if="buscar.length < 1 && (!frecuentes || frecuentes.length === 0)"
                         class="flex flex-col items-center justify-center py-16 text-slate-300">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                        <p class="font-bold text-lg">Escanéá o buscá un producto</p>
                    </div>

                </div>

                <!-- ─── COLUMNA DERECHA: Cliente → Carrito → Pagos → Total → Cobrar ─── -->
                <div class="col-span-12 lg:col-span-7">
                    <div class="bg-white rounded-3xl shadow-2xl shadow-slate-200/50 flex flex-col h-[calc(100vh-100px)] sticky top-4 border border-slate-200 overflow-hidden">

                        <!-- 1. CLIENTE -->
                        <div class="px-4 pt-3 pb-3 border-b border-slate-200 bg-slate-50/50">
                            <div class="relative w-full" @click.stop>
                                <div
                                    @click="mostrarDropdownClientes = !mostrarDropdownClientes"
                                    class="bg-white px-3 py-2 rounded-xl text-sm font-bold text-slate-700 cursor-pointer flex justify-between items-center border border-slate-200 hover:border-sky-400 transition-all shadow-sm"
                                >
                                    <span class="truncate flex items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-sky-500 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" /></svg>
                                        {{ clienteActivoObj ? clienteActivoObj.nombre + ' ' + clienteActivoObj.apellido : 'Consumidor Final' }}
                                    </span>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                </div>

                                <div v-if="mostrarDropdownClientes" class="absolute right-0 top-full mt-2 w-72 bg-white border border-slate-200 shadow-xl rounded-2xl z-50 overflow-hidden">
                                    <div class="p-3 border-b border-slate-100 bg-slate-50 relative">
                                        <span class="absolute left-5 top-1/2 -translate-y-1/2 text-slate-400">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                                        </span>
                                        <input
                                            v-model="busquedaCliente"
                                            @input="onBuscarClienteInput"
                                            type="text"
                                            placeholder="Buscá por nombre o documento..."
                                            class="w-full pl-9 text-sm font-medium border-slate-200 rounded-xl focus:ring-sky-500 focus:border-sky-500 py-2"
                                            autofocus
                                        >
                                        <div v-if="buscandoClientes" class="absolute right-3 top-1/2 -translate-y-1/2">
                                            <svg class="animate-spin h-3.5 w-3.5 text-sky-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                        </div>
                                    </div>
                                    <ul class="max-h-56 overflow-y-auto">
                                        <li
                                            @click="seleccionarCliente(null)"
                                            class="px-4 py-2.5 text-sm font-bold text-slate-600 hover:bg-sky-50 hover:text-sky-700 cursor-pointer border-b border-slate-50 flex items-center gap-2"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                                            Consumidor Final
                                        </li>
                                        <li
                                            v-for="c in clientesFiltradosSelect" :key="c.id"
                                            @click="seleccionarCliente(c)"
                                            class="px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-sky-50 hover:text-sky-700 cursor-pointer border-b border-slate-50"
                                        >
                                            <div class="flex justify-between items-center">
                                                <span>{{ c.nombre }} {{ c.apellido }}</span>
                                                <span v-if="c.documento" class="text-[10px] font-mono text-slate-400">{{ c.documento }}</span>
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- 2. CARRITO (compacto, flex-1) -->
                        <div class="flex-1 overflow-y-auto px-3 py-2 space-y-1">
                            <div v-if="carrito.length === 0" class="h-full flex flex-col items-center justify-center text-slate-300">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-14 w-14 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                                <p class="font-bold text-sm">Carrito vacío</p>
                            </div>

                            <div v-for="(item, index) in carrito" :key="item.id"
                                class="flex items-center gap-1.5 px-2 py-1.5 bg-white border border-slate-100 rounded-xl hover:border-sky-200 transition-all group"
                            >
                                <div class="flex items-center bg-slate-50 rounded-lg border border-slate-200 shrink-0">
                                    <button @click="decrementarCantidad(index)" type="button" class="w-7 h-7 flex items-center justify-center text-slate-500 hover:text-rose-500 font-bold text-base leading-none">−</button>
                                    <input
                                        type="number"
                                        v-model.number="item.cantidad"
                                        min="0"
                                        @blur="validarCantidad(index)"
                                        @keydown="prevenirNegativo($event)"
                                        class="w-9 text-center bg-transparent border-none text-xs font-black p-0 focus:ring-0 text-sky-700 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none [&]:[appearance:textfield]"
                                    >
                                    <button @click="incrementarCantidad(index)" type="button" class="w-7 h-7 flex items-center justify-center text-slate-500 hover:text-emerald-500 font-bold text-base leading-none">+</button>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <span class="font-bold text-slate-800 text-sm block truncate">{{ item.nombre }}</span>
                                </div>

                                <span class="font-black text-slate-800 text-sm shrink-0 tabular-nums">${{ (item.cantidad * item.precio_venta).toFixed(2) }}</span>

                                <button @click="eliminarDelCarrito(index)" class="shrink-0 w-6 h-6 flex items-center justify-center text-slate-300 hover:text-rose-500 hover:bg-rose-50 rounded-lg opacity-0 group-hover:opacity-100 transition-all text-sm">✕</button>
                            </div>

                            <!-- Guardar carrito (sutil, solo cuando hay items) -->
                            <div v-if="carrito.length > 0" class="flex justify-center pt-1">
                                <button @click="guardarCarrito" :disabled="guardandoCarrito"
                                    class="flex items-center gap-1 px-2 py-1 text-[10px] font-bold text-slate-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-all uppercase tracking-wider disabled:opacity-50"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" /></svg>
                                    {{ guardandoCarrito ? 'Guardando...' : 'Guardar carrito' }}
                                </button>
                            </div>
                        </div>

                        <!-- Información de crédito (cuenta corriente) -->
                        <div v-if="tieneCuentaCorriente && clienteActivoObj" class="px-4 py-1.5 border-t border-slate-100 bg-slate-50/50">
                            <div class="flex items-center justify-between text-xs" :class="bloqueoPorSaldo ? 'text-rose-600 font-bold' : 'text-emerald-600 font-bold'">
                                <span>Crédito disponible</span>
                                <span>${{ disponibleCliente.toFixed(2) }}</span>
                            </div>
                        </div>
                        <div v-else-if="tieneCuentaCorriente && !clienteActivoObj" class="px-4 py-1.5 border-t border-slate-100 bg-amber-50/50 text-xs font-bold text-amber-600">
                            Seleccioná un cliente para fiarle
                        </div>

                        <!-- 3. TOTAL + PAGOS + COBRAR (siempre visibles) -->
                        <div class="border-t border-slate-200 bg-white">

                            <!-- Métodos de pago -->
                            <div class="px-4 pt-3 pb-2">
                                <!-- Fila 1: Efectivo + Cuenta Corriente (botones grandes) -->
                                <div class="grid grid-cols-2 gap-2 mb-2">
                                    <button
                                        @click="togglePago('EFECTIVO')"
                                        class="flex items-center justify-center gap-2 py-3.5 rounded-xl border-2 transition-all shadow-sm text-sm font-bold"
                                        :class="pagos.some(p => p.metodo_pago === 'EFECTIVO')
                                            ? 'bg-emerald-50 border-emerald-500 text-emerald-700 shadow-emerald-100'
                                            : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50 hover:border-slate-300'"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                                        Efectivo
                                        <span v-if="teclaDeMetodo('EFECTIVO')" class="text-[8px] font-mono text-slate-400 bg-slate-100 px-1 rounded border border-slate-200">{{ teclaDeMetodo('EFECTIVO') }}</span>
                                    </button>
                                    <button
                                        v-if="METODOS_DISPONIBLES.some(m => m.value === 'CUENTA_CORRIENTE')"
                                        @click="togglePago('CUENTA_CORRIENTE')"
                                        class="flex items-center justify-center gap-2 py-3.5 rounded-xl border-2 transition-all shadow-sm text-sm font-bold"
                                        :class="pagos.some(p => p.metodo_pago === 'CUENTA_CORRIENTE')
                                            ? 'bg-indigo-50 border-indigo-500 text-indigo-700 shadow-indigo-100'
                                            : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50 hover:border-slate-300'"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                        Cta. Corriente
                                        <span v-if="teclaDeMetodo('CUENTA_CORRIENTE')" class="text-[8px] font-mono text-slate-400 bg-slate-100 px-1 rounded border border-slate-200">{{ teclaDeMetodo('CUENTA_CORRIENTE') }}</span>
                                    </button>
                                </div>

                                <!-- Fila 2: Transferencias + Tarjetas (dropdowns) -->
                                <div class="grid grid-cols-2 gap-2">
                                    <!-- Transferencias -->
                                    <div class="relative" @click.stop>
                                        <button
                                            @click="showTransferDropdown = !showTransferDropdown; showTarjetaDropdown = false"
                                            class="w-full flex items-center justify-center gap-2 py-3 rounded-xl border-2 transition-all shadow-sm text-sm font-bold"
                                            :class="tieneTransferenciaActiva()
                                                ? 'bg-sky-50 border-sky-500 text-sky-700 shadow-sky-100'
                                                : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50 hover:border-slate-300'"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                            Transferencias
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                            <span v-if="teclaDeMetodo('TRANSFERENCIA')" class="text-[8px] font-mono text-slate-400 bg-slate-100 px-1 rounded border border-slate-200">{{ teclaDeMetodo('TRANSFERENCIA') }}</span>
                                        </button>
                                        <div v-if="showTransferDropdown && transferMethods.length > 0"
                                            class="absolute bottom-full left-0 mb-1 w-full bg-white border border-slate-200 shadow-xl rounded-xl z-50 overflow-hidden">
                                            <button v-for="m in transferMethods" :key="m.value"
                                                @click="seleccionarTransferencia(m.value)"
                                                class="w-full px-3 py-2.5 text-left text-sm font-bold flex items-center gap-2 hover:bg-sky-50 transition-colors"
                                                :class="pagos.some(p => p.metodo_pago === m.value) ? 'text-sky-700 bg-sky-50/50' : 'text-slate-700'"
                                            >
                                                <svg v-if="m.value === 'MERCADO_PAGO'" class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="11" fill="currentColor"/><path d="M8 12c0 2 1 3.5 3 3.5s3-1.5 3-3.5-1-3.5-3-3.5-3 1.5-3 3.5z" fill="white"/></svg>
                                                <svg v-else-if="m.value === 'VIUMI'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                                {{ m.label }}
                                                <span v-if="teclaDeMetodo(m.value)" class="ml-auto text-[8px] font-mono text-slate-400 bg-slate-100 px-1 rounded border border-slate-200">{{ teclaDeMetodo(m.value) }}</span>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Tarjetas -->
                                    <div class="relative" @click.stop>
                                        <button
                                            @click="showTarjetaDropdown = !showTarjetaDropdown; showTransferDropdown = false"
                                            class="w-full flex items-center justify-center gap-2 py-3 rounded-xl border-2 transition-all shadow-sm text-sm font-bold"
                                            :class="tieneTarjetaActiva()
                                                ? 'bg-violet-50 border-violet-500 text-violet-700 shadow-violet-100'
                                                : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50 hover:border-slate-300'"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="2" y="5" width="20" height="14" rx="2" stroke-width="1.5"/><line x1="2" y1="10" x2="22" y2="10" stroke-width="1.5"/><circle cx="8" cy="15" r="1.5" fill="currentColor" stroke="none"/><circle cx="13" cy="15" r="1.5" fill="currentColor" stroke="none"/></svg>
                                            Tarjetas
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                        </button>
                                        <div v-if="showTarjetaDropdown && tarjetaMethods.length > 0"
                                            class="absolute bottom-full left-0 mb-1 w-full bg-white border border-slate-200 shadow-xl rounded-xl z-50 overflow-hidden">
                                            <button v-for="m in tarjetaMethods" :key="m.value"
                                                @click="seleccionarTarjeta(m.value)"
                                                class="w-full px-3 py-2.5 text-left text-sm font-bold flex items-center gap-2 hover:bg-violet-50 transition-colors"
                                                :class="pagos.some(p => p.metodo_pago === m.value) ? 'text-violet-700 bg-violet-50/50' : 'text-slate-700'"
                                            >
                                                <svg v-if="m.value === 'DEBITO'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="2" y="5" width="20" height="14" rx="2" stroke-width="1.5"/><line x1="2" y1="10" x2="22" y2="10" stroke-width="1.5"/><circle cx="8" cy="15" r="1.5" fill="currentColor" stroke="none"/><circle cx="13" cy="15" r="1.5" fill="currentColor" stroke="none"/></svg>
                                                <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="2" y="5" width="20" height="14" rx="2" stroke-width="1.5"/><line x1="2" y1="10" x2="22" y2="10" stroke-width="1.5"/><path d="M6 14h4" stroke-width="1.5" stroke-linecap="round"/><path d="M14 14h4" stroke-width="1.5" stroke-linecap="round"/><path d="M6 17h8" stroke-width="1.5" stroke-linecap="round"/></svg>
                                                {{ m.label }}
                                                <span v-if="teclaDeMetodo(m.value)" class="ml-auto text-[8px] font-mono text-slate-400 bg-slate-100 px-1 rounded border border-slate-200">{{ teclaDeMetodo(m.value) }}</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Montos por método (pago combinado) -->
                            <div v-if="!esUnicoEfectivo && !esUnicaTarjeta" class="px-4 pb-2 space-y-1">
                                <div v-for="(pago, idx) in pagos" :key="idx" class="flex items-center gap-2">
                                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-500 min-w-[60px] shrink-0">{{ METODOS_DISPONIBLES.find(m => m.value === pago.metodo_pago)?.label || pago.metodo_pago }}</span>
                                    <div class="relative flex-1">
                                        <span class="absolute left-2 top-1/2 -translate-y-1/2 text-[10px] font-bold text-slate-400">$</span>
                                        <input
                                            v-model.number="pago.monto"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            placeholder="0.00"
                                            class="w-full pl-5 pr-2 py-1.5 border-2 border-slate-200 rounded-lg text-sm font-bold text-slate-800 focus:border-indigo-500 focus:ring-0 transition-colors"
                                            @focus="$event.target.select()"
                                        >
                                    </div>
                                    <button
                                        v-if="pagos.length > 1"
                                        @click="removerPago(idx)"
                                        class="p-1 text-slate-300 hover:text-rose-500 hover:bg-rose-50 rounded-lg transition-all"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Configuración de recargo (tarjeta débito/crédito) -->
                            <div v-if="tieneTarjetaSeleccionada" class="px-4 pb-3 border-t border-slate-100 pt-3 space-y-3">

                                <!-- Banco -->
                                <div>
                                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest block mb-1.5">Banco</label>
                                    <select
                                        v-model="bancoSeleccionado"
                                        @change="onBancoChange"
                                        class="w-full px-3 py-2 border-2 border-slate-200 rounded-xl text-sm font-bold text-slate-800 focus:border-indigo-500 focus:ring-0 transition-colors bg-white"
                                    >
                                        <option value="" disabled>Seleccionar banco...</option>
                                        <option v-for="b in bancosDisponibles" :key="b" :value="b">{{ b }}</option>
                                    </select>
                                </div>

                                <!-- Sin recargos configurados -->
                                <div v-if="sinRecargosConfigurados" class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-amber-400 mx-auto mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    <span class="text-xs font-bold text-amber-700">Sin recargos configurados para este banco</span>
                                    <span class="block text-[10px] text-amber-500 mt-0.5">Se aplicará 0% de recargo</span>
                                </div>

                                <!-- Cuotas (solo Crédito, solo las configuradas) -->
                                <div v-if="tipoTarjetaSeleccionado === 'CREDITO' && !sinRecargosConfigurados && cuotasDisponibles.length > 0">
                                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest block mb-1.5">Cuotas</label>
                                    <div class="grid gap-1.5" :class="cuotasDisponibles.length <= 4 ? 'grid-cols-' + cuotasDisponibles.length : 'grid-cols-4'">
                                        <button
                                            v-for="cuota in cuotasDisponibles"
                                            :key="cuota.cuotas"
                                            @click="cuotasSeleccionadas = cuota.cuotas"
                                            class="relative flex flex-col items-center py-2.5 px-1 rounded-xl border-2 transition-all"
                                            :class="cuotasSeleccionadas === cuota.cuotas
                                                ? 'bg-indigo-500 border-indigo-500 text-white shadow-md shadow-indigo-100'
                                                : 'bg-white border-slate-200 text-slate-600 hover:border-indigo-300'"
                                        >
                                            <span class="text-lg font-black leading-none">{{ cuota.cuotas }}</span>
                                            <span class="text-[9px] font-bold mt-1 leading-none" :class="cuotasSeleccionadas === cuota.cuotas ? 'text-indigo-200' : 'text-slate-400'">
                                                {{ cuota.cuotas === 1 ? 'cuota' : 'cuotas' }}
                                            </span>
                                            <span
                                                class="text-[9px] font-bold mt-1.5 px-2 py-0.5 rounded-full leading-none"
                                                :class="cuota.porcentaje > 0
                                                    ? (cuotasSeleccionadas === cuota.cuotas ? 'bg-amber-400/40 text-amber-100' : 'bg-amber-50 text-amber-600')
                                                    : (cuotasSeleccionadas === cuota.cuotas ? 'bg-emerald-400/40 text-emerald-100' : 'bg-emerald-50 text-emerald-600')"
                                            >
                                                {{ cuota.porcentaje > 0 ? '+' + cuota.porcentaje + '%' : '0%' }}
                                            </span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Resumen: SUBTOTAL + RECARGO + TOTAL -->
                                <div class="bg-slate-50 rounded-xl p-3 space-y-2">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Subtotal</span>
                                        <span class="text-xs font-bold text-slate-500 tabular-nums">${{ montoTarjeta.toFixed(2) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-[10px] font-black uppercase tracking-widest"
                                            :class="recargoPorcentaje > 0 ? 'text-amber-500' : 'text-emerald-500'"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 inline -mt-0.5 mr-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
                                            Recargo ({{ recargoPorcentaje }}%)
                                        </span>
                                        <span class="text-xs font-bold tabular-nums"
                                            :class="recargoPorcentaje > 0 ? 'text-amber-600' : 'text-emerald-600'"
                                        >+${{ recargoMonto.toFixed(2) }}</span>
                                    </div>
                                    <div class="border-t border-slate-200 pt-2 flex items-end justify-between">
                                        <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Total a cobrar</span>
                                        <span class="text-xl font-black text-slate-900 tabular-nums">${{ totalConRecargo.toFixed(2) }}</span>
                                    </div>
                                </div>

                                <!-- El cliente pagará (por cuota) -->
                                <div v-if="cuotasSeleccionadas > 1" class="bg-indigo-50 rounded-xl p-3 text-center">
                                    <span class="text-[9px] font-black text-indigo-400 uppercase tracking-widest block mb-1">El cliente pagará</span>
                                    <span class="text-2xl font-black text-indigo-700 tabular-nums">${{ montoPorCuota.toFixed(2) }}</span>
                                    <span class="text-[10px] font-bold text-indigo-400 block mt-0.5">
                                        en {{ cuotasSeleccionadas }} cuotas
                                    </span>
                                </div>
                            </div>

                            <!-- Recibido + Vuelto (solo efectivo único) -->
                            <div v-if="esUnicoEfectivo && totalVenta > 0" class="px-4 pb-2 space-y-1.5">
                                <div class="flex items-center gap-2">
                                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest shrink-0">Recibido</label>
                                    <div class="relative flex-1">
                                        <span class="absolute left-2.5 top-1/2 -translate-y-1/2 font-bold text-slate-400 text-sm">$</span>
                                        <input
                                            v-model.number="montoRecibido"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            class="w-full pl-7 pr-3 py-2 border-2 border-slate-200 rounded-xl font-bold text-slate-800 focus:border-emerald-500 focus:ring-0 transition-colors text-lg"
                                            placeholder="0.00"
                                        >
                                    </div>
                                </div>

                                <div v-if="sugerencias.length > 0 && montoRecibido === null" class="flex flex-wrap gap-1">
                                    <button
                                        v-for="sug in sugerencias" :key="sug"
                                        @click="montoRecibido = sug"
                                        class="px-2.5 py-1 bg-slate-100 hover:bg-sky-100 hover:text-sky-700 border border-slate-200 hover:border-sky-300 rounded-lg text-[10px] font-bold text-slate-600 transition-all"
                                    >
                                        ${{ sug.toFixed(0) }}
                                    </button>
                                </div>

                                <div v-if="vuelto !== null" class="bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-2 flex justify-between items-center">
                                    <span class="text-emerald-700 font-black text-xs uppercase tracking-widest">Vuelto</span>
                                    <span class="text-emerald-600 font-black text-2xl">${{ vuelto.toFixed(2) }}</span>
                                </div>
                            </div>

                            <!-- Barra de progreso del pago -->
                            <div v-if="totalDisplay > 0" class="px-4 pb-1">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 h-1.5 bg-slate-200 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full transition-all duration-300"
                                            :class="esPagoCompleto ? 'bg-emerald-500' : 'bg-indigo-500'"
                                            :style="{ width: Math.min(100, esUnicoEfectivo ? ((Number(montoRecibido) || 0) / totalDisplay * 100) : (totalAsignado / totalDisplay * 100)) + '%' }">
                                        </div>
                                    </div>
                                    <span class="text-[10px] font-bold" :class="esPagoCompleto ? 'text-emerald-600' : 'text-slate-500'">
                                        <template v-if="esPagoCompleto">Completado</template>
                                        <template v-else-if="esUnicoEfectivo && montoRecibido !== null && montoRecibido !== ''">${{ Number(montoRecibido).toFixed(2) }} / ${{ totalDisplay.toFixed(2) }}</template>
                                        <template v-else>${{ totalAsignado.toFixed(2) }} / ${{ totalDisplay.toFixed(2) }}</template>
                                    </span>
                                </div>
                            </div>

                            <!-- Auto-completar restante -->
                            <div v-if="restante > 0.01 && pagos.length > 0 && pagos.length < 6 && !esUnicoEfectivo" class="px-4 pb-1 flex justify-end">
                                <button @click="autoCompletarRestante" class="text-[10px] font-bold text-indigo-600 hover:text-indigo-800 uppercase tracking-wider">
                                    Asignar restante (${{ restante.toFixed(2) }})
                                </button>
                            </div>

                            <!-- TOTAL + COBRAR -->
                            <div class="px-4 pt-2 pb-3 border-t border-slate-200 mt-1">
                                <div class="flex items-end justify-between mb-2">
                                    <span class="text-xs font-black text-slate-500 uppercase tracking-widest">Total</span>
                                    <span class="text-3xl font-black tracking-tight tabular-nums"
                                        :class="bloqueoPorSaldo ? 'text-rose-600' : (esUnicaTarjeta && recargoMonto > 0 ? 'text-violet-700' : 'text-slate-900')"
                                    >${{ totalDisplay.toFixed(2) }}</span>
                                </div>
                                <button
                                    @click="finalizarVenta"
                                    :disabled="!puedeCobrar"
                                    class="w-full bg-slate-900 hover:bg-sky-600 disabled:bg-slate-200 disabled:text-slate-400 text-white font-black py-3.5 rounded-xl shadow-lg uppercase tracking-widest active:scale-95 transition-all text-sm flex items-center justify-center gap-2"
                                >
                                    <template v-if="pagos.length === 0">Seleccioná un método de pago</template>
                                    <template v-else-if="bloqueoPorSaldo">SALDO INSUFICIENTE</template>
                                    <template v-else-if="esUnicoEfectivo && (montoRecibido === null || montoRecibido === '')">Ingresá el monto recibido</template>
                                    <template v-else-if="esUnicoEfectivo && Number(montoRecibido) < totalVenta">Faltan ${{ (totalVenta - Number(montoRecibido)).toFixed(2) }}</template>
                                    <template v-else-if="!esPagoCompleto">Asigná el total (${{ restante.toFixed(2) }})</template>
                                    <template v-else-if="tieneTarjetaSeleccionada && !bancoSeleccionado">Seleccioná un banco</template>
                                    <template v-else-if="esUnicaTarjeta">Cobrar ${{ totalConRecargo.toFixed(2) }}</template>
                                    <template v-else>Cobrar ${{ totalVenta.toFixed(2) }}</template>
                                    <span class="text-[9px] font-mono font-black text-slate-500 bg-white/20 px-1.5 py-0.5 rounded border border-white/20">F9</span>
                                </button>
                            </div>

                        </div>

                    </div>
                </div>

            </div>
        </div>

        <LectorCamara
            v-if="mostrarEscaner"
            @escaneado="manejarCodigoEscaneado"
            @cerrar="mostrarEscaner = false"
        />

        <ConfirmarPagoModal
            :show="confirmarPagoModal"
            :venta-id="confirmarVentaId"
            :display-info="confirmarDisplayInfo"
            @close="onPagoCancelado"
            @confirmed="onPagoConfirmado"
        />

    </AuthenticatedLayout>
</template>

