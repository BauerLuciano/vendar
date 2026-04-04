<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({ mostrar: Boolean, marca: Object });
const emit = defineEmits(['cerrar']);
const imagenPreview = ref(null);

const formulario = useForm({
    id: null,
    nombreMarca: '',
    imagen: null,
});

watch(() => props.marca, (nuevo) => {
    formulario.clearErrors();
    if (nuevo) {
        formulario.id = nuevo.id;
        formulario.nombreMarca = nuevo.nombreMarca;
        imagenPreview.value = nuevo.url_imagen;
    } else {
        formulario.reset();
        imagenPreview.value = null;
    }
}, { immediate: true });

const alSeleccionarImagen = (e) => {
    const archivo = e.target.files[0];
    if (archivo) {
        formulario.imagen = archivo;
        imagenPreview.value = URL.createObjectURL(archivo);
    }
};

const guardar = () => {
    const esEdicion = !!formulario.id;
    const ruta = esEdicion ? route('marcas.update', formulario.id) : route('marcas.store');
    
    formulario.post(ruta, {
        forceFormData: true,
        onSuccess: () => {
            Swal.fire('¡Éxito!', `Marca ${esEdicion ? 'actualizada' : 'guardada'} correctamente.`, 'success');
            emit('cerrar');
            formulario.reset();
            imagenPreview.value = null;
        }
    });
};
</script>

<template>
    <div v-if="mostrar" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transition-all">
            <div class="bg-sky-600 p-4 text-white font-bold text-center uppercase tracking-widest">
                {{ formulario.id ? 'Editar Marca' : 'Nueva Marca' }}
            </div>
            <form @submit.prevent="guardar" class="p-6">
                
                <div class="mb-6">
                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Nombre de la Marca</label>
                    <input 
                        v-model="formulario.nombreMarca" 
                        type="text" 
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 uppercase font-bold text-slate-700 focus:ring-sky-500 focus:border-sky-500" 
                        :class="{'border-rose-500': formulario.errors.nombreMarca}"
                        required
                    >
                    <p v-if="formulario.errors.nombreMarca" class="text-rose-500 text-[10px] mt-1 font-bold">{{ formulario.errors.nombreMarca }}</p>
                </div>

                <div class="flex flex-col items-center border-t border-slate-100 pt-6 mb-4">
                    <div class="w-24 h-24 border-2 border-dashed border-slate-300 rounded-xl mb-3 overflow-hidden bg-slate-50 flex items-center justify-center shadow-inner">
                        <img v-if="imagenPreview" :src="imagenPreview" class="w-full h-full object-contain p-2">
                        <span v-else class="text-slate-400 text-[10px] uppercase font-bold text-center">Sin logo</span>
                    </div>
                    <label class="bg-slate-800 text-white px-5 py-2 rounded-xl text-xs font-bold cursor-pointer hover:bg-slate-700 transition-colors uppercase tracking-widest">
                        Seleccionar Logo
                        <input type="file" @input="alSeleccionarImagen" class="hidden" accept="image/*">
                    </label>
                    <p v-if="formulario.errors.imagen" class="text-rose-500 text-[10px] mt-2 font-bold">{{ formulario.errors.imagen }}</p>
                </div>

                <div class="flex justify-end gap-3 border-t border-slate-100 pt-6">
                    <button type="button" @click="$emit('cerrar')" class="px-5 py-2.5 text-sm font-bold text-slate-500 hover:text-slate-700 transition-colors uppercase tracking-widest">Cancelar</button>
                    <button type="submit" class="bg-sky-600 text-white px-8 py-2.5 rounded-xl font-bold shadow-sm shadow-sky-600/30 hover:bg-sky-700 transition-all uppercase text-xs tracking-widest active:scale-95" :disabled="formulario.processing">
                        Confirmar
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>