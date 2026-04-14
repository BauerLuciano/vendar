<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({
    mostrar: Boolean,
    producto: Object,
    categorias: Array,
    marcas: Array,
    proveedores: Array
});

const page = usePage();
const emit = defineEmits(['cerrar']);
const imagenPreview = ref(null);

const formulario = useForm({
    id: null,
    nombre: '',
    codigo_barras: '',
    categoria_id: '',
    marca_id: '',
    proveedor_id: '',
    unidad_medida: 'Unidad',
    es_retornable: false,
    precio_costo: '',
    precio_venta: '',
    stock_minimo: 0,         
    stock_minimo_visual: 0,  
    stock_inicial: 0,
    stock_inicial_visual: 0, 
    unidad_peso_visual: 'Kg',
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
        formulario.proveedor_id = nuevoValor.proveedor_id || '';
        formulario.unidad_medida = nuevoValor.unidad_medida;
        formulario.es_retornable = Boolean(nuevoValor.es_retornable);
        formulario.precio_costo = nuevoValor.precio_costo;
        formulario.precio_venta = nuevoValor.precio_venta;
        formulario.stock_minimo = nuevoValor.stock_minimo;
        formulario.descripcion = nuevoValor.descripcion || '';
        imagenPreview.value = nuevoValor.url_imagen;

        if (nuevoValor.unidad_medida === 'Kg' && nuevoValor.stock_minimo > 0 && nuevoValor.stock_minimo < 1) {
            formulario.stock_minimo_visual = nuevoValor.stock_minimo * 1000;
            formulario.unidad_peso_visual = 'Gramos';
        } else {
            formulario.stock_minimo_visual = nuevoValor.stock_minimo;
            formulario.unidad_peso_visual = 'Kg';
        }
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

const autogenerarPlu = async () => {
    try {
        const respuesta = await axios.get(route('productos.generar-plu'));
        formulario.codigo_barras = respuesta.data.plu_sugerido;
    } catch (error) {
        console.error("Error al generar PLU", error);
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'error',
            title: 'Error al generar el código',
            showConfirmButton: false,
            timer: 3000
        });
    }
};

const guardar = () => {
    if (formulario.unidad_medida === 'Kg' && formulario.unidad_peso_visual === 'Gramos') {
        formulario.stock_minimo = formulario.stock_minimo_visual / 1000;
        formulario.stock_inicial = formulario.stock_inicial_visual / 1000;
    } else {
        formulario.stock_minimo = formulario.stock_minimo_visual;
        formulario.stock_inicial = formulario.stock_inicial_visual;
    }

    const esEdicion = !!formulario.id;
    const ruta = esEdicion ? route('productos.update', formulario.id) : route('productos.store');
    
    formulario.post(ruta, {
        forceFormData: true,
        onSuccess: () => {
            Swal.fire({
                title: '¡Éxito!',
                text: `Producto ${esEdicion ? 'actualizado' : 'registrado'} correctamente.`,
                icon: 'success',
                confirmButtonColor: '#0284c7'
            });
            emit('cerrar');
            formulario.reset();
            imagenPreview.value = null;
        },
        onError: (err) => {
            console.error(err);
        }
    });
};
</script>

