<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ModalCategoria from './Componentes/ModalCategoria.vue'; 
import DetalleCategoria from './Componentes/DetalleCategoria.vue'; 
import { Head, router } from '@inertiajs/vue3';
import { ref, watch, reactive } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({ 
    categorias: Object,
    filtros: Object
});

const menuAbierto = ref(null);

const formFiltros = reactive({
    search: props.filtros?.search || '',
    estado: props.filtros?.estado || 'all'
});

let timeout = null;

watch(formFiltros, (value) => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(route('categorias.index'), value, {
            preserveState: true,
            replace: true
        });
    }, 300);
});

const limpiarFiltros = () => {
    formFiltros.search = '';
    formFiltros.estado = 'all';
};

const verModal = ref(false);
const verDetalle = ref(false);
const seleccionado = ref(null);

const toggleMenu = (id) => {
    menuAbierto.value = menuAbierto.value === id ? null : id;
};

const cerrarMenu = () => {
    menuAbierto.value = null;
};

const abrirNuevo = () => { 
    cerrarMenu();
    seleccionado.value = null; 
    verModal.value = true; 
};

const abrirEditar = (c) => { 
    cerrarMenu();
    seleccionado.value = c; 
    verModal.value = true; 
};

const abrirDetalle = (c) => { 
    cerrarMenu();
    seleccionado.value = c; 
    verDetalle.value = true; 
};

const cerrarModal = () => {
    verModal.value = false;
    seleccionado.value = null;
};

