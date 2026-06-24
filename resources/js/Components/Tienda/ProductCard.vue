<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    producto: Object,
});

const emit = defineEmits(['agregar', 'detail']);

const agregando = ref(false);

const handleAgregar = () => {
    emit('agregar', props.producto);
    agregando.value = true;
    setTimeout(() => { agregando.value = false; }, 900);
};

const parsearPrecio = (valor) => {
    if (!valor) return 0;
    if (typeof valor === 'number') return valor;
    let str = String(valor);
    if (str.includes(',')) str = str.replace(/\./g, '').replace(',', '.');
    return parseFloat(str) || 0;
};

const formatearDinero = (monto) => {
    return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(monto);
};

const bajoStock = computed(() => props.producto.stock > 0 && props.producto.stock <= 5);
const sinStock = computed(() => props.producto.stock <= 0);
const enPromocion = computed(() => props.producto.promotion);

const botonStyle = computed(() => {
    if (sinStock.value) {
        return { backgroundColor: 'var(--bg-disabled)', color: 'var(--text-disabled)', borderColor: 'var(--border-color)', cursor: 'not-allowed' };
    }
    if (agregando.value) {
        return { backgroundColor: 'var(--color-success)', color: '#fff', borderColor: 'var(--color-success)' };
    }
    return {
        backgroundColor: 'color-mix(in srgb, var(--color-secondary) 15%, transparent)',
        color: 'var(--color-secondary)',
        borderColor: 'color-mix(in srgb, var(--color-secondary) 30%, transparent)',
    };
});
</script>

<template>
    <div
        class="card group border rounded-2xl overflow-hidden flex flex-col transition-all duration-300 hover:-translate-y-1.5 cursor-pointer"
        :style="{
            backgroundColor: 'var(--bg-card)',
            borderColor: 'var(--border-color)',
            boxShadow: 'var(--shadow-sm)',
        }"
        @click="emit('detail', producto)"
    >
        <div class="relative h-44 flex items-center justify-center p-3 overflow-hidden" :style="{ backgroundColor: 'var(--bg-image)' }">
            <img v-if="producto.imagen_url" :src="producto.imagen_url" :alt="producto.nombre" loading="lazy" class="max-h-full max-w-full object-contain group-hover:scale-105 transition-transform duration-500">
            <div v-else class="flex items-center justify-center w-full h-full" :style="{ backgroundColor: 'color-mix(in srgb, var(--bg-image) 50%, transparent)' }">
                <svg class="w-10 h-10 opacity-40" :style="{ color: 'var(--text-muted)' }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </div>

            <div v-if="enPromocion"
                class="absolute top-2 left-2 px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider shadow-sm z-10"
                style="background: linear-gradient(135deg, #f7941e, #ff6b35); color: #fff;">
                {{ producto.promotion.label || '🔥 Promoción' }}
            </div>

            <div v-else-if="producto.categoria?.nombre" class="absolute top-2 left-2 px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider shadow-sm z-10" :style="{ backgroundColor: 'color-mix(in srgb, var(--color-accent) 80%, transparent)', color: '#fff' }">
                {{ producto.categoria.nombre }}
            </div>

            <div v-if="!enPromocion && bajoStock" class="absolute top-2 right-2 px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider shadow-sm" :style="{ backgroundColor: 'var(--color-secondary)', color: '#fff' }">
                Últimas!
            </div>

            <div v-if="enPromocion && producto.promotion.discount_percent" class="absolute top-2 right-2 z-10 flex flex-col items-center justify-center rounded-lg font-black leading-none shadow-sm"
                style="background: linear-gradient(135deg, #e74c3c, #c0392b); color: #fff; min-width: 44px; padding: 4px 6px;"
            >
                <span class="text-[16px]">{{ producto.promotion.discount_percent }}%</span>
                <span class="text-[7px] uppercase tracking-wider font-bold" style="background: rgba(255,255,255,0.2); border-radius: 2px; padding: 1px 4px; margin-top: 1px;">OFF</span>
            </div>

            <div v-if="sinStock" class="absolute inset-0 flex items-center justify-center backdrop-blur-sm z-10" :style="{ backgroundColor: 'rgba(0,0,0,0.4)' }">
                <span class="px-3 py-1 rounded-full text-[11px] font-black uppercase tracking-wider" :style="{ backgroundColor: 'var(--color-danger)', color: '#fff' }">Sin stock</span>
            </div>
        </div>
        <div class="p-3.5 flex flex-col flex-grow">
            <h3 class="text-xs font-bold leading-tight line-clamp-2 mb-2 flex-grow transition-colors" :style="{ color: 'var(--text-primary)' }">{{ producto.nombre }}</h3>

            <div v-if="enPromocion" class="flex flex-wrap items-baseline gap-x-1.5 gap-y-0.5">
                <span class="text-lg font-black tracking-tight" style="color: #f7941e;">{{ formatearDinero(parsearPrecio(producto.promotion.final_price)) }}</span>
                <span class="text-[10px] line-through" :style="{ color: 'var(--text-muted)' }">{{ formatearDinero(parsearPrecio(producto.precio)) }}</span>
            </div>
            <p v-else class="text-lg font-black tracking-tight transition-colors" :style="{ color: 'var(--text-primary)' }">{{ formatearDinero(parsearPrecio(producto.precio)) }}</p>

            <p v-if="enPromocion && producto.promotion.discount_amount && producto.promotion.discount_amount > 0" class="text-[9px] font-bold mt-0.5 flex items-center gap-1" style="color: #22c55e;">
                Ahorrás {{ formatearDinero(parsearPrecio(producto.promotion.discount_amount)) }}
            </p>

            <button
                @click.stop="handleAgregar"
                :disabled="sinStock"
                class="btn-add mt-3 w-full font-black text-[10px] uppercase tracking-widest py-2.5 rounded-xl border transition-all duration-150 active:scale-95"
                :class="sinStock ? 'opacity-50' : 'opacity-0 group-hover:opacity-100'"
                :style="botonStyle"
            >
                <span v-if="sinStock">Sin Stock</span>
                <span v-else-if="agregando" class="flex items-center justify-center gap-1.5">✔ Agregado</span>
                <span v-else class="flex items-center justify-center gap-1.5">+ Agregar</span>
            </button>
        </div>
    </div>
</template>

<style scoped>
.card:hover {
    box-shadow: 0 8px 28px rgba(0, 0, 0, 0.08) !important;
    border-color: var(--color-accent) !important;
}

.btn-add:not(:disabled):hover {
    background-color: var(--color-secondary) !important;
    color: #fff !important;
    border-color: var(--color-secondary) !important;
}
</style>
