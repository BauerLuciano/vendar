<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    categorias: Array,
    categoriaSeleccionada: [String, Number],
});

const emit = defineEmits(['update:categoriaSeleccionada']);

const scrollContainer = ref(null);

const emojiMap = {
    lacteos: '🥛', lacteos: '🥛', leche: '🥛', yogur: '🥛', queso: '🧀',
    bebidas: '🥤', gaseosa: '🥤', soda: '🥤', agua: '💧', jugo: '🧃',
    panaderia: '🍞', pan: '🍞', factura: '🥐',
    carnes: '🥩', carne: '🥩', pollo: '🍗', cerdo: '🥩',
    golosinas: '🍬', caramelos: '🍬', chicle: '🍬',
    snacks: '🍿', papas: '🥔', chips: '🍿',
    limpieza: '🧹', detergente: '🧴', jabon: '🧼',
    bebidas: '🥤', 'bebidas-alcohol': '🍺', cerveza: '🍺', vino: '🍷', fernet: '🥃',
    congelados: '🧊', helado: '🍦',
    frutas: '🍎', verdura: '🥦', verduleria: '🥬',
    perfumeria: '🧴', cosmeticos: '💄',
    pastas: '🍝', fideos: '🍝', arroz: '🍚',
    conservas: '🥫', enlatados: '🥫',
    galletitas: '🍪', galletas: '🍪',
    mascotas: '🐾', animales: '🐶', perro: '🐕', gato: '🐈',
    infantil: '👶', bebe: '🍼', pañal: '👶',
};

const getEmoji = (nombre) => {
    const key = Object.keys(emojiMap).find(k => nombre.toLowerCase().includes(k));
    return key ? emojiMap[key] : '📦';
};

const scrollLeft = () => {
    scrollContainer.value?.scrollBy({ left: -200, behavior: 'smooth' });
};

const scrollRight = () => {
    scrollContainer.value?.scrollBy({ left: 200, behavior: 'smooth' });
};
</script>

<template>
    <div class="sticky top-[80px] z-30 backdrop-blur-xl transition-colors duration-300" :style="{ backgroundColor: 'var(--bg-categories)' }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 relative">
            <div class="absolute left-0 top-0 bottom-0 w-8 z-10 pointer-events-none"
                :style="{ background: 'linear-gradient(to right, var(--bg-categories), transparent)' }">
            </div>
            <div class="absolute right-0 top-0 bottom-0 w-8 z-10 pointer-events-none"
                :style="{ background: 'linear-gradient(to left, var(--bg-categories), transparent)' }">
            </div>

            <div ref="scrollContainer" class="flex gap-2 py-3 overflow-x-auto no-scrollbar scroll-smooth snap-x snap-mandatory">
                <button
                    @click="emit('update:categoriaSeleccionada', 'todas')"
                    class="snap-start shrink-0 flex flex-col items-center gap-1.5 px-4 py-3 rounded-2xl border-2 transition-all duration-200 hover:-translate-y-0.5"
                    :class="categoriaSeleccionada === 'todas' ? 'border-[var(--color-accent)]' : 'border-transparent'"
                    :style="{
                        backgroundColor: categoriaSeleccionada === 'todas' ? 'var(--color-accent)' : 'var(--bg-card)',
                        color: categoriaSeleccionada === 'todas' ? '#fff' : 'var(--text-primary)',
                        boxShadow: categoriaSeleccionada === 'todas' ? '0 4px 12px var(--color-accent)' : 'none',
                    }"
                >
                    <span class="text-xl leading-none">🏠</span>
                    <span class="text-[10px] font-bold whitespace-nowrap tracking-tight">Todo</span>
                </button>
                <button
                    v-for="cat in categorias"
                    :key="cat.id"
                    @click="emit('update:categoriaSeleccionada', cat.id)"
                    class="snap-start shrink-0 flex flex-col items-center gap-1.5 px-4 py-3 rounded-2xl border-2 transition-all duration-200 hover:-translate-y-0.5"
                    :class="categoriaSeleccionada == cat.id ? 'border-[var(--color-accent)]' : 'border-transparent'"
                    :style="{
                        backgroundColor: categoriaSeleccionada == cat.id ? 'var(--color-accent)' : 'var(--bg-card)',
                        color: categoriaSeleccionada == cat.id ? '#fff' : 'var(--text-primary)',
                        boxShadow: categoriaSeleccionada == cat.id ? '0 4px 12px var(--color-accent)' : 'var(--shadow-sm)',
                    }"
                >
                    <span class="text-xl leading-none">{{ getEmoji(cat.nombre) }}</span>
                    <span class="text-[10px] font-bold whitespace-nowrap tracking-tight">{{ cat.nombre }}</span>
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
</style>
