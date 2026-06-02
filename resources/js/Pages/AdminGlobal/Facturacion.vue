<script setup>
import GlobalAdminLayout from '@/Layouts/GlobalAdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
    comercios: { type: Array, default: () => [] },
    resumen: { type: Object, default: () => ({}) },
});

const formatearDinero = (monto) => {
    return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS', maximumFractionDigits: 0 }).format(monto || 0);
};

const badgeEstado = (estado) => {
    switch(estado) {
        case 'Al Día': return 'bg-emerald-50 text-emerald-700 ring-emerald-600/20';
        case 'Vencido': return 'bg-fuchsia-50 text-fuchsia-700 ring-fuchsia-600/20';
        default: return 'bg-rose-50 text-rose-700 ring-rose-600/20';
    }
};

const generarLinkMP = (comercio) => {
    router.post(route('admin.facturacion.link-mp', comercio.id), {}, {
        onSuccess: () => {
            Swal.fire({
                icon: 'info',
                title: 'Próximamente',
                text: 'La generación de links de pago con Mercado Pago estará disponible pronto.',
            });
        },
        onError: (errors) => {
            Swal.fire('Error', Object.values(errors).join('\n'), 'error');
        }
    });
};

const marcarPagado = (comercio) => {
    Swal.fire({
        title: '¿Marcar como pagado?',
        text: `Vas a marcar a ${comercio.nombre} como al día. Se extiende el vencimiento un mes.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, marcar pagado',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#4f46e5',
        cancelButtonColor: '#64748b',
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('admin.facturacion.pagar', comercio.id), {}, {
                onSuccess: () => {
                    Swal.fire({ icon: 'success', title: '¡Pagado!', timer: 1500, showConfirmButton: false });
                },
                onError: (errors) => {
                    Swal.fire('Error', Object.values(errors).join('\n'), 'error');
                }
            });
        }
    });
};
</script>

<template>
    <GlobalAdminLayout>
        <Head title="Panel Global - Facturación" />

        <div class="py-8 px-6 max-w-7xl mx-auto">
            <div class="sm:flex sm:items-center sm:justify-between mb-8">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Control de Cobros</h1>
                    <p class="mt-1 text-sm text-slate-500">Gestión de suscripciones y enlaces de Mercado Pago</p>
                </div>
                <div class="mt-4 sm:mt-0">
                    <button @click="Swal.fire({ icon: 'info', title: 'Próximamente', text: 'La configuración de credenciales de Mercado Pago estará disponible pronto.' })" class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition-all">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
                        </svg>
                        Credenciales MP
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
                <div class="overflow-hidden rounded-2xl bg-white px-6 py-5 shadow-sm ring-1 ring-slate-900/5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 rounded-xl bg-sky-50 p-3">
                            <svg class="h-6 w-6 text-sky-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a6 6 0 0 1 6-6m0 0a6 6 0 0 1 6 6m-6-6a6 6 0 0 1 6-6m0 0a6 6 0 0 1 6 6" />
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="truncate text-sm font-medium text-slate-500">Proyección del Mes</p>
                            <p class="text-2xl font-semibold text-slate-900">{{ formatearDinero(resumen?.total_esperado) }}</p>
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden rounded-2xl bg-white px-6 py-5 shadow-sm ring-1 ring-slate-900/5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 rounded-xl bg-fuchsia-50 p-3">
                            <svg class="h-6 w-6 text-fuchsia-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="truncate text-sm font-medium text-slate-500">Pagos Atrasados</p>
                            <p class="text-2xl font-semibold text-slate-900">{{ formatearDinero(resumen?.total_vencido) }}</p>
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden rounded-2xl bg-white px-6 py-5 shadow-sm ring-1 ring-slate-900/5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 rounded-xl bg-rose-50 p-3">
                            <svg class="h-6 w-6 text-rose-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="truncate text-sm font-medium text-slate-500">Locales en Mora</p>
                            <p class="text-2xl font-semibold text-slate-900">{{ resumen?.clientes_morosos }} comercios</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white ring-1 ring-slate-900/5 sm:rounded-2xl shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100">
                    <h3 class="text-sm font-semibold text-slate-900">Estado de Cuentas por Tenant</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="py-3.5 pl-6 pr-3 text-left text-xs font-semibold text-slate-900">Comercio</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold text-slate-900">Plan</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold text-slate-900">A Cobrar</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold text-slate-900">Vencimiento</th>
                                <th scope="col" class="px-3 py-3.5 text-center text-xs font-semibold text-slate-900">Estado</th>
                                <th scope="col" class="relative py-3.5 pl-3 pr-6 text-right text-xs font-semibold text-slate-900">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-if="!comercios.length">
                                <td colspan="6" class="py-12 text-center text-sm text-slate-500">No hay comercios registrados.</td>
                            </tr>
                            <tr v-for="c in comercios" :key="c.id" class="hover:bg-slate-50 transition-colors">
                                <td class="whitespace-nowrap py-4 pl-6 pr-3 font-medium text-slate-900 text-sm">{{ c.nombre }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm">
                                    <span class="inline-flex items-center rounded-md bg-slate-50 px-2 py-1 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-500/10 uppercase">{{ c.plan }}</span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm font-semibold text-slate-900 font-mono">{{ formatearDinero(c.monto) }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-500">{{ c.vencimiento }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-center">
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset" :class="badgeEstado(c.estado_cobro)">
                                        <svg class="h-1.5 w-1.5" :class="{'fill-emerald-500': c.estado_cobro === 'Al Día', 'fill-fuchsia-500': c.estado_cobro === 'Vencido', 'fill-rose-500': c.estado_cobro !== 'Al Día' && c.estado_cobro !== 'Vencido'}" viewBox="0 0 6 6">
                                            <circle cx="3" cy="3" r="3" />
                                        </svg>
                                        {{ c.estado_cobro }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap py-4 pl-3 pr-6 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button @click="generarLinkMP(c)" class="inline-flex items-center gap-1 rounded-lg bg-sky-50 px-3 py-1.5 text-xs font-bold text-sky-700 ring-1 ring-inset ring-sky-600/20 hover:bg-sky-100 transition-colors">
                                            Link MP
                                        </button>
                                        <button @click="marcarPagado(c)" class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700 ring-1 ring-inset ring-emerald-600/20 hover:bg-emerald-100 transition-colors">
                                            Pagó
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </GlobalAdminLayout>
</template>