<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { useVencimientoLote } from '@/Composables/useVencimientoLote';

const props = defineProps({
    lotes: Object,
    sucursales: Array,
    filtros: Object,
});

const { diasRestantes, textoVencimiento, claseBadgeVencimiento, claseFechaBg } = useVencimientoLote();

const search = ref(props.filtros?.search || '');
const filtroEstado = ref(props.filtros?.estado || 'todos');
const filtroVencimiento = ref(props.filtros?.vencimiento || 'todos');
const filtroSucursal = ref(props.filtros?.sucursal_id || '');

const estados = [
    { key: 'todos', label: 'Todos' },
    { key: 'activos', label: 'Activos' },
    { key: 'por_vencer', label: 'Por vencer' },
    { key: 'vencidos', label: 'Vencidos' },
    { key: 'liquidacion', label: 'Liquidacion' },
];

const vencimientos = [
    { key: 'todos', label: 'Todos' },
    { key: 'hoy', label: 'Hoy' },
    { key: '7d', label: '7 dias' },
    { key: '15d', label: '15 dias' },
    { key: '30d', label: '30 dias' },
];

let timeoutBusqueda = null;

watch(search, (valor) => {
    clearTimeout(timeoutBusqueda);
    timeoutBusqueda = setTimeout(() => {
        aplicarFiltros();
    }, 300);
});

watch([filtroEstado, filtroVencimiento, filtroSucursal], () => {
    aplicarFiltros();
});

const aplicarFiltros = () => {
    const params = {};
    if (search.value) params.search = search.value;
    if (filtroEstado.value && filtroEstado.value !== 'todos') params.estado = filtroEstado.value;
    if (filtroVencimiento.value && filtroVencimiento.value !== 'todos') params.vencimiento = filtroVencimiento.value;
    if (filtroSucursal.value) params.sucursal_id = filtroSucursal.value;

    router.get(route('lotes.index'), params, {
        preserveState: true,
        replace: true,
    });
};

const irPagina = (url) => {
    if (!url) return;
    router.get(url, {}, { preserveState: true, replace: true });
};

const badgeEstado = (lote) => {
    const dias = diasRestantes(lote.fecha_vencimiento);
    if (lote.estado_liquidacion) {
        return { label: 'Liquidacion', classes: 'bg-amber-100 text-amber-700 border-amber-200' };
    }
    if (dias !== null && dias < 0) {
        return { label: 'Vencido', classes: 'bg-rose-100 text-rose-700 border-rose-200' };
    }
    if (dias !== null && dias <= 30) {
        return { label: 'Por vencer', classes: 'bg-orange-50 text-orange-600 border-orange-200' };
    }
    return { label: 'Normal', classes: 'bg-emerald-50 text-emerald-600 border border-emerald-200' };
};

const formatearFecha = (fecha) => {
    if (!fecha) return '-';
    return fecha.split('T')[0].split('-').reverse().join('/');
};

const hayFiltrosActivos = () => {
    return search.value || filtroEstado.value !== 'todos' || filtroVencimiento.value !== 'todos' || filtroSucursal.value;
};

const limpiarFiltros = () => {
    search.value = '';
    filtroEstado.value = 'todos';
    filtroVencimiento.value = 'todos';
    filtroSucursal.value = '';
    router.get(route('lotes.index'), {}, { preserveState: true, replace: true });
};
</script>

