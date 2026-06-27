<script setup>
const props = defineProps({
    variant: { type: String, default: 'primary' },
    size: { type: String, default: 'md' },
    disabled: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    block: { type: Boolean, default: false },
});

const emit = defineEmits(['click']);

const variants = {
    primary: {
        backgroundColor: 'var(--color-accent)',
        color: '#fff',
        borderColor: 'var(--color-accent)',
    },
    secondary: {
        backgroundColor: 'var(--color-secondary)',
        color: '#fff',
        borderColor: 'var(--color-secondary)',
    },
    success: {
        backgroundColor: 'var(--color-success)',
        color: '#fff',
        borderColor: 'var(--color-success)',
    },
    danger: {
        backgroundColor: 'var(--color-danger)',
        color: '#fff',
        borderColor: 'var(--color-danger)',
    },
    ghost: {
        backgroundColor: 'transparent',
        color: 'var(--text-secondary)',
        borderColor: 'transparent',
    },
    outline: {
        backgroundColor: 'transparent',
        color: 'var(--text-secondary)',
        borderColor: 'var(--border-color)',
    },
    gradient: {
        background: 'linear-gradient(135deg, #f7941e, #ff6b35)',
        color: '#fff',
        borderColor: 'transparent',
    },
};

const sizes = {
    sm: { padding: '6px 12px', fontSize: '9px' },
    md: { padding: '8px 16px', fontSize: '11px' },
    lg: { padding: '12px 24px', fontSize: '13px' },
};

const styleObj = computed(() => ({
    ...(variants[props.variant] || variants.primary),
    ...(sizes[props.size] || sizes.md),
    opacity: props.disabled ? 0.5 : 1,
    cursor: props.disabled ? 'not-allowed' : 'pointer',
    width: props.block ? '100%' : undefined,
}));

import { computed } from 'vue';

const handleClick = () => {
    if (!props.disabled && !props.loading) {
        emit('click');
    }
};
</script>

<template>
    <button
        :disabled="disabled || loading"
        :style="styleObj"
        class="inline-flex items-center justify-center font-black uppercase tracking-widest rounded-xl border-2 transition-all duration-150 active:scale-95"
        @click="handleClick"
    >
        <span v-if="loading" class="animate-spin mr-1.5">⟳</span>
        <slot />
    </button>
</template>
