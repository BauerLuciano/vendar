<script setup>
import { ref, onMounted, watch, computed } from 'vue'; 
import { Head, Link, usePage, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
    comercio: Object,
    canLogin: Boolean,
    canRegister: Boolean,
    sucursalesBackend: Array,
    categorias: Array,
    tienda_slug: String,
});

const page = usePage();
const estaLogueado = computed(() => !!page.props.auth.user);

const sucursalElegida = ref('');
const categoriaSeleccionada = ref('todas');
const busqueda = ref('');

const sucursalObjeto = computed(() => {
    return props.sucursalesBackend?.find(s => s.id === sucursalElegida.value) || null;
});

const productos = ref([]);
const cargando = ref(false);
const localizando = ref(false);
const distanciaClienteKm = ref(0);

const carrito = ref([]);
const mostrarCarrito = ref(false);

const formPedido = useForm({
    tipo_entrega: 'local', 
    direccion_entrega: '',
    piso_depto: '', // 🔥 NUEVO CAMPO
    telefono_contacto: page.props.auth.user?.telefono || '',
    metodo_pago: '',
    notas: ''
});

// ── FILTRADO REACTIVO ──────────────────────────────────────────────────────────
const productosFiltrados = computed(() => {
    const items = Array.isArray(productos.value) ? productos.value : [];
    return items.filter(p => {
        const coincideCat = categoriaSeleccionada.value === 'todas' || p.categoria_id == categoriaSeleccionada.value;
        const coincideBusqueda = p.nombre.toLowerCase().includes(busqueda.value.toLowerCase());
        return coincideCat && coincideBusqueda;
    });
});

// ── UTILS ──────────────────────────────────────────────────────────────────────
const parsearPrecio = (valor) => {
    if (!valor) return 0;
    if (typeof valor === 'number') return valor;
    
    let str = String(valor);
    if (str.includes(',')) {
        str = str.replace(/\./g, '').replace(',', '.');
    }
    return parseFloat(str) || 0;
};

const formatearDinero = (monto) => {
    return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(monto);
};

// ── LÓGICA DE ENVÍOS ───────────────────────────────────────────────────────────
const costoDeliveryExtra = computed(() => {
    if (formPedido.tipo_entrega === 'delivery' && props.comercio) {
        const precioBase = parsearPrecio(props.comercio.envio_precio_base);
        const precioPorKm = parsearPrecio(props.comercio.envio_precio_km);
        
        if (distanciaClienteKm.value > 0) {
            return precioBase + (distanciaClienteKm.value * precioPorKm);
        }
        return precioBase;
    }
    return 0;
});

const fueraDeRango = computed(() => {
    if (formPedido.tipo_entrega === 'delivery' && props.comercio && props.comercio.envio_radio_km) {
        return distanciaClienteKm.value > parsearPrecio(props.comercio.envio_radio_km);
    }
    return false;
});

// ── CARRITO ────────────────────────────────────────────────────────────────────
const validarCantidadInput = (index, event) => {
    let valor = parseInt(event.target.value);
    carrito.value[index].cantidad = (isNaN(valor) || valor < 1) ? 1 : valor;
    guardarCarritoMemoria();
};

const cargarCarritoMemoria = () => {
    if (!props.comercio) return;
    const guardado = localStorage.getItem(`vendar_cart_${props.comercio.id}`);
    if (guardado) {
        try { 
            const items = JSON.parse(guardado);
            carrito.value = items.map(item => ({ ...item, precio: parsearPrecio(item.precio) }));
        } catch(e) {}
    }
};

const guardarCarritoMemoria = () => {
    if (!props.comercio) return;
    localStorage.setItem(`vendar_cart_${props.comercio.id}`, JSON.stringify(carrito.value));
};

const totalItems       = computed(() => carrito.value.reduce((acc, i) => acc + i.cantidad, 0));
const totalProductos   = computed(() => carrito.value.reduce((acc, i) => acc + (i.precio * i.cantidad), 0));
const totalFinalCheckout = computed(() => totalProductos.value + costoDeliveryExtra.value);

const agregarAlCarrito = (producto) => {
    if (!estaLogueado.value) {
        Swal.fire({
            title: '¡Iniciá Sesión!',
            text: 'Para pedir online necesitamos saber quién sos.',
            icon: 'info',
            background: '#0f1929',
            color: '#fff',
            showCancelButton: true,
            confirmButtonText: 'Ingresar',
            cancelButtonText: 'Seguir mirando',
            confirmButtonColor: '#00adef',
            cancelButtonColor: '#334155'
        }).then((res) => { if (res.isConfirmed) router.get(route('login')); });
        return;
    }
    const precioLimpio = parsearPrecio(producto.precio);
    const existe = carrito.value.find(item => item.id === producto.id);
    if (existe) {
        existe.cantidad++;
    } else {
        carrito.value.push({ id: producto.id, nombre: producto.nombre, precio: precioLimpio, imagen_url: producto.imagen_url, cantidad: 1 });
    }
    guardarCarritoMemoria();

    const Toast = Swal.mixin({ toast: true, position: 'bottom-end', showConfirmButton: false, timer: 1500 });
    Toast.fire({ icon: 'success', title: `Agregado: ${producto.nombre}`, background: '#0f1929', color: '#fff' });
};

