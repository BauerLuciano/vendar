<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    modelValue: Array,
    categories: Array,
    brands: Array,
    initialProducts: Array,
});

const emit = defineEmits(['update:modelValue']);

const query = ref('');
const results = ref([]);
const selected = ref([]);
const loading = ref(false);
const page = ref(1);
const hasMore = ref(true);
const initialized = ref(false);
let debounce = null;

watch(() => props.modelValue, (val) => {
    if (!val || initialized.value) return;
    if (props.initialProducts?.length) {
        selected.value = props.initialProducts.map(p => ({
            id: p.id,
            nombre: p.nombre,
            codigo_barras: p.codigo_barras,
        }));
    } else {
        selected.value = val.map(id => ({ id }));
    }
    initialized.value = true;
}, { immediate: true });

const buscar = () => {
    clearTimeout(debounce);
    debounce = setTimeout(async () => {
        if (!query.value) {
            results.value = [];
            return;
        }
        loading.value = true;
        page.value = 1;
        try {
            const res = await axios.get(route('promotions.search-products'), {
                params: { search: query.value, page: page.value },
            });
            results.value = res.data.data ?? [];
            hasMore.value = res.data.next_page_url !== null;
        } catch {
            results.value = [];
        } finally {
            loading.value = false;
        }
    }, 400);
};

const cargarMas = async () => {
    if (!hasMore.value || loading.value) return;
    loading.value = true;
    page.value++;
    try {
        const res = await axios.get(route('promotions.search-products'), {
            params: { search: query.value, page: page.value },
        });
        results.value = [...results.value, ...(res.data.data ?? [])];
        hasMore.value = res.data.next_page_url !== null;
    } catch {
        page.value--;
    } finally {
        loading.value = false;
    }
};

const toggleProducto = (producto) => {
    const idx = selected.value.findIndex(p => p.id === producto.id);
    if (idx >= 0) {
        selected.value.splice(idx, 1);
    } else {
        selected.value.push({ id: producto.id, nombre: producto.nombre, codigo_barras: producto.codigo_barras });
    }
    emit('update:modelValue', selected.value.map(p => p.id));
};

const estaSeleccionado = (id) => selected.value.some(p => p.id === id);

const eliminarSeleccionado = (id) => {
    const idx = selected.value.findIndex(p => p.id === id);
    if (idx >= 0) {
        selected.value.splice(idx, 1);
        emit('update:modelValue', selected.value.map(p => p.id));
    }
};

const formatPrice = (val) => {
    if (val === null || val === undefined) return '-';
    return '$' + Number(val).toLocaleString('es-AR', { minimumFractionDigits: 2 });
};
</script>

<template>
    <div>
        <!-- Selected chips -->
        <div v-if="selected.length > 0" class="flex flex-wrap gap-2 mb-4">
            <span v-for="p in selected" :key="p.id"
                class="inline-flex items-center gap-1 px-3 py-1.5 bg-sky-50 text-sky-700 rounded-xl text-xs font-bold border border-sky-200">
                {{ p.nombre ?? p.codigo_barras ?? p.id }}
                <button @click="eliminarSeleccionado(p.id)" class="text-sky-400 hover:text-rose-500 ml-1 font-black">&times;</button>
            </span>
            <span class="text-xs font-bold text-slate-400 self-center">{{ selected.length }} producto(s)</span>
        </div>

        <!-- Search -->
        <div class="relative">
            <svg class="absolute left-3 top-3 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input v-model="query" @input="buscar" type="text"
                class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-4 py-2.5 focus:ring-sky-500 focus:border-sky-500 transition-all text-sm font-bold text-slate-700"
                placeholder="Buscar producto por nombre o código de barras...">
        </div>

        <!-- Results -->
        <div v-if="loading && results.length === 0" class="text-center py-4 text-slate-400 text-sm">
            Buscando...
        </div>

        <div v-else-if="results.length > 0" class="mt-3 max-h-48 overflow-y-auto space-y-1">
            <div v-for="p in results" :key="p.id"
                @click="toggleProducto(p)"
                class="flex items-center justify-between px-3 py-2 rounded-lg cursor-pointer transition-all text-sm"
                :class="estaSeleccionado(p.id) ? 'bg-sky-50 border border-sky-200' : 'hover:bg-slate-50 border border-transparent'">
                <div class="flex items-center gap-3 min-w-0">
                    <div v-if="p.imagen_url" class="w-8 h-8 rounded-lg overflow-hidden flex-shrink-0 bg-slate-100">
                        <img :src="p.imagen_url" :alt="p.nombre" class="w-full h-full object-cover" loading="lazy">
                    </div>
                <div class="min-w-0">
                    <div class="font-bold text-slate-700 truncate">{{ p.nombre }}</div>
                    <div class="flex items-center gap-1 text-[10px] text-slate-400 font-bold flex-wrap">
                        <span>{{ p.codigo_barras }}</span>
                        <span v-if="p.categoria?.nombreCategoria" class="text-slate-300">|</span>
                        <span v-if="p.categoria?.nombreCategoria" class="text-slate-500">{{ p.categoria.nombreCategoria }}</span>
                        <span v-if="p.marca?.nombreMarca" class="text-slate-300">|</span>
                        <span v-if="p.marca?.nombreMarca" class="text-slate-500">{{ p.marca.nombreMarca }}</span>
                    </div>
                </div>
                </div>
                <div class="flex items-center gap-3 flex-shrink-0">
                    <span class="font-mono text-sm font-bold text-slate-600">{{ formatPrice(p.precio_venta) }}</span>
                    <div v-if="estaSeleccionado(p.id)"
                        class="w-5 h-5 rounded-full bg-sky-500 flex items-center justify-center">
                        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                    </div>
                    <div v-else class="w-5 h-5 rounded-full border-2 border-slate-300"></div>
                </div>
            </div>

            <button v-if="hasMore" @click="cargarMas"
                class="w-full text-center py-2 text-xs font-bold text-sky-600 hover:text-sky-800"
                :disabled="loading">
                {{ loading ? 'Cargando...' : 'Cargar más resultados' }}
            </button>
        </div>

        <div v-else-if="query && !loading" class="text-center py-4 text-slate-400 text-sm">
            Sin resultados para "{{ query }}"
        </div>
    </div>
</template>
