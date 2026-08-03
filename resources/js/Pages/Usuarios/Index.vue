<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AlertaAyuda from '@/Components/AlertaAyuda.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, watch, reactive, computed } from 'vue';
import Swal from 'sweetalert2';
import ModalUsuario from './Componentes/ModalUsuario.vue';
import DetalleUsuario from './Componentes/DetalleUsuario.vue';
import DropdownAcciones from '@/Components/DropdownAcciones.vue';

const props = defineProps({
    usuarios: Object,
    roles: Array,
    sucursales: Array,
    filtros: Object
});

const menuAbierto = ref(null);
const mostrarModal = ref(false);
const mostrarDetalle = ref(false);
const usuarioSeleccionado = ref(null);

const page = usePage();

const etiquetaRol = (rol) => rol === 'SuperAdmin' ? 'Dueño' : rol;

const formFiltros = reactive({
    search: props.filtros?.search || '',
    sucursal_id: props.filtros?.sucursal_id || 'all',
    rol: props.filtros?.rol || 'all',
    estado: props.filtros?.estado || 'todos'
});

let timeout = null;

watch(formFiltros, (value) => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(route('usuarios.index'), value, {
            preserveState: true,
            replace: true
        });
    }, 300);
});

const limpiarFiltros = () => {
    formFiltros.search = '';
    formFiltros.sucursal_id = 'all';
    formFiltros.rol = 'all';
    formFiltros.estado = 'todos';
};

const toggleMenu = (id) => {
    menuAbierto.value = menuAbierto.value === id ? null : id;
};

const cerrarMenu = () => {
    menuAbierto.value = null;
};

const usuarioActualId = computed(() => page.props.auth?.user?.id ?? null);

const abrirNuevo = () => {
    cerrarMenu();
    usuarioSeleccionado.value = null;
    mostrarModal.value = true;
};

const editarUsuario = (usuario) => {
    cerrarMenu();
    usuarioSeleccionado.value = usuario;
    mostrarModal.value = true;
};

const verUsuario = (usuario) => {
    cerrarMenu();
    usuarioSeleccionado.value = usuario;
    mostrarDetalle.value = true;
};

const eliminarUsuario = (usuario) => {
    cerrarMenu();
    Swal.fire({
        title: '¿Dar de baja al usuario?',
        text: `Se desactivará el acceso de ${usuario.name}. Su historial y registros se conservan y podrá reactivarse.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Sí, dar de baja',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('usuarios.destroy', usuario.id), {
                onSuccess: () => Swal.fire('Dado de baja', 'El usuario fue desactivado. Su historial se conserva.', 'success'),
                onError: (err) => Swal.fire('Error', err.error || 'No se pudo dar de baja al usuario', 'error')
            });
        }
    });
};

const reactivarUsuario = (usuario) => {
    cerrarMenu();
    Swal.fire({
        title: '¿Reactivar al usuario?',
        text: `Se restablecerá el acceso de ${usuario.name}.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0284c7',
        confirmButtonText: 'Sí, reactivar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('usuarios.restaurar', usuario.id), {}, {
                onSuccess: () => Swal.fire('Reactivado', 'El usuario puede volver a iniciar sesión.', 'success'),
                onError: (err) => Swal.fire('Error', err.error || 'No se pudo reactivar al usuario', 'error')
            });
        }
    });
};
</script>

