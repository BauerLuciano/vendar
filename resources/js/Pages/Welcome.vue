<script setup>
import { ref, onMounted, watch, computed, nextTick } from 'vue';
import { usePage, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';

import StoreLayout from '@/Layouts/StoreLayout.vue';
import StoreNavbar from '@/Components/Tienda/StoreNavbar.vue';
import HeroSection from '@/Components/Sections/HeroSection.vue';
import CategoriesSection from '@/Components/Sections/CategoriesSection.vue';
import ProductsSection from '@/Components/Sections/ProductsSection.vue';
import PromotionsSection from '@/Components/Sections/PromotionsSection.vue';
import FooterSection from '@/Components/Sections/FooterSection.vue';
import WhatsAppSection from '@/Components/Sections/WhatsAppSection.vue';
import CartItem from '@/Components/Commerce/CartItem.vue';
import DeliveryForm from '@/Components/Commerce/DeliveryForm.vue';

const props = defineProps({
    comercio: Object,
    sucursalesBackend: Array,
    categorias: Array,
    tienda_slug: String,
    consumidorLogueado: Object,
    geoapifyKey: String,
    storeConfig: Object,
    pedidoExitoso: Boolean,
    pedidoId: Number,
    mpPaymentId: String,
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

const sectionsList = computed(() => {
    const sections = props.storeConfig?.sections || {};
    return Object.entries(sections)
        .map(([id, config]) => ({ id, ...config }))
        .sort((a, b) => (a.order || 99) - (b.order || 99));
});

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
        return productos.value.filter(p => p.promotion);
    }
    return productos.value;
});

