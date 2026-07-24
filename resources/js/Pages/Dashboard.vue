<script setup>
import { ref, computed, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
    deudaTotal: { type: Number, default: 0 },
    ventasHoy: { type: Number, default: 0 },
    ventasPeriodo: { type: Number, default: 0 },
    cajasActivas: { type: Number, default: 0 },
    cajasActivasLista: { type: Array, default: () => [] },
    productosBajoStock: { type: Array, default: () => [] },
    pedidosWebPendientes: { type: Number, default: 0 },
    pedidosRecientes: { type: Array, default: () => [] },
    topProductos: { type: Array, default: () => [] },
    ventasPorDia: { type: Array, default: () => [] },
    fechaDesde: { type: String, default: '' },
    fechaHasta: { type: String, default: '' },
    esJefe: { type: Boolean, default: false },
    sucursalUsuario: { type: String, default: 'Sin Asignar' },
    estadoOnboarding: { type: Object, default: () => ({ completo: false, porcentaje: 0, pasos: [] }) },
});

const formatearDinero = (monto) => {
    return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(monto);
};

const maxVenta = computed(() => {
    const max = Math.max(...props.ventasPorDia.map(v => v.total), 1);
    return max;
});

const badgeEstado = (estado) => {
    const map = {
        nuevo: 'bg-amber-100 text-amber-700',
        preparando: 'bg-sky-100 text-sky-700',
        en_camino: 'bg-indigo-100 text-indigo-700',
        entregado: 'bg-emerald-100 text-emerald-700',
        cancelado: 'bg-rose-100 text-rose-700',
    };
    return map[estado] || 'bg-slate-100 text-slate-600';
};

const filtroDesde = ref(props.fechaDesde);
const filtroHasta = ref(props.fechaHasta);

const muchosDias = computed(() => props.ventasPorDia.length > 14);

const aplicarFiltro = () => {
    if (filtroHasta.value < filtroDesde.value) {
        Swal.fire({ icon: 'warning', title: 'Fechas inválidas', text: 'La fecha "Hasta" no puede ser menor a "Desde".', confirmButtonColor: '#0284c7' });
        return;
    }
    router.get(route('dashboard'), { desde: filtroDesde.value, hasta: filtroHasta.value }, { preserveState: true, replace: true });
};

const limpiarFiltros = () => {
    filtroDesde.value = '';
    filtroHasta.value = '';
    router.get(route('dashboard'), {}, { preserveState: true, replace: true });
};

const descargarPDF = () => {
    const params = new URLSearchParams({ desde: filtroDesde.value, hasta: filtroHasta.value });
    window.open(route('dashboard.pdf') + '?' + params.toString(), '_blank');
};

const generarOCS = () => {
    Swal.fire({
        title: '¿Generar Sugerencias de Compra?',
        text: "El sistema agrupará los productos bajo stock por proveedor y creará órdenes en estado 'Sugerida'.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0f172a',
        confirmButtonText: 'Sí, generar sugerencias',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('ordenes.generarSugerencias'), {}, {
                onSuccess: () => {
                    Swal.fire({
                        title: '¡Proceso Exitoso!',
                        text: 'Se generaron las órdenes sugeridas. ¿Querés ir a revisarlas?',
                        icon: 'success',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, llevarme allá',
                        cancelButtonText: 'Quedarme acá',
                        confirmButtonColor: '#0284c7',
                    }).then((res) => {
                        if (res.isConfirmed) router.get(route('ordenes.index'));
                    });
                },
                onError: () => {
                    Swal.fire('Error', 'No se pudieron generar las sugerencias. Revisá que los productos tengan un proveedor asignado.', 'error');
                },
            });
        }
    });
};
</script>

