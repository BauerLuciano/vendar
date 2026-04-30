<template>
    <Head title="Cajas Físicas" />

    <AuthenticatedLayout>
        <!-- Header del Módulo -->
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Módulo</p>
                    <h2 class="text-xl font-semibold text-slate-800 mt-0.5">Gestión de Cajas Físicas</h2>
                </div>
            </div>
        </template>

        <!-- Capa invisible para cerrar el dropdown al hacer click afuera o intentar scrollear -->
        <div v-if="menuAbierto" @click="cerrarMenu" @wheel.prevent @touchmove.prevent class="fixed inset-0 z-30"></div>

        <div class="py-8 bg-slate-50 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-5">
                
                <!-- Título y Botón Principal -->
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-slate-700">Listado de terminales</h3>
                        <p class="text-xs text-slate-400 mt-0.5">
                            <span class="font-medium text-slate-600">{{ cajasFiltradas.length }}</span>
                            de {{ cajas.length }} cajas
                            <span v-if="hayFiltrosActivos" class="text-blue-500 font-medium"> · filtrado</span>
                        </p>
                    </div>
                    <button @click="openModal()"
                        class="inline-flex items-center gap-2 bg-blue-600 text-white px-4 py-2.5 rounded-lg font-medium text-sm hover:bg-blue-700 active:scale-95 shadow-sm shadow-blue-600/20">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Nueva Caja
                    </button>
                </div>

                <!-- Barra de Filtros -->
                <div class="bg-white border border-slate-200 rounded-xl px-5 py-4 shadow-sm flex flex-col md:flex-row md:items-center gap-3">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 flex-1">
                        <!-- Buscador -->
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <input
                                v-model="busqueda"
                                type="text"
                                placeholder="Buscar por nombre..."
                                class="w-full pl-9 pr-3 py-2.5 text-sm border border-slate-200 rounded-lg bg-slate-50 text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            />
                        </div>

                        <!-- Filtro Sucursal -->
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                            <select
                                v-model="filtroSucursal"
                                class="w-full pl-9 pr-3 py-2.5 text-sm border border-slate-200 rounded-lg bg-slate-50 text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent appearance-none">
                                <option value="">Todas las sucursales</option>
                                <option v-for="suc in sucursales" :key="suc.id" :value="suc.id">{{ suc.nombre }}</option>
                            </select>
                        </div>

                        <!-- Filtro Estado -->
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <select
                                v-model="filtroEstado"
                                class="w-full pl-9 pr-3 py-2.5 text-sm border border-slate-200 rounded-lg bg-slate-50 text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent appearance-none">
                                <option value="">Todos los estados</option>
                                <option value="activo">Activas</option>
                                <option value="inactivo">Inactivas</option>
                            </select>
                        </div>
                    </div>

                    <button
                        v-if="hayFiltrosActivos"
                        @click="limpiarFiltros"
                        class="mt-3 md:mt-0 flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-red-50 text-red-600 border border-red-100 text-xs font-bold hover:bg-red-100 hover:text-red-700 transition-colors whitespace-nowrap w-full md:w-auto">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Limpiar Filtros
                    </button>
                </div>

                <!-- Contenedor Principal (Tabla) -->
                <div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50/80 text-[10px] font-semibold text-slate-400 uppercase tracking-widest">
                                    <th class="px-5 py-4">Nombre de la Caja</th>
                                    <th class="px-5 py-4">Sucursal</th>
                                    <th class="px-5 py-4 text-center">Estado</th>
                                    <th class="px-5 py-4 text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                
                                <!-- Mensaje Sin Resultados por Filtros -->
                                <tr v-if="cajasFiltradas.length === 0 && hayFiltrosActivos">
                                    <td colspan="4" class="py-16 text-center">
                                        <div class="flex flex-col items-center gap-3">
                                            <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center">
                                                <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                                </svg>
                                            </div>
                                            <div>
                                                <p class="text-sm font-medium text-slate-600">Sin resultados</p>
                                                <p class="text-xs text-slate-400 mt-1">Ninguna caja coincide con los filtros aplicados.</p>
                                            </div>
                                            <button @click="limpiarFiltros" class="text-xs text-blue-600 font-medium hover:underline">Limpiar filtros</button>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Mensaje Sin Cajas Registradas -->
                                <tr v-else-if="cajas.length === 0">
                                    <td colspan="4" class="py-20 text-center">
                                        <div class="flex flex-col items-center gap-3 text-slate-300">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-14 w-14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                            </svg>
                                            <div>
                                                <p class="text-sm font-medium text-slate-400">No hay cajas registradas</p>
                                                <p class="text-xs text-slate-300 mt-1">Crea tu primera caja para comenzar a operar.</p>
                                            </div>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Filas de Datos -->
                                <tr v-for="caja in cajasPaginadas" :key="caja.id" class="hover:bg-blue-50/30 group">
                                    <td class="px-5 py-4" :class="{ 'opacity-40 grayscale': !caja.estado }">
                                        <div class="font-semibold text-slate-800 text-sm">{{ caja.nombre }}</div>
                                    </td>
                                    
                                    <td class="px-5 py-4" :class="{ 'opacity-40 grayscale': !caja.estado }">
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-[10px] font-medium text-slate-500 bg-slate-100 px-2 py-0.5 rounded border border-slate-200">
                                                {{ caja.sucursal?.nombre || 'Sin Asignar' }}
                                            </span>
                                        </div>
                                    </td>
                                    
                                    <td class="px-5 py-4 text-center" :class="{ 'opacity-40 grayscale': !caja.estado }">
                                        <span :class="caja.estado 
                                                ? 'text-emerald-700 bg-emerald-50 border-emerald-200' 
                                                : 'text-slate-400 bg-slate-100 border-slate-200'"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-semibold border">
                                            <span :class="caja.estado ? 'bg-emerald-500' : 'bg-slate-400'" class="w-1.5 h-1.5 rounded-full"></span>
                                            {{ caja.estado ? 'Activa' : 'Inactiva' }}
                                        </span>
                                    </td>

                                    <td class="px-5 py-4 text-center relative opacity-100">
                                        <!-- Pasamos $event a toggleMenu para capturar las coordenadas -->
                                        <button @click.stop="toggleMenu($event, caja.id)"
                                            class="p-2 rounded-full text-slate-400 hover:text-blue-600 hover:bg-blue-100 transition-colors focus:outline-none">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                                            </svg>
                                        </button>

                                        <!-- Dropdown con posición 'fixed' -->
                                        <div v-if="menuAbierto === caja.id"
                                            class="fixed w-48 bg-white rounded-xl shadow-2xl border border-slate-200 z-[100] py-1.5 overflow-hidden"
                                            :style="{ top: menuTop, left: menuLeft }">
                                            
                                            <div class="px-4 py-2 border-b border-slate-50 mb-1">
                                                <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-widest text-left">Acciones</p>
                                            </div>

                                            <button @click="openModal(caja)"
                                                class="w-full text-left px-4 py-2.5 text-xs font-medium text-amber-600 hover:bg-amber-50 flex items-center gap-2.5">
                                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                                Editar caja
                                            </button>

                                            <!-- BAJA LÓGICA / REACTIVACIÓN (Sin opción a eliminar real) -->
                                            <button @click="toggleEstado(caja)"
                                                class="w-full text-left px-4 py-2.5 text-xs font-medium flex items-center gap-2.5"
                                                :class="caja.estado ? 'text-red-500 hover:bg-red-50' : 'text-emerald-600 hover:bg-emerald-50'">
                                                <svg v-if="caja.estado" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                                                <svg v-else class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                {{ caja.estado ? 'Dar de baja lógica' : 'Reactivar caja' }}
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Footer Paginación -->
                    <div v-if="cajasFiltradas.length > 0" class="px-5 py-4 border-t border-slate-100 bg-slate-50 flex flex-col sm:flex-row items-center justify-between gap-4">
                        <span class="text-sm text-slate-500 font-medium">
                            Mostrando 
                            {{ (paginaActual - 1) * itemsPorPagina + 1 }} 
                            a 
                            {{ Math.min(paginaActual * itemsPorPagina, cajasFiltradas.length) }} 
                            de 
                            {{ cajasFiltradas.length }} cajas
                        </span>
                        <div class="flex flex-wrap justify-center gap-1">
                            <button @click="paginaAnterior" :disabled="paginaActual === 1"
                                class="px-3 py-1.5 text-sm rounded-lg transition-colors border"
                                :class="paginaActual === 1 
                                    ? 'opacity-50 cursor-not-allowed bg-slate-50 text-slate-400 border-slate-200' 
                                    : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-100 cursor-pointer'">
                                Anterior
                            </button>
                            <button @click="paginaSiguiente" :disabled="paginaActual === totalPaginas"
                                class="px-3 py-1.5 text-sm rounded-lg transition-colors border"
                                :class="paginaActual === totalPaginas 
                                    ? 'opacity-50 cursor-not-allowed bg-slate-50 text-slate-400 border-slate-200' 
                                    : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-100 cursor-pointer'">
                                Siguiente
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Crear/Editar Caja -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden border border-slate-100">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
                    <div>
                        <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Mantenimiento</p>
                        <h3 class="text-base font-semibold text-slate-700 mt-0.5">{{ isEditing ? 'Editar Caja' : 'Nueva Caja' }}</h3>
                    </div>
                    <button @click="closeModal" class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 text-lg leading-none">✕</button>
                </div>

                <form @submit.prevent="saveCaja" class="p-6 space-y-5">
                    <div>
                        <label class="block text-[10px] font-medium uppercase text-slate-400 mb-1.5 tracking-widest">Nombre de la Caja</label>
                        <input v-model="form.nombre" type="text" placeholder="Ej: Caja Principal, Caja Ventana..." 
                            class="w-full rounded-xl border-slate-200 bg-slate-50 focus:ring-blue-500 py-2.5 text-sm font-medium text-slate-700" required>
                        <span v-if="form.errors.nombre" class="text-red-500 text-xs mt-1 block">{{ form.errors.nombre }}</span>
                    </div>

                    <div>
                        <label class="block text-[10px] font-medium uppercase text-slate-400 mb-1.5 tracking-widest">Sucursal Asignada</label>
                        <select v-model="form.sucursal_id" 
                            class="w-full rounded-xl border-slate-200 bg-slate-50 focus:ring-blue-500 py-2.5 text-sm font-medium text-slate-700" required>
                            <option value="" disabled>Seleccione una sucursal...</option>
                            <option v-for="sucursal in sucursales" :key="sucursal.id" :value="sucursal.id">
                                {{ sucursal.nombre }}
                            </option>
                        </select>
                        <span v-if="form.errors.sucursal_id" class="text-red-500 text-xs mt-1 block">{{ form.errors.sucursal_id }}</span>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="button" @click="closeModal" 
                            class="flex-1 py-3 rounded-xl text-sm font-medium text-slate-500 border border-slate-200 hover:bg-slate-50 transition-colors">
                            Cancelar
                        </button>
                        <button type="submit" :disabled="form.processing" 
                            class="flex-1 bg-blue-600 text-white py-3 rounded-xl text-sm font-medium hover:bg-blue-700 disabled:opacity-50 transition-colors shadow-sm shadow-blue-600/20">
                            {{ isEditing ? 'Guardar cambios' : 'Crear caja' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, router, Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Swal from 'sweetalert2';

const props = defineProps({
    cajas: Array,
    sucursales: Array
});

// --- Lógica del Menú Dropdown ---
const menuAbierto = ref(null);
const menuTop = ref('0px');
const menuLeft = ref('0px');

const toggleMenu = (event, id) => {
    if (menuAbierto.value === id) {
        menuAbierto.value = null;
        return;
    }
    const rect = event.currentTarget.getBoundingClientRect();
    const menuHeight = 90; // Reducido ya que quitamos el botón de eliminar
    
    if (rect.bottom + menuHeight > window.innerHeight) {
        menuTop.value = `${rect.top - menuHeight + 25}px`; 
    } else {
        menuTop.value = `${rect.bottom + 5}px`;
    }
    
    menuLeft.value = `${rect.left - 150}px`; 
    menuAbierto.value = id;
};

const cerrarMenu = () => {
    menuAbierto.value = null;
};

// --- Filtros y Búsqueda ---
const busqueda = ref('');
const filtroSucursal = ref('');
const filtroEstado = ref('');

const cajasFiltradas = computed(() => {
    return props.cajas.filter(c => {
        const matchBusqueda = busqueda.value === '' || c.nombre.toLowerCase().includes(busqueda.value.toLowerCase());
        const matchSucursal = filtroSucursal.value === '' || c.sucursal_id == filtroSucursal.value;
        const matchEstado = filtroEstado.value === '' || 
            (filtroEstado.value === 'activo' && c.estado) || 
            (filtroEstado.value === 'inactivo' && !c.estado);
        
        return matchBusqueda && matchSucursal && matchEstado;
    });
});

const hayFiltrosActivos = computed(() => 
    busqueda.value !== '' || filtroSucursal.value !== '' || filtroEstado.value !== ''
);

const limpiarFiltros = () => {
    busqueda.value = '';
    filtroSucursal.value = '';
    filtroEstado.value = '';
};

// --- Lógica de Paginación ---
const paginaActual = ref(1);
const itemsPorPagina = ref(7); // 7 items como solicitaste

// Si cambia algún filtro, volvemos a la página 1 automáticamente
watch([busqueda, filtroSucursal, filtroEstado], () => {
    paginaActual.value = 1;
});

const totalPaginas = computed(() => {
    return Math.ceil(cajasFiltradas.value.length / itemsPorPagina.value) || 1;
});

const cajasPaginadas = computed(() => {
    const inicio = (paginaActual.value - 1) * itemsPorPagina.value;
    const fin = inicio + itemsPorPagina.value;
    return cajasFiltradas.value.slice(inicio, fin);
});

const paginaAnterior = () => {
    if (paginaActual.value > 1) paginaActual.value--;
};

const paginaSiguiente = () => {
    if (paginaActual.value < totalPaginas.value) paginaActual.value++;
};

// --- Modal Crear / Editar ---
const showModal = ref(false);
const isEditing = ref(false);

const form = useForm({
    id: null,
    nombre: '',
    sucursal_id: '',
});

const openModal = (caja = null) => {
    cerrarMenu();
    if (caja) {
        isEditing.value = true;
        form.id = caja.id;
        form.nombre = caja.nombre;
        form.sucursal_id = caja.sucursal_id;
    } else {
        isEditing.value = false;
        form.reset();
        if (props.sucursales.length === 1) {
            form.sucursal_id = props.sucursales[0].id;
        }
    }
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    form.reset();
    form.clearErrors();
};

const saveCaja = () => {
    if (isEditing.value) {
        form.put(route('cajas.update', form.id), {
            onSuccess: () => {
                closeModal();
                Swal.fire({ title: '¡Actualizada!', text: 'La caja se actualizó correctamente.', icon: 'success', timer: 2000, showConfirmButton: false });
            },
        });
    } else {
        form.post(route('cajas.store'), {
            onSuccess: () => {
                closeModal();
                Swal.fire({ title: '¡Creada!', text: 'La caja se registró correctamente.', icon: 'success', timer: 2000, showConfirmButton: false });
            },
        });
    }
};

// --- Cambiar Estado (Baja Lógica / Reactivar) ---
const toggleEstado = (caja) => {
    cerrarMenu();
    
    const esInactivar = caja.estado;
    const colorBtn = esInactivar ? '#ef4444' : '#10b981'; // Rojo para dar de baja, verde para reactivar

    const titleText = esInactivar ? '¿Dar de baja lógica la caja?' : '¿Reactivar la caja?';
    const bodyText = esInactivar 
        ? `La caja "${caja.nombre}" pasará a estar inactiva. Se realiza una baja lógica para no perder la trazabilidad de los turnos y ventas históricas.` 
        : `La caja "${caja.nombre}" volverá a estar operativa para abrir nuevos turnos.`;
    const confirmText = esInactivar ? 'Sí, dar de baja' : 'Sí, reactivar';

    Swal.fire({
        title: titleText,
        text: bodyText,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: colorBtn,
        cancelButtonColor: '#94a3b8',
        confirmButtonText: confirmText,
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            router.patch(route('cajas.status', caja.id), {}, { 
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({ 
                        title: '¡Éxito!', 
                        text: esInactivar ? 'La caja fue dada de baja correctamente.' : 'La caja se reactivó con éxito.', 
                        icon: 'success', 
                        timer: 2000, 
                        showConfirmButton: false 
                    });
                }
            });
        }
    });
};
</script>