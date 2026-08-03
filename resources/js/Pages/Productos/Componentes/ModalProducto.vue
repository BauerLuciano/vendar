<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';
import LectorCamara from '@/Components/LectorCamara.vue';
import AyudaTooltip from '@/Components/AyudaTooltip.vue';

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
    categoria_id: null,
    marca_id: null,
    proveedor_id: null,
    unidad_medida: 'Unidad',
    unidad_compra: null,
    unidad_compra_otro: '',
    cantidad_por_compra: null,
    es_retornable: false,
    precio_costo: '',
    precio_venta: 0,
    stock_minimo: '',
    stock_minimo_visual: '',
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

const stockActualVisual = computed(() => {
    const v = parseFloat(formulario.stock_inicial_visual);
    return v > 0 ? v : null;
});

const equivalenciaCompra = computed(() => {
    const objetivo = parseFloat(formulario.stock_objetivo_visual);
    const cantidad = parseFloat(formulario.cantidad_por_compra);
    const unidad = formulario.unidad_compra === 'Otro'
        ? (formulario.unidad_compra_otro?.trim() || null)
        : formulario.unidad_compra;
    if (!objetivo || objetivo <= 0 || !cantidad || cantidad < 1 || !unidad) return null;
    const actual = stockActualVisual.value;
    const aComprar = actual !== null ? Math.max(0, objetivo - actual) : objetivo;
    if (aComprar <= 0) return null;
    const bultos = Math.ceil(aComprar / cantidad);
    return { bultos, unidad: unidad.toLowerCase() + (bultos === 1 ? '' : 's') };
});

const margenEstimado = computed(() => {
    const costo = parseFloat(formulario.precio_costo);
    const venta = parseFloat(formulario.precio_venta);
    if (isNaN(costo) || isNaN(venta) || costo <= 0 || venta <= 0 || venta <= costo) return null;
    return Math.round(((venta - costo) / venta) * 100);
});

const margenPendiente = computed(() => {
    const venta = parseFloat(formulario.precio_venta);
    if (isNaN(venta) || venta <= 0) return false;
    const costo = formulario.precio_costo;
    return costo === null || costo === undefined || costo === '';
});

const reposicionCard = computed(() => {
    const minimo = parseFloat(formulario.stock_minimo_visual);
    const objetivo = parseFloat(formulario.stock_objetivo_visual);
    const actual = stockActualVisual.value;
    let estado = 'sin-datos';
    if (minimo > 0) estado = 'sin-objetivo';
    if (minimo > 0 && objetivo > 0) estado = 'ok';
    let cantidad = null;
    if (estado === 'ok' && actual !== null) {
        cantidad = objetivo - actual;
    }
    return {
        minimo: minimo > 0 ? minimo : null,
        objetivo: objetivo > 0 ? objetivo : null,
        actual,
        estado,
        cantidad,
    };
});

const productosSimilares = ref([]);
let buscarSimilaresTimer = null;

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
        const unidadCompraGuardada = nuevoValor.unidad_compra;
        if (unidadCompraGuardada && OPCIONES_UNIDAD_COMPRA.includes(unidadCompraGuardada)) {
            formulario.unidad_compra = unidadCompraGuardada;
            formulario.unidad_compra_otro = '';
        } else if (unidadCompraGuardada) {
            formulario.unidad_compra = 'Otro';
            formulario.unidad_compra_otro = unidadCompraGuardada;
        } else {
            formulario.unidad_compra = null;
            formulario.unidad_compra_otro = '';
        }
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

const tieneCoincidenciaExacta = computed(() => {
    if (!productosSimilares.value.length) return false;
    const termino = formulario.nombre?.trim().toLowerCase();
    if (!termino) return false;
    return productosSimilares.value.some(p => p.nombre.toLowerCase().trim() === termino);
});

