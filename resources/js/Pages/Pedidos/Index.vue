<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    pedidos: Array,
    sucursal: Object, 
});

// ─── Control de Roles (Superadmin vs Cajero) ──────────────────────────────────
const page = usePage();
const isSuperAdmin = computed(() => {
    const user = page.props.auth.user;
    return user && (user.role === 'Superadmin' || user.role === 'superadmin' || user.id === 1);
});

// ─── Lógica de Máquina de Estados (Bloqueos) ──────────────────────────────────
const canChangeTo = (currentState, targetState) => {
    if (isSuperAdmin.value) return true;
    if (currentState === 'cancelado') return false;

    // 🔥 ACÁ ESTÁN LAS PALABRAS CORRECTAS
    const flujo = ['nuevo', 'preparando', 'en_camino', 'entregado'];
    const actualIdx = flujo.indexOf(currentState);
    const targetIdx = flujo.indexOf(targetState);

    return targetIdx >= actualIdx;
};

// Bloqueo estricto para pagos automáticos de MercadoPago
const esPagoBloqueado = (pedido) => {
    if (isSuperAdmin.value) return false;
    return pedido.metodo_pago === 'mercadopago' && pedido.estado_pago === 'pagado';
};

// ─── Formato de dinero ────────────────────────────────────────────────────────
const formatDinero = (monto) =>
    new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS', maximumFractionDigits: 0 }).format(monto);

// ─── Tiempo transcurrido (Reactivo) ───────────────────────────────────────────
const ahora = ref(Date.now());
let timer = null;

onMounted(() => { timer = setInterval(() => { ahora.value = Date.now(); }, 30000); });
onUnmounted(() => clearInterval(timer));

const tiempoTranscurrido = (fechaStr) => {
    const diff = Math.floor((ahora.value - new Date(fechaStr).getTime()) / 60000);
    if (diff < 1)  return 'Ahora';
    if (diff < 60) return `Hace ${diff} min`;
    const h = Math.floor(diff / 60);
    const m = diff % 60;
    return m > 0 ? `Hace ${h}h ${m}m` : `Hace ${h}h`;
};

const urgencia = (fechaStr) => {
    const diff = Math.floor((ahora.value - new Date(fechaStr).getTime()) / 60000);
    if (diff >= 30) return 'critico';
    if (diff >= 15) return 'alerta';
    return 'ok';
};

// ─── Filtros ──────────────────────────────────────────────────────────────────
const filtroActivo = ref('todos');
const filtros = [
    { key: 'todos',      label: 'Todos'          },
    { key: 'nuevo',      label: 'Nuevos'         },
    { key: 'preparando', label: 'En preparación' }, // 🔥 Corregido
    { key: 'en_camino',  label: 'En camino'      }, // 🔥 Corregido
    { key: 'entregado',  label: 'Entregados'     },
];

const pedidosFiltrados = computed(() => {
    if (!props.pedidos) return [];
    if (filtroActivo.value === 'todos') return props.pedidos;
    return props.pedidos.filter(p => p.estado_pedido === filtroActivo.value);
});

const contarEstado = (key) => props.pedidos?.filter(p => p.estado_pedido === key).length ?? 0;

// ─── Métricas ─────────────────────────────────────────────────────────────────
const totalPedidos   = computed(() => props.pedidos?.length ?? 0);
const pendientesPago = computed(() => props.pedidos?.filter(p => p.estado_pago === 'pendiente').length ?? 0);
const facturadoHoy   = computed(() => props.pedidos?.reduce((acc, p) => acc + Number(p.total), 0) ?? 0);

// ─── Detalle expandido ────────────────────────────────────────────────────────
const pedidoExpandido = ref(null);
const toggleDetalle = (id) => {
    pedidoExpandido.value = pedidoExpandido.value === id ? null : id;
};

// ─── Acciones ─────────────────────────────────────────────────────────────────
const cambiarEstado = (id, nuevoEstado) => {
    router.patch(route('pedidos.estado', id), { estado_pedido: nuevoEstado }, { preserveScroll: true });
};
const cambiarPago = (id, nuevoPago) => {
    router.patch(route('pedidos.pago', id), { estado_pago: nuevoPago }, { preserveScroll: true });
};

const anularPedido = (id) => {
    if(confirm('⚠️ ¿Estás seguro de que querés anular este pedido por completo?')) {
        router.patch(route('pedidos.estado', id), { estado_pedido: 'cancelado' }, { preserveScroll: true });
    }
};

