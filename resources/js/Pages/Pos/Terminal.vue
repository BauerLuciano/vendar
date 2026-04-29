<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, computed, nextTick } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    turno: Object,
    productos: Array,
    clientes: Array
});

const page = usePage();

const permitirStockNegativo = computed(() => {
    const val = page.props.empresa?.permitir_stock_negativo;
    return val === '1' || val === 1 || val === true;
});

const buscar = ref('');
const carrito = ref([]);
const metodoPago = ref('Efectivo'); 

const clienteSeleccionado = ref(null); 
const busquedaCliente = ref('');
const mostrarDropdownClientes = ref(false);
const inputBusqueda = ref(null);

const clientesFiltradosSelect = computed(() => {
    if (!busquedaCliente.value || busquedaCliente.value.length < 2) return [];
    return props.clientes.filter(c => 
        c.nombre.toLowerCase().includes(busquedaCliente.value.toLowerCase()) ||
        (c.documento && c.documento.includes(busquedaCliente.value))
    );
});

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

const bloqueoPorSaldo = computed(() => {
    if (metodoPago.value === 'Cuenta Corriente' && clienteActivoObj.value) {
        return totalVenta.value > disponibleCliente.value;
    }
    return false;
});

const productosFiltrados = computed(() => {
    if (buscar.value.length < 2) return [];
    return props.productos.filter(p => 
        p.nombre.toLowerCase().includes(buscar.value.toLowerCase()) || 
        (p.codigo_barras && p.codigo_barras.includes(buscar.value))
    );
});

