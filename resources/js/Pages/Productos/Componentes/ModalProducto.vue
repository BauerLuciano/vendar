<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';
import LectorCamara from '@/Components/LectorCamara.vue';

const props = defineProps({
    mostrar: Boolean,
    producto: Object,
    categorias: Array,
    marcas: Array,
    proveedores: Array
});

const emit = defineEmits(['cerrar']);
const imagenPreview = ref(null);

const OPCIONES_UNIDAD_COMPRA = ['Caja', 'Pack', 'Bolsa', 'Fardo', 'Pallet', 'Otro'];

const DEFAULTS_CANTIDAD_COMPRA = { Caja: 12, Pack: 6, Bolsa: 25, Fardo: 24, Pallet: 100 };

const formulario = useForm({
    id: null,
    nombre: '',
    codigo_barras: '',
    categoria_id: '',
    marca_id: '',
    proveedor_id: '',
    unidad_medida: 'Unidad',
    unidad_compra: null,
    cantidad_por_compra: null,
    es_retornable: false,
    precio_costo: '',
    precio_venta: '',
    stock_minimo: 0,
    stock_minimo_visual: 0,
    stock_objetivo: '',
    stock_objetivo_visual: '',
    stock_inicial: 0,
    stock_inicial_visual: 0,
    unidad_peso_visual: 'Kg',
    descripcion: '',
    imagen: null,
    imagen_url: null,
});

const sufijoStock = computed(() => {
    if (formulario.unidad_medida === 'Kg') {
        return formulario.unidad_peso_visual === 'Gramos' ? 'Gr' : 'Kg';
    }
    return 'Ud';
});

const unidadCompraTexto = computed(() => {
    if (formulario.unidad_compra === 'Otro') return 'unidades';
    return formulario.unidad_compra ? formulario.unidad_compra.toLowerCase() + 's' : 'unidades';
});

const equivalenciaBultos = computed(() => {
    const objetivo = parseFloat(formulario.stock_objetivo);
    const cantidad = parseFloat(formulario.cantidad_por_compra);
    if (!objetivo || !cantidad || cantidad < 1) return null;
    const bultos = Math.ceil(objetivo / cantidad);
    return { bultos, porBulto: cantidad, unidad: unidadCompraTexto.value };
});

watch(() => props.producto, (nuevoValor) => {
    formulario.clearErrors();
    productosSimilares.value = [];
    if (nuevoValor) {
        formulario.id = nuevoValor.id;
        formulario.nombre = nuevoValor.nombre;
        formulario.codigo_barras = nuevoValor.codigo_barras;
        formulario.categoria_id = nuevoValor.categoria_id;
        formulario.marca_id = nuevoValor.marca_id;
        formulario.proveedor_id = nuevoValor.proveedor_id || '';
        formulario.unidad_medida = nuevoValor.unidad_medida;
        formulario.unidad_compra = nuevoValor.unidad_compra || null;
        formulario.cantidad_por_compra = nuevoValor.cantidad_por_compra || null;
        formulario.es_retornable = Boolean(nuevoValor.es_retornable);
        formulario.precio_costo = nuevoValor.precio_costo;
        formulario.precio_venta = nuevoValor.precio_venta;
        formulario.stock_minimo = nuevoValor.stock_minimo;
        formulario.stock_objetivo = nuevoValor.stock_objetivo || '';
        formulario.descripcion = nuevoValor.descripcion || '';
        formulario.imagen_url = null;
        imagenPreview.value = nuevoValor.url_imagen;

        if (nuevoValor.unidad_medida === 'Kg') {
            if (nuevoValor.stock_minimo > 0 && nuevoValor.stock_minimo < 1) {
                formulario.stock_minimo_visual = Math.round(nuevoValor.stock_minimo * 1000);
                formulario.unidad_peso_visual = 'Gramos';
            } else {
                formulario.stock_minimo_visual = nuevoValor.stock_minimo;
                formulario.unidad_peso_visual = 'Kg';
            }
        } else {
            formulario.stock_minimo_visual = nuevoValor.stock_minimo;
        }

        if (nuevoValor.unidad_medida === 'Kg') {
            const obj = parseFloat(nuevoValor.stock_objetivo);
            if (obj > 0 && obj < 1) {
                formulario.stock_objetivo_visual = Math.round(obj * 1000);
                formulario.unidad_peso_visual = 'Gramos';
            } else {
                formulario.stock_objetivo_visual = nuevoValor.stock_objetivo || '';
                if (nuevoValor.stock_objetivo) {
                    formulario.unidad_peso_visual = 'Kg';
                }
            }
        } else {
            formulario.stock_objetivo_visual = nuevoValor.stock_objetivo || '';
        }
    } else {
        formulario.reset();
        imagenPreview.value = null;
    }
}, { immediate: true });

