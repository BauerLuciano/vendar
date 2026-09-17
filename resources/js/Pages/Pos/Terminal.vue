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
    categorias: Array,
    paymentMethods: Array,
    metodosBase: Array,
    recargos: Object,
    bancosDisponibles: Array,
    configuracionFiscal: Object,
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

const modalCrearCliente = ref(false);
const formCliente = ref({ nombre: '', apellido: '', telefono: '', cuit: '', razon_social: '', domicilio_fiscal: '', tipo_documento: '' });
const creandoCliente = ref(false);
const erroresCliente = ref({ nombre: '', apellido: '', telefono: '', cuit: '', razon_social: '', domicilio_fiscal: '' });

const REGEX_LETRAS_POS = /^[\p{L}]+(?: [\p{L}]+)*$/u;

const limpiarErrorCliente = (campo) => {
    erroresCliente.value[campo] = '';
};

const validarCampoCliente = (campo) => {
    let err = '';

    if (campo === 'nombre') {
        const v = formCliente.value.nombre.trim();
        if (v.length < 2) err = 'El nombre debe tener al menos 2 caracteres.';
        else if (v.length > 50) err = 'El nombre no puede superar los 50 caracteres.';
        else if (!REGEX_LETRAS_POS.test(v)) err = 'El nombre solo puede incluir letras y espacios.';
    }

    if (campo === 'apellido') {
        const v = formCliente.value.apellido.trim();
        if (v && v.length < 2) err = 'El apellido debe tener al menos 2 caracteres.';
        else if (v.length > 50) err = 'El apellido no puede superar los 50 caracteres.';
        else if (v && !REGEX_LETRAS_POS.test(v)) err = 'El apellido solo puede incluir letras y espacios.';
    }

    if (campo === 'telefono') {
        const t = formCliente.value.telefono.trim();
        if (t && !/^\d+$/.test(t)) err = 'El teléfono solo puede contener números.';
        else if (t && t.length < 8) err = 'El teléfono debe tener al menos 8 dígitos.';
        else if (t.length > 15) err = 'El teléfono no puede superar los 15 dígitos.';
    }

    if (campo === 'cuit') {
        const c = formCliente.value.cuit.trim();
        if (c && !/^\d{11}$/.test(c)) err = 'El CUIT debe tener 11 dígitos.';
    }

    if (campo === 'razon_social') {
        const v = formCliente.value.razon_social.trim();
        if (v && v.length > 255) err = 'La razón social no puede superar los 255 caracteres.';
    }

    if (campo === 'domicilio_fiscal') {
        const v = formCliente.value.domicilio_fiscal.trim();
        if (v && v.length > 255) err = 'El domicilio fiscal no puede superar los 255 caracteres.';
    }

    erroresCliente.value[campo] = err;

    return err;
};

const validarFormularioCliente = () => {
    const campos = ['nombre', 'apellido', 'telefono', 'cuit', 'razon_social', 'domicilio_fiscal'];
    const conErrores = campos.filter((campo) => validarCampoCliente(campo));

    if (conErrores.length > 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Revisá los campos marcados',
            text: 'Hay campos con datos inválidos. Corregilos antes de guardar.',
            toast: true, position: 'top-end', showConfirmButton: false, timer: 3000
        });
    }

    return conErrores.length === 0;
};

const moduloFiscalListo = computed(() => !!props.configuracionFiscal);
const letraComprobante = ref(null);
const errorFiscal = ref(null);

const receptorIncompletoParaFacturaA = computed(() => {
    if (letraComprobante.value !== 'A') return false;
    const c = clienteActivoObj.value;
    if (!c) return true;
    return !c.razon_social?.trim() || !c.domicilio_fiscal?.trim();
});

const actualizarLetraEsperada = async () => {
    if (!moduloFiscalListo.value) {
        letraComprobante.value = null;
        errorFiscal.value = null;
        return;
    }
    try {
        const res = await axios.get(route('pos.letra_esperada'), {
            params: { consumidor_id: clienteSeleccionado.value || '' },
        });
        letraComprobante.value = res.data.letra;
        errorFiscal.value = null;
    } catch (e) {
        letraComprobante.value = null;
        errorFiscal.value = e.response?.data?.error || 'No se pudo determinar el comprobante a emitir.';
    }
};

watch(clienteSeleccionado, actualizarLetraEsperada);
watch(moduloFiscalListo, actualizarLetraEsperada, { immediate: true });

const mostrarEscaner = ref(false);

const confirmarPagoModal = ref(false);
const confirmarVentaId = ref(null);
const confirmarDisplayInfo = ref([]);

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
const categoriaActiva = ref(null);
const productosEnCategoria = computed(() => {
    if (!categoriaActiva.value) return [];
    return productosIniciales.value.filter(p => p.categoria_id === categoriaActiva.value);
});

const tabLista = ref('frecuentes');
const favoritosLista = ref([]);
const ultimosVendidosLista = ref([]);
const cargandoFavoritos = ref(false);
const cargandoUltimos = ref(false);
const favoritosIds = ref(new Set());

async function toggleFavorito(productoId) {
    try {
        const res = await axios.post('/pos/toggle-favorito', { producto_id: productoId });
        if (res.data.favorito) {
            favoritosIds.value = new Set([...favoritosIds.value, productoId]);
        } else {
            const s = new Set(favoritosIds.value);
            s.delete(productoId);
            favoritosIds.value = s;
            favoritosLista.value = favoritosLista.value.filter(p => p.id !== productoId);
        }
    } catch (e) { console.error(e); }
}

async function cargarFavoritos() {
    cargandoFavoritos.value = true;
    try {
        const res = await fetch('/pos/favoritos');
        if (res.ok) {
            favoritosLista.value = await res.json();
            favoritosIds.value = new Set(favoritosLista.value.map(p => p.id));
        }
    } catch (e) { console.error(e); }
    finally { cargandoFavoritos.value = false; }
}

async function cargarUltimosVendidos() {
    cargandoUltimos.value = true;
    try {
        const res = await fetch('/pos/ultimos-vendidos');
        if (res.ok) ultimosVendidosLista.value = await res.json();
    } catch (e) { console.error(e); }
    finally { cargandoUltimos.value = false; }
}

watch(tabLista, (val) => {
    if (val === 'favoritos' && favoritosLista.value.length === 0) cargarFavoritos();
    if (val === 'ultimos' && ultimosVendidosLista.value.length === 0) cargarUltimosVendidos();
});

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

const abrirModalCliente = () => {
    formCliente.value = { nombre: '', apellido: '', telefono: '', cuit: '', razon_social: '', domicilio_fiscal: '', tipo_documento: '' };
    Object.keys(erroresCliente.value).forEach((k) => { erroresCliente.value[k] = ''; });
    modalCrearCliente.value = true;
};