const cambiarCantidad = (index, delta) => {
    carrito.value[index].cantidad += delta;
    if (carrito.value[index].cantidad <= 0) {
        carrito.value.splice(index, 1);
        if (carrito.value.length === 0) mostrarCarrito.value = false;
    }
    guardarCarritoMemoria();
};

const confirmarPedido = async () => {
    if (carrito.value.length === 0) return;
    
    if (!sucursalElegida.value) {
        Swal.fire({ icon: 'warning', title: 'Falta la Sucursal', text: 'Elegí una sucursal antes de confirmar.', background: '#0f1929', color: '#fff' });
        return;
    }
    
    if (formPedido.tipo_entrega === 'delivery' && (!formPedido.telefono_contacto || formPedido.telefono_contacto.trim() === '')) {
        Swal.fire({ 
            icon: 'warning', 
            title: 'Falta tu teléfono', 
            text: 'Necesitamos un número para coordinar la entrega.',
            background: '#0f1929', 
            color: '#fff' 
        });
        return;
    }
    
    // 1. Validaciones básicas
    if (formPedido.tipo_entrega === 'delivery') {
        if (!formPedido.direccion_entrega.trim()) {
            Swal.fire({ icon: 'warning', title: 'Falta Dirección', background: '#0f1929', color: '#fff' });
            return;
        }
        if (fueraDeRango.value) {
            Swal.fire({ icon: 'error', title: 'Fuera de Zona', text: `Estás muy lejos del local. Elegí "Retiro en Local".`, background: '#0f1929', color: '#fff' });
            return;
        }
    }

    if (!formPedido.metodo_pago) {
        Swal.fire({ icon: 'warning', title: 'Elegí cómo pagar', background: '#0f1929', color: '#fff' });
        return;
    }

    // 2. Armamos el paquete de datos
    const payload = {
        comercio_id: props.comercio.id,
        sucursal_id: sucursalElegida.value,
        items: carrito.value,
        total_productos: totalProductos.value,
        total_final: totalFinalCheckout.value,
        costo_envio: costoDeliveryExtra.value,
        tipo_entrega: formPedido.tipo_entrega,
        metodo_pago: formPedido.metodo_pago,
        direccion_entrega: formPedido.direccion_entrega,
        piso_depto: formPedido.piso_depto,
        telefono_contacto: formPedido.telefono_contacto,
        notas: formPedido.notas,
        distancia_km: distanciaClienteKm.value
    };
    
    try {
        // 3. Mostramos un cargando (SweetAlert con spinner)
        Swal.fire({ 
            title: 'Procesando...', 
            text: 'Estamos registrando tu pedido', 
            background: '#0f1929', 
            color: '#fff', 
            allowOutsideClick: false, 
            didOpen: () => { Swal.showLoading() } 
        });

        // 4. Mandamos el pedido al backend (la ruta que pusimos en web.php)
        const response = await axios.post('/api/pedidos-web', payload);

        // 5. SI ES MERCADO PAGO: Redirigimos al link que nos devolvió el controlador
        if (formPedido.metodo_pago === 'mercadopago' && response.data.url_pago) {
            window.location.href = response.data.url_pago; 
        } else {
            // SI ES EFECTIVO/TRANSF: Mostramos éxito y limpiamos carrito
            Swal.fire({
                title: '¡Pedido Confirmado!',
                text: 'El local ya recibió tu orden.',
                icon: 'success',
                background: '#0f1929',
                color: '#fff',
                confirmButtonColor: '#8cc63f'
            }).then(() => {
                carrito.value = [];
                guardarCarritoMemoria();
                mostrarCarrito.value = false;
                formPedido.reset();
            });
        }
    } catch (error) {
        console.error("Error al enviar pedido:", error);
        // Si el servidor tira error (ej: 401 si no estás logueado), lo mostramos acá
        const msg = error.response?.data?.error || 'No se pudo procesar el pedido. Intentá de nuevo.';
        Swal.fire({ icon: 'error', title: 'Oops...', text: msg, background: '#0f1929', color: '#fff' });
    }
};

// ── MAPA & GPS ─────────────────────────────────────────────────────────────────
let map = null;
let markersLayer = null;