const agregarAlCarrito = (producto) => {
    if (producto.stock !== undefined && producto.stock <= 0) {
        const Toast = Swal.mixin({ toast: true, position: 'bottom-end', showConfirmButton: false, timer: 2000 });
        Toast.fire({ icon: 'error', title: `${producto.nombre} no tiene stock`, background: 'var(--bg-elevated)', color: 'var(--text-primary)' });
        return;
    }
    const precioLimpio = parsearPrecio(
        producto.promotion
            ? producto.promotion.final_price
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

const handleCartQuantity = ({ index, delta, value }) => {
    if (value !== undefined) {
        carrito.value[index].cantidad = value;
    } else {
        carrito.value[index].cantidad += delta;
        if (carrito.value[index].cantidad <= 0) {
            carrito.value.splice(index, 1);
            if (carrito.value.length === 0) mostrarCarrito.value = false;
        }
    }
    guardarCarritoMemoria();
};



const confirmarPedido = async () => {
    if (carrito.value.length === 0) return;

    if (!sucursalElegida.value) {
        Swal.fire({ icon: 'warning', title: 'Falta la Sucursal', text: 'Elegí una sucursal antes de confirmar.', background: 'var(--bg-elevated)', color: 'var(--text-primary)' });
        return;
    }

    if (!estaLogueado.value && (!formPedido.cliente_nombre || formPedido.cliente_nombre.trim() === '')) {
        Swal.fire({ icon: 'warning', title: 'Falta tu nombre', text: 'Decinos tu nombre para registrar el pedido.', background: 'var(--bg-elevated)', color: 'var(--text-primary)' });
        return;
    }

    if ((!estaLogueado.value || formPedido.tipo_entrega === 'delivery') && (!formPedido.telefono_contacto || formPedido.telefono_contacto.trim() === '')) {
        Swal.fire({ icon: 'warning', title: 'Falta tu teléfono', text: 'Necesitamos un número para contactarte.', background: 'var(--bg-elevated)', color: 'var(--text-primary)' });
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

        Swal.close();

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
                confirmButtonText: 'Volver al catálogo',
            }).then(() => {
                carrito.value = [];
                guardarCarritoMemoria();
                if (props.comercio) localStorage.removeItem(`vendar_suc_${props.comercio.id}`);
                mostrarCarrito.value = false;
                formPedido.reset();
            });
        }
        Swal.close();
    } catch (error) {
        Swal.close();
        console.error('Error al enviar pedido:', error);
        const msg = error.response?.data?.mensaje || error.response?.data?.error || 'No se pudo procesar el pedido. Intentá de nuevo.';
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

onMounted(() => {
    setTimeout(() => {
        cargarCarritoMemoria();
    }, 500);

    if (props.pedidoExitoso && props.pedidoId) {
        axios.post(`/api/pedidos/${props.pedidoId}/confirmar-pago`, {
            payment_id: props.mpPaymentId || undefined,
        });
        carrito.value = [];
        guardarCarritoMemoria();
        if (props.comercio) localStorage.removeItem(`vendar_suc_${props.comercio.id}`);
        mostrarCarrito.value = false;
        formPedido.reset();
        const slug = props.tienda_slug || props.comercio?.slug || '';
        Swal.fire({
            icon: 'success',
            title: '¡Pago confirmado!',
            text: 'Tu pedido ya fue registrado. El local te va a notificar cuando esté listo.',
            background: 'var(--bg-elevated)',
            color: 'var(--text-primary)',
            confirmButtonColor: '#8cc63f',
            confirmButtonText: 'Volver al catálogo',
            iconColor: '#8cc63f',
            timer: 10000,
            timerProgressBar: true,
        }).then((r) => {
            if (r.isConfirmed || r.dismiss === Swal.DismissReason.timer) {
                window.location.href = `/tienda/${slug}`;
            }
        });
        return;
    }

    if (props.pedidoExitoso && !props.pedidoId) {
        const slug2 = props.tienda_slug || props.comercio?.slug || '';
        Swal.fire({
            icon: 'success',
            title: '¡Pedido realizado!',
            text: 'Tu pedido fue registrado correctamente. El local te va a notificar cuando esté listo.',
            background: 'var(--bg-elevated)',
            color: 'var(--text-primary)',
            confirmButtonColor: '#8cc63f',
            confirmButtonText: 'Volver al catálogo',
            iconColor: '#8cc63f',
            timer: 10000,
            timerProgressBar: true,
        }).then((r) => {
            if (r.isConfirmed || r.dismiss === Swal.DismissReason.timer) {
                window.location.href = `/tienda/${slug2}`;
            }
        });
    }
});
</script>

<template>
    <StoreLayout :titulo="`${comercio?.nombre || 'Catálogo'} | VendAR`" :comercio="comercio" :storeConfig="storeConfig">
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

        <template v-for="sec in sectionsList" :key="sec.id">
            <HeroSection
                v-if="sec.id === 'hero' && sec.enabled"
                :section="sec"
                :sucursales-backend="sucursalesBackend"
                :sucursal-elegida="sucursalElegida"
                :localizando="localizando"
                :comercio="comercio"
                :distancia-km="distanciaClienteKm"
                :mostrar-mapa="mostrarMapa"
                :geoapify-key="geoapifyKey"
                :tipo-entrega="formPedido.tipo_entrega"
                :coordenadas-gps="coordenadasGps"
                @update:sucursal-elegida="sucursalElegida = $event"
                @cargar-productos="cargarProductos()"
                @usar-gps="usarGpsHero"
                @close-mapa="mostrarMapa = false"
                @update:distancia="handleMapDistance"
                @update:coordenadas="handleMapCoords"
                @update:direccion="handleMapAddress"
                @sucursal-seleccionada="handleMapSucursal"
                @scroll-to-promos="handleScrollToPromos"
            />

            <CategoriesSection
                v-if="sec.id === 'categories' && sec.enabled"
                :section="sec"
                :categorias="categorias"
                :categoria-seleccionada="categoriaSeleccionada"
                :product-counts="productCounts"
                @update:categoria-seleccionada="categoriaSeleccionada = $event"
            />

            <PromotionsSection
                v-if="sec.id === 'promotions' && sec.enabled"
                :section="sec"
                :sucursal-id="sucursalElegida"
                @agregar="agregarAlCarrito"
                @detail="verDetalle"
                @ver-todas="handleVerTodas"
            />

            <ProductsSection
                v-if="sec.id === 'products'"
                :section="sec"
                :productos="filtroPromosOnly ? productosFiltrados : productos"
                :cargando="cargando"
                :sucursal-elegida="sucursalElegida"
                :busqueda="busqueda"
                :categoria-seleccionada="categoriaSeleccionada"
                :categorias="categorias"
                :total-paginas="totalPaginas"
                :pagina-actual="paginaActual"
                :filtro-promos-only="filtroPromosOnly"
                :producto-seleccionado="productoSeleccionado"
                :mostrar-modal-detalle="mostrarModalDetalle"
                @agregar="agregarAlCarrito"
                @detail="verDetalle"
                @sort-change="cambiarOrden"
                @page-change="cargarProductos($event)"
                @close-modal="mostrarModalDetalle = false; productoSeleccionado = null"
                @agregar-desde-modal="agregarDesdeModal"
            />

            <FooterSection
                v-if="sec.id === 'footer' && sec.enabled"
                :section="sec"
                :comercio="comercio"
            />

            <WhatsAppSection
                v-if="sec.id === 'whatsapp' && sec.enabled"
                :section="sec"
            />
        </template>

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
                            <CartItem
                                v-for="(item, index) in carrito"
                                :key="item.id"
                                :item="item"
                                :index="index"
                                @update-quantity="handleCartQuantity"
                            />
                        </div>

                        <div class="shrink-0 border-t p-5 space-y-4 overflow-y-auto max-h-[75vh] transition-colors" :style="{ borderColor: 'var(--border-subtle)', backgroundColor: 'var(--bg-elevated)' }">
                            <DeliveryForm
                                :form-data="formPedido"
                                :localizando="localizando"
                                :distancia-cliente-km="distanciaClienteKm"
                                :fuera-de-rango="fueraDeRango"
                                :comercio="comercio"
                                @update:tipo-entrega="formPedido.tipo_entrega = $event"
                                @update:direccion="formPedido.direccion_entrega = $event"
                                @update:piso="formPedido.piso_depto = $event"
                                @update:nombre="formPedido.cliente_nombre = $event"
                                @update:telefono="formPedido.telefono_contacto = $event"
                                @update:email="formPedido.cliente_email = $event"
                                @update:notas="formPedido.notas = $event"
                                @usar-gps="usarGpsHero"
                            />

                            <div v-if="!estaLogueado && formPedido.tipo_entrega === 'local'" class="space-y-2 p-3 rounded-xl" :style="{ backgroundColor: 'color-mix(in srgb, var(--color-accent) 8%, transparent)', border: '1px solid color-mix(in srgb, var(--color-accent) 20%, transparent)' }">
                                <p class="text-[10px] font-black tracking-widest uppercase" :style="{ color: 'var(--text-secondary)' }">Tus datos</p>
                                <input v-model="formPedido.cliente_nombre" type="text" placeholder="Tu nombre *" class="w-full border rounded-xl p-2.5 text-xs transition-all focus:outline-none" :style="{ backgroundColor: 'var(--bg-input)', borderColor: 'var(--border-color)', color: 'var(--text-primary)' }">
                                <input v-model="formPedido.telefono_contacto" type="text" placeholder="Teléfono de contacto *" class="w-full border rounded-xl p-2.5 text-xs transition-all focus:outline-none" :style="{ backgroundColor: 'var(--bg-input)', borderColor: 'var(--border-color)', color: 'var(--text-primary)' }">
                                <input v-model="formPedido.cliente_email" type="email" placeholder="Correo electrónico (opcional)" class="w-full border rounded-xl p-2.5 text-xs transition-all focus:outline-none" :style="{ backgroundColor: 'var(--bg-input)', borderColor: 'var(--border-color)', color: 'var(--text-primary)' }">
                            </div>

                            <div>
                                <p class="text-[10px] font-black tracking-widest uppercase mb-2 transition-colors" :style="{ color: 'var(--text-secondary)' }">Método de pago</p>
                                <div class="flex flex-col gap-2">
                                    <div class="grid grid-cols-2 gap-2">
                                        <button v-if="comercio?.acepta_efectivo" @click="formPedido.metodo_pago = 'efectivo'" :style="formPedido.metodo_pago === 'efectivo' ? { backgroundColor: 'var(--color-success)', color: '#fff', borderColor: 'var(--color-success)' } : { backgroundColor: 'var(--bg-card)', color: 'var(--text-secondary)', borderColor: 'var(--border-color)' }" class="py-2.5 border-2 rounded-xl text-[11px] font-black uppercase transition-all">💵 Efectivo</button>
                                        <button v-if="comercio?.tiene_mp" @click="formPedido.metodo_pago = 'mercadopago'" :style="formPedido.metodo_pago === 'mercadopago' ? { backgroundColor: '#009ee3', color: '#fff', borderColor: '#009ee3' } : { backgroundColor: 'var(--bg-card)', color: 'var(--text-secondary)', borderColor: 'var(--border-color)' }" class="py-2.5 border-2 rounded-xl text-[11px] font-black uppercase transition-all flex justify-center items-center gap-1">
                                            <span>💳</span> Mercado Pago
                                        </button>
                                    </div>
                                </div>
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

        <button
            v-if="sucursalElegida"
            @click="window.scrollTo({ top: 0, behavior: 'smooth' })"
            class="fixed bottom-6 left-6 z-40 w-10 h-10 rounded-full flex items-center justify-center shadow-lg hover:scale-110 transition-transform border"
            :style="{ backgroundColor: 'var(--bg-elevated)', borderColor: 'var(--border-color)', color: 'var(--text-muted)' }"
            aria-label="Volver arriba"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
        </button>


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
