<template>
    <header class="bg-slate-900 border-b border-slate-800 shadow-md px-6 py-3 flex justify-between items-center h-16 z-40 relative">
        
        <div class="flex items-center gap-4">
            <h2 class="text-xl font-black text-white tracking-tight hidden sm:block">
                Vend<span class="text-orange-500 font-bold">AR</span>
            </h2>
        </div>

        <div class="flex items-center gap-4">
            
            <div class="relative">
                <button 
                    @click="campanaAbierta = !campanaAbierta; menuAbierto = false"
                    class="relative p-2 text-slate-400 hover:text-white transition-colors group outline-none"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 group-hover:rotate-12 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>

                    <span v-if="alertasInfo.total > 0" class="absolute top-1 right-1 inline-flex items-center justify-center px-1.5 py-0.5 text-[9px] font-black leading-none text-white transform translate-x-1/4 -translate-y-1/4 bg-rose-500 rounded-full shadow-sm shadow-rose-500/50 border border-slate-900 animate-in zoom-in duration-300">
                        {{ alertasInfo.total > 99 ? '99+' : alertasInfo.total }}
                    </span>
                </button>

                <div 
                    v-show="campanaAbierta" 
                    class="absolute right-0 mt-3 w-80 bg-slate-900/95 backdrop-blur-xl rounded-2xl shadow-[0_20px_50px_-12px_rgba(0,0,0,0.8)] border border-slate-700/60 z-50 overflow-hidden animate-in fade-in slide-in-from-top-2 duration-200 origin-top-right flex flex-col"
                >
                    <div class="px-4 py-3 border-b border-slate-800 bg-slate-800/50 flex justify-between items-center">
                        <p class="text-xs uppercase tracking-widest font-black text-slate-300">Stock Crítico</p>
                        <span class="bg-rose-500/20 text-rose-400 text-[10px] font-bold px-2 py-0.5 rounded-full">{{ alertasInfo.total }} ítems</span>
                    </div>

                    <div class="max-h-[300px] overflow-y-auto custom-scrollbar">
                        <div v-if="alertasInfo.total === 0" class="p-6 text-center">
                            <p class="text-slate-400 text-sm font-bold">Todo en orden ✅</p>
                        </div>
                        
                        <div v-else class="divide-y divide-slate-800/50">
                            <div v-for="(alerta, index) in alertasInfo.detalle" :key="index" class="p-4 hover:bg-slate-800/30 transition-colors flex flex-col gap-1">
                                <div class="flex justify-between items-start">
                                    <p class="text-sm font-bold text-slate-200 leading-tight">{{ alerta.producto }}</p>
                                    <span :class="Number(alerta.cantidad_fisica) <= 0 ? 'bg-rose-500/20 text-rose-400' : 'bg-amber-500/20 text-amber-400'" class="text-[10px] font-black px-2 py-0.5 rounded-md ml-2 whitespace-nowrap">
                                        {{ Number(alerta.cantidad_fisica) <= 0 ? 'AGOTADO' : 'BAJO' }}
                                    </span>
                                </div>
                                <p class="text-[11px] text-slate-500 font-medium italic">📍 {{ alerta.sucursal }}</p>
                                <p class="text-[11px] font-mono text-slate-400 mt-1">
                                    Stock: <span :class="Number(alerta.cantidad_fisica) <= 0 ? 'text-rose-400' : 'text-amber-400'" class="font-bold">{{ Number(alerta.cantidad_fisica) }}</span> 
                                    / Mín: {{ Number(alerta.stock_minimo) }} {{ alerta.unidad_medida || 'U' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div v-if="alertasInfo.total > 0" class="p-3 bg-slate-800/80 border-t border-slate-700 flex gap-2">
                        <Link :href="route('transferencias.index')" @click="campanaAbierta = false" class="flex-1 text-center bg-slate-700 hover:bg-slate-600 text-white text-[10px] font-bold uppercase tracking-widest py-2.5 rounded-lg transition-all">
                            Logística
                        </Link>
                        <Link :href="route('dashboard')" @click="campanaAbierta = false" class="flex-1 text-center bg-sky-600 hover:bg-sky-50 text-white text-[10px] font-bold uppercase tracking-widest py-2.5 rounded-lg shadow-md transition-all">
                            Ver Todo
                        </Link>
                    </div>
                </div>
            </div>

            <div class="relative">
                <button 
                    @click="menuAbierto = !menuAbierto; campanaAbierta = false"
                    class="flex items-center gap-3 p-1 pr-4 rounded-full bg-slate-800/50 border border-slate-700/50 hover:bg-slate-800 hover:border-slate-600 transition-all group outline-none"
                >
                    <div class="relative">
                        <div class="w-9 h-9 bg-slate-700 group-hover:bg-sky-500 rounded-full flex items-center justify-center text-[11px] font-black text-white shadow-inner transition-all">
                            {{ user.name.charAt(0) }}
                        </div>
                        <div class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-emerald-500 border-2 border-slate-900 rounded-full"></div>
                    </div>

                    <div class="hidden md:flex flex-col justify-center max-w-[130px]">
                        <span class="text-slate-200 group-hover:text-white text-xs font-bold truncate tracking-tight leading-none mb-1">
                            {{ user.name }}
                        </span>
                        <span class="text-slate-400 text-[9px] font-black uppercase tracking-widest truncate leading-none">
                            {{ rolesFormateados }}
                        </span>
                    </div>

                    <svg :class="{'rotate-180 text-white': menuAbierto}" class="h-4 w-4 transition-transform text-slate-500 group-hover:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div 
                    v-show="menuAbierto" 
                    class="absolute right-0 mt-3 w-64 bg-slate-900/95 backdrop-blur-xl rounded-2xl shadow-2xl border border-slate-700/60 z-50 overflow-hidden animate-in fade-in slide-in-from-top-2 duration-200"
                >
                    <div class="px-5 py-4 border-b border-slate-800">
                        <p class="text-[10px] uppercase tracking-widest font-black text-slate-500 mb-1">Sesión Activa</p>
                        <p class="text-sm font-bold text-white truncate">{{ user.email }}</p>
                    </div>

                    <div class="p-2 space-y-1">
                        <Link :href="route('profile.edit')" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold text-slate-400 hover:text-white hover:bg-slate-800 transition-colors">
                            Configurar Perfil
                        </Link>
                        <button @click="logout" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 transition-colors">
                            Cerrar Sesión
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </header>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { usePage, router, Link } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page.props.auth.user);
const rolesUsuario = computed(() => page.props.auth.user.roles || []);
const rolesFormateados = computed(() => rolesUsuario.value.length > 0 ? rolesUsuario.value.join(' - ') : 'Sin Rol');

// 🔔 Atajamos el OBJETO de alertas (total y detalle)
const alertasInfo = computed(() => page.props.auth.alertas || { total: 0, detalle: [] });

const menuAbierto = ref(false);
const campanaAbierta = ref(false);

const logout = () => router.post(route('logout'));

const closeMenus = (e) => {
    if (!e.target.closest('.relative')) {
        menuAbierto.value = false;
        campanaAbierta.value = false;
    }
};

onMounted(() => document.addEventListener('click', closeMenus));
onUnmounted(() => document.removeEventListener('click', closeMenus));
</script>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #334155; border-radius: 10px; }
</style>