<script setup>
import PromoSection from '@/Components/Tienda/PromoSection.vue';

defineProps({
    section: { type: Object, default: () => ({}) },
    sucursalId: [String, Number],
});

defineEmits(['agregar', 'detail', 'ver-todas']);
</script>

<template>
    <PromoSection
        :sucursal-id="sucursalId"
        @agregar="$emit('agregar', $event)"
        @detail="$emit('detail', $event)"
        @ver-todas="$emit('ver-todas')"
    />
</template>
