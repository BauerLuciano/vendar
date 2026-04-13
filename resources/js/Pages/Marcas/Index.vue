<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ModalMarca from './Componentes/ModalMarca.vue'; 
import DetalleMarca from './Componentes/DetalleMarca.vue'; 
import { Head, router } from '@inertiajs/vue3';
import { ref, watch, reactive } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({ 
    marcas: Object,
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
        router.get(route('marcas.index'), value, {
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

const abrirEditar = (m) => { 
    cerrarMenu();
    seleccionado.value = m; 
    verModal.value = true; 
};

const abrirDetalle = (m) => { 
    cerrarMenu();
    seleccionado.value = m; 
    verDetalle.value = true; 
};

const cerrarModal = () => {
    verModal.value = false;
    seleccionado.value = null;
};

const toggleEstado = (m) => {
    cerrarMenu();
    const accion = m.estado ? 'desactivar' : 'activar';
    const resultado = m.estado ? 'desactivada' : 'activada';
    const colorConfirm = m.estado ? '#ef4444' : '#10b981';

    Swal.fire({
        title: `¿${accion.toUpperCase()} marca?`,
        text: `La marca "${m.nombreMarca}" cambiará su estado a ${resultado}.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: colorConfirm,
        confirmButtonText: `Sí, ${accion}`,
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            router.patch(route('marcas.status', m.id), {}, {
                onSuccess: () => {
                    Swal.fire({
                        title: '¡Listo!',
                        text: `Marca ${resultado} correctamente.`,
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
    <Head title="Gestión de Marcas" />

    <AuthenticatedLayout>
        <div v-if="menuAbierto" @click="cerrarMenu" class="fixed inset-0 z-30"></div>

        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Control de Marcas</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <div class="flex flex-col sm:flex-row justify-between items-center mb-6 bg-white p-4 rounded-2xl shadow-sm border border-gray-100 gap-4">
                    <div class="flex gap-4 w-full sm:w-1/2">
                        <div class="relative w-2/3">
                            <input 
                                v-model="formFiltros.search" 
                                type="text" 
                                placeholder="Buscar marca..." 
                                class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-xl focus:ring-sky-500 focus:border-sky-500 transition-all"
                            >
                            <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                        
                        <div class="w-1/3">
                            <select v-model="formFiltros.estado" class="w-full border border-gray-200 rounded-xl py-2 px-4 focus:ring-sky-500 focus:border-sky-500 text-slate-600 bg-slate-50 cursor-pointer">
                                <option value="all">Todos los estados</option>
                                <option value="activos">Solo Activas</option>
                                <option value="inactivos">Solo Inactivas</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex gap-2 w-full sm:w-auto justify-end items-center">
                        <button v-if="formFiltros.search || formFiltros.estado !== 'all'" @click="limpiarFiltros" class="text-sm text-gray-500 hover:text-rose-500 font-bold px-4 transition-colors">
                            Limpiar Filtros
                        </button>
                        <button @click="abrirNuevo" class="bg-sky-600 text-white px-6 py-2.5 rounded-lg font-bold shadow-lg hover:bg-sky-700 transition-all active:scale-95 whitespace-nowrap">
                            + Nueva Marca
                        </button>
                    </div>
                </div>

                <div class="bg-white shadow-xl rounded-2xl border border-gray-100 p-4">
                    <div class="overflow-visible">
                        <table class="w-full text-left border-separate border-spacing-y-2">
                            <thead>
                                <tr class="bg-sky-50 text-sky-900 uppercase text-xs font-black border-b border-sky-100">
                                    <th class="p-4 rounded-l-xl">ID</th>
                                    <th class="p-4 text-center">Logo</th> 
                                    <th class="p-4">Nombre de la Marca</th>
                                    <th class="p-4 text-center">Estado</th>
                                    <th class="p-4 text-center rounded-r-xl">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-if="marcas.data.length === 0">
                                    <td colspan="5" class="p-10 text-center text-gray-400 italic bg-gray-50 rounded-xl">
                                        No se encontraron marcas con los filtros actuales.
                                    </td>
                                </tr>

                                <tr v-for="m in marcas.data" :key="m.id" 
                                    class="bg-white hover:bg-sky-50 transition-all duration-200 group shadow-sm"
                                    :class="{'opacity-50 grayscale': !m.estado}">
                                    
                                    <td class="p-4 font-mono font-bold text-sky-800">#{{ m.id }}</td>
                                    
                                    <td class="p-4">
                                        <div class="flex justify-center items-center">
                                            <img v-if="m.url_imagen" :src="m.url_imagen" class="w-10 h-10 object-contain rounded-lg border shadow-sm bg-white p-1">
                                            <div v-else class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center text-gray-300">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="p-4 font-bold text-slate-700 uppercase tracking-tight">{{ m.nombreMarca }}</td>
                                    
                                    <td class="p-4 text-center">
                                        <span :class="m.estado ? 'text-emerald-600 bg-emerald-50' : 'text-rose-600 bg-rose-50'" class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest shadow-sm">
                                            {{ m.estado ? 'Activa' : 'Baja' }}
                                        </span>
                                    </td>
                                    
                                    <td class="p-4 text-center relative">
                                        <button @click.stop="toggleMenu(m.id)" class="p-2 rounded-full text-slate-400 hover:text-sky-600 hover:bg-sky-100 transition-colors focus:outline-none">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg>
                                        </button>

                                        <div v-if="menuAbierto === m.id" class="absolute right-10 top-10 w-48 bg-white rounded-xl shadow-2xl border border-slate-100 z-40 py-2 animate-in fade-in zoom-in-95 duration-150">
                                            <button @click="abrirDetalle(m)" class="w-full text-left px-4 py-2.5 text-xs font-bold text-sky-600 hover:bg-sky-50 flex items-center gap-3 transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                                Ver Detalles
                                            </button>

                                            <button @click="abrirEditar(m)" class="w-full text-left px-4 py-2.5 text-xs font-bold text-amber-600 hover:bg-amber-50 flex items-center gap-3 transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                                Editar Datos
                                            </button>

                                            <div class="border-t border-slate-100 my-1"></div>

                                            <button @click="toggleEstado(m)" 
                                                class="w-full text-left px-4 py-2.5 text-xs font-bold flex items-center gap-3 transition-colors"
                                                :class="m.estado ? 'text-rose-600 hover:bg-rose-50' : 'text-emerald-600 hover:bg-emerald-50'">
                                                <svg v-if="m.estado" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                                                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                {{ m.estado ? 'Dar de Baja' : 'Activar Marca' }}
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="marcas.links && marcas.data.length > 0" class="p-4 bg-slate-50 border-t border-gray-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                        <span class="text-sm text-gray-500 font-medium">
                            Mostrando {{ marcas.from }} a {{ marcas.to }} de {{ marcas.total }} resultados
                        </span>
                        <div class="flex flex-wrap justify-center gap-1">
                            <component
                                :is="link.url ? 'a' : 'span'"
                                v-for="(link, index) in marcas.links"
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

        <ModalMarca 
            :mostrar="verModal" 
            :marca="seleccionado" 
            @cerrar="cerrarModal" 
        />

        <DetalleMarca 
            :mostrar="verDetalle" 
            :marca="seleccionado" 
            @cerrar="verDetalle = false" 
        />
    </AuthenticatedLayout>
</template>