<script setup>
import { ref, onMounted } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const mostrarMenuMovil = ref(false);
const seccionesAbiertas = ref({});

const menu = [
    {
        titulo: 'Gestión General',
        enlaces: [
            { nombre: 'Comercios (Tenants)', ruta: 'admin.comercios.index', icono: 'admin_global' },
            { nombre: 'Planes SaaS', ruta: 'admin.planes.index', icono: 'planes' },
            { nombre: 'Solicitudes', ruta: 'admin.solicitudes', icono: 'solicitudes' },
            { nombre: 'Métricas Globales', ruta: 'admin.metricas', icono: 'metricas' },
            { nombre: 'Facturación', ruta: 'admin.facturacion', icono: 'facturacion' },
        ]
    },
    {
        titulo: 'Configuración',
        enlaces: [
            { nombre: 'Credencial Padrón ARCA', ruta: 'admin.arca.credencial', icono: 'arca' },
            { nombre: 'Ajustes del Sistema', ruta: '#', icono: 'configuracion', disabled: true },
            { nombre: 'Volver al sistema', ruta: 'dashboard', icono: 'volver' },
        ]
    }
];

const esRutaActiva = (ruta) => {
    if (ruta === '#') return false;
    return route().current(ruta);
};

onMounted(() => {
    menu.forEach(seccion => {
        const tieneActivo = seccion.enlaces.some(link => esRutaActiva(link.ruta));
        seccionesAbiertas.value[seccion.titulo] = tieneActivo || seccion.titulo === 'Gestión General';
    });
});

const toggleSeccion = (titulo) => {
    seccionesAbiertas.value[titulo] = !seccionesAbiertas.value[titulo];
};

const handleLogout = () => {
    Swal.fire({
        title: '¿Cerrar sesión?',
        text: '¿Estás seguro/a que deseas cerrar sesión?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, cerrar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#e11d48',
        cancelButtonColor: '#475569',
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('logout'));
        }
    });
};
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

            <div class="px-4 py-4 border-b border-slate-800 flex justify-center items-center">
                <Link :href="route('admin.comercios.index')" class="block transition-transform hover:scale-105 w-full text-center">
                    <img
                        src="/img/LogoVendar-Sidebar.png"
                        alt="VendAR Logo"
                        class="w-full h-auto max-h-28 object-contain mx-auto"
                    >
                </Link>
            </div>

            <nav class="flex-1 px-4 py-6 space-y-4 overflow-y-auto custom-scrollbar">
                <template v-for="seccion in menu" :key="seccion.titulo">
                    <div class="space-y-1">
                        <button @click="toggleSeccion(seccion.titulo)" class="w-full flex items-center justify-between px-2 py-2 text-slate-500 hover:text-slate-300 transition-colors group">
                            <span class="text-[10px] font-black uppercase tracking-widest">{{ seccion.titulo }}</span>
                            <svg :class="{'rotate-180': seccionesAbiertas[seccion.titulo]}" class="w-3 h-3 transition-transform duration-200" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>

                        <div v-show="seccionesAbiertas[seccion.titulo]" class="space-y-1 mt-1 animate-in slide-in-from-top-2 duration-200">
                            <template v-for="item in seccion.enlaces" :key="item.nombre">
                                <a v-if="item.disabled"
                                    href="#"
                                    class="flex items-center gap-3 px-4 py-2.5 rounded-xl font-bold transition-all duration-200 text-sm text-slate-500 opacity-40 cursor-not-allowed"
                                >
                                    <span class="w-5 h-5 flex items-center justify-center">
                                        <svg v-if="item.icono === 'configuracion'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.99l1.005.828c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                    </span>
                                    {{ item.nombre }}
                                </a>

                                <Link v-else
                                    :href="route(item.ruta)"
                                    @click="mostrarMenuMovil = false"
                                    :class="[esRutaActiva(item.ruta) ? 'bg-sky-600 text-white shadow-lg shadow-sky-600/20' : 'text-slate-400 hover:bg-slate-800 hover:text-white']"
                                    class="flex items-center gap-3 px-4 py-2.5 rounded-xl font-bold transition-all duration-200 text-sm"
                                >
                                    <span class="w-5 h-5 flex items-center justify-center">
                                        <svg v-if="item.icono === 'admin_global'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" /></svg>
                                        <svg v-if="item.icono === 'planes'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" /></svg>
                                        <svg v-if="item.icono === 'solicitudes'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                                        <svg v-if="item.icono === 'metricas'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>
                                        <svg v-if="item.icono === 'facturacion'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" /></svg>
                                        <svg v-if="item.icono === 'arca'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg>
                                        <svg v-if="item.icono === 'volver'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
                                    </span>
                                    {{ item.nombre }}
                                </Link>
                            </template>
                        </div>
                    </div>
                </template>
            </nav>

            <div class="p-4 border-t border-slate-800 bg-slate-900/50">
                <button
                    @click="handleLogout"
                    class="w-full flex items-center justify-center gap-3 px-4 py-3 rounded-xl bg-slate-800 text-rose-500 font-black uppercase tracking-tighter text-[11px] hover:bg-rose-600 hover:text-white transition-all duration-200 group shadow-lg"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4 group-hover:translate-x-1 transition-transform">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                    </svg>
                    Cerrar Sesión
                </button>
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
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #475569; }
</style>