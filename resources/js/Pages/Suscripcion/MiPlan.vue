<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'; 
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    comercio: Object,
    planes: Array
});

// Variable reactiva para controlar el estado del botón mientras carga la API
const planCargando = ref(null);

const formatearDinero = (monto) => {
    return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS', maximumFractionDigits: 0 }).format(monto);
};

// Validación segura por si el objeto comercio llega vacío
const esPlanActual = (planId) => {
    if (!props.comercio?.plan) return planId === 'basico'; 
    return props.comercio.plan.toLowerCase() === planId.toLowerCase();
};

// 🔥 EJECUCIÓN DEL PAGO: Conecta con tu backend y abre Mercado Pago
const pagarPlan = async (planId) => {
    planCargando.value = planId;
    try {
        const response = await axios.post(route('suscripcion.pagar'), {
            plan_id: planId
        });
        
        if (response.data?.init_point) {
            window.location.href = response.data.init_point;
        }
    } catch (error) {
        console.error("Error al iniciar el pago:", error);
        alert("No se pudo conectar con Mercado Pago. Verificá que el Access Token esté bien configurado.");
    } finally {
        planCargando.value = null;
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Mi Plan | VendAR" />

        <div class="py-12 bg-slate-50 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <header class="mb-10 px-4 sm:px-0">
                    <h1 class="text-3xl font-black text-slate-800 uppercase tracking-tighter italic">
                        Configuración de <span class="text-[#00adef]">Suscripción</span>
                    </h1>
                    <p class="text-slate-500 font-bold text-sm uppercase tracking-widest mt-1">
                        Gestioná tu plan y habilitá nuevas funciones para tu negocio
                    </p>
                </header>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 px-4 sm:px-0">
                    <div 
                        v-for="plan in planes" 
                        :key="plan.id"
                        class="relative bg-white rounded-3xl p-8 shadow-xl transition-all duration-300 border-2 flex flex-col justify-between"
                        :class="esPlanActual(plan.id) ? 'border-[#00adef] shadow-[#00adef]/10 scale-105 z-10' : 'border-transparent hover:border-slate-200'"
                    >
                        <div v-if="esPlanActual(plan.id)" class="absolute -top-4 left-1/2 -translate-x-1/2 bg-[#00adef] text-white text-[10px] font-black uppercase px-4 py-1.5 rounded-full tracking-widest shadow-lg">
                            Tu Plan Actual
                        </div>

                        <div>
                            <div class="text-center mb-8">
                                <h3 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-2">{{ plan.nombre }}</h3>
                                <div class="flex items-baseline justify-center gap-1">
                                    <span class="text-4xl font-black text-slate-800">{{ formatearDinero(plan.precio) }}</span>
                                    <span class="text-slate-400 text-xs font-bold uppercase">/ mes</span>
                                </div>
                            </div>

                            <ul class="space-y-4 mb-10">
                                <li v-for="feature in plan.caracteristicas" :key="feature" class="flex items-center gap-3 text-sm font-bold text-slate-600">
                                    <span class="text-[#8cc63f] text-lg font-black">✓</span>
                                    {{ feature }}
                                </li>
                            </ul>
                        </div>

                        <button 
                            v-if="!esPlanActual(plan.id)"
                            :disabled="planCargando === plan.id"
                            class="w-full py-4 rounded-2xl font-black uppercase tracking-widest text-xs transition-all shadow-lg hover:opacity-90 active:scale-[0.98] disabled:opacity-50 disabled:cursor-wait flex items-center justify-center gap-2"
                            :style="{ backgroundColor: plan.color, color: '#fff' }"
                            @click="pagarPlan(plan.id)"
                        >
                            <span v-if="planCargando === plan.id">Generando Link... ⏳</span>
                            <span v-else>Mejorar a este Plan ⚡</span>
                        </button>
                        
                        <div 
                            v-else 
                            class="w-full py-4 rounded-2xl font-black uppercase tracking-widest text-xs text-center border-2 border-slate-100 text-slate-400 bg-slate-50 select-none font-bold"
                        >
                            Este es tu plan actual
                        </div>
                    </div>
                </div>

                <div class="mt-16 mx-4 sm:mx-0 bg-slate-900 rounded-3xl p-8 text-white flex flex-col md:flex-row items-center justify-between gap-6 shadow-2xl overflow-hidden relative">
                    <div class="absolute right-0 top-0 opacity-5 text-[120px] font-black italic select-none leading-none pointer-events-none">V-AR</div>
                    <div class="relative z-10">
                        <h4 class="text-xl font-black uppercase italic tracking-tight">¿Necesitás un plan a medida?</h4>
                        <p class="text-slate-400 text-sm font-bold mt-1">Si tenés más de 10 sucursales, contactanos para un presupuesto personalizado.</p>
                    </div>
                    <button class="relative z-10 bg-white text-slate-900 px-8 py-3.5 rounded-xl font-black uppercase text-xs tracking-widest hover:bg-[#00adef] hover:text-white transition-all shadow-lg">
                        Hablar con Soporte
                    </button>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>