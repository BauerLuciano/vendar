<script setup>
import { useForm } from '@inertiajs/vue3';
const props = defineProps({ mostrar: Boolean, permiso: Object });
const emit = defineEmits(['cerrar']);

const form = useForm({ nombre: '' });
</script>

<template>
    <div v-if="mostrar" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm">
            <div class="p-6">
                <h3 class="text-lg font-black text-slate-800 uppercase mb-4">{{ permiso ? 'Editar Permiso' : 'Nuevo Permiso' }}</h3>
                <input v-model="form.nombre" type="text" class="w-full rounded-xl border-slate-200 mb-4 text-sm" placeholder="Ej: eliminar facturas">
                <p class="text-[10px] text-amber-600 mb-4 bg-amber-50 p-2 rounded">Advertencia: Crear un permiso no surte efecto hasta implementarlo en el código fuente.</p>
                <div class="flex justify-end gap-2">
                    <button @click="$emit('cerrar')" class="px-4 py-2 text-xs font-bold text-slate-500 bg-slate-100 rounded-xl">Cancelar</button>
                    <button class="px-4 py-2 text-xs font-bold text-white bg-slate-800 rounded-xl">Guardar</button>
                </div>
            </div>
        </div>
    </div>
</template>