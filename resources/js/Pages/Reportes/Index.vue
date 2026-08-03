<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AlertaAyuda from '@/Components/AlertaAyuda.vue';
import { formatearMoneda } from '@/Utils/formatters.js';

const props = defineProps({
    resumen: Object,
    metodos_pago: Array,
    top_productos: Array,
    ventas_recientes: Array,
    filtros: Object,
    sucursales: Array,
});

const fechaDesde = ref(props.filtros.fecha_desde);
const fechaHasta = ref(props.filtros.fecha_hasta);

const tabActual = ref('ventas');
const cargandoReportes = ref(false);
const rotacion = ref([]);
const totalInmovilizado = ref(0);
const cargandoRotacion = ref(false);
const filtroDias = ref(30);
const filtroSucursalRotacion = ref('');

const cargarRotacion = async () => {
    cargandoRotacion.value = true;
    try {
        const params = { dias: filtroDias.value };
        if (filtroSucursalRotacion.value) params.sucursal_id = filtroSucursalRotacion.value;
        const res = await axios.get(route('reportes.rotacion'), { params });
        rotacion.value = res.data.data;
        totalInmovilizado.value = res.data.total_valor;
    } finally {
        cargandoRotacion.value = false;
    }
};

const compras = ref([]);
const cargandoCompras = ref(false);
const filtroComprasDesde = ref(props.filtros.fecha_desde);
const filtroComprasHasta = ref(props.filtros.fecha_hasta);

const cargarCompras = async () => {
    cargandoCompras.value = true;
    try {
        const res = await axios.get(route('reportes.compras'), {
            params: { fecha_desde: filtroComprasDesde.value, fecha_hasta: filtroComprasHasta.value }
        });
        compras.value = res.data;
    } finally {
        cargandoCompras.value = false;
    }
};

const cuentasCorrientes = ref([]);
const cargandoCC = ref(false);
const filtroCCDesde = ref(props.filtros.fecha_desde);
const filtroCCHasta = ref(props.filtros.fecha_hasta);

const cargarCuentasCorrientes = async () => {
    cargandoCC.value = true;
    try {
        const res = await axios.get(route('reportes.cuentas-corrientes'), {
            params: { fecha_desde: filtroCCDesde.value, fecha_hasta: filtroCCHasta.value }
        });
        cuentasCorrientes.value = res.data;
    } finally {
        cargandoCC.value = false;
    }
};

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
    cargandoReportes.value = true;
    router.get(route('reportes.index'), {
        fecha_desde: fechaDesde.value,
        fecha_hasta: fechaHasta.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => cargandoReportes.value = false,
    });
};

const descargarPdf = () => {
    window.open(route('reportes.pdf', {
        fecha_desde: fechaDesde.value,
        fecha_hasta: fechaHasta.value,
    }), '_blank');
};

