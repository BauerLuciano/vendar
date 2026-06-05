<script setup>
import { computed } from 'vue';

const props = defineProps({
    sucursalesBackend: Array,
    sucursalElegida: [String, Number],
    localizando: Boolean,
    comercio: Object,
    distanciaKm: { type: Number, default: 0 },
});

const emit = defineEmits(['update:sucursalElegida', 'cargar-productos', 'usar-gps']);

const sucursalActual = computed(() => {
    if (!props.sucursalElegida || !props.sucursalesBackend) return null;
    return props.sucursalesBackend.find(s => s.id == props.sucursalElegida);
});
</script>

<template>
    <div class="relative z-10 w-full border-b transition-colors duration-300" :style="{ backgroundColor: 'var(--bg-navbar)', borderColor: 'var(--border-subtle)' }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-2.5 flex items-center gap-3">
            <div class="flex items-center flex-1 min-w-0">
                <select
                    :value="sucursalElegida"
                    @change="emit('update:sucursalElegida', $event.target.value); emit('cargar-productos')"
                    class="bg-transparent text-xs font-bold focus:outline-none min-w-0 max-w-[160px] sm:max-w-[240px] truncate appearance-none cursor-pointer transition-colors"
                    :style="{ color: 'var(--text-primary)' }"
                >
                    <option value="" disabled :style="{ backgroundColor: 'var(--bg-elevated)', color: 'var(--text-secondary)' }">Elegí un local...</option>
                    <option v-for="suc in sucursalesBackend" :key="suc.id" :value="suc.id" :style="{ backgroundColor: 'var(--bg-elevated)', color: 'var(--text-primary)' }">{{ suc.nombre }}</option>
                </select>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <span v-if="distanciaKm > 0" class="text-[10px] font-bold px-2 py-1 rounded-full whitespace-nowrap transition-colors" :style="{ color: 'var(--text-muted)', backgroundColor: 'var(--border-subtle)' }">
                    {{ distanciaKm < 1 ? Math.round(distanciaKm * 1000) + ' m' : distanciaKm.toFixed(1) + ' km' }}
                </span>
                <button
                    @click="emit('usar-gps')"
                    :disabled="localizando"
                    class="w-8 h-8 rounded-xl border flex items-center justify-center transition-all disabled:opacity-40 shrink-0"
                    :style="{ backgroundColor: 'var(--border-subtle)', borderColor: 'var(--border-color)', color: 'var(--text-muted)' }"
                    :title="localizando ? 'Buscando ubicación...' : 'Usar mi ubicación'"
                >
                    <span v-if="localizando" class="animate-spin text-xs">⟳</span>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><circle cx="12" cy="12" r="3"/><circle cx="12" cy="12" r="8"/></svg>
                </button>
            </div>
        </div>
    </div>
</template>
