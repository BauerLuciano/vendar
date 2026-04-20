<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
    sugerencias: Array // Cada item tiene objetos dentro: producto, origen, destino
});

const aprobarTransferencia = (sugerencia) => {
    Swal.fire({
        title: '¿Confirmar transferencia?',
        // Accedemos a .nombre de cada objeto para que el cartel se vea bien
        text: `Se moverán ${sugerencia.cantidad} unidades de ${sugerencia.producto.nombre} desde ${sugerencia.origen.nombre} a ${sugerencia.destino.nombre}.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981', // Un verde esmeralda más lindo
        cancelButtonColor: '#ef4444',
        confirmButtonText: 'Sí, aprobar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            // Enviamos el ID de la sugerencia al controlador
            router.post(route('transferencias.aprobar', sugerencia.id), {}, {
                onSuccess: () => {
                    Swal.fire('¡Logrado!', 'El stock ha sido nivelado correctamente.', 'success');
                },
                onError: () => {
                    Swal.fire('Error', 'Hubo un problema al procesar el movimiento.', 'error');
                }
            });
        }
    });
};
</script>

<template>
    <Head title="Transferencias Sugeridas" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-bold text-xl text-slate-800 leading-tight uppercase tracking-tight">
                📦 Optimización de Stock: <span class="text-indigo-600">Transferencias Sugeridas</span>
            </h2>
        </template>

        <div class="py-12 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
            
            <div class="mb-8 bg-indigo-50 border-l-4 border-indigo-400 p-4 rounded-r-2xl shadow-sm">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-indigo-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-indigo-700 font-medium">
                            El sistema detectó faltantes que pueden cubrirse con excedentes de otras sucursales.
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100 text-[10px] uppercase tracking-[0.2em] text-slate-400 font-black">
                            <th class="p-5">Producto</th>
                            <th class="p-5 text-center">Sucursal Origen</th>
                            <th class="p-5 text-center">→</th>
                            <th class="p-5 text-center">Sucursal Destino</th>
                            <th class="p-5 text-center">Cantidad</th>
                            <th class="p-5 text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="item in sugerencias" :key="item.id" class="hover:bg-slate-50/50 transition-colors">
                            <td class="p-5">
                                <div class="font-bold text-slate-800 text-sm">{{ item.producto.nombre }}</div>
                                <div class="text-[10px] text-slate-400 uppercase font-black tracking-widest">Ajuste de Negativo</div>
                            </td>
                            <td class="p-5 text-center">
                                <span class="bg-slate-100 text-slate-600 px-3 py-1 rounded-lg text-xs font-bold">
                                    {{ item.origen.nombre }}
                                </span>
                            </td>
                            <td class="p-5 text-center text-indigo-400 font-black">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </td>
                            <td class="p-5 text-center">
                                <span class="bg-indigo-50 text-indigo-600 px-3 py-1 rounded-lg text-xs font-bold border border-indigo-100">
                                    {{ item.destino.nombre }}
                                </span>
                            </td>
                            <td class="p-5 text-center font-black text-slate-700">
                                {{ item.cantidad }}
                            </td>
                            <td class="p-5 text-right">
                                <button 
                                    @click="aprobarTransferencia(item)"
                                    class="bg-emerald-500 hover:bg-emerald-600 text-white text-[10px] font-black uppercase tracking-widest px-5 py-2.5 rounded-xl shadow-lg shadow-emerald-100 transition-all hover:scale-105 active:scale-95"
                                >
                                    Aprobar
                                </button>
                            </td>
                        </tr>

                        <tr v-if="sugerencias.length === 0">
                            <td colspan="6" class="p-10 text-center">
                                <div class="text-slate-300 mb-2 font-bold uppercase tracking-widest">Stock Equilibrado</div>
                                <p class="text-xs text-slate-400">No se detectaron sucursales en negativo con stock disponible en otras.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>