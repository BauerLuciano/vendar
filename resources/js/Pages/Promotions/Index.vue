<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { watch, reactive } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    promotions: Object,
    filters: Object,
});

const formFiltros = reactive({
    search: props.filters?.search || '',
    type: props.filters?.type || 'all',
    active: props.filters?.active || 'all',
});

let timeout = null;

watch(formFiltros, (value) => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(route('promotions.index'), value, {
            preserveState: true,
            replace: true,
        });
    }, 300);
});

const limpiarFiltros = () => {
    formFiltros.search = '';
    formFiltros.type = 'all';
    formFiltros.active = 'all';
};

const abrirNuevo = () => {
    router.get(route('promotions.create'));
};

const abrirEditar = (p) => {
    router.get(route('promotions.edit', p.id));
};

const toggleActive = (promotion) => {
    const accion = promotion.active ? 'desactivar' : 'activar';
    const resultado = promotion.active ? 'desactivada' : 'activada';
    const colorConfirm = promotion.active ? '#ef4444' : '#10b981';

    Swal.fire({
        title: `¿${accion.toUpperCase()} promoción?`,
        text: `"${promotion.name}" cambiará su estado a ${resultado}.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: colorConfirm,
        cancelButtonColor: '#6b7280',
        confirmButtonText: `Sí, ${accion}`,
        cancelButtonText: 'Cancelar',
    }).then((result) => {
        if (result.isConfirmed) {
            router.patch(route('promotions.toggle', promotion.id), {}, {
                onSuccess: () => {
                    Swal.fire({
                        title: '¡Listo!',
                        text: `Promoción ${resultado} correctamente.`,
                        icon: 'success',
                        confirmButtonColor: '#0284c7',
                    });
                },
            });
        }
    });
};

const confirmDelete = (promotion) => {
    Swal.fire({
        title: '¿Eliminar promoción?',
        text: `"${promotion.name}" se eliminará permanentemente.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('promotions.destroy', promotion.id), {
                onSuccess: () => {
                    Swal.fire({
                        title: '¡Eliminada!',
                        text: 'Promoción eliminada correctamente.',
                        icon: 'success',
                        confirmButtonColor: '#0284c7',
                    });
                },
            });
        }
    });
};

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('es-AR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
};

const discountLabel = (promotion) => {
    if (promotion.discount_type === 'percent') {
        return `${promotion.value}%`;
    }
    if (promotion.discount_type === 'fixed_price') {
        return `$${promotion.value}`;
    }
    if (promotion.discount_type === '2x1') {
        return '2x1';
    }
    if (promotion.discount_type === 'bundle') {
        return `Combo $${promotion.value}`;
    }
    return '-';
};
</script>

