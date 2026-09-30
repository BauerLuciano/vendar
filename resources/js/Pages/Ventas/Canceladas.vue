<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    ventas: Object,
});

const irPagina = (url) => {
    if (url) router.get(url, {}, { preserveState: true });
};

const formatearDinero = (valor) =>
    new Intl.NumberFormat('es-AR', {
        style: 'currency',
        currency: 'ARS',
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(Number(valor));

/** Números de página: primera, última y vecinas de la actual. */
const numerosPagina = (paginas, actual) => {
    if (paginas <= 7) {
        return Array.from({ length: paginas }, (_, i) => i + 1);
    }

    const set = new Set([1, paginas, actual - 1, actual, actual + 1]);
    const numeros = [...set].filter((n) => n >= 1 && n <= paginas).sort((a, b) => a - b);

    const salida = [];
    numeros.forEach((n, i) => {
        if (i > 0 && n - numeros[i - 1] > 1) salida.push('...');
        salida.push(n);
    });

    return salida;
};

const paginas = numerosPagina(props.ventas.last_page || 1, props.ventas.current_page || 1);

const linkPrevio = () => (props.ventas.prev_page_url ? props.ventas.links[0] : null);
const linkSiguiente = () => (props.ventas.next_page_url ? props.ventas.links[props.ventas.links.length - 1] : null);
</script>

<template>
    <Head title="Ventas Canceladas | VendAR" />

    <AuthenticatedLayout>
        <div class="py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto bg-slate-50 min-h-screen">

            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4">
                <div>
                    <Link
                        :href="route('ventas.index')"
                        class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 hover:text-sky-600 transition-colors mb-2"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                        Historial de Ventas
                    </Link>
                    <h1 class="text-2xl font-black text-rose-700 uppercase tracking-tight">Ventas Canceladas</h1>
                    <div class="h-1 w-12 bg-rose-500 mt-1"></div>
                </div>

                <span class="bg-rose-600 text-white font-black px-4 py-2.5 rounded-xl text-sm uppercase tracking-widest shadow-lg w-full sm:w-auto text-center">
                    {{ ventas.total }} canceladas
                </span>
            </div>

            <div class="bg-white shadow-xl rounded-2xl border border-rose-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-separate border-spacing-y-2">
                        <thead>
                            <tr class="bg-rose-50 text-rose-900 uppercase text-[10px] font-black tracking-widest">
                                <th class="p-4 text-center rounded-l-xl">Venta</th>
                                <th class="p-4">Fecha</th>
                                <th class="p-4">Cliente</th>
                                <th class="p-4">Método de pago</th>
                                <th class="p-4 text-right">Total</th>
                                <th class="p-4">Motivo</th>
                                <th class="p-4 rounded-r-xl">Cancelación</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="ventas.data.length === 0">
                                <td colspan="7" class="p-12 text-center">
                                    <div class="flex flex-col items-center gap-3 text-slate-300">
                                        <svg class="h-14 w-14 text-rose-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <div>
                                            <p class="text-sm font-bold text-slate-500">No hay ventas canceladas</p>
                                            <p class="text-xs text-slate-400 mt-1">Todavía no se anuló ninguna venta.</p>
                                        </div>
                                    </div>
                                </td>
                            </tr>

                            <tr
                                v-for="v in ventas.data"
                                :key="v.id"
                                class="bg-rose-50 hover:bg-rose-100 transition-colors border-l-4 border-rose-400"
                            >
                                <td class="p-4 text-center">
                                    <span class="font-mono font-black text-rose-700">#{{ v.id }}</span>
                                    <span class="mt-1 block text-[8px] font-black uppercase tracking-widest text-white bg-rose-600 px-2 py-0.5 rounded-full">
                                        Cancelada
                                    </span>
                                </td>
                                <td class="p-4 text-slate-600 font-medium whitespace-nowrap">{{ v.fecha_venta }}</td>
                                <td class="p-4">
                                    <p class="font-bold text-slate-700">{{ v.cliente }}</p>
                                    <p v-if="v.documento" class="text-[10px] text-slate-400 font-mono">{{ v.documento }}</p>
                                    <span
                                        v-if="v.es_cuenta_corriente"
                                        class="mt-1 inline-block text-[8px] font-black uppercase tracking-widest text-sky-700 bg-sky-100 border border-sky-200 px-2 py-0.5 rounded-full"
                                    >Cuenta corriente</span>
                                </td>
                                <td class="p-4 text-slate-600 text-xs font-medium">{{ v.metodo_pago }}</td>
                                <td class="p-4 text-right font-black text-slate-700 font-mono whitespace-nowrap">{{ formatearDinero(v.total) }}</td>
                                <td class="p-4 max-w-[260px]">
                                    <span class="text-[10px] font-black uppercase tracking-widest text-rose-500 block">Motivo</span>
                                    <p class="text-xs font-bold text-rose-800 leading-snug mt-0.5 break-words">{{ v.motivo || 'Sin motivo registrado' }}</p>
                                </td>
                                <td class="p-4 whitespace-nowrap">
                                    <p class="text-xs font-bold text-slate-600">{{ v.cancelada_at || '—' }}</p>
                                    <p v-if="v.cancelada_por" class="text-[10px] text-slate-400 font-medium mt-0.5">por {{ v.cancelada_por }}</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="px-4 py-4 border-t border-rose-100 bg-rose-50/40 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <span class="text-sm text-slate-500 font-medium">
                        Mostrando {{ ventas.from }}-{{ ventas.to }} de {{ ventas.total }} ventas canceladas
                    </span>

                    <div v-if="ventas.last_page > 1" class="flex flex-wrap justify-center items-center gap-1">
                        <button
                            @click="irPagina(linkPrevio()?.url)"
                            :disabled="!ventas.prev_page_url"
                            class="px-3 py-1.5 text-xs font-bold rounded-lg border transition-colors"
                            :class="ventas.prev_page_url
                                ? 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100'
                                : 'opacity-40 cursor-not-allowed bg-slate-50 text-slate-400 border-slate-200'"
                        >
                            ‹ Anterior
                        </button>

                        <template v-for="(n, i) in paginas" :key="`${n}-${i}`">
                            <span v-if="n === '...'" class="px-2 text-slate-400 text-xs font-bold">…</span>
                            <button
                                v-else
                                @click="irPagina(ventas.links.find(l => l.page === n)?.url)"
                                :disabled="n === ventas.current_page"
                                class="px-3 py-1.5 text-xs font-bold rounded-lg border transition-colors"
                                :class="n === ventas.current_page
                                    ? 'bg-rose-600 text-white border-rose-600 shadow-sm'
                                    : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100'"
                            >
                                {{ n }}
                            </button>
                        </template>

                        <button
                            @click="irPagina(linkSiguiente()?.url)"
                            :disabled="!ventas.next_page_url"
                            class="px-3 py-1.5 text-xs font-bold rounded-lg border transition-colors"
                            :class="ventas.next_page_url
                                ? 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100'
                                : 'opacity-40 cursor-not-allowed bg-slate-50 text-slate-400 border-slate-200'"
                        >
                            Siguiente ›
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
