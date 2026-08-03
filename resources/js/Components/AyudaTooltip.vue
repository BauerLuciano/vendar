<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    texto: { type: String, required: true },
    posicion: { type: String, default: 'top' },
});

const abierto = ref(false);
const posicionFinal = ref(props.posicion);
const ancla = ref(null);

const TIP_W = 256;
const TIP_H = 96;
const MARGEN = 12;

function calcularPosicion() {
    const el = ancla.value;
    if (!el) return;
    const r = el.getBoundingClientRect();
    let p = props.posicion;

    const libreIzquierda = r.left - MARGEN;
    const libreDerecha = window.innerWidth - r.right - MARGEN;

    if (p === 'top' || p === 'bottom') {
        const centroX = r.left + r.width / 2;
        const caeFueraX = centroX < TIP_W / 2 + MARGEN || window.innerWidth - centroX < TIP_W / 2 + MARGEN;
        if (caeFueraX) {
            p = libreDerecha >= TIP_W ? 'left' : 'right';
        } else {
            const libreArriba = r.top - MARGEN;
            const libreAbajo = window.innerHeight - r.bottom - MARGEN;
            if (p === 'top' && libreArriba < TIP_H && libreAbajo >= TIP_H) {
                p = 'bottom';
            } else if (p === 'bottom' && libreAbajo < TIP_H && libreArriba >= TIP_H) {
                p = 'top';
            }
        }
    } else if (p === 'left' && libreDerecha < TIP_W && libreIzquierda >= TIP_W) {
        p = 'right';
    } else if (p === 'right' && libreIzquierda < TIP_W && libreDerecha >= TIP_W) {
        p = 'left';
    }

    posicionFinal.value = p;
}

function abrir() {
    calcularPosicion();
    abierto.value = true;
}

watch(() => props.posicion, (v) => {
    posicionFinal.value = v;
});
</script>

<template>
    <span ref="ancla" class="relative inline-flex items-center">
        <button @click.stop="abierto ? (abierto = false) : abrir()"
            @mouseenter="abrir()"
            @mouseleave="abierto = false"
            class="w-4 h-4 rounded-full bg-slate-200 hover:bg-indigo-200 text-slate-500 hover:text-indigo-600 flex items-center justify-center text-[9px] font-black transition-colors cursor-help shrink-0"
            type="button">
            ?
        </button>
        <Transition enter-active-class="ease-out duration-150" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100"
                    leave-active-class="ease-in duration-100" leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
            <div v-if="abierto"
                 class="absolute z-50 w-64 p-3 bg-slate-800 text-white text-xs font-medium rounded-xl shadow-xl pointer-events-none"
                 :class="{
                     'bottom-full mb-2 left-1/2 -translate-x-1/2': posicionFinal === 'top',
                     'top-full mt-2 left-1/2 -translate-x-1/2': posicionFinal === 'bottom',
                     'right-full mr-2 top-1/2 -translate-y-1/2': posicionFinal === 'right',
                     'left-full ml-2 top-1/2 -translate-y-1/2': posicionFinal === 'left',
                 }">
                {{ texto }}
                <div class="absolute w-2 h-2 bg-slate-800 rotate-45"
                     :class="{
                         'top-full -mt-1 left-1/2 -translate-x-1/2': posicionFinal === 'top',
                         'bottom-full -mb-1 left-1/2 -translate-x-1/2': posicionFinal === 'bottom',
                         'right-full -mr-1 top-1/2 -translate-y-1/2': posicionFinal === 'right',
                         'left-full -ml-1 top-1/2 -translate-y-1/2': posicionFinal === 'left',
                     }"></div>
            </div>
        </Transition>
    </span>
</template>