const productosSimilares = ref([]);
let buscarSimilaresTimer = null;

const tieneCoincidenciaExacta = computed(() => {
    if (!productosSimilares.value.length) return false;
    const termino = formulario.nombre?.trim().toLowerCase();
    if (!termino) return false;
    return productosSimilares.value.some(p => p.nombre.toLowerCase().trim() === termino);
});

watch(() => formulario.unidad_compra, (valor) => {
    if (valor && valor !== 'Otro' && (formulario.cantidad_por_compra === null || formulario.cantidad_por_compra === '' || formulario.cantidad_por_compra === undefined)) {
        formulario.cantidad_por_compra = DEFAULTS_CANTIDAD_COMPRA[valor] ?? null;
    }
});

watch(() => formulario.nombre, (valor) => {
    clearTimeout(buscarSimilaresTimer);
    if (!valor || valor.trim().length < 4) {
        productosSimilares.value = [];
        return;
    }
    const termino = valor.trim();
    buscarSimilaresTimer = setTimeout(async () => {
        try {
            const res = await axios.get(route('productos.buscar-similares'), {
                params: { q: termino }
            });
            productosSimilares.value = res.data.filter(p => p.id !== formulario.id);
        } catch (e) {
            productosSimilares.value = [];
        }
    }, 250);
});

const alSeleccionarImagen = (e) => {
    const archivo = e.target.files[0];
    if (archivo) {
        formulario.imagen = archivo;
        formulario.imagen_url = null;
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
            toast: true, position: 'top-end', icon: 'error',
            title: 'Error al generar el código', showConfirmButton: false, timer: 3000
        });
    }
};

const mostrarEscaner = ref(false);
const buscandoCodigo = ref(false);

function validarEAN13(code) {
    if (!/^\d{13}$/.test(code)) return false;
    let sum = 0;
    for (let i = 0; i < 12; i++) {
        sum += parseInt(code[i]) * (i % 2 === 0 ? 1 : 3);
    }
    return (10 - (sum % 10)) % 10 === parseInt(code[12]);
}

function manejarCodigoEscaneado(codigo) {
    if (codigo.length === 13) {
        const reversed = codigo.split('').reverse().join('');
        if (!validarEAN13(codigo) && validarEAN13(reversed)) {
            codigo = reversed;
        }
    }
    mostrarEscaner.value = false;
    formulario.codigo_barras = codigo;
    buscarCodigo(codigo);
}

