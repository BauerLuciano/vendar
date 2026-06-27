<script setup>
import { ref, watch, computed, onMounted, onUnmounted, nextTick } from 'vue';
import axios from 'axios';
import BaseSkeleton from '@/Components/UI/BaseSkeleton.vue';
import BaseBadge from '@/Components/UI/BaseBadge.vue';

const props = defineProps({
    sucursalId: [String, Number],
});

const emit = defineEmits(['agregar', 'detail', 'ver-todas']);

const promos = ref([]);
const cargando = ref(false);
const scrollContainer = ref(null);
const canScrollLeft = ref(false);
const canScrollRight = ref(false);

const showNav = computed(() => promos.value.length > 4);

const cargarPromos = async (id) => {
    if (!id) { promos.value = []; return; }
    cargando.value = true;
    try {
        const res = await axios.get(`/api/promociones/${id}`);
        promos.value = res.data.data || [];
        await nextTick();
        checkScroll();
    } catch (e) {
        promos.value = [];
    } finally {
        cargando.value = false;
    }
};

watch(() => props.sucursalId, (nuevoId) => {
    cargarPromos(nuevoId);
}, { immediate: false });

const formatearDinero = (monto) => {
    return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(monto);
};

const checkScroll = () => {
    const el = scrollContainer.value;
    if (!el) return;
    canScrollLeft.value = el.scrollLeft > 16;
    canScrollRight.value = el.scrollLeft < el.scrollWidth - el.clientWidth - 16;
};

const scrollLeft = () => {
    scrollContainer.value?.scrollBy({ left: -scrollContainer.value.clientWidth, behavior: 'smooth' });
};

const scrollRight = () => {
    scrollContainer.value?.scrollBy({ left: scrollContainer.value.clientWidth, behavior: 'smooth' });
};

const totalDots = computed(() => Math.max(1, Math.ceil(promos.value.length / 5)));

const currentDot = computed(() => {
    const el = scrollContainer.value;
    if (!el || el.scrollWidth <= el.clientWidth) return 0;
    const ratio = el.scrollLeft / (el.scrollWidth - el.clientWidth);
    return Math.min(Math.round(ratio * (totalDots.value - 1)), totalDots.value - 1);
});

const scrollToDot = (idx) => {
    const el = scrollContainer.value;
    if (!el) return;
    const targetScroll = (idx / (totalDots.value - 1)) * (el.scrollWidth - el.clientWidth);
    el.scrollTo({ left: targetScroll, behavior: 'smooth' });
};

let wheelHandler;
onMounted(() => {
    const el = scrollContainer.value;
    if (!el) return;
    wheelHandler = (e) => {
        if (Math.abs(e.deltaY) > Math.abs(e.deltaX)) {
            e.preventDefault();
            el.scrollBy({ left: e.deltaY, behavior: 'auto' });
        }
    };
    el.addEventListener('wheel', wheelHandler, { passive: false });
});
onUnmounted(() => {
    scrollContainer.value?.removeEventListener('wheel', wheelHandler);
});
</script>

