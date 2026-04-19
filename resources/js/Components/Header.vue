<template>
    <header class="bg-slate-900 border-b border-slate-800 shadow-md px-6 py-3 flex justify-between items-center h-16 z-40 relative">
        
        <div class="flex items-center gap-4">
            <h2 class="text-xl font-black text-white tracking-tight hidden sm:block">
                Vend<span class="text-sky-500 font-medium">AR</span>
            </h2>
        </div>

        <div class="flex items-center gap-4">
            
            <div class="relative">
                <button 
                    @click="menuAbierto = !menuAbierto"
                    class="flex items-center gap-3 p-1 pr-4 rounded-full bg-slate-800/50 border border-slate-700/50 hover:bg-slate-800 hover:border-slate-600 focus:bg-slate-800 focus:ring-2 focus:ring-sky-500/50 transition-all duration-200 outline-none text-left group"
                >
                    <div class="relative">
                        <div class="w-9 h-9 bg-slate-700 group-hover:bg-sky-500 rounded-full flex items-center justify-center text-[11px] font-black text-white shadow-inner group-hover:shadow-sky-500/30 transition-all duration-300">
                            {{ user.name.charAt(0) }}
                        </div>
                        <div class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-emerald-500 border-2 border-slate-900 rounded-full"></div>
                    </div>

                    <div class="hidden md:flex flex-col justify-center max-w-[130px]">
                        <span class="text-slate-200 group-hover:text-white text-xs font-bold truncate tracking-tight leading-none mb-1 transition-colors">
                            {{ user.name }}
                        </span>
                        <span class="text-slate-400 text-[9px] font-black uppercase tracking-widest truncate leading-none flex items-center gap-1">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-2.5 w-2.5 text-sky-500" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            {{ rolesFormateados }}
                        </span>
                    </div>

                    <svg :class="{'rotate-180 text-white': menuAbierto, 'text-slate-500': !menuAbierto}" class="h-4 w-4 transition-transform duration-300 ml-1 group-hover:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div 
                    v-show="menuAbierto" 
                    class="absolute right-0 mt-3 w-64 bg-slate-900/95 backdrop-blur-xl rounded-2xl shadow-[0_20px_50px_-12px_rgba(0,0,0,0.8)] border border-slate-700/60 z-50 overflow-hidden animate-in fade-in slide-in-from-top-2 duration-200 origin-top-right"
                >
                    <div class="px-5 py-4 border-b border-slate-800 bg-slate-800/20">
                        <p class="text-[10px] uppercase tracking-widest font-black text-slate-500 mb-1">Sesión Activa</p>
                        <p class="text-sm font-bold text-white truncate">{{ user.email || 'usuario@vendar.com' }}</p>
                    </div>

                    <div class="p-2 space-y-1">
                        <Link 
                            :href="route('profile.edit')" 
                            class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold text-slate-400 hover:text-white hover:bg-slate-800 transition-colors group/item"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-500 group-hover/item:text-sky-400 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            Configurar Perfil
                        </Link>
                        
                        <div class="h-px bg-slate-800/80 my-1 mx-2"></div>
                        
                        <button 
                            @click="logout" 
                            class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 transition-colors group/item"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-500 group-hover/item:text-rose-400 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
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

const rolesFormateados = computed(() => {
    if (rolesUsuario.value.length > 0) {
        return rolesUsuario.value.join(' - ');
    }
    return 'Sin Rol Asignado';
});

const menuAbierto = ref(false);

const logout = () => {
    router.post(route('logout'));
};

const closeMenu = (e) => {
    if (!e.target.closest('.relative')) {
        menuAbierto.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', closeMenu);
});

onUnmounted(() => {
    document.removeEventListener('click', closeMenu);
});
</script>