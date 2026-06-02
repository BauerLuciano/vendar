<script setup>
import GlobalAdminLayout from '@/Layouts/GlobalAdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    kpis: { type: Object, default: () => ({}) },
    ultimosComercios: { type: Array, default: () => [] },
});

const formatearDinero = (monto) => {
    return new Intl.NumberFormat('es-AR', {
        style: 'currency',
        currency: 'ARS',
        maximumFractionDigits: 0
    }).format(monto || 0);
};

const colorEstado = (estado) => {
    switch(estado?.toLowerCase()) {
        case 'activo': return 'bg-emerald-50 text-emerald-700 ring-emerald-600/20';
        case 'trial': return 'bg-amber-50 text-amber-700 ring-amber-600/20';
        default: return 'bg-rose-50 text-rose-700 ring-rose-600/20';
    }
};
</script>

<template>
    <GlobalAdminLayout>
        <Head title="Panel Global - Métricas" />

        <div class="py-8 px-6 max-w-7xl mx-auto">
            <div class="sm:flex sm:items-center sm:justify-between mb-8">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Métricas Globales</h1>
                    <p class="mt-1 text-sm text-slate-500">Monitoreo financiero y rendimiento de la nube VendAR</p>
                </div>
                <div class="mt-4 sm:mt-0">
                    <Link :href="route('admin.comercios.index')" class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition-all">
                        Gestionar Comercios
                    </Link>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
                <div class="overflow-hidden rounded-2xl bg-white px-6 py-5 shadow-sm ring-1 ring-slate-900/5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 rounded-xl bg-emerald-50 p-3">
                            <svg class="h-6 w-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a6 6 0 0 1 6-6m0 0a6 6 0 0 1 6 6m-6-6a6 6 0 0 1 6-6m0 0a6 6 0 0 1 6 6" />
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="truncate text-sm font-medium text-slate-500">Facturación Mensual</p>
                            <p class="text-2xl font-semibold text-slate-900">{{ formatearDinero(kpis?.mrr_estimado) }}</p>
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden rounded-2xl bg-white px-6 py-5 shadow-sm ring-1 ring-slate-900/5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 rounded-xl bg-sky-50 p-3">
                            <svg class="h-6 w-6 text-sky-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z" />
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="truncate text-sm font-medium text-slate-500">Locales Activos</p>
                            <div class="flex items-baseline gap-2">
                                <p class="text-2xl font-semibold text-slate-900">{{ kpis?.comercios_activos || 0 }}</p>
                                <p class="text-sm text-slate-500">/ {{ kpis?.comercios_totales || 0 }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden rounded-2xl bg-white px-6 py-5 shadow-sm ring-1 ring-slate-900/5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 rounded-xl bg-orange-50 p-3">
                            <svg class="h-6 w-6 text-orange-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="truncate text-sm font-medium text-slate-500">Sucursales en Nube</p>
                            <p class="text-2xl font-semibold text-slate-900">{{ kpis?.sucursales_nube || 0 }}</p>
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden rounded-2xl bg-white px-6 py-5 shadow-sm ring-1 ring-slate-900/5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 rounded-xl bg-purple-50 p-3">
                            <svg class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="truncate text-sm font-medium text-slate-500">Cuentas Globales</p>
                            <p class="text-2xl font-semibold text-slate-900">{{ kpis?.usuarios_totales || 0 }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="bg-white ring-1 ring-slate-900/5 sm:rounded-2xl shadow-sm p-7">
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-widest text-slate-500 mb-6 flex justify-between items-center">
                            <span>Estado de Base de Datos</span>
                            <span class="px-2.5 py-1 rounded-md bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20 text-[9px] font-semibold">SISTEMA ÓPTIMO</span>
                        </h3>
                        <div class="space-y-5 my-6">
                            <div>
                                <div class="flex justify-between text-xs font-medium mb-2">
                                    <span class="text-slate-600">Capacidad de Nube</span>
                                    <span class="text-sky-600">Carga estable</span>
                                </div>
                                <div class="w-full bg-slate-100 h-2.5 rounded-full overflow-hidden">
                                    <div class="bg-gradient-to-r from-sky-500 to-sky-400 w-[15%] h-full rounded-full"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between text-xs font-medium mb-2">
                                    <span class="text-slate-600">Sincronización de Catálogos</span>
                                    <span class="text-emerald-600">100% fluida</span>
                                </div>
                                <div class="w-full bg-slate-100 h-2.5 rounded-full overflow-hidden">
                                    <div class="bg-gradient-to-r from-emerald-500 to-emerald-400 w-[45%] h-full rounded-full"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-xl ring-1 ring-inset ring-slate-200 mt-6">
                        <p class="text-xs text-slate-500 leading-relaxed">
                            💡 <strong class="text-slate-700">Escalabilidad:</strong> El MRR dinámico se calcula sumando tus planes activos actuales.
                        </p>
                    </div>
                </div>

                <div class="lg:col-span-2 bg-white ring-1 ring-slate-900/5 sm:rounded-2xl shadow-sm p-7">
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-widest text-slate-500 mb-6">Últimas Altas en el Sistema</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-xs font-semibold text-slate-900">ID</th>
                                        <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold text-slate-900">Local</th>
                                        <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold text-slate-900">Plan</th>
                                        <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold text-slate-900">Fecha</th>
                                        <th scope="col" class="px-3 py-3.5 text-right text-xs font-semibold text-slate-900">Estado</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    <tr v-for="c in ultimosComercios" :key="c.id" class="hover:bg-slate-50 transition-colors">
                                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-slate-500 font-mono">#{{ c.id }}</td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm font-medium text-slate-900">{{ c.nombre }}</td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm">
                                            <span class="inline-flex items-center rounded-md bg-slate-50 px-2 py-1 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-500/10 uppercase">{{ c.plan }}</span>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-500">{{ c.fecha }}</td>
                                        <td class="whitespace-nowrap px-3 py-4 text-right">
                                            <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset"
                                                :class="colorEstado(c.estado)">
                                                <svg class="h-1.5 w-1.5" :class="{'fill-emerald-500': c.estado === 'activo', 'fill-amber-500': c.estado === 'trial', 'fill-rose-500': c.estado !== 'activo' && c.estado !== 'trial'}" viewBox="0 0 6 6">
                                                    <circle cx="3" cy="3" r="3" />
                                                </svg>
                                                {{ c.estado }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr v-if="!ultimosComercios?.length">
                                        <td colspan="5" class="text-center py-10 text-sm text-slate-500">No hay comercios registrados.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="pt-6 border-t border-slate-100 flex justify-between items-center mt-4 text-xs text-slate-500">
                        <span class="font-medium">Mostrando últimos 5 registros</span>
                        <Link :href="route('admin.comercios.index')" class="font-semibold text-indigo-600 hover:text-indigo-500">Ver todos →</Link>
                    </div>
                </div>
            </div>
        </div>
    </GlobalAdminLayout>
</template>