<script setup>
defineProps({ mostrar: Boolean, permiso: Object });
defineEmits(['cerrar']);
</script>

<template>
    <div v-if="mostrar" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm text-center p-6">
            <div class="w-12 h-12 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl">🔑</div>
            <h3 class="text-lg font-black text-slate-800 font-mono mb-2">{{ permiso?.name }}</h3>
            <p class="text-xs text-slate-500 mb-6">Identificador único del sistema.</p>
            <button @click="$emit('cerrar')" class="w-full bg-slate-100 text-slate-700 px-4 py-2 rounded-xl text-xs font-bold uppercase hover:bg-slate-200">Volver</button>
        </div>
    </div>
</template>