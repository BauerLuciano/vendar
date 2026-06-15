<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import LectorCamara from '@/Components/LectorCamara.vue';
import ConfirmarPagoModal from '@/Components/ConfirmarPagoModal.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    turno: Object,
    productos: Array,
    clientes: Array,
    frecuentes: Array,
    paymentMethods: Array,
    metodosBase: Array,
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

const pagos = ref([{ metodo_pago: 'EFECTIVO', monto: null }]);

const montoRecibido = ref(null);

const productosBusqueda = ref([]);
const buscandoProductos = ref(false);
const productosIniciales = computed(() => props.productos || []);
const totalProductos = computed(() => props.totalProductos || props.productos?.length || 0);

const productosFiltrados = computed(() => {
    if (buscar.value.length < 2) {
        return productosIniciales.value;
    }
    if (productosBusqueda.value.length > 0) return productosBusqueda.value;
    return productosIniciales.value.filter(p =>
        p.nombre.toLowerCase().includes(buscar.value.toLowerCase()) ||
        (p.codigo_barras && p.codigo_barras.includes(buscar.value))
    );
});

let timeoutBusqueda = null;
function onBuscarInput() {
    if (timeoutBusqueda) clearTimeout(timeoutBusqueda);
    if (buscar.value.length < 2) {
        productosBusqueda.value = [];
        return;
    }
    timeoutBusqueda = setTimeout(() => {
        buscarProductosAjax(buscar.value);
    }, 300);
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
    return true;
});

