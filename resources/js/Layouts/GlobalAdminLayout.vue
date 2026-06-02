<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';

const mostrarMenuMovil = ref(false);
</script>

<template>
    <div class="flex h-screen bg-slate-50 font-sans relative overflow-hidden text-slate-700">
        
        <div v-if="mostrarMenuMovil" 
             @click="mostrarMenuMovil = false" 
             class="fixed inset-0 bg-slate-900/60 z-40 lg:hidden backdrop-blur-sm">
        </div>

        <aside :class="[
                'w-64 bg-slate-900 text-slate-400 flex flex-col shadow-2xl z-50 fixed lg:static inset-y-0 left-0 transition-transform duration-300 ease-in-out',
                mostrarMenuMovil ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
            ]">
            
            <div class="h-16 flex items-center px-6 bg-slate-950 border-b border-slate-800">
                <h2 class="text-lg font-black text-white tracking-widest uppercase italic">
                    Vend<span class="text-[#00adef]">AR</span> <span class="text-[10px] text-[#00adef] font-bold align-top not-italic">SaaS</span>
                </h2>
            </div>

            <nav class="flex-1 py-6 space-y-2 overflow-y-auto custom-scrollbar">
                <div class="px-6 mb-2 text-[10px] font-black uppercase tracking-widest text-slate-600">Gestión General</div>
                
                <Link :href="route('admin.comercios.index')" 
                      @click="mostrarMenuMovil = false"
                      class="flex items-center px-6 py-3 transition-colors duration-200 font-bold text-sm"
                      :class="route().current('admin.comercios.*') 
                        ? 'bg-[#00adef]/10 text-[#00adef] border-r-4 border-[#00adef]' 
                        : 'hover:bg-slate-800 hover:text-white'">
                    <span class="mr-3 text-lg">🏢</span> Comercios (Tenants)
                </Link>

                <Link :href="route('admin.planes.index')" 
                      @click="mostrarMenuMovil = false"
                      class="flex items-center px-6 py-3 transition-colors duration-200 font-bold text-sm"
                      :class="route().current('admin.planes*') 
                        ? 'bg-[#00adef]/10 text-[#00adef] border-r-4 border-[#00adef]' 
                        : 'hover:bg-slate-800 hover:text-white'">
                    <span class="mr-3 text-lg">📦</span> Planes SaaS
                </Link>

                <Link :href="route('admin.solicitudes')" 
                      @click="mostrarMenuMovil = false"
                      class="flex items-center px-6 py-3 transition-colors duration-200 font-bold text-sm"
                      :class="route().current('admin.solicitudes*') 
                        ? 'bg-[#00adef]/10 text-[#00adef] border-r-4 border-[#00adef]' 
                        : 'hover:bg-slate-800 hover:text-white'">
                    <span class="mr-3 text-lg">📋</span> Solicitudes de Comercios
                </Link>

                <Link :href="route('admin.metricas')" 
                      @click="mostrarMenuMovil = false"
                      class="flex items-center px-6 py-3 transition-colors duration-200 font-bold text-sm"
                      :class="route().current('admin.metricas') 
                        ? 'bg-[#00adef]/10 text-[#00adef] border-r-4 border-[#00adef]' 
                        : 'hover:bg-slate-800 hover:text-white'">
                    <span class="mr-3 text-lg">📈</span> Métricas Globales
                </Link>
                
                <Link :href="route('admin.facturacion')" 
                      @click="mostrarMenuMovil = false"
                      class="flex items-center px-6 py-3 transition-colors duration-200 font-bold text-sm"
                      :class="route().current('admin.facturacion') 
                        ? 'bg-[#00adef]/10 text-[#00adef] border-r-4 border-[#00adef]' 
                        : 'hover:bg-slate-800 hover:text-white'">
                    <span class="mr-3 text-lg">💳</span> Facturación
                </Link>

                <div class="px-6 mt-8 mb-2 text-[10px] font-black uppercase tracking-widest text-slate-600">Configuración</div>
                
                <a href="#" class="flex items-center px-6 py-3 font-bold text-sm opacity-40 cursor-not-allowed">
                    <span class="mr-3 text-lg">⚙️</span> Ajustes del Sistema
                </a>
            </nav>

            <div class="p-4 bg-slate-950 border-t border-slate-800 space-y-2">
                <Link :href="route('dashboard')" class="flex items-center gap-3 text-xs font-bold text-slate-500 hover:text-white transition-colors p-2 rounded-lg hover:bg-slate-800">
                    <span>⬅</span> Volver al sistema
                </Link>

                <Link 
                    :href="route('logout')" 
                    method="post" 
                    as="button"
                    class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl bg-rose-500/10 text-rose-500 font-black uppercase tracking-tighter text-[10px] hover:bg-rose-600 hover:text-white transition-all duration-200 group"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                    </svg>
                    Cerrar Sesión
                </Link>
            </div>
        </aside>

        <div class="flex-1 flex flex-col w-full overflow-x-hidden relative">
            
            <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-4 lg:px-8 shadow-sm">
    
    <button @click="mostrarMenuMovil = true" class="lg:hidden p-2 text-slate-500 hover:text-[#00adef] focus:outline-none">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
        </svg>
    </button>

    <div class="flex items-center gap-3 ml-auto">
        <div class="text-right hidden sm:block">
            <p class="text-sm font-black text-slate-800">{{ $page.props.auth.user.name }}</p>
            <p class="text-[10px] font-bold uppercase tracking-widest text-[#00adef]">Administrador Global</p>
        </div>
        <div class="h-10 w-10 rounded-full bg-[#00adef]/10 border border-[#00adef]/30 flex items-center justify-center text-[#00adef] font-black">
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