async function buscarCodigo(codigo) {
    if (!codigo || codigo.length < 2) return;
    if (buscandoCodigo.value) return;
    buscandoCodigo.value = true;

    Swal.fire({
        toast: true, position: 'top-end', icon: 'info',
        title: '🔎 Buscando información del código...', showConfirmButton: false, timer: 8000,
    });

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 12000);

    try {
        const res = await axios.get(route('productos.buscar-codigo', codigo), {
            signal: controller.signal,
        });
        clearTimeout(timeoutId);

        if (res.data.found) {
            const p = res.data.producto;
            const result = await Swal.fire({
                title: 'Producto ya existe en DB',
                html: `
                    <div class="text-left space-y-1">
                        <p class="text-sm font-bold text-slate-700">${p.nombre}</p>
                        <p class="text-xs text-slate-500">Marca: ${p.marca || '—'} · Categoría: ${p.categoria || '—'}</p>
                        <p class="text-xs text-slate-500">Código: ${p.codigo_barras}</p>
                    </div>
                `,
                icon: 'info',
                confirmButtonText: 'Ir a editar producto',
                confirmButtonColor: '#0284c7',
                showCancelButton: true,
                cancelButtonText: 'Cerrar',
            });
            
            if (result.isConfirmed) {
                window.location.href = `/productos`; 
            }
            return;
        }

        const apiData = res.data.api_data;
        if (apiData && (apiData.nombre || apiData.title)) {
            const confirmar = await Swal.fire({
                title: '¿Cargar este producto?',
                html: `
                    <div class="flex flex-col items-center gap-3">
                        <div class="w-32 h-32 bg-slate-100 rounded-xl overflow-hidden flex items-center justify-center border border-slate-200">
                            ${apiData.imagen
                                ? `<img src="${apiData.imagen}" class="w-full h-full object-cover" onerror="this.style.display='none'">`
                                : '<span class="text-3xl text-slate-300">📦</span>'
                            }
                        </div>
                        <div class="text-left w-full space-y-1">
                            <p class="font-bold text-slate-800 text-base">${apiData.nombre || apiData.title || 'Producto'}</p>
                            ${apiData.marca ? `<p class="text-sm text-slate-500"><span class="font-medium">Marca:</span> ${apiData.marca}</p>` : ''}
                            ${apiData.presentacion ? `<p class="text-sm text-slate-500"><span class="font-medium">Presentación:</span> ${apiData.presentacion}</p>` : ''}
                        </div>
                    </div>
                `,
                icon: null,
                showCancelButton: true,
                confirmButtonText: 'Sí, cargar',
                cancelButtonText: 'No',
                confirmButtonColor: '#059669',
                cancelButtonColor: '#64748b',
                reverseButtons: true,
                customClass: { popup: 'rounded-2xl' },
            });

            if (!confirmar.isConfirmed) return;

            formulario.nombre = apiData.nombre || apiData.title || formulario.nombre;

            if (apiData.marca) {
                const marcaMatch = props.marcas.find(m => m.nombreMarca.toLowerCase().includes(apiData.marca.toLowerCase()));
                if (marcaMatch) formulario.marca_id = marcaMatch.id;
            }

            if (apiData.categoria) {
                const catMatch = props.categorias.find(c => c.nombreCategoria.toLowerCase().includes(apiData.categoria.toLowerCase()));
                if (catMatch) formulario.categoria_id = catMatch.id;
            }

            if (apiData.presentacion) {
                const cant = apiData.presentacion.toLowerCase().replace(/\s/g, '');
                if (cant.includes('kg') || cant.includes('kl') || cant.includes('g')) {
                    formulario.unidad_medida = 'Kg';
                }
            }

            formulario.descripcion = apiData.descripcion || formulario.descripcion;
            
            if (apiData.imagen) {
                imagenPreview.value = apiData.imagen;
                formulario.imagen_url = apiData.imagen;
            }

            const rellenados = [];
            if (formulario.nombre) rellenados.push('Nombre');
            if (formulario.marca_id) rellenados.push('Marca');
            if (formulario.categoria_id) rellenados.push('Categoría');
            if (formulario.descripcion) rellenados.push('Descripción');

            Swal.fire({
                toast: true, position: 'top-end', icon: 'success',
                title: `✅ Autocompletado: ${rellenados.join(', ')}`, showConfirmButton: false, timer: 4000,
            });
        } else {
            Swal.fire({
                toast: true, position: 'top-end', icon: 'warning',
                title: '⚠️ Código cargado. No se encontraron datos online.', showConfirmButton: false, timer: 4000,
            });
        }
    } catch (e) {
        clearTimeout(timeoutId);
        console.error('Error al buscar código:', e);
        const msg = e.code === 'ERR_CANCELED' || e.name === 'AbortError'
            ? '⚠️ La consulta tardó demasiado. Completá los datos manualmente.'
            : 'Error al consultar el código de barras';
        Swal.fire({
            toast: true, position: 'top-end', icon: 'warning',
            title: msg, showConfirmButton: false, timer: 5000,
        });
    } finally {
        buscandoCodigo.value = false;
    }
}

