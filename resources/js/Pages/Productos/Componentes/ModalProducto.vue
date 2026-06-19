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

const formatearDinero = (monto) => {
    return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(monto);
};

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
    promocion_activa: false,
    precio_promocion: '',
    etiqueta_promocion: '🔥 Promoción',
    promocion_fin: '',
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
        formulario.promocion_activa = Boolean(nuevoValor.promocion_activa);
        formulario.precio_promocion = nuevoValor.precio_promocion || '';
        formulario.etiqueta_promocion = nuevoValor.etiqueta_promocion || '🔥 Promoción';
        formulario.promocion_fin = nuevoValor.promocion_fin || '';
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
    
    if (!formulario.promocion_activa) {
        formulario.precio_promocion = '';
        formulario.etiqueta_promocion = '';
        formulario.promocion_fin = '';
    }

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
    <div v-if="mostrar" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-2 transition-opacity">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl flex flex-col overflow-hidden max-h-[90vh]">
            
            <!-- HEADER -->
            <div class="bg-slate-50 border-b border-slate-200 px-4 py-3 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-sky-100 flex items-center justify-center text-sky-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                    </div>
                    <h3 class="text-lg font-bold text-slate-800">
                        {{ formulario.id ? 'Editar Producto' : 'Nuevo Producto' }}
                    </h3>
                </div>
                <button @click="$emit('cerrar')" class="text-slate-400 hover:text-slate-600 hover:bg-slate-200 p-1.5 rounded-full transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            
            <!-- CONTENIDO SCROLLABLE -->
            <div class="p-4 overflow-y-auto flex-1 bg-white custom-scrollbar">
                <form id="productoForm" @submit.prevent="guardar" class="space-y-4">
                    
                    <!-- ===== SECCIÓN 1: INFORMACIÓN BÁSICA ===== -->
                    <div class="space-y-3">
                        <div class="flex items-center gap-2 border-b border-slate-200 pb-1.5">
                            <svg class="w-4 h-4 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Información básica</span>
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3">
                            <!-- Nombre -->
                            <div class="col-span-1 sm:col-span-2 lg:col-span-12">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Nombre del Producto *</label>
                                <input v-model="formulario.nombre" type="text" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg px-3 py-2 text-sm focus:bg-white focus:ring-2 focus:ring-sky-500 transition-colors" :class="{'border-rose-500 ring-rose-100': formulario.errors.nombre}" placeholder="Ej: Coca Cola 2.25L Retornable" required>
                                <p v-if="formulario.errors.nombre" class="text-rose-500 text-[10px] mt-0.5 font-medium">{{ formulario.errors.nombre }}</p>
                            </div>

                            <!-- Código barras -->
                            <div class="col-span-1 sm:col-span-1 lg:col-span-6">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Cód. Barras o PLU *</label>
                                <div class="flex gap-2">
                                    <input v-model="formulario.codigo_barras" type="text" minlength="2" maxlength="14" 
                                        @input="formulario.codigo_barras = formulario.codigo_barras.replace(/[^0-9]/g, '')" 
                                        class="flex-1 bg-slate-50 border border-slate-200 text-slate-800 rounded-lg px-3 py-2 text-sm font-mono focus:bg-white focus:ring-2 focus:ring-sky-500 transition-colors" 
                                        :class="{'border-rose-500': formulario.errors.codigo_barras}" 
                                        placeholder="Ej: 7791237290126" required>
                                        
                                    <button type="button" @click="autogenerarPlu" title="Generar PLU"
                                        class="bg-sky-100 text-sky-700 hover:bg-sky-200 border border-sky-200 rounded-lg px-3 flex items-center justify-center transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                                    </button>
                                </div>
                                <p v-if="formulario.errors.codigo_barras" class="text-rose-500 text-[10px] mt-0.5 font-medium">{{ formulario.errors.codigo_barras }}</p>
                            </div>

                            <!-- Categoría -->
                            <div class="col-span-1 sm:col-span-1 lg:col-span-6">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Categoría *</label>
                                <select v-model="formulario.categoria_id" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg px-3 py-2 text-sm focus:bg-white focus:ring-2 focus:ring-sky-500 transition-colors" :class="{'border-rose-500': formulario.errors.categoria_id}" required>
                                    <option value="" disabled>Seleccione...</option>
                                    <option v-for="cat in categorias" :key="cat.id" :value="cat.id">{{ cat.nombreCategoria }}</option>
                                </select>
                                <p v-if="formulario.errors.categoria_id" class="text-rose-500 text-[10px] mt-0.5 font-medium">{{ formulario.errors.categoria_id }}</p>
                            </div>

                            <!-- Marca -->
                            <div class="col-span-1 sm:col-span-1 lg:col-span-4">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Marca *</label>
                                <select v-model="formulario.marca_id" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg px-3 py-2 text-sm focus:bg-white focus:ring-2 focus:ring-sky-500 transition-colors" required>
                                    <option value="" disabled>Seleccione...</option>
                                    <option v-for="m in marcas" :key="m.id" :value="m.id">{{ m.nombreMarca }}</option>
                                </select>
                            </div>

                            <!-- Proveedor -->
                            <div class="col-span-1 sm:col-span-1 lg:col-span-4">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Proveedor *</label>
                                <select v-model="formulario.proveedor_id" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg px-3 py-2 text-sm focus:bg-white focus:ring-2 focus:ring-sky-500 transition-colors" required>
                                    <option value="" disabled>Seleccione...</option>
                                    <option v-for="prov in proveedores" :key="prov.id" :value="prov.id">{{ prov.razon_social }}</option>
                                </select>
                            </div>

                            <!-- Forma de venta -->
                            <div class="col-span-1 sm:col-span-1 lg:col-span-4">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Forma de Venta *</label>
                                <select v-model="formulario.unidad_medida" class="w-full bg-slate-50 border border-slate-200 text-sky-700 font-bold rounded-lg px-3 py-2 text-sm focus:bg-white focus:ring-2 focus:ring-sky-500 transition-colors">
                                    <option value="Unidad">Por Unidad</option>
                                    <option value="Kg">Por Peso (Kg)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- ===== SECCIÓN 2: PRECIOS ===== -->
                    <div class="space-y-3">
                        <div class="flex items-center gap-2 border-b border-slate-200 pb-1.5">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Precios</span>
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Precio Costo ($)</label>
                                <input v-model="formulario.precio_costo" type="number" step="0.01" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm text-rose-700 font-bold focus:bg-white focus:ring-2 focus:ring-sky-500 transition-colors" required>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Precio Venta ($)</label>
                                <input v-model="formulario.precio_venta" type="number" step="0.01" class="w-full bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2 text-sm text-emerald-800 font-bold focus:bg-white focus:ring-2 focus:ring-sky-500 transition-colors" required>
                            </div>
                        </div>
                    </div>

                    <!-- ===== SECCIÓN 3: PROMOCIÓN (con toggle) ===== -->
                    <div class="space-y-3">
                        <div class="flex items-center gap-2 border-b border-slate-200 pb-1.5">
                            <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Promoción</span>
                        </div>

                        <label class="flex items-center p-3 border border-amber-200 rounded-lg cursor-pointer hover:bg-amber-50 transition-colors" :class="{'bg-amber-50 border-amber-400': formulario.promocion_activa}">
                            <input type="checkbox" v-model="formulario.promocion_activa" class="w-4 h-4 text-amber-600 rounded border-slate-300">
                            <div class="ml-3">
                                <span class="block text-xs font-bold text-amber-700">Activar Promoción</span>
                                <span class="block text-[10px] text-amber-600">Badge y precio rebajado en tienda pública.</span>
                            </div>
                        </label>

                        <template v-if="formulario.promocion_activa">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Precio Promocional ($)</label>
                                    <input v-model="formulario.precio_promocion" type="number" step="0.01" :max="formulario.precio_venta" class="w-full bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 text-sm text-amber-800 font-bold focus:bg-white focus:ring-2 focus:ring-sky-500 transition-colors" :class="{'border-rose-500': formulario.errors.precio_promocion}">
                                    <p v-if="formulario.errors.precio_promocion" class="text-rose-500 text-[10px] mt-0.5 font-medium">{{ formulario.errors.precio_promocion }}</p>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Etiqueta</label>
                                    <select v-model="formulario.etiqueta_promocion" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg px-3 py-2 text-sm focus:bg-white focus:ring-2 focus:ring-sky-500 transition-colors">
                                        <option value="🔥 Promoción">🔥 Promoción</option>
                                        <option value="⚡ Oferta especial">⚡ Oferta especial</option>
                                        <option value="💰 Ahorrá hoy">💰 Ahorrá hoy</option>
                                        <option value="🏆 Oferta única">🏆 Oferta única</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Válido hasta</label>
                                    <input v-model="formulario.promocion_fin" type="date" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-800 focus:bg-white focus:ring-2 focus:ring-sky-500 transition-colors">
                                </div>
                            </div>

                            <div v-if="formulario.precio_promocion && formulario.precio_venta" class="flex items-center gap-2 px-3 py-1.5 bg-emerald-50 border border-emerald-200 rounded-lg">
                                <span class="text-[10px] font-bold text-emerald-700">
                                    Ahorro: {{ formatearDinero(parseFloat(formulario.precio_venta) - parseFloat(formulario.precio_promocion)) }}
                                    ({{ ((1 - formulario.precio_promocion / formulario.precio_venta) * 100).toFixed(1) }}% OFF)
                                </span>
                            </div>
                        </template>
                    </div>

                    <!-- ===== SECCIÓN 4: STOCK Y EXTRAS ===== -->
                    <div class="space-y-3">
                        <div class="flex items-center gap-2 border-b border-slate-200 pb-1.5">
                            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Stock y extras</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
                            <!-- Stock mínimo -->
                            <div class="col-span-1 sm:col-span-4">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Alerta Stock Mín.</label>
                                <div class="flex items-center border border-slate-200 rounded-lg overflow-hidden focus-within:ring-2 focus-within:ring-sky-500 bg-slate-50 transition-all">
                                    <input v-model="formulario.stock_minimo_visual" type="number" step="0.001" min="0" class="w-full border-none bg-transparent px-3 py-2 text-sm focus:ring-0 text-slate-800">
                                    <select v-if="formulario.unidad_medida === 'Kg'" v-model="formulario.unidad_peso_visual" class="border-y-0 border-r-0 border-l border-slate-200 bg-slate-100 px-2 py-2 focus:ring-0 text-xs font-bold text-sky-700">
                                        <option value="Kg">Kg</option>
                                        <option value="Gramos">Gr</option>
                                    </select>
                                    <span v-else class="border-l border-slate-200 bg-slate-100 px-3 py-2 text-xs font-bold text-slate-500 uppercase">Ud</span>
                                </div>
                            </div>

                            <!-- Stock inicial (solo creación) -->
                            <div v-if="!formulario.id" class="col-span-1 sm:col-span-8">
                                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-2 p-2.5 bg-amber-50 border border-amber-100 rounded-lg">
                                    <div class="flex-1">
                                        <span class="block text-xs font-bold text-amber-800">Stock Físico Inicial</span>
                                        <span class="block text-[10px] text-amber-600">Cantidad actual en estantería.</span>
                                    </div>
                                    <div class="w-full sm:w-48 flex items-center border border-amber-200 rounded-lg overflow-hidden bg-white shadow-sm focus-within:ring-2 focus-within:ring-amber-500 transition-all">
                                        <input v-model="formulario.stock_inicial_visual" type="number" step="0.001" min="0" class="w-full border-none bg-transparent px-3 py-1.5 text-sm focus:ring-0 text-slate-800 font-bold">
                                        <span class="bg-amber-100 px-3 py-1.5 text-[10px] font-black text-amber-700 uppercase">
                                            {{ formulario.unidad_medida === 'Kg' ? (formulario.unidad_peso_visual === 'Gramos' ? 'GR' : 'KG') : 'UD' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <!-- Retornable -->
                            <div>
                                <label class="flex items-center p-2.5 border border-slate-200 rounded-lg cursor-pointer hover:bg-slate-50 transition-colors" :class="{'bg-sky-50 border-sky-200': formulario.es_retornable}">
                                    <input type="checkbox" v-model="formulario.es_retornable" class="w-4 h-4 text-sky-600 rounded border-slate-300">
                                    <span class="ml-3 text-xs font-bold text-slate-700">Envase Retornable</span>
                                </label>
                            </div>

                            <!-- Descripción -->
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Descripción / Notas</label>
                                <textarea v-model="formulario.descripcion" rows="2" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg px-3 py-1.5 text-sm focus:bg-white focus:ring-2 focus:ring-sky-500 transition-colors resize-none" placeholder="Anotaciones internas..."></textarea>
                            </div>

                            <!-- Foto -->
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Foto</label>
                                <div class="h-20 border-2 border-dashed border-slate-300 rounded-lg bg-slate-50 hover:bg-slate-100 transition-colors flex items-center justify-center relative overflow-hidden group cursor-pointer" @click="$refs.fileInput.click()">
                                    <img v-if="imagenPreview" :src="imagenPreview" class="absolute inset-0 w-full h-full object-cover z-10">
                                    <div v-else class="text-center">
                                        <svg class="mx-auto h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Subir</span>
                                    </div>
                                    <input ref="fileInput" type="file" @input="alSeleccionarImagen" class="hidden" accept="image/*">
                                </div>
                                <p v-if="formulario.errors.imagen" class="text-rose-500 text-[10px] mt-0.5 font-bold text-center">{{ formulario.errors.imagen }}</p>
                            </div>
                        </div>
                    </div>

                </form>
            </div>

            <!-- FOOTER -->
            <div class="bg-slate-50 border-t border-slate-200 px-4 py-3 flex items-center justify-end gap-3 shrink-0">
                <button type="button" @click="$emit('cerrar')" class="px-5 py-2 rounded-lg text-sm font-bold text-slate-600 hover:bg-slate-200 transition-colors uppercase tracking-wider">Cancelar</button>
                <button type="submit" form="productoForm" class="bg-sky-600 text-white px-6 py-2 rounded-lg text-sm font-bold hover:bg-sky-700 shadow-lg shadow-sky-600/20 transition-all uppercase tracking-wider flex items-center gap-2" :disabled="formulario.processing">
                    <svg v-if="formulario.processing" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    {{ formulario.processing ? 'Guardando...' : 'Guardar Producto' }}
                </button>
            </div>

        </div>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 20px; }
</style>