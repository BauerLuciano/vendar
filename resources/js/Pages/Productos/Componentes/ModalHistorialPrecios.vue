<template>
    <div v-if="mostrar" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-4xl flex flex-col max-h-[88vh] border border-slate-100">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center shrink-0">
                <div>
                    <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Historial de precios</p>
                    <h3 class="text-base font-semibold text-slate-700 mt-0.5">
                        {{ producto.nombre }}
                        <span class="text-slate-400 font-normal text-sm">({{ producto.unidad_medida }})</span>
                    </h3>
                </div>
                <button @click="$emit('cerrar')" class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 text-lg leading-none">✕</button>
            </div>

            <div class="px-6 py-3 border-b border-slate-100 flex flex-wrap items-center gap-3 shrink-0">
                <div>
                    <label class="block text-[10px] font-medium uppercase tracking-widest text-slate-400 mb-1">Desde</label>
                    <input type="date" v-model="filtros.fecha_desde" :max="filtros.fecha_hasta"
                        class="px-3 py-1.5 text-xs border border-slate-200 rounded-lg bg-slate-50 text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                </div>
                <div>
                    <label class="block text-[10px] font-medium uppercase tracking-widest text-slate-400 mb-1">Hasta</label>
                    <input type="date" v-model="filtros.fecha_hasta" :min="filtros.fecha_desde"
                        class="px-3 py-1.5 text-xs border border-slate-200 rounded-lg bg-slate-50 text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                </div>
                <button @click="pagina = 1; cargarHistorial()"
                    class="mt-5 px-3 py-1.5 text-[10px] font-bold uppercase tracking-widest text-blue-600 bg-blue-50 border border-blue-100 rounded-lg hover:bg-blue-100 transition-colors">
                    Filtrar
                </button>
                <button v-if="filtros.fecha_desde || filtros.fecha_hasta" @click="limpiarFiltros"
                    class="mt-5 px-3 py-1.5 text-[10px] font-bold uppercase tracking-widest text-slate-500 bg-slate-100 border border-slate-200 rounded-lg hover:bg-slate-200 transition-colors">
                    Limpiar
                </button>
                <span v-if="paginacion.total > 0" class="mt-5 ml-auto text-[10px] font-medium text-slate-400">
                    {{ paginacion.total }} registros
                </span>
            </div>

            <div class="overflow-y-auto flex-1 custom-scrollbar">
                <table class="w-full text-left">
                    <thead class="sticky top-0 z-10">
                        <tr class="bg-slate-50 border-b border-slate-100 text-[10px] font-semibold text-slate-400 uppercase tracking-widest">
                            <th class="px-5 py-3.5">Fecha</th>
                            <th class="px-5 py-3.5 text-center">Costo anterior</th>
                            <th class="px-5 py-3.5 text-center">Costo nuevo</th>
                            <th class="px-5 py-3.5 text-center">Venta anterior</th>
                            <th class="px-5 py-3.5 text-center">Venta nuevo</th>
                            <th class="px-5 py-3.5">Origen</th>
                            <th class="px-5 py-3.5">Usuario</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-if="registros.length === 0">
                            <td colspan="7" class="py-16 text-center text-sm text-slate-400">Sin registros de cambios de precio.</td>
                        </tr>
                        <tr v-for="reg in registros" :key="reg.id" class="hover:bg-slate-50 bg-white">
                            <td class="px-5 py-3.5">
                                <p class="text-xs font-medium text-slate-700">{{ formatDate(reg.created_at) }}</p>
                                <p class="text-[10px] text-slate-400 mt-0.5">{{ formatTime(reg.created_at) }}</p>
                            </td>
                            <td class="px-5 py-3.5 text-center font-mono text-xs text-slate-500">
                                $ {{ formatMoney(reg.costo_anterior) }}
                            </td>
                            <td class="px-5 py-3.5 text-center font-mono text-xs font-semibold"
                                :class="reg.costo_nuevo > reg.costo_anterior ? 'text-red-600' : reg.costo_nuevo < reg.costo_anterior ? 'text-emerald-600' : 'text-slate-700'">
                                $ {{ formatMoney(reg.costo_nuevo) }}
                            </td>
                            <td class="px-5 py-3.5 text-center font-mono text-xs text-slate-500">
                                $ {{ formatMoney(reg.precio_venta_anterior) }}
                            </td>
                            <td class="px-5 py-3.5 text-center font-mono text-xs font-semibold"
                                :class="reg.precio_venta_nuevo > reg.precio_venta_anterior ? 'text-red-600' : reg.precio_venta_nuevo < reg.precio_venta_anterior ? 'text-emerald-600' : 'text-slate-700'">
                                $ {{ formatMoney(reg.precio_venta_nuevo) }}
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full"
                                    :class="origenClass(reg.origen_tipo)">
                                    {{ reg.origen_tipo }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-xs text-slate-600">{{ reg.usuario }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="paginacion.last_page > 1" class="px-6 py-3 border-t border-slate-100 flex items-center justify-between shrink-0">
                <span class="text-xs text-slate-400 font-medium">
                    Página {{ paginacion.current_page }} de {{ paginacion.last_page }}
                </span>
                <div class="flex gap-1">
                    <button @click="cambiarPagina(paginacion.current_page - 1)"
                        :disabled="paginacion.current_page <= 1"
                        class="px-3 py-1.5 text-xs font-bold rounded-lg border transition-colors"
                        :class="paginacion.current_page <= 1 ? 'text-slate-300 border-slate-100 cursor-not-allowed' : 'text-slate-600 border-slate-200 hover:bg-slate-50'">
                        ← Anterior
                    </button>
                    <button @click="cambiarPagina(paginacion.current_page + 1)"
                        :disabled="paginacion.current_page >= paginacion.last_page"
                        class="px-3 py-1.5 text-xs font-bold rounded-lg border transition-colors"
                        :class="paginacion.current_page >= paginacion.last_page ? 'text-slate-300 border-slate-100 cursor-not-allowed' : 'text-slate-600 border-slate-200 hover:bg-slate-50'">
                        Siguiente →
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background-color: #e2e8f0; border-radius: 20px; }
.custom-scrollbar { scrollbar-width: thin; scrollbar-color: #e2e8f0 transparent; }
</style>

<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
    mostrar: Boolean,
    producto: Object,
});

