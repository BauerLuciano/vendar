<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    sucursales: Array,
});

const form = useForm({
    sucursal_id: '',
});
</script>

<template>
    <Head title="Elegir Sucursal" />

    <div class="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-sky-900 flex items-center justify-center p-6">
        <div class="w-full max-w-2xl animate-fade-in-up">
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-white/10 backdrop-blur rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <i class="ri-store-2-line text-3xl text-sky-400"></i>
                </div>
                <h1 class="text-2xl font-black text-white tracking-tight">Elegí tu sucursal</h1>
                <p class="text-slate-400 text-sm mt-1">Seleccioná en qué sucursal querés trabajar hoy</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <button v-for="suc in sucursales" :key="suc.id"
                    @click="form.sucursal_id = suc.id; form.post(route('elegir.sucursal.store'))"
                    :disabled="form.processing"
                    class="relative group bg-white/5 hover:bg-white/10 backdrop-blur border border-white/10 hover:border-sky-500/50 rounded-2xl p-6 text-left transition-all duration-200 hover:shadow-xl hover:shadow-sky-500/10 disabled:opacity-50">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 bg-sky-500/10 rounded-xl flex items-center justify-center shrink-0 group-hover:bg-sky-500/20 transition-colors">
                            <i class="ri-store-3-line text-xl text-sky-400"></i>
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-lg font-bold text-white group-hover:text-sky-300 transition-colors truncate">{{ suc.nombre }}</h3>
                            <p v-if="suc.direccion" class="text-xs text-slate-400 mt-0.5 truncate">{{ suc.direccion }}</p>
                            <span class="inline-block mt-2 text-[10px] font-bold uppercase tracking-wider text-sky-400/70 group-hover:text-sky-400 transition-colors">
                                Ingresar <i class="ri-arrow-right-line ml-1"></i>
                            </span>
                        </div>
                    </div>
                </button>
            </div>

            <p v-if="form.errors.sucursal_id" class="text-rose-400 text-sm text-center mt-4">{{ form.errors.sucursal_id }}</p>
        </div>
    </div>
</template>
