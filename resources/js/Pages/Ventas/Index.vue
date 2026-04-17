<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch, reactive } from 'vue';
import Swal from 'sweetalert2';
import ModalDetalleVenta from './Componentes/ModalDetalleVenta.vue'; 

const props = defineProps({
    ventas: Object,
    filtros: Object
});

const menuAbierto = ref(null);
const verDetalle = ref(false);
const ventaSeleccionada = ref(null);

const formFiltros = reactive({
    search: props.filtros?.search || '',
    estado: props.filtros?.estado || 'all',
    fecha_desde: props.filtros?.fecha_desde || '',
    fecha_hasta: props.filtros?.fecha_hasta || ''
});

let timeout = null;

watch(formFiltros, (value) => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(route('ventas.index'), value, {
            preserveState: true,
            replace: true
        });
    }, 300);
});

const limpiarFiltros = () => {
    formFiltros.search = '';
    formFiltros.estado = 'all';
    formFiltros.fecha_desde = '';
    formFiltros.fecha_hasta = '';
};

const formatearFecha = (fecha) => {
    return new Date(fecha).toLocaleString('es-AR', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit', hour12: false
    });
};

const toggleMenu = (id) => {
    menuAbierto.value = menuAbierto.value === id ? null : id;
};

const cerrarMenu = () => {
    menuAbierto.value = null;
};

const abrirDetalle = (v) => {
    cerrarMenu();
    ventaSeleccionada.value = v;
    verDetalle.value = true;
};

const realizarAnulacion = (id, motivoFinal) => {
    router.post(route('ventas.cancelar', id), {
        motivo: motivoFinal
    }, {
        onSuccess: () => {
            Swal.fire({
                title: '¡HECHO!',
                text: 'La venta se anuló y el stock se repuso.',
                icon: 'success',
                confirmButtonColor: '#0284c7',
            });
        }
    });
};

const confirmarAnulacion = (v) => {
    cerrarMenu();
    Swal.fire({
        title: '¿CONFIRMAR ANULACIÓN?',
        text: `Se anulará el ticket #${v.id}.`,
        icon: 'warning',
        input: 'select',
        inputOptions: {
            'Error en la carga': 'Error en la carga',
            'Cliente desistió': 'Cliente desistió',
            'otro': 'Otro...'
        },
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Sí, anular',
    }).then((result) => {
        if (result.isConfirmed) {
            realizarAnulacion(v.id, result.value);
        }
    });
};
</script>

