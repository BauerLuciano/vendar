<script setup>
import { inject } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    comercio: Object,
    tienda_slug: String,
    busqueda: String,
    totalItems: Number,
    totalFinalCheckout: Number,
    estaLogueado: Boolean,
    esConsumidorLogueado: Boolean,
    esAdminLogueado: Boolean,
    consumidorActual: Object,
    carritoLength: Number,
    authUser: Object,
});

const emit = defineEmits(['update:busqueda', 'toggle-carrito']);

const theme = inject('theme');
const toggleTheme = inject('toggleTheme');
</script>

<template>
    <nav class="sticky top-0 z-40 transition-all duration-300" :style="{ backgroundColor: 'var(--bg-navbar)', borderBottom: '1px solid var(--border-subtle)', boxShadow: 'var(--shadow-sm)' }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 flex items-center gap-4 min-h-[80px]">
            <Link :href="'/tienda/' + tienda_slug" class="shrink-0 flex items-center gap-3">
                <img v-if="comercio?.url_logo" :src="comercio.url_logo" :alt="comercio.nombre" class="h-14 w-auto object-contain" :style="{ backgroundColor: 'var(--bg-input)' }">
                <img v-else :src="theme === 'dark' ? '/img/LogoVendar-Sidebar.png' : '/img/LogoVendar-Sidebar-light.png'" alt="VendAR" class="h-16 w-auto object-contain" :style="{ backgroundColor: theme === 'dark' ? 'transparent' : 'var(--bg-input)' }">
                <span class="hidden sm:inline text-base font-black tracking-tight leading-none pt-0.5" :style="{ color: 'var(--text-primary)' }">{{ comercio?.nombre || '' }}</span>
            </Link>

            <div class="flex-1 flex items-center justify-center">
                <div class="relative w-full max-w-2xl">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4" :style="{ color: 'var(--text-muted)' }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                    </svg>
                    <input
                        :value="busqueda"
                        @input="emit('update:busqueda', $event.target.value)"
                        type="text"
                        placeholder="Buscá productos..."
                        class="w-full border rounded-2xl py-2.5 pl-10 pr-4 text-sm transition-all focus:outline-none focus:border-[var(--color-accent)] focus:ring-2 focus:ring-[var(--color-accent)]/20 placeholder-[var(--text-muted)] focus:scale-[1.01]"
                        :style="{ backgroundColor: 'var(--bg-input)', borderColor: 'var(--border-color)', color: 'var(--text-primary)' }"
                    >
                </div>
            </div>

            <div class="flex items-center gap-1.5 shrink-0">
                <button
                    @click="toggleTheme"
                    class="w-8 h-8 flex items-center justify-center rounded-xl transition-all hover:scale-110 active:scale-90"
                    :style="{ color: 'var(--text-muted)' }"
                    :title="theme === 'dark' ? 'Modo claro' : 'Modo oscuro'"
                >
                    <svg v-if="theme === 'dark'" class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/>
                    </svg>
                    <svg v-else class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z"/>
                    </svg>
                </button>

                <button
                    v-if="carritoLength > 0"
                    @click="emit('toggle-carrito')"
                    class="relative flex items-center gap-1.5 px-3 py-2 rounded-xl font-bold text-xs transition-all active:scale-95"
                    :style="{ backgroundColor: 'var(--color-secondary)', color: 'var(--text-on-accent)' }"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/>
                    </svg>
                    <span :key="totalItems" class="badge-bounce rounded-lg px-1.5 font-bold text-[11px]" :style="{ backgroundColor: 'var(--bg-card)', color: 'var(--text-primary)' }">{{ totalItems }}</span>
                    <span class="hidden lg:inline font-black text-[11px]">{{ new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(totalFinalCheckout) }}</span>
                </button>

                <div v-if="esConsumidorLogueado" class="flex items-center gap-1 px-2 py-1 rounded-xl border transition-colors" :style="{ backgroundColor: 'var(--bg-input)', borderColor: 'var(--border-color)' }">
                    <Link :href="'/tienda/' + tienda_slug + '/panel'" class="text-[10px] font-bold px-1.5 py-1 rounded-lg transition-all flex items-center gap-1"
                        :style="{ color: 'var(--color-success)' }">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        Panel
                    </Link>
                    <span class="text-[9px] opacity-10">|</span>
                    <span class="text-[11px] font-bold truncate max-w-[70px]" :style="{ color: 'var(--text-primary)' }">{{ consumidorActual?.nombre }}</span>
                    <Link :href="route('tienda.logout.consumidor')" method="get" as="button" class="text-[10px] font-bold px-1.5 py-0.5 rounded-lg transition-all"
                        :style="{ color: 'var(--color-danger)' }">Salir</Link>
                </div>

                <div v-else-if="esAdminLogueado" class="flex items-center gap-1.5 px-2 py-1.5 rounded-xl border transition-colors" :style="{ backgroundColor: 'var(--bg-input)', borderColor: 'var(--border-color)' }">
                    <div class="w-6 h-6 rounded-full flex items-center justify-center shrink-0 border" :style="{ backgroundColor: 'var(--color-accent)' }">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="color: #fff"><path d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 014.998 0"/></svg>
                    </div>
                    <span class="text-[11px] font-bold truncate max-w-[80px]" :style="{ color: 'var(--text-primary)' }">{{ authUser?.name?.split(' ')[0] }}</span>
                    <span class="opacity-20">|</span>
                    <Link :href="route('logout')" method="post" as="button" class="text-[10px] font-bold px-1.5 py-0.5 rounded-lg transition-all"
                        :style="{ color: 'var(--color-danger)' }">Salir</Link>
                </div>

                <template v-else>
                    <Link :href="'/tienda/' + tienda_slug + '/login'" class="text-xs font-bold uppercase tracking-wider px-2.5 py-2 transition-colors rounded-lg"
                        :style="{ color: 'var(--text-secondary)' }">Ingresar</Link>
                    <Link :href="'/tienda/' + tienda_slug + '/register'" class="text-xs font-bold uppercase tracking-wider rounded-xl px-3.5 py-2 transition-all"
                        :style="{ backgroundColor: 'var(--color-accent)', color: 'var(--text-on-accent)' }">Registrarse</Link>
                </template>
            </div>
        </div>
    </nav>
</template>

<style scoped>
.badge-bounce {
    animation: badgeBounce 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
}
@keyframes badgeBounce {
    0% { transform: scale(1); }
    40% { transform: scale(1.25); }
    100% { transform: scale(1); }
}
</style>
