<script setup>
import Sidebar from '@/Components/Sidebar.vue';
import Header from '@/Components/Header.vue';
</script>

<template>
    <div class="min-h-screen bg-slate-100 dark:bg-slate-900 flex transition-colors duration-300">
        
        <Sidebar />

        <div class="flex-1 ml-64 min-h-screen flex flex-col">
            
            <Header />

            <header v-if="$slots.header" class="bg-white dark:bg-slate-800 shadow-sm border-b border-slate-200 dark:border-slate-700 sticky top-0 z-40 h-16 flex items-center px-8 justify-between transition-colors duration-300">
                <div class="text-slate-400 dark:text-slate-300 font-bold uppercase text-xs tracking-[0.2em]">
                    <slot name="header" />
                </div>
            </header>

            <main class="p-8 flex-1">
                <slot />
            </main>
        </div>
    </div>
</template>