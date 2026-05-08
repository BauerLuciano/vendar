<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';

const mostrarMenuMovil = ref(false);
</script>

<template>
    <div class="flex h-screen bg-slate-50 font-sans relative overflow-hidden">
        
        <div v-if="mostrarMenuMovil" 
             @click="mostrarMenuMovil = false" 
             class="fixed inset-0 bg-slate-900/60 z-40 lg:hidden backdrop-blur-sm">
        </div>

        <aside :class="[
                'w-64 bg-slate-900 text-slate-400 flex flex-col shadow-2xl z-50 fixed lg:static inset-y-0 left-0 transition-transform duration-300 ease-in-out',
                mostrarMenuMovil ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
            ]">
            <div class="h-16 flex items-center px-6 bg-slate-950 border-b border-slate-800">
                <h2 class="text-lg font-black text-white tracking-widest uppercase">
                    Vend<span class="text-indigo-500">AR</span> <span class="text-[10px] text-slate-500 font-bold align-top">SaaS</span>
                </h2>
            </div>

            <nav class="flex-1 py-6 space-y-2 overflow-y-auto custom-scrollbar">
                <div class="px-6 mb-2 text-[10px] font-black uppercase tracking-widest text-slate-600">Gestión General</div>
                
                <Link :href="route('admin.comercios.index')" 
                      @click="mostrarMenuMovil = false"
                      class="flex items-center px-6 py-3 transition-colors duration-200 font-bold text-sm"
                      :class="route().current('admin.comercios.*') ? 'bg-indigo-600/10 text-indigo-400 border-r-4 border-indigo-500' : 'hover:bg-slate-800 hover:text-white'">
                    <span class="mr-3 text-lg">🏢</span> Comercios (Tenants)
                </Link>

                <a href="#" class="flex items-center px-6 py-3 hover:bg-slate-800 hover:text-white transition-colors duration-200 font-bold text-sm opacity-50 cursor-not-allowed">
                    <span class="mr-3 text-lg">📈</span> Métricas Globales
                </a>
                
                <a href="#" class="flex items-center px-6 py-3 hover:bg-slate-800 hover:text-white transition-colors duration-200 font-bold text-sm opacity-50 cursor-not-allowed">
                    <span class="mr-3 text-lg">💳</span> Facturación
                </a>

                <div class="px-6 mt-8 mb-2 text-[10px] font-black uppercase tracking-widest text-slate-600">Configuración</div>
                
                <a href="#" class="flex items-center px-6 py-3 hover:bg-slate-800 hover:text-white transition-colors duration-200 font-bold text-sm opacity-50 cursor-not-allowed">
                    <span class="mr-3 text-lg">⚙️</span> Ajustes del Sistema
                </a>
            </nav>

            <div class="p-4 bg-slate-950 border-t border-slate-800">
                <Link :href="route('dashboard')" class="flex items-center justify-between text-xs font-bold text-slate-400 hover:text-white transition-colors p-2 rounded-lg hover:bg-slate-800">
                    <span>⬅ Volver al sistema</span>
                </Link>
            </div>
        </aside>

        <div class="flex-1 flex flex-col w-full overflow-x-hidden relative">
            <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-4 lg:px-8 shadow-sm">
                
                <button @click="mostrarMenuMovil = true" class="lg:hidden p-2 text-slate-500 hover:text-indigo-600 focus:outline-none">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>

                <div class="flex items-center gap-3 ml-auto">
                    <div class="text-right hidden sm:block">
                        <p class="text-sm font-black text-slate-800">{{ $page.props.auth.user.name }}</p>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-indigo-500">Administrador Global</p>
                    </div>
                    <div class="h-10 w-10 rounded-full bg-indigo-100 border-2 border-indigo-200 flex items-center justify-center text-indigo-600 font-black">
                        {{ $page.props.auth.user.name.charAt(0) }}
                    </div>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto p-4 lg:p-8 bg-slate-50">
                <slot />
            </main>
        </div>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
</style>