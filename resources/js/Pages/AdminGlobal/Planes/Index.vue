<script setup>
import GlobalAdminLayout from '@/Layouts/GlobalAdminLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    planes: { type: Array, default: () => [] },
    modulosDisponibles: { type: Array, default: () => [] },
});

const busqueda = ref('');
const mostrarModal = ref(false);
const planSeleccionado = ref(null);

const planesFiltrados = computed(() => {
    if (!busqueda.value) return props.planes;
    return props.planes.filter(p =>
        p.nombre.toLowerCase().includes(busqueda.value.toLowerCase())
    );
});

const form = useForm({
    nombre: '',
    slug: '',
    descripcion: '',
    precio_mensual: 0,
    modulos: {},
    sucursales_limit: 1,
    usuarios_limit: 1,
    destacado: false,
    orden: 0,
    activo: true,
});

const abrirModal = (plan = null) => {
    planSeleccionado.value = plan;
    if (plan) {
        const modulos = {};
        props.modulosDisponibles.forEach(m => {
            modulos[m.id] = plan.modulos[m.id] ?? false;
        });

        form.nombre = plan.nombre;
        form.slug = plan.slug;
        form.descripcion = plan.descripcion || '';
        form.precio_mensual = plan.precio_mensual;
        form.modulos = modulos;
        form.sucursales_limit = plan.sucursales_limit;
        form.usuarios_limit = plan.usuarios_limit;
        form.destacado = plan.destacado;
        form.orden = plan.orden;
        form.activo = plan.activo;
    } else {
        form.reset();
        form.orden = props.planes.length + 1;
    }
    mostrarModal.value = true;
};

const guardar = () => {
    if (planSeleccionado.value) {
        form.put(route('admin.planes.update', planSeleccionado.value.id), {
            onSuccess: () => {
                mostrarModal.value = false;
                Swal.fire('Actualizado', 'Plan actualizado correctamente.', 'success');
            },
            onError: (errors) => {
                Swal.fire('Error', Object.values(errors).join('\n'), 'error');
            }
        });
    } else {
        form.post(route('admin.planes.store'), {
            onSuccess: () => {
                mostrarModal.value = false;
                Swal.fire('Creado', 'Nuevo plan creado.', 'success');
            },
            onError: (errors) => {
                Swal.fire('Error', Object.values(errors).join('\n'), 'error');
            }
        });
    }
};

