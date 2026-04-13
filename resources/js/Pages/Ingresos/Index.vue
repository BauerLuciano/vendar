<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DetalleIngreso from './Componentes/DetalleIngreso.vue';
import ModalIngreso from './Componentes/ModalIngreso.vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import { ref, onMounted, watch, reactive } from 'vue';
import Swal from 'sweetalert2'; 

const props = defineProps({ 
    ingresos: Object,
    productos: Array,
    proveedores: Array,
    sucursales: Array,
    filtros: Object
});

const page = usePage();
const verDetalle = ref(false);
const verModal = ref(false);
const seleccionado = ref(null);

const formFiltros = reactive({
    search: props.filtros?.search || '',
    proveedor_id: props.filtros?.proveedor_id || 'all',
    sucursal_id: props.filtros?.sucursal_id || 'all',
    fecha_desde: props.filtros?.fecha_desde || '',
    fecha_hasta: props.filtros?.fecha_hasta || ''
});

let timeout = null;

watch(formFiltros, (value) => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(route('ingresos.index'), value, {
            preserveState: true,
            replace: true
        });
    }, 300);
});

const limpiarFiltros = () => {
    formFiltros.search = '';
    formFiltros.proveedor_id = 'all';
    formFiltros.sucursal_id = 'all';
    formFiltros.fecha_desde = '';
    formFiltros.fecha_hasta = '';
};

const abrirDetalle = (ingreso) => {
    seleccionado.value = ingreso;
    verDetalle.value = true;
};

const abrirNuevo = () => {
    verModal.value = true;
};

onMounted(() => {
    if (page.props.flash.alertas_inflacion && page.props.flash.alertas_inflacion.length > 0) {
        let listaProductos = page.props.flash.alertas_inflacion.map(p => 
            `<li class="flex justify-between items-center border-b border-slate-100 py-2">
                <span class="font-bold text-slate-700">${p.producto}</span>
                <span class="bg-rose-100 text-rose-600 px-2 py-0.5 rounded-lg text-xs font-black">+${p.porcentaje || 'Aumento'}</span>
            </li>`
        ).join('');

        Swal.fire({
            title: '<span class="text-2xl font-black text-slate-800">¡Alerta de Aumentos!</span>',
            html: `
                <div class="text-left mt-4">
                    <p class="text-slate-500 text-sm mb-4">Los siguientes productos entraron con un costo mayor al registrado anteriormente:</p>
                    <ul class="bg-slate-50 p-4 rounded-2xl border border-slate-200 max-h-60 overflow-y-auto">
                        ${listaProductos}
                    </ul>
                    <p class="mt-4 text-[11px] text-slate-400 italic text-center text-balance">
                        Los costos han sido actualizados automáticamente en tu catálogo de productos.
                    </p>
                </div>
            `,
            icon: 'warning',
            confirmButtonColor: '#4f46e5',
            confirmButtonText: 'Entendido, voy a revisar mis precios',
            customClass: {
                popup: 'rounded-3xl',
                confirmButton: 'rounded-xl font-bold uppercase text-xs tracking-widest py-3 px-6'
            }
        });
    }
    
    if (page.props.flash.exito) {
        Swal.fire({
            title: '¡Éxito!',
            text: page.props.flash.exito,
            icon: 'success',
            timer: 3000,
            showConfirmButton: false,
            timerProgressBar: true,
            customClass: {
                popup: 'rounded-3xl'
            }
        });
    }

    if (page.props.flash.error) {
        Swal.fire({
            title: 'Hubo un problema',
            text: page.props.flash.error,
            icon: 'error',
            confirmButtonColor: '#e11d48',
            customClass: {
                popup: 'rounded-3xl'
            }
        });
    }
});
</script>

