<script setup>
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    mostrar: Boolean,
    rol: Object,
    permisosDisponibles: Array
});

const emit = defineEmits(['cerrar']);

const form = useForm({
    nombre: '',
    permisos: []
});

// Cuando se abre el modal, cargamos los datos si estamos editando
watch(() => props.mostrar, (mostrando) => {
    if (mostrando) {
        form.nombre = props.rol ? props.rol.name : '';
        // Mapeamos los nombres de los permisos que ya tiene el rol
        form.permisos = props.rol ? props.rol.permissions.map(p => p.name) : [];
    } else {
        form.reset();
        form.clearErrors();
    }
});

const guardar = () => {
    if (props.rol) {
        form.put(route('roles.update', props.rol.id), {
            onSuccess: () => {
                emit('cerrar');
                Swal.fire('¡Actualizado!', 'El rol fue modificado.', 'success');
            }
        });
    } else {
        form.post(route('roles.store'), {
            onSuccess: () => {
                emit('cerrar');
                Swal.fire('¡Creado!', 'Nuevo rol guardado.', 'success');
            }
        });
    }
};
</script>

<template>
    <div v-if="mostrar" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">
            <div class="bg-sky-600 p-4 text-white font-black text-center uppercase tracking-widest flex justify-between items-center px-6">
                <span>{{ rol ? 'Editar Rol' : 'Nuevo Rol' }}</span>
                <button @click="$emit('cerrar')">✖</button>
            </div>
            
            <form @submit.prevent="guardar" class="p-6 space-y-6">
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">Nombre del Rol</label>
                    <input v-model="form.nombre" type="text" class="w-full rounded-xl border-slate-200 focus:ring-sky-500" required placeholder="Ej: Vendedor Senior">
                    <span v-if="form.errors.nombre" class="text-rose-500 text-xs">{{ form.errors.nombre }}</span>
                </div>

                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-2">Permisos Asignados</label>
                    <div class="grid grid-cols-2 gap-2 max-h-48 overflow-y-auto p-2 border rounded-xl bg-slate-50">
                        <label v-for="permiso in permisosDisponibles" :key="permiso.id" class="flex items-center gap-2 text-sm font-medium text-slate-700 cursor-pointer">
                            <input type="checkbox" :value="permiso.name" v-model="form.permisos" class="rounded text-sky-600 focus:ring-sky-500">
                            {{ permiso.name }}
                        </label>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t">
                    <button type="button" @click="$emit('cerrar')" class="px-4 py-2 text-sm font-bold text-slate-500 hover:bg-slate-100 rounded-xl">Cancelar</button>
                    <button type="submit" :disabled="form.processing" class="px-6 py-2 bg-sky-600 text-white text-sm font-black uppercase rounded-xl hover:bg-sky-700 disabled:opacity-50">
                        Guardar Rol
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>