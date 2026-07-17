<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="$emit('cerrar')">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-slate-100 flex items-center justify-center">
                            <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        </div>
                        <h3 class="text-lg font-bold text-slate-800">Imprimir etiquetas</h3>
                    </div>
                    <button @click="$emit('cerrar')" class="text-slate-400 hover:text-slate-600 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="p-6 space-y-5">

                    <!-- Modo de selección -->
                    <div>
                        <label class="block text-[10px] font-semibold text-slate-400 uppercase tracking-widest mb-3">¿Qué productos?</label>
                        <div class="space-y-2">
                            <label class="flex items-center gap-3 px-4 py-3 rounded-xl border transition-colors cursor-pointer"
                                :class="modo === 'todos' ? 'border-blue-300 bg-blue-50' : 'border-slate-200 hover:bg-slate-50'">
                                <input type="radio" v-model="modo" value="todos" class="w-4 h-4 text-blue-600 focus:ring-blue-500" />
                                <span class="text-sm font-medium text-slate-700">Todos los productos</span>
                                <span class="ml-auto text-xs text-slate-400 font-medium">{{ totalProductos }} productos</span>
                            </label>

                            <label class="flex items-center gap-3 px-4 py-3 rounded-xl border transition-colors cursor-pointer"
                                :class="modo === 'categoria' ? 'border-blue-300 bg-blue-50' : 'border-slate-200 hover:bg-slate-50'">
                                <input type="radio" v-model="modo" value="categoria" class="w-4 h-4 text-blue-600 focus:ring-blue-500" />
                                <span class="text-sm font-medium text-slate-700">Por categoría</span>
                            </label>

                            <label class="flex items-center gap-3 px-4 py-3 rounded-xl border transition-colors cursor-pointer"
                                :class="modo === 'marca' ? 'border-blue-300 bg-blue-50' : 'border-slate-200 hover:bg-slate-50'">
                                <input type="radio" v-model="modo" value="marca" class="w-4 h-4 text-blue-600 focus:ring-blue-500" />
                                <span class="text-sm font-medium text-slate-700">Por marca</span>
                            </label>

                            <label class="flex items-center gap-3 px-4 py-3 rounded-xl border transition-colors cursor-pointer"
                                :class="modo === 'busqueda' ? 'border-blue-300 bg-blue-50' : 'border-slate-200 hover:bg-slate-50'">
                                <input type="radio" v-model="modo" value="busqueda" class="w-4 h-4 text-blue-600 focus:ring-blue-500" />
                                <span class="text-sm font-medium text-slate-700">Buscar productos</span>
                            </label>
                        </div>
                    </div>

                    <!-- Panel de categoría -->
                    <div v-if="modo === 'categoria'" class="space-y-3">
                        <select v-model="categoriaId"
                            class="w-full rounded-xl border-slate-200 bg-slate-50 py-2.5 text-sm font-medium text-slate-700 focus:ring-blue-500">
                            <option value="">Seleccionar categoría...</option>
                            <option v-for="cat in categorias" :key="cat.id" :value="cat.id">{{ cat.nombreCategoria }}</option>
                        </select>
                        <div v-if="categoriaId" class="text-xs text-slate-500 font-medium">
                            Cantidad encontrada: <span class="text-slate-700 font-bold">{{ productosCategoria.length }}</span> productos
                        </div>
                    </div>

                    <!-- Panel de marca -->
                    <div v-if="modo === 'marca'" class="space-y-3">
                        <select v-model="marcaId"
                            class="w-full rounded-xl border-slate-200 bg-slate-50 py-2.5 text-sm font-medium text-slate-700 focus:ring-blue-500">
                            <option value="">Seleccionar marca...</option>
                            <option v-for="m in marcas" :key="m.id" :value="m.id">{{ m.nombreMarca }}</option>
                        </select>
                        <div v-if="marcaId" class="text-xs text-slate-500 font-medium">
                            Cantidad encontrada: <span class="text-slate-700 font-bold">{{ productosMarca.length }}</span> productos
                        </div>
                    </div>

                    <!-- Panel de búsqueda -->
                    <div v-if="modo === 'busqueda'" class="space-y-3">
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <input v-model="busquedaTexto" type="text" placeholder="Escribí para buscar..."
                                class="w-full pl-9 pr-3 py-2.5 text-sm border border-slate-200 rounded-xl bg-slate-50 text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
                        </div>
                        <div v-if="busquedaTexto.length >= 2" class="max-h-48 overflow-y-auto border border-slate-100 rounded-xl divide-y divide-slate-50">
                            <div v-if="productosBusqueda.length === 0" class="px-4 py-6 text-center text-sm text-slate-400">
                                No se encontraron productos
                            </div>
                            <label v-for="p in productosBusqueda" :key="p.id"
                                class="flex items-center gap-3 px-4 py-2.5 hover:bg-slate-50 cursor-pointer transition-colors">
                                <input type="checkbox" :value="p.id" v-model="busquedaIds"
                                    class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                                <span class="text-sm text-slate-700">{{ p.nombre }}</span>
                                <span class="ml-auto text-xs text-slate-400 font-medium">${{ Number(p.precio_venta).toLocaleString('es-AR') }}</span>
                            </label>
                        </div>
                        <div v-if="busquedaIds.length > 0" class="text-xs text-slate-500 font-medium">
                            <span class="text-slate-700 font-bold">{{ busquedaIds.length }}</span> productos seleccionados
                        </div>
                    </div>

                    <!-- Copias -->
                    <div class="border-t border-slate-100 pt-5">
                        <label class="block text-[10px] font-semibold text-slate-400 uppercase tracking-widest mb-3">Copias por etiqueta</label>
                        <div class="flex items-center gap-2">
                            <label v-for="n in [1, 2, 3]" :key="n"
                                class="flex-1 flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl border transition-colors cursor-pointer text-center"
                                :class="copias === n && !copiasCustom ? 'border-blue-300 bg-blue-50 text-blue-700' : 'border-slate-200 hover:bg-slate-50 text-slate-600'">
                                <input type="radio" :value="n" v-model.number="copias" :checked="!copiasCustom && copias === n" @click="copiasCustom = false" class="hidden" />
                                <span class="text-sm font-semibold">{{ n }}</span>
                            </label>
                            <div class="flex items-center gap-2 flex-1"
                                :class="copiasCustom ? 'border-blue-300 bg-blue-50 rounded-xl px-3 py-2 border' : ''">
                                <label class="flex items-center gap-2 cursor-pointer whitespace-nowrap">
                                    <input type="radio" :checked="copiasCustom" @click="copiasCustom = true" class="w-4 h-4 text-blue-600 focus:ring-blue-500" />
                                    <span class="text-xs font-medium" :class="copiasCustom ? 'text-blue-700' : 'text-slate-500'">Otro</span>
                                </label>
                                <input v-if="copiasCustom" v-model.number="copiasCustomValor" type="number" min="1" max="50"
                                    class="w-16 px-2 py-1.5 text-sm text-center border border-slate-200 rounded-lg bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none" />
                            </div>
                        </div>
                    </div>

                </div>

                <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-between">
                    <div class="text-xs text-slate-400 font-medium" v-if="totalEtiquetas > 0">
                        Total: <span class="text-slate-700 font-bold">{{ totalEtiquetas }}</span> etiquetas
                    </div>
                    <div v-else></div>
                    <div class="flex items-center gap-3">
                        <button @click="$emit('cerrar')" class="px-4 py-2.5 text-sm font-medium text-slate-600 hover:text-slate-800 transition-colors">
                            Cancelar
                        </button>
                        <button @click="imprimir" :disabled="!puedeImprimir"
                            class="inline-flex items-center gap-2 bg-slate-800 hover:bg-slate-700 disabled:opacity-40 disabled:cursor-not-allowed text-white px-5 py-2.5 rounded-xl font-medium text-sm shadow-sm transition-all active:scale-95">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            Imprimir
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    categorias: Array,
    marcas: Array,
    productos: Array,
});

