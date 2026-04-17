<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';
import ModalRol from './Componentes/ModalRol.vue';
import DetalleRol from './Componentes/DetalleRol.vue';
import ModalPermiso from './Componentes/ModalPermiso.vue';
import DetallePermiso from './Componentes/DetallePermiso.vue';

const props = defineProps({
    roles: Array,
    permisos: Array
});

// Pestaña Activa
const tabActiva = ref('roles'); // 'roles' o 'permisos'

// Menús de acciones (3 puntitos)
const menuAbierto = ref(null);

const toggleMenu = (id) => {
    menuAbierto.value = menuAbierto.value === id ? null : id;
};

const cerrarMenu = () => {
    menuAbierto.value = null;
};

// ================= ESTADOS Y LÓGICA DE ROLES =================
const mostrarModalRol = ref(false);
const mostrarDetalleRol = ref(false);
const rolSeleccionado = ref(null);
const busquedaRol = ref('');

const rolesFiltrados = computed(() => {
    if (!busquedaRol.value) return props.roles;
    return props.roles.filter(r => r.name.toLowerCase().includes(busquedaRol.value.toLowerCase()));
});

const abrirNuevoRol = () => { 
    rolSeleccionado.value = null; 
    mostrarModalRol.value = true; 
};

const editarRol = (rol) => { 
    cerrarMenu();
    rolSeleccionado.value = rol; 
    mostrarModalRol.value = true; 
};

const verRol = (rol) => { 
    cerrarMenu();
    rolSeleccionado.value = rol; 
    mostrarDetalleRol.value = true; 
};

