<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Swal from 'sweetalert2';
import ModalRol from './Componentes/ModalRol.vue';
import DetalleRol from './Componentes/DetalleRol.vue';
import ModalPermiso from './Componentes/ModalPermiso.vue';
import DetallePermiso from './Componentes/DetallePermiso.vue';

const props = defineProps({
    roles: Array,
    permisos: Array
});

// Estados para Roles
const mostrarModalRol = ref(false);
const mostrarDetalleRol = ref(false);
const rolSeleccionado = ref(null);

// Estados para Permisos
const mostrarModalPermiso = ref(false);
const mostrarDetallePermiso = ref(false);
const permisoSeleccionado = ref(null);

// Acciones de Roles
const abrirNuevoRol = () => {
    rolSeleccionado.value = null;
    mostrarModalRol.value = true;
};

const editarRol = (rol) => {
    rolSeleccionado.value = rol;
    mostrarModalRol.value = true;
};

const verRol = (rol) => {
    rolSeleccionado.value = rol;
    mostrarDetalleRol.value = true;
};

const eliminarRol = (rol) => {
    Swal.fire({
        title: '¿Eliminar Rol?',
        text: `Se borrará el rol ${rol.name}.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('roles.destroy', rol.id), {
                onSuccess: () => Swal.fire('Eliminado', 'Rol borrado con éxito', 'success'),
                onError: (err) => Swal.fire('Error', err.error, 'error')
            });
        }
    });
};

// Acciones de Permisos
const abrirNuevoPermiso = () => {
    permisoSeleccionado.value = null;
    mostrarModalPermiso.value = true;
};

const verPermiso = (permiso) => {
    permisoSeleccionado.value = permiso;
    mostrarDetallePermiso.value = true;
};
</script>

<template>
    <Head title="Control de Seguridad" />

    <AuthenticatedLayout>
        <div class="py-8 px-6 sm:px-10 bg-slate-50 min-h-screen font-sans">
            
            <div class="mb-10 border-b border-slate-200 pb-4">
                <h1 class="text-xl font-bold text-slate-800 uppercase tracking-widest">Control de Seguridad</h1>
            </div>

            <div class="mb-12">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                    <h2 class="text-sm font-bold text-slate-600 uppercase tracking-widest">Listado de Roles</h2>
                    <button @click="abrirNuevoRol" class="bg-[#0284c7] text-white px-5 py-2.5 rounded-lg text-sm font-semibold shadow-sm hover:bg-sky-700 transition-colors">
                        + Nuevo Rol
                    </button>
                </div>

                <div class="bg-white shadow-sm rounded-2xl p-6 border border-slate-100">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-[#f0f8fa] text-[#0369a1] uppercase text-[11px] font-bold tracking-wider">
                                    <th class="p-4 rounded-l-xl w-1/3">Rol / Perfil</th>
                                    <th class="p-4 text-center w-1/3">Permisos Asignados</th>
                                    <th class="p-4 text-center rounded-r-xl w-1/3">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <tr v-for="rol in roles" :key="rol.id" class="hover:bg-slate-50 transition-colors">
                                    <td class="p-4 font-semibold text-slate-700 text-sm">{{ rol.name }}</td>
                                    <td class="p-4 text-center">
                                        <span class="bg-emerald-50 text-emerald-600 px-3 py-1 rounded-full text-xs font-bold border border-emerald-100">
                                            {{ rol.permissions?.length || 0 }} accesos
                                        </span>
                                    </td>
                                    <td class="p-4 flex justify-center gap-3">
                                        <button @click="verRol(rol)" class="w-8 h-8 rounded-full bg-sky-50 text-sky-500 flex items-center justify-center hover:bg-sky-100 transition-colors" title="Ver Detalle">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                        </button>
                                        <button @click="editarRol(rol)" class="w-8 h-8 rounded-full bg-amber-50 text-amber-500 flex items-center justify-center hover:bg-amber-100 transition-colors" title="Editar">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg>
                                        </button>
                                        <button @click="eliminarRol(rol)" class="w-8 h-8 rounded-full bg-rose-50 text-rose-500 flex items-center justify-center hover:bg-rose-100 transition-colors" title="Eliminar">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div>
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                    <h2 class="text-sm font-bold text-slate-600 uppercase tracking-widest">Catálogo de Permisos</h2>
                    <button @click="abrirNuevoPermiso" class="bg-slate-800 text-white px-5 py-2.5 rounded-lg text-sm font-semibold shadow-sm hover:bg-slate-900 transition-colors">
                        + Nuevo Permiso
                    </button>
                </div>

                <div class="bg-white shadow-sm rounded-2xl p-6 border border-slate-100 max-w-4xl">
                    <div class="overflow-y-auto max-h-[500px]">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-[#f0f8fa] text-[#0369a1] uppercase text-[11px] font-bold tracking-wider">
                                    <th class="p-4 rounded-l-xl w-3/4">Identificador del Permiso</th>
                                    <th class="p-4 text-center rounded-r-xl w-1/4">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <tr v-for="permiso in permisos" :key="permiso.id" class="hover:bg-slate-50 transition-colors">
                                    <td class="p-4 font-mono text-sm font-semibold text-slate-600">{{ permiso.name }}</td>
                                    <td class="p-4 flex justify-center gap-3">
                                        <button @click="verPermiso(permiso)" class="w-8 h-8 rounded-full bg-sky-50 text-sky-500 flex items-center justify-center hover:bg-sky-100 transition-colors" title="Ver Detalle">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>

        <ModalRol :mostrar="mostrarModalRol" :rol="rolSeleccionado" :permisosDisponibles="permisos" @cerrar="mostrarModalRol = false" />
        <DetalleRol :mostrar="mostrarDetalleRol" :rol="rolSeleccionado" @cerrar="mostrarDetalleRol = false" />
        <ModalPermiso :mostrar="mostrarModalPermiso" :permiso="permisoSeleccionado" @cerrar="mostrarModalPermiso = false" />
        <DetallePermiso :mostrar="mostrarDetallePermiso" :permiso="permisoSeleccionado" @cerrar="mostrarDetallePermiso = false" />
    </AuthenticatedLayout>
</template>