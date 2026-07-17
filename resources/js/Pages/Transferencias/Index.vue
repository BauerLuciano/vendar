<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    sugerencias: Array,
    enTransito: Array,
    historial: Array,
});

const pestañaActiva = ref('sugerencias');

const despacharTransferencia = (sugerencia) => {
    Swal.fire({
        title: '¿Despachar transferencia?',
        html: `Se descontarán <b>${sugerencia.cantidad} unidades</b> de <b>${sugerencia.producto.nombre}</b> desde <b>${sugerencia.origen.nombre}</b>.<br><br>El stock quedará en tránsito hasta que la sucursal destino lo reciba.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#f59e0b',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Sí, despachar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('transferencias.despachar', sugerencia.id), {}, {
                onSuccess: () => {
                    Swal.fire({
                        title: '¡Despachado!',
                        text: 'La mercadería está en tránsito.',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });
                },
                onError: (errors) => {
                    const errorMsg = errors.error || 'Hubo un problema al despachar.';
                    Swal.fire('Error', errorMsg, 'error');
                }
            });
        }
    });
};

const recibirTransferencia = (item) => {
    Swal.fire({
        title: '¿Recibir transferencia?',
        html: `Se sumarán <b>${item.cantidad} unidades</b> de <b>${item.producto.nombre}</b> al stock de <b>${item.destino.nombre}</b>.<br><br>Origen: ${item.origen.nombre}`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Sí, recibir',
        cancelButtonText: 'Cancelar',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('transferencias.recibir', item.id), {}, {
                onSuccess: () => {
                    Swal.fire({
                        title: '¡Recibido!',
                        text: 'El stock fue actualizado correctamente.',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });
                },
                onError: (errors) => {
                    const errorMsg = errors.error || 'Hubo un problema al recibir.';
                    Swal.fire('Error', errorMsg, 'error');
                }
            });
        }
    });
};

const cancelarTransferencia = (item) => {
    Swal.fire({
        title: '¿Cancelar transferencia?',
        html: `Se cancelará la transferencia de <b>${item.producto.nombre}</b> (${item.cantidad} unidades).<br><br>Esta acción no se puede deshacer.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Sí, cancelar',
        cancelButtonText: 'No, volver',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('transferencias.cancelar', item.id), {}, {
                onSuccess: () => {
                    Swal.fire({
                        title: 'Cancelada',
                        text: 'La transferencia fue cancelada.',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });
                },
                onError: (errors) => {
                    const errorMsg = errors.error || 'No se pudo cancelar.';
                    Swal.fire('Error', errorMsg, 'error');
                }
            });
        }
    });
};

