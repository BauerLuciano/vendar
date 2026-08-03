<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { router, Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Swal from 'sweetalert2';

const props = defineProps({
    estado: Object,
});

const pasoActual = ref(0);
const mostrandoFelicitacion = ref(false);
const autoAvanzando = ref(false);
const pasoCompletadoPrevio = ref(false);

const pasos = computed(() => props.estado?.pasos || []);
const porcentaje = computed(() => props.estado?.porcentaje || 0);
const completados = computed(() => props.estado?.completados || 0);
const total = computed(() => props.estado?.total || 0);
const completo = computed(() => props.estado?.completo || false);

const TIEMPO_POR_PASO = 2;
const totalRequeridos = computed(() => props.estado?.total_requeridos || total.value);
const tiempoRestante = computed(() => {
    const pendientes = totalRequeridos.value - completados.value;
    return pendientes * TIEMPO_POR_PASO;
});

const pasoSeleccionado = computed(() => pasos.value[pasoActual.value]);

onMounted(() => {
    router.reload({ only: ['estado'], preserveScroll: true });

    if (completo.value) {
        mostrandoFelicitacion.value = true;
    } else {
        const idx = pasos.value.findIndex(p => !p.completado && !p.opcional);
        if (idx >= 0) pasoActual.value = idx;
    }
});

watch(completo, (val) => {
    if (val) {
        setTimeout(() => { mostrandoFelicitacion.value = true; }, 600);
    }
});

watch(() => pasoSeleccionado.value?.completado, (nuevo, viejo) => {
    if (nuevo && viejo === false && !autoAvanzando.value) {
        autoAvanzando.value = true;
        setTimeout(() => {
            if (pasoActual.value < pasos.value.length - 1) {
                pasoActual.value++;
            }
            autoAvanzando.value = false;
        }, 900);
    }
});

const avanzar = () => {
    if (pasoActual.value < pasos.value.length - 1) {
        pasoActual.value++;
    }
};

const retroceder = () => {
    if (pasoActual.value > 0) {
        pasoActual.value--;
    }
};

const irAPaso = (paso, index) => {
    pasoActual.value = index;
};

const irAPantalla = (paso) => {
    if (paso?.ruta) {
        router.get(route(paso.ruta, { onboarding: paso.id }));
    } else if (paso?.url) {
        router.get(paso.url);
    }
};

const iconoSvg = (icono) => {
    const iconos = {
        tienda:     '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />',
        sucursal:   '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />',
        caja:       '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />',
        lista:     '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />',
        tag:        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />',
        producto:   '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />',
        proveedor:  '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />',
        ajustes:    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />',
        turno:      '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />',
        tarjeta:    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />',
    };
    return iconos[icono] || iconos.lista;
};

const felicidades = () => {
    router.get(route('pos.index'));
};
</script>

<template>
    <Head title="Configuración Inicial — VendAR" />

    <AuthenticatedLayout>
        <div class="py-8 px-4 sm:px-6 lg:px-8 max-w-5xl mx-auto bg-slate-50 min-h-screen">

            <!-- FELICITACIONES -->
            <div v-if="mostrandoFelicitacion" class="flex flex-col items-center justify-center min-h-[70vh] text-center">
                <div class="relative">
                    <div class="text-8xl mb-6 animate-bounce-in">🎉</div>
                    <div class="absolute -top-2 -right-2 w-6 h-6 bg-emerald-400 rounded-full animate-ping-slow"></div>
                    <div class="absolute -top-1 -left-1 w-4 h-4 bg-indigo-400 rounded-full animate-ping-slow" style="animation-delay: 0.3s"></div>
                    <div class="absolute -bottom-1 right-4 w-3 h-3 bg-amber-400 rounded-full animate-ping-slow" style="animation-delay: 0.6s"></div>
                </div>
                <h1 class="text-4xl font-black text-slate-800 mb-4 animate-fade-in-up">¡Todo listo!</h1>
                <p class="text-xl text-slate-600 font-medium mb-2 max-w-md animate-fade-in-up" style="animation-delay: 0.15s">
                    VendAR está configurado para tu negocio.
                </p>
                <p class="text-slate-400 font-medium mb-8 animate-fade-in-up" style="animation-delay: 0.3s">
                    Ya podés empezar a vender.
                </p>
                <button @click="felicidades"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white px-10 py-4 rounded-2xl font-black text-lg shadow-xl shadow-indigo-600/30 transition-all flex items-center gap-3 animate-fade-in-up"
                    style="animation-delay: 0.45s">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                    Ir al POS
                </button>
                <Link :href="route('dashboard')"
                    class="mt-4 text-sm text-slate-400 hover:text-slate-600 font-bold transition-colors animate-fade-in-up"
                    style="animation-delay: 0.6s">
                    Volver al Dashboard
                </Link>
            </div>

            <!-- WIZARD -->
            <div v-else>
                <div class="text-center mb-8">
                    <h1 class="text-3xl font-black text-slate-800 tracking-tight">Configuración Inicial</h1>
                    <p class="text-slate-500 font-medium text-sm mt-1">Completá estos pasos para empezar a vender</p>

                    <div class="mt-6 max-w-md mx-auto">
                        <div class="flex justify-between text-xs font-bold text-slate-500 mb-2">
                            <span>Progreso</span>
                            <span>{{ porcentaje }}%</span>
                        </div>
                        <div class="h-3 bg-slate-200 rounded-full overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-indigo-500 to-indigo-600 rounded-full transition-all duration-700 ease-out"
                                 :style="{ width: porcentaje + '%' }"></div>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-2 font-medium">
                            {{ completados }} de {{ props.estado?.total_requeridos ?? total }} pasos obligatorios completados
                            <span v-if="tiempoRestante > 0" class="text-slate-300"> · ~{{ tiempoRestante }} min restantes</span>
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

                    <!-- SIDEBAR PASOS -->
                    <div class="lg:col-span-4">
                        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 sticky top-8">
                            <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-4 px-2">Pasos</h3>
                            <div class="space-y-1">
                                <button v-for="(paso, index) in pasos" :key="paso.id"
                                    @click="irAPaso(paso, index)"
                                    class="w-full text-left px-3 py-3 rounded-xl flex items-center gap-3 transition-all group"
                                    :class="[
                                        index === pasoActual ? 'bg-indigo-50 border border-indigo-200' : 'hover:bg-slate-50',
                                    ]">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 text-xs font-black"
                                         :class="[
                                             paso.completado ? 'bg-emerald-100 text-emerald-600' : (index === pasoActual ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-500')
                                         ]">
                                        <svg v-if="paso.completado" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                        <span v-else>{{ index + 1 }}</span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="font-bold text-sm truncate"
                                             :class="paso.completado ? 'text-emerald-700' : (index === pasoActual ? 'text-indigo-700' : 'text-slate-700')">
                                            {{ paso.titulo }}
                                        </div>
                                        <div class="text-[11px] text-slate-400 font-medium truncate">{{ paso.descripcion }}</div>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <span v-if="paso.completado" class="text-emerald-500 text-[10px] font-black uppercase">Listo</span>
                                        <span v-else-if="paso.opcional" class="text-amber-500 text-[9px] font-black uppercase tracking-wider bg-amber-50 px-1.5 py-0.5 rounded">Opcional</span>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- CONTENIDO DEL PASO -->
                    <div class="lg:col-span-8">
                        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                            <!-- Header del paso -->
                            <div class="px-6 py-5 border-b border-slate-100 flex items-center gap-4">
                                <div class="w-12 h-12 rounded-xl flex items-center justify-center"
                                     :class="pasoSeleccionado?.completado ? 'bg-emerald-100' : 'bg-indigo-100'">
                                    <svg class="w-6 h-6" :class="pasoSeleccionado?.completado ? 'text-emerald-600' : 'text-indigo-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24" v-html="iconoSvg(pasoSeleccionado?.icono)"></svg>
                                </div>
                                <div>
                                    <h2 class="font-black text-xl text-slate-800">{{ pasoSeleccionado?.titulo }}</h2>
                                    <p class="text-sm text-slate-500 font-medium">{{ pasoSeleccionado?.descripcion }}</p>
                                </div>
                                <div class="ml-auto flex items-center gap-2">
                                    <span v-if="pasoSeleccionado?.opcional && !pasoSeleccionado?.completado"
                                          class="bg-amber-100 text-amber-700 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest">
                                        Opcional
                                    </span>
                                    <span v-if="pasoSeleccionado?.completado"
                                          class="bg-emerald-100 text-emerald-700 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest">
                                        Completado
                                    </span>
                                </div>
                            </div>

                            <!-- Contenido -->
                            <div class="p-6">
                                <!-- Estado completado -->
                                <div v-if="pasoSeleccionado?.completado" class="text-center py-8">
                                    <div class="w-16 h-16 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <svg class="w-8 h-8 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                    </div>
                                    <p class="text-slate-600 font-bold mb-1">Este paso está completado</p>
                                    <p v-if="pasoSeleccionado?.extra" class="text-sm text-slate-400">{{ pasoSeleccionado.extra }}</p>
                                    <div v-if="autoAvanzando" class="mt-4 flex items-center justify-center gap-2 text-indigo-500 text-sm font-bold">
                                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                        Avanzando al siguiente paso...
                                    </div>
                                </div>

                                <!-- Estado pendiente -->
                                <div v-else class="flex flex-col items-center py-8">
                                    <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <svg class="w-8 h-8 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" v-html="iconoSvg(pasoSeleccionado?.icono)"></svg>
                                    </div>
                                    <p class="text-slate-700 font-bold text-lg mb-2">Pendiente</p>
                                    <p class="text-sm text-slate-500 text-center max-w-md mb-6">{{ pasoSeleccionado?.instrucciones }}</p>
                                    <button @click="irAPantalla(pasoSeleccionado)"
                                            class="bg-indigo-600 hover:bg-indigo-700 text-white px-8 py-3 rounded-xl font-black shadow-lg shadow-indigo-600/30 transition-all uppercase tracking-widest text-sm">
                                        Ir a {{ pasoSeleccionado?.titulo }}
                                    </button>
                                    <p class="text-xs text-slate-400 mt-4">Completá lo necesario y volvé para continuar.</p>
                                </div>
                            </div>

                            <!-- Navegación -->
                            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex justify-between">
                                <button @click="retroceder" :disabled="pasoActual === 0"
                                    class="px-5 py-2 text-sm font-bold text-slate-500 hover:text-slate-700 uppercase tracking-widest disabled:opacity-30 disabled:cursor-not-allowed transition-colors">
                                    ← Anterior
                                </button>
                                <button @click="avanzar" :disabled="pasoActual >= pasos.length - 1"
                                    class="px-5 py-2 text-sm font-bold uppercase tracking-widest transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                                    :class="pasoSeleccionado?.completado || pasoSeleccionado?.opcional ? 'text-indigo-600 hover:text-indigo-800' : 'text-amber-600 hover:text-amber-800'">
                                    {{ pasoSeleccionado?.completado ? 'Siguiente →' : (pasoSeleccionado?.opcional ? 'Saltar →' : 'Omitir este paso →') }}
                                </button>
                            </div>
                        </div>

                        <!-- Tip rapido -->
                        <div class="mt-6 bg-amber-50 border border-amber-200 rounded-2xl p-5 flex items-start gap-4">
                            <div class="text-2xl shrink-0">💡</div>
                            <div>
                                <p class="font-bold text-amber-800 text-sm">Tip rápido</p>
                                <p class="text-amber-700 text-sm font-medium mt-1">
                                    <template v-if="pasoSeleccionado?.opcional">
                                        Este paso es opcional. Podés completarlo ahora o después. Los pasos opcionales no afectan tu progreso obligatorio.
                                    </template>
                                    <template v-else>
                                        Hacé click en "Ir a {{ pasoSeleccionado?.titulo }}" para completar este paso. Cuando vuelvas, el progreso se actualiza automáticamente.
                                    </template>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@keyframes bounce-in {
    0% { transform: scale(0); opacity: 0; }
    50% { transform: scale(1.2); }
    100% { transform: scale(1); opacity: 1; }
}
.animate-bounce-in {
    animation: bounce-in 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
}
@keyframes fade-in-up {
    from { opacity: 0; transform: translateY(16px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in-up {
    animation: fade-in-up 0.5s ease-out forwards;
    opacity: 0;
}
@keyframes ping-slow {
    0% { transform: scale(1); opacity: 1; }
    100% { transform: scale(2); opacity: 0; }
}
.animate-ping-slow {
    animation: ping-slow 1.5s ease-out infinite;
}
</style>