<template>
    <div v-if="mostrar" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 sm:p-6 transition-opacity">
        
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl flex flex-col overflow-hidden max-h-full sm:max-h-[90vh]">
            
            <div class="bg-slate-50 border-b border-slate-200 px-6 py-4 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-sky-100 flex items-center justify-center text-sky-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-800">
                        {{ formulario.id ? 'Editar Producto' : 'Registrar Nuevo Producto' }}
                    </h3>
                </div>
                <button @click="$emit('cerrar')" class="text-slate-400 hover:text-slate-600 hover:bg-slate-200 p-2 rounded-full transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            
            <div class="p-6 overflow-y-auto flex-1 bg-white custom-scrollbar">
                <form id="productoForm" @submit.prevent="guardar" class="grid grid-cols-1 md:grid-cols-12 gap-5">
                    
                    <div class="col-span-1 md:col-span-12">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Nombre del Producto *</label>
                        <input v-model="formulario.nombre" type="text" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-2.5 focus:bg-white focus:ring-2 focus:ring-sky-500 transition-colors" :class="{'border-rose-500 ring-rose-100': formulario.errors.nombre}" placeholder="Ej: Coca Cola 2.25L Retornable" required>
                        <p v-if="formulario.errors.nombre" class="text-rose-500 text-xs mt-1 font-medium">{{ formulario.errors.nombre }}</p>
                    </div>

                    <div class="col-span-1 md:col-span-6">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Cód. Barras o PLU *</label>
                        <div class="flex gap-2">
                            <input v-model="formulario.codigo_barras" type="text" minlength="2" maxlength="14" 
                                @input="formulario.codigo_barras = formulario.codigo_barras.replace(/[^0-9]/g, '')" 
                                class="flex-1 bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-2.5 font-mono focus:bg-white focus:ring-2 focus:ring-sky-500 transition-colors" 
                                :class="{'border-rose-500': formulario.errors.codigo_barras}" 
                                placeholder="Ej: 7791237290126 o PLU" required>
                                
                            <button type="button" @click="autogenerarPlu" title="Generar código interno (PLU)"
                                class="bg-sky-100 text-sky-700 hover:bg-sky-200 hover:text-sky-800 border border-sky-200 rounded-xl px-3 flex items-center justify-center transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                            </button>
                        </div>
                        <p v-if="formulario.errors.codigo_barras" class="text-rose-500 text-xs mt-1 font-medium">{{ formulario.errors.codigo_barras }}</p>
                    </div>

                    <div class="col-span-1 md:col-span-6">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Categoría *</label>
                        <select v-model="formulario.categoria_id" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-2.5 focus:bg-white focus:ring-2 focus:ring-sky-500 transition-colors" :class="{'border-rose-500': formulario.errors.categoria_id}" required>
                            <option value="" disabled>Seleccione una categoría...</option>
                            <option v-for="cat in categorias" :key="cat.id" :value="cat.id">{{ cat.nombreCategoria }}</option>
                        </select>
                        <p v-if="formulario.errors.categoria_id" class="text-rose-500 text-xs mt-1 font-medium">{{ formulario.errors.categoria_id }}</p>
                    </div>

                    <div class="col-span-1 md:col-span-4">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Marca *</label>
                        <select v-model="formulario.marca_id" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-2.5 focus:bg-white focus:ring-2 focus:ring-sky-500 transition-colors" required>
                            <option value="" disabled>Seleccione...</option>
                            <option v-for="m in marcas" :key="m.id" :value="m.id">{{ m.nombreMarca }}</option>
                        </select>
                    </div>

                    <div class="col-span-1 md:col-span-4">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Proveedor *</label>
                        <select v-model="formulario.proveedor_id" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-2.5 focus:bg-white focus:ring-2 focus:ring-sky-500 transition-colors" required>
                            <option value="" disabled>Seleccione...</option>
                            <option v-for="prov in proveedores" :key="prov.id" :value="prov.id">{{ prov.razon_social }}</option>
                        </select>
                    </div>

                    <div class="col-span-1 md:col-span-4">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Forma de Venta *</label>
                        <select v-model="formulario.unidad_medida" class="w-full bg-slate-50 border border-slate-200 text-sky-700 font-bold rounded-xl px-4 py-2.5">
                            <option value="Unidad">Por Unidad</option>
                            <option value="Kg">Por Peso (Kg)</option>
                        </select>
                    </div>

                    <div class="col-span-1 md:col-span-12 border-t border-slate-100 my-2"></div>

                    <div class="col-span-1 md:col-span-4">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Precio Costo ($)</label>
                        <input v-model="formulario.precio_costo" type="number" step="0.01" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-rose-700 font-bold" required>
                    </div>

                    <div class="col-span-1 md:col-span-4">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Precio Venta ($)</label>
                        <input v-model="formulario.precio_venta" type="number" step="0.01" class="w-full bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-2.5 text-emerald-800 font-bold" required>
                    </div>

                    <div class="col-span-1 md:col-span-4">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Alerta Stock Mín.</label>
                        <div class="flex items-center border border-slate-200 rounded-xl overflow-hidden focus-within:ring-2 focus-within:ring-sky-500 bg-slate-50 transition-all">
                            <input v-model="formulario.stock_minimo_visual" type="number" step="0.001" min="0" class="w-full border-none bg-transparent px-4 py-2.5 focus:ring-0 text-slate-800">
                            <select v-if="formulario.unidad_medida === 'Kg'" v-model="formulario.unidad_peso_visual" class="border-y-0 border-r-0 border-l border-slate-200 bg-slate-100 px-3 py-2.5 focus:ring-0 text-sm font-bold text-sky-700">
                                <option value="Kg">Kilos</option>
                                <option value="Gramos">Gramos</option>
                            </select>
                            <span v-else class="border-l border-slate-200 bg-slate-100 px-4 py-2.5 text-sm font-bold text-slate-500 uppercase">Ud</span>
                        </div>
                    </div>

                    <div v-if="!formulario.id" class="col-span-1 md:col-span-12">
                        <div class="p-4 bg-amber-50 border border-amber-100 rounded-2xl flex flex-col md:flex-row items-center gap-4">
                            <div class="flex-1">
                                <span class="block text-sm font-bold text-amber-800">Stock Físico Inicial</span>
                                <span class="block text-xs text-amber-600 tracking-tight">Cargá la cantidad que tenés actualmente en estantería.</span>
                            </div>
                            <div class="w-full md:w-64 flex items-center border border-amber-200 rounded-xl overflow-hidden bg-white shadow-sm focus-within:ring-2 focus-within:ring-amber-500 transition-all">
                                <input v-model="formulario.stock_inicial_visual" type="number" step="0.001" min="0" class="w-full border-none bg-transparent px-4 py-2.5 focus:ring-0 text-slate-800 font-black text-lg">
                                <span class="bg-amber-100 px-4 py-2.5 text-xs font-black text-amber-700 uppercase">
                                    {{ formulario.unidad_medida === 'Kg' ? (formulario.unidad_peso_visual === 'Gramos' ? 'GR' : 'KG') : 'UD' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="col-span-1 md:col-span-12">
                        <label class="flex items-center p-4 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-50 transition-colors" :class="{'bg-sky-50 border-sky-200': formulario.es_retornable}">
                            <input type="checkbox" v-model="formulario.es_retornable" class="w-5 h-5 text-sky-600 rounded border-slate-300">
                            <div class="ml-4">
                                <span class="block text-sm font-bold text-slate-700">Envase Retornable</span>
                                <span class="block text-xs text-slate-500">Activá esto si el cliente debe entregar un envase al comprar.</span>
                            </div>
                        </label>
                    </div>

                    <div class="col-span-1 md:col-span-8 flex flex-col">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Descripción o Notas</label>
                        <textarea v-model="formulario.descripcion" rows="4" class="w-full flex-1 bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-3 focus:bg-white focus:ring-2 focus:ring-sky-500 transition-colors resize-none" placeholder="Anotaciones internas..."></textarea>
                    </div>

                    <div class="col-span-1 md:col-span-4 flex flex-col">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Foto del Producto</label>
                        <div class="flex-1 border-2 border-dashed border-slate-300 rounded-xl bg-slate-50 hover:bg-slate-100 transition-colors flex flex-col items-center justify-center relative overflow-hidden group cursor-pointer" @click="$refs.fileInput.click()">
                            <img v-if="imagenPreview" :src="imagenPreview" class="absolute inset-0 w-full h-full object-cover z-10">
                            <div v-else class="text-center p-4">
                                <svg class="mx-auto h-8 w-8 text-slate-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Subir Foto</span>
                            </div>
                            <input ref="fileInput" type="file" @input="alSeleccionarImagen" class="hidden" accept="image/*">
                        </div>
                        <p v-if="formulario.errors.imagen" class="text-rose-500 text-[10px] mt-1 font-bold text-center">{{ formulario.errors.imagen }}</p>
                    </div>

                </form>
            </div>

            <div class="bg-slate-50 border-t border-slate-200 px-6 py-4 flex items-center justify-end gap-3 shrink-0">
                <button type="button" @click="$emit('cerrar')" class="px-5 py-2.5 rounded-xl text-sm font-bold text-slate-600 hover:bg-slate-200 transition-colors uppercase tracking-wider">Cancelar</button>
                <button type="submit" form="productoForm" class="bg-sky-600 text-white px-8 py-2.5 rounded-xl text-sm font-bold hover:bg-sky-700 shadow-lg shadow-sky-600/20 transition-all uppercase tracking-wider flex items-center gap-2" :disabled="formulario.processing">
                    <svg v-if="formulario.processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    {{ formulario.processing ? 'Guardando...' : 'Guardar Producto' }}
                </button>
            </div>

        </div>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 20px; }
</style>