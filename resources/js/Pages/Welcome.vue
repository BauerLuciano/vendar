<script setup>
import { ref, onMounted, onUnmounted, watch, computed, nextTick } from 'vue';
import { usePage, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';

import StoreLayout from '@/Layouts/StoreLayout.vue';
import StoreNavbar from '@/Components/Tienda/StoreNavbar.vue';
import CategoryCarousel from '@/Components/Tienda/CategoryCarousel.vue';
import StoreHero from '@/Components/Tienda/StoreHero.vue';
import StoreMap from '@/Components/Tienda/StoreMap.vue';
import ProductGrid from '@/Components/Tienda/ProductGrid.vue';
import ProductDetailModal from '@/Components/Tienda/ProductDetailModal.vue';
import PromoSection from '@/Components/Tienda/PromoSection.vue';

const props = defineProps({
    comercio: Object,
    sucursalesBackend: Array,
    categorias: Array,
    tienda_slug: String,
    consumidorLogueado: Object,
    geoapifyKey: String,
});

const page = usePage();
const esAdminLogueado = computed(() => !!page.props.auth.user);
const esConsumidorLogueado = computed(() => !!props.consumidorLogueado);
const estaLogueado = computed(() => esAdminLogueado.value || esConsumidorLogueado.value);
const consumidorActual = computed(() => props.consumidorLogueado);

const sucursalElegida = ref('');
const categoriaSeleccionada = ref('todas');
const busqueda = ref('');
const filtroPromosOnly = ref(false);

const productos = ref([]);
const cargando = ref(false);
const localizando = ref(false);
const distanciaClienteKm = ref(0);
const ultimasCoordenadasDelivery = ref(null);

const productoSeleccionado = ref(null);
const mostrarModalDetalle = ref(false);
const ordenActual = ref('nombre_asc');
const mostrarMapa = ref(false);
const coordenadasGps = ref(null);

const carrito = ref([]);
const mostrarCarrito = ref(false);

const formPedido = useForm({
    tipo_entrega: 'local',
    direccion_entrega: '',
    piso_depto: '',
    cliente_nombre: props.consumidorLogueado ? `${props.consumidorLogueado.nombre} ${props.consumidorLogueado.apellido}`.trim() : page.props.auth.user?.name || '',
    cliente_email: props.consumidorLogueado?.email || page.props.auth.user?.email || '',
    telefono_contacto: props.consumidorLogueado?.telefono || page.props.auth.user?.telefono || '',
    metodo_pago: '',
    notas: '',
});

const totalPaginas = ref(1);
const paginaActual = ref(1);
let timeoutBusquedaProductos = null;

const parsearPrecio = (valor) => {
    if (!valor) return 0;
    if (typeof valor === 'number') return valor;
    let str = String(valor);
    if (str.includes(',')) str = str.replace(/\./g, '').replace(',', '.');
    return parseFloat(str) || 0;
};

const formatearDinero = (monto) => {
    return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(monto);
};

const costoDeliveryExtra = computed(() => {
    if (formPedido.tipo_entrega === 'delivery' && props.comercio) {
        const precioBase = parsearPrecio(props.comercio.envio_precio_base);
        const precioPorKm = parsearPrecio(props.comercio.envio_precio_km);
        if (distanciaClienteKm.value > 0) return precioBase + (distanciaClienteKm.value * precioPorKm);
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

const cargarCarritoMemoria = () => {
    if (!props.comercio) return;
    const id = props.comercio.id;
    const guardado = localStorage.getItem(`vendar_cart_${id}`);
    if (guardado) {
        try {
            const items = JSON.parse(guardado);
            carrito.value = items.map(item => ({ ...item, precio: parsearPrecio(item.precio) }));
        } catch (e) { /* empty */ }
    }
    const sucGuardada = localStorage.getItem(`vendar_suc_${id}`);
    if (sucGuardada && props.sucursalesBackend?.some(s => s.id == sucGuardada)) {
        sucursalElegida.value = sucGuardada;
        cargarProductos();
    }
};

const guardarCarritoMemoria = () => {
    if (!props.comercio) return;
    const id = props.comercio.id;
    localStorage.setItem(`vendar_cart_${id}`, JSON.stringify(carrito.value));
    if (sucursalElegida.value) localStorage.setItem(`vendar_suc_${id}`, sucursalElegida.value);
};

const totalItems = computed(() => carrito.value.reduce((acc, i) => acc + i.cantidad, 0));
const totalProductos = computed(() => carrito.value.reduce((acc, i) => acc + (i.precio * i.cantidad), 0));
const totalFinalCheckout = computed(() => totalProductos.value + costoDeliveryExtra.value);

const categoryCounts = ref({});

const productCounts = computed(() => ({
    ...categoryCounts.value,
    todas: Object.values(categoryCounts.value).reduce((acc, c) => acc + c, 0),
}));

const productosFiltrados = computed(() => {
    if (filtroPromosOnly.value) {
        return productos.value.filter(p => p.promocion_activa);
    }
    return productos.value;
});

const agregarAlCarrito = (producto) => {
    if (!estaLogueado.value) {
        window.location.href = '/tienda/' + props.tienda_slug + '/login';
        return;
    }
    const precioLimpio = parsearPrecio(
        producto.promocion_activa && producto.precio_promocion
            ? producto.precio_promocion
            : producto.precio
    );
    const existe = carrito.value.find(item => item.id === producto.id);
    if (existe) {
        existe.cantidad++;
    } else {
        carrito.value.push({ id: producto.id, nombre: producto.nombre, precio: precioLimpio, imagen_url: producto.imagen_url, cantidad: 1 });
    }
    guardarCarritoMemoria();
    const Toast = Swal.mixin({ toast: true, position: 'bottom-end', showConfirmButton: false, timer: 1500 });
    Toast.fire({ icon: 'success', title: `Agregado: ${producto.nombre}`, background: 'var(--bg-elevated)', color: 'var(--text-primary)' });
};

const cambiarCantidad = (index, delta) => {
    carrito.value[index].cantidad += delta;
    if (carrito.value[index].cantidad <= 0) {
        carrito.value.splice(index, 1);
        if (carrito.value.length === 0) mostrarCarrito.value = false;
    }
    guardarCarritoMemoria();
};

const validarCantidadInput = (index, event) => {
    let valor = parseInt(event.target.value);
    carrito.value[index].cantidad = (isNaN(valor) || valor < 1) ? 1 : valor;
    guardarCarritoMemoria();
};

const confirmarPedido = async () => {
    if (carrito.value.length === 0) return;

    if (!sucursalElegida.value) {
        Swal.fire({ icon: 'warning', title: 'Falta la Sucursal', text: 'Elegí una sucursal antes de confirmar.', background: 'var(--bg-elevated)', color: 'var(--text-primary)' });
        return;
    }

    if (formPedido.tipo_entrega === 'delivery' && (!formPedido.telefono_contacto || formPedido.telefono_contacto.trim() === '')) {
        Swal.fire({ icon: 'warning', title: 'Falta tu teléfono', text: 'Necesitamos un número para coordinar la entrega.', background: 'var(--bg-elevated)', color: 'var(--text-primary)' });
        return;
    }

    if (formPedido.tipo_entrega === 'delivery') {
        if (!formPedido.direccion_entrega.trim()) {
            Swal.fire({ icon: 'warning', title: 'Falta Dirección', background: 'var(--bg-elevated)', color: 'var(--text-primary)' });
            return;
        }
        if (fueraDeRango.value) {
            Swal.fire({ icon: 'error', title: 'Fuera de Zona', text: `Estás muy lejos del local. Elegí "Retiro en Local".`, background: 'var(--bg-elevated)', color: 'var(--text-primary)' });
            return;
        }
    }

    if (!formPedido.metodo_pago) {
        Swal.fire({ icon: 'warning', title: 'Elegí cómo pagar', background: 'var(--bg-elevated)', color: 'var(--text-primary)' });
        return;
    }

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
        cliente_nombre: formPedido.cliente_nombre,
        cliente_email: formPedido.cliente_email,
        notas: formPedido.notas,
        distancia_km: distanciaClienteKm.value,
    };

    try {
        Swal.fire({
            title: 'Procesando...',
            text: 'Estamos registrando tu pedido',
            background: 'var(--bg-elevated)',
            color: 'var(--text-primary)',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); },
        });

        const response = await axios.post('/api/pedidos-web', payload);

        if (formPedido.metodo_pago === 'mercadopago' && response.data.url_pago) {
            window.location.href = response.data.url_pago;
        } else {
            Swal.fire({
                title: '¡Pedido Confirmado!',
                text: 'El local ya recibió tu orden.',
                icon: 'success',
                background: 'var(--bg-elevated)',
                color: 'var(--text-primary)',
                confirmButtonColor: '#8cc63f',
            }).then(() => {
                carrito.value = [];
                guardarCarritoMemoria();
                if (props.comercio) localStorage.removeItem(`vendar_suc_${props.comercio.id}`);
                mostrarCarrito.value = false;
                formPedido.reset();
            });
        }
    } catch (error) {
        console.error('Error al enviar pedido:', error);
        const msg = error.response?.data?.error || 'No se pudo procesar el pedido. Intentá de nuevo.';
        Swal.fire({ icon: 'error', title: 'Oops...', text: msg, background: 'var(--bg-elevated)', color: 'var(--text-primary)' });
    }
};

const verDetalle = (producto) => {
    productoSeleccionado.value = producto;
    mostrarModalDetalle.value = true;
};

const agregarDesdeModal = (producto) => {
    agregarAlCarrito(producto);
    mostrarModalDetalle.value = false;
    productoSeleccionado.value = null;
};

const cambiarOrden = (valor) => {
    ordenActual.value = valor;
    cargarProductos(1);
};

const cargarProductos = async (pagina = 1) => {
    if (!sucursalElegida.value) return;
    cargando.value = true;
    paginaActual.value = pagina;
    try {
        const params = { page: pagina, per_page: 50 };
        if (categoriaSeleccionada.value !== 'todas') params.categoria_id = categoriaSeleccionada.value;
        if (busqueda.value) params.busqueda = busqueda.value;
        if (ordenActual.value) {
            const [sortBy, sortOrder] = ordenActual.value.split('_');
            const sortMap = { nombre: 'nombre', precio: 'precio_venta' };
            params.sort_by = sortMap[sortBy] || 'nombre';
            params.sort_order = sortOrder || 'asc';
        }
        const response = await axios.get(`/api/catalogo/${sucursalElegida.value}`, { params });
        const result = response.data;
        productos.value = Array.isArray(result.data) ? result.data : [];
        totalPaginas.value = result.meta?.last_page || 1;
        categoryCounts.value = result.counts_por_categoria || {};
    } catch (error) { console.error(error); productos.value = []; } finally { cargando.value = false; }
};

const usarGpsHero = () => {
    if (!navigator.geolocation) {
        Swal.fire({ icon: 'error', title: 'GPS no disponible', text: 'Tu navegador no soporta geolocalización.', background: 'var(--bg-elevated)', color: 'var(--text-primary)' });
        return;
    }
    localizando.value = true;
    navigator.geolocation.getCurrentPosition((position) => {
        const { latitude, longitude } = position.coords;
        coordenadasGps.value = { lat: latitude, lng: longitude };
        ultimasCoordenadasDelivery.value = { lat: latitude, lng: longitude };
        localizando.value = false;
        mostrarMapa.value = true;
    }, (error) => {
        localizando.value = false;
        if (error.code === 1) {
            Swal.fire({ icon: 'warning', title: 'Permiso denegado', text: 'Activá la ubicación en tu navegador para usar esta función.', background: 'var(--bg-elevated)', color: 'var(--text-primary)' });
        } else {
            Swal.fire({ icon: 'error', title: 'Error de ubicación', text: 'No pudimos obtener tu ubicación. Probá escribir la dirección manualmente.', background: 'var(--bg-elevated)', color: 'var(--text-primary)' });
        }
        mostrarMapa.value = true;
    });
};

const handleMapDistance = (dist) => { distanciaClienteKm.value = dist; };
const handleMapCoords = (coords) => { ultimasCoordenadasDelivery.value = coords; };
const handleMapAddress = (addr) => { formPedido.direccion_entrega = addr; };
const handleMapSucursal = (id) => { sucursalElegida.value = id; cargarProductos(); };

watch(categoriaSeleccionada, () => {
    filtroPromosOnly.value = false;
    cargarProductos(1);
    nextTick(() => {
        const el = document.getElementById('productos-section');
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
});

const handleScrollToPromos = () => {
    const el = document.getElementById('promo-section');
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

const handleVerTodas = () => {
    filtroPromosOnly.value = true;
    categoriaSeleccionada.value = 'todas';
    cargarProductos(1);
    nextTick(() => {
        const el = document.getElementById('productos-section');
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
};
watch(busqueda, () => {
    if (timeoutBusquedaProductos) clearTimeout(timeoutBusquedaProductos);
    timeoutBusquedaProductos = setTimeout(() => { cargarProductos(1); }, 350);
});

onUnmounted(() => {
    if (timeoutBusquedaProductos) clearTimeout(timeoutBusquedaProductos);
});

onMounted(() => {
    setTimeout(() => {
        cargarCarritoMemoria();
    }, 500);
});
</script>

<template>
    <StoreLayout :titulo="`${comercio?.nombre || 'Catálogo'} | VendAR`">
        <StoreNavbar
            :comercio="comercio"
            :tienda_slug="tienda_slug"
            :busqueda="busqueda"
            :total-items="totalItems"
            :total-final-checkout="totalFinalCheckout"
            :esta-logueado="estaLogueado"
            :es-consumidor-logueado="esConsumidorLogueado"
            :es-admin-logueado="esAdminLogueado"
            :consumidor-actual="consumidorActual"
            :carrito-length="carrito.length"
            :auth-user="page.props.auth.user"
            @update:busqueda="busqueda = $event"
            @toggle-carrito="mostrarCarrito = !mostrarCarrito"
        />

        <CategoryCarousel
            :categorias="categorias"
            :categoria-seleccionada="categoriaSeleccionada"
            :product-counts="productCounts"
            @update:categoria-seleccionada="categoriaSeleccionada = $event"
        />

        <StoreHero
            :sucursales-backend="sucursalesBackend"
            :sucursal-elegida="sucursalElegida"
            :localizando="localizando"
            :comercio="comercio"
            :distancia-km="distanciaClienteKm"
            @update:sucursal-elegida="sucursalElegida = $event"
            @cargar-productos="cargarProductos()"
            @usar-gps="usarGpsHero"
        />

        <StoreMap
            :show="mostrarMapa"
            :sucursales-backend="sucursalesBackend"
            :sucursal-elegida="sucursalElegida"
            :geoapify-key="geoapifyKey"
            :tipo-entrega="formPedido.tipo_entrega"
            :coordenadas-gps="coordenadasGps"
            @close="mostrarMapa = false"
            @update:distancia="handleMapDistance"
            @update:coordenadas="handleMapCoords"
            @update:direccion="handleMapAddress"
            @sucursal-seleccionada="handleMapSucursal"
        />

        <div v-if="sucursalElegida" class="max-w-7xl mx-auto w-full px-4 sm:px-6 pt-6">
            <div class="border rounded-3xl p-6 sm:p-8 text-center transition-colors" :style="{ backgroundColor: 'color-mix(in srgb, var(--color-accent) 6%, transparent)', borderColor: 'color-mix(in srgb, var(--color-accent) 15%, transparent)' }">
                <p class="text-2xl sm:text-3xl mb-3">🛒</p>
                <h3 class="text-lg sm:text-xl font-black mb-1.5 transition-colors" :style="{ color: 'var(--text-primary)' }">Comprá online &mdash; Retirá en sucursal</h3>
                <p class="text-sm mb-4 transition-colors" :style="{ color: 'var(--text-muted)' }">Miles de productos disponibles para vos</p>
                <button
                    @click="handleScrollToPromos"
                    class="font-black text-xs uppercase tracking-widest py-3 px-8 rounded-xl border transition-all duration-200 active:scale-95"
                    style="background: linear-gradient(135deg, #f7941e, #ff6b35); color: #fff; border-color: transparent; box-shadow: 0 4px 16px rgba(247, 148, 30, 0.3);"
                >
                    🏷️ Ver promociones
                </button>
            </div>
        </div>

        <PromoSection
            :sucursal-id="sucursalElegida"
            @agregar="agregarAlCarrito"
            @detail="verDetalle"
            @ver-todas="handleVerTodas"
        />

        <div id="productos-section">
            <ProductGrid
                :productos="filtroPromosOnly ? productosFiltrados : productos"
                :cargando="cargando"
                :sucursal-elegida="sucursalElegida"
                :busqueda="busqueda"
                :categoria-seleccionada="categoriaSeleccionada"
                :categorias="categorias"
                :total-paginas="totalPaginas"
                :pagina-actual="paginaActual"
                :filtro-promos-only="filtroPromosOnly"
                @agregar="agregarAlCarrito"
                @detail="verDetalle"
                @sort-change="cambiarOrden"
                @page-change="cargarProductos($event)"
            />
        </div>

        <ProductDetailModal
            :producto="productoSeleccionado"
            :visible="mostrarModalDetalle"
            @close="mostrarModalDetalle = false; productoSeleccionado = null"
            @agregar="agregarDesdeModal"
        />

        <Transition name="backdrop">
            <div v-if="mostrarCarrito" class="fixed inset-0 z-50 flex justify-end" :style="{ backgroundColor: 'var(--overlay)', backdropFilter: 'blur(2px)' }" @click.self="mostrarCarrito = false">
                <Transition name="drawer">
                    <div class="w-full md:max-w-[420px] h-full shadow-2xl flex flex-col md:border-l transition-colors duration-300" :style="{ backgroundColor: 'var(--bg-elevated)', borderColor: 'var(--border-subtle)' }">
                        <div class="px-5 py-4 border-b flex justify-between items-center shrink-0 transition-colors" :style="{ backgroundColor: 'var(--bg-elevated)', borderColor: 'var(--border-subtle)' }">
                            <div>
                                <h2 class="text-sm font-black tracking-widest uppercase flex items-center gap-2 transition-colors" :style="{ color: 'var(--text-primary)' }">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" :style="{ color: 'var(--color-secondary)' }"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/></svg>
                                    Tu orden
                                </h2>
                                <p class="text-[10px] mt-0.5 transition-colors" :style="{ color: 'var(--text-muted)' }">{{ totalItems }} {{ totalItems === 1 ? 'producto' : 'productos' }}</p>
                            </div>
                            <button @click="mostrarCarrito = false" class="w-8 h-8 flex items-center justify-center rounded-xl text-xl transition-all" :style="{ backgroundColor: 'var(--bg-input)', color: 'var(--text-muted)' }">&times;</button>
                        </div>

                        <div class="overflow-y-auto flex-grow p-4 space-y-2.5 custom-scrollbar">
                            <div v-for="(item, index) in carrito" :key="item.id" class="flex items-center gap-3 border-2 p-3 rounded-xl shadow-sm transition-colors" :style="{ backgroundColor: 'var(--bg-card)', borderColor: 'var(--border-subtle)' }">
                                <div class="w-14 h-14 rounded-xl p-1.5 flex items-center justify-center shrink-0" :style="{ backgroundColor: 'var(--bg-image)' }">
                                    <img :src="item.imagen_url || '/img/LogoVendar-Sidebar.png'" class="max-h-full object-contain">
                                </div>
                                <div class="flex-grow min-w-0">
                                    <p class="text-xs font-bold truncate leading-tight transition-colors" :style="{ color: 'var(--text-primary)' }">{{ item.nombre }}</p>
                                    <p class="text-sm font-black mt-0.5" :style="{ color: 'var(--color-accent)' }">{{ formatearDinero(item.precio * item.cantidad) }}</p>
                                </div>
                                <div class="flex items-center gap-1 border-2 rounded-xl p-0.5 shrink-0 transition-colors" :style="{ backgroundColor: 'var(--bg-page)', borderColor: 'var(--border-color)' }">
                                    <button @click="cambiarCantidad(index, -1)" class="w-7 h-7 rounded-lg font-bold text-sm flex items-center justify-center transition-colors hover:bg-[var(--color-danger-hover)] hover:text-[var(--text-on-accent)]" :style="{ backgroundColor: 'var(--bg-card)', color: 'var(--text-secondary)' }">−</button>
                                    <input type="number" :value="item.cantidad" @input="validarCantidadInput(index, $event)" class="w-9 bg-transparent border-none text-center text-xs font-black p-0 transition-colors" :style="{ color: 'var(--text-primary)' }">
                                    <button @click="cambiarCantidad(index, 1)" class="btn-plus w-7 h-7 rounded-lg font-bold text-sm flex items-center justify-center transition-colors" :style="{ backgroundColor: 'var(--bg-card)', color: 'var(--text-secondary)' }">+</button>
                                </div>
                            </div>
                        </div>

                        <div class="shrink-0 border-t p-5 space-y-4 overflow-y-auto max-h-[75vh] transition-colors" :style="{ borderColor: 'var(--border-subtle)', backgroundColor: 'var(--bg-elevated)' }">
                            <div>
                                <p class="text-[10px] font-black tracking-widest uppercase mb-2 transition-colors" :style="{ color: 'var(--text-secondary)' }">Tipo de entrega</p>
                                <div class="grid grid-cols-2 gap-2">
                                    <button @click="formPedido.tipo_entrega = 'local'" :style="formPedido.tipo_entrega === 'local' ? { backgroundColor: 'var(--color-accent)', color: '#fff', borderColor: 'var(--color-accent)' } : { backgroundColor: 'var(--bg-card)', color: 'var(--text-secondary)', borderColor: 'var(--border-color)' }" class="py-2.5 border-2 rounded-xl text-[11px] font-black uppercase flex flex-col items-center gap-1 transition-all">
                                        <span>🏬</span> Retiro en local
                                    </button>
                                    <button @click="formPedido.tipo_entrega = 'delivery'" :style="formPedido.tipo_entrega === 'delivery' ? { backgroundColor: 'var(--color-secondary)', color: '#fff', borderColor: 'var(--color-secondary)' } : { backgroundColor: 'var(--bg-card)', color: 'var(--text-secondary)', borderColor: 'var(--border-color)' }" class="py-2.5 border-2 rounded-xl text-[11px] font-black uppercase flex flex-col items-center gap-1 transition-all">
                                        <span>🛵</span> Delivery
                                    </button>
                                </div>
                            </div>

                            <div v-if="formPedido.tipo_entrega === 'delivery'" class="space-y-2 p-3 rounded-xl" :style="{ backgroundColor: 'color-mix(in srgb, var(--color-secondary) 8%, transparent)', border: '1px solid color-mix(in srgb, var(--color-secondary) 20%, transparent)' }">
                                <div class="flex items-center justify-between mb-1">
                                    <button @click="usarGpsHero" type="button" class="btn-gps text-[10px] font-bold flex items-center gap-1 transition-colors" :style="{ color: 'var(--color-secondary)' }">
                                        <span v-if="localizando" class="animate-spin">⟳</span>
                                        <span v-else>📍</span>
                                        Fijar mi ubicación GPS
                                    </button>
                                    <span v-if="distanciaClienteKm > 0" class="text-[10px] font-bold px-2 py-0.5 rounded-full transition-colors" :style="{ color: 'var(--text-muted)', backgroundColor: 'var(--border-subtle)' }">A {{ distanciaClienteKm < 1 ? Math.round(distanciaClienteKm * 1000) + ' m' : distanciaClienteKm.toFixed(1) + ' km' }}</span>
                                </div>
                                <input v-model="formPedido.direccion_entrega" type="text" placeholder="Calle y número..." class="input-delivery w-full border rounded-xl p-2.5 text-xs transition-all focus:outline-none placeholder-[var(--text-muted)]" :style="{ backgroundColor: 'var(--bg-input)', borderColor: 'var(--border-color)', color: 'var(--text-primary)' }">
                                <input v-model="formPedido.piso_depto" type="text" placeholder="Casa, Depto, Piso (Opcional)..." class="input-delivery w-full border rounded-xl p-2.5 text-xs transition-all focus:outline-none placeholder-[var(--text-muted)]" :style="{ backgroundColor: 'var(--bg-input)', borderColor: 'var(--border-color)', color: 'var(--text-primary)' }">
                                <input v-model="formPedido.cliente_nombre" type="text" placeholder="Tu nombre..." class="input-delivery w-full border rounded-xl p-2.5 text-xs transition-all focus:outline-none placeholder-[var(--text-muted)]" :style="{ backgroundColor: 'var(--bg-input)', borderColor: 'var(--border-color)', color: 'var(--text-primary)' }">
                                <input v-model="formPedido.telefono_contacto" type="text" placeholder="Teléfono de contacto..." class="input-delivery w-full border rounded-xl p-2.5 text-xs transition-all focus:outline-none placeholder-[var(--text-muted)]" :style="{ backgroundColor: 'var(--bg-input)', borderColor: 'var(--border-color)', color: 'var(--text-primary)' }">
                                <input v-model="formPedido.cliente_email" type="email" placeholder="Correo electrónico (opcional)..." class="input-delivery w-full border rounded-xl p-2.5 text-xs transition-all focus:outline-none placeholder-[var(--text-muted)]" :style="{ backgroundColor: 'var(--bg-input)', borderColor: 'var(--border-color)', color: 'var(--text-primary)' }">
                                <textarea v-model="formPedido.notas" placeholder="Observaciones (Ej: Tocar timbre fuerte, sin cebolla)..." class="input-delivery w-full border rounded-xl p-2.5 text-xs transition-all focus:outline-none placeholder-[var(--text-muted)] resize-none" rows="2" :style="{ backgroundColor: 'var(--bg-input)', borderColor: 'var(--border-color)', color: 'var(--text-primary)' }"></textarea>
                                <p v-if="fueraDeRango" class="text-[10px] font-bold flex items-center gap-1 mt-1" :style="{ color: 'var(--color-danger)' }">⚠️ Estás fuera de la zona de cobertura (Max: {{ comercio.envio_radio_km }}km).</p>
                            </div>

                            <div>
                                <p class="text-[10px] font-black tracking-widest uppercase mb-2 transition-colors" :style="{ color: 'var(--text-secondary)' }">Método de pago</p>
                                <div class="flex flex-col gap-2">
                                    <div class="grid grid-cols-2 gap-2">
                                        <button v-if="comercio?.acepta_efectivo" @click="formPedido.metodo_pago = 'efectivo'" :style="formPedido.metodo_pago === 'efectivo' ? { backgroundColor: 'var(--color-success)', color: '#fff', borderColor: 'var(--color-success)' } : { backgroundColor: 'var(--bg-card)', color: 'var(--text-secondary)', borderColor: 'var(--border-color)' }" class="py-2.5 border-2 rounded-xl text-[11px] font-black uppercase transition-all">💵 Efectivo</button>
                                        <button v-if="comercio?.transferencia_cbu || comercio?.transferencia_alias" @click="formPedido.metodo_pago = 'transferencia'" :style="formPedido.metodo_pago === 'transferencia' ? { backgroundColor: 'var(--color-accent)', color: '#fff', borderColor: 'var(--color-accent)' } : { backgroundColor: 'var(--bg-card)', color: 'var(--text-secondary)', borderColor: 'var(--border-color)' }" class="py-2.5 border-2 rounded-xl text-[11px] font-black uppercase transition-all">🏦 Transf.</button>
                                    </div>
                                    <button v-if="comercio?.tiene_mp" @click="formPedido.metodo_pago = 'mercadopago'" :style="formPedido.metodo_pago === 'mercadopago' ? { backgroundColor: '#009ee3', color: '#fff', borderColor: '#009ee3' } : { backgroundColor: 'var(--bg-card)', color: 'var(--text-secondary)', borderColor: 'var(--border-color)' }" class="w-full py-2.5 border-2 rounded-xl text-[11px] font-black uppercase transition-all flex justify-center items-center gap-1">
                                        <span>💳</span> Pagar con Mercado Pago
                                    </button>
                                </div>
                            </div>

                            <div v-if="formPedido.metodo_pago === 'transferencia'" class="p-3 rounded-xl mt-2" :style="{ backgroundColor: 'color-mix(in srgb, var(--color-accent) 8%, transparent)', border: '1px solid color-mix(in srgb, var(--color-accent) 25%, transparent)' }">
                                <p class="text-[9px] font-black tracking-widest uppercase mb-1" :style="{ color: 'var(--color-accent)' }">Datos Bancarios del Local</p>
                                <p class="text-xs transition-colors" :style="{ color: 'var(--text-secondary)' }"><b>CBU/CVU:</b> {{ comercio.transferencia_cbu || 'No definido' }}</p>
                                <p class="text-xs transition-colors" :style="{ color: 'var(--text-secondary)' }"><b>Alias:</b> {{ comercio.transferencia_alias || 'No definido' }}</p>
                                <p class="text-xs transition-colors" :style="{ color: 'var(--text-secondary)' }"><b>Titular:</b> {{ comercio.transferencia_titular || 'No definido' }}</p>
                            </div>

                            <div class="border-2 rounded-xl p-4 space-y-2 mt-4 transition-colors" :style="{ backgroundColor: 'var(--bg-card)', borderColor: 'var(--border-color)' }">
                                <div class="flex justify-between text-sm font-bold transition-colors" :style="{ color: 'var(--text-secondary)' }">
                                    <span>Subtotal</span><span>{{ formatearDinero(totalProductos) }}</span>
                                </div>
                                <div v-if="formPedido.tipo_entrega === 'delivery'" class="flex justify-between text-sm font-bold" :style="{ color: 'var(--color-secondary)' }">
                                    <span>Costo de Envío</span><span>+ {{ formatearDinero(costoDeliveryExtra) }}</span>
                                </div>
                                <div class="flex justify-between text-lg font-black border-t-2 pt-2 mt-1 transition-colors" :style="{ color: 'var(--text-primary)', borderColor: 'var(--border-subtle)' }">
                                    <span>TOTAL</span><span>{{ formatearDinero(totalFinalCheckout) }}</span>
                                </div>
                            </div>

                            <button
                                @click="confirmarPedido"
                                :disabled="fueraDeRango"
                                class="btn-confirmar w-full text-white font-black py-4 rounded-xl text-xs uppercase tracking-widest active:scale-95 transition-all"
                            >✓ Confirmar Pedido</button>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </StoreLayout>
</template>

<style>
.no-scrollbar::-webkit-scrollbar { display: none; }
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: var(--bg-card); border-radius: 4px; }

input[type=number]::-webkit-inner-spin-button,
input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
input[type=number] { -moz-appearance: textfield; }

.leaflet-popup-content-wrapper {
    background: var(--bg-elevated) !important;
    color: var(--text-primary) !important;
    border: 1px solid color-mix(in srgb, var(--color-accent) 25%, transparent);
    border-radius: 12px !important;
    box-shadow: 0 10px 40px rgba(0,0,0,0.5);
}
.leaflet-popup-tip { background: var(--bg-elevated) !important; }

.marcador-sucursal, .pin-entrega {
    background: none !important;
    border: none !important;
}

.backdrop-enter-active, .backdrop-leave-active { transition: opacity 0.25s ease; }
.backdrop-enter-from, .backdrop-leave-to { opacity: 0; }

.drawer-enter-active, .drawer-leave-active { transition: transform 0.3s cubic-bezier(0.4,0,0.2,1); }
.drawer-enter-from, .drawer-leave-to { transform: translateX(100%); }

.btn-plus:hover {
    background-color: var(--color-success) !important;
    color: #fff !important;
}

.btn-confirmar:not(:disabled) {
    background-color: var(--color-success);
    box-shadow: 0 4px 12px color-mix(in srgb, var(--color-success) 20%, transparent);
}
.btn-confirmar:not(:disabled):hover {
    filter: brightness(0.9);
}
.btn-confirmar:disabled {
    background-color: var(--bg-disabled);
    color: var(--text-disabled);
    cursor: not-allowed;
}

.btn-gps:hover {
    color: #fff !important;
}

.input-delivery:focus {
    border-color: color-mix(in srgb, var(--color-secondary) 50%, transparent) !important;
    outline: none;
}

@media (max-width: 767px) {
    .drawer-enter-active, .drawer-leave-active { transition: transform 0.3s cubic-bezier(0.4,0,0.2,1); }
    .drawer-enter-from, .drawer-leave-to { transform: translateY(100%); }
}
</style>
