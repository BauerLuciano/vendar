<script setup>
import GlobalAdminLayout from '@/Layouts/GlobalAdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    kpis: Object,
    ultimosComercios: Array
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
        case 'activo': return 'bg-[#8cc63f]/10 text-[#8cc63f] border border-[#8cc63f]/20';
        case 'trial': return 'bg-[#00adef]/10 text-[#00adef] border border-[#00adef]/20';
        default: return 'bg-rose-500/10 text-rose-500 border border-rose-500/20';
    }
};
</script>

<template>
    <GlobalAdminLayout>
        <Head title="Métricas Globales | Admin VendAR" />

        <div class="p-6 md:p-8 bg-[#050a15] min-h-[calc(100vh-4rem)] w-full text-slate-200 font-sans flex flex-col justify-between rounded-tl-2xl border-t border-l border-slate-800/50">
            
            <div>
                <div class="mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <h1 class="text-3xl font-black text-white uppercase tracking-tighter italic flex items-center gap-3">
                            <span class="w-3 h-3 bg-[#00adef] rounded-full animate-pulse"></span>
                            Métricas Globales
                        </h1>
                        <p class="text-xs text-slate-500 font-bold uppercase tracking-widest mt-1">
                            Monitoreo financiero y rendimiento de la nube VendAR
                        </p>
                    </div>

                    <Link 
                        :href="route('admin.comercios.index')" 
                        class="bg-[#111c30] hover:bg-slate-800 border border-white/10 text-white px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all"
                    >
                        ➔ Gestionar Comercios
                    </Link>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10 w-full">
                    <div class="bg-[#111c30] border-l-4 border-[#8cc63f] border-y border-r border-white/5 p-6 rounded-r-2xl shadow-xl relative overflow-hidden flex flex-col justify-between">
                        <div class="absolute -right-4 -bottom-4 text-emerald-500/5 text-7xl font-black select-none">💵</div>
                        <div>
                            <span class="text-[10px] font-black tracking-widest text-slate-500 uppercase block mb-1">Facturación Mensual</span>
                            <span class="text-3xl font-black text-white tracking-tight block mb-2">{{ formatearDinero(kpis?.mrr_estimado) }}</span>
                        </div>
                        <span class="text-xs text-[#8cc63f] font-bold flex items-center gap-1 mt-2">↗ Ingreso Recurrente Est.</span>
                    </div>

                    <div class="bg-[#111c30] border-l-4 border-[#00adef] border-y border-r border-white/5 p-6 rounded-r-2xl shadow-xl relative overflow-hidden flex flex-col justify-between">
                        <div class="absolute -right-4 -bottom-4 text-sky-500/5 text-7xl font-black select-none">🏪</div>
                        <div>
                            <span class="text-[10px] font-black tracking-widest text-slate-500 uppercase block mb-1">Locales Activos</span>
                            <div class="flex items-baseline gap-2 mb-2">
                                <span class="text-3xl font-black text-white tracking-tight">{{ kpis?.comercios_activos || 0 }}</span>
                                <span class="text-xs text-slate-500 font-bold">/ {{ kpis?.comercios_totales || 0 }}</span>
                            </div>
                        </div>
                        <div class="w-full bg-slate-900 h-1.5 rounded-full overflow-hidden mt-2">
                            <div class="bg-[#00adef] h-full rounded-full transition-all duration-1000" :style="`width: ${(kpis?.comercios_activos / (kpis?.comercios_totales || 1)) * 100}%`"></div>
                        </div>
                    </div>

                    <div class="bg-[#111c30] border-l-4 border-[#f7941e] border-y border-r border-white/5 p-6 rounded-r-2xl shadow-xl relative overflow-hidden flex flex-col justify-between">
                        <div class="absolute -right-4 -bottom-4 text-orange-500/5 text-7xl font-black select-none">📍</div>
                        <div>
                            <span class="text-[10px] font-black tracking-widest text-slate-500 uppercase block mb-1">Sucursales en Nube</span>
                            <span class="text-3xl font-black text-white tracking-tight block mb-2">{{ kpis?.sucursales_nube || 0 }}</span>
                        </div>
                        <span class="text-xs text-[#f7941e] font-bold mt-2">Puntos de venta desplegados</span>
                    </div>

                    <div class="bg-[#111c30] border-l-4 border-purple-500 border-y border-r border-white/5 p-6 rounded-r-2xl shadow-xl relative overflow-hidden flex flex-col justify-between">
                        <div class="absolute -right-4 -bottom-4 text-purple-500/5 text-7xl font-black select-none">👥</div>
                        <div>
                            <span class="text-[10px] font-black tracking-widest text-slate-500 uppercase block mb-1">Cuentas Globales</span>
                            <span class="text-3xl font-black text-white tracking-tight block mb-2">{{ kpis?.usuarios_totales || 0 }}</span>
                        </div>
                        <span class="text-xs text-purple-400 font-bold mt-2">Dueños y colaboradores</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 w-full">
                    <div class="bg-[#111c30] border border-white/5 p-7 rounded-2xl shadow-xl flex flex-col justify-between h-full">
                        <div>
                            <h3 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-6 flex justify-between items-center">
                                <span>Estado de Base de Datos</span>
                                <span class="px-2.5 py-1 rounded-md bg-[#8cc63f]/10 text-[#8cc63f] text-[9px]">SISTEMA ÓPTIMO</span>
                            </h3>
                            <div class="space-y-5 my-6">
                                <div>
                                    <div class="flex justify-between text-xs font-bold mb-2">
                                        <span class="text-slate-300">Capacidad de Nube</span>
                                        <span class="text-[#00adef]">Carga estable</span>
                                    </div>
                                    <div class="w-full bg-slate-900 h-2.5 rounded-full overflow-hidden p-0.5 border border-white/5">
                                        <div class="bg-gradient-to-r from-[#00adef] to-sky-400 w-[15%] h-full rounded-full animate-pulse"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between text-xs font-bold mb-2">
                                        <span class="text-slate-300">Sincronización de Catálogos</span>
                                        <span class="text-[#8cc63f]">100% fluida</span>
                                    </div>
                                    <div class="w-full bg-slate-900 h-2.5 rounded-full overflow-hidden p-0.5 border border-white/5">
                                        <div class="bg-gradient-to-r from-[#8cc63f] to-emerald-400 w-[45%] h-full rounded-full"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-[#050a15] p-4 rounded-xl border border-white/5 mt-6">
                            <p class="text-[11px] text-slate-400 leading-relaxed">
                                💡 <strong class="text-white font-bold">Escalabilidad:</strong> El MRR dinámico se calcula sumando tus planes activos actuales.
                            </p>
                        </div>
                    </div>

                    <div class="lg:col-span-2 bg-[#111c30] border border-white/5 p-7 rounded-2xl shadow-xl flex flex-col justify-between h-full">
                        <div>
                            <h3 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-6">Últimas Altas en el Sistema</h3>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="border-b border-white/5 text-[10px] font-black text-slate-500 uppercase tracking-widest">
                                            <th class="pb-4 font-medium pr-4 w-16">ID</th>
                                            <th class="pb-4 font-medium pr-4">Local</th>
                                            <th class="pb-4 font-medium pr-4">Plan</th>
                                            <th class="pb-4 font-medium pr-4">Fecha</th>
                                            <th class="pb-4 font-medium text-right">Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-white/5 text-xs font-bold">
                                        <tr v-for="c in ultimosComercios" :key="c.id" class="hover:bg-white/[0.02] transition-colors">
                                            <td class="py-4 pr-4 text-slate-500 font-mono">#{{ c.id }}</td>
                                            <td class="py-4 pr-4 text-white font-black">{{ c.nombre }}</td>
                                            <td class="py-4 pr-4"><span class="text-[10px] font-black uppercase text-slate-300 tracking-wider">{{ c.plan }}</span></td>
                                            <td class="py-4 pr-4 text-slate-500">{{ c.fecha }}</td>
                                            <td class="py-4 text-right">
                                                <span class="px-3 py-1 rounded-md text-[9px] font-black uppercase tracking-widest inline-block" :class="colorEstado(c.estado)">
                                                    {{ c.estado }}
                                                </span>
                                            </td>
                                        </tr>
                                        <tr v-if="!ultimosComercios?.length">
                                            <td colspan="5" class="text-center py-10 text-slate-600 font-normal">No hay comercios registrados.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="pt-6 border-t border-white/5 flex justify-between items-center mt-4 text-slate-500 text-[10px] font-black uppercase tracking-widest">
                            <span>Mostrando últimos 5 registros</span>
                            <Link :href="route('admin.comercios.index')" class="text-[#00adef] hover:underline">Ver todos ➔</Link>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-8 pt-4 border-t border-slate-800/60 text-right text-[9px] font-mono text-slate-600 uppercase tracking-widest">
                Infraestructura Nube VendAR • Nodo Misiones Activo
            </div>

        </div>
    </GlobalAdminLayout>
</template>