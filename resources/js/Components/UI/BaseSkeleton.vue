<script setup>
import { computed } from 'vue';

const props = defineProps({
    width: { type: String, default: '100%' },
    height: { type: String, default: '16px' },
    rounded: { type: String, default: '8px' },
    count: { type: Number, default: 1 },
});

const styleObj = computed(() => ({
    width: props.width,
    height: props.height,
    borderRadius: props.rounded,
    backgroundColor: 'var(--bg-input)',
    position: 'relative',
    overflow: 'hidden',
}));
</script>

<template>
    <div v-for="n in count" :key="n" class="skeleton" :style="styleObj">
        <div class="shimmer" />
    </div>
</template>

<style scoped>
.skeleton {
    position: relative;
    overflow: hidden;
}

.shimmer {
    position: absolute;
    inset: 0;
    background: linear-gradient(
        90deg,
        transparent 0%,
        rgba(255, 255, 255, 0.04) 50%,
        transparent 100%
    );
    background-size: 200% 100%;
    animation: shimmer 1.8s ease-in-out infinite;
    pointer-events: none;
}

@keyframes shimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}
</style>
