<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

// Atajamos las variables que manda el backend
const props = defineProps({
    deudaTotal: { type: Number, default: 0 },
    ventasHoy: { type: Number, default: 0 },
    cajasActivas: { type: Number, default: 0 },
    productosBajoStock: { type: Array, default: () => [] },
    esJefe: { type: Boolean, default: false },
    sucursalUsuario: { type: String, default: 'Sin Asignar' }
});

// Función para formatear plata
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
            
            <div class="mb-8 flex flex-col sm:flex-row justify-between items-start sm:items-end gap-4">
                <div>
                    <h1 class="text-2xl font-black text-slate-800 uppercase tracking-tight">Panel de Control</h1>
                    <div class="h-1 w-12 bg-sky-500 mt-1"></div>
                </div>
                
                <div class="flex flex-col items-start sm:items-end gap-2">
                    <p class="text-sm font-bold text-slate-500">{{ new Date().toLocaleDateString('es-AR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}</p>
                    
                    <div class="flex items-center gap-2 bg-sky-100 text-sky-800 px-3 py-1.5 rounded-lg border border-sky-200 shadow-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        <span class="text-[10px] font-black uppercase tracking-widest leading-none">
                            SUCURSAL: <span class="text-sky-600">{{ sucursalUsuario }}</span>
                        </span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                
                <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-200 relative overflow-hidden group">
                    <div class="absolute -right-6 -top-6 bg-emerald-50 w-32 h-32 rounded-full z-0 transition-transform group-hover:scale-110"></div>
                    <div class="relative z-10 flex flex-col h-full justify-between">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-12 h-12 bg-emerald-100 text-emerald-600 rounded-xl flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <h2 class="text-xs font-black text-slate-500 uppercase tracking-widest">Ventas de Hoy</h2>
                        </div>
                        <div>
                            <p class="text-3xl font-black text-slate-800 tracking-tighter">{{ formatearDinero(ventasHoy) }}</p>
                            <p class="text-xs text-emerald-600 font-bold mt-2 flex items-center gap-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z" clip-rule="evenodd" /></svg>
                                Facturación actual
                            </p>
                        </div>
                    </div>
                </div>

                <div v-if="esJefe" class="bg-white p-6 rounded-3xl shadow-sm border border-slate-200 relative overflow-hidden group">
                    <div class="absolute -right-6 -top-6 bg-rose-50 w-32 h-32 rounded-full z-0 transition-transform group-hover:scale-110"></div>
                    <div class="relative z-10 flex flex-col h-full justify-between">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-12 h-12 bg-rose-100 text-rose-500 rounded-xl flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                            </div>
                            <h2 class="text-xs font-black text-slate-500 uppercase tracking-widest">Deuda en Calle</h2>
                        </div>
                        <div>
                            <p class="text-3xl font-black text-slate-800 tracking-tighter">{{ formatearDinero(deudaTotal) }}</p>
                            <p class="text-xs text-rose-500 font-bold mt-2">Cuentas corrientes pendientes</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-200 relative overflow-hidden group">
                    <div class="absolute -right-6 -top-6 bg-indigo-50 w-32 h-32 rounded-full z-0 transition-transform group-hover:scale-110"></div>
                    <div class="relative z-10 flex flex-col h-full justify-between">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-12 h-12 bg-indigo-100 text-indigo-500 rounded-xl flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <h2 class="text-xs font-black text-slate-500 uppercase tracking-widest">Cajas Abiertas</h2>
                        </div>
                        <div>
                            <p class="text-3xl font-black text-slate-800 tracking-tighter">{{ cajasActivas }}</p>
                            <p class="text-xs text-indigo-500 font-bold mt-2">Turnos operando ahora</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-200 relative overflow-hidden group">
                    <div class="absolute -right-6 -top-6 bg-sky-50 w-32 h-32 rounded-full z-0 transition-transform group-hover:scale-110"></div>
                    <div class="relative z-10 flex flex-col h-full justify-between">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-12 h-12 bg-sky-100 text-sky-500 rounded-xl flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                </svg>
                            </div>
                            <h2 class="text-xs font-black text-slate-500 uppercase tracking-widest">Pedidos Web</h2>
                        </div>
                        <div>
                            <p class="text-3xl font-black text-slate-800 tracking-tighter">0</p>
                            <p class="text-xs text-slate-400 font-bold mt-2">Pendientes de preparación</p>
                        </div>
                    </div>
                </div>

            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
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
                            <button v-if="esJefe && productosBajoStock.length > 0" class="bg-slate-900 text-white text-xs px-4 py-2 rounded-lg font-bold shadow-md hover:bg-sky-600 transition-colors">
                                Generar OCS
                            </button>
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

                <div class="lg:col-span-1">
                    <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-200 h-full">
                        <h2 class="text-sm font-black text-slate-800 uppercase tracking-widest mb-6">Accesos Rápidos</h2>
                        
                        <div class="space-y-3">
                            <Link :href="route('pos.index')" class="w-full flex items-center gap-4 p-4 rounded-2xl bg-slate-50 hover:bg-sky-50 border border-slate-100 hover:border-sky-200 transition-all group">
                                <div class="w-10 h-10 bg-white rounded-xl shadow-sm flex items-center justify-center text-sky-500 group-hover:scale-110 transition-transform">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                                </div>
                                <div class="text-left">
                                    <h3 class="text-sm font-bold text-slate-700 group-hover:text-sky-700">Punto de Venta</h3>
                                    <p class="text-[10px] uppercase font-bold text-slate-400">Abrir caja facturación</p>
                                </div>
                            </Link>

                            <Link v-if="esJefe" :href="route('ingresos.index')" class="w-full flex items-center gap-4 p-4 rounded-2xl bg-slate-50 hover:bg-emerald-50 border border-slate-100 hover:border-emerald-200 transition-all group">
                                <div class="w-10 h-10 bg-white rounded-xl shadow-sm flex items-center justify-center text-emerald-500 group-hover:scale-110 transition-transform">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
                                </div>
                                <div class="text-left">
                                    <h3 class="text-sm font-bold text-slate-700 group-hover:text-emerald-700">Ingreso Mercadería</h3>
                                    <p class="text-[10px] uppercase font-bold text-slate-400">Cargar remitos y stock</p>
                                </div>
                            </Link>

                            <Link :href="route('consumidores.index')" class="w-full flex items-center gap-4 p-4 rounded-2xl bg-slate-50 hover:bg-indigo-50 border border-slate-100 hover:border-indigo-200 transition-all group">
                                <div class="w-10 h-10 bg-white rounded-xl shadow-sm flex items-center justify-center text-indigo-500 group-hover:scale-110 transition-transform">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                                </div>
                                <div class="text-left">
                                    <h3 class="text-sm font-bold text-slate-700 group-hover:text-indigo-700">Nuevo Cliente</h3>
                                    <p class="text-[10px] uppercase font-bold text-slate-400">Cuentas Corrientes</p>
                                </div>
                            </Link>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>