const initMap = () => {
    const contenedor = document.getElementById('map-container');
    if (!contenedor || map) return;
    map = L.map('map-container', { zoomControl: false }).setView([-27.367, -55.896], 13);
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', { attribution: '&copy; OSM' }).addTo(map);
    markersLayer = L.layerGroup().addTo(map);
    dibujarPines();
};

const dibujarPines = () => {
    if (!markersLayer || !props.sucursalesBackend) return;
    markersLayer.clearLayers();
    props.sucursalesBackend.forEach(suc => {
        const lat = Number(suc.latitud);
        const lng = Number(suc.longitud);
        if (lat !== 0 && lng !== 0) {
            L.circleMarker([lat, lng], { radius: 11, fillColor: '#00adef', color: '#ffffff', weight: 3, opacity: 1, fillOpacity: 1 })
                .addTo(markersLayer)
                .bindPopup(`<div style="padding:4px"><b>${suc.nombre}</b><br><small>${suc.direccion || ''}</small></div>`)
                .on('click', () => { sucursalElegida.value = suc.id; cargarProductos(); map.setView([lat, lng], 15); });
        }
    });
};

watch(() => props.sucursalesBackend, () => dibujarPines(), { deep: true });

const calcularDistanciaFisica = (lat1, lon1, lat2, lon2) => {
    const R = 6371, dLat = (lat2-lat1)*Math.PI/180, dLon = (lon2-lon1)*Math.PI/180;
    const a = Math.sin(dLat/2)**2 + Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dLon/2)**2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
};

