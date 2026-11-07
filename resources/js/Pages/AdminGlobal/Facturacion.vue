<script setup>
import GlobalAdminLayout from '@/Layouts/GlobalAdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    comercios: { type: Array, default: () => [] },
    resumen: { type: Object, default: () => ({}) },
    historial: { type: Array, default: () => [] },
    historial_filtros: { type: Object, default: () => ({}) },
    comercios_filtro: { type: Array, default: () => [] },
    estados_pago: { type: Array, default: () => [] },
    precios_confirmados: { type: Boolean, default: false },
    cobros_habilitados: { type: Boolean, default: false },
    aviso_precios: { type: String, default: '' },
    aviso_pagos_online: { type: String, default: '' },
});

const filtros = reactive({
    comercio_id: props.historial_filtros?.comercio_id ?? '',
    estado: props.historial_filtros?.estado ?? '',
    periodo: props.historial_filtros?.periodo ?? '',
});

const formatearDinero = (monto) => {
    if (monto === null || monto === undefined) return '—';

    return new Intl.NumberFormat('es-AR', {
        style: 'currency',
        currency: 'ARS',
        maximumFractionDigits: 0,
    }).format(monto);
};

const badgeEstado = (estado) => {
    switch (estado) {
        case 'Al Día': return 'bg-emerald-50 text-emerald-700 ring-emerald-600/20';
        case 'Por Vencer': return 'bg-amber-50 text-amber-700 ring-amber-600/20';
        case 'Vencido': return 'bg-fuchsia-50 text-fuchsia-700 ring-fuchsia-600/20';
        case 'Suspendido': return 'bg-rose-50 text-rose-700 ring-rose-600/20';
        default: return 'bg-slate-50 text-slate-600 ring-slate-500/20';
    }
};

const puntoEstado = (estado) => {
    switch (estado) {
        case 'Al Día': return 'fill-emerald-500';
        case 'Por Vencer': return 'fill-amber-500';
        case 'Vencido': return 'fill-fuchsia-500';
        case 'Suspendido': return 'fill-rose-500';
        default: return 'fill-slate-400';
    }
};

const etiquetaEstadoPago = (estado) => {
    const etiquetas = {
        pending: 'Pendiente',
        approved: 'Aprobado',
        rejected: 'Rechazado',
        cancelled: 'Cancelado',
        refunded: 'Reembolsado',
        expired: 'Expirado',
    };

    return etiquetas[estado] ?? estado;
};

const badgeEstadoPago = (estado) => {
    switch (estado) {
        case 'approved': return 'bg-emerald-50 text-emerald-700 ring-emerald-600/20';
        case 'pending': return 'bg-amber-50 text-amber-700 ring-amber-600/20';
        case 'rejected':
        case 'cancelled':
        case 'expired': return 'bg-rose-50 text-rose-700 ring-rose-600/20';
        default: return 'bg-slate-50 text-slate-600 ring-slate-500/20';
    }
};