<template>
    <Head title="Control de Lotes" />

    <AuthenticatedLayout>
        <div class="py-6 px-4 sm:px-6 lg:px-8 bg-slate-50 min-h-screen">

            <!-- Header -->
            <div class="mb-6">
                <h1 class="text-2xl font-black text-slate-800 uppercase tracking-tight">Control de Lotes</h1>
                <div class="h-1 w-16 bg-sky-500 mt-2"></div>
                <p class="text-sm text-slate-500 mt-2 font-medium">Monitoreá la mercadería, vencimientos y liquidaciones.</p>
            </div>

            <!-- Filtros -->
            <div class="flex flex-col lg:flex-row gap-3 mb-6">
                <!-- Buscador -->
                <div class="relative flex-1 max-w-md">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                        <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                        </svg>
                    </div>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Buscar por producto o codigo de barras..."
                        class="w-full pl-10 pr-4 py-2.5 text-sm border border-slate-200 rounded-xl bg-white text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent transition-all"
                    />
                </div>

                <!-- Sucursal -->
                <select
                    v-if="sucursales && sucursales.length > 1"
                    v-model="filtroSucursal"
                    class="px-3 py-2.5 text-sm border border-slate-200 rounded-xl bg-white text-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-500 min-w-[180px]"
                >
                    <option value="">Todas las sucursales</option>
                    <option v-for="s in sucursales" :key="s.id" :value="s.id">{{ s.nombre }}</option>
                </select>

                <!-- Limpiar filtros -->
                <button
                    v-if="hayFiltrosActivos()"
                    @click="limpiarFiltros"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-red-50 text-red-600 border border-red-100 text-xs font-bold hover:bg-red-100 hover:text-red-700 transition-colors whitespace-nowrap"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Limpiar
                </button>
            </div>

            <!-- Filtros de estado -->
            <div class="bg-white border border-slate-200/60 rounded-2xl p-2 shadow-sm mb-4 flex flex-wrap gap-1">
                <button
                    v-for="f in estados"
                    :key="f.key"
                    @click="filtroEstado = f.key"
                    class="flex-1 sm:flex-none inline-flex justify-center items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all"
                    :class="filtroEstado === f.key
                        ? 'bg-slate-800 text-white shadow-md'
                        : 'bg-transparent text-slate-500 hover:bg-slate-100 hover:text-slate-800'"
                >
                    {{ f.label }}
                </button>
            </div>

            <!-- Filtros de vencimiento -->
            <div class="bg-white border border-slate-200/60 rounded-2xl p-2 shadow-sm mb-6 flex flex-wrap gap-1">
                <span class="self-center text-[10px] font-black text-slate-400 uppercase tracking-widest px-2 hidden sm:block">Vence:</span>
                <button
                    v-for="v in vencimientos"
                    :key="v.key"
                    @click="filtroVencimiento = v.key"
                    class="flex-1 sm:flex-none inline-flex justify-center items-center px-3 py-1.5 rounded-lg text-[11px] font-bold transition-all"
                    :class="filtroVencimiento === v.key
                        ? 'bg-sky-600 text-white shadow-md'
                        : 'bg-transparent text-slate-500 hover:bg-slate-100 hover:text-slate-800'"
                >
                    {{ v.label }}
                </button>
            </div>

            <!-- Tabla -->
            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[10px] uppercase tracking-widest text-slate-400 bg-slate-50 border-b border-slate-200">
                                <th class="p-4 font-black w-[35%]">Producto</th>
                                <th class="p-4 font-black">Sucursal</th>
                                <th class="p-4 font-black text-center w-20">Stock</th>
                                <th class="p-4 font-black text-center">Vencimiento</th>
                                <th class="p-4 font-black text-center">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="lotes.data.length === 0">
                                <td colspan="5" class="p-12 text-center">
                                    <div class="flex flex-col items-center gap-3">
                                        <div class="w-16 h-16 bg-slate-100 rounded-2xl flex items-center justify-center">
                                            <svg class="w-8 h-8 text-slate-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m6 4.125l2.25 2.25m0 0l2.25 2.25M12 13.875l2.25-2.25M12 13.875l-2.25 2.25M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                                            </svg>
                                        </div>
                                        <p class="text-slate-400 font-bold text-sm">No se encontraron lotes</p>
                                        <p class="text-slate-300 text-xs">Intenta ajustar los filtros de busqueda</p>
                                    </div>
                                </td>
                            </tr>
                            <tr
                                v-for="lote in lotes.data"
                                :key="lote.id"
                                class="hover:bg-slate-50/80 transition-colors"
                                :class="claseFechaBg(lote.fecha_vencimiento)"
                            >
                                <td class="p-4">
                                    <p class="font-bold text-slate-800 text-sm">{{ lote.producto?.nombre }}</p>
                                    <p class="text-[10px] font-mono text-slate-400 mt-0.5">{{ lote.producto?.codigo_barras }}</p>
                                </td>
                                <td class="p-4 text-xs font-bold text-slate-500">{{ lote.sucursal?.nombre }}</td>
                                <td class="p-4 text-center">
                                    <span class="font-black text-sky-600 text-sm">{{ Number(lote.stock_actual) }}</span>
                                </td>
                                <td class="p-4 text-center">
                                    <div class="flex flex-col items-center gap-0.5">
                                        <span
                                            :class="claseBadgeVencimiento(lote.fecha_vencimiento)"
                                            class="px-2.5 py-1 rounded-lg border text-xs font-black whitespace-nowrap"
                                        >
                                            {{ formatearFecha(lote.fecha_vencimiento) }}
                                        </span>
                                        <span
                                            :class="claseBadgeVencimiento(lote.fecha_vencimiento)"
                                            class="text-[10px] font-bold"
                                        >
                                            {{ textoVencimiento(lote.fecha_vencimiento) }}
                                        </span>
                                    </div>
                                </td>
                                <td class="p-4 text-center">
                                    <span
                                        :class="badgeEstado(lote).classes"
                                        class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-widest border whitespace-nowrap"
                                    >
                                        {{ badgeEstado(lote).label }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div v-if="lotes.last_page > 1" class="px-4 py-3 border-t border-slate-100 flex items-center justify-between">
                    <p class="text-xs text-slate-400 font-medium">
                        Mostrando {{ lotes.from }} a {{ lotes.to }} de {{ lotes.total }} lotes
                    </p>
                    <div class="flex items-center gap-1">
                        <button
                            @click="irPagina(lotes.prev_page_url)"
                            :disabled="!lotes.prev_page_url"
                            class="px-3 py-1.5 text-xs font-bold rounded-lg transition-all"
                            :class="lotes.prev_page_url ? 'text-slate-600 hover:bg-slate-100' : 'text-slate-300 cursor-not-allowed'"
                        >
                            Anterior
                        </button>
                        <template v-for="page in lotes.last_page" :key="page">
                            <button
                                v-if="page === 1 || page === lotes.last_page || (page >= lotes.current_page - 1 && page <= lotes.current_page + 1)"
                                @click="irPagina(lotes.path + '?page=' + page)"
                                class="w-8 h-8 text-xs font-bold rounded-lg transition-all"
                                :class="page === lotes.current_page ? 'bg-sky-600 text-white' : 'text-slate-500 hover:bg-slate-100'"
                            >
                                {{ page }}
                            </button>
                            <span
                                v-else-if="page === lotes.current_page - 2 || page === lotes.current_page + 2"
                                class="text-slate-300 text-xs px-1"
                            >
                                ...
                            </span>
                        </template>
                        <button
                            @click="irPagina(lotes.next_page_url)"
                            :disabled="!lotes.next_page_url"
                            class="px-3 py-1.5 text-xs font-bold rounded-lg transition-all"
                            :class="lotes.next_page_url ? 'text-slate-600 hover:bg-slate-100' : 'text-slate-300 cursor-not-allowed'"
                        >
                            Siguiente
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; height: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
</style>