<template>
    <Head title="Directorio de Equipo" />

    <AuthenticatedLayout>

        <div class="py-8 px-6 sm:px-10 bg-slate-50 min-h-screen font-sans">
            
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end mb-8 gap-4">
                <div>
                    <h1 class="text-2xl font-black text-slate-800 uppercase tracking-tight">Directorio de Equipo</h1>
                    <div class="h-1 w-16 bg-[#0284c7] mt-2"></div>
                </div>
                <button @click="abrirNuevo" class="bg-[#0284c7] text-white px-5 py-2.5 rounded-xl text-sm font-semibold shadow-lg hover:bg-sky-700 transition-colors flex items-center justify-center gap-2 w-full sm:w-auto">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                        <path d="M11 5a3 3 0 11-6 0 3 3 0 016 0zM2.615 16.428a1.224 1.224 0 01-.569-1.175 6.002 6.002 0 0111.908 0c.058.467-.172.92-.57 1.174A9.953 9.953 0 018 18a9.953 9.953 0 01-5.385-1.572zM16.25 5.75a.75.75 0 00-1.5 0v2h-2a.75.75 0 000 1.5h2v2a.75.75 0 001.5 0v-2h2a.75.75 0 000-1.5h-2v-2z" />
                    </svg>
                    Nuevo Usuario
                </button>
            </div>

            <div class="mb-6 bg-white p-4 rounded-2xl shadow-sm border border-slate-100 flex flex-col gap-4">
                <div class="flex flex-col sm:flex-row gap-4 w-full">
                    <div class="relative w-full sm:w-1/2">
                        <input 
                            v-model="formFiltros.search" 
                            type="text" 
                            placeholder="Buscar por Nombre, Email o ID..." 
                            class="w-full pl-10 pr-4 py-2.5 border border-slate-200 rounded-xl focus:ring-sky-500 focus:border-sky-500 transition-all font-medium text-sm"
                        >
                        <svg class="w-5 h-5 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    
                    <div class="w-full sm:w-1/4">
                        <select v-model="formFiltros.rol" class="w-full border border-slate-200 rounded-xl py-2.5 px-4 focus:ring-sky-500 focus:border-sky-500 text-slate-600 bg-slate-50 cursor-pointer font-medium text-sm">
                            <option value="all">Todos los roles</option>
                            <option v-for="r in roles" :key="r.id" :value="r.name">{{ etiquetaRol(r.name) }}</option>
                        </select>
                    </div>

                    <div class="w-full sm:w-1/4">
                        <select v-model="formFiltros.sucursal_id" class="w-full border border-slate-200 rounded-xl py-2.5 px-4 focus:ring-sky-500 focus:border-sky-500 text-slate-600 bg-slate-50 cursor-pointer font-medium text-sm">
                            <option value="all">Todas las sucursales</option>
                            <option v-for="suc in sucursales" :key="suc.id" :value="suc.id">{{ suc.nombre }}</option>
                        </select>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-4 w-full items-center justify-between">
                    <div class="w-full sm:w-1/4">
                        <select v-model="formFiltros.estado" class="w-full border border-slate-200 rounded-xl py-2.5 px-4 focus:ring-sky-500 focus:border-sky-500 text-slate-600 bg-slate-50 cursor-pointer font-medium text-sm">
                            <option value="todos">Todos los estados</option>
                            <option value="activos">Activos</option>
                            <option value="inactivos">Inactivos</option>
                        </select>
                    </div>
                    <button v-if="formFiltros.search || formFiltros.rol !== 'all' || formFiltros.sucursal_id !== 'all' || formFiltros.estado !== 'todos'" @click="limpiarFiltros" class="text-sm text-slate-500 hover:text-rose-500 font-bold px-4 transition-colors">
                        Limpiar Filtros
                    </button>
                </div>
            </div>

            <div class="bg-white shadow-xl rounded-2xl border border-slate-100 p-4 overflow-visible">
                <div class="overflow-visible">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100 text-slate-400 uppercase text-[10px] font-black tracking-widest">
                                <th class="p-4 w-16 text-center rounded-tl-xl">ID</th>
                                <th class="p-4">Usuario</th>
                                <th class="p-4">Contacto</th>
                                <th class="p-4 text-center">Sucursal</th>
                                <th class="p-4 text-center">Rol Asignado</th>
                                <th class="p-4 text-center rounded-tr-xl">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="usuarios.data.length === 0">
                                <td colspan="6" class="p-10">
                                    <AlertaAyuda>No se encontraron usuarios.</AlertaAyuda>
                                </td>
                            </tr>
                            <tr v-for="user in usuarios.data" :key="user.id" class="hover:bg-slate-50 transition-colors group">
                                <td class="p-4 text-center text-xs text-slate-400 font-bold">#{{ user.id }}</td>
                                
                                <td class="p-4">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-slate-800 text-sm" :class="user.deleted_at ? 'line-through opacity-60' : ''">{{ user.name }}</span>
                                        <span class="text-[10px] font-bold uppercase tracking-widest mt-0.5"
                                            :class="user.deleted_at ? 'text-slate-400' : 'text-emerald-500'">
                                            {{ user.deleted_at ? 'Inactivo' : 'Activo' }}
                                        </span>
                                    </div>
                                </td>
                                
                                <td class="p-4">
                                    <div class="flex flex-col space-y-1">
                                        <span class="text-xs text-slate-500 font-medium tracking-wide">MAIL: <span class="font-bold text-slate-700">{{ user.email }}</span></span>
                                    </div>
                                </td>
                                
                                <td class="p-4 text-center">
                                    <div class="flex flex-wrap gap-1 justify-center">
                                        <template v-if="user.sucursales?.length">
                                            <span v-for="suc in user.sucursales" :key="suc.id"
                                                class="text-[10px] font-bold px-2 py-0.5 rounded-full"
                                                :class="suc.id === user.branch_id ? 'bg-sky-100 text-sky-700' : 'bg-slate-100 text-slate-500'">
                                                {{ suc.nombre }}
                                            </span>
                                        </template>
                                        <span v-else class="text-sm font-bold text-slate-600">{{ user.branch?.nombre || '-' }}</span>
                                    </div>
                                </td>
                                
                                <td class="p-4 text-center">
                                    <span class="text-xs font-black text-sky-700 bg-sky-50 px-3 py-1 rounded-lg uppercase tracking-wider">
                                        {{ user.roles?.length > 0 ? etiquetaRol(user.roles[0].name) : '-' }}
                                    </span>
                                </td>
                                
                                <td class="p-4 text-center">
                                    <DropdownAcciones :abierto="menuAbierto === user.id" @close="menuAbierto = null">
                                        <template #trigger>
                                            <button @click.stop="toggleMenu(user.id)" class="p-2 rounded-full text-slate-400 hover:text-sky-600 hover:bg-sky-100 transition-colors focus:outline-none">
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg>
                                            </button>
                                        </template>

                                        <button @click="verUsuario(user)" class="w-full text-left px-4 py-2.5 text-xs font-bold text-sky-600 hover:bg-sky-50 flex items-center gap-3 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                            Ver Detalles
                                        </button>

                                        <button @click="editarUsuario(user)" class="w-full text-left px-4 py-2.5 text-xs font-bold text-amber-600 hover:bg-amber-50 flex items-center gap-3 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                            Editar Usuario
                                        </button>

                                        <div class="border-t border-slate-100 my-1"></div>

                                        <button v-if="!user.deleted_at && user.id !== usuarioActualId" @click="eliminarUsuario(user)" class="w-full text-left px-4 py-2.5 text-xs font-bold text-rose-600 hover:bg-rose-50 flex items-center gap-3 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                            Dar de baja
                                        </button>

                                        <button v-if="user.deleted_at" @click="reactivarUsuario(user)" class="w-full text-left px-4 py-2.5 text-xs font-bold text-emerald-600 hover:bg-emerald-50 flex items-center gap-3 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                                            Reactivar
                                        </button>
                                    </DropdownAcciones>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="usuarios.links && usuarios.data.length > 0" class="p-4 bg-slate-50 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4 rounded-b-xl">
                    <span class="text-sm text-slate-500 font-medium">
                        Mostrando {{ usuarios.from }} a {{ usuarios.to }} de {{ usuarios.total }} usuarios
                    </span>
                    <div class="flex flex-wrap justify-center gap-1">
                        <component
                            :is="link.url ? 'a' : 'span'"
                            v-for="(link, index) in usuarios.links"
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

        <ModalUsuario 
            :mostrar="mostrarModal" 
            :usuario="usuarioSeleccionado" 
            :roles="roles" 
            :sucursales="sucursales" 
            @cerrar="mostrarModal = false" 
        />
        <DetalleUsuario 
            :mostrar="mostrarDetalle" 
            :usuario="usuarioSeleccionado" 
            @cerrar="mostrarDetalle = false" 
        />
    </AuthenticatedLayout>
</template>