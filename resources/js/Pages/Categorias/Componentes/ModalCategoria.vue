<script setup>
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({ mostrar: Boolean, categoria: Object });
const emit = defineEmits(['cerrar']);

const formulario = useForm({
    id: null,
    nombreCategoria: '',
    descripcion: '', // ¡Faltaba esto!
});

watch(() => props.categoria, (nuevoValor) => {
    formulario.clearErrors();
    if (nuevoValor) {
        formulario.id = nuevoValor.id;
        formulario.nombreCategoria = nuevoValor.nombreCategoria;
        formulario.descripcion = nuevoValor.descripcion || '';
    } else {
        formulario.reset();
    }
}, { immediate: true });

const guardar = () => {
    const esEdicion = !!formulario.id;
    const ruta = esEdicion ? route('categorias.update', formulario.id) : route('categorias.store');
    const metodo = esEdicion ? 'put' : 'post';
    
    formulario[metodo](ruta, {
        onSuccess: () => {
            Swal.fire({
                title: '¡Éxito!',
                text: `Categoría ${esEdicion ? 'actualizada' : 'guardada'} correctamente.`,
                icon: 'success',
                confirmButtonColor: '#0284c7',
            });
            emit('cerrar');
            formulario.reset();
        }
    });
};
</script>

<template>
    <div v-if="mostrar" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transition-all">
            <div class="bg-sky-600 p-4 text-white font-bold text-center uppercase tracking-widest">
                {{ formulario.id ? 'Editar Categoría' : 'Registrar Nueva Categoría' }}
            </div>
            
            <form @submit.prevent="guardar" class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Nombre de la Categoría</label>
                    <input 
                        v-model="formulario.nombreCategoria" 
                        type="text" 
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 focus:ring-sky-500 focus:border-sky-500 transition-all uppercase font-bold text-slate-700" 
                        :class="{'border-rose-500': formulario.errors.nombreCategoria}"
                        placeholder="EJ: BEBIDAS, LIMPIEZA..."
                        required
                    >
                    <p v-if="formulario.errors.nombreCategoria" class="text-rose-500 text-[10px] mt-1 font-bold">{{ formulario.errors.nombreCategoria }}</p>
                </div>

                <div>
                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Descripción</label>
                    <textarea 
                        v-model="formulario.descripcion" 
                        rows="3"
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 focus:ring-sky-500 focus:border-sky-500 transition-all resize-none text-sm text-slate-700" 
                        :class="{'border-rose-500': formulario.errors.descripcion}"
                        placeholder="Breve detalle de los productos que incluye..."
                    ></textarea>
                    <p v-if="formulario.errors.descripcion" class="text-rose-500 text-[10px] mt-1 font-bold">{{ formulario.errors.descripcion }}</p>
                </div>

                <div class="flex justify-end gap-3 border-t border-slate-100 pt-6 mt-4">
                    <button type="button" @click="$emit('cerrar')" class="px-5 py-2.5 text-sm font-bold text-slate-500 hover:text-slate-700 transition-colors uppercase tracking-widest">Cancelar</button>
                    <button type="submit" class="bg-sky-600 text-white px-8 py-2.5 rounded-xl font-bold hover:bg-sky-700 shadow-sm shadow-sky-600/30 transition-all uppercase text-xs tracking-widest active:scale-95" :disabled="formulario.processing">
                        {{ formulario.processing ? 'Enviando...' : 'Confirmar Datos' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>