<script setup>
import { computed } from 'vue';

const props = defineProps({
    hover: { type: Boolean, default: true },
    padding: { type: String, default: 'p-3.5' },
    border: { type: Boolean, default: true },
});
</script>

<template>
    <div
        :class="[
            'rounded-2xl overflow-hidden flex flex-col transition-all duration-300',
            hover ? 'hover:-translate-y-1.5 cursor-pointer' : '',
            padding,
        ]"
        :style="{
            backgroundColor: 'var(--bg-card)',
            borderColor: border ? 'var(--border-color)' : 'transparent',
            borderWidth: border ? '1px' : '0',
            borderStyle: border ? 'solid' : 'none',
            boxShadow: 'var(--shadow-sm)',
        }"
    >
        <slot />
    </div>
</template>
