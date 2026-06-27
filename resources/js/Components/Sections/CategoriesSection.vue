<script setup>
import CategoryCarousel from '@/Components/Tienda/CategoryCarousel.vue';

defineProps({
    section: { type: Object, default: () => ({}) },
    categorias: Array,
    categoriaSeleccionada: [String, Number],
    productCounts: Object,
});

defineEmits(['update:categoria-seleccionada']);
</script>

<template>
    <CategoryCarousel
        :categorias="categorias"
        :categoria-seleccionada="categoriaSeleccionada"
        :product-counts="productCounts"
        @update:categoria-seleccionada="$emit('update:categoria-seleccionada', $event)"
    />
</template>