const emit = defineEmits(['cerrar']);

const registros = ref([]);
const pagina = ref(1);
const filtros = ref({ fecha_desde: '', fecha_hasta: '' });
const paginacion = ref({ current_page: 1, last_page: 1, from: 0, to: 0, total: 0 });

watch(() => props.mostrar, (val) => {
    if (val && props.producto) {
        registros.value = [];
        pagina.value = 1;
        filtros.value = { fecha_desde: '', fecha_hasta: '' };
        cargarHistorial();
    }
});

const cargarHistorial = async () => {
    if (!props.producto) return;
    try {
        const params = { page: pagina.value };
        if (filtros.value.fecha_desde) params.fecha_desde = filtros.value.fecha_desde;
        if (filtros.value.fecha_hasta) params.fecha_hasta = filtros.value.fecha_hasta;
        const respuesta = await axios.get(route('productos.historial-precios', props.producto.id), { params });
        registros.value = respuesta.data.data;
        paginacion.value = {
            current_page: respuesta.data.current_page,
            last_page: respuesta.data.last_page,
            from: respuesta.data.from,
            to: respuesta.data.to,
            total: respuesta.data.total,
        };
    } catch (error) {
        Swal.fire('Error', 'No se pudo cargar el historial de precios', 'error');
    }
};

const cambiarPagina = (page) => {
    pagina.value = page;
    cargarHistorial();
};

const limpiarFiltros = () => {
    filtros.value = { fecha_desde: '', fecha_hasta: '' };
    pagina.value = 1;
    cargarHistorial();
};

const formatDate = (dateStr) => {
    return new Date(dateStr).toLocaleDateString('es-AR');
};

const formatTime = (dateStr) => {
    return new Date(dateStr).toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
};

const formatMoney = (value) => {
    if (value === null || value === undefined) return '0,00';
    return new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value);
};

const origenClass = (origen) => {
    const map = {
        'Alta de producto': 'bg-blue-50 text-blue-700',
        'Edición manual': 'bg-amber-50 text-amber-700',
        'Importación Excel': 'bg-purple-50 text-purple-700',
        'Ingreso Manual': 'bg-emerald-50 text-emerald-700',
        'Recepción OC': 'bg-indigo-50 text-indigo-700',
    };
    return map[origen] || 'bg-slate-50 text-slate-600';
};
</script>
