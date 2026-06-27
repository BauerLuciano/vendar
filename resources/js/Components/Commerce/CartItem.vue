<script setup>
import { computed } from 'vue';

const props = defineProps({
    item: { type: Object, required: true },
    index: { type: Number, required: true },
});

const emit = defineEmits(['update-quantity']);

const formatearDinero = (monto) => {
    return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(monto);
};

const itemTotal = computed(() => {
    const precio = parseFloat(props.item.precio) || 0;
    const cantidad = parseInt(props.item.cantidad) || 0;
    return formatearDinero(precio * cantidad);
});

const handleDelta = (delta) => {
    emit('update-quantity', { index: props.index, delta });
};

const handleInput = (event) => {
    let valor = parseInt(event.target.value);
    if (isNaN(valor) || valor < 1) valor = 1;
    emit('update-quantity', { index: props.index, value: valor });
};
</script>

<template>
    <div
        class="flex items-center gap-3 border-2 p-3 rounded-xl shadow-sm transition-colors"
        :style="{
            backgroundColor: 'var(--bg-card)',
            borderColor: 'var(--border-subtle)',
        }"
    >
        <div
            class="w-14 h-14 rounded-xl p-1.5 flex items-center justify-center shrink-0"
            :style="{ backgroundColor: 'var(--bg-image)' }"
        >
            <img
                :src="item.imagen_url || '/img/LogoVendar-Sidebar.png'"
                class="max-h-full object-contain"
            >
        </div>
        <div class="flex-grow min-w-0">
            <p class="text-xs font-bold truncate leading-tight transition-colors" :style="{ color: 'var(--text-primary)' }">
                {{ item.nombre }}
            </p>
            <p class="text-sm font-black mt-0.5" :style="{ color: 'var(--color-accent)' }">
                {{ itemTotal }}
            </p>
        </div>
        <div
            class="flex items-center gap-1 border-2 rounded-xl p-0.5 shrink-0 transition-colors"
            :style="{
                backgroundColor: 'var(--bg-page)',
                borderColor: 'var(--border-color)',
            }"
        >
            <button
                @click="handleDelta(-1)"
                class="w-7 h-7 rounded-lg font-bold text-sm flex items-center justify-center transition-colors hover:bg-[var(--color-danger-hover)] hover:text-[var(--text-on-accent)]"
                :style="{
                    backgroundColor: 'var(--bg-card)',
                    color: 'var(--text-secondary)',
                }"
            >
                −
            </button>
            <input
                type="number"
                :value="item.cantidad"
                @input="handleInput"
                class="w-9 bg-transparent border-none text-center text-xs font-black p-0 transition-colors"
                :style="{ color: 'var(--text-primary)' }"
            >
            <button
                @click="handleDelta(1)"
                class="w-7 h-7 rounded-lg font-bold text-sm flex items-center justify-center transition-colors hover:bg-[var(--color-success)] hover:text-white"
                :style="{
                    backgroundColor: 'var(--bg-card)',
                    color: 'var(--text-secondary)',
                }"
            >
                +
            </button>
        </div>
    </div>
</template>