const precioVentaInvalido = computed(() => {
    const costo = parseFloat(formulario.precio_costo);
    const venta = parseFloat(formulario.precio_venta);
    if (isNaN(costo) || isNaN(venta) || costo <= 0) return false;
    return venta <= costo;
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

    try {
        Swal.fire({
            toast: true, position: 'top-end', icon: 'info',
            title: '🔎 Buscando información del código...', showConfirmButton: false, timer: 8000,
        });

        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 12000);

        let res;
        try {
            res = await axios.get(route('productos.buscar-codigo', codigo), {
                signal: controller.signal,
            });
            clearTimeout(timeoutId);
        } catch (e) {
            clearTimeout(timeoutId);
            const msg = e.code === 'ERR_CANCELED' || e.name === 'AbortError'
                ? '⚠️ La consulta tardó demasiado. Completá los datos manualmente.'
                : 'Error al consultar el código de barras';
            Swal.fire({
                toast: true, position: 'top-end', icon: 'warning',
                title: msg, showConfirmButton: false, timer: 5000,
            });
            return;
        }

        if (res.data.found) {
            const p = res.data.global_product;
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
        console.error('Error al buscar código:', e);
        Swal.fire({
            toast: true, position: 'top-end', icon: 'warning',
            title: 'Error al consultar el código de barras', showConfirmButton: false, timer: 5000,
        });
    } finally {
        buscandoCodigo.value = false;
    }
}

const guardar = () => {
    formulario.nombre = formulario.nombre.trim();
    formulario.descripcion = formulario.descripcion?.trim() || '';

    const unidadCompraOriginal = formulario.unidad_compra;
    if (formulario.unidad_compra === 'Otro') {
        formulario.unidad_compra = formulario.unidad_compra_otro?.trim() || null;
    }

    if (formulario.unidad_medida === 'Kg' && formulario.unidad_peso_visual === 'Gramos') {
        formulario.stock_minimo = formulario.stock_minimo_visual !== '' && formulario.stock_minimo_visual !== null
            ? formulario.stock_minimo_visual / 1000
            : '';
        formulario.stock_inicial = formulario.stock_inicial_visual !== '' && formulario.stock_inicial_visual !== null
            ? formulario.stock_inicial_visual / 1000
            : 0;
        formulario.stock_objetivo = formulario.stock_objetivo_visual ? formulario.stock_objetivo_visual / 1000 : '';
    } else {
        formulario.stock_minimo = formulario.stock_minimo_visual;
        formulario.stock_inicial = formulario.stock_inicial_visual;
        formulario.stock_objetivo = formulario.stock_objetivo_visual;
    }

    const costo = parseFloat(formulario.precio_costo);
    const venta = parseFloat(formulario.precio_venta);
    if (!isNaN(costo) && !isNaN(venta) && costo >= venta) {
        Swal.fire({
            title: 'Precio inválido',
            text: 'El precio de venta debe ser mayor al precio de costo.',
            icon: 'warning',
            confirmButtonColor: '#0284c7'
        });
        return;
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
            formulario.unidad_compra = unidadCompraOriginal;
            console.error(err);
        }
    });
};
</script>

