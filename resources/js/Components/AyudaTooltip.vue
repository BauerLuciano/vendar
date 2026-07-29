<script setup>
import { ref } from 'vue';

const props = defineProps({
    texto: { type: String, required: true },
    posicion: { type: String, default: 'top' },
});

const abierto = ref(false);
</script>

<template>
    <span class="relative inline-flex items-center">
        <button @click.stop="abierto = !abierto"
            @mouseenter="abierto = true"
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
                     'bottom-full mb-2 left-1/2 -translate-x-1/2': posicion === 'top',
                     'top-full mt-2 left-1/2 -translate-x-1/2': posicion === 'bottom',
                     'right-full mr-2 top-1/2 -translate-y-1/2': posicion === 'left',
                     'left-full ml-2 top-1/2 -translate-y-1/2': posicion === 'right',
                 }">
                {{ texto }}
                <div class="absolute w-2 h-2 bg-slate-800 rotate-45"
                     :class="{
                         'top-full -mt-1 left-1/2 -translate-x-1/2': posicion === 'top',
                         'bottom-full -mb-1 left-1/2 -translate-x-1/2': posicion === 'bottom',
                         'right-full -mr-1 top-1/2 -translate-y-1/2': posicion === 'left',
                         'left-full -ml-1 top-1/2 -translate-y-1/2': posicion === 'right',
                     }"></div>
            </div>
        </Transition>
    </span>
</template>
