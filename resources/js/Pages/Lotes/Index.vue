<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    lotes: Object
});

const formatearFecha = (fecha) => {
    if (!fecha) return '-';
    const soloFecha = fecha.split('T')[0]; 
    return soloFecha.split('-').reverse().join('/');
};

// Función para calcular si está en rojo (vence en menos de 7 días)
const esUrgente = (fecha) => {
    const hoy = new Date();
    const vencimiento = new Date(fecha);
    const diferenciaDias = Math.ceil((vencimiento - hoy) / (1000 * 60 * 60 * 24));
    return diferenciaDias <= 7;
};

const formatearCantidad = (cantidad) => {
    return Number(cantidad); 
};
</script>

<template>
    <Head title="Control de Lotes y Vencimientos" />

    <AuthenticatedLayout>
        <div class="py-6 px-4 sm:px-6 lg:px-8 bg-slate-50 min-h-screen">
            
            <div class="mb-8">
                <h1 class="text-2xl font-black text-slate-800 uppercase tracking-tight">Control de Vencimientos</h1>
                <div class="h-1 w-16 bg-sky-500 mt-2"></div>
                <p class="text-sm text-slate-500 mt-2 font-medium">Monitoreá la mercadería próxima a caducar y su estado de liquidación.</p>
            </div>

            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[10px] uppercase tracking-widest text-slate-400 bg-slate-50 border-b border-slate-200">
                                <th class="p-4 font-black">Producto</th>
                                <th class="p-4 font-black">Sucursal</th>
                                <th class="p-4 font-black text-center">Vencimiento</th>
                                <th class="p-4 font-black text-center">Stock Actual</th>
                                <th class="p-4 font-black text-center">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="lotes.data.length === 0">
                                <td colspan="5" class="p-8 text-center text-slate-400 font-bold italic">
                                    No hay lotes activos con fecha de vencimiento en el sistema.
                                </td>
                            </tr>
                            <tr v-for="lote in lotes.data" :key="lote.id" class="hover:bg-slate-50 transition-colors">
                                <td class="p-4">
                                    <p class="font-bold text-slate-800 text-sm">{{ lote.producto?.nombre }}</p>
                                    <p class="text-[10px] font-mono text-slate-400">{{ lote.producto?.codigo_barras }}</p>
                                </td>
                                <td class="p-4 text-xs font-bold text-slate-500">{{ lote.sucursal?.nombre }}</td>
                                
                                <td class="p-4 text-center">
                                    <span :class="esUrgente(lote.fecha_vencimiento) ? 'text-rose-600 bg-rose-50 border-rose-200' : 'text-slate-600 bg-white border-slate-200'" class="px-3 py-1.5 rounded-lg border text-xs font-black shadow-sm flex items-center justify-center gap-2 w-max mx-auto">
                                        <svg v-if="esUrgente(lote.fecha_vencimiento)" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" /></svg>
                                        {{ formatearFecha(lote.fecha_vencimiento) }}
                                    </span>
                                </td>

                                <td class="p-4 text-center">
                                    <div class="flex flex-col items-center">
                                        <span class="font-black text-sky-600 text-sm">{{ formatearCantidad(lote.stock_actual) }}</span>
										<span class="text-[9px] text-slate-400 font-bold uppercase tracking-widest">de {{ formatearCantidad(lote.stock_inicial) }} iniciales</span>
                                    </div>
                                </td>

                                <td class="p-4 text-center">
                                    <span v-if="lote.estado_liquidacion" class="px-3 py-1 bg-amber-100 text-amber-700 rounded-md text-[10px] font-black uppercase tracking-widest shadow-sm">
                                        EN LIQUIDACIÓN 📉
                                    </span>
                                    <span v-else class="px-3 py-1 bg-emerald-50 text-emerald-600 rounded-md text-[10px] font-black uppercase tracking-widest border border-emerald-100">
                                        Normal
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; height: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
</style>