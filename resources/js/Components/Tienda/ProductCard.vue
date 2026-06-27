<script setup>
import { ref, computed } from 'vue';
import BaseBadge from '@/Components/UI/BaseBadge.vue';
import BaseCard from '@/Components/UI/BaseCard.vue';
import BaseButton from '@/Components/UI/BaseButton.vue';
import PriceDisplay from '@/Components/Commerce/PriceDisplay.vue';

const props = defineProps({
    producto: Object,
});

const emit = defineEmits(['agregar', 'detail']);

const agregando = ref(false);

const handleAgregar = () => {
    emit('agregar', props.producto);
    agregando.value = true;
    setTimeout(() => { agregando.value = false; }, 900);
};

const bajoStock = computed(() => props.producto.stock > 0 && props.producto.stock <= 5);
const sinStock = computed(() => props.producto.stock <= 0);
const enPromocion = computed(() => props.producto.promotion);
</script>

<template>
    <div
        class="group border rounded-2xl overflow-hidden flex flex-col transition-all duration-300 hover:-translate-y-1.5 cursor-pointer"
        :style="{
            backgroundColor: 'var(--bg-card)',
            borderColor: 'var(--border-color)',
            boxShadow: 'var(--shadow-sm)',
        }"
        @click="emit('detail', producto)"
    >
        <div class="relative flex items-center justify-center p-3 overflow-hidden" :style="{ backgroundColor: 'var(--bg-image)', aspectRatio: '4 / 3' }">
            <img v-if="producto.imagen_url" :src="producto.imagen_url" :alt="producto.nombre" loading="lazy" class="max-h-full max-w-full object-contain group-hover:scale-105 transition-transform duration-500">
            <div v-else class="flex items-center justify-center w-full h-full" :style="{ backgroundColor: 'color-mix(in srgb, var(--bg-image) 50%, transparent)' }">
                <svg class="w-10 h-10 opacity-40" :style="{ color: 'var(--text-muted)' }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </div>

            <BaseBadge
                v-if="enPromocion"
                variant="promo"
                class="absolute top-2 left-2 z-10"
            >
                {{ producto.promotion.label || '🔥 Promoción' }}
            </BaseBadge>

            <BaseBadge
                v-else-if="producto.categoria?.nombre"
                variant="primary"
                class="absolute top-2 left-2 z-10"
            >
                {{ producto.categoria.nombre }}
            </BaseBadge>

            <BaseBadge
                v-if="!enPromocion && bajoStock"
                variant="secondary"
                class="absolute top-2 right-2 z-10"
            >
                Últimas!
            </BaseBadge>

            <div
                v-if="enPromocion && producto.promotion.discount_percent"
                class="absolute top-2 right-2 z-10 flex flex-col items-center justify-center rounded-lg font-black leading-none shadow-sm"
                style="background: linear-gradient(135deg, #e74c3c, #c0392b); color: #fff; min-width: 44px; padding: 4px 6px;"
            >
                <span class="text-[16px]">{{ producto.promotion.discount_percent }}%</span>
                <span class="text-[7px] uppercase tracking-wider font-bold" style="background: rgba(255,255,255,0.2); border-radius: 2px; padding: 1px 4px; margin-top: 1px;">OFF</span>
            </div>

            <div v-if="sinStock" class="absolute inset-0 flex items-center justify-center backdrop-blur-sm z-10" :style="{ backgroundColor: 'rgba(0,0,0,0.4)' }">
                <BaseBadge variant="danger">Sin stock</BaseBadge>
            </div>
        </div>

        <div class="p-3.5 flex flex-col flex-grow">
            <h3 class="text-xs font-bold leading-tight line-clamp-2 mb-2 flex-grow transition-colors" :style="{ color: 'var(--text-primary)' }">
                {{ producto.nombre }}
            </h3>

            <PriceDisplay
                :price="producto.precio"
                :promo="producto.promotion"
                size="lg"
            />

            <BaseButton
                @click.stop="handleAgregar"
                :disabled="sinStock"
                size="sm"
                variant="secondary"
                block
                class="mt-3"
                :class="sinStock ? 'opacity-50' : ''"
            >
                <template v-if="sinStock">Sin Stock</template>
                <template v-else-if="agregando">✔ Agregado</template>
                <template v-else>+ Agregar</template>
            </BaseButton>
        </div>
    </div>
</template>

<style scoped>
.card:hover {
    box-shadow: 0 8px 28px rgba(0, 0, 0, 0.08) !important;
    border-color: var(--color-accent) !important;
}

.btn-add:not(:disabled):hover {
    background-color: var(--color-secondary) !important;
    color: #fff !important;
    border-color: var(--color-secondary) !important;
}
</style>