const guardarCliente = async () => {
    if (!validarFormularioCliente()) return;
    creandoCliente.value = true;
    try {
        const res = await axios.post(route('pos.crear.cliente'), formCliente.value);
        const nuevo = res.data;
        props.clientes.push(nuevo);
        seleccionarCliente(nuevo);
        modalCrearCliente.value = false;
    } catch (e) {
        const msg = e.response?.data?.errors?.cuit?.[0]
            || e.response?.data?.errors?.nombre?.[0]
            || 'No se pudo crear el cliente.';
        Swal.fire('Error', msg, 'error');
    } finally {
        creandoCliente.value = false;
    }
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

const moduloClientesHabilitado = computed(() => {
    const modulos = page.props.auth?.modulos || {};
    return !!modulos.fiados;
});

watch(tieneCuentaCorriente, (activa) => {
    if (!activa) {
        clienteSeleccionado.value = null;
        busquedaCliente.value = '';
        clientesFiltradosSelect.value = [];
        mostrarDropdownClientes.value = false;
    }
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
    if (!permitirStockNegativo.value && (Number(producto.stock_actual) || 0) <= 0) {
        mostrarFlash('error', `Sin stock: ${producto.nombre}`);
        buscar.value = '';
        return;
    }

    if (producto.unidad_medida === 'Kg') {
        const { value: formValues } = await Swal.fire({
            title: 'Ingresar Cantidad',
            html: `
                <div class="mb-4 text-slate-500 font-bold text-sm">Estás vendiendo: <span class="text-sky-600">${producto.nombre}</span></div>
                <div class="text-[10px] text-amber-600 font-black mb-2 uppercase tracking-widest">Disponible: ${producto.stock_actual} kg</div>
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
                    Swal.showValidationMessage(`Sin stock suficiente (Disponible: ${producto.stock_actual}kg)`);
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
        Swal.fire('Stock Insuficiente', `Solo quedan ${producto.stock_actual} disponibles.`, 'warning');
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
        Swal.fire('Falta Cliente', 'Para usar Cuenta Corriente tenés que seleccionar un cliente.', 'warning');
        return;
    }

    if (moduloFiscalListo.value && letraComprobante.value === 'A' && receptorIncompletoParaFacturaA.value) {
        Swal.fire(
            'Datos fiscales incompletos',
            'Para emitir Factura A el cliente debe tener CUIT, razón social y domicilio fiscal. Completalos desde el selector de cliente.',
            'warning'
        );
        return;
    }

    if (moduloFiscalListo.value && errorFiscal.value) {
        Swal.fire('Facturación no disponible', errorFiscal.value, 'warning');
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
    nextTick(() => { if (inputBusqueda.value) inputBusqueda.value.focus(); });
}

function onPagoCancelado() {
    confirmarPagoModal.value = false;
    confirmarVentaId.value = null;
    confirmarDisplayInfo.value = [];
    nextTick(() => { if (inputBusqueda.value) inputBusqueda.value.focus(); });
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

const fmtMonto = (n) => Number(n).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const turnoDesdeLabel = computed(() => {
    if (!props.turno?.fecha_apertura) return '';
    const d = new Date(props.turno.fecha_apertura);
    if (isNaN(d.getTime())) return '';
    return d.toLocaleString('es-AR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
});

const mostrarConfigTarjeta = ref(true);

const productosVisibles = computed(() => {
    if (buscar.value.length >= 1) return productosFiltrados.value;
    if (categoriaActiva.value) return productosEnCategoria.value;
    if (tabLista.value === 'favoritos') return favoritosLista.value;
    if (tabLista.value === 'ultimos') return ultimosVendidosLista.value;
    if (tabLista.value === 'todos') return productosIniciales.value;
    return (props.frecuentes && props.frecuentes.length > 0) ? props.frecuentes : [];
});

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
});
onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <Head title="Terminal POS - VendAR" />

    <AuthenticatedLayout>
        <div class="py-4 px-4 sm:px-6 lg:px-8 bg-gradient-to-br from-slate-50 to-slate-100 min-h-screen" @click="mostrarDropdownClientes = false; showTransferDropdown = false; showTarjetaDropdown = false">

            <div class="grid grid-cols-12 gap-4 lg:gap-6">

                <!-- ─── COLUMNA IZQUIERDA: Búsqueda + Productos ─── -->
                <div class="col-span-12 lg:col-span-7 xl:col-span-8 flex flex-col gap-3">

                    <!-- Cabecera del turno -->
                    <div v-if="turno" class="flex flex-wrap items-center gap-2">
                        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm px-3 py-2 flex items-center gap-2.5 min-w-[130px]">
                            <span class="w-9 h-9 rounded-xl bg-sky-600 text-white flex items-center justify-center shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </span>
                            <div>
                                <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 leading-none">Turno</p>
                                <p class="text-sm font-black text-slate-800 leading-tight">#{{ turno.id }}</p>
                            </div>
                        </div>
                        <div v-if="turno?.caja?.nombre" class="bg-white rounded-2xl border border-slate-200 shadow-sm px-3 py-2 flex items-center gap-2.5">
                            <span class="w-9 h-9 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                            </span>
                            <div>
                                <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 leading-none">Caja</p>
                                <p class="text-sm font-black text-slate-800 leading-tight">{{ turno.caja.nombre }}</p>
                            </div>
                        </div>
                        <div v-if="turno?.sucursal?.nombre" class="bg-white rounded-2xl border border-slate-200 shadow-sm px-3 py-2 flex items-center gap-2.5">
                            <span class="w-9 h-9 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                            </span>
                            <div>
                                <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 leading-none">Sucursal</p>
                                <p class="text-sm font-black text-slate-800 leading-tight">{{ turno.sucursal.nombre }}</p>
                            </div>
                        </div>
                        <div v-if="page.props.auth?.user?.name" class="bg-white rounded-2xl border border-slate-200 shadow-sm px-3 py-2 flex items-center gap-2.5">
                            <span class="w-9 h-9 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                            </span>
                            <div>
                                <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 leading-none">Cajero</p>
                                <p class="text-sm font-black text-slate-800 leading-tight">{{ page.props.auth.user.name }}</p>
                            </div>
                        </div>
                        <div v-if="turnoDesdeLabel" class="bg-white rounded-2xl border border-slate-200 shadow-sm px-3 py-2 flex items-center gap-2.5">
                            <span class="w-9 h-9 rounded-xl bg-rose-100 text-rose-500 flex items-center justify-center shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                            </span>
                            <div>
                                <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 leading-none">Abierto</p>
                                <p class="text-sm font-black text-slate-800 leading-tight">{{ turnoDesdeLabel }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Buscador grande -->
                    <div class="bg-white rounded-2xl shadow-md border border-slate-200 focus-within:border-sky-500 focus-within:ring-4 focus-within:ring-sky-500/20 transition-all overflow-hidden">
                        <div class="relative flex items-center h-14">
                            <span class="absolute left-4 text-slate-400 pointer-events-none">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                            </span>
                            <input
                                ref="inputBusqueda"
                                v-model="buscar"
                                @input="onBuscarInput"
                                @keyup.enter="procesarBusquedaEnter"
                                type="text"
                                placeholder="Escaneá código o buscá por nombre..."
                                class="w-full pl-12 pr-36 h-full bg-transparent border-none focus:ring-0 text-lg font-bold text-slate-800 placeholder-slate-400"
                                autofocus
                            />
                            <div v-if="buscandoProductos" class="absolute right-36 top-1/2 -translate-y-1/2">
                                <svg class="animate-spin h-5 w-5 text-sky-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            </div>
                            <div class="absolute right-2 flex items-center gap-1.5">
                                <span class="hidden sm:block px-2 py-0.5 bg-slate-100 rounded-lg text-[10px] font-bold text-slate-400 uppercase border border-slate-200">
                                    ENTER ↵
                                </span>
                                <button
                                    @click="mostrarEscaner = true"
                                    class="bg-sky-100 text-sky-600 p-2 rounded-xl hover:bg-sky-600 hover:text-white transition-all shadow-sm border border-sky-200 flex items-center justify-center"
                                    title="Escanear con Cámara"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Producto agregado (feedback animado) -->
                    <div v-if="productAddedFeedback" class="fixed top-24 left-1/2 -translate-x-1/2 z-50">
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

                    <!-- Categorías -->
                    <div v-if="buscar.length < 1 && categorias && categorias.length > 0" class="mb-1">
                        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-thin">
                            <button
                                v-if="categoriaActiva"
                                @click="categoriaActiva = null"
                                class="shrink-0 px-3 py-2 rounded-xl text-[10px] font-black uppercase tracking-wider transition-all border"
                                :class="categoriaActiva === null ? 'bg-sky-600 text-white border-sky-600' : 'bg-white text-slate-400 border-slate-200 hover:border-slate-300'"
                            >
                                Todos
                            </button>
                            <button
                                v-for="cat in categorias" :key="cat.id"
                                @click="categoriaActiva = categoriaActiva === cat.id ? null : cat.id"
                                class="shrink-0 px-3.5 py-2 rounded-xl text-xs font-bold transition-all border"
                                :class="categoriaActiva === cat.id ? 'bg-sky-600 text-white border-sky-600 shadow-sm' : 'bg-white text-slate-600 border-slate-200 hover:border-sky-300 hover:text-sky-600'"
                            >
                                {{ cat.nombre }}
                            </button>
                        </div>
                    </div>

                    <!-- Tabs: cuando no hay búsqueda ni categoría activa -->
                    <div v-if="buscar.length < 1 && !categoriaActiva" class="mb-1">
                        <div class="flex items-center gap-1 border-b border-slate-200 pb-1">
                            <button @click="tabLista = 'frecuentes'"
                                class="px-3 py-2 rounded-t-lg text-[10px] font-black uppercase tracking-wider transition-all"
                                :class="tabLista === 'frecuentes' ? 'bg-sky-600 text-white' : 'text-slate-500 hover:text-sky-600'">
                                Más vendidos
                            </button>
                            <button @click="tabLista = 'favoritos'; cargarFavoritos()"
                                class="px-3 py-2 rounded-t-lg text-[10px] font-black uppercase tracking-wider transition-all"
                                :class="tabLista === 'favoritos' ? 'bg-rose-600 text-white' : 'text-slate-500 hover:text-rose-600'">
                                Favoritos
                            </button>
                            <button @click="tabLista = 'ultimos'; cargarUltimosVendidos()"
                                class="px-3 py-2 rounded-t-lg text-[10px] font-black uppercase tracking-wider transition-all"
                                :class="tabLista === 'ultimos' ? 'bg-amber-600 text-white' : 'text-slate-500 hover:text-amber-600'">
                                Últimos vendidos
                            </button>
                            <button @click="tabLista = 'todos'"
                                class="px-3 py-2 rounded-t-lg text-[10px] font-black uppercase tracking-wider transition-all"
                                :class="tabLista === 'todos' ? 'bg-slate-700 text-white' : 'text-slate-500 hover:text-slate-700'">
                                Todos
                            </button>
                        </div>
                    </div>

                    <!-- Resultados según el contexto -->
                    <div v-if="buscar.length >= 1" class="flex-1">
                        <p class="text-xs font-bold text-slate-500 mb-2">Resultados: {{ productosVisibles.length }}</p>
                        <div v-if="buscandoProductos" class="flex items-center justify-center py-14 text-slate-400">
                            <svg class="animate-spin h-7 w-7 mr-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            Buscando productos...
                        </div>
                        <div v-else-if="productosVisibles.length === 0" class="flex flex-col items-center justify-center py-16 text-slate-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                            <p class="font-bold text-base">Sin resultados</p>
                            <p class="text-sm">Probá con otro término de búsqueda</p>
                        </div>
                        <div v-else class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-2.5">
                            <!-- CARD DE PRODUCTO -->
                            <div v-for="p in productosVisibles" :key="p.id"
                                @click="clickEnProducto(p)"
                                :class="!permitirStockNegativo && (Number(p.stock_actual) || 0) <= 0 ? 'opacity-55 grayscale' : 'hover:border-sky-400 hover:shadow-lg hover:-translate-y-0.5'"
                                class="group relative bg-white rounded-2xl border border-slate-200 transition-all cursor-pointer overflow-hidden"
                            >
                                <div class="relative aspect-square bg-slate-50 border-b border-slate-100">
                                    <img v-if="p.imagen" :src="'/storage/' + p.imagen" class="w-full h-full object-cover" loading="lazy" />
                                    <div v-else class="w-full h-full flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                                    </div>
                                    <button v-if="tabLista !== 'frecuentes'"
                                        @click.stop="toggleFavorito(p.id)"
                                        class="absolute top-1.5 left-1.5 w-7 h-7 flex items-center justify-center bg-white/90 backdrop-blur rounded-full shadow-sm text-sm transition-all hover:scale-110 z-10"
                                        :class="favoritosIds.has(p.id) ? 'text-rose-500' : 'text-slate-300 hover:text-rose-400'"
                                    >
                                        {{ favoritosIds.has(p.id) ? '❤️' : '🤍' }}
                                    </button>
                                    <div v-if="p.en_liquidacion" class="absolute top-1.5 right-1.5 z-10 px-2 py-0.5 bg-rose-500 text-white rounded-lg text-[10px] font-black leading-none">
                                        -{{ p.porcentaje_descuento }}%
                                    </div>
                                </div>
                                <div class="p-2.5">
                                    <p class="font-bold text-slate-800 text-sm leading-tight line-clamp-2 min-h-[2rem]">{{ p.nombre }}</p>
                                    <div class="flex items-baseline gap-1 mt-1">
                                        <p v-if="p.en_liquidacion" class="text-rose-600 font-black text-sm">${{ fmtMonto(p.precio_rebajado) }}</p>
                                        <p :class="p.en_liquidacion ? 'text-slate-400 line-through text-[11px]' : 'text-slate-900 font-black text-base'">${{ fmtMonto(p.precio_venta) }}</p>
                                        <span v-if="p.unidad_medida === 'Kg'" class="text-[10px] text-slate-400 font-bold">/kg</span>
                                    </div>
                                    <p class="mt-1.5 text-[10px] font-bold leading-none"
                                        :class="(Number(p.stock_actual) || 0) <= 0 ? 'text-rose-500' : (Number(p.stock_actual) <= (p.stock_minimo || 5) ? 'text-amber-500' : 'text-slate-400')">
                                        <template v-if="(Number(p.stock_actual) || 0) <= 0 && !permitirStockNegativo">Sin stock</template>
                                        <template v-else>Stock: {{ p.stock_actual }}<span v-if="p.unidad_medida === 'Kg'"> kg</span></template>
                                    </p>
                                </div>
                                <div v-if="cantidadEnCarrito(p.id)" class="absolute bottom-2 right-2 z-10 bg-sky-600 text-white text-[11px] font-black px-2 py-0.5 rounded-full shadow-md">
                                    ×{{ cantidadEnCarrito(p.id) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="categoriaActiva" class="flex-1">
                        <div v-if="productosVisibles.length === 0" class="flex flex-col items-center justify-center py-16 text-slate-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                            <p class="font-bold text-base">Sin productos en esta categoría</p>
                        </div>
                        <div v-else class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-2.5">
                            <div v-for="p in productosVisibles" :key="'cat-' + p.id"
                                @click="clickEnProducto(p)"
                                :class="!permitirStockNegativo && (Number(p.stock_actual) || 0) <= 0 ? 'opacity-55 grayscale' : 'hover:border-sky-400 hover:shadow-lg hover:-translate-y-0.5'"
                                class="group relative bg-white rounded-2xl border border-slate-200 transition-all cursor-pointer overflow-hidden"
                            >
                                <div class="relative aspect-square bg-slate-50 border-b border-slate-100">
                                    <img v-if="p.imagen" :src="'/storage/' + p.imagen" class="w-full h-full object-cover" loading="lazy" />
                                    <div v-else class="w-full h-full flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                                    </div>
                                    <div v-if="p.en_liquidacion" class="absolute top-1.5 right-1.5 z-10 px-2 py-0.5 bg-rose-500 text-white rounded-lg text-[10px] font-black leading-none">
                                        -{{ p.porcentaje_descuento }}%
                                    </div>
                                </div>
                                <div class="p-2.5">
                                    <p class="font-bold text-slate-800 text-sm leading-tight line-clamp-2 min-h-[2rem]">{{ p.nombre }}</p>
                                    <div class="flex items-baseline gap-1 mt-1">
                                        <p v-if="p.en_liquidacion" class="text-rose-600 font-black text-sm">${{ fmtMonto(p.precio_rebajado) }}</p>
                                        <p :class="p.en_liquidacion ? 'text-slate-400 line-through text-[11px]' : 'text-slate-900 font-black text-base'">${{ fmtMonto(p.precio_venta) }}</p>
                                        <span v-if="p.unidad_medida === 'Kg'" class="text-[10px] text-slate-400 font-bold">/kg</span>
                                    </div>
                                </div>
                                <div v-if="cantidadEnCarrito(p.id)" class="absolute bottom-2 right-2 z-10 bg-sky-600 text-white text-[11px] font-black px-2 py-0.5 rounded-full shadow-md">
                                    ×{{ cantidadEnCarrito(p.id) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="flex-1">
                        <div v-if="tabLista === 'favoritos' && cargandoFavoritos" class="flex items-center justify-center py-14 text-slate-400">
                            <svg class="animate-spin h-7 w-7 mr-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            Cargando favoritos...
                        </div>
                        <div v-else-if="tabLista === 'ultimos' && cargandoUltimos" class="flex items-center justify-center py-14 text-slate-400">
                            <svg class="animate-spin h-7 w-7 mr-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            Cargando últimos vendidos...
                        </div>
                        <div v-else-if="productosVisibles.length === 0" class="flex flex-col items-center justify-center py-16 text-slate-300">
                            <template v-if="tabLista === 'frecuentes'">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                                <p class="font-bold text-lg">Escanéá o buscá un producto</p>
                            </template>
                            <template v-else-if="tabLista === 'favoritos'">
                                <p class="font-bold text-base">No tenés productos favoritos</p>
                            </template>
                            <template v-else-if="tabLista === 'ultimos'">
                                <p class="font-bold text-base">No hay ventas en este turno todavía</p>
                            </template>
                            <template v-else>
                                <p class="font-bold text-base">No hay productos cargados</p>
                            </template>
                        </div>
                        <div v-else class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-2.5">
                            <div v-for="p in productosVisibles" :key="'tab-' + p.id"
                                @click="clickEnProducto(p)"
                                :class="!permitirStockNegativo && (Number(p.stock_actual) || 0) <= 0 ? 'opacity-55 grayscale' : 'hover:border-sky-400 hover:shadow-lg hover:-translate-y-0.5'"
                                class="group relative bg-white rounded-2xl border border-slate-200 transition-all cursor-pointer overflow-hidden"
                            >
                                <div class="relative aspect-square bg-slate-50 border-b border-slate-100">
                                    <img v-if="p.imagen" :src="'/storage/' + p.imagen" class="w-full h-full object-cover" loading="lazy" />
                                    <div v-else class="w-full h-full flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                                    </div>
                                    <button v-if="tabLista === 'todos'"
                                        @click.stop="toggleFavorito(p.id)"
                                        class="absolute top-1.5 left-1.5 w-7 h-7 flex items-center justify-center bg-white/90 backdrop-blur rounded-full shadow-sm text-sm transition-all hover:scale-110 z-10"
                                        :class="favoritosIds.has(p.id) ? 'text-rose-500' : 'text-slate-300 hover:text-rose-400'"
                                    >
                                        {{ favoritosIds.has(p.id) ? '❤️' : '🤍' }}
                                    </button>
                                    <div v-if="p.en_liquidacion" class="absolute top-1.5 right-1.5 z-10 px-2 py-0.5 bg-rose-500 text-white rounded-lg text-[10px] font-black leading-none">
                                        -{{ p.porcentaje_descuento }}%
                                    </div>
                                </div>
                                <div class="p-2.5">
                                    <p class="font-bold text-slate-800 text-sm leading-tight line-clamp-2 min-h-[2rem]">{{ p.nombre }}</p>
                                    <div class="flex items-baseline gap-1 mt-1">
                                        <p v-if="p.en_liquidacion" class="text-rose-600 font-black text-sm">${{ fmtMonto(p.precio_rebajado) }}</p>
                                        <p :class="p.en_liquidacion ? 'text-slate-400 line-through text-[11px]' : 'text-slate-900 font-black text-base'">${{ fmtMonto(p.precio_venta) }}</p>
                                        <span v-if="p.unidad_medida === 'Kg'" class="text-[10px] text-slate-400 font-bold">/kg</span>
                                    </div>
                                </div>
                                <div v-if="cantidadEnCarrito(p.id)" class="absolute bottom-2 right-2 z-10 bg-sky-600 text-white text-[11px] font-black px-2 py-0.5 rounded-full shadow-md">
                                    ×{{ cantidadEnCarrito(p.id) }}
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
<!-- ─── COLUMNA DERECHA: Cliente → Carrito → Pagos → Cobrar ─── -->
                <div class="col-span-12 lg:col-span-5 xl:col-span-4">
                    <div class="bg-white rounded-3xl shadow-2xl shadow-slate-200/50 flex flex-col h-[calc(100vh-140px)] sticky top-4 border border-slate-200 overflow-hidden">

                        <!-- Cabecera del panel -->
                        <div class="shrink-0 px-4 py-2.5 bg-gradient-to-r from-sky-600 to-sky-500 text-white flex items-center justify-between">
                            <div>
                                <p class="text-[9px] font-black uppercase tracking-widest text-sky-100 leading-none">Total de la venta</p>
                                <p class="text-xl font-black leading-tight tabular-nums">${{ totalDisplay.toFixed(2) }}</p>
                            </div>
                            <span class="text-[11px] font-bold text-sky-100 bg-white/10 px-2.5 py-1 rounded-lg">
                                {{ carrito.length }} {{ carrito.length === 1 ? 'producto' : 'productos' }}
                            </span>
                        </div>

                        <!-- 1. CLIENTE -->
                        <div class="shrink-0 px-3 pt-2.5 pb-2 border-b border-slate-200 bg-slate-50/50">
                            <!-- Sin Cuenta Corriente: Consumidor Final fijo (sin selector) -->
                            <div v-if="!tieneCuentaCorriente" class="bg-white px-3 py-2 rounded-xl border border-slate-200 shadow-sm flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-sky-500 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" /></svg>
                                <span class="text-sm font-bold text-slate-700 truncate">Consumidor Final</span>
                                <span class="ml-auto text-[9px] font-black uppercase tracking-widest text-slate-400 shrink-0">Sin identificar</span>
                            </div>

                            <!-- Con Cuenta Corriente: selector de cliente -->
                            <div v-else class="relative w-full" @click.stop>
                                <div
                                    @click="mostrarDropdownClientes = !mostrarDropdownClientes"
                                    class="bg-white px-3 py-2 rounded-xl text-sm font-bold cursor-pointer flex justify-between items-center border transition-all shadow-sm"
                                    :class="clienteActivoObj
                                        ? 'text-slate-700 border-slate-200 hover:border-sky-400'
                                        : 'text-amber-700 border-amber-400 bg-amber-50/60 hover:border-amber-500'"
                                >
                                    <span class="truncate flex items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" :class="clienteActivoObj ? 'text-sky-500' : 'text-amber-500'" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" /></svg>
                                        {{ clienteActivoObj ? clienteActivoObj.nombre + ' ' + clienteActivoObj.apellido : 'Seleccioná un cliente' }}
                                    </span>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                </div>

                                <div v-if="mostrarDropdownClientes" class="absolute right-0 top-full mt-2 w-full min-w-[260px] bg-white border border-slate-200 shadow-xl rounded-2xl z-50 overflow-hidden">
                                    <div class="p-3 border-b border-slate-100 bg-slate-50 relative">
                                        <span class="absolute left-5 top-1/2 -translate-y-1/2 text-slate-400">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                                        </span>
                                        <input
                                            v-model="busquedaCliente"
                                            @input="onBuscarClienteInput"
                                            type="text"
                                            placeholder="Buscá por nombre o documento..."
                                            class="w-full pl-9 pr-10 text-sm font-medium border-slate-200 rounded-xl focus:ring-sky-500 focus:border-sky-500 py-2"
                                            autofocus
                                        >
                                        <button
                                            v-if="!buscandoClientes && moduloClientesHabilitado"
                                            @click="abrirModalCliente"
                                            type="button"
                                            title="Crear cliente nuevo"
                                            class="absolute right-3 top-1/2 -translate-y-1/2 text-sky-500 hover:text-sky-700 transition-colors"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" /></svg>
                                        </button>
                                        <div v-if="buscandoClientes" class="absolute right-3 top-1/2 -translate-y-1/2">
                                            <svg class="animate-spin h-3.5 w-3.5 text-sky-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                        </div>
                                    </div>
                                    <ul class="max-h-56 overflow-y-auto">
                                        <li
                                            v-if="clienteActivoObj"
                                            @click="seleccionarCliente(null)"
                                            class="px-4 py-2.5 text-sm font-bold text-slate-500 hover:bg-slate-50 cursor-pointer border-b border-slate-50 flex items-center gap-2"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                            Quitar selección
                                        </li>
                                        <li
                                            v-for="c in clientesFiltradosSelect" :key="'cli-' + c.id"
                                            @click="seleccionarCliente(c)"
                                            class="px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-sky-50 hover:text-sky-700 cursor-pointer border-b border-slate-50"
                                        >
                                            <div class="flex justify-between items-center">
                                                <span>{{ c.nombre }} {{ c.apellido }}</span>
                                                <span v-if="c.documento" class="text-[10px] font-mono text-slate-400">{{ c.documento }}</span>
                                            </div>
                                        </li>
                                        <li v-if="clientesFiltradosSelect.length === 0 && busquedaCliente.length >= 2" class="px-4 py-2.5 text-sm font-medium text-slate-400">
                                            Sin resultados...
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <p v-if="tieneCuentaCorriente && !clienteActivoObj" class="mt-1.5 px-1 text-[10px] font-bold text-amber-600">
                                La venta por cuenta corriente exige seleccionar un cliente o cambiar el método de pago.
                            </p>

                            <div v-if="moduloFiscalListo" class="mt-2">
                                <div v-if="errorFiscal" class="flex items-center gap-1.5 bg-amber-50 border border-amber-200 rounded-xl px-3 py-2 text-[11px] font-bold text-amber-700">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                    <span>{{ errorFiscal }}</span>
                                </div>
                                <div v-else class="flex items-center justify-between bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5">
                                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-500">Comprobante</span>
                                    <span
                                        v-if="letraComprobante === 'A'"
                                        class="px-2 py-0.5 rounded-lg text-[11px] font-black text-white"
                                        :class="receptorIncompletoParaFacturaA ? 'bg-amber-500' : 'bg-sky-600'"
                                        :title="receptorIncompletoParaFacturaA ? 'Faltan datos fiscales del receptor' : ''"
                                    >
                                        Factura A
                                    </span>
                                    <span v-else-if="letraComprobante === 'B'" class="px-2 py-0.5 rounded-lg text-[11px] font-black text-white bg-slate-500">
                                        Factura B
                                    </span>
                                    <span v-else class="text-[11px] text-slate-400 font-bold">—</span>
                                </div>
                                <p v-if="letraComprobante === 'A' && receptorIncompletoParaFacturaA" class="mt-1 px-1 text-[10px] font-bold text-amber-600">
                                    Completá CUIT, razón social y domicilio fiscal del cliente para emitir Factura A.
                                </p>
                            </div>
                        </div>

                        <Teleport to="body">
                            <div v-if="modalCrearCliente" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40" @click.self="modalCrearCliente = false">
                                <div class="bg-white rounded-3xl shadow-2xl w-full max-w-sm mx-4 overflow-hidden">
                                    <div class="p-5">
                                        <div class="flex items-center justify-between mb-4">
                                            <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest">Nuevo Cliente</h3>
                                            <button @click="modalCrearCliente = false" class="text-slate-400 hover:text-slate-600 transition-colors">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                            </button>
                                        </div>
                                        <div class="space-y-3">
                                            <div>
                                                <label class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1 block">Nombre *</label>
                                                <input v-model="formCliente.nombre" type="text" maxlength="50" placeholder="Nombre"
                                                    @input="limpiarErrorCliente('nombre')"
                                                    @blur="validarCampoCliente('nombre')"
                                                    class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-medium focus:ring-sky-500 focus:border-sky-500"
                                                    :class="{'border-rose-500': erroresCliente.nombre}" autofocus>
                                                <p v-if="erroresCliente.nombre" class="mt-1 text-[10px] text-rose-500 font-bold">{{ erroresCliente.nombre }}</p>
                                            </div>
                                            <div>
                                                <label class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1 block">Apellido</label>
                                                <input v-model="formCliente.apellido" type="text" maxlength="50" placeholder="Apellido"
                                                    @input="limpiarErrorCliente('apellido')"
                                                    @blur="validarCampoCliente('apellido')"
                                                    class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-medium focus:ring-sky-500 focus:border-sky-500"
                                                    :class="{'border-rose-500': erroresCliente.apellido}">
                                                <p v-if="erroresCliente.apellido" class="mt-1 text-[10px] text-rose-500 font-bold">{{ erroresCliente.apellido }}</p>
                                            </div>
                                            <div>
                                                <label class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1 block">Teléfono</label>
                                                <input v-model="formCliente.telefono" type="text" placeholder="Teléfono"
                                                    @input="formCliente.telefono = formCliente.telefono.replace(/\D/g, '').slice(0, 15); limpiarErrorCliente('telefono')"
                                                    @blur="validarCampoCliente('telefono')"
                                                    class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-medium focus:ring-sky-500 focus:border-sky-500"
                                                    :class="{'border-rose-500': erroresCliente.telefono}">
                                                <p v-if="erroresCliente.telefono" class="mt-1 text-[10px] text-rose-500 font-bold">{{ erroresCliente.telefono }}</p>
                                            </div>
                                            <div>
                                                <label class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1 block">CUIT</label>
                                                <input v-model="formCliente.cuit" type="text" inputmode="numeric" placeholder="11 dígitos (para Factura A)"
                                                    @input="formCliente.cuit = formCliente.cuit.replace(/\D/g, '').slice(0, 11); limpiarErrorCliente('cuit')"
                                                    @blur="validarCampoCliente('cuit')"
                                                    class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-medium focus:ring-sky-500 focus:border-sky-500"
                                                    :class="{'border-rose-500': erroresCliente.cuit}">
                                                <p v-if="erroresCliente.cuit" class="mt-1 text-[10px] text-rose-500 font-bold">{{ erroresCliente.cuit }}</p>
                                            </div>
                                            <div>
                                                <label class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1 block">Razón Social</label>
                                                <input v-model="formCliente.razon_social" type="text" placeholder="Solo si facturás a una empresa"
                                                    @input="limpiarErrorCliente('razon_social')"
                                                    @blur="validarCampoCliente('razon_social')"
                                                    class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-medium focus:ring-sky-500 focus:border-sky-500"
                                                    :class="{'border-rose-500': erroresCliente.razon_social}">
                                                <p v-if="erroresCliente.razon_social" class="mt-1 text-[10px] text-rose-500 font-bold">{{ erroresCliente.razon_social }}</p>
                                            </div>
                                            <div>
                                                <label class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1 block">Domicilio Fiscal</label>
                                                <input v-model="formCliente.domicilio_fiscal" type="text" placeholder="Domicilio fiscal (para Factura A)"
                                                    @input="limpiarErrorCliente('domicilio_fiscal')"
                                                    @blur="validarCampoCliente('domicilio_fiscal')"
                                                    class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-medium focus:ring-sky-500 focus:border-sky-500"
                                                    :class="{'border-rose-500': erroresCliente.domicilio_fiscal}">
                                                <p v-if="erroresCliente.domicilio_fiscal" class="mt-1 text-[10px] text-rose-500 font-bold">{{ erroresCliente.domicilio_fiscal }}</p>
                                            </div>
                                        </div>
                                        <div class="flex gap-2 mt-5">
                                            <button @click="modalCrearCliente = false" class="flex-1 border border-slate-300 text-slate-600 text-[10px] font-black uppercase tracking-widest px-4 py-2.5 rounded-xl hover:bg-slate-50 transition-colors">Cancelar</button>
                                            <button @click="guardarCliente" :disabled="creandoCliente || !formCliente.nombre.trim()" class="flex-1 bg-sky-600 text-white text-[10px] font-black uppercase tracking-widest px-4 py-2.5 rounded-xl hover:bg-sky-700 disabled:opacity-50 transition-colors flex items-center justify-center gap-2">
                                                <svg v-if="creandoCliente" class="animate-spin h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                                Crear
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </Teleport>

                        <!-- 2. ZONA MEDIA (scroll): CARRITO + PAGOS -->
                        <div class="flex-1 overflow-y-auto px-3 py-2 space-y-2 min-h-0">

                            <!-- CARRITO -->
                            <div v-if="carrito.length === 0" class="h-full min-h-[140px] flex flex-col items-center justify-center text-slate-300">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-14 w-14 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                                <p class="font-bold text-sm">Carrito vacío</p>
                            </div>

                            <div v-for="(item, index) in carrito" :key="'it-' + item.id"
                                class="flex items-center gap-2 px-2 py-2 bg-white border border-slate-100 rounded-xl hover:border-sky-200 transition-all group"
                            >
                                <div class="flex items-center bg-slate-50 rounded-lg border border-slate-200 shrink-0">
                                    <button @click="decrementarCantidad(index)" type="button" class="w-8 h-8 flex items-center justify-center text-slate-500 hover:text-rose-500 font-bold text-lg leading-none">−</button>
                                    <input
                                        type="number"
                                        v-model.number="item.cantidad"
                                        min="0"
                                        @blur="validarCantidad(index)"
                                        @keydown="prevenirNegativo($event)"
                                        class="w-10 text-center bg-transparent border-none text-sm font-black p-0 focus:ring-0 text-sky-700 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none [&]:[appearance:textfield]"
                                    >
                                    <button @click="incrementarCantidad(index)" type="button" class="w-8 h-8 flex items-center justify-center text-slate-500 hover:text-emerald-500 font-bold text-lg leading-none">+</button>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <span class="font-bold text-slate-800 text-sm block truncate">{{ item.nombre }}</span>
                                </div>

                                <span class="font-black text-slate-800 text-sm shrink-0 tabular-nums">${{ (item.cantidad * item.precio_venta).toFixed(2) }}</span>

                                <button @click="eliminarDelCarrito(index)" class="shrink-0 w-7 h-7 flex items-center justify-center text-slate-300 hover:text-rose-500 hover:bg-rose-50 rounded-lg transition-all text-sm" title="Quitar">✕</button>
                            </div>

                            <!-- Información de crédito (cuenta corriente) -->
                            <div v-if="tieneCuentaCorriente && clienteActivoObj"
                                class="px-2 py-1.5 border border-slate-100 rounded-lg"
                                :class="bloqueoPorSaldo ? 'bg-rose-50 border-rose-200' : 'bg-slate-50/50'"
                            >
                                <div class="flex items-center justify-between text-xs" :class="bloqueoPorSaldo ? 'text-rose-600 font-bold' : 'text-emerald-600 font-bold'">
                                    <span>Crédito disponible</span>
                                    <span>${{ disponibleCliente.toFixed(2) }}</span>
                                </div>
                            </div>
                            <div v-else-if="tieneCuentaCorriente && !clienteActivoObj" class="px-2 py-1.5 bg-amber-50/50 border border-amber-100 rounded-lg text-[11px] font-bold text-amber-600">
                                Seleccioná un cliente para fiarle
                            </div>

                            <div class="border-t border-dashed border-slate-200 mt-1 pt-1"></div>
<!-- 3. MÉTODOS DE PAGO -->
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Métodos de pago</p>

                                <!-- Fila 1: Efectivo + Cuenta Corriente -->
                                <div class="grid grid-cols-2 gap-1.5">
                                    <button
                                        @click="togglePago('EFECTIVO')"
                                        class="flex items-center justify-center gap-1.5 py-2.5 rounded-xl border-2 transition-all shadow-sm text-xs font-black uppercase tracking-wider"
                                        :class="esUnicoEfectivo || pagos.some(p => p.metodo_pago === 'EFECTIVO')
                                            ? 'bg-emerald-50 border-emerald-500 text-emerald-700 shadow-emerald-100'
                                            : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50 hover:border-slate-300'"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h14a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2V9zm3 1h6m-6 4h6"/></svg>
                                        Efectivo
                                        <span v-if="teclaDeMetodo('EFECTIVO')" class="text-[8px] font-mono text-slate-400 bg-white px-1 rounded border border-slate-200">{{ teclaDeMetodo('EFECTIVO') }}</span>
                                    </button>
                                    <button
                                        v-if="METODOS_DISPONIBLES.some(m => m.value === 'CUENTA_CORRIENTE')"
                                        @click="togglePago('CUENTA_CORRIENTE')"
                                        class="flex items-center justify-center gap-1.5 py-2.5 rounded-xl border-2 transition-all shadow-sm text-xs font-black uppercase tracking-wider"
                                        :class="tieneCuentaCorriente
                                            ? 'bg-indigo-50 border-indigo-500 text-indigo-700 shadow-indigo-100'
                                            : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50 hover:border-slate-300'"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg>
                                        Cta. Corriente
                                        <span v-if="teclaDeMetodo('CUENTA_CORRIENTE')" class="text-[8px] font-mono text-slate-400 bg-white px-1 rounded border border-slate-200">{{ teclaDeMetodo('CUENTA_CORRIENTE') }}</span>
                                    </button>
                                </div>

                                <!-- Fila 2: Transferencias + Tarjetas (dropdowns) -->
                                <div class="grid grid-cols-2 gap-1.5 mt-1.5">
                                    <div class="relative" @click.stop>
                                        <button
                                            @click="showTransferDropdown = !showTransferDropdown; showTarjetaDropdown = false"
                                            class="w-full flex items-center justify-center gap-1.5 py-2.5 rounded-xl border-2 transition-all shadow-sm text-xs font-black uppercase tracking-wider"
                                            :class="tieneTransferenciaActiva()
                                                ? 'bg-sky-50 border-sky-500 text-sky-700 shadow-sky-100'
                                                : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50 hover:border-slate-300'"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                            Transferencias
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                            <span v-if="teclaDeMetodo('TRANSFERENCIA')" class="text-[8px] font-mono text-slate-400 bg-white px-1 rounded border border-slate-200">{{ teclaDeMetodo('TRANSFERENCIA') }}</span>
                                        </button>
                                        <div v-if="showTransferDropdown && transferMethods.length > 0"
                                            class="absolute bottom-full left-0 mb-1 w-full bg-white border border-slate-200 shadow-xl rounded-xl z-50 overflow-hidden">
                                            <button v-for="m in transferMethods" :key="m.value"
                                                @click="seleccionarTransferencia(m.value)"
                                                class="w-full px-3 py-2.5 text-left text-sm font-bold flex items-center gap-2 hover:bg-sky-50 transition-colors"
                                                :class="pagos.some(p => p.metodo_pago === m.value) ? 'text-sky-700 bg-sky-50/50' : 'text-slate-700'">
                                                {{ m.label }}
                                                <span v-if="teclaDeMetodo(m.value)" class="ml-auto text-[8px] font-mono text-slate-400 bg-slate-100 px-1 rounded border border-slate-200">{{ teclaDeMetodo(m.value) }}</span>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="relative" @click.stop>
                                        <button
                                            @click="showTarjetaDropdown = !showTarjetaDropdown; showTransferDropdown = false"
                                            class="w-full flex items-center justify-center gap-1.5 py-2.5 rounded-xl border-2 transition-all shadow-sm text-xs font-black uppercase tracking-wider"
                                            :class="tieneTarjetaActiva()
                                                ? 'bg-violet-50 border-violet-500 text-violet-700 shadow-violet-100'
                                                : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50 hover:border-slate-300'"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="2" y="5" width="20" height="14" rx="2" stroke-width="1.5"/><line x1="2" y1="10" x2="22" y2="10" stroke-width="1.5"/><circle cx="8" cy="15" r="1.5" fill="currentColor" stroke="none"/><circle cx="13" cy="15" r="1.5" fill="currentColor" stroke="none"/></svg>
                                            Tarjetas
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                        </button>
                                        <div v-if="showTarjetaDropdown && tarjetaMethods.length > 0"
                                            class="absolute bottom-full left-0 mb-1 w-full bg-white border border-slate-200 shadow-xl rounded-xl z-50 overflow-hidden">
                                            <button v-for="m in tarjetaMethods" :key="m.value"
                                                @click="seleccionarTarjeta(m.value)"
                                                class="w-full px-3 py-2.5 text-left text-sm font-bold flex items-center gap-2 hover:bg-violet-50 transition-colors"
                                                :class="pagos.some(p => p.metodo_pago === m.value) ? 'text-violet-700 bg-violet-50/50' : 'text-slate-700'">
                                                {{ m.label }}
                                                <span v-if="teclaDeMetodo(m.value)" class="ml-auto text-[8px] font-mono text-slate-400 bg-slate-100 px-1 rounded border border-slate-200">{{ teclaDeMetodo(m.value) }}</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Montos por método (pago combinado) -->
                            <div v-if="!esUnicoEfectivo && !esUnicaTarjeta" class="space-y-1.5">
                                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Montos por método</p>
                                <div v-for="(pago, idx) in pagos" :key="'m-' + idx" class="flex items-center gap-2">
                                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-500 min-w-[70px] shrink-0">{{ METODOS_DISPONIBLES.find(m => m.value === pago.metodo_pago)?.label || pago.metodo_pago }}</span>
                                    <div class="relative flex-1">
                                        <span class="absolute left-2 top-1/2 -translate-y-1/2 text-[11px] font-bold text-slate-400">$</span>
                                        <input
                                            v-model.number="pago.monto"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            placeholder="0.00"
                                            class="w-full pl-6 pr-2 py-1.5 border border-slate-200 rounded-lg text-sm font-bold text-slate-800 focus:border-sky-500 focus:ring-0 transition-colors [&::-webkit-inner-spin-button]:appearance-none"
                                            @focus="$event.target.select()"
                                        >
                                    </div>
                                    <button
                                        v-if="pagos.length > 1"
                                        @click="removerPago(idx)"
                                        class="p-1 text-slate-300 hover:text-rose-500 hover:bg-rose-50 rounded-lg transition-all shrink-0"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Configuración de tarjeta (banco + cuotas + recargo) -->
                            <div v-if="tieneTarjetaSeleccionada" class="space-y-2 p-2.5 bg-slate-50/60 border border-slate-100 rounded-2xl">
                                <div>
                                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest block mb-1">Banco</label>
                                    <select
                                        v-model="bancoSeleccionado"
                                        @change="onBancoChange"
                                        class="w-full px-2.5 py-2 border border-slate-200 rounded-xl text-sm font-bold text-slate-800 focus:border-sky-500 focus:ring-0 transition-colors bg-white"
                                    >
                                        <option value="" disabled>Seleccionar banco...</option>
                                        <option v-for="b in bancosDisponibles" :key="b" :value="b">{{ b }}</option>
                                    </select>
                                </div>

                                <div v-if="sinRecargosConfigurados" class="bg-amber-50 border border-amber-200 rounded-xl px-3 py-2 text-center">
                                    <span class="text-[11px] font-bold text-amber-700">Sin recargos configurados para este banco</span>
                                </div>

                                <div v-if="tipoTarjetaSeleccionado === 'CREDITO' && !sinRecargosConfigurados && cuotasDisponibles.length > 0">
                                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest block mb-1">Cuotas</label>
                                    <div class="grid grid-cols-4 gap-1.5">
                                        <button
                                            v-for="cuota in cuotasDisponibles"
                                            :key="cuota.cuotas"
                                            @click="cuotasSeleccionadas = cuota.cuotas"
                                            class="relative flex flex-col items-center py-2 px-1 rounded-xl border-2 transition-all"
                                            :class="cuotasSeleccionadas === cuota.cuotas
                                                ? 'bg-indigo-500 border-indigo-500 text-white shadow-md shadow-indigo-100'
                                                : 'bg-white border-slate-200 text-slate-600 hover:border-indigo-300'"
                                        >
                                            <span class="text-base font-black leading-none">{{ cuota.cuotas }}</span>
                                            <span class="text-[9px] font-bold mt-0.5 leading-none" :class="cuotasSeleccionadas === cuota.cuotas ? 'text-indigo-200' : 'text-slate-400'">
                                                {{ cuota.cuotas === 1 ? 'cuota' : 'cuotas' }}
                                            </span>
                                            <span class="text-[9px] font-bold mt-1 px-1.5 py-0.5 rounded-full leading-none"
                                                :class="cuota.porcentaje > 0
                                                    ? (cuotasSeleccionadas === cuota.cuotas ? 'bg-amber-400/40 text-amber-100' : 'bg-amber-50 text-amber-600')
                                                    : (cuotasSeleccionadas === cuota.cuotas ? 'bg-emerald-400/40 text-emerald-100' : 'bg-emerald-50 text-emerald-600')"
                                            >
                                                {{ cuota.porcentaje > 0 ? '+' + cuota.porcentaje + '%' : '0%' }}
                                            </span>
                                        </button>
                                    </div>
                                </div>

                                <div class="bg-white rounded-xl p-2.5 space-y-1.5 border border-slate-200">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Subtotal</span>
                                        <span class="text-xs font-bold text-slate-500 tabular-nums">${{ montoTarjeta.toFixed(2) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-[10px] font-black uppercase tracking-widest" :class="recargoPorcentaje > 0 ? 'text-amber-500' : 'text-emerald-500'">
                                            Recargo ({{ recargoPorcentaje }}%)
                                        </span>
                                        <span class="text-xs font-bold tabular-nums" :class="recargoPorcentaje > 0 ? 'text-amber-600' : 'text-emerald-600'">+${{ recargoMonto.toFixed(2) }}</span>
                                    </div>
                                    <div class="border-t border-slate-100 pt-1.5 flex items-end justify-between">
                                        <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Total a cobrar</span>
                                        <span class="text-lg font-black text-slate-900 tabular-nums">${{ totalConRecargo.toFixed(2) }}</span>
                                    </div>
                                </div>

                                <div v-if="cuotasSeleccionadas > 1" class="bg-indigo-50 rounded-xl p-2.5 text-center">
                                    <span class="text-[9px] font-black text-indigo-400 uppercase tracking-widest block mb-0.5">El cliente pagará</span>
                                    <span class="text-xl font-black text-indigo-700 tabular-nums">${{ montoPorCuota.toFixed(2) }}</span>
                                    <span class="text-[10px] font-bold text-indigo-400 block mt-0.5">en {{ cuotasSeleccionadas }} cuotas</span>
                                </div>
                            </div>

                            <!-- Recibido + Vuelto (efectivo único) -->
                            <div v-if="esUnicoEfectivo && totalVenta > 0">
                                <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1 block">Recibido</label>
                                <div class="relative">
                                    <span class="absolute left-2.5 top-1/2 -translate-y-1/2 font-bold text-slate-400 text-sm">$</span>
                                    <input
                                        v-model.number="montoRecibido"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="w-full pl-7 pr-3 py-2 border-2 border-slate-200 rounded-xl font-bold text-slate-800 focus:border-emerald-500 focus:ring-0 transition-colors text-lg [&::-webkit-inner-spin-button]:appearance-none"
                                        placeholder="0.00"
                                    >
                                </div>
                                <div v-if="sugerencias.length > 0 && montoRecibido === null" class="flex flex-wrap gap-1 mt-1.5">
                                    <button
                                        v-for="sug in sugerencias" :key="sug"
                                        @click="montoRecibido = sug"
                                        class="px-2.5 py-1 bg-slate-100 hover:bg-sky-100 hover:text-sky-700 border border-slate-200 hover:border-sky-300 rounded-lg text-[10px] font-bold text-slate-600 transition-all"
                                    >
                                        ${{ sug.toFixed(0) }}
                                    </button>
                                </div>
                                <div v-if="vuelto !== null" class="bg-emerald-50 border border-emerald-200 rounded-xl px-3 py-2 flex justify-between items-center mt-1.5">
                                    <span class="text-emerald-700 font-black text-xs uppercase tracking-widest">Vuelto</span>
                                    <span class="text-emerald-600 font-black text-2xl">${{ vuelto.toFixed(2) }}</span>
                                </div>
                            </div>

                            <!-- Barra de progreso del pago -->
                            <div v-if="totalDisplay > 0">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 h-2 bg-slate-200 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full transition-all duration-300"
                                            :class="esPagoCompleto ? 'bg-emerald-500' : 'bg-sky-500'"
                                            :style="{ width: Math.min(100, esUnicoEfectivo ? ((Number(montoRecibido) || 0) / totalDisplay * 100) : (totalAsignado / totalDisplay * 100)) + '%' }">
                                        </div>
                                    </div>
                                    <span class="text-[10px] font-bold shrink-0" :class="esPagoCompleto ? 'text-emerald-600' : 'text-slate-500'">
                                        <template v-if="esPagoCompleto">Completado</template>
                                        <template v-else-if="esUnicoEfectivo && montoRecibido !== null && montoRecibido !== ''">${{ Number(montoRecibido).toFixed(2) }} / ${{ totalDisplay.toFixed(2) }}</template>
                                        <template v-else>${{ totalAsignado.toFixed(2) }} / ${{ totalDisplay.toFixed(2) }}</template>
                                    </span>
                                </div>
                            </div>

                            <!-- Auto-completar restante -->
                            <div v-if="restante > 0.01 && pagos.length > 0 && pagos.length < 6 && !esUnicoEfectivo" class="flex justify-end">
                                <button @click="autoCompletarRestante" class="text-[10px] font-bold text-sky-600 hover:text-sky-800 uppercase tracking-wider">
                                    Asignar restante (${{ restante.toFixed(2) }})
                                </button>
                            </div>
                        </div>

                        <!-- TOTAL + COBRAR (fijo al fondo) -->
                        <div class="shrink-0 p-3 border-t border-slate-200 bg-slate-50/50 space-y-2">
                            <div class="flex items-end justify-between">
                                <span class="text-[10px] font-black uppercase tracking-widest text-slate-500">Total</span>
                                <span class="text-2xl font-black tracking-tight tabular-nums leading-none"
                                    :class="bloqueoPorSaldo ? 'text-rose-600' : (esUnicaTarjeta && recargoMonto > 0 ? 'text-violet-700' : 'text-slate-900')"
                                >${{ totalDisplay.toFixed(2) }}</span>
                            </div>

                            <button
                                @click="finalizarVenta"
                                :disabled="!puedeCobrar"
                                class="w-full flex items-center justify-center gap-2 bg-slate-900 hover:bg-sky-600 disabled:bg-slate-200 disabled:text-slate-400 text-white font-black py-3.5 rounded-2xl shadow-lg uppercase tracking-widest active:scale-[0.98] transition-all text-sm"
                            >
                                <template v-if="pagos.length === 0">Seleccioná un método de pago</template>
                                <template v-else-if="bloqueoPorSaldo">Saldo insuficiente</template>
                                <template v-else-if="esUnicoEfectivo && (montoRecibido === null || montoRecibido === '')">Ingresá el monto recibido</template>
                                <template v-else-if="esUnicoEfectivo && Number(montoRecibido) < totalVenta">Faltan ${{ (totalVenta - Number(montoRecibido)).toFixed(2) }}</template>
                                <template v-else-if="!esPagoCompleto">Asigná el total (${{ restante.toFixed(2) }})</template>
                                <template v-else-if="tieneTarjetaSeleccionada && !bancoSeleccionado">Seleccioná un banco</template>
                                <template v-else-if="esUnicaTarjeta">Cobrar ${{ totalConRecargo.toFixed(2) }}</template>
                                <template v-else>Cobrar ${{ totalVenta.toFixed(2) }}</template>
                            </button>

                            <div class="flex items-center justify-center gap-3 text-[10px] font-bold text-slate-400">
                                <span class="flex items-center gap-1"><kbd class="bg-white border border-slate-200 rounded px-1.5 py-0.5 font-black text-slate-500 shadow-sm">F9</kbd> Cobrar</span>
                                <span class="flex items-center gap-1"><kbd class="bg-white border border-slate-200 rounded px-1.5 py-0.5 font-black text-slate-500 shadow-sm">F1-F8</kbd> Métodos</span>
                                <span class="flex items-center gap-1"><kbd class="bg-white border border-slate-200 rounded px-1.5 py-0.5 font-black text-slate-500 shadow-sm">Esc</kbd> Limpiar</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lector de cámara -->
            <LectorCamara
                v-if="mostrarEscaner"
                @escaneado="manejarCodigoEscaneado"
                @cerrar="mostrarEscaner = false"
            />

            <!-- Confirmación de pago -->
            <ConfirmarPagoModal
                :show="confirmarPagoModal"
                :venta-id="confirmarVentaId"
                :display-info="confirmarDisplayInfo"
                @close="onPagoCancelado"
                @confirmed="onPagoConfirmado"
            />
        </div>
    </AuthenticatedLayout>
</template>