const usarGps = () => {
    if (!navigator.geolocation) return;
    localizando.value = true;
    navigator.geolocation.getCurrentPosition(async (position) => {
        const { latitude, longitude } = position.coords;
        map.setView([latitude, longitude], 14);
        
        try {
            const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${latitude}&lon=${longitude}`;
            const response = await axios.get(url);
            if (response.data && response.data.address) {
                const calle = response.data.address.road || '';
                const numero = response.data.address.house_number || '';
                formPedido.direccion_entrega = `${calle} ${numero}`.trim();
            }
        } catch (error) {
            console.error("No se pudo obtener el nombre de la calle", error);
        }
        
        let masCercana = null, distanciaMinima = Infinity;
        props.sucursalesBackend?.forEach(suc => {
            const sLat = Number(suc.latitud), sLng = Number(suc.longitud);
            if (sLat !== 0 && sLng !== 0) {
                const d = calcularDistanciaFisica(latitude, longitude, sLat, sLng);
                if (d < distanciaMinima) { distanciaMinima = d; masCercana = suc; }
            }
        });
        
        if (masCercana) { 
            sucursalElegida.value = masCercana.id; 
            distanciaClienteKm.value = distanciaMinima;
            cargarProductos(); 
        }
        localizando.value = false;
    }, () => { localizando.value = false; });
};

const cargarProductos = async () => {
    if (!sucursalElegida.value) return;
    cargando.value = true;
    try {
        const response = await axios.get(`/api/catalogo/${sucursalElegida.value}`);
        productos.value = Array.isArray(response.data) ? response.data : [];
    } catch (error) { console.error(error); productos.value = []; } finally { cargando.value = false; }
};

onMounted(() => {
    setTimeout(() => { initMap(); cargarCarritoMemoria(); }, 500);
});
</script>

<template>
    <Head :title="`${comercio?.nombre || 'Catálogo'} | VendAR`" />

    <div class="min-h-screen bg-[#080f1e] font-sans text-slate-300 relative flex flex-col overflow-x-hidden">

        <div class="fixed inset-0 pointer-events-none overflow-hidden z-0">
            <div class="absolute w-[700px] h-[700px] bg-[#00adef]/6 rounded-full blur-[180px] -top-40 -left-40"></div>
            <div class="absolute w-[500px] h-[500px] bg-[#f7941e]/5 rounded-full blur-[160px] bottom-0 right-0"></div>
            <div class="absolute w-[300px] h-[300px] bg-[#8cc63f]/4 rounded-full blur-[120px] top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2"></div>
            <div class="absolute inset-0" style="background-image: linear-gradient(rgba(0,173,239,0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(0,173,239,0.03) 1px, transparent 1px); background-size: 40px 40px;"></div>
        </div>

        <nav class="sticky top-0 z-40 border-b border-white/5 bg-[#080f1e]/85 backdrop-blur-2xl">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 py-0 flex items-stretch gap-4 min-h-[68px]">
                <div class="flex items-center gap-4 pr-4 border-r border-white/5 shrink-0">
                    <img src="/img/LogoVendar-Sidebar.png" alt="VendAR" class="h-10 w-auto object-contain">
                    <div class="hidden sm:flex flex-col leading-none">
                        <span class="text-[17px] font-black text-white tracking-tight uppercase">{{ comercio?.nombre || 'VendAR' }}</span>
                        <span class="text-[9px] font-black tracking-widest text-[#8cc63f] uppercase flex items-center gap-1 mt-0.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-[#8cc63f] animate-pulse inline-block"></span>
                            Tienda Oficial
                        </span>
                    </div>
                </div>

                <div class="flex-1 flex items-center py-3">
                    <div class="relative w-full max-w-2xl">
                        <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                        </svg>
                        <input
                            v-model="busqueda"
                            type="text"
                            placeholder="Buscá productos, marcas o categorías..."
                            class="w-full bg-[#111c30] border border-white/8 rounded-2xl py-2.5 pl-11 pr-5 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-[#00adef]/50 focus:ring-2 focus:ring-[#00adef]/20 transition-all"
                        >
                    </div>
                </div>

                <div class="flex items-center gap-2 pl-4 border-l border-white/5 shrink-0" v-if="canLogin">
                    <button
                        v-if="carrito.length > 0"
                        @click="mostrarCarrito = true"
                        class="relative flex items-center gap-2.5 bg-[#f7941e] hover:bg-[#f7941e]/90 text-white px-4 py-2 rounded-xl font-bold text-xs transition-all shadow-lg shadow-[#f7941e]/20 active:scale-95"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/>
                        </svg>
                        <span class="bg-white/20 rounded-lg px-1.5 py-0.5 font-mono font-black text-[11px]">{{ totalItems }}</span>
                        <span class="hidden lg:inline font-black">{{ formatearDinero(totalFinalCheckout) }}</span>
                    </button>

                    <Link
                        v-if="estaLogueado && page.props.auth.user.roles?.some(r => ['SuperAdmin','Administrador Global','Encargado','cajero'].includes(r))"
                        :href="route('dashboard')"
                        class="text-[11px] font-bold uppercase tracking-wider text-white bg-white/5 hover:bg-white/10 border border-white/10 rounded-xl px-4 py-2 transition-all"
                    >Mi Panel</Link>

                    <div v-else-if="estaLogueado" class="flex items-center gap-2 bg-[#111c30] border border-white/8 px-3 py-2 rounded-xl">
                        <div class="w-6 h-6 rounded-full bg-[#00adef]/15 border border-[#00adef]/30 flex items-center justify-center text-[#00adef] shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0"/></svg>
                        </div>
                        <span class="text-[11px] font-bold text-slate-200 max-w-[90px] truncate">{{ page.props.auth.user.name.split(' ')[0] }}</span>
                        <span class="text-white/20">|</span>
                        <Link :href="route('logout')" method="post" as="button" class="text-[11px] font-bold text-rose-500 hover:text-white hover:bg-rose-600 px-2 py-1 rounded-lg transition-all">Salir</Link>
                    </div>

                    <template v-else>
                        <Link :href="route('login')" class="text-[11px] font-bold uppercase tracking-wider text-[#00adef] hover:text-white px-3 py-2 transition-colors">Ingresar</Link>
                        <Link v-if="canRegister" :href="route('register', { tienda: props.tienda_slug })" class="text-[11px] font-bold uppercase tracking-wider text-white bg-[#00adef] hover:bg-[#00adef]/80 rounded-xl px-4 py-2 shadow-lg shadow-[#00adef]/20 transition-all">Registrarse</Link>
                    </template>
                </div>
            </div>
        </nav>

        <div class="sticky top-[68px] z-30 border-b border-white/5 bg-[#0a1325]/90 backdrop-blur-xl">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 overflow-x-auto no-scrollbar">
                <div class="flex gap-0 min-w-max">
                    <button
                        @click="categoriaSeleccionada = 'todas'"
                        :class="categoriaSeleccionada === 'todas' ? 'border-b-2 border-[#00adef] text-[#00adef] bg-[#00adef]/5' : 'border-b-2 border-transparent text-slate-500 hover:text-slate-300'"
                        class="py-3.5 px-5 text-[10px] font-black uppercase tracking-widest whitespace-nowrap transition-all"
                    >Todo</button>
                    <button
                        v-for="cat in categorias"
                        :key="cat.id"
                        @click="categoriaSeleccionada = cat.id"
                        :class="categoriaSeleccionada == cat.id ? 'border-b-2 border-[#00adef] text-[#00adef] bg-[#00adef]/5' : 'border-b-2 border-transparent text-slate-500 hover:text-slate-300'"
                        class="py-3.5 px-5 text-[10px] font-black uppercase tracking-widest whitespace-nowrap transition-all"
                    >{{ cat.nombre }}</button>
                </div>
            </div>
        </div>

        <div class="relative z-10 w-full border-b border-white/5">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 py-6 flex flex-col md:flex-row gap-4 items-stretch">
                <div class="flex-1 bg-gradient-to-br from-[#00adef]/10 to-[#00adef]/3 border border-[#00adef]/15 rounded-2xl p-6 flex flex-col justify-between min-h-[130px] relative overflow-hidden">
                    <div class="absolute right-4 top-4 w-20 h-20 bg-[#00adef]/10 rounded-full blur-xl"></div>
                    <div>
                        <p class="text-[10px] font-black text-[#00adef] tracking-widest uppercase mb-1">📍 Elegí tu sucursal</p>
                        <p class="text-white font-bold text-sm">El mapa detecta tu local más cercano automáticamente.</p>
                    </div>
                    <div class="flex gap-2 mt-4">
                        <select
                            v-model="sucursalElegida"
                            @change="cargarProductos"
                            class="flex-1 bg-[#111c30] border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm font-bold focus:outline-none focus:border-[#00adef]/50 focus:ring-2 focus:ring-[#00adef]/20 transition-all"
                        >
                            <option value="" disabled>Seleccioná un local...</option>
                            <option v-for="suc in sucursalesBackend" :key="suc.id" :value="suc.id">{{ suc.nombre }}</option>
                        </select>
                        <button
                            @click="usarGps"
                            :disabled="localizando"
                            class="bg-[#00adef] hover:bg-[#00adef]/80 text-white px-4 rounded-xl font-bold text-sm transition-all flex items-center gap-2 disabled:opacity-50 shadow-lg shadow-[#00adef]/20"
                        >
                            <span v-if="localizando" class="animate-spin">⟳</span>
                            <span v-else>📍</span>
                        </button>
                    </div>
                </div>

                <div class="md:w-72 bg-gradient-to-br from-[#f7941e]/10 to-[#f7941e]/3 border border-[#f7941e]/15 rounded-2xl p-6 flex flex-col justify-between relative overflow-hidden">
                    <div class="absolute right-4 top-4 w-16 h-16 bg-[#f7941e]/15 rounded-full blur-xl"></div>
                    <div>
                        <p class="text-[10px] font-black text-[#f7941e] tracking-widest uppercase mb-1">🛵 Delivery disponible</p>
                        <p class="text-white font-bold text-sm">Pedí desde casa y recibí en minutos.</p>
                    </div>
                    <div class="mt-4 flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full bg-[#8cc63f] animate-pulse"></div>
                        <span class="text-[#8cc63f] text-xs font-bold">Locales activos ahora</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="relative z-10 max-w-7xl mx-auto w-full px-4 sm:px-6 pt-6">
            <div id="map-container" class="w-full h-[180px] rounded-2xl border border-white/8 overflow-hidden bg-[#111c30] shadow-xl"></div>
        </div>

        <main class="flex-grow relative z-10 max-w-7xl mx-auto w-full px-4 sm:px-6 py-6 pb-24">
            <div class="flex items-center justify-between mb-5" v-if="sucursalElegida">
                <div>
                    <h2 class="text-base font-black text-white uppercase tracking-tight">
                        {{ categoriaSeleccionada === 'todas' ? 'Todos los productos' : categorias?.find(c => c.id == categoriaSeleccionada)?.nombre }}
                    </h2>
                    <p class="text-[11px] text-slate-500 mt-0.5">{{ productosFiltrados.length }} productos disponibles</p>
                </div>
                <div class="flex md:hidden relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/></svg>
                    <input v-model="busqueda" type="text" placeholder="Buscar..." class="bg-[#111c30] border border-white/8 rounded-xl py-2 pl-9 pr-4 text-xs text-white placeholder-slate-600 focus:outline-none focus:border-[#00adef]/50 w-40 transition-all">
                </div>
            </div>

            <div v-if="cargando" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                <div v-for="n in 10" :key="n" class="bg-[#111c30]/80 border border-white/5 rounded-2xl overflow-hidden animate-pulse">
                    <div class="h-36 bg-white/5"></div>
                    <div class="p-3 space-y-2">
                        <div class="h-2.5 bg-white/5 rounded w-3/4"></div>
                        <div class="h-4 bg-white/5 rounded w-1/2"></div>
                        <div class="h-8 bg-white/5 rounded-xl"></div>
                    </div>
                </div>
            </div>

            <div v-else-if="!sucursalElegida" class="flex flex-col items-center justify-center py-20 text-center">
                <div class="w-20 h-20 bg-[#00adef]/10 border border-[#00adef]/20 rounded-3xl flex items-center justify-center text-4xl mb-5">🏪</div>
                <h3 class="text-lg font-black text-white mb-2">Tu pedido empieza acá</h3>
                <p class="text-slate-500 text-sm max-w-xs">Elegí una sucursal arriba o usá el GPS para ver el local más cercano.</p>
            </div>

            <div v-else-if="productosFiltrados.length === 0 && !cargando" class="flex flex-col items-center justify-center py-20 text-center">
                <div class="w-20 h-20 bg-[#f7941e]/10 border border-[#f7941e]/20 rounded-3xl flex items-center justify-center text-4xl mb-5">📦</div>
                <h3 class="text-lg font-black text-white mb-2">Sin productos</h3>
                <p class="text-slate-500 text-sm max-w-xs">{{ busqueda ? `No encontramos resultados para "${busqueda}"` : 'Este local todavía no cargó productos visibles.' }}</p>
            </div>

            <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                <div
                    v-for="p in productosFiltrados"
                    :key="p.id"
                    class="group bg-[#0f1929] border border-white/6 rounded-2xl overflow-hidden flex flex-col transition-all duration-200 hover:border-[#00adef]/30 hover:shadow-xl hover:shadow-[#00adef]/5 hover:-translate-y-0.5"
                >
                    <div class="relative h-36 bg-white flex items-center justify-center p-3 overflow-hidden">
                        <img :src="p.imagen_url || '/img/LogoVendar-Sidebar.png'" :alt="p.nombre" class="max-h-full max-w-full object-contain group-hover:scale-105 transition-transform duration-300">
                        <div class="absolute top-2 left-2 bg-[#8cc63f] text-white px-2 py-0.5 rounded-full text-[8px] font-black uppercase tracking-wider shadow-sm">{{ p.categoria?.nombre || 'General' }}</div>
                        <div class="absolute inset-x-0 bottom-0 h-6 bg-gradient-to-t from-[#0f1929]/20 to-transparent"></div>
                    </div>
                    <div class="p-3 flex flex-col flex-grow">
                        <h3 class="text-[10px] font-bold text-slate-300 leading-tight line-clamp-2 mb-2 flex-grow">{{ p.nombre }}</h3>
                        <p class="text-[17px] font-black text-white tracking-tight">{{ formatearDinero(parsearPrecio(p.precio)) }}</p>
                        <button
                            @click="agregarAlCarrito(p)"
                            :disabled="p.stock <= 0"
                            class="mt-2.5 w-full font-black text-[9px] uppercase tracking-widest py-2.5 rounded-xl border transition-all duration-150"
                            :class="p.stock <= 0
                                ? 'bg-slate-700/30 text-slate-600 border-slate-700/30 cursor-not-allowed'
                                : 'bg-[#f7941e]/15 hover:bg-[#f7941e] text-[#f7941e] hover:text-white border-[#f7941e]/30 hover:border-[#f7941e] active:scale-95'"
                        >
                            {{ p.stock <= 0 ? 'Sin Stock' : '+ Agregar' }}
                        </button>
                    </div>
                </div>
            </div>
        </main>

        <Transition name="backdrop">
            <div v-if="mostrarCarrito" class="fixed inset-0 z-50 flex justify-end bg-black/70 backdrop-blur-sm" @click.self="mostrarCarrito = false">
                <Transition name="drawer">
                    <div class="bg-[#090e1b] w-full max-w-[420px] h-full shadow-2xl flex flex-col border-l border-white/5">

                        <div class="px-5 py-4 border-b border-white/5 bg-[#0f1929] flex justify-between items-center shrink-0">
                            <div>
                                <h2 class="text-sm font-black text-white tracking-widest uppercase flex items-center gap-2">
                                    <svg class="w-4 h-4 text-[#f7941e]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/></svg>
                                    Tu orden
                                </h2>
                                <p class="text-[10px] text-slate-500 mt-0.5">{{ totalItems }} {{ totalItems === 1 ? 'producto' : 'productos' }}</p>
                            </div>
                            <button @click="mostrarCarrito = false" class="w-8 h-8 flex items-center justify-center rounded-xl bg-white/5 hover:bg-white/10 text-slate-400 hover:text-white text-xl transition-all">&times;</button>
                        </div>

                        <div class="overflow-y-auto flex-grow p-4 space-y-2.5 custom-scrollbar">
                            <div v-for="(item, index) in carrito" :key="item.id" class="flex items-center gap-3 bg-[#0f1929] border border-white/6 p-3 rounded-xl">
                                <div class="w-14 h-14 bg-white rounded-xl p-1.5 flex items-center justify-center shrink-0">
                                    <img :src="item.imagen_url || '/img/LogoVendar-Sidebar.png'" class="max-h-full object-contain">
                                </div>
                                <div class="flex-grow min-w-0">
                                    <p class="text-[11px] font-bold text-slate-200 truncate leading-tight">{{ item.nombre }}</p>
                                    <p class="text-sm font-black text-[#00adef] mt-0.5">{{ formatearDinero(item.precio * item.cantidad) }}</p>
                                </div>
                                <div class="flex items-center gap-1 bg-[#080f1e] border border-white/8 rounded-xl p-1 shrink-0">
                                    <button @click="cambiarCantidad(index, -1)" class="w-6 h-6 bg-[#1a2742] hover:bg-rose-600 text-white rounded-lg font-bold text-xs flex items-center justify-center transition-colors">−</button>
                                    <input type="number" :value="item.cantidad" @input="validarCantidadInput(index, $event)" class="w-9 bg-transparent border-none text-center text-xs font-black text-white focus:ring-0 p-0">
                                    <button @click="cambiarCantidad(index, 1)" class="w-6 h-6 bg-[#1a2742] hover:bg-[#8cc63f] text-white rounded-lg font-bold text-xs flex items-center justify-center transition-colors">+</button>
                                </div>
                            </div>
                        </div>

                        <div class="shrink-0 border-t border-white/5 bg-[#0f1929] p-5 space-y-4 overflow-y-auto max-h-[75vh]">
                            
                            <div>
                                <p class="text-[9px] font-black tracking-widest text-slate-500 uppercase mb-2">Tipo de entrega</p>
                                <div class="grid grid-cols-2 gap-2">
                                    <button @click="formPedido.tipo_entrega = 'local'" :class="formPedido.tipo_entrega === 'local' ? 'bg-[#00adef] text-white border-[#00adef] shadow-lg shadow-[#00adef]/20' : 'bg-[#080f1e] text-slate-500 border-white/8 hover:border-white/20'" class="py-2.5 border rounded-xl text-[10px] font-black uppercase flex flex-col items-center gap-1 transition-all">
                                        <span>🏬</span> Retiro en local
                                    </button>
                                    <button @click="formPedido.tipo_entrega = 'delivery'" :class="formPedido.tipo_entrega === 'delivery' ? 'bg-[#f7941e] text-white border-[#f7941e] shadow-lg shadow-[#f7941e]/20' : 'bg-[#080f1e] text-slate-500 border-white/8 hover:border-white/20'" class="py-2.5 border rounded-xl text-[10px] font-black uppercase flex flex-col items-center gap-1 transition-all">
                                        <span>🛵</span> Delivery
                                    </button>
                                </div>
                            </div>

                            <div v-if="formPedido.tipo_entrega === 'delivery'" class="space-y-2 p-3 bg-[#f7941e]/5 border border-[#f7941e]/15 rounded-xl">
                                <div class="flex items-center justify-between mb-1">
                                    <button @click="usarGps" type="button" class="text-[10px] font-bold text-[#f7941e] flex items-center gap-1 hover:text-white transition-colors">
                                        <span v-if="localizando" class="animate-spin">⟳</span>
                                        <span v-else>📍</span> 
                                        Fijar mi ubicación GPS
                                    </button>
                                    <span v-if="distanciaClienteKm > 0" class="text-[10px] text-slate-400 font-bold bg-white/5 px-2 py-0.5 rounded-full">A {{ distanciaClienteKm.toFixed(1) }} km</span>
                                </div>
                                <input v-model="formPedido.direccion_entrega" type="text" placeholder="Calle y número..." class="w-full bg-[#080f1e] border border-white/8 rounded-xl p-2.5 text-xs text-white placeholder-slate-600 focus:outline-none focus:border-[#f7941e]/50 transition-all">
                                
                                <input v-model="formPedido.piso_depto" type="text" placeholder="Casa, Depto, Piso (Opcional)..." class="w-full bg-[#080f1e] border border-white/8 rounded-xl p-2.5 text-xs text-white placeholder-slate-600 focus:outline-none focus:border-[#f7941e]/50 transition-all">
                                
                                <input v-model="formPedido.telefono_contacto" type="text" placeholder="Teléfono de contacto..." class="w-full bg-[#080f1e] border border-white/8 rounded-xl p-2.5 text-xs text-white placeholder-slate-600 focus:outline-none focus:border-[#f7941e]/50 transition-all">
                                
                                <textarea v-model="formPedido.notas" placeholder="Observaciones (Ej: Tocar timbre fuerte, sin cebolla)..." class="w-full bg-[#080f1e] border border-white/8 rounded-xl p-2.5 text-xs text-white placeholder-slate-600 focus:outline-none focus:border-[#f7941e]/50 transition-all resize-none" rows="2"></textarea>

                                <p v-if="fueraDeRango" class="text-[10px] font-bold text-rose-500 flex items-center gap-1 mt-1">⚠️ Estás fuera de la zona de cobertura (Max: {{ comercio.envio_radio_km }}km).</p>
                            </div>

                            <div>
                                <p class="text-[9px] font-black tracking-widest text-slate-500 uppercase mb-2">Método de pago</p>
                                <div class="flex flex-col gap-2">
                                    <div class="grid grid-cols-2 gap-2">
                                        <button v-if="comercio?.acepta_efectivo" @click="formPedido.metodo_pago = 'efectivo'" :class="formPedido.metodo_pago === 'efectivo' ? 'bg-[#8cc63f] text-white border-[#8cc63f]' : 'bg-[#080f1e] text-slate-500 border-white/8 hover:border-white/20'" class="py-2 border rounded-xl text-[10px] font-black uppercase transition-all">💵 Efectivo</button>
                                        <button v-if="comercio?.transferencia_cbu || comercio?.transferencia_alias" @click="formPedido.metodo_pago = 'transferencia'" :class="formPedido.metodo_pago === 'transferencia' ? 'bg-[#00adef] text-white border-[#00adef]' : 'bg-[#080f1e] text-slate-500 border-white/8 hover:border-white/20'" class="py-2 border rounded-xl text-[10px] font-black uppercase transition-all">🏦 Transf.</button>
                                    </div>
                                    <button v-if="comercio?.mp_access_token" @click="formPedido.metodo_pago = 'mercadopago'" :class="formPedido.metodo_pago === 'mercadopago' ? 'bg-[#009ee3] text-white border-[#009ee3] shadow-lg shadow-[#009ee3]/20' : 'bg-[#080f1e] text-slate-500 border-white/8 hover:border-white/20'" class="w-full py-2 border rounded-xl text-[10px] font-black uppercase transition-all flex justify-center items-center gap-1">
                                        <span>💳</span> Pagar con Mercado Pago
                                    </button>
                                </div>
                            </div>

                            <div v-if="formPedido.metodo_pago === 'transferencia'" class="p-3 bg-[#00adef]/5 border border-[#00adef]/20 rounded-xl mt-2">
                                <p class="text-[9px] font-black tracking-widest text-[#00adef] uppercase mb-1">Datos Bancarios del Local</p>
                                <p class="text-xs text-slate-300"><b>CBU/CVU:</b> {{ comercio.transferencia_cbu || 'No definido' }}</p>
                                <p class="text-xs text-slate-300"><b>Alias:</b> {{ comercio.transferencia_alias || 'No definido' }}</p>
                                <p class="text-xs text-slate-300"><b>Titular:</b> {{ comercio.transferencia_titular || 'No definido' }}</p>
                            </div>

                            <div class="bg-[#080f1e] border border-white/6 rounded-xl p-4 space-y-2 mt-4">
                                <div class="flex justify-between text-xs text-slate-400 font-bold">
                                    <span>Subtotal</span><span>{{ formatearDinero(totalProductos) }}</span>
                                </div>
                                <div v-if="formPedido.tipo_entrega === 'delivery'" class="flex justify-between text-xs text-[#f7941e] font-bold">
                                    <span>Costo de Envío</span><span>+ {{ formatearDinero(costoDeliveryExtra) }}</span>
                                </div>
                                <div class="flex justify-between text-base font-black text-white border-t border-white/8 pt-2 mt-1">
                                    <span>TOTAL</span><span>{{ formatearDinero(totalFinalCheckout) }}</span>
                                </div>
                            </div>

                            <button
                                @click="confirmarPedido"
                                :disabled="fueraDeRango"
                                class="w-full bg-[#8cc63f] hover:bg-[#8cc63f]/80 disabled:bg-slate-700 disabled:text-slate-500 text-white font-black py-4 rounded-xl text-xs uppercase tracking-widest shadow-lg shadow-[#8cc63f]/20 active:scale-95 transition-all"
                            >✓ Confirmar Pedido</button>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>

    </div>
</template>

<style>
/* Scrollbar */
.no-scrollbar::-webkit-scrollbar { display: none; }
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 4px; }

/* Spin input */
input[type=number]::-webkit-inner-spin-button,
input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
input[type=number] { -moz-appearance: textfield; }

/* Leaflet popup dark */
.leaflet-popup-content-wrapper {
    background: #0f1929 !important;
    color: #fff !important;
    border: 1px solid rgba(0,173,239,0.25);
    border-radius: 12px !important;
    box-shadow: 0 10px 40px rgba(0,0,0,0.5);
}
.leaflet-popup-tip { background: #0f1929 !important; }

/* Transitions */
.backdrop-enter-active, .backdrop-leave-active { transition: opacity 0.25s ease; }
.backdrop-enter-from, .backdrop-leave-to { opacity: 0; }

.drawer-enter-active, .drawer-leave-active { transition: transform 0.3s cubic-bezier(0.4,0,0.2,1); }
.drawer-enter-from, .drawer-leave-to { transform: translateX(100%); }
</style>