const guardar = () => {
    if (formulario.unidad_medida === 'Kg' && formulario.unidad_peso_visual === 'Gramos') {
        formulario.stock_minimo = formulario.stock_minimo_visual / 1000;
        formulario.stock_inicial = formulario.stock_inicial_visual / 1000;
        formulario.stock_objetivo = formulario.stock_objetivo_visual ? formulario.stock_objetivo_visual / 1000 : '';
    } else {
        formulario.stock_minimo = formulario.stock_minimo_visual;
        formulario.stock_inicial = formulario.stock_inicial_visual;
        formulario.stock_objetivo = formulario.stock_objetivo_visual;
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
        onError: (err) => console.error(err)
    });
};
</script>

<template>
    <div v-if="mostrar" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-2 transition-opacity">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl flex flex-col overflow-hidden max-h-[90vh]">
            
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
            
            <div class="p-4 overflow-y-auto flex-1 bg-white custom-scrollbar">
                <form id="productoForm" @submit.prevent="guardar" class="space-y-4">
                    
                    <div class="space-y-3">
                        <div class="flex items-center gap-2 border-b border-slate-200 pb-1.5">
                            <svg class="w-4 h-4 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Información básica</span>
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3">
                            <div class="col-span-1 sm:col-span-2 lg:col-span-12">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Nombre del Producto *</label>
                                <input v-model="formulario.nombre" type="text" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg px-3 py-2 text-sm focus:bg-white focus:ring-2 focus:ring-sky-500 transition-colors" :class="{'border-rose-500 ring-rose-100': formulario.errors.nombre}" placeholder="Ej: Coca Cola 2.25L Retornable" required>
                                <p v-if="formulario.errors.nombre" class="text-rose-500 text-[10px] mt-0.5 font-medium">{{ formulario.errors.nombre }}</p>

                                <div v-if="productosSimilares.length" class="bg-amber-50 border border-amber-200 rounded-lg p-3 mt-2">
                                    <p v-if="tieneCoincidenciaExacta" class="text-[10px] font-bold text-amber-800 bg-amber-100 border border-amber-300 rounded-md px-2.5 py-1.5 mb-2 flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                        Ya existe un producto con ese nombre.
                                    </p>
                                    <p class="text-[10px] font-bold text-amber-700 uppercase tracking-wider mb-1.5">Productos similares encontrados</p>
                                    <ul class="space-y-1.5">
                                        <li v-for="p in productosSimilares" :key="p.id" class="bg-white/60 border border-amber-100 rounded-md px-2.5 py-1.5">
                                            <p class="text-xs font-bold text-slate-800 leading-tight">{{ p.nombre }}</p>
                                            <div class="flex flex-wrap gap-x-3 gap-y-0.5 mt-0.5">
                                                <span v-if="p.marca_nombre" class="text-[9px] text-slate-500">Marca: {{ p.marca_nombre }}</span>
                                                <span class="text-[9px] text-slate-400 font-mono">{{ p.codigo_barras }}</span>
                                                <span class="text-[9px] text-slate-400">{{ p.unidad_medida }}</span>
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <div class="col-span-1 sm:col-span-1 lg:col-span-6">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Cód. Barras o PLU *</label>
                                <div class="flex gap-2">
                                    <input v-model="formulario.codigo_barras" type="text" minlength="2" maxlength="14" 
                                        @input="formulario.codigo_barras = formulario.codigo_barras.replace(/[^0-9]/g, '')"
                                        @keyup.enter="buscarCodigo(formulario.codigo_barras)"
                                        class="flex-1 bg-slate-50 border border-slate-200 text-slate-800 rounded-lg px-3 py-2 text-sm font-mono focus:bg-white focus:ring-2 focus:ring-sky-500 transition-colors" 
                                        :class="{'border-rose-500': formulario.errors.codigo_barras}" 
                                        placeholder="Ej: 7791237290126" required>
                                    
                                    <button type="button" @click="mostrarEscaner = true" title="Escanear código de barras"
                                        class="bg-emerald-100 text-emerald-700 hover:bg-emerald-200 border border-emerald-200 rounded-lg px-3 flex items-center justify-center transition-colors">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><rect x="2" y="4" width="2" height="16" rx="0.5"/><rect x="5" y="5" width="1" height="14" rx="0.3"/><rect x="7" y="3" width="3" height="18" rx="0.5"/><rect x="11" y="6" width="1" height="12" rx="0.3"/><rect x="13" y="4" width="2" height="16" rx="0.5"/><rect x="16" y="7" width="1" height="10" rx="0.3"/><rect x="18" y="3" width="3" height="18" rx="0.5"/><rect x="22" y="5" width="1" height="14" rx="0.3"/></svg>
                                    </button>
                                    <button type="button" @click="autogenerarPlu" title="Generar PLU"
                                        class="bg-sky-100 text-sky-700 hover:bg-sky-200 border border-sky-200 rounded-lg px-3 flex items-center justify-center transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                                    </button>
                                    <div v-if="buscandoCodigo" class="flex items-center">
                                        <svg class="animate-spin h-5 w-5 text-sky-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                    </div>
                                </div>
                                <p v-if="formulario.errors.codigo_barras" class="text-rose-500 text-[10px] mt-0.5 font-medium">{{ formulario.errors.codigo_barras }}</p>
                            </div>

                            <div class="col-span-1 sm:col-span-1 lg:col-span-6">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Categoría *</label>
                                <select v-model="formulario.categoria_id" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg px-3 py-2 text-sm focus:bg-white focus:ring-2 focus:ring-sky-500 transition-colors" :class="{'border-rose-500': formulario.errors.categoria_id}" required>
                                    <option value="" disabled>Seleccione...</option>
                                    <option v-for="cat in categorias" :key="cat.id" :value="cat.id">{{ cat.nombreCategoria }}</option>
                                </select>
                                <p v-if="formulario.errors.categoria_id" class="text-rose-500 text-[10px] mt-0.5 font-medium">{{ formulario.errors.categoria_id }}</p>
                            </div>

                            <div class="col-span-1 sm:col-span-1 lg:col-span-4">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Marca *</label>
                                <select v-model="formulario.marca_id" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg px-3 py-2 text-sm focus:bg-white focus:ring-2 focus:ring-sky-500 transition-colors" required>
                                    <option value="" disabled>Seleccione...</option>
                                    <option v-for="m in marcas" :key="m.id" :value="m.id">{{ m.nombreMarca }}</option>
                                </select>
                            </div>

                            <div class="col-span-1 sm:col-span-1 lg:col-span-4">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Proveedor *</label>
                                <select v-model="formulario.proveedor_id" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg px-3 py-2 text-sm focus:bg-white focus:ring-2 focus:ring-sky-500 transition-colors" required>
                                    <option value="" disabled>Seleccione...</option>
                                    <option v-for="prov in proveedores" :key="prov.id" :value="prov.id">{{ prov.razon_social }}</option>
                                </select>
                            </div>

                            <div class="col-span-1 sm:col-span-1 lg:col-span-4">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Forma de Venta *</label>
                                <select v-model="formulario.unidad_medida" class="w-full bg-slate-50 border border-slate-200 text-sky-700 font-bold rounded-lg px-3 py-2 text-sm focus:bg-white focus:ring-2 focus:ring-sky-500 transition-colors">
                                    <option value="Unidad">Por Unidad</option>
                                    <option value="Kg">Por Peso (Kg)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center gap-2 border-b border-slate-200 pb-1.5">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Precios</span>
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Precio Costo ($)</label>
                                <input v-model="formulario.precio_costo" type="number" step="0.01" min="0" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm text-rose-700 font-bold focus:bg-white focus:ring-2 focus:ring-sky-500 transition-colors" required>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Precio Venta ($)</label>
                                <input v-model="formulario.precio_venta" type="number" step="0.01" min="0" class="w-full bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2 text-sm text-emerald-800 font-bold focus:bg-white focus:ring-2 focus:ring-sky-500 transition-colors" required>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center gap-2 border-b border-slate-200 pb-1.5">
                            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Stock</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Stock Mínimo</label>
                                <div class="flex items-center border border-slate-200 rounded-lg overflow-hidden focus-within:ring-2 focus-within:ring-sky-500 bg-slate-50 transition-all">
                                    <input v-model="formulario.stock_minimo_visual" type="number" :step="formulario.unidad_medida === 'Unidad' ? '1' : '0.001'" min="0" class="w-full border-none bg-transparent px-3 py-2 text-sm focus:ring-0 text-slate-800">
                                    <select v-if="formulario.unidad_medida === 'Kg'" v-model="formulario.unidad_peso_visual" class="border-y-0 border-r-0 border-l border-slate-200 bg-slate-100 px-2 py-2 focus:ring-0 text-xs font-bold text-sky-700">
                                        <option value="Kg">Kg</option>
                                        <option value="Gramos">Gr</option>
                                    </select>
                                    <span v-else class="border-l border-slate-200 bg-slate-100 px-3 py-2 text-xs font-bold text-slate-500 uppercase">Ud</span>
                                </div>
                            </div>

                            <div v-if="!formulario.id" class="sm:col-span-2">
                                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-2 p-2.5 bg-amber-50 border border-amber-100 rounded-lg">
                                    <div class="flex-1">
                                        <span class="block text-xs font-bold text-amber-800">Stock Físico Inicial</span>
                                        <span class="block text-[10px] text-amber-600">Cantidad actual en estantería.</span>
                                    </div>
                                    <div class="w-full sm:w-48 flex items-center border border-amber-200 rounded-lg overflow-hidden bg-white shadow-sm focus-within:ring-2 focus-within:ring-amber-500 transition-all">
                                        <input v-model="formulario.stock_inicial_visual" type="number" :step="formulario.unidad_medida === 'Unidad' ? '1' : '0.001'" min="0" class="w-full border-none bg-transparent px-3 py-1.5 text-sm focus:ring-0 text-slate-800 font-bold">
                                        <span class="bg-amber-100 px-3 py-1.5 text-[10px] font-black text-amber-700 uppercase">
                                            {{ sufijoStock }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center gap-2 border-b border-slate-200 pb-1.5">
                            <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Reposición / Stock Objetivo</span>
                        </div>

                        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div class="sm:col-span-1">
                                    <label class="block text-[10px] font-bold text-amber-700 uppercase tracking-wider mb-1">Stock Objetivo</label>
                                    <div class="flex items-center border border-amber-300 rounded-lg overflow-hidden focus-within:ring-2 focus-within:ring-amber-500 bg-white transition-all">
                                        <input v-model="formulario.stock_objetivo_visual" type="number" :step="formulario.unidad_medida === 'Unidad' ? '1' : '0.001'" min="0" placeholder="Opcional" class="w-full border-none bg-transparent px-3 py-2 text-sm focus:ring-0 text-slate-800">
                                        <select v-if="formulario.unidad_medida === 'Kg'" v-model="formulario.unidad_peso_visual" class="border-y-0 border-r-0 border-l border-amber-200 bg-amber-100 px-2 py-2 focus:ring-0 text-xs font-bold text-amber-700">
                                            <option value="Kg">Kg</option>
                                            <option value="Gramos">Gr</option>
                                        </select>
                                        <span v-else class="border-l border-amber-200 bg-amber-100 px-3 py-2 text-xs font-bold text-amber-700 uppercase">Ud</span>
                                    </div>
                                </div>

                                <div class="sm:col-span-2 flex items-end">
                                    <div v-if="equivalenciaBultos" class="flex items-center gap-2 text-sm">
                                        <svg class="w-4 h-4 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                        <span class="text-amber-800 font-medium">
                                            ≈ {{ equivalenciaBultos.bultos }} {{ equivalenciaBultos.unidad }}
                                            <span class="text-amber-600 text-xs">({{ equivalenciaBultos.porBulto }} unidades por {{ formulario.unidad_compra === 'Otro' ? 'unidad' : formulario.unidad_compra?.toLowerCase() }})</span>
                                        </span>
                                    </div>
                                    <p v-else class="text-[10px] text-amber-600">Completá la sección "Presentación de Compra" para ver la equivalencia.</p>
                                </div>
                            </div>

                            <p class="text-[10px] text-amber-600 mt-2">Cantidad ideal a mantener en stock. Si vacío se usa fórmula actual.</p>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center gap-2 border-b border-slate-200 pb-1.5">
                            <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Presentación de Compra</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
                            <div class="col-span-1 sm:col-span-4">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Unidad de Compra</label>
                                <select v-model="formulario.unidad_compra" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg px-3 py-2 text-sm focus:bg-white focus:ring-2 focus:ring-sky-500 transition-colors">
                                    <option :value="null">Sin configurar</option>
                                    <option v-for="opcion in OPCIONES_UNIDAD_COMPRA" :key="opcion" :value="opcion">{{ opcion }}</option>
                                </select>
                            </div>

                            <div v-if="formulario.unidad_compra === 'Otro'" class="col-span-1 sm:col-span-4">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Especifique</label>
                                <input v-model="formulario.unidad_compra" type="text" placeholder="Ej: Tarro, Botella..." class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg px-3 py-2 text-sm focus:bg-white focus:ring-2 focus:ring-sky-500 transition-colors">
                            </div>

                            <div class="col-span-1 sm:col-span-4">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Cantidad por Compra</label>
                                <input v-model="formulario.cantidad_por_compra" type="number" step="0.01" min="1" placeholder="Ej: 12" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg px-3 py-2 text-sm focus:bg-white focus:ring-2 focus:ring-sky-500 transition-colors" :class="{'border-rose-500': formulario.errors.cantidad_por_compra}">
                                <p v-if="formulario.errors.cantidad_por_compra" class="text-rose-500 text-[10px] mt-0.5 font-medium">{{ formulario.errors.cantidad_por_compra }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="flex items-center p-2.5 border border-slate-200 rounded-lg cursor-pointer hover:bg-slate-50 transition-colors" :class="{'bg-sky-50 border-sky-200': formulario.es_retornable}">
                                <input type="checkbox" v-model="formulario.es_retornable" class="w-4 h-4 text-sky-600 rounded border-slate-300">
                                <span class="ml-3 text-xs font-bold text-slate-700">Envase Retornable</span>
                            </label>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Descripción / Notas</label>
                            <textarea v-model="formulario.descripcion" rows="2" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg px-3 py-1.5 text-sm focus:bg-white focus:ring-2 focus:ring-sky-500 transition-colors resize-none" placeholder="Anotaciones internas..."></textarea>
                        </div>

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

                </form>
            </div>

            <div class="bg-slate-50 border-t border-slate-200 px-4 py-3 flex items-center justify-end gap-3 shrink-0">
                <button type="button" @click="$emit('cerrar')" class="px-5 py-2 rounded-lg text-sm font-bold text-slate-600 hover:bg-slate-200 transition-colors uppercase tracking-wider">Cancelar</button>
                <button type="submit" form="productoForm" class="bg-sky-600 text-white px-6 py-2 rounded-lg text-sm font-bold hover:bg-sky-700 shadow-lg shadow-sky-600/20 transition-all uppercase tracking-wider flex items-center gap-2" :disabled="formulario.processing">
                    <svg v-if="formulario.processing" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    {{ formulario.processing ? 'Guardando...' : 'Guardar Producto' }}
                </button>
            </div>

        </div>

        <LectorCamara 
            v-if="mostrarEscaner" 
            @escaneado="manejarCodigoEscaneado" 
            @cerrar="mostrarEscaner = false" 
        />
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 20px; }
</style>
