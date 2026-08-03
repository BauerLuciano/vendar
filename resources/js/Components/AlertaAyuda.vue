<script setup>
defineProps({
    titulo: { type: String, default: '' },
});
</script>

<template>
    <div class="flex items-start gap-2.5 p-3 bg-amber-50 border border-amber-200 rounded-xl">
        <svg class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        <div class="text-sm font-semibold text-amber-800">
            <p v-if="titulo" class="font-black mb-0.5">{{ titulo }}</p>
            <slot />
        </div>
    </div>
</template>