<template>
    <div id="promo-section" v-if="promos.length > 0 || cargando" class="max-w-7xl mx-auto w-full px-4 sm:px-6 pt-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-black" style="background: linear-gradient(135deg, #f7941e, #ff6b35); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                🔥 Ofertas destacadas
            </h2>
            <button
                v-if="!cargando"
                @click="$emit('ver-todas')"
                class="text-[11px] font-bold flex items-center gap-1 transition-all duration-200 hover:gap-1.5"
                style="color: #f7941e;"
            >
                Ver todas
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>

        <div v-if="cargando" class="flex gap-3 overflow-x-hidden flex-nowrap">
            <div v-for="n in 5" :key="n" class="shrink-0 w-[82vw] sm:w-[44vw] md:w-[30vw] lg:w-[19%] xl:w-[16%] border rounded-2xl overflow-hidden" :style="{ backgroundColor: 'var(--bg-card)', borderColor: 'var(--border-color)' }">
                <BaseSkeleton width="100%" height="192px" rounded="0" />
                <div class="p-3 space-y-2">
                    <BaseSkeleton width="75%" height="12px" rounded="4px" />
                    <BaseSkeleton width="50%" height="20px" rounded="4px" />
                    <BaseSkeleton width="100%" height="36px" rounded="10px" />
                </div>
            </div>
        </div>

        <div v-if="!cargando" class="relative">
            <button
                v-if="showNav && canScrollLeft"
                @click="scrollLeft"
                class="absolute left-0 top-1/2 -translate-y-1/2 -translate-x-3 z-20 w-9 h-9 rounded-full flex items-center justify-center shadow-lg transition-all duration-200 hover:scale-110 hover:shadow-xl"
                style="background: linear-gradient(135deg, #f7941e, #ff6b35); color: #fff;"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
            </button>

            <button
                v-if="showNav && canScrollRight"
                @click="scrollRight"
                class="absolute right-0 top-1/2 -translate-y-1/2 translate-x-3 z-20 w-9 h-9 rounded-full flex items-center justify-center shadow-lg transition-all duration-200 hover:scale-110 hover:shadow-xl"
                style="background: linear-gradient(135deg, #f7941e, #ff6b35); color: #fff;"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
            </button>

            <div
                ref="scrollContainer"
                @scroll="checkScroll"
                class="flex gap-3 overflow-x-auto snap-x snap-mandatory scrollbar-hide flex-nowrap pb-1"
            >
                <div
                    v-for="p in promos"
                    :key="p.id"
                    class="promo-card snap-start shrink-0 w-[82vw] sm:w-[44vw] md:w-[30vw] lg:w-[19%] xl:w-[16%] border rounded-2xl overflow-hidden cursor-pointer transition-all duration-300"
                    :style="{ backgroundColor: 'var(--bg-card)', borderColor: 'var(--border-color)' }"
                    @click="emit('detail', p)"
                >
                    <div class="relative flex items-center justify-center p-3 overflow-hidden" :style="{ backgroundColor: 'var(--bg-image)', aspectRatio: '4 / 3' }">
                        <img
                            :src="p.imagen_url || '/img/LogoVendar-Sidebar.png'"
                            :alt="p.nombre"
                            loading="lazy"
                            class="max-h-full max-w-full object-contain promo-card-img transition-transform duration-500"
                        >
                        <div v-if="p.stock <= 0" class="absolute inset-0 flex items-center justify-center z-10" style="background: rgba(0,0,0,0.45);">
                            <BaseBadge variant="danger">Sin stock</BaseBadge>
                        </div>
                        <div
                            class="absolute top-2 left-2 px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider shadow-lg z-10"
                            style="background: linear-gradient(135deg, #f7941e, #ff6b35); color: #fff;"
                        >
                            {{ p.promotion?.label || '🔥 Promoción' }}
                        </div>
                        <div
                            v-if="p.promotion?.discount_percent && p.stock > 0"
                            class="absolute top-2 right-2 z-10 flex flex-col items-center justify-center rounded-xl font-black leading-none shadow-lg"
                            style="background: linear-gradient(135deg, #e74c3c, #c0392b); color: #fff; min-width: 52px; padding: 6px 8px;"
                        >
                            <span class="text-[11px] opacity-80">-</span>
                            <span class="text-lg">{{ p.promotion.discount_percent }}%</span>
                            <span class="text-[8px] uppercase tracking-wider font-bold" style="background: rgba(255,255,255,0.2); border-radius: 3px; padding: 1px 6px; margin-top: 1px;">OFF</span>
                        </div>
                    </div>
                    <div class="p-3 flex flex-col flex-grow">
                        <h3 class="text-[11px] font-bold leading-tight line-clamp-2 mb-1.5 transition-colors" :style="{ color: 'var(--text-primary)' }">
                            {{ p.nombre }}
                        </h3>

                        <div class="flex flex-wrap items-baseline gap-x-1.5 gap-y-0.5 mb-1">
                            <span class="text-base font-black tracking-tight" style="color: #f7941e;">{{ formatearDinero(p.promotion?.final_price) }}</span>
                            <span class="text-[10px] line-through" :style="{ color: 'var(--text-muted)' }">{{ formatearDinero(p.precio) }}</span>
                        </div>

                        <p v-if="p.promotion?.discount_amount && p.promotion.discount_amount > 0" class="text-[10px] font-bold flex items-center gap-1" style="color: #22c55e;">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Ahorrás {{ formatearDinero(p.promotion.discount_amount) }}
                        </p>

                        <button
                            @click.stop="emit('agregar', p)"
                            :disabled="p.stock <= 0"
                            class="btn-add-promo mt-2 w-full font-black text-[10px] uppercase tracking-widest py-2.5 rounded-xl border transition-all duration-200 active:scale-95"
                            :class="p.stock <= 0 ? 'opacity-50 cursor-not-allowed' : ''"
                            style="background: linear-gradient(135deg, #f7941e, #ff6b35); color: #fff; border-color: transparent; box-shadow: 0 4px 12px rgba(247, 148, 30, 0.25);"
                        >
                            <template v-if="p.stock <= 0">Sin Stock</template>
                            <template v-else>+ Agregar</template>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="!cargando && showNav" class="flex items-center justify-center gap-2 mt-3">
            <button
                v-for="dot in totalDots"
                :key="dot"
                @click="scrollToDot(dot - 1)"
                class="w-2 h-2 rounded-full transition-all duration-300"
                :class="currentDot === dot - 1 ? 'w-5' : ''"
                :style="{
                    backgroundColor: currentDot === dot - 1 ? '#f7941e' : 'var(--border-color)',
                }"
            ></button>
        </div>
    </div>
</template>

<style scoped>
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }

.promo-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 32px rgba(247, 148, 30, 0.15) !important;
    border-color: #f7941e40 !important;
}

.promo-card:hover .promo-card-img {
    transform: scale(1.08);
}

.btn-add-promo:hover {
    filter: brightness(1.1) !important;
    box-shadow: 0 6px 20px rgba(247, 148, 30, 0.4) !important;
    transform: translateY(-1px);
}
</style>