const procesarBusquedaEnter = () => {
    const query = buscar.value.trim();
    if (!query) return;

    if (query.length === 13 && query.startsWith('20')) {
        const pluBalanza = parseInt(query.substring(2, 6), 10).toString();
        const pesoGramos = parseInt(query.substring(7, 12), 10);
        const pesoKilos = pesoGramos / 1000;

        const productoBalanza = props.productos.find(p => p.codigo_barras === pluBalanza);
        
        if (productoBalanza) {
            agregarItemAlCarrito(productoBalanza, pesoKilos);
            buscar.value = '';
            return;
        }
    }

    const exactMatch = props.productos.find(p => p.codigo_barras === query);
    if (exactMatch) {
        clickEnProducto(exactMatch);
        return;
    }
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
        // 🔥 MAGIA: Si el producto está en liquidación, metemos al carrito el precio con descuento
        const precioCobrar = producto.en_liquidacion ? producto.precio_rebajado : producto.precio_venta;
        
        carrito.value.push({ 
            ...producto, 
            cantidad: cantidadAgregada,
            precio_original: producto.precio_venta, 
            precio_venta: precioCobrar // Lo sobreescribimos para que el backend lo cobre bien
        });
    }
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
    if (carrito.value.length === 0) return;
    
    if (metodoPago.value === 'Cuenta Corriente' && !clienteSeleccionado.value) {
        Swal.fire('Falta Cliente', 'Tenés que seleccionar a quién le vas a fiar.', 'warning');
        return;
    }

    Swal.fire({
        title: 'Procesando cobro...',
        text: 'Registrando salida de stock...',
        didOpen: () => { Swal.showLoading() },
        allowOutsideClick: false
    });

    router.post(route('ventas.store'), {
        turno_caja_id: props.turno.id, 
        consumidor_id: clienteSeleccionado.value,
        items: carrito.value,
        total: totalVenta.value,
        metodo_pago: metodoPago.value
    }, {
        onSuccess: (page) => {
            const ventaId = page.props.flash.venta_id;

            if (ventaId) {
                window.open(route('ventas.imprimir', ventaId), '_blank', 'width=450,height=600');
            }

            carrito.value = [];
            clienteSeleccionado.value = null;
            buscar.value = '';
            
            Swal.fire({
                icon: 'success',
                title: '¡Venta Registrada!',
                text: 'El cobro se procesó correctamente.',
                timer: 2000,
                showConfirmButton: false
            });

            nextTick(() => { if (inputBusqueda.value) inputBusqueda.value.focus(); });
        },
        onError: (errors) => {
            Swal.fire({
                icon: 'error',
                title: 'Error al cobrar',
                text: errors.error || 'Verificá que haya stock suficiente y reintentá.',
                confirmButtonColor: '#ef4444'
            });
        }
    });
};
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
                                @keyup.enter="procesarBusquedaEnter"
                                type="text" 
                                placeholder="Escaneá código o buscá por nombre..."
                                class="w-full pl-14 pr-4 py-4 bg-transparent border-none focus:ring-0 text-lg font-bold text-slate-800 placeholder-slate-400"
                                autofocus
                            />
                            <div class="absolute right-4 px-2 py-1 bg-slate-100 rounded text-[10px] font-bold text-slate-400 uppercase border border-slate-200">
                                ENTER ↵
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
                                            type="text" 
                                            placeholder="Buscá por nombre o documento..." 
                                            class="w-full pl-10 text-sm font-medium border-slate-200 rounded-xl focus:ring-sky-500 focus:border-sky-500 py-2.5"
                                            autofocus
                                        >
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

                        <div class="px-5 pt-4 pb-2 bg-slate-50 flex flex-col gap-3">
                            <div class="flex gap-2">
                                <label class="flex-1 cursor-pointer">
                                    <input type="radio" v-model="metodoPago" value="Efectivo" class="peer sr-only">
                                    <div class="text-center px-2 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-wider border-2 peer-checked:border-emerald-500 peer-checked:bg-emerald-50 peer-checked:text-emerald-700 text-slate-400 bg-white hover:bg-slate-50 transition-all flex flex-col items-center gap-1 shadow-sm">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                                        Efectivo
                                    </div>
                                </label>
                                <label class="flex-1 cursor-pointer">
                                    <input type="radio" v-model="metodoPago" value="Débito" class="peer sr-only">
                                    <div class="text-center px-2 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-wider border-2 peer-checked:border-sky-500 peer-checked:bg-sky-50 peer-checked:text-sky-700 text-slate-400 bg-white hover:bg-slate-50 transition-all flex flex-col items-center gap-1 shadow-sm">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                                        Digital
                                    </div>
                                </label>
                                <label class="flex-1 cursor-pointer">
                                    <input type="radio" v-model="metodoPago" value="Cuenta Corriente" class="peer sr-only">
                                    <div class="text-center px-2 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-wider border-2 peer-checked:border-amber-500 peer-checked:bg-amber-50 peer-checked:text-amber-700 text-slate-400 bg-white hover:bg-slate-50 transition-all flex flex-col items-center gap-1 shadow-sm">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                                        Fiado
                                    </div>
                                </label>
                            </div>

                            <div v-if="metodoPago === 'Cuenta Corriente' && clienteActivoObj" class="p-3 rounded-xl border flex items-center justify-between transition-colors" :class="bloqueoPorSaldo ? 'bg-rose-50 border-rose-200 text-rose-700' : 'bg-emerald-50 border-emerald-200 text-emerald-700'">
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-widest opacity-70">Crédito Disponible</p>
                                    <p class="font-bold text-sm">${{ disponibleCliente.toFixed(2) }}</p>
                                </div>
                            </div>
                            <div v-else-if="metodoPago === 'Cuenta Corriente' && !clienteActivoObj" class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-700 flex items-center gap-2">
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
                                                type="number" step="0.001"
                                                v-model.number="item.cantidad" 
                                                @blur="validarCantidad(index)"
                                                class="w-16 text-center bg-transparent border-none text-sm font-black p-0 focus:ring-0 text-sky-700"
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

                            <button 
                                @click="finalizarVenta"
                                :disabled="carrito.length === 0 || bloqueoPorSaldo || (metodoPago === 'Cuenta Corriente' && !clienteActivoObj)"
                                class="w-full bg-slate-900 hover:bg-sky-600 disabled:bg-slate-200 disabled:text-slate-400 text-white font-black py-3.5 rounded-xl shadow-lg uppercase tracking-widest active:scale-95 transition-all text-sm"
                            >
                                {{ bloqueoPorSaldo ? 'SALDO INSUFICIENTE' : 'Cobrar' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>