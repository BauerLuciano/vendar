<script setup>
import { ref, watch, reactive, computed } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Swal from 'sweetalert2';
import ModalCrearOrden from './Componentes/ModalCrearOrden.vue';
import ModalRecepcion from './Componentes/ModalRecepcion.vue';
import TimelineHistorial from './Componentes/TimelineHistorial.vue';

const props = defineProps({
    ordenes: Object,
    proveedores: Array,
    sucursales: Array,
    productos: Array,
    filtros: Object,
});

const menuAbierto = ref(null);
const showModalDetalle = ref(false);
const showModalCrear = ref(false);
const showModalEditar = ref(false);
const showModalRecepcion = ref(false);
const showModalTimeline = ref(false);
const ordenSeleccionada = ref(null);
const ordenParaEditar = ref(null);

const formFiltros = reactive({
    search: props.filtros?.search || '',
    estado: props.filtros?.estado || 'all',
    proveedor_id: props.filtros?.proveedor_id || 'all',
    fecha_desde: props.filtros?.fecha_desde || '',
    fecha_hasta: props.filtros?.fecha_hasta || ''
});

let timeout = null;
watch(formFiltros, () => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(route('ordenes-compra.index'), formFiltros, { preserveState: true, replace: true });
    }, 300);
});

const limpiarFiltros = () => {
    Object.assign(formFiltros, { search: '', estado: 'all', proveedor_id: 'all', fecha_desde: '', fecha_hasta: '' });
};

const generarSugerencias = () => {
    Swal.fire({
        title: '¿Generar sugerencias?',
        text: "El sistema analizará el stock y armará los pedidos automáticamente.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, generar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#4f46e5',
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('ordenes-compra.sugerencias'), {}, { preserveScroll: true });
        }
    });
};

const formatearDinero = (monto) => new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(monto);
const formatearFecha = (f) => f ? new Date(f).toLocaleDateString('es-AR') : '—';

const badgeClases = (estado) => ({
    'Sugerida':              'bg-slate-100 text-slate-600',
    'Borrador':              'bg-amber-100 text-amber-700',
    'Enviada':               'bg-sky-100 text-sky-700',
    'Cotizada':              'bg-purple-100 text-purple-700',
    'Aprobada':              'bg-indigo-100 text-indigo-700',
    'Confirmada':            'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30',
    'Parcialmente Recibida': 'bg-amber-100 text-amber-700 ring-2 ring-amber-300 ring-offset-1',
    'Recibida':              'bg-emerald-100 text-emerald-700',
    'Cancelada':             'bg-rose-100 text-rose-700',
}[estado] || 'bg-slate-100 text-slate-600');

const toggleMenu = (id) => { menuAbierto.value = menuAbierto.value === id ? null : id; };
const cerrarMenu = () => { menuAbierto.value = null; };
const cerrarDetalles = () => { showModalDetalle.value = false; ordenSeleccionada.value = null; };

const accionEnvio = (orden) => {
    cerrarMenu();
    Swal.fire({
        title: '¿Enviar al proveedor?',
        text: "Se registrará la fecha de envío y el estado cambiará a Enviada.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, enviar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#0ea5e9',
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('ordenes-compra.enviar', orden.id), {}, { preserveScroll: true });
        }
    });
};

const accionConfirmar = (orden) => {
    cerrarMenu();
    Swal.fire({
        title: '¿Confirmar pedido?',
        text: "El proveedor aceptó. La orden pasará a Confirmada.",
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: 'Sí, confirmar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#4f46e5',
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('ordenes-compra.confirmar', orden.id), {}, { preserveScroll: true });
        }
    });
};

const accionRechazar = (orden) => {
    cerrarMenu();
    Swal.fire({
        title: '¿Rechazar esta orden?',
        input: 'text',
        inputLabel: 'Motivo del rechazo',
        inputPlaceholder: 'Ej: precios muy altos, no disponible...',
        showCancelButton: true,
        confirmButtonText: 'Rechazar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#e11d48',
        inputValidator: (v) => !v && 'Ingresá un motivo.',
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('ordenes-compra.rechazar', orden.id), { motivo_rechazo: result.value }, { preserveScroll: true });
        }
    });
};