// ─── Clases dinámicas ─────────────────────────────────────────────────────────
const borderEstado = (estado) => ({
    'border-l-rose-500':    estado === 'nuevo',
    'border-l-amber-500':   estado === 'preparando', // 🔥 Corregido
    'border-l-sky-500':     estado === 'en_camino',  // 🔥 Corregido
    'border-l-emerald-500': estado === 'entregado',
    'border-l-slate-800':   estado === 'cancelado',
});

const tiempoBadgeClass = (fechaStr) => ({
    'ok':      'bg-slate-100 text-slate-600',
    'alerta':  'bg-amber-100 text-amber-700',
    'critico': 'bg-rose-100 text-rose-700 font-bold animate-pulse',
}[urgencia(fechaStr)]);

const pagoSelectClass = (estado) =>
    estado === 'pagado'
        ? 'text-emerald-700 bg-emerald-50 border-emerald-200'
        : 'text-rose-600 bg-rose-50 border-rose-200 font-bold';

const prepSelectClass = (estado) => ({
    'nuevo':      'text-rose-700 bg-rose-50 border-rose-200 font-bold',
    'preparando': 'text-amber-700 bg-amber-50 border-amber-200 font-bold', // 🔥 Corregido
    'en_camino':  'text-sky-700 bg-sky-50 border-sky-200 font-bold',       // 🔥 Corregido
    'entregado':  'text-emerald-700 bg-emerald-50 border-emerald-200',
    'cancelado':  'text-slate-500 bg-slate-100 border-slate-200 line-through',
}[estado] ?? 'text-slate-600 bg-slate-50 border-slate-200');
</script>

