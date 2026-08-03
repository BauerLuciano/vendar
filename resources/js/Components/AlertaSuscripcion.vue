<script setup>
import { ref, computed, watch } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';

const page = usePage();

const estadoCuenta = computed(() => page.props.estadoCuenta ?? null);
const enMora = computed(() => estadoCuenta.value?.en_mora ?? false);
const suspendido = computed(() => estadoCuenta.value?.suspendido ?? false);
const yaVisto = ref(false);

const mostrarModal = computed(() => enMora.value && !yaVisto.value);

const diasRestantes = computed(() => estadoCuenta.value?.dias_restantes_mora ?? 0);

const cerrarModal = () => { yaVisto.value = true; };
</script>

<template>
    <!-- BANNER PERSISTENTE mientras está en mora -->
    <div v-if="enMora && !suspendido" class="bg-amber-50 border-b border-amber-200 px-4 py-2.5 text-center">
        <p class="text-sm font-bold text-amber-800">
            Tu plan venció el {{ estadoCuenta?.vencimiento }}.
            <template v-if="diasRestantes > 0">Te quedan {{ diasRestantes }} día{{ diasRestantes !== 1 ? 's' : '' }} de gracia.</template>
            <Link :href="route('suscripcion.mi-plan')" class="underline font-black hover:text-amber-950 ml-2">
                Renovar y Pagar →
            </Link>
        </p>
    </div>

    <!-- MODAL de advertencia al entrar -->
    <div v-if="mostrarModal" class="fixed inset-0 z-[9999] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/70 backdrop-blur-sm" @click="cerrarModal"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl max-w-md w-full p-8 text-center">
            <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
            </div>
            <h2 class="text-xl font-black text-slate-900 uppercase tracking-tight mb-2">Tu plan está vencido</h2>
            <p class="text-sm text-slate-500 leading-relaxed mb-1">
                El período pagado de tu plan terminó el <strong>{{ estadoCuenta?.vencimiento }}</strong>.
            </p>
            <p v-if="diasRestantes > 0" class="text-sm text-slate-500 leading-relaxed mb-6">
                Tenés <strong class="text-amber-600">{{ diasRestantes }} día{{ diasRestantes !== 1 ? 's' : '' }}</strong> de gracia para renovar. Pasada esa fecha, tu cuenta se suspenderá temporalmente.
            </p>
            <p v-else class="text-sm text-slate-500 leading-relaxed mb-6">
                Renová hoy para evitar la suspensión de tu cuenta.
            </p>

            <div class="flex flex-col sm:flex-row gap-3">
                <Link :href="route('suscripcion.mi-plan')"
                    class="flex-1 bg-[#00adef] hover:bg-[#00adef]/90 text-white font-black py-3 rounded-xl uppercase tracking-widest text-xs shadow-lg transition-all">
                    Renovar y Pagar
                </Link>
                <button @click="cerrarModal"
                    class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold py-3 rounded-xl uppercase tracking-widest text-xs transition-all">
                    Ahora no
                </button>
            </div>
        </div>
    </div>
</template>