<template>
    <Head title="Promociones" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Promociones</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <div class="flex flex-col sm:flex-row justify-between items-center mb-6 bg-white p-4 rounded-2xl shadow-sm border border-gray-100 gap-4">
                    <div class="flex gap-4 w-full sm:w-3/4">
                        <div class="relative w-1/2">
                            <input
                                v-model="formFiltros.search"
                                type="text"
                                placeholder="Buscar promoción..."
                                class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-xl focus:ring-sky-500 focus:border-sky-500 transition-all"
                            >
                            <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>

                        <div class="w-1/4">
                            <select v-model="formFiltros.type" class="w-full border border-gray-200 rounded-xl py-2 px-4 focus:ring-sky-500 focus:border-sky-500 text-slate-600 bg-slate-50 cursor-pointer">
                                <option value="all">Todos los tipos</option>
                                <option value="MANUAL">Manual</option>
                                <option value="AUTO">Automática</option>
                            </select>
                        </div>

                        <div class="w-1/4">
                            <select v-model="formFiltros.active" class="w-full border border-gray-200 rounded-xl py-2 px-4 focus:ring-sky-500 focus:border-sky-500 text-slate-600 bg-slate-50 cursor-pointer">
                                <option value="all">Todos los estados</option>
                                <option value="1">Solo Activas</option>
                                <option value="0">Solo Inactivas</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex gap-2 w-full sm:w-auto justify-end items-center">
                        <button v-if="formFiltros.search || formFiltros.type !== 'all' || formFiltros.active !== 'all'" @click="limpiarFiltros" class="text-sm text-gray-500 hover:text-rose-500 font-bold px-4 transition-colors">
                            Limpiar Filtros
                        </button>
                        <button @click="abrirNuevo" class="bg-sky-600 text-white px-6 py-2.5 rounded-xl font-bold shadow-md hover:bg-sky-700 hover:-translate-y-0.5 transition-all active:scale-95 whitespace-nowrap">
                            + Nueva Promoción
                        </button>
                    </div>
                </div>

                <div class="bg-white shadow-xl rounded-2xl border border-gray-100 p-4">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="bg-sky-50 text-sky-900 uppercase text-xs font-black border-b border-sky-100">
                                    <th class="p-4 rounded-l-xl">Nombre</th>
                                    <th class="p-4">Tipo</th>
                                    <th class="p-4">Descuento</th>
                                    <th class="p-4">Vigencia</th>
                                    <th class="p-4 text-center">Prioridad</th>
                                    <th class="p-4 text-center">Estado</th>
                                    <th class="p-4 text-center rounded-r-xl">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-if="promotions.data.length === 0">
                                    <td colspan="7" class="p-10 text-center text-gray-400 italic bg-gray-50">
                                        No se encontraron promociones con los filtros actuales.
                                    </td>
                                </tr>

                                <tr v-for="p in promotions.data" :key="p.id"
                                    class="bg-white hover:bg-sky-50 transition-all duration-200"
                                    :class="{'opacity-50 grayscale bg-slate-50': !p.active}">

                                    <td class="p-4 font-bold text-slate-700">{{ p.name }}</td>

                                    <td class="p-4">
                                        <span :class="p.type === 'MANUAL' ? 'text-purple-600 bg-purple-50' : 'text-amber-600 bg-amber-50'"
                                            class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest shadow-sm">
                                            {{ p.type === 'MANUAL' ? 'Manual' : 'Auto' }}
                                        </span>
                                    </td>

                                    <td class="p-4 font-mono font-bold text-sky-800">
                                        {{ discountLabel(p) }}
                                    </td>

                                    <td class="p-4 text-sm text-slate-500">
                                        {{ formatDate(p.starts_at) }} → {{ formatDate(p.ends_at) }}
                                    </td>

                                    <td class="p-4 text-center font-mono text-slate-600">
                                        {{ p.priority }}
                                    </td>

                                    <td class="p-4 text-center">
                                        <span :class="p.active ? 'text-emerald-600 bg-emerald-50' : 'text-rose-600 bg-rose-50'"
                                            class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest shadow-sm">
                                            {{ p.active ? 'Activa' : 'Inactiva' }}
                                        </span>
                                    </td>

                                    <td class="p-4 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <button @click="abrirEditar(p)"
                                                class="p-2 rounded-lg text-amber-500 hover:bg-amber-50 transition-all"
                                                title="Editar">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                            </button>

                                            <button @click="toggleActive(p)"
                                                class="p-2 rounded-lg transition-all"
                                                :class="p.active ? 'text-rose-500 hover:bg-rose-50' : 'text-emerald-500 hover:bg-emerald-50'"
                                                :title="p.active ? 'Desactivar' : 'Activar'">
                                                <svg v-if="p.active" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                                                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            </button>

                                            <button @click="confirmDelete(p)"
                                                class="p-2 rounded-lg text-rose-500 hover:bg-rose-50 transition-all"
                                                title="Eliminar">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="promotions.links && promotions.data.length > 0" class="p-4 bg-slate-50 border-t border-gray-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                        <span class="text-sm text-gray-500 font-medium">
                            Mostrando {{ promotions.from }} a {{ promotions.to }} de {{ promotions.total }} resultados
                        </span>
                        <div class="flex flex-wrap justify-center gap-1">
                            <component
                                :is="link.url ? 'a' : 'span'"
                                v-for="(link, index) in promotions.links"
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

    </AuthenticatedLayout>
</template>