const descargarExcel = () => {
    window.open(route('reportes.excel', {
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
                <div class="flex items-center gap-2">
                    <div class="bg-slate-100 rounded-xl p-1 flex">
                        <button @click="tabActual = 'ventas'" :class="['px-4 py-2 text-sm font-bold rounded-lg transition-all uppercase', tabActual === 'ventas' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-700']">Ventas</button>
                        <button @click="tabActual = 'rotacion'; if (rotacion.length === 0) cargarRotacion()" :class="['px-4 py-2 text-sm font-bold rounded-lg transition-all uppercase', tabActual === 'rotacion' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-700']">Rotación</button>
                        <button @click="tabActual = 'compras'; if (!compras.total_gastado && !cargandoCompras) cargarCompras()" :class="['px-4 py-2 text-sm font-bold rounded-lg transition-all uppercase', tabActual === 'compras' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-700']">Compras</button>
                        <button @click="tabActual = 'cc'; if (!cuentasCorrientes.resumen && !cargandoCC) cargarCuentasCorrientes()" :class="['px-4 py-2 text-sm font-bold rounded-lg transition-all uppercase', tabActual === 'cc' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-700']">C. Corrientes</button>
                    </div>
                    <button v-if="tabActual === 'ventas'" @click="descargarExcel" class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2.5 px-4 rounded-xl shadow transition-all text-sm uppercase tracking-wider">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                        Excel
                    </button>
                    <button v-if="tabActual === 'ventas'" @click="descargarPdf" class="inline-flex items-center gap-2 bg-slate-800 hover:bg-slate-700 text-white font-bold py-2.5 px-5 rounded-xl shadow transition-all text-sm uppercase tracking-wider">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Descargar PDF
                    </button>
                </div>
            </div>

            <template v-if="tabActual === 'ventas'">
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

            <div v-if="cargandoReportes" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div v-for="n in 6" :key="n" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 animate-pulse">
                    <div class="h-3 w-24 bg-slate-100 rounded mb-3"></div>
                    <div class="h-8 w-32 bg-slate-100 rounded"></div>
                </div>
            </div>

            <div v-if="!cargandoReportes" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
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

            <div v-if="!cargandoReportes" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-emerald-50 rounded-2xl border border-emerald-200 p-5">
                    <p class="text-xs font-black text-emerald-600 uppercase tracking-wider">Costo Total</p>
                    <p class="text-3xl font-black text-slate-800 mt-1">{{ formatearMoneda(resumen.costo_total) }}</p>
                </div>
                <div class="bg-sky-50 rounded-2xl border border-sky-200 p-5">
                    <p class="text-xs font-black text-sky-600 uppercase tracking-wider">Ganancia Bruta</p>
                    <p class="text-3xl font-black text-slate-800 mt-1">{{ formatearMoneda(resumen.ganancia_bruta) }}</p>
                </div>
                <div class="bg-indigo-50 rounded-2xl border border-indigo-200 p-5">
                    <p class="text-xs font-black text-indigo-600 uppercase tracking-wider">Margen</p>
                    <p class="text-3xl font-black text-slate-800 mt-1 flex items-baseline gap-1">
                        <span>{{ resumen.margen }}%</span>
                        <span :class="resumen.margen >= 20 ? 'text-emerald-500' : resumen.margen >= 10 ? 'text-amber-500' : 'text-red-500'" class="text-sm font-bold">
                            ({{ resumen.margen >= 20 ? 'Saludable' : resumen.margen >= 10 ? 'Aceptable' : 'Bajo' }})
                        </span>
                    </p>
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
            </template>

            <template v-if="tabActual === 'rotacion'">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4 sm:p-6">
                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Mín. Días Sin Venta</label>
                        <input v-model.number="filtroDias" type="number" min="1" class="bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-medium text-slate-700 w-24">
                    </div>
                    <div v-if="sucursales?.length > 1">
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Sucursal</label>
                        <select v-model="filtroSucursalRotacion" class="bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-medium text-slate-700">
                            <option value="">Todas</option>
                            <option v-for="s in sucursales" :key="s.id" :value="s.id">{{ s.nombre }}</option>
                        </select>
                    </div>
                    <button @click="cargarRotacion" class="bg-sky-600 hover:bg-sky-500 text-white font-bold py-2 px-5 rounded-lg transition-all text-sm">Consultar</button>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
                    <p class="text-xs font-black text-slate-400 uppercase tracking-wider">Productos con Baja Rotación</p>
                    <p class="text-3xl font-black text-slate-800 mt-1">{{ rotacion.length }}</p>
                </div>
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
                    <p class="text-xs font-black text-slate-400 uppercase tracking-wider">Valor Inmovilizado Total</p>
                    <p class="text-3xl font-black text-slate-800 mt-1">{{ formatearMoneda(totalInmovilizado) }}</p>
                </div>
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
                    <p class="text-xs font-black text-slate-400 uppercase tracking-wider">Días Mínimo</p>
                    <p class="text-3xl font-black text-slate-800 mt-1">{{ filtroDias }} días</p>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h2 class="text-sm font-black text-slate-700 uppercase">Productos</h2>
                </div>
                <div class="overflow-x-auto">
                    <table v-if="!cargandoRotacion && rotacion.length > 0" class="w-full text-sm">
                        <thead>
                            <tr class="text-xs font-bold text-slate-400 uppercase border-b border-slate-100 bg-slate-50/50">
                                <th class="text-left px-5 py-3">Producto</th>
                                <th class="text-right px-5 py-3">Stock</th>
                                <th class="text-right px-5 py-3">P. Costo</th>
                                <th class="text-right px-5 py-3">Valor Inmov.</th>
                                <th class="text-right px-5 py-3">Última Venta</th>
                                <th class="text-right px-5 py-3">Días Sin Venta</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="p in rotacion" :key="p.id" class="border-b border-slate-50 hover:bg-slate-50/50 transition-colors">
                                <td class="px-5 py-3 font-medium text-slate-700">{{ p.nombre }}</td>
                                <td class="px-5 py-3 text-right text-slate-600">{{ p.stock }}</td>
                                <td class="px-5 py-3 text-right text-slate-600">{{ formatearMoneda(p.precio_costo) }}</td>
                                <td class="px-5 py-3 text-right font-semibold text-slate-800">{{ formatearMoneda(p.valor_inmovilizado) }}</td>
                                <td class="px-5 py-3 text-right text-slate-400 text-xs">{{ p.ultima_venta ?? 'Nunca' }}</td>
                                <td class="px-5 py-3 text-right">
                                    <span :class="['text-xs font-bold px-2 py-1 rounded-full', p.dias_sin_venta >= 90 ? 'bg-red-100 text-red-700' : p.dias_sin_venta >= 60 ? 'bg-amber-100 text-amber-700' : 'bg-sky-100 text-sky-700']">{{ p.dias_sin_venta >= 9999 ? '∞' : p.dias_sin_venta + 'd' }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-if="cargandoRotacion" class="text-center py-8 text-slate-400">Cargando...</div>
                    <AlertaAyuda v-else-if="rotacion.length === 0">No hay productos con baja rotación para los filtros seleccionados</AlertaAyuda>
                </div>
            </div>
            </template>

            <template v-if="tabActual === 'compras'">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4 sm:p-6">
                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Desde</label>
                        <input v-model="filtroComprasDesde" type="date" class="bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-medium text-slate-700">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Hasta</label>
                        <input v-model="filtroComprasHasta" type="date" class="bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-medium text-slate-700">
                    </div>
                    <button @click="cargarCompras" class="bg-sky-600 hover:bg-sky-500 text-white font-bold py-2 px-5 rounded-lg transition-all text-sm">Filtrar</button>
                </div>
            </div>

            <div v-if="cargandoCompras" class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div v-for="n in 4" :key="n" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 animate-pulse">
                    <div class="h-3 w-24 bg-slate-100 rounded mb-3"></div>
                    <div class="h-8 w-32 bg-slate-100 rounded"></div>
                </div>
            </div>

            <div v-if="!cargandoCompras && compras.total_gastado !== undefined" class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
                    <p class="text-xs font-black text-slate-400 uppercase tracking-wider">Total Gastado</p>
                    <p class="text-3xl font-black text-slate-800 mt-1">{{ formatearMoneda(compras.total_gastado) }}</p>
                </div>
                <div v-for="e in compras.por_estado" :key="e.estado" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
                    <p class="text-xs font-black text-slate-400 uppercase tracking-wider">{{ e.estado }}</p>
                    <p class="text-3xl font-black text-slate-800 mt-1">{{ e.cantidad }} <span class="text-sm font-bold text-slate-400">órdenes</span></p>
                    <p class="text-sm font-semibold text-slate-600 mt-1">{{ formatearMoneda(e.total) }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div v-if="compras.por_proveedor?.length > 0" class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="px-5 py-4 border-b border-slate-100">
                        <h2 class="text-sm font-black text-slate-700 uppercase">Top Proveedores</h2>
                    </div>
                    <div class="p-5">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-xs font-bold text-slate-400 uppercase border-b border-slate-100">
                                    <th class="text-left pb-2">Proveedor</th>
                                    <th class="text-right pb-2">Órdenes</th>
                                    <th class="text-right pb-2">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="p in compras.por_proveedor" :key="p.razon_social" class="border-b border-slate-50 last:border-0">
                                    <td class="py-2.5 font-medium text-slate-700">{{ p.razon_social }}</td>
                                    <td class="py-2.5 text-right text-slate-600">{{ p.cantidad }}</td>
                                    <td class="py-2.5 text-right font-semibold text-slate-800">{{ formatearMoneda(p.total) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="compras.tendencia?.length > 0" class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="px-5 py-4 border-b border-slate-100">
                        <h2 class="text-sm font-black text-slate-700 uppercase">Tendencia Mensual</h2>
                    </div>
                    <div class="p-5">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-xs font-bold text-slate-400 uppercase border-b border-slate-100">
                                    <th class="text-left pb-2">Mes</th>
                                    <th class="text-right pb-2">Órdenes</th>
                                    <th class="text-right pb-2">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="t in compras.tendencia" :key="t.mes" class="border-b border-slate-50 last:border-0">
                                    <td class="py-2.5 font-medium text-slate-700">{{ t.mes }}</td>
                                    <td class="py-2.5 text-right text-slate-600">{{ t.cantidad }}</td>
                                    <td class="py-2.5 text-right font-semibold text-slate-800">{{ formatearMoneda(t.total) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div v-if="compras.pendientes?.length > 0" class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h2 class="text-sm font-black text-slate-700 uppercase">Órdenes Pendientes</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-xs font-bold text-slate-400 uppercase border-b border-slate-100 bg-slate-50/50">
                                <th class="text-left px-5 py-3">#</th>
                                <th class="text-left px-5 py-3">Proveedor</th>
                                <th class="text-left px-5 py-3">Estado</th>
                                <th class="text-right px-5 py-3">Total</th>
                                <th class="text-right px-5 py-3">Días</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="o in compras.pendientes" :key="o.id" class="border-b border-slate-50 hover:bg-slate-50/50 transition-colors">
                                <td class="px-5 py-3 font-mono text-xs text-slate-400">{{ o.nro_comprobante || 'OC #' + o.id }}</td>
                                <td class="px-5 py-3 font-medium text-slate-700">{{ o.proveedor }}</td>
                                <td class="px-5 py-3">
                                    <span :class="['text-xs font-bold px-2 py-1 rounded-full', o.estado === 'Sugerida' ? 'bg-amber-100 text-amber-700' : o.estado === 'Borrador' ? 'bg-slate-100 text-slate-600' : 'bg-blue-100 text-blue-700']">{{ o.estado }}</span>
                                </td>
                                <td class="px-5 py-3 text-right font-semibold text-slate-800">{{ formatearMoneda(o.total) }}</td>
                                <td class="px-5 py-3 text-right text-slate-400 text-xs">{{ o.dias }}d</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <p v-if="!cargandoCompras && compras.total_gastado !== undefined && compras.pendientes?.length === 0" class="text-sm text-slate-400 text-center py-8">Sin órdenes de compra en el período</p>
            </template>

            <template v-if="tabActual === 'cc'">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4 sm:p-6">
                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Desde</label>
                        <input v-model="filtroCCDesde" type="date" class="bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-medium text-slate-700">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Hasta</label>
                        <input v-model="filtroCCHasta" type="date" class="bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-medium text-slate-700">
                    </div>
                    <button @click="cargarCuentasCorrientes" class="bg-sky-600 hover:bg-sky-500 text-white font-bold py-2 px-5 rounded-lg transition-all text-sm">Filtrar</button>
                </div>
            </div>

            <div v-if="cargandoCC" class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div v-for="n in 4" :key="n" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 animate-pulse">
                    <div class="h-3 w-24 bg-slate-100 rounded mb-3"></div>
                    <div class="h-8 w-32 bg-slate-100 rounded"></div>
                </div>
            </div>

            <div v-if="!cargandoCC && cuentasCorrientes.resumen" class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
                    <p class="text-xs font-black text-slate-400 uppercase tracking-wider">Deuda Total</p>
                    <p class="text-3xl font-black text-slate-800 mt-1">{{ formatearMoneda(cuentasCorrientes.resumen.deuda_total) }}</p>
                </div>
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
                    <p class="text-xs font-black text-slate-400 uppercase tracking-wider">Clientes con Deuda</p>
                    <p class="text-3xl font-black text-slate-800 mt-1">{{ cuentasCorrientes.resumen.clientes_activos }}</p>
                </div>
                <div class="bg-amber-50 rounded-2xl border border-amber-200 p-5">
                    <p class="text-xs font-black text-amber-600 uppercase tracking-wider">Próximos al Límite</p>
                    <p class="text-3xl font-black text-slate-800 mt-1">{{ cuentasCorrientes.resumen.al_limite }}</p>
                </div>
                <div class="bg-emerald-50 rounded-2xl border border-emerald-200 p-5">
                    <p class="text-xs font-black text-emerald-600 uppercase tracking-wider">Cobros del Período</p>
                    <p class="text-3xl font-black text-slate-800 mt-1">{{ formatearMoneda(cuentasCorrientes.resumen.cobros_periodo) }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div v-if="cuentasCorrientes.top_deudores?.length > 0" class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="px-5 py-4 border-b border-slate-100">
                        <h2 class="text-sm font-black text-slate-700 uppercase">Top Deudores</h2>
                    </div>
                    <div class="p-5">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-xs font-bold text-slate-400 uppercase border-b border-slate-100">
                                    <th class="text-left pb-2">Cliente</th>
                                    <th class="text-right pb-2">Deuda</th>
                                    <th class="text-right pb-2">Límite</th>
                                    <th class="text-right pb-2">Uso</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="d in cuentasCorrientes.top_deudores" :key="d.id" class="border-b border-slate-50 last:border-0">
                                    <td class="py-2.5 font-medium text-slate-700">{{ d.cliente }}</td>
                                    <td class="py-2.5 text-right font-semibold text-slate-800">{{ formatearMoneda(d.deuda) }}</td>
                                    <td class="py-2.5 text-right text-slate-600">{{ formatearMoneda(d.limite) }}</td>
                                    <td class="py-2.5 text-right">
                                        <span :class="['text-xs font-bold px-2 py-1 rounded-full', d.uso_porcentaje >= 80 ? 'bg-red-100 text-red-700' : d.uso_porcentaje >= 50 ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700']">{{ d.uso_porcentaje }}%</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="cuentasCorrientes.movimientos_recientes?.length > 0" class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="px-5 py-4 border-b border-slate-100">
                        <h2 class="text-sm font-black text-slate-700 uppercase">Movimientos Recientes</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-xs font-bold text-slate-400 uppercase border-b border-slate-100 bg-slate-50/50">
                                    <th class="text-left px-5 py-3">Cliente</th>
                                    <th class="text-left px-5 py-3">Tipo</th>
                                    <th class="text-left px-5 py-3">Descripción</th>
                                    <th class="text-right px-5 py-3">Monto</th>
                                    <th class="text-right px-5 py-3">Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="m in cuentasCorrientes.movimientos_recientes" :key="m.id" class="border-b border-slate-50 hover:bg-slate-50/50 transition-colors">
                                    <td class="px-5 py-3 font-medium text-slate-700">{{ m.cliente }}</td>
                                    <td class="px-5 py-3">
                                        <span :class="['text-xs font-bold px-2 py-1 rounded-full', m.tipo === 'pago' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700']">{{ m.tipo === 'pago' ? 'Pago' : 'Cargo' }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-slate-600 text-xs">{{ m.descripcion || '-' }}</td>
                                    <td class="px-5 py-3 text-right font-semibold" :class="m.tipo === 'pago' ? 'text-emerald-600' : 'text-red-600'">{{ m.tipo === 'pago' ? '' : '-' }}{{ formatearMoneda(m.monto) }}</td>
                                    <td class="px-5 py-3 text-right text-slate-400 text-xs">{{ m.fecha }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <p v-if="!cargandoCC && cuentasCorrientes.resumen && cuentasCorrientes.top_deudores?.length === 0 && cuentasCorrientes.movimientos_recientes?.length === 0" class="text-sm text-slate-400 text-center py-8">Sin movimientos en el período</p>
            </template>
        </div>
    </AuthenticatedLayout>
</template>