const accionCancelar = (orden) => {
    cerrarMenu();
    Swal.fire({
        title: '¿Cancelar esta orden?',
        input: 'text',
        inputLabel: 'Motivo de cancelación',
        inputPlaceholder: 'Ej: ya no se necesita, cambio de proveedor...',
        showCancelButton: true,
        confirmButtonText: 'Cancelar orden',
        cancelButtonText: 'Volver',
        confirmButtonColor: '#e11d48',
        inputValidator: (v) => !v && 'Ingresá un motivo.',
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('ordenes-compra.cancelar', orden.id), { motivo_cancelacion: result.value }, { preserveScroll: true });
        }
    });
};

const accionEliminar = (orden) => {
    cerrarMenu();
    Swal.fire({
        title: '¿Eliminar orden?',
        text: "Esta acción no se puede deshacer.",
        icon: 'error',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        confirmButtonColor: '#e11d48',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('ordenes-compra.destroy', orden.id), { preserveScroll: true });
        }
    });
};

const accionRecibir = (orden) => {
    cerrarMenu();
    ordenSeleccionada.value = orden;
    showModalRecepcion.value = true;
};

const abrirDetalles = (orden) => { cerrarMenu(); ordenSeleccionada.value = orden; showModalDetalle.value = true; };
const abrirEditar = (orden) => { cerrarMenu(); ordenParaEditar.value = orden; showModalEditar.value = true; };
const abrirTimeline = (orden) => { cerrarMenu(); ordenSeleccionada.value = orden; showModalTimeline.value = true; };

const puedeAccion = (orden, accion) => {
    const estados = {
        editar:     ['Sugerida'],
        enviar:     ['Sugerida'],
        eliminar:   ['Sugerida'],
        confirmar:  ['Enviada'],
        rechazar:   ['Enviada'],
        recibir:    ['Confirmada', 'Parcialmente Recibida'],
        cancelar:   ['Sugerida', 'Enviada', 'Confirmada', 'Parcialmente Recibida'],
    };
    return estados[accion]?.includes(orden.estado) ?? false;
};

const tienePendientes = (orden) =>
    orden.detalles?.some(d => (d.cantidad_recibida || 0) < d.cantidad_pedida);
</script>