<template>
    <Head title="Dashboard" />
    <AuthenticatedLayout>
        <div class="py-6 px-4 sm:px-6 lg:px-8 bg-slate-50 min-h-screen">
            <div class="mb-8 flex flex-col sm:flex-row justify-between items-start sm:items-end gap-4">
                <div>
                    <h1 class="text-2xl font-black text-slate-800 uppercase tracking-tight">Panel de Control</h1>
                    <div class="h-1 w-12 bg-sky-500 mt-1"></div>
                </div>
                <div class="flex flex-col items-start sm:items-end gap-2">
                    <p class="text-sm font-bold text-slate-500">{{ new Date().toLocaleDateString('es-AR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}</p>
                    <div class="flex items-center gap-2 bg-sky-100 text-sky-800 px-3 py-1.5 rounded-lg border border-sky-200 shadow-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                        <span class="text-[10px] font-black uppercase tracking-widest leading-none">SUCURSAL: <span class="text-sky-600">{{ sucursalUsuario }}</span></span>
                    </div>
                </div>
            </div>

            <div v-if="!estadoOnboarding.completo" class="bg-gradient-to-r from-sky-50 to-indigo-50 rounded-3xl border border-sky-200 p-5 mb-6 shadow-sm">
                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                    <div class="w-12 h-12 bg-sky-100 text-sky-600 rounded-2xl flex items-center justify-center shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest">Bienvenido a VendAR</h3>
                        <p class="text-xs text-slate-500 mt-1">Completá los pasos para empezar a vender:</p>
                        <div class="mt-3">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Progreso</span>
                                <span class="text-[10px] font-black text-sky-600">{{ estadoOnboarding.porcentaje }}%</span>
                            </div>
                            <div class="w-full bg-sky-100 rounded-full h-2">
                                <div class="bg-sky-500 h-2 rounded-full transition-all duration-500" :style="{ width: estadoOnboarding.porcentaje + '%' }"></div>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 mt-3">
                            <span v-for="paso in estadoOnboarding.pasos" :key="paso.key" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest"
                                  :class="paso.completado ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'">
                                <svg v-if="paso.completado" xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                                <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
                                {{ paso.titulo }}
                            </span>
                        </div>
                    </div>
                    <Link :href="route('onboarding.index')" class="bg-sky-600 text-white text-[10px] font-black uppercase tracking-widest px-5 py-2.5 rounded-xl hover:bg-sky-700 transition-colors shrink-0">
                        Completar
                    </Link>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
                <div class="bg-white p-5 rounded-3xl shadow-sm border border-slate-200 relative overflow-hidden group">
                    <div class="absolute -right-6 -top-6 bg-emerald-50 w-28 h-28 rounded-full"></div>
                    <div class="relative z-10">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-11 h-11 bg-emerald-100 text-emerald-600 rounded-xl flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </div>
                            <h2 class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Ventas Hoy</h2>
                        </div>
                        <p class="text-2xl font-black text-slate-800 tracking-tighter">{{ formatearDinero(ventasHoy) }}</p>
                    </div>
                </div>

                <div v-if="esJefe" class="bg-white p-5 rounded-3xl shadow-sm border border-slate-200 relative overflow-hidden group">
                    <div class="absolute -right-6 -top-6 bg-rose-50 w-28 h-28 rounded-full"></div>
                    <div class="relative z-10">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-11 h-11 bg-rose-100 text-rose-500 rounded-xl flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                            </div>
                            <div>
                                <h2 class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Cuentas Corrientes</h2>
                                <p class="text-[10px] font-medium text-slate-400 leading-tight">Saldo total deudores</p>
                            </div>
                        </div>
                        <p class="text-2xl font-black text-slate-800 tracking-tighter">{{ formatearDinero(deudaTotal) }}</p>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-3xl shadow-sm border border-slate-200 relative overflow-hidden group">
                    <div class="absolute -right-6 -top-6 bg-indigo-50 w-28 h-28 rounded-full"></div>
                    <div class="relative z-10">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-11 h-11 bg-indigo-100 text-indigo-500 rounded-xl flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                            </div>
                            <h2 class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Cajas Abiertas</h2>
                        </div>
                        <p class="text-2xl font-black text-slate-800 tracking-tighter">{{ cajasActivas }}</p>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-3xl shadow-sm border border-slate-200 relative overflow-hidden group">
                    <div class="absolute -right-6 -top-6 bg-sky-50 w-28 h-28 rounded-full"></div>
                    <div class="relative z-10">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-11 h-11 bg-sky-100 text-sky-500 rounded-xl flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                            </div>
                            <h2 class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Stock Bajo</h2>
                        </div>
                        <p class="text-2xl font-black text-slate-800 tracking-tighter">{{ productosBajoStock.length }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-4 mb-6">
                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                    <div class="flex items-center gap-2">
                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">Desde</label>
                        <input type="date" v-model="filtroDesde" :max="filtroHasta" class="border border-slate-200 rounded-lg px-3 py-2 text-xs font-bold text-slate-700 focus:outline-none focus:border-sky-400" />
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">Hasta</label>
                        <input type="date" v-model="filtroHasta" :min="filtroDesde" class="border border-slate-200 rounded-lg px-3 py-2 text-xs font-bold text-slate-700 focus:outline-none focus:border-sky-400" />
                    </div>
                    <div class="flex gap-2 ml-auto">
                        <button @click="aplicarFiltro" class="bg-sky-600 text-white text-[10px] font-black uppercase tracking-widest px-5 py-2.5 rounded-xl hover:bg-sky-700 transition-colors">Filtrar</button>
                        <button @click="limpiarFiltros" class="border border-slate-300 text-slate-600 text-[10px] font-black uppercase tracking-widest px-4 py-2.5 rounded-xl hover:bg-slate-50 transition-colors">Limpiar</button>
                        <button @click="descargarPDF" class="bg-slate-800 text-white text-[10px] font-black uppercase tracking-widest px-5 py-2.5 rounded-xl hover:bg-slate-900 transition-colors flex items-center gap-1.5">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                            PDF
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-5">
                        <div class="flex items-center gap-2 mb-5">
                            <div class="w-8 h-8 bg-sky-100 text-sky-600 rounded-lg flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                            </div>
                            <h2 class="text-sm font-black text-slate-800 uppercase tracking-widest">Ventas</h2>
                        </div>
                        <div v-if="!muchosDias" class="flex items-end gap-1 sm:gap-2 h-48">
                            <div v-for="(dia, i) in ventasPorDia" :key="i" class="flex-1 flex flex-col items-center gap-1.5 h-full">
                                <div class="flex-1 w-full flex flex-col justify-end">
                                    <div
                                        class="w-full rounded-t-lg transition-all duration-500 hover:opacity-80 cursor-pointer relative group/bar"
                                        :style="{
                                            height: Math.max((dia.total / maxVenta) * 100, dia.total > 0 ? 4 : 0) + '%',
                                            backgroundColor: dia.total > 0 ? (i === ventasPorDia.length - 1 ? '#0284c7' : '#94a3b8') : 'transparent'
                                        }"
                                    >
                                        <div v-if="dia.total > 0" class="absolute -top-7 left-1/2 -translate-x-1/2 bg-slate-800 text-white text-[10px] font-bold px-2 py-0.5 rounded opacity-0 group-hover/bar:opacity-100 transition whitespace-nowrap">
                                            {{ formatearDinero(dia.total) }}
                                        </div>
                                    </div>
                                </div>
                                <span class="text-[9px] font-bold text-slate-400 uppercase text-center leading-tight">{{ dia.dia ? dia.dia.substring(0, 3) : '' }}</span>
                            </div>
                        </div>
                        <div v-else class="overflow-x-auto pb-2 -mb-2">
                            <div class="flex items-end gap-2 h-48" :style="{ minWidth: ventasPorDia.length * 42 + 'px' }">
                                <div v-for="(dia, i) in ventasPorDia" :key="i" class="flex flex-col items-center gap-1.5 h-full shrink-0" style="width:36px">
                                    <div class="flex-1 w-full flex flex-col justify-end">
                                        <div
                                            class="w-full rounded-t-lg transition-all duration-500 hover:opacity-80 cursor-pointer relative group/bar"
                                            :style="{
                                                height: Math.max((dia.total / maxVenta) * 100, dia.total > 0 ? 4 : 0) + '%',
                                                backgroundColor: dia.total > 0 ? (i === ventasPorDia.length - 1 ? '#0284c7' : '#94a3b8') : 'transparent'
                                            }"
                                        >
                                            <div v-if="dia.total > 0" class="absolute -top-7 left-1/2 -translate-x-1/2 bg-slate-800 text-white text-[10px] font-bold px-2 py-0.5 rounded opacity-0 group-hover/bar:opacity-100 transition whitespace-nowrap">
                                                {{ formatearDinero(dia.total) }}
                                            </div>
                                        </div>
                                    </div>
                                    <span class="text-[9px] font-bold text-slate-400 uppercase text-center leading-tight">{{ dia.dia ? dia.dia.substring(0, 3) : '' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="cajasActivasLista.length > 0" class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                        <div class="p-4 border-b border-slate-100 bg-slate-50 flex items-center gap-2">
                            <div class="w-7 h-7 bg-emerald-100 text-emerald-600 rounded-lg flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </div>
                            <h2 class="text-xs font-black text-slate-800 uppercase tracking-widest">Cajas Operando</h2>
                        </div>
                        <div class="divide-y divide-slate-100">
                            <div v-for="caja in cajasActivasLista" :key="caja.id" class="p-4 hover:bg-slate-50 transition-colors">
                                <div class="flex items-center gap-3">
                                    <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse shrink-0"></span>
                                    <div class="flex-1 grid grid-cols-2 sm:grid-cols-4 gap-3">
                                        <div>
                                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">{{ caja.cajero }}</p>
                                            <p class="text-xs font-bold text-slate-700">{{ caja.caja }}</p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Sucursal</p>
                                            <p class="text-xs font-bold text-slate-700">{{ caja.sucursal }}</p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Desde</p>
                                            <p class="text-xs font-bold text-slate-700">{{ caja.fecha_apertura }} hs</p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Facturado</p>
                                            <p class="text-xs font-bold text-emerald-600">{{ formatearDinero(caja.facturado) }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-1 space-y-6">
                    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                        <div class="p-4 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 bg-amber-100 text-amber-600 rounded-lg flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                                </div>
                                <h2 class="text-xs font-black text-slate-800 uppercase tracking-widest">Pedidos Pendientes</h2>
                                <span v-if="pedidosRecientes.length" class="text-[10px] font-bold text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full">{{ pedidosWebPendientes }}</span>
                            </div>
                            <Link :href="route('pedidos.index')" class="text-[10px] font-bold text-sky-600 hover:text-sky-800 uppercase tracking-widest">Ir</Link>
                        </div>
                        <div class="divide-y divide-slate-100 max-h-80 overflow-y-auto">
                            <div v-if="pedidosRecientes.length === 0" class="p-5 text-center">
                                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-100 text-slate-500 text-[10px] font-bold uppercase tracking-widest">Sin pedidos pendientes</span>
                            </div>
                            <div v-for="pedido in pedidosRecientes" :key="pedido.id" class="p-3.5 hover:bg-slate-50 transition-colors">
                                <div class="flex items-center justify-between mb-0.5">
                                    <span class="text-sm font-bold text-slate-700 truncate mr-2">{{ pedido.cliente }}</span>
                                    <span class="text-xs font-black text-slate-500 shrink-0">{{ formatearDinero(pedido.total) }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] text-slate-400 font-medium">{{ pedido.sucursal }} · {{ pedido.desde }}</span>
                                    <span class="text-[9px] font-black uppercase tracking-widest px-2 py-0.5 rounded-full shrink-0" :class="badgeEstado(pedido.estado)">{{ pedido.estado_display || pedido.estado }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                        <div class="p-4 border-b border-slate-100 bg-slate-50 flex items-center gap-2">
                            <div class="w-7 h-7 bg-violet-100 text-violet-600 rounded-lg flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" /><path stroke-linecap="round" stroke-linejoin="round" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" /></svg>
                            </div>
                            <h2 class="text-xs font-black text-slate-800 uppercase tracking-widest">Top Vendido {{ fechaDesde === fechaHasta ? 'Hoy' : '(período)' }}</h2>
                        </div>
                        <div class="divide-y divide-slate-100">
                            <div v-if="topProductos.length === 0" class="p-5 text-center">
                                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-100 text-slate-500 text-[10px] font-bold uppercase tracking-widest">Sin ventas en el período</span>
                            </div>
                            <div v-for="(prod, idx) in topProductos" :key="idx" class="p-3.5 flex items-center gap-3 hover:bg-slate-50 transition-colors">
                                <span class="w-6 h-6 rounded-lg flex items-center justify-center text-[10px] font-black shrink-0"
                                    :class="idx === 0 ? 'bg-amber-100 text-amber-600' : idx === 1 ? 'bg-slate-200 text-slate-500' : idx === 2 ? 'bg-orange-100 text-orange-600' : 'bg-slate-100 text-slate-400'"
                                >{{ idx + 1 }}</span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-bold text-slate-700 truncate">{{ prod.nombre }}</p>
                                    <p class="text-[10px] text-slate-400 font-medium">{{ prod.cantidad }} vendidos</p>
                                </div>
                                <span class="text-xs font-black text-slate-600 shrink-0">{{ formatearDinero(prod.total) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 bg-amber-100 text-amber-500 rounded-lg flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        </div>
                        <h2 class="text-xs font-black text-slate-800 uppercase tracking-tight">Alertas de Stock</h2>
                    </div>
                    <button v-if="esJefe && productosBajoStock.length > 0" @click="generarOCS" class="bg-slate-900 text-white text-[10px] px-4 py-2 rounded-lg font-bold shadow-md hover:bg-sky-600 transition-colors uppercase tracking-widest">Generar OCS</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[10px] uppercase tracking-widest text-slate-400 bg-white border-b border-slate-100">
                                <th class="p-4 font-black">Producto</th>
                                <th class="p-4 font-black">Sucursal</th>
                                <th class="p-4 font-black text-center">Stock Actual</th>
                                <th class="p-4 font-black text-center">Mínimo</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <tr v-if="productosBajoStock.length === 0">
                                <td colspan="4" class="p-8 text-center">
                                    <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 text-emerald-600 text-xs font-bold uppercase tracking-widest">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
                                        Stock en niveles óptimos
                                    </span>
                                </td>
                            </tr>
                            <tr v-for="(item, index) in productosBajoStock" :key="index" class="hover:bg-slate-50/50 transition-colors">
                                <td class="p-4 font-bold text-slate-700">{{ item.producto }}</td>
                                <td class="p-4 text-slate-500 text-sm">{{ item.sucursal }}</td>
                                <td class="p-4 text-center">
                                    <span class="px-2 py-1 bg-rose-100 text-rose-700 font-black rounded-md shadow-sm text-xs">
                                        {{ Number(item.cantidad_fisica) }}
                                        <span class="text-[10px] ml-1">{{ item.unidad_medida || 'U' }}</span>
                                    </span>
                                </td>
                                <td class="p-4 text-center text-slate-400 font-bold text-xs">
                                    {{ Number(item.stock_minimo) }}
                                    <span class="text-[10px] ml-1 uppercase">{{ item.unidad_medida || 'U' }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
