<script setup>
import StoreHero from '@/Components/Tienda/StoreHero.vue';
import StoreMap from '@/Components/Tienda/StoreMap.vue';

defineProps({
    section: { type: Object, default: () => ({}) },
    sucursalesBackend: Array,
    sucursalElegida: [String, Number],
    localizando: Boolean,
    comercio: Object,
    distanciaKm: Number,
    mostrarMapa: Boolean,
    geoapifyKey: String,
    tipoEntrega: String,
    coordenadasGps: Object,
});

defineEmits([
    'update:sucursal-elegida',
    'cargar-productos',
    'usar-gps',
    'close-mapa',
    'update:distancia',
    'update:coordenadas',
    'update:direccion',
    'sucursal-seleccionada',
    'scroll-to-promos',
]);
</script>

<template>
    <div>
        <StoreHero
            :sucursales-backend="sucursalesBackend"
            :sucursal-elegida="sucursalElegida"
            :localizando="localizando"
            :comercio="comercio"
            :distancia-km="distanciaKm"
            @update:sucursal-elegida="$emit('update:sucursal-elegida', $event)"
            @cargar-productos="$emit('cargar-productos')"
            @usar-gps="$emit('usar-gps')"
        />

        <StoreMap
            :show="mostrarMapa"
            :sucursales-backend="sucursalesBackend"
            :sucursal-elegida="sucursalElegida"
            :geoapify-key="geoapifyKey"
            :tipo-entrega="tipoEntrega"
            :coordenadas-gps="coordenadasGps"
            @close="$emit('close-mapa')"
            @update:distancia="$emit('update:distancia', $event)"
            @update:coordenadas="$emit('update:coordenadas', $event)"
            @update:direccion="$emit('update:direccion', $event)"
            @sucursal-seleccionada="$emit('sucursal-seleccionada', $event)"
        />

        <div v-if="sucursalElegida" class="max-w-7xl mx-auto w-full px-4 sm:px-6 pt-6">
            <div class="border rounded-3xl p-6 sm:p-8 text-center transition-colors" :style="{ backgroundColor: 'color-mix(in srgb, var(--color-accent) 6%, transparent)', borderColor: 'color-mix(in srgb, var(--color-accent) 15%, transparent)' }">
                <p class="text-2xl sm:text-3xl mb-3">🛒</p>
                <h3 class="text-lg sm:text-xl font-black mb-1.5 transition-colors" :style="{ color: 'var(--text-primary)' }">Comprá online &mdash; Retirá en sucursal</h3>
                <p class="text-sm mb-4 transition-colors" :style="{ color: 'var(--text-muted)' }">Miles de productos disponibles para vos</p>
                <button
                    @click="$emit('scroll-to-promos')"
                    class="font-black text-xs uppercase tracking-widest py-3 px-8 rounded-xl border transition-all duration-200 active:scale-95"
                    style="background: linear-gradient(135deg, #f7941e, #ff6b35); color: #fff; border-color: transparent; box-shadow: 0 4px 16px rgba(247, 148, 30, 0.3);"
                >
                    🏷️ Ver promociones
                </button>
            </div>
        </div>
    </div>
</template>