<template>
    <Head title="Órdenes de Compra" />

    <AuthenticatedLayout>
        <div v-if="menuAbierto" @click="cerrarMenu" class="fixed inset-0 z-30"></div>

        <div class="py-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto bg-slate-50 min-h-screen">

            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end mb-8 gap-4">
                <div>
                    <h1 class="text-3xl font-black text-slate-800 tracking-tight">Órdenes de Compra</h1>
                    <p class="text-slate-500 font-medium text-sm mt-1">Gestión y reposición de mercadería a proveedores</p>
                </div>
                <div class="flex gap-3 w-full sm:w-auto">
                    <button @click="showModalCrear = true" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-3 rounded-2xl font-black text-sm shadow-xl shadow-indigo-600/30 transition-all flex items-center gap-2 whitespace-nowrap">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                        Nueva Orden
                    </button>
                    <button @click="generarSugerencias" class="bg-slate-800 hover:bg-slate-900 text-white px-5 py-3 rounded-2xl font-black text-sm shadow-xl transition-all flex items-center gap-2 whitespace-nowrap">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                        Sugerencias
                    </button>
                </div>
            </div>

            <div class="mb-6 bg-white p-4 rounded-3xl shadow-sm border border-slate-200 flex flex-col gap-4">
                <div class="flex flex-col sm:flex-row gap-4 w-full">
                    <div class="relative w-full sm:w-1/3">
                        <input v-model="formFiltros.search" type="text" placeholder="Buscar por ID (ej: 15)..." class="w-full pl-10 pr-4 py-2.5 border border-slate-200 rounded-xl focus:ring-indigo-500 focus:border-indigo-500 transition-all font-medium text-sm">
                        <svg class="w-5 h-5 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <div class="w-full sm:w-1/3">
                        <select v-model="formFiltros.estado" class="w-full border border-slate-200 rounded-xl py-2.5 px-4 focus:ring-indigo-500 focus:border-indigo-500 text-slate-600 bg-slate-50 cursor-pointer font-medium text-sm">
                            <option value="all">Todos los estados</option>
                            <option value="Sugerida">Sugeridas</option>
                            <option value="Enviada">Enviadas</option>
                            <option value="Confirmada">Confirmadas</option>
                            <option value="Parcialmente Recibida">Parc. Recibidas</option>
                            <option value="Recibida">Recibidas</option>
                            <option value="Cancelada">Canceladas</option>
                        </select>
                    </div>
                    <div class="w-full sm:w-1/3">
                        <select v-model="formFiltros.proveedor_id" class="w-full border border-slate-200 rounded-xl py-2.5 px-4 focus:ring-indigo-500 focus:border-indigo-500 text-slate-600 bg-slate-50 cursor-pointer font-medium text-sm">
                            <option value="all">Todos los proveedores</option>
                            <option v-for="prov in proveedores" :key="prov.id" :value="prov.id">{{ prov.razon_social }}</option>
                        </select>
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                    <div class="flex flex-col sm:flex-row gap-4 w-full sm:w-2/3">
                        <div class="flex items-center gap-2 w-full sm:w-1/2">
                            <label class="text-xs font-bold text-slate-400 uppercase tracking-widest whitespace-nowrap">Desde</label>
                            <input v-model="formFiltros.fecha_desde" type="date" class="w-full border border-slate-200 rounded-xl py-2 px-3 focus:ring-indigo-500 focus:border-indigo-500 text-slate-600 bg-slate-50 text-sm">
                        </div>
                        <div class="flex items-center gap-2 w-full sm:w-1/2">
                            <label class="text-xs font-bold text-slate-400 uppercase tracking-widest whitespace-nowrap">Hasta</label>
                            <input v-model="formFiltros.fecha_hasta" type="date" class="w-full border border-slate-200 rounded-xl py-2 px-3 focus:ring-indigo-500 focus:border-indigo-500 text-slate-600 bg-slate-50 text-sm">
                        </div>
                    </div>
                    <div class="flex gap-2 w-full sm:w-auto justify-end">
                        <button v-if="formFiltros.search || formFiltros.estado !== 'all' || formFiltros.proveedor_id !== 'all' || formFiltros.fecha_desde || formFiltros.fecha_hasta" @click="limpiarFiltros" class="text-sm text-slate-500 hover:text-rose-500 font-bold px-4 transition-colors">
                            Limpiar Filtros
                        </button>
                    </div>
                </div>
            </div>

            <div class="bg-white border border-slate-200 shadow-xl shadow-slate-200/40 rounded-3xl overflow-visible">
                <div class="overflow-visible">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100">
                                <th class="py-4 px-6 text-[10px] font-black tracking-widest uppercase text-slate-400 rounded-tl-3xl">ID / Fecha</th>
                                <th class="py-4 px-6 text-[10px] font-black tracking-widest uppercase text-slate-400">Proveedor</th>
                                <th class="py-4 px-6 text-[10px] font-black tracking-widest uppercase text-slate-400">Estado</th>
                                <th class="py-4 px-6 text-[10px] font-black tracking-widest uppercase text-slate-400 text-right">Total Est.</th>
                                <th class="py-4 px-6 text-[10px] font-black tracking-widest uppercase text-slate-400 text-center rounded-tr-3xl">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="ordenes.data.length === 0">
                                <td colspan="5" class="p-10 text-center text-slate-400 italic bg-slate-50">
                                    No se encontraron órdenes con esos filtros.
                                </td>
                            </tr>
                            <tr v-for="orden in ordenes.data" :key="orden.id" class="hover:bg-slate-50/50 transition-colors">
                                <td class="py-4 px-6">
                                    <div class="font-black text-slate-700">#OC-{{ orden.id.toString().padStart(4, '0') }}</div>
                                    <div class="text-xs font-bold text-slate-400">{{ formatearFecha(orden.fecha_emision) }}</div>
                                </td>
                                <td class="py-4 px-6 font-bold text-slate-700">{{ orden.proveedor?.razon_social }}</td>
                                <td class="py-4 px-6">
                                    <span :class="badgeClases(orden.estado)" class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest">
                                        {{ orden.estado }}
                                    </span>
                                </td>
                                <td class="py-4 px-6 text-right font-black text-slate-800">{{ formatearDinero(orden.total_estimado) }}</td>

                                <td class="py-4 px-6 text-center relative">
                                    <button @click.stop="toggleMenu(orden.id)" class="p-2 rounded-full text-slate-400 hover:text-sky-600 hover:bg-sky-100 transition-colors focus:outline-none">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg>
                                    </button>

                                    <div v-if="menuAbierto === orden.id" class="absolute right-10 top-10 w-56 bg-white rounded-xl shadow-2xl border border-slate-100 z-40 py-2 animate-in fade-in zoom-in-95 duration-150">

                                        <button @click="abrirDetalles(orden)" class="w-full text-left px-4 py-2.5 text-xs font-bold text-sky-600 hover:bg-sky-50 flex items-center gap-3 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                            Ver Detalles
                                        </button>

                                        <button @click="abrirTimeline(orden)" class="w-full text-left px-4 py-2.5 text-xs font-bold text-violet-600 hover:bg-violet-50 flex items-center gap-3 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            Historial
                                        </button>

                                        <a :href="route('ordenes-compra.pdf', orden.id)" target="_blank" class="w-full text-left px-4 py-2.5 text-xs font-bold text-indigo-600 hover:bg-indigo-50 flex items-center gap-3 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                            PDF
                                        </a>

                                        <div class="border-t border-slate-100 my-1"></div>

                                        <button v-if="puedeAccion(orden, 'editar')" @click="abrirEditar(orden)" class="w-full text-left px-4 py-2.5 text-xs font-bold text-amber-600 hover:bg-amber-50 flex items-center gap-3 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                            Editar
                                        </button>

                                        <button v-if="puedeAccion(orden, 'enviar')" @click="accionEnvio(orden)" class="w-full text-left px-4 py-2.5 text-xs font-bold text-sky-600 hover:bg-sky-50 flex items-center gap-3 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                            Enviar al Proveedor
                                        </button>

                                        <button v-if="puedeAccion(orden, 'confirmar')" @click="accionConfirmar(orden)" class="w-full text-left px-4 py-2.5 text-xs font-bold text-indigo-600 hover:bg-indigo-50 flex items-center gap-3 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                            Confirmar (Proveedor Aceptó)
                                        </button>

                                        <button v-if="puedeAccion(orden, 'rechazar')" @click="accionRechazar(orden)" class="w-full text-left px-4 py-2.5 text-xs font-bold text-orange-600 hover:bg-orange-50 flex items-center gap-3 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                            Rechazar
                                        </button>

                                        <button v-if="puedeAccion(orden, 'recibir')" @click="accionRecibir(orden)" class="w-full text-left px-4 py-2.5 text-xs font-bold text-emerald-600 hover:bg-emerald-50 flex items-center gap-3 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                                            Recibir Mercadería
                                        </button>

                                        <button v-if="puedeAccion(orden, 'cancelar')" @click="accionCancelar(orden)" class="w-full text-left px-4 py-2.5 text-xs font-bold text-rose-600 hover:bg-rose-50 flex items-center gap-3 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                            Cancelar Orden
                                        </button>

                                        <button v-if="puedeAccion(orden, 'eliminar')" @click="accionEliminar(orden)" class="w-full text-left px-4 py-2.5 text-xs font-bold text-rose-600 hover:bg-rose-50 flex items-center gap-3 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            Eliminar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="ordenes.links && ordenes.data.length > 0" class="p-4 bg-slate-50 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <span class="text-sm text-slate-500 font-medium">
                        Mostrando {{ ordenes.from }} a {{ ordenes.to }} de {{ ordenes.total }} órdenes
                    </span>
                    <div class="flex flex-wrap justify-center gap-1">
                        <component :is="link.url ? 'a' : 'span'" v-for="(link, index) in ordenes.links" :key="index" :href="link.url"
                            @click.prevent="link.url ? router.get(link.url, formFiltros, { preserveState: true }) : null"
                            v-html="link.label.replace('Previous', 'Anterior').replace('Next', 'Siguiente')"
                            class="px-3 py-1.5 text-sm rounded-lg transition-colors border"
                            :class="[link.active ? 'bg-indigo-600 text-white font-bold border-indigo-600 shadow-sm' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-100', !link.url ? 'opacity-50 cursor-not-allowed bg-slate-50' : 'cursor-pointer']"
                        />
                    </div>
                </div>
            </div>

            <!-- MODAL DETALLE -->
            <div v-if="showModalDetalle" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
                <div class="bg-white rounded-3xl w-full max-w-4xl shadow-2xl overflow-hidden animate-in zoom-in-95 duration-200 flex flex-col max-h-[90vh]">
                    <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                        <div>
                            <h3 class="font-black text-xl text-slate-800 flex items-center gap-3">
                                Detalle #OC-{{ ordenSeleccionada?.id?.toString().padStart(4, '0') }}
                                <span :class="badgeClases(ordenSeleccionada?.estado)" class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-widest align-middle">
                                    {{ ordenSeleccionada?.estado }}
                                </span>
                            </h3>
                            <p class="text-xs font-bold text-slate-500 mt-1 uppercase tracking-widest">{{ ordenSeleccionada?.proveedor?.razon_social }}</p>
                        </div>
                        <button @click="cerrarDetalles" class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-200 text-slate-600 hover:bg-slate-300 transition-colors">✕</button>
                    </div>

                    <div class="p-6 overflow-y-auto flex-1 bg-slate-50">
                        <div v-if="ordenSeleccionada?.fecha_entrega_esperada" class="mb-4 bg-sky-50 text-sky-800 p-4 rounded-xl border border-sky-100 flex items-center gap-3">
                            <svg class="w-6 h-6 text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            <div>
                                <p class="text-[10px] uppercase font-black tracking-widest text-sky-600">Fecha de Entrega Esperada</p>
                                <p class="font-bold">{{ formatearFecha(ordenSeleccionada.fecha_entrega_esperada) }}</p>
                            </div>
                        </div>

                        <div v-if="ordenSeleccionada?.observaciones" class="mb-4 bg-slate-100 text-slate-700 p-4 rounded-xl border border-slate-200">
                            <p class="text-[10px] uppercase font-black tracking-widest text-slate-500 mb-1">Observaciones</p>
                            <p class="text-sm font-medium">{{ ordenSeleccionada.observaciones }}</p>
                        </div>

                        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
                            <table class="w-full text-left">
                                <thead>
                                    <tr class="bg-slate-100 border-b">
                                        <th class="py-3 px-4 text-[10px] font-black uppercase text-slate-500">Producto</th>
                                        <th class="py-3 px-4 text-[10px] font-black uppercase text-center text-slate-500">Pedida</th>
                                        <th class="py-3 px-4 text-[10px] font-black uppercase text-center text-slate-500">Recibida</th>
                                        <th class="py-3 px-4 text-[10px] font-black uppercase text-right text-slate-500">Costo U.</th>
                                        <th class="py-3 px-4 text-[10px] font-black uppercase text-right text-slate-500">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="detalle in ordenSeleccionada?.detalles" :key="detalle.id" class="border-b border-slate-100">
                                        <td class="py-3 px-4 font-bold text-slate-700">{{ detalle.producto?.nombre }}</td>
                                        <td class="py-3 px-4 text-center">
                                            <span class="bg-sky-100 text-sky-700 px-2 py-1 rounded-lg font-black text-sm">{{ detalle.cantidad_pedida }}</span>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <span :class="detalle.cantidad_recibida >= detalle.cantidad_pedida ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
                                                  class="px-2 py-1 rounded-lg font-black text-sm">
                                                {{ detalle.cantidad_recibida || 0 }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-right text-slate-500 font-medium">{{ formatearDinero(detalle.costo_unitario_estimado) }}</td>
                                        <td class="py-3 px-4 text-right font-black text-slate-800">{{ formatearDinero(detalle.subtotal_estimado) }}</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="bg-slate-50 border-t-2 border-slate-200">
                                        <td colspan="4" class="py-4 px-4 text-right font-black text-slate-500 uppercase tracking-widest text-xs">Total:</td>
                                        <td class="py-4 px-4 text-right font-black text-xl text-slate-900">{{ formatearDinero(ordenSeleccionada?.total_estimado) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <div class="p-6 bg-white border-t border-slate-100 flex flex-wrap justify-between gap-3">
                        <a :href="route('ordenes-compra.pdf', ordenSeleccionada?.id)" target="_blank" class="bg-slate-800 hover:bg-slate-900 text-white font-black py-3 px-5 rounded-xl shadow-lg flex items-center gap-2 transition-all text-sm">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            PDF
                        </a>
                        <div class="flex gap-2 flex-wrap">
                            <button v-if="puedeAccion(ordenSeleccionada, 'enviar')" @click="accionEnvio(ordenSeleccionada)" class="bg-sky-500 hover:bg-sky-600 text-white font-black py-3 px-5 rounded-xl shadow-lg flex items-center gap-2 transition-all text-sm">Enviar al Proveedor</button>
                            <button v-if="puedeAccion(ordenSeleccionada, 'confirmar')" @click="accionConfirmar(ordenSeleccionada)" class="bg-indigo-600 hover:bg-indigo-700 text-white font-black py-3 px-5 rounded-xl shadow-lg flex items-center gap-2 transition-all text-sm">Confirmar Pedido</button>
                            <button v-if="puedeAccion(ordenSeleccionada, 'recibir')" @click="accionRecibir(ordenSeleccionada)" class="bg-emerald-500 hover:bg-emerald-600 text-white font-black py-3 px-5 rounded-xl shadow-lg flex items-center gap-2 transition-all text-sm">Recibir Mercadería</button>
                            <button v-if="puedeAccion(ordenSeleccionada, 'editar')" @click="cerrarDetalles(); abrirEditar(ordenSeleccionada)" class="bg-amber-500 hover:bg-amber-600 text-white font-black py-3 px-5 rounded-xl shadow-lg flex items-center gap-2 transition-all text-sm">Editar Orden</button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>

    <ModalCrearOrden
        :mostrar="showModalCrear || showModalEditar"
        :productos="productos"
        :proveedores="proveedores"
        :sucursales="sucursales"
        :orden-para-editar="showModalEditar ? ordenParaEditar : null"
        @cerrar="showModalCrear = false; showModalEditar = false; ordenParaEditar = null"
    />

    <ModalRecepcion
        :mostrar="showModalRecepcion"
        :orden="ordenSeleccionada"
        @cerrar="showModalRecepcion = false; ordenSeleccionada = null"
    />

    <TimelineHistorial
        :mostrar="showModalTimeline"
        :orden="ordenSeleccionada"
        @cerrar="showModalTimeline = false; ordenSeleccionada = null"
    />
</template>
