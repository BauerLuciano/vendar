<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatearMoneda } from '@/Utils/formatters.js';

const props = defineProps({
    resumen: Object,
    metodos_pago: Array,
    top_productos: Array,
    ventas_recientes: Array,
    filtros: Object,
});

const fechaDesde = ref(props.filtros.fecha_desde);
const fechaHasta = ref(props.filtros.fecha_hasta);

const presets = [
    { label: 'Hoy', desde: () => new Date().toISOString().slice(0, 10), hasta: () => new Date().toISOString().slice(0, 10) },
    { label: 'Ayer', desde: () => { const d = new Date(); d.setDate(d.getDate() - 1); return d.toISOString().slice(0, 10); }, hasta: () => { const d = new Date(); d.setDate(d.getDate() - 1); return d.toISOString().slice(0, 10); } },
    { label: 'Este Mes', desde: () => { const d = new Date(); return new Date(d.getFullYear(), d.getMonth(), 1).toISOString().slice(0, 10); }, hasta: () => new Date().toISOString().slice(0, 10) },
    { label: 'Mes Pasado', desde: () => { const d = new Date(); return new Date(d.getFullYear(), d.getMonth() - 1, 1).toISOString().slice(0, 10); }, hasta: () => new Date(new Date().getFullYear(), new Date().getMonth(), 0).toISOString().slice(0, 10) },
];

const aplicarPreset = (preset) => {
    fechaDesde.value = preset.desde();
    fechaHasta.value = preset.hasta();
    filtrar();
};

const filtrar = () => {
    router.get(route('reportes.index'), {
        fecha_desde: fechaDesde.value,
        fecha_hasta: fechaHasta.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const descargarPdf = () => {
    window.open(route('reportes.pdf', {
        fecha_desde: fechaDesde.value,
        fecha_hasta: fechaHasta.value,
    }), '_blank');
};
</script>

<template>
    <Head title="Reportes de Ventas" />

    <AuthenticatedLayout>
        <div class="py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-black text-slate-800 uppercase tracking-wide">Reportes de Ventas</h1>
                    <p class="text-sm text-slate-500 mt-1">Analizá el rendimiento de tu negocio</p>
                </div>
                <button @click="descargarPdf" class="inline-flex items-center gap-2 bg-slate-800 hover:bg-slate-700 text-white font-bold py-2.5 px-5 rounded-xl shadow transition-all text-sm uppercase tracking-wider">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Descargar PDF
                </button>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4 sm:p-6">
                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Desde</label>
                        <input v-model="fechaDesde" type="date" class="bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-medium text-slate-700">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Hasta</label>
                        <input v-model="fechaHasta" type="date" class="bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-medium text-slate-700">
                    </div>
                    <button @click="filtrar" class="bg-sky-600 hover:bg-sky-500 text-white font-bold py-2 px-5 rounded-lg transition-all text-sm">Filtrar</button>
                    <div class="flex flex-wrap gap-1.5 ml-auto">
                        <button v-for="p in presets" :key="p.label" @click="aplicarPreset(p)" class="text-xs font-bold px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-100 text-slate-600 transition-all uppercase">{{ p.label }}</button>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
                    <p class="text-xs font-black text-slate-400 uppercase tracking-wider">Total Vendido</p>
                    <p class="text-3xl font-black text-slate-800 mt-1">{{ formatearMoneda(resumen.total_ventas) }}</p>
                </div>
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
                    <p class="text-xs font-black text-slate-400 uppercase tracking-wider">Ventas Realizadas</p>
                    <p class="text-3xl font-black text-slate-800 mt-1">{{ resumen.cantidad_ventas }}</p>
                </div>
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
                    <p class="text-xs font-black text-slate-400 uppercase tracking-wider">Ticket Promedio</p>
                    <p class="text-3xl font-black text-slate-800 mt-1">{{ formatearMoneda(resumen.ticket_promedio) }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="px-5 py-4 border-b border-slate-100">
                        <h2 class="text-sm font-black text-slate-700 uppercase">Medios de Pago</h2>
                    </div>
                    <div class="p-5">
                        <table v-if="metodos_pago.length > 0" class="w-full text-sm">
                            <thead>
                                <tr class="text-xs font-bold text-slate-400 uppercase border-b border-slate-100">
                                    <th class="text-left pb-2">Método</th>
                                    <th class="text-right pb-2">Ventas</th>
                                    <th class="text-right pb-2">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="mp in metodos_pago" :key="mp.metodo" class="border-b border-slate-50 last:border-0">
                                    <td class="py-2.5 font-medium text-slate-700">{{ mp.label }}</td>
                                    <td class="py-2.5 text-right text-slate-600">{{ mp.cantidad }}</td>
                                    <td class="py-2.5 text-right font-semibold text-slate-800">{{ formatearMoneda(mp.total) }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-else class="text-sm text-slate-400 text-center py-4">Sin ventas en el período</p>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="px-5 py-4 border-b border-slate-100">
                        <h2 class="text-sm font-black text-slate-700 uppercase">Productos Más Vendidos</h2>
                    </div>
                    <div class="p-5">
                        <table v-if="top_productos.length > 0" class="w-full text-sm">
                            <thead>
                                <tr class="text-xs font-bold text-slate-400 uppercase border-b border-slate-100">
                                    <th class="text-left pb-2">Producto</th>
                                    <th class="text-right pb-2">Cantidad</th>
                                    <th class="text-right pb-2">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(p, i) in top_productos" :key="p.id" class="border-b border-slate-50 last:border-0">
                                    <td class="py-2 font-medium text-slate-700 flex items-center gap-2">
                                        <span class="text-xs font-black text-slate-300 w-5">{{ i + 1 }}.</span>
                                        {{ p.nombre }}
                                    </td>
                                    <td class="py-2 text-right text-slate-600">{{ p.cantidad }}</td>
                                    <td class="py-2 text-right font-semibold text-slate-800">{{ formatearMoneda(p.total) }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-else class="text-sm text-slate-400 text-center py-4">Sin ventas en el período</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h2 class="text-sm font-black text-slate-700 uppercase">Últimas Ventas del Período</h2>
                </div>
                <div class="overflow-x-auto">
                    <table v-if="ventas_recientes.length > 0" class="w-full text-sm">
                        <thead>
                            <tr class="text-xs font-bold text-slate-400 uppercase border-b border-slate-100 bg-slate-50/50">
                                <th class="text-left px-5 py-3">#</th>
                                <th class="text-left px-5 py-3">Cliente</th>
                                <th class="text-left px-5 py-3">Método de Pago</th>
                                <th class="text-right px-5 py-3">Total</th>
                                <th class="text-right px-5 py-3">Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="v in ventas_recientes" :key="v.id" class="border-b border-slate-50 hover:bg-slate-50/50 transition-colors">
                                <td class="px-5 py-3 font-mono text-xs text-slate-400">#{{ v.id }}</td>
                                <td class="px-5 py-3 font-medium text-slate-700">{{ v.cliente }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ v.metodo_pago_label }}</td>
                                <td class="px-5 py-3 text-right font-semibold text-slate-800">{{ formatearMoneda(v.total) }}</td>
                                <td class="px-5 py-3 text-right text-slate-400 text-xs">{{ v.fecha }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="text-sm text-slate-400 text-center py-8">Sin ventas en el período seleccionado</p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