const eliminar = (plan) => {
    Swal.fire({
        title: 'Eliminar plan',
        text: `¿Eliminar "${plan.nombre}"? Esta acción no se puede deshacer.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonText: 'Cancelar',
        confirmButtonText: 'Sí, eliminar',
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route('admin.planes.destroy', plan.id), {
                onSuccess: () => Swal.fire('Eliminado', 'Plan eliminado.', 'success'),
                onError: (errors) => Swal.fire('Error', errors.error || 'No se pudo eliminar.', 'error'),
            });
        }
    });
};

const formatearDinero = (monto) => {
    return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS', maximumFractionDigits: 0 }).format(monto);
};
</script>

<template>
    <Head title="Panel Global - Planes" />

    <GlobalAdminLayout>
        <div class="py-8 px-6 max-w-7xl mx-auto">
            <div class="sm:flex sm:items-center sm:justify-between mb-8">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Planes SaaS</h1>
                    <p class="mt-1 text-sm text-slate-500">Administrá los planes disponibles, precios y módulos incluidos.</p>
                </div>
                <div class="mt-4 sm:mt-0">
                    <button @click="abrirModal()" class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 transition-all">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M10 3a1.5 1.5 0 011.5 1.5v4h4a1.5 1.5 0 010 3h-4v4a1.5 1.5 0 01-3 0v-4h-4a1.5 1.5 0 010-3h4v-4A1.5 1.5 0 0110 3z" />
                        </svg>
                        Nuevo Plan
                    </button>
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
                        <input v-model="busqueda" type="text" class="block w-full rounded-xl border-0 py-2 pl-10 text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" placeholder="Buscar plan...">
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="py-3.5 pl-6 pr-3 text-left text-xs font-semibold text-slate-900">Plan</th>
                                <th scope="col" class="px-3 py-3.5 text-center text-xs font-semibold text-slate-900">Precio</th>
                                <th scope="col" class="px-3 py-3.5 text-center text-xs font-semibold text-slate-900">Sucursales</th>
                                <th scope="col" class="px-3 py-3.5 text-center text-xs font-semibold text-slate-900">Usuarios</th>
                                <th scope="col" class="px-3 py-3.5 text-center text-xs font-semibold text-slate-900">Destacado</th>
                                <th scope="col" class="px-3 py-3.5 text-center text-xs font-semibold text-slate-900">Activo</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold text-slate-900">Módulos</th>
                                <th scope="col" class="relative py-3.5 pl-3 pr-6 text-right text-xs font-semibold text-slate-900">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-if="!planesFiltrados.length">
                                <td colspan="8" class="py-12 text-center text-sm text-slate-500">No hay planes registrados.</td>
                            </tr>
                            <tr v-for="plan in planesFiltrados" :key="plan.id" class="hover:bg-slate-50 transition-colors">
                                <td class="whitespace-nowrap py-4 pl-6 pr-3">
                                    <div class="font-medium text-slate-900">{{ plan.nombre }}</div>
                                    <div v-if="plan.descripcion" class="text-xs text-slate-500">{{ plan.descripcion }}</div>
                                    <div class="text-[10px] text-slate-400 font-mono mt-0.5">{{ plan.slug }}</div>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-center font-bold text-slate-800">
                                    {{ formatearDinero(plan.precio_mensual) }}
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-center">
                                    {{ plan.sucursales_limit === 999 ? '∞' : plan.sucursales_limit }}
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-center">
                                    {{ plan.usuarios_limit === 999 ? '∞' : plan.usuarios_limit }}
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-center">
                                    <span v-if="plan.destacado" class="inline-flex items-center rounded-full bg-amber-50 px-2 py-1 text-[10px] font-bold text-amber-700 ring-1 ring-inset ring-amber-600/20">★ Popular</span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-center">
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset"
                                        :class="plan.activo ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-rose-50 text-rose-700 ring-rose-600/20'">
                                        <svg class="h-1.5 w-1.5" :class="plan.activo ? 'fill-emerald-500' : 'fill-rose-500'" viewBox="0 0 6 6"><circle cx="3" cy="3" r="3" /></svg>
                                        {{ plan.activo ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td class="px-3 py-4 text-sm">
                                    <div class="flex flex-wrap gap-1">
                                        <span v-for="(val, mod) in plan.modulos" v-show="val" :key="mod"
                                            class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-1 text-[10px] font-medium text-indigo-700 ring-1 ring-inset ring-indigo-700/10 uppercase">
                                            {{ mod }}
                                        </span>
                                    </div>
                                </td>
                                <td class="relative whitespace-nowrap py-4 pl-3 pr-6 text-right text-sm font-medium">
                                    <button @click="abrirModal(plan)" class="text-indigo-600 hover:text-indigo-900 font-semibold transition-colors mr-3">
                                        Editar
                                    </button>
                                    <button @click="eliminar(plan)" class="text-rose-600 hover:text-rose-900 font-semibold transition-colors">
                                        Eliminar
                                    </button>
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
                                    <h3 class="text-lg font-bold leading-6 text-slate-900">
                                        {{ planSeleccionado ? 'Editar Plan' : 'Crear Nuevo Plan' }}
                                    </h3>
                                    <div class="mt-2">
                                        <p class="text-sm text-slate-500">Definí los parámetros del plan SaaS.</p>
                                    </div>

                                    <form @submit.prevent="guardar" class="mt-6 grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                                        <div class="sm:col-span-2">
                                            <label class="block text-sm font-medium leading-6 text-slate-900">Nombre del Plan</label>
                                            <div class="mt-1">
                                                <input v-model="form.nombre" type="text" maxlength="255" class="block w-full rounded-xl border-0 py-2 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" required>
                                            </div>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium leading-6 text-slate-900">Slug (identificador único)</label>
                                            <div class="mt-1">
                                                <input v-model="form.slug" type="text" class="block w-full rounded-xl border-0 py-2 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" required>
                                            </div>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium leading-6 text-slate-900">Precio Mensual ($)</label>
                                            <div class="mt-1">
                                                <input v-model.number="form.precio_mensual" type="number" step="0.01" class="block w-full rounded-xl border-0 py-2 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" required>
                                            </div>
                                        </div>

                                        <div class="sm:col-span-2">
                                            <label class="block text-sm font-medium leading-6 text-slate-900">Descripción</label>
                                            <div class="mt-1">
                                                <textarea v-model="form.descripcion" rows="2" class="block w-full rounded-xl border-0 py-2 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"></textarea>
                                            </div>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium leading-6 text-slate-900">Límite de Sucursales</label>
                                            <div class="mt-1">
                                                <input v-model.number="form.sucursales_limit" type="number" min="0" step="1" class="block w-full rounded-xl border-0 py-2 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                            </div>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium leading-6 text-slate-900">Límite de Usuarios</label>
                                            <div class="mt-1">
                                                <input v-model.number="form.usuarios_limit" type="number" min="0" step="1" class="block w-full rounded-xl border-0 py-2 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                            </div>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium leading-6 text-slate-900">Orden de visualización</label>
                                            <div class="mt-1">
                                                <input v-model.number="form.orden" type="number" min="0" step="1" class="block w-full rounded-xl border-0 py-2 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-6 pt-6">
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input v-model="form.destacado" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-600">
                                                <span class="text-sm font-medium text-slate-900">Plan Destacado (Popular)</span>
                                            </label>
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input v-model="form.activo" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-600">
                                                <span class="text-sm font-medium text-slate-900">Plan Activo</span>
                                            </label>
                                        </div>

                                        <div class="sm:col-span-2 pt-2 border-t border-slate-100 mt-2">
                                            <label class="block text-sm font-semibold leading-6 text-slate-900 mb-3">Módulos Incluidos</label>
                                            <div class="grid grid-cols-2 gap-4">
                                                <div v-for="mod in modulosDisponibles" :key="mod.id" class="relative flex items-start p-3 rounded-xl border border-slate-200 hover:bg-slate-50 transition-colors cursor-pointer">
                                                    <div class="flex h-6 items-center">
                                                        <input :id="'mod-' + mod.id" v-model="form.modulos[mod.id]" type="checkbox" :true-value="true" :false-value="false" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-600 cursor-pointer">
                                                    </div>
                                                    <div class="ml-3 text-sm leading-6">
                                                        <label :for="'mod-' + mod.id" class="font-medium text-slate-900 cursor-pointer">{{ mod.nombre }}</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="bg-slate-50 px-4 py-4 sm:flex sm:flex-row-reverse sm:px-6 border-t border-slate-100">
                            <button type="button" @click="guardar" :disabled="form.processing" class="inline-flex w-full justify-center rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 sm:ml-3 sm:w-auto transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                                {{ planSeleccionado ? 'Guardar Cambios' : 'Crear Plan' }}
                            </button>
                            <button type="button" @click="mostrarModal = false" class="mt-3 inline-flex w-full justify-center rounded-xl bg-white px-6 py-2.5 text-sm font-semibold text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto transition-all">
                                Cancelar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </GlobalAdminLayout>
</template>
