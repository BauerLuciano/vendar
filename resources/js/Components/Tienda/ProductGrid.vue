<script setup>
import ProductCard from './ProductCard.vue';

const props = defineProps({
    productos: Array,
    cargando: Boolean,
    sucursalElegida: [String, Number],
    busqueda: String,
    categoriaSeleccionada: [String, Number],
    categorias: Array,
    totalPaginas: Number,
    paginaActual: Number,
});

const emit = defineEmits(['agregar', 'detail', 'sort-change', 'page-change']);
</script>

<template>
    <main class="flex-grow relative z-10 max-w-7xl mx-auto w-full px-4 sm:px-6 py-6 pb-24">
        <div class="flex items-center justify-between mb-5" v-if="sucursalElegida">
            <div>
                <h2 class="text-base font-black uppercase tracking-tight transition-colors" :style="{ color: 'var(--text-primary)' }">
                    {{ categoriaSeleccionada === 'todas' ? 'Todos los productos' : categorias?.find(c => c.id == categoriaSeleccionada)?.nombre }}
                </h2>
                <p class="text-[11px] mt-0.5 transition-colors" :style="{ color: 'var(--text-muted)' }">{{ productos.length }} productos disponibles</p>
            </div>
            <select
                @change="$emit('sort-change', $event.target.value)"
                class="border rounded-xl px-3 py-2 text-[10px] font-bold transition-all cursor-pointer appearance-none outline-none focus:border-[#00adef]/30"
                :style="{
                    backgroundColor: 'var(--bg-input)',
                    borderColor: 'var(--border-color)',
                    color: 'var(--text-muted)',
                }"
            >
                <option value="nombre_asc">A-Z</option>
                <option value="nombre_desc">Z-A</option>
                <option value="precio_asc">Menor precio</option>
                <option value="precio_desc">Mayor precio</option>
            </select>
        </div>

        <div v-if="cargando" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-5">
            <div v-for="n in 10" :key="n" class="border rounded-2xl overflow-hidden relative" :style="{ backgroundColor: 'var(--bg-card)', borderColor: 'var(--border-color)' }">
                <div class="shimmer-overlay"></div>
                <div class="h-36" :style="{ backgroundColor: 'var(--bg-input)' }"></div>
                <div class="p-3 space-y-2">
                    <div class="h-2.5 rounded w-3/4" :style="{ backgroundColor: 'var(--bg-input)' }"></div>
                    <div class="h-4 rounded w-1/2" :style="{ backgroundColor: 'var(--bg-input)' }"></div>
                    <div class="h-8 rounded-xl" :style="{ backgroundColor: 'var(--bg-input)' }"></div>
                </div>
            </div>
        </div>

        <div v-else-if="!sucursalElegida" class="flex flex-col items-center justify-center py-20 text-center">
            <div class="w-24 h-24 bg-gradient-to-br from-[#00adef]/20 to-[#00adef]/5 border border-[#00adef]/25 rounded-[28px] flex items-center justify-center mb-6">
                <svg class="w-10 h-10 text-[#00adef]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
            </div>
            <h3 class="text-xl font-black mb-2 transition-colors" :style="{ color: 'var(--text-primary)' }">Tu pedido empieza acá</h3>
            <p class="text-sm max-w-sm transition-colors" :style="{ color: 'var(--text-muted)' }">Elegí una sucursal arriba o usá el GPS para ver el local más cercano.</p>
        </div>

        <div v-else-if="productos.length === 0 && !cargando" class="flex flex-col items-center justify-center py-20 text-center">
            <div class="w-24 h-24 bg-gradient-to-br from-[#f7941e]/20 to-[#f7941e]/5 border border-[#f7941e]/25 rounded-[28px] flex items-center justify-center mb-6">
                <svg class="w-10 h-10 text-[#f7941e]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m8.25 3v6.75m-4.5-7.5l1.5 9m9-9l-1.5 9M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/></svg>
            </div>
            <h3 class="text-xl font-black mb-2 transition-colors" :style="{ color: 'var(--text-primary)' }">Sin productos</h3>
            <p class="text-sm max-w-sm transition-colors" :style="{ color: 'var(--text-muted)' }">{{ busqueda ? `No encontramos resultados para "${busqueda}"` : 'Este local todavía no cargó productos visibles.' }}</p>
        </div>

        <TransitionGroup v-else name="card" tag="div" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-5">
            <ProductCard
                v-for="p in productos"
                :key="p.id"
                :producto="p"
                @agregar="emit('agregar', $event)"
                @detail="emit('detail', $event)"
            />
        </TransitionGroup>

        <Transition name="fade">
            <div v-if="totalPaginas > 1 && !cargando" class="flex justify-center items-center gap-4 mt-8">
                <button
                    @click="emit('page-change', paginaActual - 1)"
                    :disabled="paginaActual === 1"
                    class="px-5 py-2.5 border rounded-xl text-xs font-bold transition-all disabled:opacity-40 disabled:cursor-not-allowed"
                    :style="{
                        backgroundColor: 'var(--bg-input)',
                        borderColor: 'var(--border-color)',
                        color: 'var(--text-muted)',
                    }"
                >← Anterior</button>
                <span class="text-xs font-bold transition-colors" :style="{ color: 'var(--text-muted)' }">Pág. {{ paginaActual }} de {{ totalPaginas }}</span>
                <button
                    @click="emit('page-change', paginaActual + 1)"
                    :disabled="paginaActual === totalPaginas"
                    class="px-5 py-2.5 border rounded-xl text-xs font-bold transition-all disabled:opacity-40 disabled:cursor-not-allowed"
                    :style="{
                        backgroundColor: 'var(--bg-input)',
                        borderColor: 'var(--border-color)',
                        color: 'var(--text-muted)',
                    }"
                >Siguiente →</button>
            </div>
        </Transition>
    </main>
</template>

<style scoped>
.shimmer-overlay {
    position: absolute;
    inset: 0;
            background: linear-gradient(
        90deg,
        transparent 0%,
        rgba(255, 255, 255, 0.04) 50%,
        transparent 100%
    );
    background-size: 200% 100%;
    animation: shimmer 1.8s ease-in-out infinite;
    pointer-events: none;
    z-index: 1;
}

@keyframes shimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

.card-enter-active,
.card-leave-active {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.card-enter-from {
    opacity: 0;
    transform: scale(0.92) translateY(12px);
}
.card-leave-to {
    opacity: 0;
    transform: scale(0.92) translateY(12px);
}
.card-move {
    transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.25s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