<template>
    <Head title="Historial de Ingresos" />

    <AuthenticatedLayout>
        <template #header><h2 class="font-semibold text-xl text-gray-800 leading-tight">Historial de Stock</h2></template>

        <div class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                <h3 class="text-lg font-bold text-gray-600 uppercase tracking-wider">Ingresos Registrados</h3>
                <button @click="abrirNuevo" class="bg-sky-600 text-white px-6 py-2.5 rounded-xl font-bold shadow-lg hover:bg-sky-700 hover:-translate-y-0.5 transition-all active:scale-95 uppercase text-xs tracking-widest w-full sm:w-auto">
                    + Cargar Remito
                </button>
            </div>

            <div class="mb-6 bg-white p-4 rounded-3xl shadow-sm border border-slate-200 flex flex-col gap-4">
                <div class="flex flex-col sm:flex-row gap-4 w-full">
                    <div class="relative w-full sm:w-1/3">
                        <input 
                            v-model="formFiltros.search" 
                            type="text" 
                            placeholder="Buscar por Nro. Remito..." 
                            class="w-full pl-10 pr-4 py-2.5 border border-slate-200 rounded-xl focus:ring-sky-500 focus:border-sky-500 transition-all font-medium text-sm"
                        >
                        <svg class="w-5 h-5 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    
                    <div class="w-full sm:w-1/3">
                        <select v-model="formFiltros.sucursal_id" class="w-full border border-slate-200 rounded-xl py-2.5 px-4 focus:ring-sky-500 focus:border-sky-500 text-slate-600 bg-slate-50 cursor-pointer font-medium text-sm">
                            <option value="all">Todas las sucursales</option>
                            <option v-for="suc in sucursales" :key="suc.id" :value="suc.id">{{ suc.nombre }}</option>
                        </select>
                    </div>

                    <div class="w-full sm:w-1/3">
                        <select v-model="formFiltros.proveedor_id" class="w-full border border-slate-200 rounded-xl py-2.5 px-4 focus:ring-sky-500 focus:border-sky-500 text-slate-600 bg-slate-50 cursor-pointer font-medium text-sm">
                            <option value="all">Todos los proveedores</option>
                            <option v-for="prov in proveedores" :key="prov.id" :value="prov.id">{{ prov.razon_social }}</option>
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
                        <button v-if="formFiltros.search || formFiltros.sucursal_id !== 'all' || formFiltros.proveedor_id !== 'all' || formFiltros.fecha_desde || formFiltros.fecha_hasta" @click="limpiarFiltros" class="text-sm text-slate-500 hover:text-rose-500 font-bold px-4 transition-colors">
                            Limpiar Filtros
                        </button>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow-xl rounded-3xl overflow-hidden border border-slate-200">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-sky-50 text-sky-900 uppercase text-xs font-black border-b border-sky-100">
                                <th class="p-4">Fecha</th>
                                <th class="p-4">Nro Remito</th>
                                <th class="p-4">Sucursal</th>
                                <th class="p-4">Proveedor</th>
                                <th class="p-4 text-right">Total Costo</th>
                                <th class="p-4 text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="ingresos.data.length === 0">
                                <td colspan="6" class="p-10 text-center text-slate-400 italic bg-slate-50">
                                    No se encontraron ingresos con esos filtros.
                                </td>
                            </tr>
                            <tr v-for="i in ingresos.data" :key="i.id" class="bg-white hover:bg-sky-50 transition-colors group">
                                <td class="p-4 font-bold text-slate-700">{{ i.fecha_ingreso }}</td>
                                <td class="p-4 font-mono font-bold text-sky-800">{{ i.numero_remito || 'S/N' }}</td>
                                <td class="p-4 text-sm text-slate-600 font-bold">{{ i.sucursal?.nombre }}</td>
                                <td class="p-4 text-sm text-slate-600">{{ i.proveedor?.razon_social || 'Variado / Ninguno' }}</td>
                                <td class="p-4 text-right font-black text-rose-600 font-mono">${{ i.total_costo }}</td>
                                <td class="p-4 text-center">
                                    <button @click="abrirDetalle(i)" class="bg-slate-800 text-white px-4 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest hover:bg-slate-700 shadow-sm transition-all">Ver Detalle</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="ingresos.links && ingresos.data.length > 0" class="p-4 bg-slate-50 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <span class="text-sm text-slate-500 font-medium">
                        Mostrando {{ ingresos.from }} a {{ ingresos.to }} de {{ ingresos.total }} ingresos
                    </span>
                    <div class="flex flex-wrap justify-center gap-1">
                        <component
                            :is="link.url ? 'a' : 'span'"
                            v-for="(link, index) in ingresos.links"
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

        <DetalleIngreso :mostrar="verDetalle" :ingreso="seleccionado" @cerrar="verDetalle = false" />
        
        <ModalIngreso 
            :mostrar="verModal" 
            :productos="productos" 
            :proveedores="proveedores" 
            :sucursales="sucursales" 
            @cerrar="verModal = false" 
        />
    </AuthenticatedLayout>
</template>