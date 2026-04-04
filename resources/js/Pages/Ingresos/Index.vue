<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DetalleIngreso from './Componentes/DetalleIngreso.vue';
import ModalIngreso from './Componentes/ModalIngreso.vue';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ 
    ingresos: Array,
    productos: Array,
    proveedores: Array,
    sucursales: Array
});

const verDetalle = ref(false);
const verModal = ref(false);
const seleccionado = ref(null);

const abrirDetalle = (ingreso) => {
    seleccionado.value = ingreso;
    verDetalle.value = true;
};

const abrirNuevo = () => {
    verModal.value = true;
};
</script>

<template>
    <Head title="Historial de Ingresos" />

    <AuthenticatedLayout>
        <template #header><h2 class="font-semibold text-xl text-gray-800 leading-tight">Historial de Stock</h2></template>

        <div class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-bold text-gray-600 uppercase">Ingresos Registrados</h3>
                <button @click="abrirNuevo" class="bg-sky-600 text-white px-6 py-2.5 rounded-lg font-bold shadow-lg hover:bg-sky-700 transition-all uppercase text-xs tracking-widest">
                    + Cargar Remito
                </button>
            </div>

            <div class="bg-white shadow-xl rounded-2xl overflow-hidden border border-gray-100 p-4">
                <table class="w-full text-left border-separate border-spacing-y-2">
                    <thead>
                        <tr class="bg-sky-50 text-sky-900 uppercase text-xs font-black">
                            <th class="p-4 rounded-l-xl">Fecha</th>
                            <th class="p-4">Nro Remito</th>
                            <th class="p-4">Sucursal</th>
                            <th class="p-4">Proveedor</th>
                            <th class="p-4 text-right">Total Costo</th>
                            <th class="p-4 text-center rounded-r-xl">Auditar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="ingresos.length === 0">
                            <td colspan="6" class="p-10 text-center text-slate-400 italic bg-slate-50 rounded-xl">No hay ingresos registrados.</td>
                        </tr>
                        <tr v-for="i in ingresos" :key="i.id" class="bg-white border-b hover:bg-sky-50 transition-colors shadow-sm">
                            <td class="p-4 font-bold text-slate-700">{{ i.fecha_ingreso }}</td>
                            <td class="p-4 font-mono font-bold text-sky-800">{{ i.numero_remito || 'S/N' }}</td>
                            <td class="p-4 text-sm text-slate-600 font-bold">{{ i.sucursal?.nombre }}</td>
                            <td class="p-4 text-sm text-slate-600">{{ i.proveedor?.razon_social || 'Variado / Ninguno' }}</td>
                            <td class="p-4 text-right font-black text-rose-600 font-mono">${{ i.total_costo }}</td>
                            <td class="p-4 text-center">
                                <button @click="abrirDetalle(i)" class="bg-slate-800 text-white px-4 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest hover:bg-slate-700 shadow-sm">Ver Detalle</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
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