<template>
    <div v-if="mostrar" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-2 transition-opacity">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl flex flex-col overflow-hidden max-h-[92vh]">

            <div class="bg-slate-50 border-b border-slate-200 px-5 py-2.5 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-full bg-sky-100 flex items-center justify-center text-sky-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    </div>
                    <h3 class="text-base font-bold text-slate-800">
                        {{ formulario.id ? 'Editar Producto' : 'Nuevo Producto' }}
                    </h3>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-[10px] text-slate-400"><span class="text-rose-500 font-bold">*</span> Campo obligatorio</span>
                    <button @click="$emit('cerrar')" class="text-slate-400 hover:text-slate-600 hover:bg-slate-200 p-1.5 rounded-full transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            <div class="p-4 overflow-y-auto flex-1 bg-white custom-scrollbar">
                <form id="productoForm" @submit.prevent="guardar" class="space-y-3">

                    <div class="flex items-center gap-1.5 mb-1">
                        <svg class="w-3.5 h-3.5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Identificación</span>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">
                        <div class="lg:col-span-8 space-y-2.5">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-0.5">Nombre <span class="text-rose-500">*</span></label>
                                <input v-model="formulario.nombre" type="text" maxlength="255" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg px-3 py-1.5 text-sm focus:bg-white focus:ring-2 focus:ring-sky-500 transition-colors" :class="{'border-rose-500 ring-rose-100': formulario.errors.nombre}" placeholder="Ej: Coca Cola 2.25L Retornable" required>
                                <p v-if="formulario.errors.nombre" class="text-rose-500 text-[10px] mt-0.5 font-medium">{{ formulario.errors.nombre }}</p>

                                <div v-if="productosSimilares.length" class="bg-amber-50 border border-amber-200 rounded-lg p-2.5 mt-1.5">
                                    <p v-if="tieneCoincidenciaExacta" class="text-[10px] font-bold text-amber-800 bg-amber-100 border border-amber-300 rounded px-2 py-1 mb-1.5 flex items-center gap-1">
                                        <svg class="w-3 h-3 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                        Ya existe un producto con ese nombre.
                                    </p>
                                    <p class="text-[10px] font-bold text-amber-700 uppercase tracking-wider mb-1">Productos similares</p>
                                    <ul class="space-y-1">
                                        <li v-for="p in productosSimilares" :key="p.id" class="bg-white/60 border border-amber-100 rounded px-2 py-1">
                                            <p class="text-xs font-bold text-slate-800 leading-tight">{{ p.nombre }}</p>
                                            <div class="flex flex-wrap gap-x-2 gap-y-0 mt-0.5">
                                                <span v-if="p.marca_nombre" class="text-[9px] text-slate-500">{{ p.marca_nombre }}</span>
                                                <span class="text-[9px] text-slate-400 font-mono">{{ p.codigo_barras }}</span>
                                                <span class="text-[9px] text-slate-400">{{ p.unidad_medida }}</span>
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-4 gap-2.5">
                                <div class="sm:col-span-2">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-0.5">Cód. Barras / PLU</label>
                                    <div class="flex gap-1.5">
                                        <input v-model="formulario.codigo_barras" type="text" minlength="2" maxlength="14"
                                            @input="formulario.codigo_barras = formulario.codigo_barras.replace(/[^0-9]/g, '')"
                                            @keyup.enter="buscarCodigo(formulario.codigo_barras)"
                                            class="flex-1 bg-slate-50 border border-slate-200 text-slate-800 rounded-lg px-3 py-1.5 text-sm font-mono focus:bg-white focus:ring-2 focus:ring-sky-500 transition-colors"
                                            :class="{'border-rose-500': formulario.errors.codigo_barras}"
                                            placeholder="Ej: 7791234567890 o 1001">
                                        <button type="button" @click="mostrarEscaner = true" title="Escanear"
                                            class="bg-emerald-100 text-emerald-700 hover:bg-emerald-200 border border-emerald-200 rounded-lg px-2 flex items-center justify-center transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><rect x="2" y="4" width="2" height="16" rx="0.5"/><rect x="5" y="5" width="1" height="14" rx="0.3"/><rect x="7" y="3" width="3" height="18" rx="0.5"/><rect x="11" y="6" width="1" height="12" rx="0.3"/><rect x="13" y="4" width="2" height="16" rx="0.5"/><rect x="16" y="7" width="1" height="10" rx="0.3"/><rect x="18" y="3" width="3" height="18" rx="0.5"/><rect x="22" y="5" width="1" height="14" rx="0.3"/></svg>
                                        </button>
                                        <button type="button" @click="autogenerarPlu" title="Generar PLU"
                                            class="bg-sky-100 text-sky-700 hover:bg-sky-200 border border-sky-200 rounded-lg px-2 flex items-center justify-center transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                                        </button>
                                        <div v-if="buscandoCodigo" class="flex items-center px-1">
                                            <svg class="animate-spin h-4 w-4 text-sky-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                        </div>
                                    </div>
                                    <p v-if="formulario.errors.codigo_barras" class="text-rose-500 text-[10px] mt-0.5 font-medium">{{ formulario.errors.codigo_barras }}</p>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-0.5">Categoría</label>
                                    <select v-model="formulario.categoria_id" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg px-3 py-1.5 text-sm focus:bg-white focus:ring-2 focus:ring-sky-500 transition-colors" :class="{'border-rose-500': formulario.errors.categoria_id}">
                                        <option :value="null">Ninguna</option>
                                        <option v-for="cat in categorias" :key="cat.id" :value="cat.id">{{ cat.nombreCategoria }}</option>
                                    </select>
                                    <p v-if="formulario.errors.categoria_id" class="text-rose-500 text-[10px] mt-0.5 font-medium">{{ formulario.errors.categoria_id }}</p>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-0.5">Marca</label>
                                    <select v-model="formulario.marca_id" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg px-3 py-1.5 text-sm focus:bg-white focus:ring-2 focus:ring-sky-500 transition-colors">
                                        <option :value="null">Ninguna</option>
                                        <option v-for="m in marcas" :key="m.id" :value="m.id">{{ m.nombreMarca }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="lg:col-span-4 flex flex-col items-center">
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1 self-start">Foto</label>
                            <div class="w-full max-w-[140px] aspect-square border-2 border-dashed border-slate-300 rounded-xl bg-slate-50 hover:bg-slate-100 transition-colors flex items-center justify-center relative overflow-hidden cursor-pointer" @click="$refs.fileInput.click()">
                                <img v-if="imagenPreview" :src="imagenPreview" class="absolute inset-0 w-full h-full object-cover z-10">
                                <template v-else>
                                    <svg class="h-8 w-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </template>
                                <input ref="fileInput" type="file" @input="alSeleccionarImagen" class="hidden" accept="image/*">
                            </div>
                            <p v-if="formulario.errors.imagen" class="text-rose-500 text-[10px] mt-1 font-medium text-center">{{ formulario.errors.imagen }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5 mb-1.5 pt-1 border-t border-slate-100">
                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Venta</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-0.5">Se vende por <span class="text-rose-500">*</span></label>
                            <select v-model="formulario.unidad_medida" class="w-full bg-slate-50 border border-slate-200 text-sky-700 font-bold rounded-lg px-3 py-1.5 text-sm focus:bg-white focus:ring-2 focus:ring-sky-500 transition-colors">
                                <option value="Unidad">Unidad</option>
                                <option value="Kg">Peso (Kg)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-0.5">Precio de costo</label>
                            <input v-model="formulario.precio_costo" type="number" step="0.01" min="0"
                                class="w-full bg-slate-50 border rounded-lg px-3 py-1.5 text-sm font-bold focus:bg-white focus:ring-2 focus:ring-sky-500 transition-colors"
                                :class="precioVentaInvalido ? 'border-rose-300 text-rose-700' : 'border-slate-200 text-slate-800'"
                                placeholder="Opcional">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-0.5">Precio de venta <span class="text-rose-500">*</span></label>
                            <input v-model="formulario.precio_venta" type="number" step="0.01" min="0.01"
                                class="w-full bg-emerald-50 border rounded-lg px-3 py-1.5 text-sm font-bold focus:bg-white focus:ring-2 focus:ring-sky-500 transition-colors"
                                :class="precioVentaInvalido ? 'border-rose-300 text-rose-700' : 'border-emerald-200 text-emerald-800'"
                                required>
                            <p v-if="precioVentaInvalido" class="text-rose-500 text-[10px] mt-0.5 font-medium">
                                Debe ser mayor a ${{ Number(formulario.precio_costo).toLocaleString('es-AR') }}
                            </p>
                        </div>
                    </div>

                    <div v-if="margenEstimado !== null" class="mt-2 flex items-center justify-between bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2">
                        <span class="text-[10px] font-bold text-emerald-700 uppercase tracking-wider">Margen estimado</span>
                        <span class="text-sm font-black text-emerald-800">{{ margenEstimado }}%</span>
                    </div>
                    <div v-else-if="margenPendiente" class="mt-2 flex items-center gap-1.5 bg-slate-50 border border-slate-200 rounded-lg px-3 py-2">
                        <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/></svg>
                        <span class="text-[10px] font-medium text-slate-600">Calcularemos el margen cuando definas el costo.</span>
                    </div>

                    <div v-if="!formulario.id" class="pt-1 border-t border-slate-100">
                        <div class="flex items-center gap-1.5 mb-1.5">
                            <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Stock</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                            <div>
                                <label class="flex items-center gap-1 text-[10px] font-bold text-amber-700 uppercase tracking-wider mb-0.5">
                                    Stock Inicial
                                    <AyudaTooltip posicion="left" texto="Cantidad disponible al momento de crear el producto." />
                                </label>
                                <div class="flex items-center border border-amber-200 rounded-lg overflow-hidden focus-within:ring-2 focus-within:ring-amber-500 bg-amber-50/50 transition-all">
                                    <input v-model="formulario.stock_inicial_visual" type="number" :step="formulario.unidad_medida === 'Unidad' ? '1' : '0.001'" min="0" placeholder="0" class="w-full border-none bg-transparent px-3 py-1.5 text-sm focus:ring-0 text-slate-800 font-bold">
                                    <span class="bg-amber-100 px-2 py-1.5 text-[10px] font-black text-amber-700 uppercase">{{ sufijoStock }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pt-1 border-t border-slate-100">
                        <div class="flex items-center gap-1.5 mb-1.5">
                            <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Reposición</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                            <div>
                                <label class="flex items-center gap-1 text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-0.5">
                                    Stock mínimo
                                    <AyudaTooltip posicion="left" texto="Cuando el stock baje de este número, VendAR lo marcará para reponer." />
                                </label>
                                <div class="flex items-center border border-slate-200 rounded-lg overflow-hidden focus-within:ring-2 focus-within:ring-sky-500 bg-slate-50 transition-all">
                                    <input v-model="formulario.stock_minimo_visual" type="number" :step="formulario.unidad_medida === 'Unidad' ? '1' : '0.001'" min="0" class="w-full border-none bg-transparent px-3 py-1.5 text-sm focus:ring-0 text-slate-800">
                                    <select v-if="formulario.unidad_medida === 'Kg'" v-model="formulario.unidad_peso_visual" class="border-y-0 border-r-0 border-l border-slate-200 bg-slate-100 px-1.5 py-1.5 focus:ring-0 text-[10px] font-bold text-sky-700">
                                        <option value="Kg">Kg</option>
                                        <option value="Gramos">Gr</option>
                                    </select>
                                    <span v-else class="border-l border-slate-200 bg-slate-100 px-2 py-1.5 text-[10px] font-bold text-slate-500 uppercase">Ud</span>
                                </div>
                            </div>

                            <div>
                                <label class="flex items-center gap-1 text-[10px] font-bold text-amber-700 uppercase tracking-wider mb-0.5">
                                    Stock objetivo
                                    <AyudaTooltip posicion="left" texto="Sin este valor el producto no participará de la reposición inteligente." />
                                </label>
                                <div class="flex items-center border border-amber-200 rounded-lg overflow-hidden focus-within:ring-2 focus-within:ring-amber-500 bg-amber-50/50 transition-all">
                                    <input v-model="formulario.stock_objetivo_visual" type="number" :step="formulario.unidad_medida === 'Unidad' ? '1' : '0.001'" min="0" placeholder="Opcional" class="w-full border-none bg-transparent px-3 py-1.5 text-sm focus:ring-0 text-slate-800">
                                    <select v-if="formulario.unidad_medida === 'Kg'" v-model="formulario.unidad_peso_visual" class="border-y-0 border-r-0 border-l border-amber-200 bg-amber-100 px-1.5 py-1.5 focus:ring-0 text-[10px] font-bold text-amber-700">
                                        <option value="Kg">Kg</option>
                                        <option value="Gramos">Gr</option>
                                    </select>
                                    <span v-else class="border-l border-amber-200 bg-amber-100 px-2 py-1.5 text-[10px] font-bold text-amber-700 uppercase">Ud</span>
                                </div>
                                <div class="mt-1 flex flex-col gap-1 text-[10px] text-amber-700">
                                    <template v-if="equivalenciaCompra">
                                        <span class="flex items-center gap-1.5">
                                            <svg class="w-3 h-3 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                            <span>≈ {{ equivalenciaCompra.bultos }} {{ equivalenciaCompra.unidad }}</span>
                                        </span>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <Transition mode="out-in"
                                    enter-active-class="transition ease-out duration-200"
                                    enter-from-class="opacity-0 translate-y-1"
                                    enter-to-class="opacity-100 translate-y-0"
                                    leave-active-class="transition ease-in duration-150"
                                    leave-from-class="opacity-100 translate-y-0"
                                    leave-to-class="opacity-0 translate-y-1">
                            <div v-if="reposicionCard.estado !== 'ok'" key="reposicion-simple"
                                 class="mt-3 bg-amber-50/70 border border-amber-200 rounded-xl px-3 py-2">
                                <p class="flex items-start gap-1.5 text-[11px] leading-relaxed text-amber-800">
                                    <svg class="w-3.5 h-3.5 text-amber-600 shrink-0 mt-px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18v-5.25m0 0a6.01 6.01 0 001.5-.189m-1.5.189a6.01 6.01 0 01-1.5-.189m3.75 7.478a12.06 12.06 0 01-4.5 0m3.75 2.383a14.406 14.406 0 01-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 10-7.517 0c.85.493 1.509 1.333 1.509 2.316V18" /></svg>
                                    Definí el stock mínimo y el stock objetivo para que VendAR pueda sugerirte automáticamente cuánto comprar cuando un producto necesite reposición.
                                </p>
                            </div>
                            <div v-else key="reposicion-completa"
                                 class="mt-3 bg-amber-50/70 border border-amber-200 rounded-xl px-3 py-2.5">
                                <p class="text-[10px] font-black text-amber-800 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                                    <svg class="w-3 h-3 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/></svg>
                                    ¿Cómo funciona la reposición?
                                </p>
                                <div class="grid gap-2 grid-cols-1 sm:grid-cols-2" :class="{ 'sm:grid-cols-3': reposicionCard.actual !== null }">
                                    <div class="bg-white/70 border border-amber-100 rounded-lg px-2 py-1.5 text-center">
                                        <p class="text-[9px] font-bold text-amber-600 uppercase tracking-wider">Stock mínimo</p>
                                        <p class="text-sm font-black text-amber-900">{{ reposicionCard.minimo ?? '—' }} <span class="text-[10px] font-bold text-amber-500">{{ sufijoStock }}</span></p>
                                    </div>
                                    <div class="bg-white/70 border border-amber-100 rounded-lg px-2 py-1.5 text-center">
                                        <p class="text-[9px] font-bold text-amber-600 uppercase tracking-wider">Stock objetivo</p>
                                        <p class="text-sm font-black text-amber-900">{{ reposicionCard.objetivo ?? '—' }} <span class="text-[10px] font-bold text-amber-500">{{ sufijoStock }}</span></p>
                                    </div>
                                    <div v-if="reposicionCard.actual !== null" class="bg-white/70 border border-amber-100 rounded-lg px-2 py-1.5 text-center">
                                        <p class="text-[9px] font-bold text-amber-600 uppercase tracking-wider">Stock actual</p>
                                        <p class="text-sm font-black text-amber-900">{{ reposicionCard.actual }} <span class="text-[10px] font-bold text-amber-500">{{ sufijoStock }}</span></p>
                                    </div>
                                </div>
                                <p class="mt-2 text-[10px] text-amber-800 leading-relaxed">
                                    <template v-if="reposicionCard.estado === 'ok' && reposicionCard.cantidad !== null && reposicionCard.cantidad > 0">
                                        Cuando el stock llegue al mínimo, VendAR sugerirá comprar <span class="font-black">{{ reposicionCard.cantidad }} {{ sufijoStock }}</span> para volver al stock objetivo.
                                    </template>
                                    <template v-else-if="reposicionCard.estado === 'ok'">
                                        Cuando el stock llegue al mínimo, VendAR sugerirá reponer hasta alcanzar el stock objetivo.
                                    </template>
                                    <template v-else>
                                        Definí el stock mínimo y el stock objetivo para que VendAR te indique cuánto reponer.
                                    </template>
                                </p>
                            </div>
                        </Transition>
                    </div>

                    <div class="pt-1 border-t border-slate-100">
                        <div class="flex items-center gap-1.5 mb-1.5">
                            <svg class="w-3.5 h-3.5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Compra</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-2.5 items-end">
                            <div class="sm:col-span-4">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-0.5">Proveedor</label>
                                <select v-model="formulario.proveedor_id" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg px-3 py-1.5 text-sm focus:bg-white focus:ring-2 focus:ring-sky-500 transition-colors">
                                    <option :value="null">Ninguno</option>
                                    <option v-for="prov in proveedores" :key="prov.id" :value="prov.id">{{ prov.razon_social }}</option>
                                </select>
                            </div>
                            <div class="sm:col-span-4">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-0.5">Se compra en</label>
                                <select v-model="formulario.unidad_compra" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg px-3 py-1.5 text-sm focus:bg-white focus:ring-2 focus:ring-sky-500 transition-colors" :class="{'border-rose-500': formulario.errors.unidad_compra}">
                                    <option :value="null">Sin configurar</option>
                                    <option v-for="opcion in OPCIONES_UNIDAD_COMPRA" :key="opcion" :value="opcion">{{ opcion }}</option>
                                </select>
                            </div>
                            <div class="sm:col-span-4">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-0.5">Unidades por presentación</label>
                                <input v-model="formulario.cantidad_por_compra" type="number" step="1" min="1" placeholder="Ej: 12" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg px-3 py-1.5 text-sm focus:bg-white focus:ring-2 focus:ring-sky-500 transition-colors" :class="{'border-rose-500': formulario.errors.cantidad_por_compra}">
                                <p v-if="formulario.errors.cantidad_por_compra" class="text-rose-500 text-[10px] mt-0.5 font-medium">{{ formulario.errors.cantidad_por_compra }}</p>
                            </div>
                            <div v-if="formulario.unidad_compra === 'Otro'" class="sm:col-span-4">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-0.5">Otra presentación</label>
                                <input v-model="formulario.unidad_compra_otro" type="text" placeholder="Ej: Tarro, Botella..." class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg px-3 py-1.5 text-sm focus:bg-white focus:ring-2 focus:ring-sky-500 transition-colors">
                            </div>
                            <div class="sm:col-span-12 -mt-1">
                                <p v-if="formulario.errors.unidad_compra" class="text-rose-500 text-[10px] font-medium">{{ formulario.errors.unidad_compra }}</p>
                                <p v-else class="text-[10px] text-slate-400">Si completás uno de estos dos campos, el otro pasa a ser obligatorio.</p>
                            </div>
                            <div class="sm:col-span-12">
                                <label class="flex items-center gap-2 p-2 border border-slate-200 rounded-lg cursor-pointer hover:bg-slate-50 transition-colors" :class="{'bg-sky-50 border-sky-200': formulario.es_retornable}">
                                    <input type="checkbox" v-model="formulario.es_retornable" class="w-3.5 h-3.5 text-sky-600 rounded border-slate-300">
                                    <span class="text-xs font-bold text-slate-700">Envase Retornable</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="pt-1 border-t border-slate-100">
                        <div class="flex items-center gap-1.5 mb-1.5">
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Descripción</span>
                        </div>

                        <textarea v-model="formulario.descripcion" rows="3" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-lg px-3 py-1.5 text-sm focus:bg-white focus:ring-2 focus:ring-sky-500 transition-colors resize-none" placeholder="Anotaciones internas..."></textarea>
                    </div>

                </form>
            </div>

            <div class="bg-slate-50 border-t border-slate-200 px-5 py-2.5 flex items-center justify-end gap-3 shrink-0">
                <button type="button" @click="$emit('cerrar')" class="px-4 py-1.5 rounded-lg text-sm font-bold text-slate-600 hover:bg-slate-200 transition-colors uppercase tracking-wider">Cancelar</button>
                <button type="submit" form="productoForm" class="bg-sky-600 text-white px-5 py-1.5 rounded-lg text-sm font-bold hover:bg-sky-700 shadow-lg shadow-sky-600/20 transition-all uppercase tracking-wider flex items-center gap-2" :disabled="formulario.processing">
                    <svg v-if="formulario.processing" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    {{ formulario.processing ? 'Guardando...' : 'Guardar' }}
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