<template>
    <Head title="Historial de Ventas" />

    <AuthenticatedLayout>
        <div v-if="menuAbierto" @click="cerrarMenu" class="fixed inset-0 z-30"></div>

        <div class="py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto bg-slate-50 min-h-screen">
            
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4">
                <div>
                    <h1 class="text-2xl font-black text-slate-800 uppercase tracking-tight">Historial de Ventas</h1>
                    <div class="h-1 w-12 bg-sky-500 mt-1"></div>
                </div>
                <Link :href="route('pos.index')" class="bg-sky-600 text-white font-bold py-3 px-6 rounded-xl shadow-lg text-sm uppercase tracking-widest w-full sm:w-auto text-center hover:bg-sky-700 transition-colors">
                    Nueva Venta (POS)
                </Link>
            </div>

            <div class="mb-6 bg-white p-4 rounded-2xl shadow-sm border border-slate-100 flex flex-col gap-4">
                <div class="flex flex-col sm:flex-row gap-4 w-full">
                    <div class="relative w-full sm:w-1/2">
                        <input 
                            v-model="formFiltros.search" 
                            type="text" 
                            placeholder="Buscar por N° de Ticket o Nombre del Cliente..." 
                            class="w-full pl-10 pr-4 py-2.5 border border-slate-200 rounded-xl focus:ring-sky-500 focus:border-sky-500 transition-all font-medium text-sm"
                        >
                        <svg class="w-5 h-5 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    
                    <div class="w-full sm:w-1/2">
                        <select v-model="formFiltros.estado" class="w-full border border-slate-200 rounded-xl py-2.5 px-4 focus:ring-sky-500 focus:border-sky-500 text-slate-600 bg-slate-50 cursor-pointer font-medium text-sm">
                            <option value="all">Todos los estados</option>
                            <option value="Completada">Completadas</option>
                            <option value="Cancelada">Canceladas</option>
                        </select>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                    <div class="flex flex-col sm:flex-row gap-4 w-full sm:w-2/3">
                        <div class="flex items-center gap-2 w-full sm:w-1/2">
                            <label class="text-xs font-bold text-slate-400 uppercase tracking-widest whitespace-nowrap">Desde</label>
                            <input v-model="formFiltros.fecha_desde" type="date" class="w-full border border-slate-200 rounded-xl py-2 px-3 focus:ring-sky-500 focus:border-sky-500 text-slate-600 bg-slate-50 text-sm">
                        </div>
                        <div class="flex items-center gap-2 w-full sm:w-1/2">
                            <label class="text-xs font-bold text-slate-400 uppercase tracking-widest whitespace-nowrap">Hasta</label>
                            <input v-model="formFiltros.fecha_hasta" type="date" class="w-full border border-slate-200 rounded-xl py-2 px-3 focus:ring-sky-500 focus:border-sky-500 text-slate-600 bg-slate-50 text-sm">
                        </div>
                    </div>

                    <div class="flex gap-2 w-full sm:w-auto justify-end">
                        <button v-if="formFiltros.search || formFiltros.estado !== 'all' || formFiltros.fecha_desde || formFiltros.fecha_hasta" @click="limpiarFiltros" class="text-sm text-slate-500 hover:text-rose-500 font-bold px-4 transition-colors">
                            Limpiar Filtros
                        </button>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow-xl rounded-2xl border border-slate-100 p-4">
                <div class="overflow-visible">
                    <table class="w-full text-left border-separate border-spacing-y-2">
                        <thead>
                            <tr class="bg-sky-50 text-sky-900 uppercase text-[10px] font-black tracking-widest">
                                <th class="p-4 text-center rounded-l-xl">N° Ticket</th>
                                <th class="p-4">Fecha</th>
                                <th class="p-4">Cliente</th>
                                <th class="p-4 text-center">Estado</th>
                                <th class="p-4 text-right">Total</th>
                                <th class="p-4 text-center rounded-r-xl">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="ventas.data.length === 0">
                                <td colspan="6" class="p-10 text-center text-slate-400 italic bg-slate-50">
                                    No se encontraron ventas con esos filtros.
                                </td>
                            </tr>
                            <tr v-for="v in ventas.data" :key="v.id" 
                                class="bg-white hover:bg-sky-50 transition-all shadow-sm"
                                :class="{'opacity-60 grayscale bg-slate-50': v.estado === 'Cancelada'}"
                            >
                                <td class="p-4 text-center font-mono font-bold text-sky-800">#{{ v.id }}</td>
                                <td class="p-4 text-slate-600 font-medium">{{ formatearFecha(v.created_at) }}</td>
                                <td class="p-4 font-bold text-slate-700">{{ v.consumidor?.nombre || 'Consumidor Final' }}</td>
                                <td class="p-4 text-center">
                                    <span 
                                        :class="v.estado === 'Completada' ? 'bg-emerald-100 text-emerald-700 border-emerald-200' : 'bg-rose-100 text-rose-700 border-rose-200'"
                                        class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest border"
                                    >
                                        {{ v.estado }}
                                    </span>
                                </td>
                                <td class="p-4 text-right font-black text-slate-900 font-mono text-lg">${{ v.total }}</td>
                                
                                <td class="p-4 text-center relative">
                                    <button @click.stop="toggleMenu(v.id)" class="p-2 rounded-full text-slate-400 hover:text-sky-600 hover:bg-sky-100 transition-colors focus:outline-none">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg>
                                    </button>

                                    <div v-if="menuAbierto === v.id" class="absolute right-10 top-10 w-48 bg-white rounded-xl shadow-2xl border border-slate-100 z-40 py-2 animate-in fade-in zoom-in-95 duration-150">
                                        <button @click="abrirDetalle(v)" class="w-full text-left px-4 py-2.5 text-xs font-bold text-sky-600 hover:bg-sky-50 flex items-center gap-3 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                            Ver Detalles
                                        </button>

                                        <div v-if="v.estado === 'Completada'">
                                            <div class="border-t border-slate-100 my-1"></div>
                                            <button @click="confirmarAnulacion(v)" class="w-full text-left px-4 py-2.5 text-xs font-bold text-rose-600 hover:bg-rose-50 flex items-center gap-3 transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                                Anular Venta
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="ventas.links && ventas.data.length > 0" class="p-4 bg-slate-50 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <span class="text-sm text-slate-500 font-medium">
                        Mostrando {{ ventas.from }} a {{ ventas.to }} de {{ ventas.total }} ventas
                    </span>
                    <div class="flex flex-wrap justify-center gap-1">
                        <component
                            :is="link.url ? 'a' : 'span'"
                            v-for="(link, index) in ventas.links"
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

        <ModalDetalleVenta 
            :mostrar="verDetalle" 
            :venta="ventaSeleccionada" 
            @cerrar="verDetalle = false" 
        />
    </AuthenticatedLayout>
</template>