const formatearFecha = (fecha) => {
    return new Date(fecha).toLocaleString('es-AR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const estadoBadge = (estado) => {
    const map = {
        pendiente: { label: 'Pendiente', classes: 'bg-amber-100 text-amber-700 border border-amber-200' },
        en_transito: { label: 'En Tránsito', classes: 'bg-blue-100 text-blue-700 border border-blue-200' },
        recibida: { label: 'Recibida', classes: 'bg-emerald-100 text-emerald-700 border border-emerald-200' },
        cancelada: { label: 'Cancelada', classes: 'bg-slate-100 text-slate-500 border border-slate-200' },
        rechazada: { label: 'Rechazada', classes: 'bg-rose-100 text-rose-700 border border-rose-200' },
    };
    return map[estado] || { label: estado, classes: 'bg-slate-100 text-slate-500' };
};
</script>

<template>
    <Head title="Transferencias Sugeridas" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-bold text-xl text-slate-800 leading-tight uppercase tracking-tight">
                📦 Optimización de Stock: <span class="text-indigo-600">Transferencias</span>
            </h2>
        </template>

        <div class="py-12 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
            
            <div class="mb-6 bg-indigo-50 border-l-4 border-indigo-400 p-4 rounded-r-2xl shadow-sm">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-indigo-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-indigo-700 font-medium">
                            El sistema detecta faltantes que pueden cubrirse con excedentes de otras sucursales. Despachá el stock y la sucursal destino lo recibe.
                        </p>
                    </div>
                </div>
            </div>

            <div class="flex gap-6 mb-6 border-b border-slate-200 px-2">
                <button 
                    @click="pestañaActiva = 'sugerencias'"
                    :class="[pestañaActiva === 'sugerencias' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300']"
                    class="pb-3 border-b-2 font-bold text-sm transition-all uppercase tracking-widest"
                >
                    Por Despachar ({{ sugerencias.length }})
                </button>
                <button 
                    @click="pestañaActiva = 'transito'"
                    :class="[pestañaActiva === 'transito' ? 'border-blue-500 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300']"
                    class="pb-3 border-b-2 font-bold text-sm transition-all uppercase tracking-widest"
                >
                    En Tránsito ({{ enTransito.length }})
                </button>
                <button 
                    @click="pestañaActiva = 'historial'"
                    :class="[pestañaActiva === 'historial' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300']"
                    class="pb-3 border-b-2 font-bold text-sm transition-all uppercase tracking-widest"
                >
                    Historial
                </button>
            </div>

            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100 text-[10px] uppercase tracking-[0.2em] text-slate-400 font-black">
                            <th class="p-5">Producto</th>
                            <th class="p-5 text-center">Origen</th>
                            <th class="p-5 text-center">→</th>
                            <th class="p-5 text-center">Destino</th>
                            <th class="p-5 text-center">Cantidad</th>
                            <th v-if="pestañaActiva === 'historial'" class="p-5 text-center">Estado</th>
                            <th v-if="pestañaActiva === 'historial'" class="p-5 text-center">Fecha</th>
                            <th v-if="pestañaActiva === 'sugerencias'" class="p-5 text-right">Acción</th>
                            <th v-if="pestañaActiva === 'transito'" class="p-5 text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        
                        <!-- PESTAÑA: POR DESPACHAR (pendientes, soy origen) -->
                        <template v-if="pestañaActiva === 'sugerencias'">
                            <tr v-for="item in sugerencias" :key="item.id" class="hover:bg-slate-50/50 transition-colors">
                                <td class="p-5">
                                    <div class="font-bold text-slate-800 text-sm">{{ item.producto.nombre }}</div>
                                    <div class="text-[10px] text-amber-500 uppercase font-black tracking-widest">A Despachar</div>
                                </td>
                                <td class="p-5 text-center">
                                    <span class="bg-slate-100 text-slate-600 px-3 py-1 rounded-lg text-xs font-bold">{{ item.origen.nombre }}</span>
                                </td>
                                <td class="p-5 text-center text-indigo-400 font-black">→</td>
                                <td class="p-5 text-center">
                                    <span class="bg-indigo-50 text-indigo-600 px-3 py-1 rounded-lg text-xs font-bold border border-indigo-100">{{ item.destino.nombre }}</span>
                                </td>
                                <td class="p-5 text-center font-black text-slate-700">{{ item.cantidad }}</td>
                                <td class="p-5 text-right space-x-2">
                                    <button 
                                        @click="cancelarTransferencia(item)"
                                        class="bg-slate-200 hover:bg-slate-300 text-slate-600 text-[10px] font-black uppercase tracking-widest px-4 py-2.5 rounded-xl transition-all"
                                    >
                                        Cancelar
                                    </button>
                                    <button 
                                        @click="despacharTransferencia(item)"
                                        class="bg-amber-500 hover:bg-amber-600 text-white text-[10px] font-black uppercase tracking-widest px-5 py-2.5 rounded-xl shadow-lg shadow-amber-100 transition-all hover:scale-105 active:scale-95"
                                    >
                                        Despachar
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="sugerencias.length === 0">
                                <td colspan="6" class="p-10 text-center">
                                    <div class="text-slate-300 mb-2 font-bold uppercase tracking-widest">Sin pendientes</div>
                                    <p class="text-xs text-slate-400">No hay transferencias para despachar desde esta sucursal.</p>
                                </td>
                            </tr>
                        </template>

                        <!-- PESTAÑA: EN TRÁNSITO (en_transito, soy destino) -->
                        <template v-if="pestañaActiva === 'transito'">
                            <tr v-for="item in enTransito" :key="'t-'+item.id" class="hover:bg-blue-50/30 transition-colors">
                                <td class="p-5">
                                    <div class="font-bold text-slate-800 text-sm">{{ item.producto.nombre }}</div>
                                    <div class="text-[10px] text-blue-500 uppercase font-black tracking-widest">En Tránsito</div>
                                </td>
                                <td class="p-5 text-center">
                                    <span class="bg-slate-100 text-slate-600 px-3 py-1 rounded-lg text-xs font-bold">{{ item.origen.nombre }}</span>
                                </td>
                                <td class="p-5 text-center text-blue-400 font-black">→</td>
                                <td class="p-5 text-center">
                                    <span class="bg-blue-50 text-blue-600 px-3 py-1 rounded-lg text-xs font-bold border border-blue-100">{{ item.destino.nombre }}</span>
                                </td>
                                <td class="p-5 text-center font-black text-slate-700">{{ item.cantidad }}</td>
                                <td class="p-5 text-right">
                                    <button 
                                        @click="recibirTransferencia(item)"
                                        class="bg-emerald-500 hover:bg-emerald-600 text-white text-[10px] font-black uppercase tracking-widest px-5 py-2.5 rounded-xl shadow-lg shadow-emerald-100 transition-all hover:scale-105 active:scale-95"
                                    >
                                        Recibir
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="enTransito.length === 0">
                                <td colspan="6" class="p-10 text-center">
                                    <div class="text-slate-300 mb-2 font-bold uppercase tracking-widest">Sin tránsitos</div>
                                    <p class="text-xs text-slate-400">No hay transferencias en tránsito hacia esta sucursal.</p>
                                </td>
                            </tr>
                        </template>

                        <!-- PESTAÑA: HISTORIAL -->
                        <template v-if="pestañaActiva === 'historial'">
                            <tr v-for="item in historial" :key="'h-'+item.id" class="hover:bg-slate-50/50 transition-colors bg-slate-50/30">
                                <td class="p-5">
                                    <div class="font-bold text-slate-600 text-sm">{{ item.producto.nombre }}</div>
                                </td>
                                <td class="p-5 text-center text-slate-500 text-xs">{{ item.origen.nombre }}</td>
                                <td class="p-5 text-center text-slate-400 font-black">→</td>
                                <td class="p-5 text-center text-slate-500 text-xs">{{ item.destino.nombre }}</td>
                                <td class="p-5 text-center font-bold text-slate-600">{{ item.cantidad }}</td>
                                <td class="p-5 text-center">
                                    <span :class="estadoBadge(item.estado).classes" class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-widest">
                                        {{ estadoBadge(item.estado).label }}
                                    </span>
                                </td>
                                <td class="p-5 text-center text-slate-400 text-xs font-medium">
                                    {{ formatearFecha(item.updated_at) }}
                                </td>
                            </tr>
                            <tr v-if="historial.length === 0">
                                <td colspan="7" class="p-10 text-center text-slate-400">Aún no hay transferencias en el historial.</td>
                            </tr>
                        </template>

                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
