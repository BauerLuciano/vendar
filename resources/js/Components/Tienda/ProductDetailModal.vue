<script setup>
import { computed } from 'vue';

const props = defineProps({
    producto: Object,
    visible: Boolean,
});

const emit = defineEmits(['close', 'agregar']);

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

const tieneStock = computed(() => props.producto && props.producto.stock > 0);
</script>

<template>
    <Transition name="backdrop">
        <div v-if="visible && producto" class="fixed inset-0 z-50 flex items-center justify-center p-4" :style="{ backgroundColor: 'var(--overlay)', backdropFilter: 'blur(2px)' }" @click.self="emit('close')">
            <Transition name="modal" appear>
                <div class="border rounded-3xl w-full max-w-lg max-h-[90vh] overflow-y-auto shadow-2xl transition-colors duration-300" :style="{ backgroundColor: 'var(--bg-elevated)', borderColor: 'var(--border-color)' }">
                    <div class="relative">
                        <div class="h-64 flex items-center justify-center p-6 relative" :style="{ backgroundColor: 'var(--bg-image)' }">
                            <img v-if="producto.imagen_url" :src="producto.imagen_url" :alt="producto.nombre" class="max-h-full max-w-full object-contain">
                            <div v-else class="flex items-center justify-center w-full h-full" :style="{ backgroundColor: 'var(--bg-elevated)' }">
                                <svg class="w-20 h-20" :style="{ color: 'var(--text-muted)' }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                            <button @click="emit('close')" class="absolute top-3 right-3 w-8 h-8 flex items-center justify-center rounded-full text-lg transition-all" :style="{ color: 'var(--text-on-accent)' }">&times;</button>
                            <div class="absolute top-3 left-3 bg-[#8cc63f] text-white px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-wider shadow-sm">{{ producto.categoria?.nombre || 'General' }}</div>
                        </div>
                    </div>

                    <div class="p-6 space-y-4">
                        <div>
                            <h2 class="text-xl font-black leading-tight transition-colors" :style="{ color: 'var(--text-primary)' }">{{ producto.nombre }}</h2>
                            <p v-if="producto.marca" class="text-[11px] font-bold mt-1 transition-colors" :style="{ color: 'var(--text-secondary)' }">{{ producto.marca.nombre }}</p>
                        </div>

                        <p class="text-[15px] font-black text-[#00adef]">{{ formatearDinero(parsearPrecio(producto.precio)) }}</p>

                        <div class="border rounded-xl p-4 transition-colors" :style="{ backgroundColor: 'var(--bg-page)', borderColor: 'var(--border-subtle)' }">
                            <p class="text-[10px] font-black tracking-widest uppercase mb-1.5 transition-colors" :style="{ color: 'var(--text-muted)' }">Descripción</p>
                            <p class="text-sm leading-relaxed transition-colors" :style="{ color: 'var(--text-secondary)' }">{{ producto.descripcion }}</p>
                        </div>

                        <div class="flex items-center gap-3">
                            <div class="flex items-center gap-2">
                                <div :class="tieneStock ? 'bg-[#8cc63f]' : 'bg-rose-500'" class="w-2 h-2 rounded-full"></div>
                                <span class="text-xs font-bold" :class="tieneStock ? 'text-[#8cc63f]' : 'text-rose-500'">
                                    {{ tieneStock ? `${producto.stock} en stock` : 'Sin stock' }}
                                </span>
                            </div>
                        </div>

                        <button
                            @click="emit('agregar', producto)"
                            :disabled="!tieneStock"
                            class="w-full font-black text-xs uppercase tracking-widest py-4 rounded-xl border transition-all duration-150"
                            :class="tieneStock
                                ? 'bg-[#f7941e] hover:bg-[#f7941e]/80 text-white border-[#f7941e] shadow-lg shadow-[#f7941e]/20 active:scale-95'
                                : 'cursor-not-allowed'"
                            :style="!tieneStock ? { backgroundColor: 'var(--bg-disabled)', color: 'var(--text-disabled)', borderColor: 'var(--border-color)' } : {}"
                        >
                            <span v-if="tieneStock">+ Agregar al pedido — {{ formatearDinero(parsearPrecio(producto.precio)) }}</span>
                            <span v-else>Sin stock disponible</span>
                        </button>
                    </div>
                </div>
            </Transition>
        </div>
    </Transition>
</template>

<style scoped>
.backdrop-enter-active, .backdrop-leave-active { transition: opacity 0.2s ease; }
.backdrop-enter-from, .backdrop-leave-to { opacity: 0; }

.modal-enter-active { transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); }
.modal-leave-active { transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1); }
.modal-enter-from { opacity: 0; transform: scale(0.95) translateY(10px); }
.modal-leave-to { opacity: 0; transform: scale(0.95) translateY(10px); }
</style>
