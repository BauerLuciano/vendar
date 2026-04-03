<script setup>
import { Link } from '@inertiajs/vue3';

const links = [
    { nombre: 'Dashboard', ruta: 'dashboard', icono: 'dashboard' },
    { nombre: 'Productos', ruta: 'productos.index', icono: 'productos' },
    { nombre: 'Categorías', ruta: 'categorias.index', icono: 'categorias' },
    { nombre: 'Marcas', ruta: 'marcas.index', icono: 'marcas' }, 
    { nombre: 'Ventas (POS)', ruta: 'pos.index', icono: 'ventas' },
];
</script>

<template>
    <div class="w-64 bg-slate-900 h-screen fixed left-0 top-0 shadow-2xl flex flex-col z-50">
        
        <div class="p-6 flex items-center gap-3 border-b border-slate-800">
            <div class="w-10 h-10 bg-sky-600 rounded-xl flex items-center justify-center shadow-lg shadow-sky-600/30">
                <span class="text-white font-black text-xl">V</span>
            </div>
            <div class="flex flex-col">
                <span class="text-white font-black tracking-tighter text-lg leading-none">VENDAR<span class="text-sky-500">APP</span></span>
                <span class="text-[10px] text-slate-500 font-bold uppercase tracking-widest mt-1">Sist. Gestión</span>
            </div>
        </div>

        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
            <Link v-for="item in links" :key="item.nombre" 
                :href="item.ruta !== '#' ? route(item.ruta) : '#'"
                :class="[
                    item.ruta !== '#' && route().current(item.ruta + '*') 
                    ? 'bg-sky-600 text-white shadow-lg shadow-sky-600/20' 
                    : 'text-slate-400 hover:bg-slate-800 hover:text-white'
                ]"
                class="flex items-center gap-3 px-4 py-3 rounded-xl font-bold transition-all duration-200 group"
            >
                <span class="w-5 h-5 flex items-center justify-center">
                    <svg v-if="item.icono === 'dashboard'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" /></svg>
                    
                    <svg v-if="item.icono === 'productos'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-5.25v9" /></svg>
                    
                    <svg v-if="item.icono === 'categorias'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581a2.25 2.25 0 0 0 3.182 0l4.318-4.318a2.25 2.25 0 0 0 0-3.182L11.159 3.659A2.25 2.25 0 0 0 9.568 3Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" /></svg>
                    
                    <svg v-if="item.icono === 'marcas'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.504-1.125-1.125-1.125h-6.75c-.621 0-1.125.504-1.125 1.125v3.375m9 0h-9M9 10.125c0 .621.504 1.125 1.125 1.125h3.75c.621 0 1.125-.504 1.125-1.125V4.875c0-.621-.504-1.125-1.125-1.125h-3.75c-.621 0-1.125.504-1.125 1.125v5.25Z" /></svg>
                    
                    <svg v-if="item.icono === 'ventas'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" /></svg>
                </span>
                {{ item.nombre }}
            </Link>
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