const eliminarRol = (rol) => {
    cerrarMenu();
    Swal.fire({
        title: '¿Eliminar Rol?',
        text: `Se borrará el rol "${rol.name}". Esto puede afectar a los usuarios que lo tengan asignado.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('roles.destroy', rol.id), {
                onSuccess: () => Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Rol eliminado', showConfirmButton: false, timer: 3000 }),
                onError: (err) => Swal.fire('Acción denegada', err.error, 'error')
            });
        }
    });
};

// ================= ESTADOS Y LÓGICA DE PERMISOS =================
const mostrarModalPermiso = ref(false);
const mostrarDetallePermiso = ref(false);
const permisoSeleccionado = ref(null);
const busquedaPermiso = ref('');

const permisosFiltrados = computed(() => {
    if (!busquedaPermiso.value) return props.permisos;
    return props.permisos.filter(p => p.name.toLowerCase().includes(busquedaPermiso.value.toLowerCase()));
});

const abrirNuevoPermiso = () => { 
    permisoSeleccionado.value = null; 
    mostrarModalPermiso.value = true; 
};

const editarPermiso = (permiso) => {
    cerrarMenu();
    permisoSeleccionado.value = permiso;
    mostrarModalPermiso.value = true;
};

const verPermiso = (permiso) => { 
    cerrarMenu();
    permisoSeleccionado.value = permiso; 
    mostrarDetallePermiso.value = true; 
};
</script>

<template>
    <Head title="Control de Seguridad" />

    <AuthenticatedLayout>
        <!-- Overlay para cerrar menús: z-40 para quedar debajo del dropdown (z-50) pero sobre el resto -->
        <div v-if="menuAbierto" @click="cerrarMenu" class="fixed inset-0 z-40"></div>

        <div class="py-6 px-4 sm:px-6 lg:px-8 bg-slate-50 min-h-screen">
            
            <div class="mb-8">
                <h1 class="text-2xl font-black text-slate-800 uppercase tracking-tight">Control de Seguridad</h1>
                <div class="h-1 w-16 bg-sky-500 mt-2"></div>
                <p class="text-sm text-slate-500 mt-2 font-medium">Gestioná los perfiles y niveles de acceso de tu equipo.</p>
            </div>

            <div class="flex gap-4 border-b border-slate-200 mb-6">
                <button 
                    @click="tabActiva = 'roles'" 
                    class="pb-3 text-sm font-bold uppercase tracking-widest transition-colors border-b-2"
                    :class="tabActiva === 'roles' ? 'border-sky-500 text-sky-600' : 'border-transparent text-slate-400 hover:text-slate-600'">
                    Roles de Usuario
                </button>
                <button 
                    @click="tabActiva = 'permisos'" 
                    class="pb-3 text-sm font-bold uppercase tracking-widest transition-colors border-b-2"
                    :class="tabActiva === 'permisos' ? 'border-sky-500 text-sky-600' : 'border-transparent text-slate-400 hover:text-slate-600'">
                    Catálogo de Permisos
                </button>
            </div>

            <!-- ===================== TAB ROLES ===================== -->
            <div v-show="tabActiva === 'roles'" class="animate-in fade-in duration-300">
                
                <div class="mb-6 bg-white p-4 rounded-2xl shadow-sm border border-slate-100 flex flex-col sm:flex-row justify-between items-center gap-4">
                    <div class="relative w-full sm:w-1/2">
                        <input 
                            v-model="busquedaRol" 
                            type="text" 
                            placeholder="Buscar rol..." 
                            class="w-full pl-10 pr-4 py-2.5 border border-slate-200 rounded-xl focus:ring-sky-500 focus:border-sky-500 transition-all font-medium text-sm"
                        >
                        <svg class="w-5 h-5 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <button @click="abrirNuevoRol" class="bg-sky-600 hover:bg-sky-700 text-white font-bold py-2.5 px-5 rounded-xl shadow-sm shadow-sky-600/30 transition-all flex items-center justify-center gap-2 w-full sm:w-auto">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" /></svg>
                        Nuevo Rol
                    </button>
                </div>

                <!-- Sin overflow-hidden para que el dropdown no quede cortado -->
                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-4">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100 text-xs uppercase tracking-widest text-slate-400">
                                <th class="p-4 font-black rounded-tl-3xl">Rol / Perfil</th>
                                <th class="p-4 font-black text-center">Permisos Asignados</th>
                                <th class="p-4 font-black text-center rounded-tr-3xl">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="rolesFiltrados.length === 0">
                                <td colspan="3" class="p-8 text-center text-slate-400 font-bold">No se encontraron roles.</td>
                            </tr>
                            <tr v-for="(rol, index) in rolesFiltrados" :key="rol.id" class="hover:bg-slate-50/50 transition-colors">
                                
                                <td class="p-4">
                                    <div class="font-bold text-slate-800">{{ rol.name }}</div>
                                    <div v-if="['Administrador Global', 'SuperAdmin'].includes(rol.name)" class="text-[10px] font-black text-rose-500 uppercase tracking-widest mt-0.5">Rol de Sistema (Intocable)</div>
                                </td>
                                
                                <td class="p-4 text-center">
                                    <span class="inline-flex items-center gap-1.5 bg-emerald-50 text-emerald-700 px-3 py-1 rounded-full text-xs font-bold border border-emerald-100">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                                        {{ rol.permissions?.length || 0 }} permisos
                                    </span>
                                </td>

                                <td class="p-4 text-center relative" :style="menuAbierto === 'rol_'+rol.id ? 'z-index: 50;' : ''">
                                    <button @click.stop="toggleMenu('rol_'+rol.id)" class="p-2 rounded-full text-slate-400 hover:text-sky-600 hover:bg-sky-100 transition-colors focus:outline-none">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg>
                                    </button>

                                    <div v-if="menuAbierto === 'rol_'+rol.id" 
                                         class="absolute right-10 w-48 bg-white rounded-xl shadow-2xl border border-slate-100 z-50 py-2 animate-in fade-in zoom-in-95 duration-150"
                                         :class="index >= rolesFiltrados.length - 2 && rolesFiltrados.length > 2 ? 'bottom-8' : 'top-10'">
                                        
                                        <button @click="verRol(rol)" class="w-full text-left px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50 flex items-center gap-3 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                            Ver Detalles
                                        </button>

                                        <button @click="editarRol(rol)" class="w-full text-left px-4 py-2.5 text-xs font-bold text-sky-600 hover:bg-sky-50 flex items-center gap-3 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                            Editar Rol
                                        </button>

                                        <template v-if="!['Administrador Global', 'SuperAdmin'].includes(rol.name)">
                                            <div class="border-t border-slate-100 my-1"></div>
                                            <button @click="eliminarRol(rol)" class="w-full text-left px-4 py-2.5 text-xs font-bold text-rose-600 hover:bg-rose-50 flex items-center gap-3 transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                Eliminar Rol
                                            </button>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ===================== TAB PERMISOS ===================== -->
            <div v-show="tabActiva === 'permisos'" class="animate-in fade-in duration-300">
                
                <div class="mb-6 bg-white p-4 rounded-2xl shadow-sm border border-slate-100 flex flex-col sm:flex-row justify-between items-center gap-4">
                    <div class="relative w-full sm:w-1/2">
                        <input 
                            v-model="busquedaPermiso" 
                            type="text" 
                            placeholder="Buscar identificador de permiso..." 
                            class="w-full pl-10 pr-4 py-2.5 border border-slate-200 rounded-xl focus:ring-indigo-500 focus:border-indigo-500 transition-all font-medium text-sm"
                        >
                        <svg class="w-5 h-5 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <button @click="abrirNuevoPermiso" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-5 rounded-xl shadow-sm shadow-indigo-600/30 transition-all flex items-center justify-center gap-2 w-full sm:w-auto">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" /></svg>
                        Nuevo Permiso
                    </button>
                </div>

                <!-- Sin overflow-hidden para que el dropdown no quede cortado -->
                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-4">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100 text-xs uppercase tracking-widest text-slate-400">
                                <th class="p-4 font-black rounded-tl-3xl w-1/3">Identificador del Permiso</th>
                                <th class="p-4 font-black w-1/2">Descripción</th>
                                <th class="p-4 font-black text-center rounded-tr-3xl w-auto">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="permisosFiltrados.length === 0">
                                <td colspan="3" class="p-8 text-center text-slate-400 font-bold">No se encontraron permisos.</td>
                            </tr>
                            <tr v-for="(permiso, index) in permisosFiltrados" :key="permiso.id" class="hover:bg-slate-50/50 transition-colors">
                                
                                <td class="p-4">
                                    <span class="font-mono text-sm font-semibold text-slate-600 bg-slate-100 px-3 py-1 rounded-lg border border-slate-200">{{ permiso.name }}</span>
                                </td>

                                <td class="p-4">
                                    <p class="text-xs text-slate-500 font-medium">{{ permiso.description || 'Sin descripción asignada.' }}</p>
                                </td>

                                <td class="p-4 text-center relative" :style="menuAbierto === 'permiso_'+permiso.id ? 'z-index: 50;' : ''">
                                    <button @click.stop="toggleMenu('permiso_'+permiso.id)" class="p-2 rounded-full text-slate-400 hover:text-indigo-600 hover:bg-indigo-100 transition-colors focus:outline-none">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg>
                                    </button>

                                    <div v-if="menuAbierto === 'permiso_'+permiso.id" 
                                         class="absolute right-10 w-48 bg-white rounded-xl shadow-2xl border border-slate-100 z-50 py-2 animate-in fade-in zoom-in-95 duration-150"
                                         :class="index >= permisosFiltrados.length - 2 && permisosFiltrados.length > 2 ? 'bottom-8' : 'top-10'">
                                        
                                        <button @click="verPermiso(permiso)" class="w-full text-left px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50 flex items-center gap-3 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                            Ver Detalles
                                        </button>

                                        <button @click="editarPermiso(permiso)" class="w-full text-left px-4 py-2.5 text-xs font-bold text-indigo-600 hover:bg-indigo-50 flex items-center gap-3 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                            Editar Permiso
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <ModalRol :mostrar="mostrarModalRol" :rol="rolSeleccionado" :permisosDisponibles="permisos" @cerrar="mostrarModalRol = false" />
        <DetalleRol :mostrar="mostrarDetalleRol" :rol="rolSeleccionado" @cerrar="mostrarDetalleRol = false" />
        <ModalPermiso :mostrar="mostrarModalPermiso" :permiso="permisoSeleccionado" @cerrar="mostrarModalPermiso = false" />
        <DetallePermiso :mostrar="mostrarDetallePermiso" :permiso="permisoSeleccionado" @cerrar="mostrarDetallePermiso = false" />
    </AuthenticatedLayout>
</template>