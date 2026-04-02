<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    productos: Array
});
</script>

<template>
    <Head title="Gestión de Productos" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Listado de Productos</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white shadow-sm sm:rounded-lg p-6 text-slate-700">
                    
                    <div class="flex justify-end mb-6">
                        <button class="bg-sky-600 text-white px-5 py-2 rounded-lg shadow hover:bg-sky-700 font-bold transition-all">
                            + Nuevo Producto
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-separate border-spacing-y-2">
                            <thead>
                                <tr class="bg-sky-50 text-sky-900 uppercase text-xs">
                                    <th class="p-3 rounded-l-lg">Cód. Barras (SKU)</th>
                                    <th class="p-3">Producto</th>
                                    <th class="p-3 text-center">Imagen</th>
                                    <th class="p-3 text-right">Costo</th>
                                    <th class="p-3 text-right">Venta</th>
                                    <th class="p-3">Categoría</th>
                                    <th class="p-3">Marca</th>
                                    <th class="p-3 text-center">Stock Mín</th>
                                    <th class="p-3 text-center">Estado</th>
                                    <th class="p-3 text-center rounded-r-lg">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="producto in productos" :key="producto.id" 
                                    class="bg-white border-b hover:bg-sky-50 transition-all shadow-sm"
                                    :class="{'opacity-50 grayscale': !producto.estado}">
                                    
                                    <td class="p-3 font-mono font-bold text-sky-800">{{ producto.sku }}</td>
                                    
                                    <td class="p-3 font-semibold text-gray-900">{{ producto.nombre }}</td>
                                    
                                    <td class="p-3">
                                        <div class="flex justify-center">
                                            <img v-if="producto.imagen" :src="'/storage/' + producto.imagen" class="w-12 h-12 object-cover rounded-md border shadow-sm">
                                            <div v-else class="text-gray-400 italic text-[10px]">Sin imagen</div>
                                        </div>
                                    </td>

                                    <td class="p-3 text-right text-gray-500 font-mono">${{ producto.precio_costo }}</td>
                                    <td class="p-3 text-right font-bold text-sky-600 font-mono">${{ producto.precio_venta }}</td>
                                    
                                    <td class="p-3 text-gray-600">
                                        {{ producto.categoria ? producto.categoria.nombreCategoria : '---' }}
                                    </td>
                                    
                                    <td class="p-3 text-gray-600">
                                        {{ producto.marca ? producto.marca.nombreMarca : '---' }}
                                    </td>

                                    <td class="p-3 text-center font-bold">{{ producto.stock_minimo }}</td>

                                    <td class="p-3 text-center">
                                        <span v-if="producto.estado" class="text-emerald-600 bg-emerald-50 px-2 py-1 rounded text-xs font-black uppercase">Activo</span>
                                        <span v-else class="text-rose-600 bg-rose-50 px-2 py-1 rounded text-xs font-black uppercase">Baja</span>
                                    </td>

                                    <td class="p-3">
                                        <div class="flex justify-center gap-2">
                                            <button class="p-1.5 text-sky-500 hover:bg-sky-100 rounded-full transition-colors" title="Ver Detalle">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </button>
                                            <button class="p-1.5 text-amber-500 hover:bg-amber-100 rounded-full transition-colors" title="Editar">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                            <button v-if="producto.estado" class="p-1.5 text-rose-500 hover:bg-rose-100 rounded-full transition-colors" title="Dar de Baja">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                                </svg>
                                            </button>
                                            <button v-else class="p-1.5 text-emerald-500 hover:bg-emerald-100 rounded-full transition-colors" title="Reactivar">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
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
    </AuthenticatedLayout>
</template>