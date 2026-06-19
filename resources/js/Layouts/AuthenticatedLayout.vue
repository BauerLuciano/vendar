<script setup>
import { ref } from 'vue';
import Sidebar from '@/Components/Sidebar.vue';
import Header from '@/Components/Header.vue';
import { useAtajosTeclado } from '@/Composables/useAtajosTeclado';

useAtajosTeclado();

const mostrarMenuMovil = ref(false);
</script>

<template>
    <div class="h-screen w-full bg-slate-50 flex overflow-hidden">
        
        <div v-if="mostrarMenuMovil" 
             @click="mostrarMenuMovil = false" 
             class="fixed inset-0 bg-slate-900/60 z-[110] lg:hidden backdrop-blur-sm">
        </div>

        <div :class="[
            'fixed lg:static inset-y-0 left-0 z-[120] h-full transition-transform duration-300 ease-in-out',
            mostrarMenuMovil ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
        ]">
            <Sidebar @click="mostrarMenuMovil = false" />
        </div>

        <div class="flex-1 flex flex-col min-w-0 h-full">
            
            <Header @abrirMenu="mostrarMenuMovil = true" />

            <div class="flex-1 overflow-y-auto">
                <header v-if="$slots.header" class="bg-white shadow-sm border-b border-slate-200 px-4 lg:px-8 py-4 sticky top-0 z-30">
                    <div class="text-slate-500 font-bold uppercase text-[10px] lg:text-xs tracking-widest truncate">
                        <slot name="header" />
                    </div>
                </header>
                
                <main class="p-4 lg:p-8">
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>