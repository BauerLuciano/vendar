<script setup>
import GlobalAdminLayout from '@/Layouts/GlobalAdminLayout.vue'; // Asegurate de que la ruta coincida con tu carpeta de Layouts
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    comercios: Array,
    resumen: Object
});

const formatearDinero = (monto) => {
    return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS', maximumFractionDigits: 0 }).format(monto || 0);
};

const badgeEstado = (estado) => {
    switch(estado) {
        case 'Al Día': return 'bg-[#8cc63f]/10 text-[#8cc63f] border-[#8cc63f]/20';
        case 'Vencido': return 'bg-fuchsia-500/10 text-fuchsia-400 border-fuchsia-500/20 animate-pulse';
        default: return 'bg-rose-500/10 text-rose-500 border-rose-500/20';
    }
};
</script>

<template>
    <GlobalAdminLayout>
        <Head title="Facturación y Cobros | Admin VendAR" />

        <div class="p-8 bg-[#050a15] rounded-3xl shadow-2xl border border-slate-800 text-slate-200 font-sans">
            
            <div class="mb-10 flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-black text-white uppercase tracking-tighter italic flex items-center gap-3">
                        <span class="w-3 h-3 bg-[#8cc63f] rounded-full"></span>
                        Control de Cobros
                    </h1>
                    <p class="text-xs text-slate-500 font-bold uppercase tracking-widest mt-1">
                        Gestión de suscripciones y enlaces de Mercado Pago
                    </p>
                </div>
                
                <button class="bg-[#00adef] hover:bg-[#00adef]/80 text-white font-black px-5 py-2.5 rounded-xl text-xs uppercase tracking-widest shadow-lg shadow-[#00adef]/20 transition-all">
                    ⚙️ Credenciales MP
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
                <div class="bg-[#111c30] border-l-4 border-[#00adef] border-y border-r border-white/5 p-6 rounded-r-2xl shadow-xl">
                    <span class="text-[10px] font-black tracking-widest text-slate-500 uppercase block mb-1">Proyección del Mes</span>
                    <span class="text-3xl font-black text-white tracking-tight block">{{ formatearDinero(resumen?.total_esperado) }}</span>
                </div>

                <div class="bg-[#111c30] border-l-4 border-fuchsia-500 border-y border-r border-white/5 p-6 rounded-r-2xl shadow-xl">
                    <span class="text-[10px] font-black tracking-widest text-slate-500 uppercase block mb-1">Pagos Atrasados</span>
                    <span class="text-3xl font-black text-fuchsia-400 tracking-tight block">{{ formatearDinero(resumen?.total_vencido) }}</span>
                </div>

                <div class="bg-[#111c30] border-l-4 border-rose-500 border-y border-r border-white/5 p-6 rounded-r-2xl shadow-xl">
                    <span class="text-[10px] font-black tracking-widest text-slate-500 uppercase block mb-1">Locales en Mora</span>
                    <span class="text-3xl font-black text-rose-500 tracking-tight block">{{ resumen?.clientes_morosos }} comercios</span>
                </div>
            </div>

            <div class="bg-[#111c30] border border-white/5 p-7 rounded-2xl shadow-xl">
                <h3 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-6">Estado de Cuentas por Tenant</h3>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-white/5 text-[10px] font-black text-slate-500 uppercase tracking-widest">
                                <th class="pb-4 pr-4">Comercio</th>
                                <th class="pb-4 pr-4">Plan</th>
                                <th class="pb-4 pr-4">A Cobrar</th>
                                <th class="pb-4 pr-4">Vencimiento</th>
                                <th class="pb-4 pr-4">Estado</th>
                                <th class="pb-4 text-right">Acción de Cobro</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5 text-xs font-bold">
                            <tr v-for="c in comercios" :key="c.id" class="hover:bg-white/[0.02] transition-colors">
                                <td class="py-4 pr-4 text-white font-black">{{ c.nombre }}</td>
                                <td class="py-4 pr-4 text-slate-400"><span class="text-[10px] uppercase tracking-wider">{{ c.plan }}</span></td>
                                <td class="py-4 pr-4 text-[#8cc63f] font-mono">{{ formatearDinero(c.monto) }}</td>
                                <td class="py-4 pr-4 text-slate-400">{{ c.vencimiento }}</td>
                                <td class="py-4 pr-4">
                                    <span class="px-2.5 py-1 rounded-md text-[9px] font-black uppercase tracking-widest border" :class="badgeEstado(c.estado_cobro)">
                                        {{ c.estado_cobro }}
                                    </span>
                                </td>
                                <td class="py-4 text-right space-x-2">
                                    <button class="bg-[#00adef]/10 hover:bg-[#00adef] text-[#00adef] hover:text-white border border-[#00adef]/30 px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-wider transition-all">
                                        ⚡ Link MP
                                    </button>
                                    <button class="bg-[#8cc63f]/10 hover:bg-[#8cc63f] text-[#8cc63f] hover:text-white border border-[#8cc63f]/30 px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-wider transition-all">
                                        ✓ Pagó
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </GlobalAdminLayout>
</template>