const toggleEstado = (c) => {
    cerrarMenu();
    const accion = c.estado ? 'desactivar' : 'activar';
    const resultado = c.estado ? 'desactivada' : 'activada'; 
    const colorConfirm = c.estado ? '#ef4444' : '#10b981';

    Swal.fire({
        title: `¿${accion.toUpperCase()} categoría?`,
        text: `La categoría "${c.nombreCategoria}" cambiará su estado a ${resultado}.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: colorConfirm,
        cancelButtonColor: '#6b7280',
        confirmButtonText: `Sí, ${accion}`,
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            router.patch(route('categorias.status', c.id), {}, {
                onSuccess: () => {
                    Swal.fire({
                        title: '¡Listo!',
                        text: `Categoría ${resultado} correctamente.`,
                        icon: 'success',
                        confirmButtonColor: '#0284c7',
                    });
                }
            });
        }
    });
};
</script>

<template>
    <Head title="Gestión de Categorías" />

    <AuthenticatedLayout>
        <div v-if="menuAbierto" @click="cerrarMenu" class="fixed inset-0 z-30"></div>

        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Control de Categorías</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <div class="flex flex-col sm:flex-row justify-between items-center mb-6 bg-white p-4 rounded-2xl shadow-sm border border-gray-100 gap-4">
                    <div class="flex gap-4 w-full sm:w-1/2">
                        <div class="relative w-2/3">
                            <input 
                                v-model="formFiltros.search" 
                                type="text" 
                                placeholder="Buscar categoría..." 
                                class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-xl focus:ring-sky-500 focus:border-sky-500 transition-all"
                            >
                            <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                        
                        <div class="w-1/3">
                            <select v-model="formFiltros.estado" class="w-full border border-gray-200 rounded-xl py-2 px-4 focus:ring-sky-500 focus:border-sky-500 text-slate-600 bg-slate-50 cursor-pointer">
                                <option value="all">Todos los estados</option>
                                <option value="activos">Solo Activos</option>
                                <option value="inactivos">Solo Inactivos</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex gap-2 w-full sm:w-auto justify-end items-center">
                        <button v-if="formFiltros.search || formFiltros.estado !== 'all'" @click="limpiarFiltros" class="text-sm text-gray-500 hover:text-rose-500 font-bold px-4 transition-colors">
                            Limpiar Filtros
                        </button>
                        <button @click="abrirNuevo" class="bg-sky-600 text-white px-6 py-2.5 rounded-xl font-bold shadow-md hover:bg-sky-700 hover:-translate-y-0.5 transition-all active:scale-95 whitespace-nowrap">
                            + Nueva Categoría
                        </button>
                    </div>
                </div>

                <div class="bg-white shadow-xl rounded-2xl border border-gray-100 p-4">
                    <div class="overflow-visible">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="bg-sky-50 text-sky-900 uppercase text-xs font-black border-b border-sky-100">
                                    <th class="p-4 rounded-l-xl">ID</th>
                                    <th class="p-4">Nombre de la Categoría</th>
                                    <th class="p-4 text-center">Estado</th>
                                    <th class="p-4 text-center rounded-r-xl">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-if="categorias.data.length === 0">
                                    <td colspan="4" class="p-10 text-center text-gray-400 italic bg-gray-50">
                                        No se encontraron categorías con los filtros actuales.
                                    </td>
                                </tr>

                                <tr v-for="c in categorias.data" :key="c.id" 
                                    class="bg-white hover:bg-sky-50 transition-all duration-200 group"
                                    :class="{'opacity-50 grayscale bg-slate-50': !c.estado}">
                                    
                                    <td class="p-4 font-mono font-bold text-sky-800">#{{ c.id }}</td>
                                    <td class="p-4 font-bold text-slate-700 uppercase">{{ c.nombreCategoria }}</td>
                                    
                                    <td class="p-4 text-center">
                                        <span :class="c.estado ? 'text-emerald-600 bg-emerald-50' : 'text-rose-600 bg-rose-50'" class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest shadow-sm">
                                            {{ c.estado ? 'Activa' : 'Baja' }}
                                        </span>
                                    </td>
                                    
                                    <td class="p-4 text-center relative">
                                        <button @click.stop="toggleMenu(c.id)" class="p-2 rounded-full text-slate-400 hover:text-sky-600 hover:bg-sky-100 transition-colors focus:outline-none">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg>
                                        </button>

                                        <div v-if="menuAbierto === c.id" class="absolute right-10 top-10 w-48 bg-white rounded-xl shadow-2xl border border-slate-100 z-40 py-2 animate-in fade-in zoom-in-95 duration-150">
                                            <button @click="abrirDetalle(c)" class="w-full text-left px-4 py-2.5 text-xs font-bold text-sky-600 hover:bg-sky-50 flex items-center gap-3 transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                                Ver Detalles
                                            </button>

                                            <button @click="abrirEditar(c)" class="w-full text-left px-4 py-2.5 text-xs font-bold text-amber-600 hover:bg-amber-50 flex items-center gap-3 transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                                Editar Datos
                                            </button>

                                            <div class="border-t border-slate-100 my-1"></div>

                                            <button @click="toggleEstado(c)" 
                                                class="w-full text-left px-4 py-2.5 text-xs font-bold flex items-center gap-3 transition-colors"
                                                :class="c.estado ? 'text-rose-600 hover:bg-rose-50' : 'text-emerald-600 hover:bg-emerald-50'">
                                                <svg v-if="c.estado" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                                                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                {{ c.estado ? 'Dar de Baja' : 'Activar Categoría' }}
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="categorias.links && categorias.data.length > 0" class="p-4 bg-slate-50 border-t border-gray-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                        <span class="text-sm text-gray-500 font-medium">
                            Mostrando {{ categorias.from }} a {{ categorias.to }} de {{ categorias.total }} resultados
                        </span>
                        <div class="flex flex-wrap justify-center gap-1">
                            <component
                                :is="link.url ? 'a' : 'span'"
                                v-for="(link, index) in categorias.links"
                                :key="index"
                                :href="link.url"
                                @click.prevent="link.url ? router.get(link.url, formFiltros, { preserveState: true }) : null"
                                v-html="link.label.replace('Previous', 'Anterior').replace('Next', 'Siguiente')"
                                class="px-3 py-1.5 text-sm rounded-lg transition-colors border"
                                :class="[
                                    link.active ? 'bg-sky-600 text-white font-bold border-sky-600 shadow-sm' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-100',
                                    !link.url ? 'opacity-50 cursor-not-allowed bg-gray-50' : 'cursor-pointer'
                                ]"
                            />
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <ModalCategoria 
            :mostrar="verModal" 
            :categoria="seleccionado" 
            @cerrar="cerrarModal" 
        />

        <DetalleCategoria 
            :mostrar="verDetalle" 
            :categoria="seleccionado" 
            @cerrar="verDetalle = false" 
        />
    </AuthenticatedLayout>
</template>