const aplicarFiltros = () => {
    router.get(route('admin.facturacion'), {
        comercio_id: filtros.comercio_id || undefined,
        estado: filtros.estado || undefined,
        periodo: filtros.periodo || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const limpiarFiltros = () => {
    filtros.comercio_id = '';
    filtros.estado = '';
    filtros.periodo = '';
    aplicarFiltros();
};

const registrarPago = (comercio) => {
    Swal.fire({
        title: 'Registrar pago manual',
        html: `
            <p class="text-sm text-slate-600 mb-3">Comercio: <strong>${comercio.nombre}</strong></p>
            <p class="text-sm text-slate-600 mb-3">Importe configurado de referencia: <strong>${formatearDinero(comercio.precio_configurado)}</strong></p>
            <p class="text-xs text-amber-700 bg-amber-50 rounded-lg p-2">El precio comercial todavía no está confirmado. Este registro sólo debe usarse cuando el negocio habilite los cobros.</p>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Registrar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#4f46e5',
        cancelButtonColor: '#64748b',
        preConfirm: () => {
            return router.post(route('admin.facturacion.pagar', comercio.id), {
                importe: comercio.precio_configurado,
                payment_method: 'efectivo',
            }, {
                preserveScroll: true,
            });
        },
    });
};
</script>

<template>
    <GlobalAdminLayout>
        <Head title="Panel Global - Facturación" />

        <div class="py-8 px-6 max-w-7xl mx-auto">
            <div class="sm:flex sm:items-center sm:justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Control de Cobros</h1>
                    <p class="mt-1 text-sm text-slate-500">Estado de suscripciones, pagos e historial por comercio</p>
                </div>
                <div class="mt-4 sm:mt-0">
                    <span class="inline-flex items-center gap-2 rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-semibold text-slate-500 ring-1 ring-inset ring-slate-200">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-11.25a.75.75 0 00-1.5 0v2.5c0 .199.079.39.22.53l1.5 1.5a.75.75 0 101.06-1.06l-1.28-1.28V6.75z" clip-rule="evenodd" />
                        </svg>
                        {{ aviso_pagos_online }}
                    </span>
                </div>
            </div>

            <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4">
                <div class="flex items-start gap-3">
                    <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-600" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 6a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 6zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                    </svg>
                    <div>
                        <p class="text-sm font-semibold text-amber-800">Precios comerciales pendientes</p>
                        <p class="mt-1 text-sm text-amber-700">{{ aviso_precios }}</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
                <div class="rounded-2xl bg-white px-5 py-4 shadow-sm ring-1 ring-slate-900/5">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Al Día</p>
                    <p class="mt-2 text-2xl font-semibold text-emerald-600">{{ resumen?.al_dia ?? 0 }}</p>
                </div>
                <div class="rounded-2xl bg-white px-5 py-4 shadow-sm ring-1 ring-slate-900/5">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Por Vencer</p>
                    <p class="mt-2 text-2xl font-semibold text-amber-600">{{ resumen?.por_vencer ?? 0 }}</p>
                </div>
                <div class="rounded-2xl bg-white px-5 py-4 shadow-sm ring-1 ring-slate-900/5">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Vencidos</p>
                    <p class="mt-2 text-2xl font-semibold text-fuchsia-600">{{ resumen?.vencidos ?? 0 }}</p>
                </div>
                <div class="rounded-2xl bg-white px-5 py-4 shadow-sm ring-1 ring-slate-900/5">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Suspendidos</p>
                    <p class="mt-2 text-2xl font-semibold text-rose-600">{{ resumen?.suspendidos ?? 0 }}</p>
                </div>
                <div class="rounded-2xl bg-white px-5 py-4 shadow-sm ring-1 ring-slate-900/5">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">En Mora</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ resumen?.en_mora ?? 0 }}</p>
                </div>
            </div>

            <div class="bg-white ring-1 ring-slate-900/5 sm:rounded-2xl shadow-sm overflow-hidden mb-8">
                <div class="p-5 border-b border-slate-100">
                    <h3 class="text-sm font-semibold text-slate-900">Estado de Cuentas por Tenant</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="py-3.5 pl-6 pr-3 text-left text-xs font-semibold text-slate-900">Comercio</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold text-slate-900">Plan</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold text-slate-900">Precio configurado</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold text-slate-900">Vencimiento</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold text-slate-900">Días</th>
                                <th scope="col" class="px-3 py-3.5 text-center text-xs font-semibold text-slate-900">Estado</th>
                                <th scope="col" class="relative py-3.5 pl-3 pr-6 text-right text-xs font-semibold text-slate-900">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-if="!comercios.length">
                                <td colspan="7" class="py-12 text-center text-sm text-slate-500">No hay comercios registrados.</td>
                            </tr>
                            <tr v-for="c in comercios" :key="c.id" class="hover:bg-slate-50 transition-colors">
                                <td class="whitespace-nowrap py-4 pl-6 pr-3 font-medium text-slate-900 text-sm">{{ c.nombre }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm">
                                    <span class="inline-flex items-center rounded-md bg-slate-50 px-2 py-1 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-500/10 uppercase">{{ c.plan }}</span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm">
                                    <div class="flex flex-col">
                                        <span class="font-mono font-semibold text-slate-900">{{ formatearDinero(c.precio_configurado) }}</span>
                                        <span v-if="c.precio_es_referencia" class="text-[10px] font-medium uppercase tracking-wide text-amber-600">Referencia · no oficial</span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-500">{{ c.vencimiento || 'Sin fecha' }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-500">
                                    <span v-if="c.estado_cobro === 'Vencido'" class="text-fuchsia-600 font-medium">{{ c.dias_atraso }} días de atraso</span>
                                    <span v-else-if="c.dias_restantes !== null">{{ c.dias_restantes }} días restantes</span>
                                    <span v-else>—</span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-center">
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset" :class="badgeEstado(c.estado_cobro)">
                                        <svg class="h-1.5 w-1.5" :class="puntoEstado(c.estado_cobro)" viewBox="0 0 6 6">
                                            <circle cx="3" cy="3" r="3" />
                                        </svg>
                                        {{ c.estado_cobro }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap py-4 pl-3 pr-6 text-right">
                                    <button
                                        v-if="cobros_habilitados && precios_confirmados"
                                        @click="registrarPago(c)"
                                        class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700 ring-1 ring-inset ring-emerald-600/20 hover:bg-emerald-100 transition-colors"
                                    >
                                        Registrar pago
                                    </button>
                                    <span v-else class="text-xs text-slate-400">Cobros deshabilitados</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white ring-1 ring-slate-900/5 sm:rounded-2xl shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">Historial de Pagos</h3>
                        <p class="mt-1 text-xs text-slate-500">Registros persistentes de pagos de suscripción. No se muestran pagos inventados.</p>
                    </div>
                    <div class="flex flex-wrap items-end gap-2">
                        <div>
                            <label class="block text-[10px] font-semibold uppercase tracking-wide text-slate-500 mb-1">Comercio</label>
                            <select v-model="filtros.comercio_id" class="rounded-lg border-slate-300 text-xs focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Todos</option>
                                <option v-for="c in comercios_filtro" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-semibold uppercase tracking-wide text-slate-500 mb-1">Estado</label>
                            <select v-model="filtros.estado" class="rounded-lg border-slate-300 text-xs focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Todos</option>
                                <option v-for="e in estados_pago" :key="e" :value="e">{{ etiquetaEstadoPago(e) }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-semibold uppercase tracking-wide text-slate-500 mb-1">Período</label>
                            <input v-model="filtros.periodo" type="month" class="rounded-lg border-slate-300 text-xs focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <button @click="aplicarFiltros" class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-500 transition-colors">Filtrar</button>
                        <button @click="limpiarFiltros" class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-200 transition-colors">Limpiar</button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="py-3.5 pl-6 pr-3 text-left text-xs font-semibold text-slate-900">Fecha</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold text-slate-900">Comercio</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold text-slate-900">Importe</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold text-slate-900">Método</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold text-slate-900">Período abonado</th>
                                <th scope="col" class="px-3 py-3.5 text-center text-xs font-semibold text-slate-900">Estado</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold text-slate-900">Responsable</th>
                                <th scope="col" class="py-3.5 pl-3 pr-6 text-left text-xs font-semibold text-slate-900">Referencia</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-if="!historial.length">
                                <td colspan="8" class="py-12 text-center text-sm text-slate-500">
                                    Todavía no hay pagos registrados. Cuando se habiliten los cobros, el historial aparecerá acá.
                                </td>
                            </tr>
                            <tr v-for="p in historial" :key="p.id" class="hover:bg-slate-50 transition-colors">
                                <td class="whitespace-nowrap py-4 pl-6 pr-3 text-sm text-slate-500">{{ p.fecha || '—' }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm font-medium text-slate-900">{{ p.comercio || '—' }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm font-mono font-semibold text-slate-900">{{ formatearDinero(p.importe) }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-500 capitalize">{{ p.metodo || '—' }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-500">
                                    <span v-if="p.periodo_inicio || p.periodo_fin">{{ p.periodo_inicio || '—' }} → {{ p.periodo_fin || '—' }}</span>
                                    <span v-else>—</span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-center">
                                    <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset" :class="badgeEstadoPago(p.estado)">
                                        {{ etiquetaEstadoPago(p.estado) }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-500">{{ p.responsable || '—' }}</td>
                                <td class="whitespace-nowrap py-4 pl-3 pr-6 text-sm text-slate-500">{{ p.referencia || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </GlobalAdminLayout>
</template>