function togglePago(metodo) {
    const idx = pagos.value.findIndex(p => p.metodo_pago === metodo);
    if (idx >= 0) {
        removerPago(idx);
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
    if (pagos.value.length <= 1) return;
    pagos.value.splice(idx, 1);
    if (pagos.value.length === 1 && pagos.value[0].monto === null) {
        pagos.value[0].monto = null;
    }
}

function autoCompletarRestante() {
    if (pagos.value.length === 0) return;
    const ultimo = pagos.value[pagos.value.length - 1];
    ultimo.monto = (Number(ultimo.monto) || 0) + Math.max(0, restante.value);
}

const procesarBusquedaEnter = async () => {
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

// 🔥 FUNCIÓN QUE RECIBE EL CÓDIGO DE LA CÁMARA
const flashFeedback = ref(null);
let flashTimeout = null;

const mostrarFlash = (tipo, mensaje) => {
    flashFeedback.value = { tipo, mensaje };
    if (flashTimeout) clearTimeout(flashTimeout);
    flashTimeout = setTimeout(() => { flashFeedback.value = null; }, 2000);
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

    mostrarFlash('success', `${producto.nombre} agregado`);
};

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
};

const decrementarCantidad = (index) => { 
    const isKg = carrito.value[index].unidad_medida === 'Kg';
    const resta = isKg ? 0.1 : 1;
    if (carrito.value[index].cantidad > resta) {
        carrito.value[index].cantidad -= resta; 
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

    const pagosData = pagos.value.map(p => ({
        metodo_pago: p.metodo_pago,
        monto: esUnicoEfectivo.value ? totalVenta.value : (Number(p.monto) || 0),
    }));

    router.post(route('ventas.store'), {
        turno_caja_id: props.turno.id, 
        consumidor_id: clienteSeleccionado.value,
        items: carrito.value,
        total: totalVenta.value,
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
                    window.open(route('ventas.imprimir', ventaId), '_blank', 'width=450,height=600');
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

const atajos = {
    F1: 'EFECTIVO',
    F2: 'DEBITO',
    F3: 'CREDITO',
    F4: 'TRANSFERENCIA',
    F5: 'MERCADO_PAGO',
    F8: 'CUENTA_CORRIENTE',
};

const handleKeydown = (e) => {
    const key = e.key;
    if (key.startsWith('F') && atajos[key]) {
        e.preventDefault();
        togglePago(atajos[key]);
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
    <Head title="Terminal POS - Kiosco" />

    <AuthenticatedLayout>
        <div class="py-6 px-4 sm:px-6 lg:px-8 bg-gradient-to-br from-slate-50 to-slate-100 min-h-screen" @click="mostrarDropdownClientes = false">
            
            <div class="mb-6 flex justify-between items-end">
                <div>
                    <div class="flex items-center gap-3 mb-2">
                        <span class="bg-indigo-100 text-indigo-800 text-[10px] font-black px-2 py-1 rounded-md uppercase tracking-widest border border-indigo-200 shadow-sm">
                            {{ turno.caja.nombre }}
                        </span>
                        <span class="text-xs font-bold text-slate-500">
                            Sucursal: {{ turno.caja.sucursal.nombre }}
                        </span>
                    </div>
                    <h1 class="text-2xl font-black text-slate-800 uppercase tracking-tight flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                        Punto de Venta
                    </h1>
                </div>
            </div>

            <div class="grid grid-cols-12 gap-6">
                <div class="col-span-12 lg:col-span-8 flex flex-col gap-6">
                    
                    <div class="bg-white rounded-2xl shadow-md border border-slate-200 focus-within:border-sky-500 focus-within:ring-4 focus-within:ring-sky-500/20 transition-all overflow-hidden">
                        <div class="relative flex items-center">
                            <span class="absolute left-4 text-slate-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                            </span>
                            <input 
                                ref="inputBusqueda"
                                v-model="buscar"
                                @input="onBuscarInput"
                                @keyup.enter="procesarBusquedaEnter"
                                type="text" 
                                placeholder="Escaneá código o buscá por nombre..."
                                class="w-full pl-14 pr-40 py-4 bg-transparent border-none focus:ring-0 text-lg font-bold text-slate-800 placeholder-slate-400"
                                autofocus
                            />
                            <div v-if="buscandoProductos" class="absolute right-36 top-1/2 -translate-y-1/2">
                                <svg class="animate-spin h-5 w-5 text-sky-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            </div>
                            <div class="absolute right-3 flex items-center gap-2">
                                <div class="hidden sm:block px-2 py-1 bg-slate-100 rounded text-[10px] font-bold text-slate-400 uppercase border border-slate-200">
                                    ENTER ↵
                                </div>
                                <button 
                                    @click="mostrarEscaner = true" 
                                    class="bg-sky-100 text-sky-600 p-2 rounded-xl hover:bg-sky-500 hover:text-white transition-all shadow-sm border border-sky-200 flex items-center gap-1 group"
                                    title="Escanear con Cámara"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="flashFeedback" class="fixed top-20 right-4 z-50 animate-in fade-in slide-in-from-right-2 duration-200">
                        <div class="px-4 py-2.5 rounded-xl shadow-lg border text-sm font-bold flex items-center gap-2"
                            :class="flashFeedback.tipo === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : 'bg-rose-50 border-rose-200 text-rose-700'">
                            <svg v-if="flashFeedback.tipo === 'success'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                            <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            {{ flashFeedback.mensaje }}
                        </div>
                    </div>

                            <div v-if="totalProductos > productosIniciales.length" class="text-[10px] font-bold text-amber-600 bg-amber-50 px-3 py-1.5 rounded-lg border border-amber-200 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                Mostrando los primeros {{ productosIniciales.length }} de {{ totalProductos }} productos. Usá la búsqueda para encontrar más.
                            </div>
                            <div v-if="frecuentes && frecuentes.length > 0 && buscar.length < 2" class="mb-4">
                                <div class="flex items-center gap-2 mb-3">
                                    <span class="text-sm font-black text-amber-700 uppercase tracking-widest">⭐ Más vendidos</span>
                                </div>
                                <div class="flex gap-3 overflow-x-auto pb-2 -mx-1 px-1 scrollbar-thin">
                                    <div v-for="p in frecuentes" :key="'freq-' + p.id"
                                        @click="clickEnProducto(p)"
                                        class="shrink-0 bg-gradient-to-br from-amber-50 to-orange-50 border border-amber-200 hover:border-amber-400 rounded-xl px-4 py-3 cursor-pointer hover:shadow-md transition-all min-w-[140px]"
                                    >
                                        <div class="text-xs font-bold text-amber-800 truncate max-w-[120px]">{{ p.nombre }}</div>
                                        <div class="text-sm font-black text-amber-900 mt-1">
                                            ${{ Number(p.precio_rebajado || p.precio_venta).toLocaleString() }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-if="productosFiltrados.length > 0" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        <div 
                            v-for="p in productosFiltrados" :key="p.id"
                            @click="clickEnProducto(p)"
                            class="bg-white p-4 rounded-2xl shadow-md border border-slate-200 hover:border-sky-500 hover:shadow-xl hover:-translate-y-1 transition-all cursor-pointer group relative overflow-hidden"
                        >
                            <div v-if="p.en_liquidacion" class="absolute top-0 left-0 px-2 py-1 bg-rose-500 text-white rounded-br-xl text-[10px] font-black uppercase tracking-widest shadow-md z-10 animate-pulse">
                                {{ p.porcentaje_descuento }}% OFF 📉
                            </div>

                            <div class="absolute top-0 right-0 px-2 py-1 rounded-bl-xl text-[10px] font-black uppercase tracking-widest" :class="p.stock_actual <= 0 ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700'">
                                Stock: {{ p.stock_actual }}
                            </div>

                            <div class="flex items-center gap-4 mt-2">
                                <div class="w-16 h-16 bg-slate-50 rounded-xl overflow-hidden flex items-center justify-center border border-slate-100 shrink-0">
                                    <img v-if="p.imagen" :src="'/storage/' + p.imagen" class="w-full h-full object-cover" />
                                    <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                                </div>
                                <div>
                                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none mb-1">{{ p.codigo_barras || 'SIN CÓDIGO' }}</p>
                                    <p class="font-bold text-slate-800 leading-tight group-hover:text-sky-600 transition-colors line-clamp-2">{{ p.nombre }}</p>
                                    
                                    <div class="mt-1 flex items-baseline gap-2">
                                        <p v-if="p.en_liquidacion" class="text-rose-600 font-black text-lg">${{ p.precio_rebajado }}</p>
                                        <p :class="p.en_liquidacion ? 'text-slate-400 line-through text-xs' : 'text-sky-600 font-black text-lg'">${{ p.precio_venta }}</p>
                                        <span v-if="p.unidad_medida === 'Kg'" class="text-xs text-slate-400">/kg</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-if="carrito.length > 0" class="flex justify-center">
                            <button @click="guardarCarrito" :disabled="guardandoCarrito"
                                class="flex items-center gap-1.5 px-3 py-1.5 bg-amber-50 border border-amber-200 hover:bg-amber-100 text-amber-700 font-bold text-[10px] rounded-lg uppercase tracking-wider transition-all disabled:opacity-50"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" /></svg>
                                {{ guardandoCarrito ? 'Guardando...' : carrito.length + ' ítem' + (carrito.length > 1 ? 's' : '') + ' — Guardar' }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-4">
                    <div class="bg-white rounded-3xl shadow-2xl shadow-slate-200/50 flex flex-col h-[calc(100vh-140px)] sticky top-6 border border-slate-200 overflow-hidden">
                        
                        <div class="p-5 border-b border-slate-200 bg-slate-50">
                            <div class="relative w-full" @click.stop>
                                <div 
                                    @click="mostrarDropdownClientes = !mostrarDropdownClientes"
                                    class="bg-white px-4 py-3 rounded-xl text-sm font-bold text-slate-700 cursor-pointer flex justify-between items-center border border-slate-200 hover:border-sky-400 transition-all shadow-sm"
                                >
                                    <span class="truncate flex items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-sky-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" /></svg>
                                        {{ clienteActivoObj ? clienteActivoObj.nombre + ' ' + clienteActivoObj.apellido : 'Consumidor Final' }}
                                    </span>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                </div>
                                
                                <div v-if="mostrarDropdownClientes" class="absolute right-0 top-full mt-2 w-80 bg-white border border-slate-200 shadow-xl rounded-2xl z-50 overflow-hidden">
                                    <div class="p-4 border-b border-slate-100 bg-slate-50 relative">
                                        <span class="absolute left-6 top-1/2 -translate-y-1/2 text-slate-400">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                                        </span>
                                        <input 
                                            v-model="busquedaCliente" 
                                            @input="onBuscarClienteInput"
                                            type="text" 
                                            placeholder="Buscá por nombre o documento..." 
                                            class="w-full pl-10 text-sm font-medium border-slate-200 rounded-xl focus:ring-sky-500 focus:border-sky-500 py-2.5"
                                            autofocus
                                        >
                                        <div v-if="buscandoClientes" class="absolute right-4 top-1/2 -translate-y-1/2">
                                            <svg class="animate-spin h-4 w-4 text-sky-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                        </div>
                                    </div>
                                    <ul class="max-h-64 overflow-y-auto">
                                        <li 
                                            @click="seleccionarCliente(null)" 
                                            class="px-4 py-3 text-sm font-bold text-slate-600 hover:bg-sky-50 hover:text-sky-700 cursor-pointer border-b border-slate-50 flex items-center gap-2"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                                            Consumidor Final
                                        </li>
                                        <li 
                                            v-for="c in clientesFiltradosSelect" :key="c.id"
                                            @click="seleccionarCliente(c)"
                                            class="px-4 py-3 text-sm font-medium text-slate-700 hover:bg-sky-50 hover:text-sky-700 cursor-pointer border-b border-slate-50"
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

                        <div class="border-b border-slate-200">
                            <button @click="mostrarMovimientos = !mostrarMovimientos; if (mostrarMovimientos) fetchPendientes()"
                                class="w-full flex items-center justify-between px-5 py-2.5 text-sm font-bold text-slate-600 hover:bg-slate-100 transition-colors"
                            >
                                <div class="flex items-center gap-2">
                                    <span class="text-emerald-600 font-black text-xs">${{ resumenCaja.saldo.toLocaleString() }}</span>
                                    <span v-if="ventasPendientes.length > 0" class="bg-amber-100 text-amber-700 text-[10px] font-black px-1.5 py-0.5 rounded-full">{{ ventasPendientes.length }} pend.</span>
                                    <span v-if="!cargandoMovimientos" class="text-[10px] text-slate-400 font-mono">{{ movimientosTurno.length }} mov.</span>
                                    <svg v-if="cargandoMovimientos" class="animate-spin h-3 w-3 text-sky-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                </div>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform text-slate-400" :class="mostrarMovimientos ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                            </button>
                            <div v-if="mostrarMovimientos" class="border-t border-slate-100">
                                <div class="flex border-b border-slate-100">
                                    <button @click="tabCajaPend = 'caja'" class="flex-1 py-2 text-center text-[10px] font-black uppercase tracking-wider transition-colors" :class="tabCajaPend === 'caja' ? 'text-sky-700 border-b-2 border-sky-500 bg-sky-50/50' : 'text-slate-400 hover:text-slate-600'">
                                        Movimientos
                                    </button>
                                    <button @click="tabCajaPend = 'pendientes'" class="flex-1 py-2 text-center text-[10px] font-black uppercase tracking-wider transition-colors" :class="tabCajaPend === 'pendientes' ? 'text-sky-700 border-b-2 border-sky-500 bg-sky-50/50' : 'text-slate-400 hover:text-slate-600'">
                                        Pendientes
                                    </button>
                                </div>
                                <div v-if="tabCajaPend === 'caja'">
                                    <div v-if="movimientosTurno.length === 0" class="px-5 py-4 text-center text-xs text-slate-400">
                                        No hay movimientos en este turno
                                    </div>
                                    <div v-else class="max-h-40 overflow-y-auto">
                                        <div v-for="m in movimientosTurno" :key="m.id"
                                            class="flex items-center justify-between px-4 py-1.5 text-xs hover:bg-slate-50 border-b border-slate-50 last:border-0"
                                        >
                                            <div class="flex items-center gap-2 min-w-0">
                                                <span class="shrink-0 w-1.5 h-1.5 rounded-full" :class="m.tipo === 'INGRESO' ? 'bg-emerald-400' : 'bg-red-400'"></span>
                                                <span class="truncate font-medium text-slate-700">{{ m.concepto }}</span>
                                                <span class="shrink-0 text-[10px] text-slate-400 font-mono">{{ m.created_at }}</span>
                                            </div>
                                            <div class="flex items-center gap-2 shrink-0">
                                                <span class="text-[10px] text-slate-400 hidden sm:inline">{{ m.metodo_pago_display }}</span>
                                                <span class="font-black font-mono tabular-nums" :class="m.tipo === 'INGRESO' ? 'text-emerald-600' : 'text-red-500'">
                                                    {{ m.tipo === 'INGRESO' ? '+' : '-' }}${{ m.monto.toLocaleString() }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between px-4 py-1.5 bg-slate-50 text-[10px] font-bold border-t border-slate-200">
                                        <span class="text-slate-500">I: <span class="text-emerald-600">${{ resumenCaja.ingresos.toLocaleString() }}</span></span>
                                        <span class="text-slate-500">E: <span class="text-red-500">${{ resumenCaja.egresos.toLocaleString() }}</span></span>
                                        <span class="text-slate-500">S: <span class="text-emerald-600">${{ resumenCaja.saldo.toLocaleString() }}</span></span>
                                    </div>
                                </div>
                                <div v-if="tabCajaPend === 'pendientes'">
                                    <div class="flex border-b border-slate-100">
                                        <button @click="tabPendientes = 'carritos'" class="flex-1 py-1.5 text-center text-[10px] font-black uppercase tracking-wider transition-colors" :class="tabPendientes === 'carritos' ? 'text-sky-700 border-b-2 border-sky-500 bg-sky-50/50' : 'text-slate-400 hover:text-slate-600'">
                                            Carritos ({{ ventasPendientes.length }})
                                        </button>
                                        <button @click="tabPendientes = 'pagos'; fetchVentasPendientesPago()" class="flex-1 py-1.5 text-center text-[10px] font-black uppercase tracking-wider transition-colors" :class="tabPendientes === 'pagos' ? 'text-sky-700 border-b-2 border-sky-500 bg-sky-50/50' : 'text-slate-400 hover:text-slate-600'">
                                            Pagos ({{ ventasPendientesPago.length }})
                                        </button>
                                    </div>

                                    <div v-if="tabPendientes === 'carritos'">
                                        <div v-if="ventasPendientes.length === 0" class="px-5 py-4 text-center text-xs text-slate-400">
                                            No hay carritos guardados
                                        </div>
                                        <div v-else class="max-h-40 overflow-y-auto">
                                            <div v-for="p in ventasPendientes" :key="p.id"
                                                class="flex items-center justify-between px-4 py-1.5 text-xs hover:bg-slate-50 border-b border-slate-50 last:border-0"
                                            >
                                                <div>
                                                    <span class="font-bold text-slate-700">{{ p.items_count }} prod.</span>
                                                    <span class="text-slate-400 ml-2">${{ Number(p.total).toLocaleString() }}</span>
                                                    <span class="text-slate-400 ml-2 font-mono">{{ p.created_at }}</span>
                                                </div>
                                                <div class="flex gap-1">
                                                    <button @click="restaurarPendiente(p)" :disabled="restaurandoCarrito"
                                                        class="px-2 py-0.5 bg-sky-50 hover:bg-sky-100 text-sky-700 rounded-lg font-bold text-[10px] uppercase tracking-wider transition-colors"
                                                    >
                                                        {{ restaurandoCarrito ? '...' : 'Abrir' }}
                                                    </button>
                                                    <button @click="eliminarPendiente(p)"
                                                        class="px-2 py-0.5 text-slate-400 hover:text-rose-500 rounded-lg font-bold text-[10px] uppercase tracking-wider transition-colors"
                                                    >
                                                        ✕
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="tabPendientes === 'pagos'">
                                        <div v-if="ventasPendientesPago.length === 0" class="px-5 py-4 text-center text-xs text-slate-400">
                                            No hay pagos pendientes
                                        </div>
                                        <div v-else class="max-h-40 overflow-y-auto">
                                            <div v-for="p in ventasPendientesPago" :key="p.id"
                                                class="flex items-center justify-between px-4 py-1.5 text-xs hover:bg-slate-50 border-b border-slate-50 last:border-0"
                                            >
                                                <div>
                                                    <span class="font-bold text-slate-700">{{ p.items_count }} prod.</span>
                                                    <span class="text-slate-400 ml-2">${{ Number(p.total).toLocaleString() }}</span>
                                                    <span class="text-slate-400 ml-2 font-mono">{{ p.created_at }}</span>
                                                    <span v-if="p.consumidor" class="text-slate-400 ml-2">· {{ p.consumidor }}</span>
                                                </div>
                                                <button @click="confirmarVentaId = p.id; confirmarDisplayInfo = p.pagos?.map(pg => ({ metodo_pago: pg.metodo_pago, monto: pg.monto, label: pg.metodo_pago })) || []; confirmarPagoModal = true"
                                                    class="px-2 py-0.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 rounded-lg font-bold text-[10px] uppercase tracking-wider transition-colors"
                                                >
                                                    Cobrar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="px-5 pt-4 pb-2 bg-slate-50 flex flex-col gap-3">
                            <div class="flex gap-1">
                                <button
                                    v-for="m in METODOS_DISPONIBLES" :key="m.value"
                                    @click="togglePago(m.value)"
                                    :title="m.label"
                                    class="flex-1 flex items-center justify-center px-1 py-2 rounded-xl border-2 transition-all shadow-sm"
                                    :class="pagos.some(p => p.metodo_pago === m.value)
                                        ? 'bg-indigo-50 border-indigo-500 text-indigo-700 shadow-indigo-100'
                                        : 'bg-white border-slate-200 text-slate-400 hover:bg-slate-50 hover:text-slate-600'"
                                >
                                    <svg v-if="m.value === 'EFECTIVO'" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                                    <svg v-else-if="m.value === 'DEBITO' || m.value === 'CREDITO'" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                                    <svg v-else-if="m.value === 'CUENTA_CORRIENTE'" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                                    <svg v-else-if="m.value === 'TRANSFERENCIA'" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
                                    <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
                                </button>
                            </div>

                            <div v-for="(pago, idx) in pagos" :key="idx" class="flex items-center gap-2">
                                <span class="text-xs font-black uppercase tracking-wider text-slate-500 min-w-[70px]">{{ METODOS_DISPONIBLES.find(m => m.value === pago.metodo_pago)?.label || pago.metodo_pago }}</span>
                                <template v-if="esUnicoEfectivo">
                                    <div class="flex-1 text-right text-sm font-bold text-slate-400">${{ totalVenta.toFixed(2) }}</div>
                                </template>
                                <template v-else>
                                    <div class="relative flex-1">
                                        <span class="absolute left-2 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">$</span>
                                        <input
                                            v-model.number="pago.monto"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            placeholder="0.00"
                                            class="w-full pl-6 pr-2 py-1.5 border-2 border-slate-200 rounded-lg text-sm font-bold text-slate-800 focus:border-indigo-500 focus:ring-0 transition-colors"
                                            @focus="$event.target.select()"
                                        >
                                    </div>
                                    <button
                                        v-if="pagos.length > 1"
                                        @click="removerPago(idx)"
                                        class="p-1.5 text-slate-300 hover:text-rose-500 hover:bg-rose-50 rounded-lg transition-all"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                </template>
                            </div>

                            <div v-if="esUnicoEfectivo && totalVenta > 0" class="flex items-center gap-2">
                                <div class="flex-1 h-2 bg-slate-200 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full transition-all duration-300"
                                        :class="esPagoCompleto ? 'bg-emerald-500' : 'bg-indigo-500'"
                                        :style="{ width: Math.min(100, ((Number(montoRecibido) || 0) / totalVenta * 100)) + '%' }">
                                    </div>
                                </div>
                                <span class="text-xs font-bold" :class="esPagoCompleto ? 'text-emerald-600' : 'text-slate-500'">
                                    <template v-if="montoRecibido !== null && montoRecibido !== ''">${{ Number(montoRecibido).toFixed(2) }} / ${{ totalVenta.toFixed(2) }}</template>
                                    <template v-else>Falta monto recibido</template>
                                </span>
                            </div>

                            <div v-else-if="totalVenta > 0" class="flex items-center gap-2">
                                <div class="flex-1 h-2 bg-slate-200 rounded-full overflow-hidden">
                                    <div class="h-full bg-indigo-500 rounded-full transition-all duration-300"
                                        :class="esPagoCompleto ? 'bg-emerald-500' : ''"
                                        :style="{ width: Math.min(100, (totalAsignado / totalVenta * 100)) + '%' }">
                                    </div>
                                </div>
                                <span class="text-xs font-bold" :class="esPagoCompleto ? 'text-emerald-600' : 'text-slate-500'">
                                    <template v-if="esPagoCompleto">Completado</template>
                                    <template v-else>${{ totalAsignado.toFixed(2) }} / ${{ totalVenta.toFixed(2) }}</template>
                                </span>
                            </div>

                            <div v-if="restante > 0.01 && pagos.length > 0 && pagos.length < 6" class="flex justify-end">
                                <button @click="autoCompletarRestante" class="text-[10px] font-bold text-indigo-600 hover:text-indigo-800 uppercase tracking-wider">
                                    Asignar restante (${{ restante.toFixed(2) }}) al último
                                </button>
                            </div>

                            <div v-if="tieneCuentaCorriente && clienteActivoObj" class="p-3 rounded-xl border flex items-center justify-between transition-colors" :class="bloqueoPorSaldo ? 'bg-rose-50 border-rose-200 text-rose-700' : 'bg-emerald-50 border-emerald-200 text-emerald-700'">
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-widest opacity-70">Crédito Disponible</p>
                                    <p class="font-bold text-sm">${{ disponibleCliente.toFixed(2) }}</p>
                                </div>
                            </div>
                            <div v-else-if="tieneCuentaCorriente && !clienteActivoObj" class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-700 flex items-center gap-2">
                                <span class="text-xs font-bold">Tenés que elegir un cliente para fiarle.</span>
                            </div>
                        </div>

                        <div class="flex-1 overflow-y-auto p-4 space-y-3 bg-slate-50 z-10">
                            <div v-if="carrito.length === 0" class="h-full flex flex-col items-center justify-center text-slate-300 opacity-70">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-20 w-20 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                                <p class="font-bold text-lg">Carrito vacío</p>
                            </div>

                            <div v-for="(item, index) in carrito" :key="item.id" class="flex flex-col p-3 bg-white border border-slate-200 rounded-2xl shadow-sm relative group hover:border-sky-200 transition-all">
                                <div class="flex justify-between items-start mb-2">
                                    <div class="pr-6">
                                        <span class="font-bold text-slate-800 text-sm block">{{ item.nombre }}</span>
                                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                            ${{ item.precio_venta }} 
                                            <span v-if="item.en_liquidacion" class="text-rose-500 font-bold ml-1">(-{{ item.porcentaje_descuento }}% OFF)</span>
                                            · Stock: {{ item.stock_actual }}
                                        </span>
                                    </div>
                                    <button @click="eliminarDelCarrito(index)" class="absolute top-3 right-3 text-slate-300 hover:text-rose-500 transition-colors">✕</button>
                                </div>
                                
                                <div class="flex justify-between items-end mt-1">
                                    <div class="flex items-center bg-slate-50 rounded-xl p-1 border border-slate-200">
                                        <button @click="decrementarCantidad(index)" type="button" class="w-8 h-8 flex items-center justify-center bg-white rounded-lg shadow-sm">−</button>
                                        <div class="flex flex-col items-center justify-center px-1">
                                            <input 
                                                type="number"
                                                v-model.number="item.cantidad" 
                                                min="0"
                                                @blur="validarCantidad(index)"
                                                @keydown="prevenirNegativo($event)"
                                                class="w-16 text-center bg-transparent border-none text-sm font-black p-0 focus:ring-0 text-sky-700 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none [&]:[appearance:textfield]"
                                            >
                                        </div>
                                        <button @click="incrementarCantidad(index)" type="button" class="w-8 h-8 flex items-center justify-center bg-white rounded-lg shadow-sm">+</button>
                                    </div>
                                    
                                    <div class="text-right">
                                        <span class="font-black text-slate-800 text-lg">${{ (item.cantidad * item.precio_venta).toFixed(2) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="p-5 bg-white border-t border-slate-200 z-20">
                            <div class="flex justify-between items-baseline mb-4">
                                <span class="text-slate-500 font-black uppercase tracking-widest text-xs">Total</span>
                                <span class="text-3xl font-black text-slate-900 tracking-tight" :class="{'text-rose-600': bloqueoPorSaldo}">${{ totalVenta.toFixed(2) }}</span>
                            </div>

                            <div v-if="esUnicoEfectivo && totalVenta > 0" class="mb-4 space-y-3">
                                <div>
                                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Recibido ($)</label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-2.5 font-bold text-slate-400">$</span>
                                        <input
                                            v-model.number="montoRecibido"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            class="w-full pl-8 pr-4 py-2.5 border-2 border-slate-200 rounded-xl font-bold text-slate-800 focus:border-emerald-500 focus:ring-0 transition-colors text-lg"
                                            placeholder="0.00"
                                        >
                                    </div>
                                </div>

                                <div v-if="vuelto !== null" class="bg-emerald-50 border-2 border-emerald-200 rounded-xl p-3 flex justify-between items-center">
                                    <span class="text-emerald-700 font-black text-sm uppercase tracking-widest">Vuelto</span>
                                    <span class="text-emerald-600 font-black text-2xl">${{ vuelto.toFixed(2) }}</span>
                                </div>

                                <div v-if="sugerencias.length > 0 && montoRecibido === null" class="flex flex-wrap gap-1.5">
                                    <button
                                        v-for="sug in sugerencias" :key="sug"
                                        @click="montoRecibido = sug"
                                        class="px-3 py-1.5 bg-slate-100 hover:bg-sky-100 hover:text-sky-700 border border-slate-200 hover:border-sky-300 rounded-lg text-xs font-bold text-slate-600 transition-all"
                                    >
                                        ${{ sug.toFixed(2) }}
                                    </button>
                                </div>
                            </div>

                            <button 
                                @click="finalizarVenta"
                                :disabled="!puedeCobrar"
                                class="w-full bg-slate-900 hover:bg-sky-600 disabled:bg-slate-200 disabled:text-slate-400 text-white font-black py-3.5 rounded-xl shadow-lg uppercase tracking-widest active:scale-95 transition-all text-sm"
                            >
                                <template v-if="bloqueoPorSaldo">SALDO INSUFICIENTE</template>
                                <template v-else-if="esUnicoEfectivo && (montoRecibido === null || montoRecibido === '')">Ingresá el monto recibido</template>
                                <template v-else-if="esUnicoEfectivo && Number(montoRecibido) < totalVenta">Faltan ${{ (totalVenta - Number(montoRecibido)).toFixed(2) }}</template>
                                <template v-else-if="!esPagoCompleto">Asigná el total (${{ restante.toFixed(2) }})</template>
                                <template v-else>Cobrar ${{ totalVenta.toFixed(2) }}</template>
                            </button>
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