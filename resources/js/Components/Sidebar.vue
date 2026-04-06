<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';

const page = usePage();
// Obtenemos los roles que mandamos desde HandleInertiaRequests
const rolesUsuario = computed(() => page.props.auth.user.roles || []);

// El "Patovica" del Frontend
const tieneAcceso = (rolesPermitidos) => {
    if (rolesUsuario.value.includes('Administrador Global') || rolesUsuario.value.includes('SuperAdmin')) return true;
    if (!rolesPermitidos || rolesPermitidos.length === 0) return true;
    return rolesPermitidos.some(rol => rolesUsuario.value.includes(rol));
};

const menu = [
    {
        titulo: 'Principal',
        roles: [], // Todos ven
        enlaces: [
            { nombre: 'Dashboard', ruta: 'dashboard', icono: 'dashboard', roles: [] },
        ]
    },
    {
        titulo: 'Comercial',
        roles: ['Cajero', 'Encargado', 'SuperAdmin'], 
        enlaces: [
            { nombre: 'Punto de Venta', ruta: 'pos.index', icono: 'pos', roles: ['Cajero', 'Encargado', 'SuperAdmin'] },
            { nombre: 'Historial de Ventas', ruta: 'ventas.index', icono: 'ventas', roles: ['Cajero', 'Encargado', 'SuperAdmin'] }, 
        ]
    },
    {
        titulo: 'Inventario',
        roles: ['Encargado', 'SuperAdmin'], // El Cajero NO ve esta sección
        enlaces: [
            { nombre: 'Ingresos de Stock', ruta: 'ingresos.index', icono: 'ingresos', roles: ['Encargado', 'SuperAdmin'] },
            { nombre: 'Productos', ruta: 'productos.index', icono: 'productos', roles: ['Encargado', 'SuperAdmin'] },
            { nombre: 'Categorías', ruta: 'categorias.index', icono: 'categorias', roles: ['Encargado', 'SuperAdmin'] },
            { nombre: 'Marcas', ruta: 'marcas.index', icono: 'marcas', roles: ['Encargado', 'SuperAdmin'] },
        ]
    },
    {
        titulo: 'Contactos',
        roles: ['Cajero', 'Encargado', 'SuperAdmin'],
        enlaces: [
            { nombre: 'Clientes', ruta: 'consumidores.index', icono: 'clientes', roles: ['Cajero', 'Encargado', 'SuperAdmin'] },
            { nombre: 'Proveedores', ruta: 'proveedores.index', icono: 'proveedores', roles: ['Encargado', 'SuperAdmin'] },
        ]
    },
    {
        titulo: 'Administración',
        roles: ['SuperAdmin'], // Solo dueños y nosotros
        enlaces: [
            { nombre: 'Sucursales', ruta: 'sucursales.index', icono: 'sucursales', roles: ['SuperAdmin'] },
            { nombre: 'Equipo (Usuarios)', ruta: 'usuarios.index', icono: 'usuarios', roles: ['SuperAdmin'] },
            { nombre: 'Roles y Permisos', ruta: 'roles.index', icono: 'seguridad', roles: ['SuperAdmin'] },
        ]
    }
];

const seccionesAbiertas = ref({});

const esRutaActiva = (ruta) => {
    if (ruta === '#') return false;
    return route().current(ruta.split('.')[0] + '*');
};

onMounted(() => {
    menu.forEach(seccion => {
        const tieneActivo = seccion.enlaces.some(link => esRutaActiva(link.ruta));
        seccionesAbiertas.value[seccion.titulo] = tieneActivo || seccion.titulo === 'Principal';
    });
});

const toggleSeccion = (titulo) => {
    seccionesAbiertas.value[titulo] = !seccionesAbiertas.value[titulo];
};
</script>

