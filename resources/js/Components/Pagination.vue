<script setup>
defineProps({
    currentPage: { type: Number, required: true },
    totalPages: { type: Number, required: true },
    size: { type: String, default: 'md' }
});

const emit = defineEmits(['page-change']);
</script>

<template>
    <div v-if="totalPages > 1" class="flex justify-between items-center"
        :class="size === 'sm' ? 'mt-4 bg-slate-50 p-2 rounded border' : 'mt-4'">
        <button @click="emit('page-change', currentPage - 1)" :disabled="currentPage === 1"
            class="rounded-lg disabled:opacity-50 hover:bg-gray-300 transition-colors text-sm font-medium"
            :class="size === 'sm' ? 'px-3 py-1.5 bg-white border shadow-sm' : 'px-4 py-2 bg-gray-200'">
            Anterior
        </button>
        <span class="text-sm text-slate-500 font-medium"
            :class="size === 'sm' ? 'font-bold' : ''">
            Página {{ currentPage }} de {{ totalPages }}
        </span>
        <button @click="emit('page-change', currentPage + 1)" :disabled="currentPage === totalPages"
            class="rounded-lg disabled:opacity-50 hover:bg-gray-300 transition-colors text-sm font-medium"
            :class="size === 'sm' ? 'px-3 py-1.5 bg-white border shadow-sm' : 'px-4 py-2 bg-gray-200'">
            Siguiente
        </button>
    </div>
</template>
