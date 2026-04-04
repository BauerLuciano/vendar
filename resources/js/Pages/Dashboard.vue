<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

// Atajamos las variables que manda el backend
const props = defineProps({
    deudaTotal: {
        type: Number,
        default: 0
    },
    productosBajoStock: {
        type: Array,
        default: () => []
    }
});

// Función para formatear plata (Ej: $ 15.000,00)
const formatearDinero = (monto) => {
    return new Intl.NumberFormat('es-AR', {
        style: 'currency',
        currency: 'ARS'
    }).format(monto);
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <div class="py-6 px-4 sm:px-6 lg:px-8 bg-slate-50 min-h-screen">
            <div class="mb-8">
                <h1 class="text-2xl font-black text-slate-800 uppercase tracking-tight">Panel de Control</h1>
                <div class="h-1 w-12 bg-sky-500 mt-1"></div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <div class="lg:col-span-1">
                    <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-200 relative overflow-hidden group">
                        <div class="absolute -right-6 -top-6 bg-rose-50 w-32 h-32 rounded-full z-0 transition-transform group-hover:scale-110"></div>
                        
                        <div class="relative z-10 flex flex-col h-full justify-between">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="w-12 h-12 bg-rose-100 text-rose-500 rounded-xl flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <h2 class="text-sm font-black text-slate-500 uppercase tracking-widest">Deuda en la calle</h2>
                            </div>
                            
                            <div>
                                <p class="text-4xl font-black text-slate-800 tracking-tighter">
                                    {{ formatearDinero(deudaTotal) }}
                                </p>
                                <p class="text-xs text-slate-400 font-bold mt-2">Saldo total pendiente de cobro</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-2">
                    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden flex flex-col h-full">
                        <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 bg-amber-100 text-amber-500 rounded-lg flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                </div>
                                <h2 class="text-lg font-black text-slate-800 uppercase tracking-tight">Alertas de Stock</h2>
                            </div>
                            <Link :href="route('productos.index')" class="text-xs font-bold text-sky-600 hover:text-sky-800 uppercase tracking-widest transition-colors">
                                Ver Inventario &rarr;
                            </Link>
                        </div>

                        <div class="flex-1 overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="text-[10px] uppercase tracking-widest text-slate-400 bg-white border-b border-slate-100">
                                        <th class="p-4 font-black">Producto</th>
                                        <th class="p-4 font-black">Sucursal</th>
                                        <th class="p-4 font-black text-center">Stock Actual</th>
                                        <th class="p-4 font-black text-center">Mínimo</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                    <tr v-if="productosBajoStock.length === 0">
                                        <td colspan="4" class="p-8 text-center">
                                            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 text-emerald-600 text-xs font-bold uppercase tracking-widest">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
                                                Stock en niveles óptimos
                                            </span>
                                        </td>
                                    </tr>
                                    <tr v-for="(item, index) in productosBajoStock" :key="index" class="hover:bg-slate-50/50 transition-colors">
                                        <td class="p-4 font-bold text-slate-700">{{ item.producto }}</td>
                                        <td class="p-4 text-slate-500 text-sm">{{ item.sucursal }}</td>
                                        <td class="p-4 text-center">
                                            <span class="px-2 py-1 bg-rose-100 text-rose-700 font-black rounded-md shadow-sm">
                                                {{ item.cantidad_fisica }}
                                            </span>
                                        </td>
                                        <td class="p-4 text-center text-slate-400 font-bold">
                                            {{ item.stock_minimo }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>