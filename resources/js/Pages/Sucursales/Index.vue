<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ModalSucursal from './Componentes/ModalSucursal.vue'; 
import DetalleSucursal from './Componentes/DetalleSucursal.vue'; 
import { Head, router } from '@inertiajs/vue3';
import { ref, watch, reactive } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({ 
    sucursales: Object,
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
        router.get(route('sucursales.index'), value, {
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

const abrirEditar = (s) => { 
    cerrarMenu();
    seleccionado.value = s; 
    verModal.value = true; 
};

const abrirDetalle = (s) => { 
    cerrarMenu();
    seleccionado.value = s; 
    verDetalle.value = true; 
};

const cerrarModal = () => {
    verModal.value = false;
    seleccionado.value = null;
};

const toggleEstado = (s) => {
    cerrarMenu();
    const accion = s.estado ? 'desactivar' : 'activar';
    const resultado = s.estado ? 'desactivada' : 'activada';
    const colorConfirm = s.estado ? '#ef4444' : '#10b981';

    Swal.fire({
        title: `¿${accion.toUpperCase()} sucursal?`,
        text: `La sucursal "${s.nombre}" cambiará su estado a ${resultado}.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: colorConfirm,
        cancelButtonColor: '#6b7280',
        confirmButtonText: `Sí, ${accion}`,
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            router.patch(route('sucursales.status', s.id), {}, {
                onSuccess: () => Swal.fire({
                    title: '¡Listo!',
                    text: `Sucursal ${resultado} correctamente.`,
                    icon: 'success',
                    confirmButtonColor: '#0284c7'
                })
            });
        }
    });
};
</script>

<template>
    <Head title="Sucursales" />

    <AuthenticatedLayout>
        <div v-if="menuAbierto" @click="cerrarMenu" class="fixed inset-0 z-30"></div>

        <template #header>Gestión de Sucursales</template>

        <div class="py-6 px-4 sm:px-6 lg:px-8 bg-slate-50 min-h-screen">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end mb-8 gap-4">
                <div>
                    <h1 class="text-2xl font-black text-slate-800 uppercase tracking-tight">Directorio de Sucursales</h1>
                    <div class="h-1 w-12 bg-sky-500 mt-1"></div>
                </div>
                <button 
                    @click="abrirNuevo"
                    class="bg-sky-600 hover:bg-sky-700 text-white font-bold py-2.5 px-5 rounded-xl shadow-sm shadow-sky-600/30 transition-all flex items-center justify-center gap-2 w-full sm:w-auto"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6zM16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z" /></svg>
                    Nueva Sucursal
                </button>
            </div>

            <div class="mb-6 bg-white p-4 rounded-2xl shadow-sm border border-slate-100 flex flex-col gap-4">
                <div class="flex flex-col sm:flex-row gap-4 w-full">
                    <div class="relative w-full sm:w-2/3">
                        <input 
                            v-model="formFiltros.search" 
                            type="text" 
                            placeholder="Buscar por Nombre, Dirección o ID..." 
                            class="w-full pl-10 pr-4 py-2.5 border border-slate-200 rounded-xl focus:ring-sky-500 focus:border-sky-500 transition-all font-medium text-sm"
                        >
                        <svg class="w-5 h-5 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    
                    <div class="w-full sm:w-1/3">
                        <select v-model="formFiltros.estado" class="w-full border border-slate-200 rounded-xl py-2.5 px-4 focus:ring-sky-500 focus:border-sky-500 text-slate-600 bg-slate-50 cursor-pointer font-medium text-sm">
                            <option value="all">Todos los estados</option>
                            <option value="activas">Solo Activas</option>
                            <option value="inactivas">Solo Inactivas</option>
                        </select>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button v-if="formFiltros.search || formFiltros.estado !== 'all'" @click="limpiarFiltros" class="text-sm text-slate-500 hover:text-rose-500 font-bold px-4 transition-colors">
                        Limpiar Filtros
                    </button>
                </div>
            </div>

            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-visible">
                <div class="overflow-visible">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100 text-xs tracking-widest text-slate-400 uppercase">
                                <th class="p-4 font-black rounded-tl-3xl">ID</th>
                                <th class="p-4 font-black">Sucursal</th>
                                <th class="p-4 font-black">Tipo</th>
                                <th class="p-4 font-black text-center">Estado</th>
                                <th class="p-4 font-black text-center rounded-tr-3xl">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="sucursales.data.length === 0">
                                <td colspan="5" class="p-8 text-center text-slate-400 font-bold">No se encontraron sucursales con esos filtros.</td>
                            </tr>
                            <tr v-for="s in sucursales.data" :key="s.id" class="hover:bg-slate-50/50 transition-colors group" :class="{'opacity-50 grayscale bg-slate-50': !s.estado}">
                                <td class="p-4 font-mono font-bold text-sky-800">#{{ s.id }}</td>
                                <td class="p-4 font-bold text-slate-800 uppercase">{{ s.nombre }}</td>
                                <td class="p-4 text-xs font-black text-sky-600 uppercase">{{ s.tipo.replace('_', ' ') }}</td>
                                
                                <td class="p-4 text-center">
                                    <span :class="s.estado ? 'text-emerald-600 bg-emerald-50' : 'text-rose-600 bg-rose-50'" class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest">
                                        {{ s.estado ? 'Activa' : 'Inactiva' }}
                                    </span>
                                </td>
                                
                                <td class="p-4 text-center relative">
                                    <button @click.stop="toggleMenu(s.id)" class="p-2 rounded-full text-slate-400 hover:text-sky-600 hover:bg-sky-100 transition-colors focus:outline-none">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg>
                                    </button>

                                    <div v-if="menuAbierto === s.id" class="absolute right-10 top-10 w-48 bg-white rounded-xl shadow-2xl border border-slate-100 z-40 py-2 animate-in fade-in zoom-in-95 duration-150">
                                        <button @click="abrirDetalle(s)" class="w-full text-left px-4 py-2.5 text-xs font-bold text-sky-600 hover:bg-sky-50 flex items-center gap-3 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                            Ver Detalles
                                        </button>

                                        <button @click="abrirEditar(s)" class="w-full text-left px-4 py-2.5 text-xs font-bold text-amber-600 hover:bg-amber-50 flex items-center gap-3 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                            Editar Datos
                                        </button>

                                        <div class="border-t border-slate-100 my-1"></div>

                                        <button @click="toggleEstado(s)" 
                                            class="w-full text-left px-4 py-2.5 text-xs font-bold flex items-center gap-3 transition-colors"
                                            :class="s.estado ? 'text-rose-600 hover:bg-rose-50' : 'text-emerald-600 hover:bg-emerald-50'">
                                            <svg v-if="s.estado" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                                            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            {{ s.estado ? 'Dar de Baja' : 'Activar Sucursal' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="sucursales.links && sucursales.data.length > 0" class="p-4 bg-slate-50 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <span class="text-sm text-slate-500 font-medium">
                        Mostrando {{ sucursales.from }} a {{ sucursales.to }} de {{ sucursales.total }} sucursales
                    </span>
                    <div class="flex flex-wrap justify-center gap-1">
                        <component
                            :is="link.url ? 'a' : 'span'"
                            v-for="(link, index) in sucursales.links"
                            :key="index"
                            :href="link.url"
                            @click.prevent="link.url ? router.get(link.url, formFiltros, { preserveState: true }) : null"
                            v-html="link.label.replace('Previous', 'Anterior').replace('Next', 'Siguiente')"
                            class="px-3 py-1.5 text-sm rounded-lg transition-colors border"
                            :class="[
                                link.active ? 'bg-sky-600 text-white font-bold border-sky-600 shadow-sm' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-100',
                                !link.url ? 'opacity-50 cursor-not-allowed bg-slate-50' : 'cursor-pointer'
                            ]"
                        />
                    </div>
                </div>
            </div>
        </div>

        <ModalSucursal :mostrar="verModal" :sucursal="seleccionado" @cerrar="cerrarModal" />
        <DetalleSucursal :mostrar="verDetalle" :sucursal="seleccionado" @cerrar="verDetalle = false" />
    </AuthenticatedLayout>
</template>