<template>
    <div class="w-64 bg-slate-900 h-screen fixed left-0 top-0 shadow-2xl flex flex-col z-50">
        
        <div class="p-6 border-b border-slate-800 flex justify-center">
            <Link :href="route('dashboard')" class="block transition-transform hover:scale-105">
                <img src="/img/LogoVendar-Sidebar.png" alt="VendAR Logo" class="w-48 h-auto object-contain">
            </Link>
        </div>

        <nav class="flex-1 px-4 py-6 space-y-4 overflow-y-auto font-sans custom-scrollbar">
            <template v-for="seccion in menu" :key="seccion.titulo">
                <div v-if="tieneAcceso(seccion.roles)" class="space-y-1">
                    
                    <button @click="toggleSeccion(seccion.titulo)" class="w-full flex items-center justify-between px-2 py-2 text-slate-500 hover:text-slate-300 transition-colors group">
                        <span class="text-[10px] font-black uppercase tracking-widest">{{ seccion.titulo }}</span>
                        <svg :class="{'rotate-180': seccionesAbiertas[seccion.titulo]}" class="w-3 h-3 transition-transform duration-200" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>

                    <div v-show="seccionesAbiertas[seccion.titulo]" class="space-y-1 mt-1 animate-in slide-in-from-top-2 duration-200">
                        <template v-for="item in seccion.enlaces" :key="item.nombre">
                            <Link v-if="tieneAcceso(item.roles)"
                                :href="item.ruta !== '#' ? route(item.ruta) : '#'"
                                :class="[esRutaActiva(item.ruta) ? 'bg-sky-600 text-white shadow-lg shadow-sky-600/20' : 'text-slate-400 hover:bg-slate-800 hover:text-white']"
                                class="flex items-center gap-3 px-4 py-2.5 rounded-xl font-bold transition-all duration-200 text-sm"
                            >
                                <span class="w-5 h-5 flex items-center justify-center">
                                    <svg v-if="item.icono === 'dashboard'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" /></svg>
                                    <svg v-if="item.icono === 'pos'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" /></svg>
                                    <svg v-if="item.icono === 'ventas'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" /></svg>
                                    <svg v-if="item.icono === 'ingresos'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" /></svg>
                                    <svg v-if="item.icono === 'productos'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0l-3-3m3 3l3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" /></svg>
                                    <svg v-if="item.icono === 'categorias'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581a2.25 2.25 0 0 0 3.182 0l4.318-4.318a2.25 2.25 0 0 0 0-3.182L11.159 3.659A2.25 2.25 0 0 0 9.568 3Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" /></svg>
                                    <svg v-if="item.icono === 'marcas'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.504-1.125-1.125-1.125h-6.75c-.621 0-1.125.504-1.125 1.125v3.375m9 0h-9M9 10.125c0 .621.504 1.125 1.125 1.125h3.75c.621 0 1.125-.504 1.125-1.125V4.875c0-.621-.504-1.125-1.125-1.125h-3.75c-.621 0-1.125.504-1.125 1.125v5.25Z" /></svg>
                                    <svg v-if="item.icono === 'proveedores'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" /></svg>
                                    <svg v-if="item.icono === 'sucursales'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75v-3.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.5c0 .414.336.75.75.75Z" /></svg>
                                    <svg v-if="item.icono === 'clientes'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>
                                    <svg v-if="item.icono === 'seguridad'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" /></svg>
                                    <svg v-if="item.icono === 'usuarios'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Zm6-10.125a1.875 1.875 0 1 1-3.75 0 1.875 1.875 0 0 1 3.75 0Zm1.294 6.336a6.721 6.721 0 0 1-3.17.789 6.721 6.721 0 0 1-3.168-.789 3.376 3.376 0 0 1 6.338 0Z" /></svg>
                                </span>
                                {{ item.nombre }}
                            </Link>
                        </template>
                    </div>
                </div>
            </template>
        </nav>

        <div class="p-4 border-t border-slate-800 bg-slate-950/50">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-sky-500 rounded-full flex items-center justify-center text-[10px] font-black text-white uppercase shadow-lg shadow-sky-500/20">
                    {{ $page.props.auth.user.name.charAt(0) }}
                </div>
                <div class="flex flex-col overflow-hidden">
                    <span class="text-white text-xs font-bold truncate tracking-tight">{{ $page.props.auth.user.name }}</span>
                    <Link :href="route('logout')" method="post" as="button" class="text-slate-500 text-[10px] hover:text-rose-500 text-left font-bold uppercase transition-colors tracking-widest">
                        Cerrar Sesión
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>