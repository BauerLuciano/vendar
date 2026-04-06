<script setup>
defineProps({ mostrar: Boolean, rol: Object });
defineEmits(['cerrar']);
</script>

<template>
    <div v-if="mostrar" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
            <div class="p-6 text-center border-b">
                <h3 class="text-xl font-black text-slate-800 uppercase">{{ rol?.name }}</h3>
                <p class="text-xs text-slate-400">Detalle de Accesos</p>
            </div>
            <div class="p-6">
                <div class="flex flex-wrap gap-2">
                    <span v-for="permiso in rol?.permissions" :key="permiso.id" class="bg-emerald-100 text-emerald-800 px-3 py-1 rounded-full text-xs font-bold border border-emerald-200">
                        ✓ {{ permiso.name }}
                    </span>
                    <span v-if="rol?.permissions.length === 0" class="text-slate-400 text-sm italic">
                        Este rol no tiene permisos asignados.
                    </span>
                </div>
            </div>
            <div class="p-4 bg-slate-50 text-center">
                <button @click="$emit('cerrar')" class="bg-slate-800 text-white px-6 py-2 rounded-xl text-xs font-bold uppercase">Cerrar</button>
            </div>
        </div>
    </div>
</template>