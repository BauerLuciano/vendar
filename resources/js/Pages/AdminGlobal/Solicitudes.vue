<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

defineProps({
    solicitudes: Array,
});

const formAprobar = useForm({
    nombre_comercio: '',
});

const abrirModalAprobar = (solicitud) => {
    formAprobar.nombre_comercio = solicitud.nombre;
    Swal.fire({
        title: 'Aprobar Solicitud',
        html: `
            <p class="text-slate-400 text-sm mb-3">Se aprobará a <strong class="text-white">${solicitud.nombre}</strong> (${solicitud.email})</p>
            <label class="block text-left text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Nombre del Comercio</label>
            <input id="nombre_comercio" class="w-full bg-[#111c30] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-[#00adef]/50" value="${solicitud.nombre}" />
        `,
        showCancelButton: true,
        confirmButtonText: '✓ Aprobar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#8cc63f',
        cancelButtonColor: '#334155',
        background: '#0f1929',
        color: '#fff',
        preConfirm: () => {
            const nombre = document.getElementById('nombre_comercio').value;
            if (!nombre.trim()) {
                Swal.showValidationMessage('El nombre del comercio es obligatorio');
                return false;
            }
            return nombre;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('admin.solicitudes.aprobar', solicitud.id), {
                nombre_comercio: result.value,
            }, {
                onSuccess: () => {
                    Swal.fire({ icon: 'success', title: '¡Aprobado!', background: '#0f1929', color: '#fff', timer: 1500, showConfirmButton: false });
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
        cancelButtonColor: '#334155',
        background: '#0f1929',
        color: '#fff',
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('admin.solicitudes.rechazar', solicitud.id));
        }
    });
};
</script>

<template>
    <Head title="Solicitudes de Comercios | VendAR" />

    <div class="min-h-screen bg-[#050a15] flex">
        <div class="flex-1 p-8 overflow-auto">
            <div class="max-w-6xl mx-auto">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h1 class="text-2xl font-black text-white uppercase tracking-tight">Solicitudes Pendientes</h1>
                        <p class="text-slate-500 text-xs mt-1">{{ solicitudes.length }} {{ solicitudes.length === 1 ? 'solicitud esperando' : 'solicitudes esperando' }} aprobación</p>
                    </div>
                    <Link :href="route('admin.comercios.index')" class="text-[11px] font-bold text-slate-400 hover:text-white bg-white/5 hover:bg-white/10 border border-white/10 rounded-xl px-4 py-2 transition-all">
                        ← Volver a Comercios
                    </Link>
                </div>

                <div v-if="solicitudes.length === 0" class="flex flex-col items-center justify-center py-20 text-center">
                    <div class="w-20 h-20 bg-[#8cc63f]/10 border border-[#8cc63f]/20 rounded-3xl flex items-center justify-center text-4xl mb-5">✅</div>
                    <h3 class="text-lg font-black text-white mb-2">Todo al día</h3>
                    <p class="text-slate-500 text-sm">No hay solicitudes pendientes de aprobación.</p>
                </div>

                <div v-else class="bg-[#0f1929] border border-white/6 rounded-2xl overflow-hidden">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-white/6">
                                <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-500">Usuario</th>
                                <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-500">Email</th>
                                <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-500">Plan</th>
                                <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-500">Fecha</th>
                                <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-500 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="s in solicitudes" :key="s.id" class="border-b border-white/4 hover:bg-white/[0.02] transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-full bg-[#00adef]/15 border border-[#00adef]/30 flex items-center justify-center text-[#00adef] text-xs font-black shrink-0">
                                            {{ s.nombre.charAt(0).toUpperCase() }}
                                        </div>
                                        <span class="text-sm font-bold text-white">{{ s.nombre }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-xs text-slate-400">{{ s.email }}</td>
                                <td class="px-6 py-4">
                                    <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-full bg-[#f7941e]/15 text-[#f7941e] border border-[#f7941e]/20">
                                        {{ s.plan_deseado }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-xs text-slate-500 font-mono">{{ s.fecha_registro }}</td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            @click="abrirModalAprobar(s)"
                                            class="text-[10px] font-black uppercase tracking-wider px-3 py-1.5 rounded-lg bg-[#8cc63f]/15 text-[#8cc63f] border border-[#8cc63f]/25 hover:bg-[#8cc63f] hover:text-white transition-all"
                                        >
                                            ✓ Aprobar
                                        </button>
                                        <button
                                            @click="confirmarRechazo(s)"
                                            class="text-[10px] font-black uppercase tracking-wider px-3 py-1.5 rounded-lg bg-rose-500/15 text-rose-500 border border-rose-500/25 hover:bg-rose-600 hover:text-white transition-all"
                                        >
                                            ✕ Rechazar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>
