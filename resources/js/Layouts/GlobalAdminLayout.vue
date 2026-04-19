<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="flex h-screen bg-slate-50 font-sans">
        
        <aside class="w-64 bg-slate-900 text-slate-400 flex flex-col shadow-2xl z-10">
            <div class="h-16 flex items-center px-6 bg-slate-950 border-b border-slate-800">
                <h2 class="text-lg font-black text-white tracking-widest uppercase">
                    Vend<span class="text-indigo-500">AR</span> <span class="text-[10px] text-slate-500 font-bold align-top">SaaS</span>
                </h2>
            </div>

            <nav class="flex-1 py-6 space-y-2 overflow-y-auto">
                <div class="px-6 mb-2 text-[10px] font-black uppercase tracking-widest text-slate-600">Gestión General</div>
                
                <Link :href="route('admin.comercios.index')" 
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

        <div class="flex-1 flex flex-col overflow-hidden">
            <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-end px-8 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="text-right">
                        <p class="text-sm font-black text-slate-800">{{ $page.props.auth.user.name }}</p>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-indigo-500">Administrador Global</p>
                    </div>
                    <div class="h-10 w-10 rounded-full bg-indigo-100 border-2 border-indigo-200 flex items-center justify-center text-indigo-600 font-black">
                        {{ $page.props.auth.user.name.charAt(0) }}
                    </div>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto">
                <slot />
            </main>
        </div>
    </div>
</template>