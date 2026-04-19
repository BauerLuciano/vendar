<script setup>
import GlobalAdminLayout from '@/Layouts/GlobalAdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    comercios: Array,
    modulosDisponibles: Array
});

const busqueda = ref('');
const mostrarModal = ref(false);
const comercioSeleccionado = ref(null);

// Filtrado de comercios
const comerciosFiltrados = computed(() => {
    if (!busqueda.value) return props.comercios;
    return props.comercios.filter(c => 
        c.nombre.toLowerCase().includes(busqueda.value.toLowerCase())
    );
});

// Formulario para Crear/Editar
const form = useForm({
    nombre: '',
    plan: 'basico',
    status: 'trial',
    limite_sucursales: 1,
    vencimiento_pago: '',
    modulos_habilitados: { pos: true } // POS siempre activo por defecto
});

const abrirModal = (comercio = null) => {
    comercioSeleccionado.value = comercio;
    if (comercio) {
        form.nombre = comercio.nombre;
        form.plan = comercio.plan;
        form.status = comercio.status;
        form.limite_sucursales = comercio.limite_sucursales;
        form.vencimiento_pago = comercio.vencimiento_pago;
        form.modulos_habilitados = comercio.modulos_habilitados || { pos: true };
    } else {
        form.reset();
    }
    mostrarModal.value = true;
};

const guardar = () => {
    if (comercioSeleccionado.value) {
        form.put(route('admin.comercios.update', comercioSeleccionado.value.id), {
            onSuccess: () => {
                mostrarModal.value = false;
                Swal.fire('Actualizado', 'Configuración de comercio guardada.', 'success');
            }
        });
    } else {
        form.post(route('admin.comercios.store'), {
            onSuccess: () => {
                mostrarModal.value = false;
                Swal.fire('Registrado', 'Nuevo comercio añadido al sistema.', 'success');
            }
        });
    }
};

// Formatear fecha
const formatearFecha = (fecha) => {
    if (!fecha) return 'N/A';
    return new Date(fecha).toLocaleDateString();
};
</script>

