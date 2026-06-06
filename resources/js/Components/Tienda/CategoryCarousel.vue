<script setup>
import { ref } from 'vue';

const props = defineProps({
    categorias: Array,
    categoriaSeleccionada: [String, Number],
    productCounts: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['update:categoriaSeleccionada']);

const scrollContainer = ref(null);
const canScrollLeft = ref(false);
const canScrollRight = ref(false);

const emojiMap = {
    lacteos: '🥛', leche: '🥛', yogur: '🥛', queso: '🧀',
    bebidas: '🥤', gaseosa: '🥤', soda: '🥤', agua: '💧', jugo: '🧃',
    panaderia: '🍞', pan: '🍞', factura: '🥐',
    carnes: '🥩', carne: '🥩', pollo: '🍗', cerdo: '🥩',
    golosinas: '🍬', caramelos: '🍬', chicle: '🍬',
    snacks: '🍿', papas: '🥔', chips: '🍿',
    limpieza: '🧹', detergente: '🧴', jabon: '🧼',
    cerveza: '🍺', vino: '🍷', fernet: '🥃',
    congelados: '🧊', helado: '🍦',
    frutas: '🍎', verdura: '🥦', verduleria: '🥬',
    perfumeria: '🧴', cosmeticos: '💄',
    pastas: '🍝', fideos: '🍝', arroz: '🍚',
    conservas: '🥫', enlatados: '🥫',
    galletitas: '🍪', galletas: '🍪',
    mascotas: '🐾', animales: '🐶', perro: '🐕', gato: '🐈',
    infantil: '👶', bebe: '🍼', pañal: '👶',
    fiambres: '🥓', fiambreria: '🥓', jamon: '🥓',
    huevos: '🥚', granja: '🐔',
    salsas: '🫙', aderezos: '🫙', condimentos: '🧂',
    harinas: '🌾', cereales: '🌾',
    infusiones: '☕', te: '☕', cafe: '☕', mate: '🧉',
};

const getEmoji = (nombre) => {
    const key = Object.keys(emojiMap).find(k => nombre.toLowerCase().includes(k));
    return key ? emojiMap[key] : '📦';
};

const getCount = (catId) => {
    const count = props.productCounts[catId];
    return count !== undefined ? `(${count})` : '';
};

const getTotalCount = () => {
    const count = props.productCounts['todas'] || props.productCounts['all'];
    return count !== undefined ? `(${count})` : '';
};

const checkScroll = () => {
    const el = scrollContainer.value;
    if (!el) return;
    canScrollLeft.value = el.scrollLeft > 16;
    canScrollRight.value = el.scrollLeft < el.scrollWidth - el.clientWidth - 16;
};

const scrollLeft = () => {
    scrollContainer.value?.scrollBy({ left: -280, behavior: 'smooth' });
};

const scrollRight = () => {
    scrollContainer.value?.scrollBy({ left: 280, behavior: 'smooth' });
};
</script>

<template>
    <div class="sticky top-[80px] z-30 backdrop-blur-xl transition-colors duration-300" :style="{ backgroundColor: 'var(--bg-categories)' }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 relative">
            <button
                v-if="canScrollLeft"
                @click="scrollLeft"
                class="absolute left-0 top-1/2 -translate-y-1/2 z-20 w-9 h-9 rounded-full flex items-center justify-center shadow-lg transition-all duration-200 hover:scale-110"
                :style="{ backgroundColor: 'var(--bg-card)', color: 'var(--text-primary)' }"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
            </button>

            <button
                v-if="canScrollRight"
                @click="scrollRight"
                class="absolute right-0 top-1/2 -translate-y-1/2 z-20 w-9 h-9 rounded-full flex items-center justify-center shadow-lg transition-all duration-200 hover:scale-110"
                :style="{ backgroundColor: 'var(--bg-card)', color: 'var(--text-primary)' }"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
            </button>

            <div
                ref="scrollContainer"
                @scroll="checkScroll"
                class="flex gap-3 py-3 overflow-x-auto no-scrollbar scroll-smooth snap-x snap-mandatory"
            >
                <button
                    @click="emit('update:categoriaSeleccionada', 'todas')"
                    class="cat-card snap-start shrink-0 flex flex-col items-center gap-2.5 px-6 py-4 rounded-2xl border-2 transition-all duration-200"
                    :class="categoriaSeleccionada === 'todas' ? 'scale-[1.05] shadow-lg' : ''"
                    :style="{
                        backgroundColor: categoriaSeleccionada === 'todas' ? 'var(--color-accent)' : 'var(--bg-card)',
                        color: categoriaSeleccionada === 'todas' ? '#fff' : 'var(--text-primary)',
                        borderColor: categoriaSeleccionada === 'todas' ? 'var(--color-accent)' : 'var(--border-color)',
                        boxShadow: categoriaSeleccionada === 'todas' ? '0 6px 24px color-mix(in srgb, var(--color-accent) 45%, transparent)' : 'var(--shadow-sm)',
                    }"
                >
                    <span class="text-3xl leading-none">🏠</span>
                    <div class="flex flex-col items-center">
                        <span class="text-[12px] font-black whitespace-nowrap">Todo</span>
                        <span v-if="getTotalCount()" class="text-[9px] font-bold mt-0.5" :style="{ opacity: 0.7 }">{{ getTotalCount() }}</span>
                    </div>
                </button>
                <button
                    v-for="cat in categorias"
                    :key="cat.id"
                    @click="emit('update:categoriaSeleccionada', cat.id)"
                    class="cat-card snap-start shrink-0 flex flex-col items-center gap-2.5 px-6 py-4 rounded-2xl border-2 transition-all duration-200"
                    :class="categoriaSeleccionada == cat.id ? 'scale-[1.05] shadow-lg' : ''"
                    :style="{
                        backgroundColor: categoriaSeleccionada == cat.id ? 'var(--color-accent)' : 'var(--bg-card)',
                        color: categoriaSeleccionada == cat.id ? '#fff' : 'var(--text-primary)',
                        borderColor: categoriaSeleccionada == cat.id ? 'var(--color-accent)' : 'var(--border-color)',
                        boxShadow: categoriaSeleccionada == cat.id ? '0 6px 24px color-mix(in srgb, var(--color-accent) 45%, transparent)' : 'var(--shadow-sm)',
                    }"
                >
                    <span class="text-3xl leading-none">{{ getEmoji(cat.nombre) }}</span>
                    <div class="flex flex-col items-center">
                        <span class="text-[12px] font-black whitespace-nowrap">{{ cat.nombre }}</span>
                        <span v-if="getCount(cat.id)" class="text-[9px] font-bold mt-0.5" :style="{ opacity: 0.7 }">{{ getCount(cat.id) }}</span>
                    </div>
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

.cat-card:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 6px 20px rgba(0, 173, 239, 0.15) !important;
}
</style>
