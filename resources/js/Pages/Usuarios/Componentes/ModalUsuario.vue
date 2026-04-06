<script setup>
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    mostrar: Boolean,
    usuario: Object,
    roles: Array,
    sucursales: Array
});

const emit = defineEmits(['cerrar']);

const form = useForm({
    name: '',
    email: '',
    password: '',
    branch_id: '',
    rol: ''
});

// Resetea o llena el formulario cuando se abre el modal
watch(() => props.mostrar, (mostrando) => {
    if (mostrando) {
        if (props.usuario) {
            form.name = props.usuario.name;
            form.email = props.usuario.email;
            form.password = ''; // Por seguridad no traemos la contraseña
            form.branch_id = props.usuario.branch_id;
            form.rol = props.usuario.roles?.length > 0 ? props.usuario.roles[0].name : '';
        } else {
            form.reset();
        }
        form.clearErrors();
    }
});

const guardar = () => {
    if (props.usuario) {
        form.put(route('usuarios.update', props.usuario.id), {
            onSuccess: () => {
                emit('cerrar');
                Swal.fire('¡Actualizado!', 'Usuario modificado con éxito.', 'success');
            }
        });
    } else {
        form.post(route('usuarios.store'), {
            onSuccess: () => {
                emit('cerrar');
                Swal.fire('¡Creado!', 'Nuevo usuario registrado en el sistema.', 'success');
            }
        });
    }
};
</script>

<template>
    <div v-if="mostrar" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden animate-in zoom-in-95 duration-200">
            <div class="bg-[#0284c7] p-5 text-white flex justify-between items-center">
                <h3 class="font-bold uppercase tracking-widest text-sm">{{ usuario ? 'Editar Usuario' : 'Nuevo Usuario' }}</h3>
                <button @click="$emit('cerrar')" class="text-white hover:text-sky-200 text-xl font-bold">&times;</button>
            </div>
            
            <form @submit.prevent="guardar" class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2 sm:col-span-1">
                        <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Nombre</label>
                        <input v-model="form.name" type="text" class="w-full rounded-lg border-slate-200 text-sm focus:ring-sky-500" required>
                        <span class="text-rose-500 text-xs" v-if="form.errors.name">{{ form.errors.name }}</span>
                    </div>
                    <div class="col-span-2 sm:col-span-1">
                        <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Email</label>
                        <input v-model="form.email" type="email" class="w-full rounded-lg border-slate-200 text-sm focus:ring-sky-500" required>
                        <span class="text-rose-500 text-xs" v-if="form.errors.email">{{ form.errors.email }}</span>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Contraseña</label>
                    <input v-model="form.password" type="password" class="w-full rounded-lg border-slate-200 text-sm focus:ring-sky-500" :placeholder="usuario ? 'Dejar en blanco para no cambiarla' : 'Mínimo 8 caracteres'" :required="!usuario">
                    <span class="text-rose-500 text-xs" v-if="form.errors.password">{{ form.errors.password }}</span>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2 sm:col-span-1">
                        <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Sucursal Base</label>
                        <select v-model="form.branch_id" class="w-full rounded-lg border-slate-200 text-sm focus:ring-sky-500" required>
                            <option value="" disabled>Seleccione...</option>
                            <option v-for="sucursal in sucursales" :key="sucursal.id" :value="sucursal.id">{{ sucursal.nombre }}</option>
                        </select>
                    </div>
                    <div class="col-span-2 sm:col-span-1">
                        <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Rol / Perfil</label>
                        <select v-model="form.rol" class="w-full rounded-lg border-slate-200 text-sm focus:ring-sky-500 font-bold text-sky-700" required>
                            <option value="" disabled>Seleccione...</option>
                            <option v-for="rol in roles" :key="rol.id" :value="rol.name">{{ rol.name }}</option>
                        </select>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-6 mt-2 border-t border-slate-100">
                    <button type="button" @click="$emit('cerrar')" class="px-5 py-2.5 text-sm font-bold text-slate-500 hover:bg-slate-100 rounded-lg transition-colors">Cancelar</button>
                    <button type="submit" :disabled="form.processing" class="px-5 py-2.5 bg-[#0284c7] text-white text-sm font-bold uppercase tracking-widest rounded-lg hover:bg-sky-700 disabled:opacity-50 transition-colors">
                        Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>