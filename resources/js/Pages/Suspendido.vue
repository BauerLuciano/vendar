<script setup>
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    titular: Object,
    comercio: Object,
    sucursal: Object,
    cuit: String,
    dias_vencidos: Number,
    suspendida_por_vencimiento: Boolean,
});
</script>

<template>
    <Head title="Suscripción Vencida" />

    <div class="min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950 flex items-center justify-center p-4">
        <div class="max-w-lg w-full animate-in fade-in zoom-in duration-300">

            <!-- Encabezado -->
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-amber-500/10 border border-amber-500/20 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h1 class="text-2xl font-black text-white uppercase tracking-wider mb-1">Suscripción Suspendida</h1>
                <p class="text-slate-400 text-sm">
                    <template v-if="suspendida_por_vencimiento">
                        Su acceso ha sido suspendido por vencimiento de la suscripción.
                    </template>
                    <template v-else>
                        Su acceso ha sido suspendido. Comuníquese con el equipo de VendAR para regularizar su situación.
                    </template>
                </p>
            </div>

            <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl overflow-hidden">

                <!-- Botón renovar (acción principal) -->
                <div class="px-6 pt-6 pb-5 border-b border-slate-800">
                    <Link
                        :href="route('suscripcion.mi-plan')"
                        class="w-full flex items-center justify-center gap-3 bg-[#00adef] hover:bg-[#0096d4] text-white font-black py-4 rounded-xl uppercase tracking-widest text-sm transition-all shadow-lg shadow-[#00adef]/20 hover:shadow-[#00adef]/40 active:scale-[0.98]"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Renovar Suscripción
                    </Link>
                    <p class="text-center text-slate-500 text-xs mt-3">Elegí tu plan y pagá de forma segura con Mercado Pago</p>
                </div>

                <!-- Datos del comercio -->
                <div v-if="comercio" class="px-6 py-5 border-b border-slate-800">
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-3">Comercio</p>
                    <div class="space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="text-slate-400 text-sm">Nombre</span>
                            <span class="text-white text-sm font-bold">{{ comercio.nombre }}</span>
                        </div>
                        <div v-if="sucursal" class="flex justify-between items-center">
                            <span class="text-slate-400 text-sm">Sucursal</span>
                            <span class="text-white text-sm font-bold">{{ sucursal.nombre }}</span>
                        </div>
                    </div>
                </div>

                <!-- Datos del titular -->
                <div v-if="titular" class="px-6 py-5 border-b border-slate-800">
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-3">Titular</p>
                    <div class="space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="text-slate-400 text-sm">Nombre</span>
                            <span class="text-white text-sm font-bold">{{ titular.nombre }}</span>
                        </div>
                        <div v-if="cuit" class="flex justify-between items-center">
                            <span class="text-slate-400 text-sm">CUIT</span>
                            <span class="text-white text-sm font-bold font-mono">{{ cuit }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-slate-400 text-sm">Correo</span>
                            <span class="text-sky-400 text-sm font-bold">{{ titular.email }}</span>
                        </div>
                    </div>
                </div>

                <!-- Detalle de la suscripción -->
                <div v-if="comercio" class="px-6 py-5 border-b border-slate-800">
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-3">Suscripción</p>
                    <div class="space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="text-slate-400 text-sm">Plan</span>
                            <span class="text-white text-sm font-bold">{{ comercio.plan }}</span>
                        </div>
                        <div v-if="comercio.vencimiento_pago" class="flex justify-between items-center">
                            <span class="text-slate-400 text-sm">Vencimiento</span>
                            <span class="text-amber-400 text-sm font-bold">{{ comercio.vencimiento_pago }}</span>
                        </div>
                        <div v-if="dias_vencidos !== null && dias_vencidos !== undefined" class="flex justify-between items-center">
                            <span class="text-slate-400 text-sm">Días vencido</span>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-amber-500/10 border border-amber-500/20 rounded-lg">
                                <span class="w-2 h-2 bg-amber-500 rounded-full animate-pulse"></span>
                                <span class="text-amber-400 text-sm font-black">{{ dias_vencidos }} día{{ dias_vencidos !== 1 ? 's' : '' }}</span>
                            </span>
                        </div>
                        <div v-else class="flex justify-between items-center">
                            <span class="text-slate-400 text-sm">Motivo</span>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-slate-500/10 border border-slate-500/20 rounded-lg">
                                <span class="text-slate-300 text-sm font-black">Suspensión por administración</span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Contacto soporte -->
                <div class="px-6 py-5 border-b border-slate-800">
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-3">Soporte VendAR</p>
                    <div class="space-y-2">
                        <a href="mailto:ventas@vendar.com.ar" class="flex items-center gap-3 group">
                            <div class="w-8 h-8 bg-sky-500/10 border border-sky-500/20 rounded-lg flex items-center justify-center shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-sky-400 text-sm font-bold group-hover:text-sky-300 transition-colors">ventas@vendar.com.ar</p>
                                <p class="text-slate-500 text-[10px] font-bold uppercase">Correo comercial</p>
                            </div>
                        </a>
                        <a href="https://wa.me/5493755123456" target="_blank" rel="noopener noreferrer" class="flex items-center gap-3 group">
                            <div class="w-8 h-8 bg-emerald-500/10 border border-emerald-500/20 rounded-lg flex items-center justify-center shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-emerald-400 text-sm font-bold group-hover:text-emerald-300 transition-colors">+54 9 3755 123456</p>
                                <p class="text-slate-500 text-[10px] font-bold uppercase">WhatsApp</p>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Cerrar sesión -->
                <div class="px-6 pb-6 pt-5">
                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="w-full bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold py-3 rounded-xl uppercase tracking-widest text-xs transition-colors border border-slate-700 hover:border-slate-600"
                    >
                        Cerrar Sesión
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
