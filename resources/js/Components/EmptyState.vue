<script setup>
import { Link } from '@inertiajs/vue3';

const emit = defineEmits(['accion-click']);

defineProps({
    titulo: { type: String, required: true },
    descripcion: { type: String, default: '' },
    icono: { type: String, default: 'inbox' },
    accionLabel: { type: String, default: '' },
    accionHref: { type: String, default: '' },
    accionRoute: { type: String, default: '' },
    accionParams: { type: Object, default: () => ({}) },
    accionEvent: { type: Boolean, default: false },
});
</script>

<template>
    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-10 text-center">
        <div class="mx-auto w-16 h-16 bg-slate-100 text-slate-400 rounded-2xl flex items-center justify-center mb-4">
            <svg v-if="icono === 'inbox'" xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
            <svg v-else-if="icono === 'caja'" xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            <svg v-else-if="icono === 'sucursal'" xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
        </div>
        <h3 class="text-sm font-black text-slate-700 uppercase tracking-widest mb-2">{{ titulo }}</h3>
        <p v-if="descripcion" class="text-xs text-slate-500 mb-5 max-w-sm mx-auto leading-relaxed">{{ descripcion }}</p>
        <div v-if="accionLabel" class="flex justify-center">
            <Link v-if="accionRoute" :href="route(accionRoute, accionParams)" class="bg-sky-600 text-white text-[10px] font-black uppercase tracking-widest px-5 py-2.5 rounded-xl hover:bg-sky-700 transition-colors">
                {{ accionLabel }}
            </Link>
            <button v-else-if="accionEvent" @click="$emit('accion-click')" class="bg-sky-600 text-white text-[10px] font-black uppercase tracking-widest px-5 py-2.5 rounded-xl hover:bg-sky-700 transition-colors">
                {{ accionLabel }}
            </button>
            <a v-else-if="accionHref" :href="accionHref" class="bg-sky-600 text-white text-[10px] font-black uppercase tracking-widest px-5 py-2.5 rounded-xl hover:bg-sky-700 transition-colors">
                {{ accionLabel }}
            </a>
        </div>
    </div>
</template>
