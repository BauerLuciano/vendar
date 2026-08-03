<script setup>
import { ref, watch, nextTick, onBeforeUnmount } from 'vue';

const props = defineProps({
    abierto: { type: Boolean, default: false },
    ancho: { type: String, default: 'w-48' },
    alignRight: { type: Boolean, default: true },
});

const emit = defineEmits(['close']);

const MARGEN = 8;

const anchorRef = ref(null);
const menuRef = ref(null);
const pos = ref({ top: 0, left: 0, flipped: false });

let limpiarListeners = null;

function calcularPosicion() {
    const btn = anchorRef.value?.getBoundingClientRect();
    const menu = menuRef.value;
    if (!btn || !menu) return;

    const alto = menu.offsetHeight;
    const ancho = menu.offsetWidth;

    let top = btn.bottom + MARGEN;
    let flipped = false;

    const espacioAbajo = window.innerHeight - btn.bottom;
    if (espacioAbajo < alto + MARGEN && btn.top > alto + MARGEN) {
        top = btn.top - alto - MARGEN;
        flipped = true;
    }

    let left = props.alignRight ? btn.right - ancho : btn.left;
    left = Math.max(MARGEN, Math.min(left, window.innerWidth - ancho - MARGEN));
    top = Math.max(MARGEN, top);

    pos.value = { top, left, flipped };
}

function cerrar() {
    emit('close');
}

function handleClickDocumento(e) {
    if (anchorRef.value?.contains(e.target)) return;
    if (menuRef.value?.contains(e.target)) return;
    cerrar();
}

function handleScroll(e) {
    if (menuRef.value?.contains(e.target)) return;
    cerrar();
}

function handleKeydown(e) {
    if (e.key === 'Escape') cerrar();
}

function handleResize() {
    calcularPosicion();
}

watch(() => props.abierto, async (val) => {
    if (limpiarListeners) {
        limpiarListeners();
        limpiarListeners = null;
    }

    if (!val) return;

    await nextTick();
    calcularPosicion();

    document.addEventListener('click', handleClickDocumento, true);
    document.addEventListener('scroll', handleScroll, true);
    window.addEventListener('resize', handleResize);
    document.addEventListener('keydown', handleKeydown);

    limpiarListeners = () => {
        document.removeEventListener('click', handleClickDocumento, true);
        document.removeEventListener('scroll', handleScroll, true);
        window.removeEventListener('resize', handleResize);
        document.removeEventListener('keydown', handleKeydown);
    };
});

onBeforeUnmount(() => {
    if (limpiarListeners) limpiarListeners();
});
</script>

<template>
    <span ref="anchorRef" class="inline-flex min-w-[40px] min-h-[40px] justify-center">
        <slot name="trigger" />

        <Teleport to="body">
            <Transition
                enter-active-class="transition ease-out duration-150"
                enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="transition ease-in duration-100"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
            >
                <div
                    v-if="abierto"
                    ref="menuRef"
                    class="fixed z-[9999] rounded-xl shadow-2xl border border-slate-100 py-2 bg-white"
                    :class="[ancho, pos.flipped ? 'origin-bottom-right' : 'origin-top-right']"
                    :style="{ top: pos.top + 'px', left: pos.left + 'px' }"
                    @click.stop
                >
                    <slot />
                </div>
            </Transition>
        </Teleport>
    </span>
</template>
