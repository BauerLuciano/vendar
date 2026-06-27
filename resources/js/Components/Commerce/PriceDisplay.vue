<script setup>
import { computed } from 'vue';

const props = defineProps({
    price: { type: [Number, String], default: 0 },
    promo: { type: Object, default: null },
    size: { type: String, default: 'lg' },
});

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

const sizes = {
    sm: { price: 'text-sm', original: 'text-[9px]', savings: 'text-[8px]' },
    md: { price: 'text-base', original: 'text-[10px]', savings: 'text-[9px]' },
    lg: { price: 'text-xl', original: 'text-[11px]', savings: 'text-[10px]' },
};

const sizeClasses = computed(() => sizes[props.size] || sizes.lg);

const finalPrice = computed(() => {
    if (props.promo?.final_price) return parsearPrecio(props.promo.final_price);
    return parsearPrecio(props.price);
});

const originalPrice = computed(() => parsearPrecio(props.price));

const savings = computed(() => {
    if (props.promo?.discount_amount && parsearPrecio(props.promo.discount_amount) > 0) {
        return parsearPrecio(props.promo.discount_amount);
    }
    return 0;
});

const onSale = computed(() => !!props.promo);
</script>

<template>
    <div class="flex flex-wrap items-baseline gap-x-1.5 gap-y-0.5">
        <span
            v-if="onSale"
            :class="[sizeClasses.price, 'font-black tracking-tight']"
            style="color: #f7941e;"
        >
            {{ formatearDinero(finalPrice) }}
        </span>
        <span
            v-else
            :class="[sizeClasses.price, 'font-black tracking-tight transition-colors']"
            :style="{ color: 'var(--text-primary)' }"
        >
            {{ formatearDinero(originalPrice) }}
        </span>
        <span
            v-if="onSale"
            :class="[sizeClasses.original, 'line-through']"
            :style="{ color: 'var(--text-muted)' }"
        >
            {{ formatearDinero(originalPrice) }}
        </span>
        <p
            v-if="onSale && savings > 0"
            :class="[sizeClasses.savings, 'font-bold w-full flex items-center gap-1']"
            style="color: #22c55e;"
        >
            Ahorrás {{ formatearDinero(savings) }}
        </p>
    </div>
</template>
