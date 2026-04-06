<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Swal from 'sweetalert2';
import ModalUsuario from './Componentes/ModalUsuario.vue';
import DetalleUsuario from './Componentes/DetalleUsuario.vue';

const props = defineProps({
    usuarios: Array,
    roles: Array,
    sucursales: Array
});

const mostrarModal = ref(false);
const mostrarDetalle = ref(false);
const usuarioSeleccionado = ref(null);

const abrirNuevo = () => {
    usuarioSeleccionado.value = null;
    mostrarModal.value = true;
};

const editarUsuario = (usuario) => {
    usuarioSeleccionado.value = usuario;
    mostrarModal.value = true;
};

const verUsuario = (usuario) => {
    usuarioSeleccionado.value = usuario;
    mostrarDetalle.value = true;
};

const eliminarUsuario = (usuario) => {
    Swal.fire({
        title: '¿Eliminar Usuario?',
        text: `Se borrará el acceso para ${usuario.name}.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('usuarios.destroy', usuario.id), {
                onSuccess: () => Swal.fire('Eliminado', 'Usuario borrado con éxito', 'success'),
                onError: (err) => Swal.fire('Error', err.error, 'error')
            });
        }
    });
};
</script>

<template>
    <Head title="Directorio de Equipo" />

    <AuthenticatedLayout>
        <div class="py-8 px-6 sm:px-10 bg-slate-50 min-h-screen font-sans">
            
            <div class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-2xl font-black text-slate-800 uppercase tracking-tight">Directorio de Equipo</h1>
                    <div class="h-1 w-16 bg-[#0284c7] mt-2"></div>
                </div>
                <button @click="abrirNuevo" class="bg-[#0284c7] text-white px-5 py-2.5 rounded-lg text-sm font-semibold shadow-sm hover:bg-sky-700 transition-colors flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                        <path d="M11 5a3 3 0 11-6 0 3 3 0 016 0zM2.615 16.428a1.224 1.224 0 01-.569-1.175 6.002 6.002 0 0111.908 0c.058.467-.172.92-.57 1.174A9.953 9.953 0 018 18a9.953 9.953 0 01-5.385-1.572zM16.25 5.75a.75.75 0 00-1.5 0v2h-2a.75.75 0 000 1.5h2v2a.75.75 0 001.5 0v-2h2a.75.75 0 000-1.5h-2v-2z" />
                    </svg>
                    Nuevo Usuario
                </button>
            </div>

            <div class="bg-white shadow-xl rounded-2xl border border-slate-100 p-6">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-slate-400 uppercase text-[10px] font-black tracking-widest border-b border-slate-100">
                                <th class="p-4 w-16 text-center">ID</th>
                                <th class="p-4">Usuario</th>
                                <th class="p-4">Contacto</th>
                                <th class="p-4 text-center">Sucursal</th>
                                <th class="p-4 text-center">Rol Asignado</th>
                                <th class="p-4 text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="user in usuarios" :key="user.id" class="hover:bg-slate-50 transition-colors group">
                                <td class="p-4 text-center text-xs text-slate-400 font-bold">#{{ user.id }}</td>
                                
                                <td class="p-4">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-slate-800 text-sm">{{ user.name }}</span>
                                        <span class="text-[10px] font-bold text-emerald-500 uppercase">Activo</span>
                                    </div>
                                </td>
                                
                                <td class="p-4">
                                    <div class="flex flex-col space-y-1">
                                        <span class="text-[11px] text-slate-500 font-medium tracking-wide">MAIL: {{ user.email }}</span>
                                    </div>
                                </td>
                                
                                <td class="p-4 text-center text-sm font-semibold text-slate-600">
                                    {{ user.branch?.nombre || '-' }}
                                </td>
                                
                                <td class="p-4 text-center">
                                    <span class="text-sm font-bold text-slate-700">
                                        {{ user.roles?.length > 0 ? user.roles[0].name : '-' }}
                                    </span>
                                </td>
                                
                                <td class="p-4 flex justify-center gap-2">
                                    <button @click="verUsuario(user)" class="w-8 h-8 rounded-full bg-sky-50 text-sky-500 flex items-center justify-center hover:bg-sky-100 transition-colors" title="Ver Detalle">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                    </button>
                                    <button @click="editarUsuario(user)" class="w-8 h-8 rounded-full bg-amber-50 text-amber-500 flex items-center justify-center hover:bg-amber-100 transition-colors" title="Editar">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg>
                                    </button>
                                    <button @click="eliminarUsuario(user)" class="w-8 h-8 rounded-full bg-rose-50 text-rose-500 flex items-center justify-center hover:bg-rose-100 transition-colors" title="Eliminar">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
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