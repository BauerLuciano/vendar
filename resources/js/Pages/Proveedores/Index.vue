<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ModalProveedor from './Componentes/ModalProveedor.vue'; 
import DetalleProveedor from './Componentes/DetalleProveedor.vue'; 
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({ proveedores: Array });

const verModal = ref(false);
const verDetalle = ref(false);
const seleccionado = ref(null);

const abrirNuevo = () => { seleccionado.value = null; verModal.value = true; };
const abrirEditar = (p) => { seleccionado.value = p; verModal.value = true; };
const abrirDetalle = (p) => { seleccionado.value = p; verDetalle.value = true; };

const cerrarModal = () => { verModal.value = false; seleccionado.value = null; };

const toggleEstado = (p) => {
    const accion = p.estado ? 'desactivar' : 'activar';
    Swal.fire({
        title: `¿${accion.toUpperCase()} proveedor?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: p.estado ? '#ef4444' : '#10b981',
        confirmButtonText: `Sí, ${accion}`
    }).then((result) => {
        if (result.isConfirmed) {
            router.patch(route('proveedores.status', p.id), {}, {
                onSuccess: () => Swal.fire('¡Listo!', 'Estado modificado.', 'success')
            });
        }
    });
};
</script>

<template>
    <Head title="Proveedores" />

    <AuthenticatedLayout>
        <template #header><h2 class="font-semibold text-xl text-gray-800 leading-tight">Proveedores</h2></template>

        <div class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-bold text-gray-600 uppercase">Listado de Proveedores</h3>
                <button @click="abrirNuevo" class="bg-sky-600 text-white px-6 py-2 rounded-lg font-bold shadow-lg hover:bg-sky-700">+ Nuevo Proveedor</button>
            </div>

            <div class="bg-white shadow-xl rounded-2xl overflow-hidden border border-gray-100 p-4">
                <table class="w-full text-left border-separate border-spacing-y-2">
                    <thead>
                        <tr class="bg-sky-50 text-sky-900 uppercase text-xs font-black">
                            <th class="p-4 rounded-l-xl">Razón Social</th>
                            <th class="p-4">CUIT</th>
                            <th class="p-4 text-center">Estado</th>
                            <th class="p-4 text-center rounded-r-xl">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="p in proveedores" :key="p.id" class="bg-white border-b hover:bg-sky-50" :class="{'opacity-50': !p.estado}">
                            <td class="p-4 font-bold text-slate-700 uppercase">{{ p.razon_social }}</td>
                            <td class="p-4 font-mono font-bold text-sky-800">{{ p.cuit }}</td>
                            <td class="p-4 text-center">
                                <span :class="p.estado ? 'text-emerald-600 bg-emerald-50' : 'text-rose-600 bg-rose-50'" class="px-3 py-1 rounded-full text-[10px] font-black uppercase">
                                    {{ p.estado ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="p-4 flex justify-center gap-3">
                                <button @click="abrirDetalle(p)" class="bg-sky-100 text-sky-600 p-2 rounded-xl">🔍</button>
                                <button @click="abrirEditar(p)" class="bg-amber-100 text-amber-600 p-2 rounded-xl">✏️</button>
                                <button @click="toggleEstado(p)" class="bg-slate-100 text-slate-600 p-2 rounded-xl">🔄</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <ModalProveedor :mostrar="verModal" :proveedor="seleccionado" @cerrar="cerrarModal" />
        <DetalleProveedor :mostrar="verDetalle" :proveedor="seleccionado" @cerrar="verDetalle = false" />
    </AuthenticatedLayout>
</template>