const emit = defineEmits(['cerrar']);

const modo = ref('todos');
const categoriaId = ref('');
const marcaId = ref('');
const busquedaTexto = ref('');
const busquedaIds = ref([]);
const busquedaResultados = ref([]);
const copias = ref(1);
const copiasCustom = ref(false);
const copiasCustomValor = ref(2);

const totalProductos = computed(() => props.productos.length);

const productosCategoria = computed(() => {
    if (!categoriaId.value) return [];
    return props.productos.filter(p => p.categoria?.id == categoriaId.value);
});

const productosMarca = computed(() => {
    if (!marcaId.value) return [];
    return props.productos.filter(p => p.marca?.id == marcaId.value);
});

let debounceTimer = null;
watch(busquedaTexto, (val) => {
    clearTimeout(debounceTimer);
    busquedaResultados.value = [];
    busquedaIds.value = [];
    if (val.length < 2) return;
    debounceTimer = setTimeout(() => {
        busquedaResultados.value = props.productos.filter(p =>
            p.nombre.toLowerCase().includes(val.toLowerCase()) ||
            p.codigo_barras?.toLowerCase().includes(val.toLowerCase())
        ).slice(0, 50);
    }, 300);
});

const productosBusqueda = computed(() => busquedaResultados.value);

const copiasFinales = computed(() => copiasCustom.value ? (copiasCustomValor.value || 1) : copias.value);

const cantidadProductos = computed(() => {
    switch (modo.value) {
        case 'todos': return totalProductos.value;
        case 'categoria': return productosCategoria.value.length;
        case 'marca': return productosMarca.value.length;
        case 'busqueda': return busquedaIds.value.length;
        default: return 0;
    }
});

const totalEtiquetas = computed(() => cantidadProductos.value * copiasFinales.value);

const puedeImprimir = computed(() => {
    if (cantidadProductos.value === 0) return false;
    if (copiasFinales.value < 1) return false;
    if (modo.value === 'categoria' && !categoriaId.value) return false;
    if (modo.value === 'marca' && !marcaId.value) return false;
    if (modo.value === 'busqueda' && busquedaIds.value.length === 0) return false;
    return true;
});

watch(modo, () => {
    categoriaId.value = '';
    marcaId.value = '';
    busquedaTexto.value = '';
    busquedaIds.value = [];
    busquedaResultados.value = [];
});

const imprimir = async () => {
    if (!puedeImprimir.value) return;

    const data = new FormData();
    data.append('modo', modo.value);
    data.append('copias', copiasFinales.value);

    if (modo.value === 'categoria') {
        data.append('categoria_id', categoriaId.value);
    } else if (modo.value === 'marca') {
        data.append('marca_id', marcaId.value);
    } else if (modo.value === 'busqueda') {
        data.append('busqueda', busquedaTexto.value);
    }

    try {
        const response = await axios.post(route('productos.etiquetas'), data, {
            responseType: 'blob',
        });

        const blob = new Blob([response.data], { type: 'application/pdf' });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'etiquetas_' + new Date().toISOString().slice(0, 19).replace(/[T:]/g, '-') + '.pdf';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);

        emit('cerrar');
    } catch (error) {
        console.error('Error al generar etiquetas:', error);
    }
};
</script>