<template>
    <Head title="Panel Global - Comercios" />

    <GlobalAdminLayout>
        <div class="py-8 px-6 max-w-7xl mx-auto">
            
            <div class="sm:flex sm:items-center sm:justify-between mb-8">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Comercios Registrados</h1>
                    <p class="mt-1 text-sm text-slate-500">Administrá los clientes SaaS, sus planes y módulos habilitados.</p>
                </div>
                <div class="mt-4 sm:mt-0">
                    <button @click="abrirModal()" class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 transition-all">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M10 3a1.5 1.5 0 011.5 1.5v4h4a1.5 1.5 0 010 3h-4v4a1.5 1.5 0 01-3 0v-4h-4a1.5 1.5 0 010-3h4v-4A1.5 1.5 0 0110 3z" />
                        </svg>
                        Nuevo Comercio
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-3 mb-8">
                <div class="overflow-hidden rounded-2xl bg-white px-6 py-5 shadow-sm ring-1 ring-slate-900/5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 rounded-xl bg-indigo-50 p-3">
                            <svg class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z" />
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="truncate text-sm font-medium text-slate-500">Total Clientes</p>
                            <p class="text-2xl font-semibold text-slate-900">{{ comercios.length }}</p>
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden rounded-2xl bg-white px-6 py-5 shadow-sm ring-1 ring-slate-900/5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 rounded-xl bg-emerald-50 p-3">
                            <svg class="h-6 w-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="truncate text-sm font-medium text-slate-500">Activos</p>
                            <p class="text-2xl font-semibold text-slate-900">{{ comercios.filter(c => c.status === 'activo').length }}</p>
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden rounded-2xl bg-white px-6 py-5 shadow-sm ring-1 ring-slate-900/5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 rounded-xl bg-rose-50 p-3">
                            <svg class="h-6 w-6 text-rose-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="truncate text-sm font-medium text-slate-500">Suspendidos</p>
                            <p class="text-2xl font-semibold text-slate-900">{{ comercios.filter(c => c.status === 'suspendido').length }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white ring-1 ring-slate-900/5 sm:rounded-2xl shadow-sm overflow-hidden">
                <div class="p-4 border-b border-slate-100 sm:flex sm:items-center sm:justify-between bg-white">
                    <div class="relative w-full sm:max-w-xs">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <svg class="h-5 w-5 text-slate-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <input v-model="busqueda" type="text" class="block w-full rounded-xl border-0 py-2 pl-10 text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" placeholder="Buscar comercio...">
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="py-3.5 pl-6 pr-3 text-left text-xs font-semibold text-slate-900">Comercio</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold text-slate-900">Plan</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold text-slate-900">Módulos</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold text-slate-900">Vencimiento</th>
                                <th scope="col" class="px-3 py-3.5 text-center text-xs font-semibold text-slate-900">Estado</th>
                                <th scope="col" class="relative py-3.5 pl-3 pr-6 text-right text-xs font-semibold text-slate-900">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-for="comercio in comerciosFiltrados" :key="comercio.id" class="hover:bg-slate-50 transition-colors">
                                <td class="whitespace-nowrap py-4 pl-6 pr-3">
                                    <div class="font-medium text-slate-900">{{ comercio.nombre }}</div>
                                    <div class="text-xs text-slate-500">{{ comercio.slug }}</div>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm">
                                    <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset"
                                        :class="{
                                            'bg-slate-50 text-slate-600 ring-slate-500/10': comercio.plan === 'basico',
                                            'bg-blue-50 text-blue-700 ring-blue-700/10': comercio.plan === 'pro',
                                            'bg-purple-50 text-purple-700 ring-purple-700/10': comercio.plan === 'premium',
                                        }">
                                        {{ comercio.plan.charAt(0).toUpperCase() + comercio.plan.slice(1) }}
                                    </span>
                                </td>
                                <td class="px-3 py-4 text-sm">
                                    <div class="flex flex-wrap gap-1">
                                        <span v-for="(val, mod) in comercio.modulos_habilitados" :key="mod" 
                                              v-show="val" class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-1 text-[10px] font-medium text-indigo-700 ring-1 ring-inset ring-indigo-700/10 uppercase">
                                            {{ mod }}
                                        </span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-600 font-medium">
                                    {{ formatearFecha(comercio.vencimiento_pago) }}
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-center">
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset"
                                        :class="{
                                            'bg-emerald-50 text-emerald-700 ring-emerald-600/20': comercio.status === 'activo',
                                            'bg-rose-50 text-rose-700 ring-rose-600/20': comercio.status === 'suspendido',
                                            'bg-amber-50 text-amber-700 ring-amber-600/20': comercio.status === 'trial',
                                        }">
                                        <svg class="h-1.5 w-1.5" :class="{'fill-emerald-500': comercio.status === 'activo', 'fill-rose-500': comercio.status === 'suspendido', 'fill-amber-500': comercio.status === 'trial'}" viewBox="0 0 6 6" aria-hidden="true">
                                            <circle cx="3" cy="3" r="3" />
                                        </svg>
                                        {{ comercio.status.charAt(0).toUpperCase() + comercio.status.slice(1) }}
                                    </span>
                                </td>
                                <td class="relative whitespace-nowrap py-4 pl-3 pr-6 text-right text-sm font-medium">
                                    <button @click="abrirModal(comercio)" class="text-indigo-600 hover:text-indigo-900 font-semibold transition-colors">Configurar<span class="sr-only">, {{ comercio.nombre }}</span></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div v-if="mostrarModal" class="relative z-50" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity"></div>

            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-2xl">
                        <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                            <div class="sm:flex sm:items-start">
                                <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left w-full">
                                    <h3 class="text-lg font-bold leading-6 text-slate-900" id="modal-title">
                                        {{ comercioSeleccionado ? 'Configurar Comercio' : 'Registrar Nuevo Comercio' }}
                                    </h3>
                                    <div class="mt-2">
                                        <p class="text-sm text-slate-500">Completá los datos del tenant para provisionar su entorno en el sistema.</p>
                                    </div>

                                    <form @submit.prevent="guardar" class="mt-6 grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                                        <div>
                                            <label class="block text-sm font-medium leading-6 text-slate-900">Nombre del Comercio</label>
                                            <div class="mt-1">
                                                <input v-model="form.nombre" type="text" class="block w-full rounded-xl border-0 py-2 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" required>
                                            </div>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium leading-6 text-slate-900">Plan SaaS</label>
                                            <div class="mt-1">
                                                <select v-model="form.plan" class="block w-full rounded-xl border-0 py-2 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                                    <option value="basico">Plan Básico</option>
                                                    <option value="pro">Plan Profesional</option>
                                                    <option value="premium">Plan Premium / Enterprise</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium leading-6 text-slate-900">Estado de Cuenta</label>
                                            <div class="mt-1">
                                                <select v-model="form.status" class="block w-full rounded-xl border-0 py-2 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                                    <option value="activo">Activo (Al día)</option>
                                                    <option value="suspendido">Suspendido (Falta de pago)</option>
                                                    <option value="trial">Período de Prueba</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium leading-6 text-slate-900">Límite de Sucursales</label>
                                            <div class="mt-1">
                                                <input v-model="form.limite_sucursales" type="number" class="block w-full rounded-xl border-0 py-2 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                            </div>
                                        </div>

                                        <div class="sm:col-span-2">
                                            <label class="block text-sm font-medium leading-6 text-slate-900">Próximo Vencimiento</label>
                                            <div class="mt-1">
                                                <input v-model="form.vencimiento_pago" type="date" class="block w-full rounded-xl border-0 py-2 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                            </div>
                                        </div>

                                        <div class="sm:col-span-2 pt-2 border-t border-slate-100 mt-2">
                                            <label class="block text-sm font-semibold leading-6 text-slate-900 mb-3">Módulos Habilitados</label>
                                            <div class="grid grid-cols-2 gap-4">
                                                <div v-for="mod in modulosDisponibles" :key="mod.id" class="relative flex items-start p-3 rounded-xl border border-slate-200 hover:bg-slate-50 transition-colors cursor-pointer">
                                                    <div class="flex h-6 items-center">
                                                        <input :id="mod.id" v-model="form.modulos_habilitados[mod.id]" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-600 cursor-pointer">
                                                    </div>
                                                    <div class="ml-3 text-sm leading-6">
                                                        <label :for="mod.id" class="font-medium text-slate-900 cursor-pointer">{{ mod.nombre }}</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="bg-slate-50 px-4 py-4 sm:flex sm:flex-row-reverse sm:px-6 border-t border-slate-100">
                            <button type="button" @click="guardar" class="inline-flex w-full justify-center rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 sm:ml-3 sm:w-auto transition-colors">
                                {{ comercioSeleccionado ? 'Guardar Cambios' : 'Crear Comercio' }}
                            </button>
                            <button type="button" @click="mostrarModal = false" class="mt-3 inline-flex w-full justify-center rounded-xl bg-white px-6 py-2.5 text-sm font-semibold text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto transition-colors">
                                Cancelar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </GlobalAdminLayout>
</template>