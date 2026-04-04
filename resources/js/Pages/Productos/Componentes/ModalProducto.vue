<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    mostrar: Boolean,
    producto: Object,
    categorias: Array,
    marcas: Array
});

const emit = defineEmits(['cerrar']);
const imagenPreview = ref(null);

const formulario = useForm({
    id: null,
    nombre: '',
    codigo_barras: '',
    categoria_id: '',
    marca_id: '',
    unidad_medida: 'Unidad',
    es_retornable: false,
    precio_costo: '',
    precio_venta: '',
    stock_minimo: 0,
    descripcion: '',
    imagen: null,
});

watch(() => props.producto, (nuevoValor) => {
    formulario.clearErrors();
    if (nuevoValor) {
        formulario.id = nuevoValor.id;
        formulario.nombre = nuevoValor.nombre;
        formulario.codigo_barras = nuevoValor.codigo_barras;
        formulario.categoria_id = nuevoValor.categoria_id;
        formulario.marca_id = nuevoValor.marca_id;
        formulario.unidad_medida = nuevoValor.unidad_medida;
        formulario.es_retornable = Boolean(nuevoValor.es_retornable);
        formulario.precio_costo = nuevoValor.precio_costo;
        formulario.precio_venta = nuevoValor.precio_venta;
        formulario.stock_minimo = nuevoValor.stock_minimo;
        formulario.descripcion = nuevoValor.descripcion || '';
        imagenPreview.value = nuevoValor.url_imagen;
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
    const ruta = esEdicion ? route('productos.update', formulario.id) : route('productos.store');
    
    formulario.post(ruta, {
        forceFormData: true,
        onSuccess: () => {
            Swal.fire('¡Éxito!', `Producto ${esEdicion ? 'actualizado' : 'guardado'} correctamente.`, 'success');
            emit('cerrar');
            formulario.reset();
            imagenPreview.value = null;
        }
    });
};
</script>

<template>
    <div v-if="mostrar" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl overflow-y-auto max-h-[90vh]">
            <div class="bg-sky-600 p-4 text-white font-bold text-center uppercase tracking-widest sticky top-0 z-10">
                {{ formulario.id ? 'Editar Producto' : 'Registrar Nuevo Producto' }}
            </div>
            
            <form @submit.prevent="guardar" class="p-6 grid grid-cols-2 gap-x-6 gap-y-4">
                
                <div class="col-span-2">
                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Nombre del Producto</label>
                    <input v-model="formulario.nombre" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 focus:ring-sky-500 focus:border-sky-500" :class="{'border-rose-500': formulario.errors.nombre}" required>
                    <p v-if="formulario.errors.nombre" class="text-rose-500 text-[10px] mt-1 font-bold">{{ formulario.errors.nombre }}</p>
                </div>

                <div>
                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Cód. Barras</label>
                    <input 
                        v-model="formulario.codigo_barras" 
                        type="text" 
                        minlength="8" 
                        maxlength="14" 
                        @input="formulario.codigo_barras = formulario.codigo_barras.replace(/[^0-9]/g, '')" 
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 font-mono" 
                        :class="{'border-rose-500': formulario.errors.codigo_barras}" 
                        placeholder="Ej: 7791234567890"
                        required
                    >
                    <p v-if="formulario.errors.codigo_barras" class="text-rose-500 text-[10px] mt-1 font-bold">{{ formulario.errors.codigo_barras }}</p>
                </div>

                <div>
                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Stock Mínimo Global</label>
                    <input v-model="formulario.stock_minimo" type="number" min="0" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2" :class="{'border-rose-500': formulario.errors.stock_minimo}">
                    <p v-if="formulario.errors.stock_minimo" class="text-rose-500 text-[10px] mt-1 font-bold">{{ formulario.errors.stock_minimo }}</p>
                </div>

                <div>
                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Categoría</label>
                    <select v-model="formulario.categoria_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2" :class="{'border-rose-500': formulario.errors.categoria_id}" required>
                        <option value="" disabled>Seleccione...</option>
                        <option v-for="cat in categorias" :key="cat.id" :value="cat.id">{{ cat.nombreCategoria }}</option>
                    </select>
                    <p v-if="formulario.errors.categoria_id" class="text-rose-500 text-[10px] mt-1 font-bold">{{ formulario.errors.categoria_id }}</p>
                </div>

                <div>
                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Marca</label>
                    <select v-model="formulario.marca_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2" :class="{'border-rose-500': formulario.errors.marca_id}" required>
                        <option value="" disabled>Seleccione...</option>
                        <option v-for="m in marcas" :key="m.id" :value="m.id">{{ m.nombreMarca }}</option>
                    </select>
                    <p v-if="formulario.errors.marca_id" class="text-rose-500 text-[10px] mt-1 font-bold">{{ formulario.errors.marca_id }}</p>
                </div>

                <div>
                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Unidad de Medida</label>
                    <select v-model="formulario.unidad_medida" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 font-bold text-sky-700" :class="{'border-rose-500': formulario.errors.unidad_medida}">
                        <option value="Unidad">Unidad</option>
                        <option value="Kg">Kilogramo (Kg)</option>
                        <option value="Gramos">Gramos (gr)</option>
                    </select>
                    <p v-if="formulario.errors.unidad_medida" class="text-rose-500 text-[10px] mt-1 font-bold">{{ formulario.errors.unidad_medida }}</p>
                </div>

                <div class="flex items-center mt-6">
                    <input type="checkbox" id="retornable" v-model="formulario.es_retornable" class="w-5 h-5 text-sky-600 bg-slate-50 border-slate-300 rounded focus:ring-sky-500">
                    <label for="retornable" class="ml-2 text-sm font-bold text-slate-700 uppercase">Es Envase Retornable</label>
                </div>

                <div>
                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Precio Costo ($)</label>
                    <input v-model="formulario.precio_costo" type="number" step="0.01" min="0" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-rose-700 font-bold" :class="{'border-rose-500': formulario.errors.precio_costo}" required>
                    <p v-if="formulario.errors.precio_costo" class="text-rose-500 text-[10px] mt-1 font-bold">{{ formulario.errors.precio_costo }}</p>
                </div>

                <div>
                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Precio Venta ($)</label>
                    <input v-model="formulario.precio_venta" type="number" step="0.01" min="0" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-emerald-700 font-bold" :class="{'border-rose-500': formulario.errors.precio_venta}" required>
                    <p v-if="formulario.errors.precio_venta" class="text-rose-500 text-[10px] mt-1 font-bold">{{ formulario.errors.precio_venta }}</p>
                </div>

                <div class="col-span-2">
                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Descripción</label>
                    <textarea v-model="formulario.descripcion" rows="2" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 resize-none" :class="{'border-rose-500': formulario.errors.descripcion}"></textarea>
                    <p v-if="formulario.errors.descripcion" class="text-rose-500 text-[10px] mt-1 font-bold">{{ formulario.errors.descripcion }}</p>
                </div>

                <div class="col-span-2 flex flex-col items-center border-t border-slate-100 pt-4">
                    <div class="w-24 h-24 border-2 border-dashed border-slate-300 rounded-xl overflow-hidden flex items-center justify-center bg-slate-50 shadow-inner">
                        <img v-if="imagenPreview" :src="imagenPreview" class="w-full h-full object-cover">
                        <span v-else class="text-slate-400 text-[10px] uppercase font-bold text-center">Subir<br>Foto</span>
                    </div>
                    <label class="mt-3 cursor-pointer bg-slate-800 text-white px-4 py-2 rounded-xl text-xs font-bold hover:bg-slate-700 transition-colors">
                        Seleccionar Imagen
                        <input type="file" @input="alSeleccionarImagen" class="hidden" accept="image/*">
                    </label>
                    <p v-if="formulario.errors.imagen" class="text-rose-500 text-[10px] mt-1 font-bold">{{ formulario.errors.imagen }}</p>
                </div>

                <div class="col-span-2 flex justify-end gap-3 border-t border-slate-100 pt-4 mt-2">
                    <button type="button" @click="$emit('cerrar')" class="px-5 py-2.5 text-sm font-bold text-slate-500 hover:text-slate-700 transition-colors uppercase tracking-widest">Cancelar</button>
                    <button type="submit" class="bg-sky-600 text-white px-8 py-2.5 rounded-xl font-bold hover:bg-sky-700 shadow-sm shadow-sky-600/30 transition-all uppercase tracking-widest text-xs" :disabled="formulario.processing">
                        {{ formulario.processing ? 'Guardando...' : 'Guardar Producto' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>