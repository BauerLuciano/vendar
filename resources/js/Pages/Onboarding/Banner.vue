<script setup>
import { computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';

const page = usePage();

const paso = computed(() => page.props.onboarding);

const visible = computed(() => paso.value && !paso.value.completado);

const volverAlWizard = () => {
    router.get(route('onboarding.index'));
};
</script>

<template>
    <div v-if="visible" class="bg-gradient-to-r from-indigo-600 to-indigo-700 border-b border-indigo-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex items-center gap-4 flex-wrap">
            <div class="flex items-center gap-3 text-white">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-sm font-bold">
                    Configuración Inicial · Paso <span class="text-indigo-200">{{ paso.titulo }}</span>
                </span>
            </div>
            <div class="flex-1 min-w-[120px] max-w-xs">
                <div class="h-2 bg-indigo-900/40 rounded-full overflow-hidden">
                    <div class="h-full bg-white rounded-full transition-all duration-500" :style="{ width: '100%' }"></div>
                </div>
            </div>
            <p class="text-indigo-200 text-xs font-medium hidden sm:block">{{ paso.descripcion }}</p>
            <button @click="volverAlWizard"
                class="ml-auto bg-white/20 hover:bg-white/30 text-white text-xs font-black uppercase tracking-widest px-4 py-2 rounded-xl transition-all shrink-0">
                Volver al Wizard
            </button>
        </div>
    </div>
</template>
