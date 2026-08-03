<script setup>
import GlobalAdminLayout from '@/Layouts/GlobalAdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
    solicitudes: { type: Array, default: () => [] },
});

const escapeHtml = (str) => {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
};

const abrirModalAprobar = (solicitud) => {
    Swal.fire({
        title: 'Aprobar Solicitud',
        html: `<p class="text-sm mb-3">Se aprobará a <strong>${escapeHtml(solicitud.nombre)}</strong> (${escapeHtml(solicitud.email)})</p>`,
        input: 'text',
        inputLabel: 'Nombre del Comercio',
        inputValue: solicitud.nombre_comercio ?? '',
        inputPlaceholder: 'Ej: Kiosco 24hs',
        inputAttributes: {
            autocomplete: 'off',
        },
        showCancelButton: true,
        confirmButtonText: '✓ Aprobar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#4f46e5',
        cancelButtonColor: '#64748b',
        inputValidator: (value) => {
            if (!value?.trim()) return 'El nombre del comercio es obligatorio';
            return undefined;
        },
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('admin.solicitudes.aprobar', solicitud.id), {
                nombre_comercio: result.value,
            }, {
                onSuccess: () => {
                    Swal.fire({ icon: 'success', title: '¡Aprobado!', timer: 1500, showConfirmButton: false });
                },
                onError: (errors) => {
                    Swal.fire('Error', Object.values(errors).join('\n'), 'error');
                }
            });
        }
    });
};

const confirmarRechazo = (solicitud) => {
    Swal.fire({
        title: '¿Rechazar solicitud?',
        text: `Se eliminará la cuenta de ${solicitud.nombre}. Esta acción no se puede deshacer.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, rechazar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#e11d48',
        cancelButtonColor: '#64748b',
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('admin.solicitudes.rechazar', solicitud.id), {}, {
                onSuccess: () => {
                    Swal.fire({ icon: 'success', title: 'Rechazado', timer: 1500, showConfirmButton: false });
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
    <Head title="Panel Global - Solicitudes" />

    <GlobalAdminLayout>
        <div class="py-8 px-6 max-w-7xl mx-auto">
            <div class="sm:flex sm:items-center sm:justify-between mb-8">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Solicitudes Pendientes</h1>
                    <p class="mt-1 text-sm text-slate-500">{{ solicitudes.length }} {{ solicitudes.length === 1 ? 'solicitud esperando' : 'solicitudes esperando' }} aprobación</p>
                </div>
                <div class="mt-4 sm:mt-0">
                    <Link :href="route('admin.comercios.index')" class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 transition-all">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M17 10a.75.75 0 01-.75.75H5.612l4.158 3.96a.75.75 0 11-1.04 1.08l-5.5-5.25a.75.75 0 010-1.08l5.5-5.25a.75.75 0 111.04 1.08L5.612 9.25H16.25A.75.75 0 0117 10z" clip-rule="evenodd" />
                        </svg>
                        Volver a Comercios
                    </Link>
                </div>
            </div>

            <div v-if="solicitudes.length === 0" class="flex flex-col items-center justify-center py-20 text-center bg-white ring-1 ring-slate-900/5 sm:rounded-2xl shadow-sm">
                <div class="w-20 h-20 bg-emerald-50 rounded-3xl flex items-center justify-center text-4xl mb-5">✅</div>
                <h3 class="text-lg font-bold text-slate-900 mb-2">Todo al día</h3>
                <p class="text-slate-500 text-sm">No hay solicitudes pendientes de aprobación.</p>
            </div>

            <div v-else class="bg-white ring-1 ring-slate-900/5 sm:rounded-2xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="py-3.5 pl-6 pr-3 text-left text-xs font-semibold text-slate-900">Usuario</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold text-slate-900">Comercio</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold text-slate-900">Email</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold text-slate-900">Plan</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold text-slate-900">Fecha</th>
                                <th scope="col" class="relative py-3.5 pl-3 pr-6 text-right text-xs font-semibold text-slate-900">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-for="s in solicitudes" :key="s.id" class="hover:bg-slate-50 transition-colors">
                                <td class="whitespace-nowrap py-4 pl-6 pr-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-full bg-indigo-50 border border-indigo-200 flex items-center justify-center text-indigo-600 text-xs font-bold shrink-0">
                                            {{ (s.nombre ?? '?').charAt(0).toUpperCase() }}
                                        </div>
                                        <div class="font-medium text-slate-900">{{ s.nombre }}</div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm font-medium text-slate-700">
                                    <template v-if="s.nombre_comercio">{{ s.nombre_comercio }}</template>
                                    <span v-else class="text-slate-400">—</span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-500">{{ s.email }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm">
                                    <span class="inline-flex items-center rounded-md bg-slate-50 px-2 py-1 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-500/10 uppercase">
                                        {{ s.plan_deseado }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-500 font-mono">{{ s.fecha_registro }}</td>
                                <td class="whitespace-nowrap py-4 pl-3 pr-6 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            @click="abrirModalAprobar(s)"
                                            class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700 ring-1 ring-inset ring-emerald-600/20 hover:bg-emerald-100 transition-colors"
                                        >
                                            Aprobar
                                        </button>
                                        <button
                                            @click="confirmarRechazo(s)"
                                            class="inline-flex items-center gap-1 rounded-lg bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-700 ring-1 ring-inset ring-rose-600/20 hover:bg-rose-100 transition-colors"
                                        >
                                            Rechazar
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
