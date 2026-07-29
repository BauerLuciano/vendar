<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    turnos: Array,
    fecha: String,
    periodo: { type: String, default: 'dia' },
    tituloPeriodo: { type: String, default: '' },
});

const formatearDinero = (monto) => {
    return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(monto);
};

const fechaSel = ref(props.fecha);
const periodoSel = ref(props.periodo);
const cargando = ref(false);

const periodos = [
    { value: 'dia', label: 'Día' },
    { value: 'semana', label: 'Semana' },
    { value: 'mes', label: 'Mes' },
];

const cambiarPeriodo = (p) => {
    periodoSel.value = p;
    cargando.value = true;
    router.get(route('cajas.cierre.diario'), { periodo: p, fecha: fechaSel.value }, { preserveState: true, replace: true, onFinish: () => cargando.value = false });
};

const cambiarFecha = () => {
    cargando.value = true;
    router.get(route('cajas.cierre.diario'), { periodo: periodoSel.value, fecha: fechaSel.value }, { preserveState: true, replace: true, onFinish: () => cargando.value = false });
};

const exportarExcel = () => {
    const url = route('cajas.cierre.diario', { periodo: periodoSel.value, fecha: fechaSel.value, exportar: 'excel' });
    window.open(url, '_blank');
};

const totalApertura = () => props.turnos.reduce((s, t) => s + t.monto_apertura, 0);
const totalFacturado = () => props.turnos.reduce((s, t) => s + t.facturado, 0);
const totalCierre = () => props.turnos.reduce((s, t) => s + t.monto_cierre, 0);
const totalDiferencia = () => props.turnos.reduce((s, t) => s + t.diferencia, 0);
</script>

<template>
    <Head title="Cierre Diario" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Módulo</p>
                    <h2 class="text-xl font-semibold text-slate-800 mt-0.5">Cierre {{ tituloPeriodo }}</h2>
                </div>
                <div class="flex items-center gap-3">
                    <Link :href="route('cajas.index')" class="text-[10px] font-bold text-sky-600 hover:text-sky-800 uppercase tracking-widest">← Volver a Cajas</Link>
                </div>
            </div>
        </template>

        <div class="py-8 bg-slate-50 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-5">

                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-4 flex flex-col sm:flex-row items-start sm:items-center gap-3">
                    <div class="flex items-center gap-1 bg-slate-100 rounded-lg p-0.5">
                        <button v-for="p in periodos" :key="p.value" @click="cambiarPeriodo(p.value)"
                            class="px-3 py-1.5 rounded-md text-xs font-bold transition-all"
                            :class="periodoSel === p.value ? 'bg-white text-sky-700 shadow-sm' : 'text-slate-500 hover:text-slate-700'">
                            {{ p.label }}
                        </button>
                    </div>
                    <input type="date" v-model="fechaSel" @change="cambiarFecha" class="border border-slate-200 rounded-lg px-3 py-2 text-xs font-bold text-slate-700 focus:outline-none focus:border-sky-400" />
                    <span class="text-xs text-slate-400 ml-auto">{{ props.turnos.length }} turno(s)</span>
                    <button @click="exportarExcel" class="bg-emerald-600 text-white px-4 py-2 rounded-lg text-[10px] font-black uppercase tracking-widest hover:bg-emerald-700 transition-colors shadow-sm flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                        Excel
                    </button>
                </div>

                <div v-if="cargando" class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden p-6">
                    <div class="space-y-4 animate-pulse">
                        <div v-for="n in 4" :key="n" class="flex items-center gap-4">
                            <div class="h-4 w-24 bg-slate-100 rounded"></div>
                            <div class="h-4 w-20 bg-slate-100 rounded"></div>
                            <div class="h-4 w-28 bg-slate-100 rounded"></div>
                            <div class="h-4 w-16 bg-slate-100 rounded ml-auto"></div>
                            <div class="h-4 w-20 bg-slate-100 rounded ml-auto"></div>
                            <div class="h-4 w-16 bg-slate-100 rounded ml-auto"></div>
                            <div class="h-4 w-16 bg-slate-100 rounded ml-auto"></div>
                            <div class="h-4 w-14 bg-slate-100 rounded ml-auto"></div>
                        </div>
                    </div>
                </div>

                <div v-if="!cargando" class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-[10px] uppercase tracking-widest text-slate-400 bg-slate-50 border-b border-slate-200">
                                <th class="p-4 font-black text-left">Sucursal</th>
                                <th class="p-4 font-black text-left">Caja</th>
                                <th class="p-4 font-black text-left">Cajero</th>
                                <th class="p-4 font-black text-right">Apertura</th>
                                <th class="p-4 font-black text-right">Facturado</th>
                                <th class="p-4 font-black text-right">Cierre</th>
                                <th class="p-4 font-black text-right">Diferencia</th>
                                <th class="p-4 font-black text-center">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="turnos.length === 0">
                                <td colspan="8" class="p-10 text-center text-slate-400 font-bold text-sm">No hay turnos para este período.</td>
                            </tr>
                            <tr v-for="t in turnos" :key="t.id" class="hover:bg-slate-50/50 transition-colors">
                                <td class="p-4 font-bold text-slate-700">{{ t.sucursal }}</td>
                                <td class="p-4 text-slate-600">{{ t.caja }}</td>
                                <td class="p-4 text-slate-600">{{ t.cajero }}</td>
                                <td class="p-4 text-right font-mono font-bold text-slate-700">{{ formatearDinero(t.monto_apertura) }}</td>
                                <td class="p-4 text-right font-mono font-bold text-emerald-600">{{ formatearDinero(t.facturado) }}</td>
                                <td class="p-4 text-right font-mono font-bold text-slate-700">{{ formatearDinero(t.monto_cierre) }}</td>
                                <td class="p-4 text-right font-mono font-bold"
                                    :class="t.diferencia < 0 ? 'text-red-600' : t.diferencia > 0 ? 'text-amber-600' : 'text-slate-400'">
                                    {{ formatearDinero(t.diferencia) }}
                                </td>
                                <td class="p-4 text-center">
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-widest"
                                          :class="t.estado === 'Cerrado' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">
                                        {{ t.estado }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="turnos.length > 0" class="bg-slate-50 border-t-2 border-slate-200">
                            <tr class="text-[10px] uppercase tracking-widest text-slate-500 font-black">
                                <td colspan="3" class="p-4 text-right">TOTALES</td>
                                <td class="p-4 text-right font-mono">{{ formatearDinero(totalApertura()) }}</td>
                                <td class="p-4 text-right font-mono text-emerald-700">{{ formatearDinero(totalFacturado()) }}</td>
                                <td class="p-4 text-right font-mono">{{ formatearDinero(totalCierre()) }}</td>
                                <td class="p-4 text-right font-mono"
                                    :class="totalDiferencia() < 0 ? 'text-red-600' : totalDiferencia() > 0 ? 'text-amber-600' : 'text-slate-400'">
                                    {{ formatearDinero(totalDiferencia()) }}
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
