<script setup>
import { computed } from 'vue';

const props = defineProps({
    variant: { type: String, default: 'default' },
    size: { type: String, default: 'sm' },
});

const variants = {
    default: {
        backgroundColor: 'color-mix(in srgb, var(--color-accent) 80%, transparent)',
        color: '#fff',
    },
    primary: {
        backgroundColor: 'var(--color-accent)',
        color: '#fff',
    },
    secondary: {
        backgroundColor: 'var(--color-secondary)',
        color: '#fff',
    },
    success: {
        backgroundColor: 'var(--color-success)',
        color: '#fff',
    },
    danger: {
        backgroundColor: 'var(--color-danger)',
        color: '#fff',
    },
    promo: {
        background: 'linear-gradient(135deg, #f7941e, #ff6b35)',
        color: '#fff',
    },
    discount: {
        background: 'linear-gradient(135deg, #e74c3c, #c0392b)',
        color: '#fff',
    },
};

const sizes = {
    sm: { fontSize: '9px', padding: '2px 8px', borderRadius: '999px' },
    md: { fontSize: '10px', padding: '4px 10px', borderRadius: '8px' },
    promo: { fontSize: '10px', padding: '3px 10px', borderRadius: '999px' },
};

const resolvedSize = computed(() => {
    if (props.variant === 'promo') return sizes.promo;
    return sizes[props.size] || sizes.sm;
});

const styleObj = computed(() => ({
    ...(variants[props.variant] || variants.default),
    ...resolvedSize.value,
    display: 'inline-flex',
    alignItems: 'center',
    fontWeight: 900,
    textTransform: 'uppercase',
    letterSpacing: '0.05em',
}));
</script>

<template>
    <span :style="styleObj" :class="props.variant === 'promo' ? 'badge-pulse' : ''">
        <slot />
    </span>
</template>

<style scoped>
@keyframes badge-pulse {
    0%, 100% { filter: brightness(1); }
    50% { filter: brightness(1.15); }
}
.badge-pulse {
    animation: badge-pulse 2s ease-in-out infinite;
}
</style>
