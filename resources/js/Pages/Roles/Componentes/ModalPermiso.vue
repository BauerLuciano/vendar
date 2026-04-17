<script setup>
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({ mostrar: Boolean, permiso: Object });
const emit = defineEmits(['cerrar']);

const form = useForm({ 
    nombre: '',
    descripcion: ''
});

// Cuando se abre el modal, cargamos los datos si es edición, o vaciamos si es nuevo
watch(() => props.mostrar, (mostrando) => {
    if (mostrando) {
        if (props.permiso) {
            form.nombre = props.permiso.name;
            form.descripcion = props.permiso.description || '';
        } else {
            form.reset();
        }
        form.clearErrors();
    }
});

const submit = () => {
    if (props.permiso) {
        form.put(route('permisos.update', props.permiso.id), {
            onSuccess: () => {
                emit('cerrar');
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Permiso actualizado', showConfirmButton: false, timer: 3000 });
            }
        });
    } else {
        form.post(route('permisos.store'), {
            onSuccess: () => {
                emit('cerrar');
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Permiso creado', showConfirmButton: false, timer: 3000 });
            }
        });
    }
};
</script>

<template>
    <div v-if="mostrar" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
        <div class="bg-white rounded-3xl shadow-xl w-full max-w-md overflow-hidden transform transition-all">
            
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                <h3 class="text-lg font-black text-slate-800 uppercase tracking-tight">
                    {{ permiso ? 'Editar Permiso' : 'Nuevo Permiso' }}
                </h3>
                <button @click="$emit('cerrar')" class="text-slate-400 hover:text-slate-600 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <form @submit.prevent="submit" class="p-6 space-y-4">
                
                <div>
                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Identificador del Permiso</label>
                    <input 
                        v-model="form.nombre" 
                        type="text" 
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-indigo-500 focus:border-indigo-500 font-mono text-sm text-slate-700" 
                        placeholder="Ej: eliminar facturas"
                        required
                    >
                    <p v-if="form.errors.nombre" class="mt-1 text-xs text-rose-500 font-bold">{{ form.errors.nombre }}</p>
                </div>

                <div>
                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Descripción de la acción</label>
                    <textarea 
                        v-model="form.descripcion" 
                        rows="3"
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-indigo-500 focus:border-indigo-500 font-medium text-sm text-slate-700" 
                        placeholder="Explica qué hace este permiso en el sistema..."
                    ></textarea>
                    <p v-if="form.errors.descripcion" class="mt-1 text-xs text-rose-500 font-bold">{{ form.errors.descripcion }}</p>
                </div>

                <div class="bg-amber-50 border border-amber-100 p-3 rounded-xl flex gap-3 items-start mt-2">
                    <svg class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    <p class="text-[10px] text-amber-700 font-medium leading-tight">
                        <strong>Atención al desarrollador:</strong> Crear el registro en esta base de datos no aplica la restricción de seguridad automáticamente. Recordá implementar el bloqueo (el "patovica") en el código fuente de Vue y Laravel usando directivas de Spatie.
                    </p>
                </div>

                <div class="pt-4 flex justify-end gap-3 border-t border-slate-100 mt-4">
                    <button type="button" @click="$emit('cerrar')" class="px-5 py-2.5 text-sm font-bold text-slate-500 hover:text-slate-700 transition-colors">Cancelar</button>
                    <button type="submit" :disabled="form.processing" class="bg-indigo-600 hover:bg-indigo-700 disabled:bg-slate-300 text-white font-bold py-2.5 px-6 rounded-xl shadow-sm shadow-indigo-600/30 transition-all flex items-center gap-2">
                        <span v-if="form.processing">Guardando...</span>
                        <span v-else>Guardar Permiso</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>