<template>
    <Head title="Monitor de Pedidos" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Panel de Control</p>
                <h2 class="text-xl font-black text-slate-800 mt-0.5">Monitor de Pedidos Web</h2>
            </div>
        </template>

        <div class="py-8 bg-slate-50 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <div class="grid grid-cols-1 gap-4" :class="isSuperAdmin ? 'sm:grid-cols-3' : 'sm:grid-cols-2'">
                    <div class="bg-white border border-slate-200/60 rounded-2xl px-6 py-5 shadow-sm flex items-center gap-5 transition-all hover:shadow-md">
                        <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center shrink-0">
                            <svg class="w-6 h-6 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Órdenes Totales</p>
                            <p class="text-3xl font-black text-slate-800 mt-1">{{ totalPedidos }}</p>
                        </div>
                    </div>

                    <div class="bg-white border border-slate-200/60 rounded-2xl px-6 py-5 shadow-sm flex items-center gap-5 transition-all hover:shadow-md">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center shrink-0" :class="pendientesPago > 0 ? 'bg-amber-100' : 'bg-slate-100'">
                            <svg class="w-6 h-6" :class="pendientesPago > 0 ? 'text-amber-600' : 'text-slate-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Pendientes de Pago</p>
                            <p class="text-3xl font-black mt-1" :class="pendientesPago > 0 ? 'text-amber-600' : 'text-slate-800'">{{ pendientesPago }}</p>
                        </div>
                    </div>

                    <div v-if="isSuperAdmin" class="bg-white border border-slate-200/60 rounded-2xl px-6 py-5 shadow-sm flex items-center gap-5 transition-all hover:shadow-md">
                        <div class="w-12 h-12 rounded-full bg-emerald-50 flex items-center justify-center shrink-0">
                            <svg class="w-6 h-6 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08-.402-2.599-1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Facturado Hoy</p>
                            <p class="text-3xl font-black text-emerald-600 mt-1">{{ formatDinero(facturadoHoy) }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white border border-slate-200/60 rounded-2xl p-2 shadow-sm flex flex-wrap gap-1">
                    <button
                        v-for="f in filtros"
                        :key="f.key"
                        @click="filtroActivo = f.key"
                        class="flex-1 sm:flex-none inline-flex justify-center items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all"
                        :class="filtroActivo === f.key
                            ? 'bg-slate-800 text-white shadow-md'
                            : 'bg-transparent text-slate-500 hover:bg-slate-100 hover:text-slate-800'"
                    >
                        {{ f.label }}
                        <span
                            class="rounded-md px-1.5 py-0.5 text-[10px] font-black"
                            :class="filtroActivo === f.key ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-600'"
                        >
                            {{ f.key === 'todos' ? totalPedidos : contarEstado(f.key) }}
                        </span>
                    </button>
                </div>

                <div class="flex flex-col gap-4">

                    <div v-if="pedidosFiltrados.length === 0" class="py-20 text-center bg-white rounded-2xl border border-slate-200/60 shadow-sm">
                        <div class="flex flex-col items-center gap-3">
                            <div class="w-16 h-16 rounded-full bg-slate-50 flex items-center justify-center border border-slate-100">
                                <span class="text-2xl">🍽️</span>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-slate-600">No hay pedidos{{ filtroActivo !== 'todos' ? ' en este estado' : ' en curso' }}</p>
                                <p class="text-xs text-slate-400 mt-1">Cuando los clientes compren desde la web, aparecerán aquí.</p>
                            </div>
                        </div>
                    </div>

                    <template v-for="pedido in pedidosFiltrados" :key="pedido.id">
                        
                        <div class="bg-white border-y border-r border-l-4 border-y-slate-200/60 border-r-slate-200/60 rounded-xl shadow-sm hover:shadow-md transition-shadow flex flex-col overflow-hidden"
                             :class="borderEstado(pedido.estado_pedido)">
                             
                            <div class="p-5 flex flex-col xl:flex-row justify-between xl:items-center gap-6" :class="{'opacity-60': pedido.estado_pedido === 'cancelado'}">

                                <div class="flex flex-col md:flex-row w-full xl:w-auto flex-1 items-start gap-4 md:gap-0">
                                    
                                    <div class="w-full md:w-1/4 flex flex-col gap-2 md:pr-5">
                                        <span class="font-mono text-sm font-black text-slate-700 w-fit">
                                            #{{ pedido.id }}
                                        </span>
                                        <span v-if="pedido.estado_pedido !== 'cancelado'" class="inline-flex items-center text-[10px] font-bold px-2.5 py-1 rounded-md border w-fit" :class="tiempoBadgeClass(pedido.created_at)">
                                            ⏱ {{ tiempoTranscurrido(pedido.created_at) }}
                                        </span>
                                        <span v-else class="inline-flex items-center text-[10px] font-bold px-2.5 py-1 rounded-md border bg-slate-100 text-slate-500 w-fit">
                                            🚫 Cancelado
                                        </span>
                                    </div>

                                    <div class="w-full md:w-1/4 flex flex-col gap-1.5 md:px-5 md:border-l border-slate-100">
                                        <p class="text-sm font-bold text-slate-800">{{ pedido.cliente_nombre }}</p>
                                        <div class="flex items-center gap-1.5 text-xs text-slate-500">
                                            <svg class="w-3.5 h-3.5 shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                            <span class="truncate">{{ pedido.cliente_telefono }}</span>
                                        </div>
                                    </div>

                                    <div class="w-full md:w-1/4 flex flex-col gap-1.5 md:px-5 md:border-l border-slate-100">
                                        <span class="inline-flex items-center gap-1.5 text-[10px] font-bold px-2 py-0.5 rounded-md border w-fit"
                                            :class="pedido.cliente_direccion === 'Retiro en local' ? 'bg-sky-50 text-sky-700 border-sky-100' : 'bg-amber-50 text-amber-700 border-amber-100'">
                                            <span>{{ pedido.cliente_direccion === 'Retiro en local' ? '🏬' : '🛵' }}</span>
                                            {{ pedido.cliente_direccion === 'Retiro en local' ? 'Retira en local' : 'Envío' }}
                                        </span>
                                        <p v-if="pedido.cliente_direccion !== 'Retiro en local'" class="text-[11px] font-medium text-slate-500 truncate w-full" :title="pedido.cliente_direccion">
                                            {{ pedido.cliente_direccion }}
                                        </p>
                                    </div>

                                    <div class="w-full md:w-1/4 flex flex-col gap-1.5 md:pl-5 md:border-l border-slate-100">
                                        <div class="text-base font-black text-slate-800 tabular-nums" :class="{'line-through text-slate-400': pedido.estado_pedido === 'cancelado'}">
                                            {{ formatDinero(pedido.total) }}
                                        </div>
                                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="pedido.metodo_pago === 'mercadopago' ? 'bg-sky-400' : 'bg-emerald-400'"></span>
                                            <span class="truncate">{{ pedido.metodo_pago }}</span>
                                        </p>
                                    </div>
                                </div>

                                <div class="flex flex-row items-end justify-start sm:justify-end gap-2.5 w-full xl:w-auto shrink-0 border-t xl:border-t-0 border-slate-100 pt-4 xl:pt-0">
                                    
                                    <div class="w-[125px]">
                                        <label class="block text-[8px] font-bold text-slate-600 uppercase tracking-widest mb-1 pl-1">ORDEN</label>
                                        <select v-model="pedido.estado_pedido" @change="cambiarEstado(pedido.id, $event.target.value)" :disabled="pedido.estado_pedido === 'cancelado' && !isSuperAdmin" class="w-full text-[11px] font-sans font-bold leading-relaxed border border-slate-200 rounded-lg shadow-sm cursor-pointer focus:ring-2 focus:ring-slate-200 transition-all disabled:opacity-50 disabled:cursor-not-allowed" :class="prepSelectClass(pedido.estado_pedido)" style="padding: 6px 24px 6px 10px;">
                                            <option value="nuevo" :disabled="!canChangeTo(pedido.estado_pedido, 'nuevo')">🔴 Nueva</option>
                                            
                                            <option value="preparando" :disabled="!canChangeTo(pedido.estado_pedido, 'preparando')">🍳 En prep.</option>
                                            <option value="en_camino" :disabled="!canChangeTo(pedido.estado_pedido, 'en_camino')">🛵 En camino</option>
                                            <option value="entregado" :disabled="!canChangeTo(pedido.estado_pedido, 'entregado')">✅ Entregado</option>
                                            <option v-if="pedido.estado_pedido === 'cancelado' || isSuperAdmin" value="cancelado" :disabled="!isSuperAdmin">🚫 Cancelado</option>
                                        </select>
                                    </div>

                                    <div class="w-[125px]">
                                        <label class="block text-[8px] font-bold text-slate-600 uppercase tracking-widest mb-1 pl-1">PAGO</label>
                                        <select v-model="pedido.estado_pago" @change="cambiarPago(pedido.id, $event.target.value)" :disabled="(pedido.estado_pedido === 'cancelado' || esPagoBloqueado(pedido))" class="w-full text-[11px] font-sans font-bold leading-relaxed border border-slate-200 rounded-lg shadow-sm cursor-pointer focus:ring-2 focus:ring-slate-200 transition-all disabled:opacity-50 disabled:cursor-not-allowed" :class="pagoSelectClass(pedido.estado_pago)" style="padding: 6px 24px 6px 10px;">
                                            <option value="pendiente">⏳ A pagar</option>
                                            <option value="pagado">💵 Pagado</option>
                                        </select>
                                    </div>

                                     <a v-if="pedido.cliente_direccion !== 'Retiro en local'" :href="`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(pedido.cliente_direccion)}`" target="_blank" class="inline-flex justify-center items-center gap-1.5 text-[11px] font-sans font-bold rounded-lg border text-slate-600 border-slate-200 bg-white hover:bg-slate-50 hover:text-slate-900 transition-all active:scale-95" style="padding: 6px 12px;">
                                         Ver Envío
                                         <svg class="w-3.5 h-3.5 shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                     </a>

                                     <button @click="toggleDetalle(pedido.id)" class="inline-flex justify-center items-center gap-1.5 text-[11px] font-sans font-bold rounded-lg border transition-all active:scale-95" :class="pedidoExpandido === pedido.id ? 'bg-slate-800 text-white border-slate-800 shadow-md' : 'text-slate-600 border-slate-200 bg-white hover:bg-slate-50 hover:text-slate-900'" style="padding: 6px 12px;">
                                        {{ pedidoExpandido === pedido.id ? 'Ocultar' : 'Detalles' }}
                                        <svg class="w-3.5 h-3.5 transition-transform duration-200 shrink-0" :class="{'rotate-180': pedidoExpandido === pedido.id}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                    
                                </div>
                            </div>

                            <div v-show="pedidoExpandido === pedido.id" class="border-t border-slate-100 bg-slate-50 px-6 py-5">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    
                                    <div>
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">Resumen de Productos</p>
                                        <ul class="space-y-2">
                                            <li v-for="item in pedido.items" :key="item.id" class="flex justify-between items-center bg-white border border-slate-200/60 rounded-lg px-3 py-2 shadow-sm">
                                                <div class="flex items-center gap-3">
                                                    <span class="text-[11px] font-black text-slate-500 bg-slate-100 px-2 py-0.5 rounded tabular-nums shrink-0">
                                                        {{ item.cantidad }}x
                                                    </span>
                                                    <span class="text-sm font-bold text-slate-700">
                                                        {{ item.producto?.nombre ?? 'Producto no encontrado' }}
                                                    </span>
                                                </div>
                                                <span class="text-sm font-black text-slate-800 tabular-nums shrink-0">
                                                    {{ formatDinero(item.subtotal) }}
                                                </span>
                                            </li>
                                        </ul>
                                    </div>

                                    <div class="flex flex-col">
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">Información Adicional</p>
                                        
                                        <div v-if="pedido.notas" class="mb-3 p-3 bg-amber-50/50 border border-amber-200 rounded-lg flex gap-3">
                                            <span class="text-lg shrink-0">💬</span>
                                            <div>
                                                <p class="text-[10px] font-bold text-amber-700 uppercase tracking-widest mb-0.5">Nota del cliente</p>
                                                <p class="text-sm font-medium text-amber-900 leading-snug">{{ pedido.notas }}</p>
                                            </div>
                                        </div>
                                        <div v-else class="mb-3 p-3 bg-slate-100/50 border border-slate-200 rounded-lg flex items-center gap-3">
                                            <span class="text-slate-400 shrink-0">📝</span>
                                            <p class="text-xs font-medium text-slate-500">Sin notas adicionales.</p>
                                        </div>

                                        <div class="bg-white border border-slate-200/60 rounded-lg p-3 shadow-sm mb-4">
                                            <div class="flex justify-between text-xs text-slate-500 mb-1">
                                                <span>Subtotal:</span>
                                                <span class="font-bold text-slate-700">{{ formatDinero(pedido.subtotal) }}</span>
                                            </div>
                                            <div class="flex justify-between text-xs text-slate-500 mb-2 pb-2 border-b border-slate-100">
                                                <span>Costo de envío:</span>
                                                <span class="font-bold text-slate-700">{{ formatDinero(pedido.costo_envio) }}</span>
                                            </div>
                                            <div class="flex justify-between items-end mt-2 pt-2 border-t border-slate-100">
                                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">A COBRAR</span>
                                                <span class="text-xl font-black text-slate-800">{{ formatDinero(pedido.total) }}</span>
                                            </div>
                                        </div>

                                        <div v-if="isSuperAdmin" class="mt-2 p-4 bg-slate-800 text-slate-300 rounded-xl shadow-inner border border-slate-700">
                                            <div class="flex justify-between items-center mb-3">
                                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest flex items-center gap-1.5">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                                    Auditoría Interna
                                                </p>
                                                
                                                <button v-if="pedido.estado_pedido !== 'cancelado'" @click="anularPedido(pedido.id)" class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-1 rounded text-rose-400 hover:bg-rose-500/20 border border-transparent hover:border-rose-500/50 transition-colors">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                    Anular Forzosamente
                                                </button>
                                            </div>

                                            <div class="grid grid-cols-2 gap-y-3 gap-x-4 text-xs">
                                                <div>
                                                    <p class="text-slate-500 font-bold mb-0.5 text-[9px] uppercase">Creado</p>
                                                    <p class="font-medium text-slate-100">{{ new Date(pedido.created_at).toLocaleTimeString('es-AR', {hour: '2-digit', minute:'2-digit'}) }} hs</p>
                                                </div>
                                                <div>
                                                    <p class="text-slate-500 font-bold mb-0.5 text-[9px] uppercase">Últ. Actualización</p>
                                                    <p class="font-medium text-slate-100">{{ new Date(pedido.updated_at || pedido.created_at).toLocaleTimeString('es-AR', {hour: '2-digit', minute:'2-digit'}) }} hs</p>
                                                </div>
                                                <div class="col-span-2">
                                                    <p class="text-slate-500 font-bold mb-0.5 text-[9px] uppercase">ID Sistema / Sucursal</p>
                                                    <p class="font-mono text-slate-200">TX-{{ pedido.id.toString().padStart(6, '0') }} • {{ sucursal?.nombre ?? 'Sucursal Principal' }}</p>
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
select:focus { box-shadow: none; outline: none; }
</style>