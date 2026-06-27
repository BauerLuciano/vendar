<script setup>
import ProductGrid from '@/Components/Tienda/ProductGrid.vue';
import ProductDetailModal from '@/Components/Tienda/ProductDetailModal.vue';

defineProps({
    section: { type: Object, default: () => ({}) },
    productos: Array,
    cargando: Boolean,
    sucursalElegida: [String, Number],
    busqueda: String,
    categoriaSeleccionada: [String, Number],
    categorias: Array,
    totalPaginas: Number,
    paginaActual: Number,
    filtroPromosOnly: Boolean,
    productoSeleccionado: Object,
    mostrarModalDetalle: Boolean,
});

defineEmits([
    'agregar',
    'detail',
    'sort-change',
    'page-change',
    'close-modal',
    'agregar-desde-modal',
]);
</script>

<template>
    <div id="productos-section">
        <ProductGrid
            :productos="productos"
            :cargando="cargando"
            :sucursal-elegida="sucursalElegida"
            :busqueda="busqueda"
            :categoria-seleccionada="categoriaSeleccionada"
            :categorias="categorias"
            :total-paginas="totalPaginas"
            :pagina-actual="paginaActual"
            :filtro-promos-only="filtroPromosOnly"
            @agregar="$emit('agregar', $event)"
            @detail="$emit('detail', $event)"
            @sort-change="$emit('sort-change', $event)"
            @page-change="$emit('page-change', $event)"
        />

        <ProductDetailModal
            :producto="productoSeleccionado"
            :visible="mostrarModalDetalle"
            @close="$emit('close-modal')"
            @agregar="$emit('agregar-desde-modal', $event)"
        />
    </div>
</template>
