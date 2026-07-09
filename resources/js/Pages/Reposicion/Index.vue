<script setup>
import { ref, onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Swal from 'sweetalert2';

const props = defineProps({
    faltantes: Array,
    proveedores: Array,
    sucursalActual: Object
});

// Lista de productos que el usuario checkea para pedir
const seleccionados = ref([]);

// Cuando carga la página, preparamos la lista con las recomendaciones matemáticas
onMounted(() => {
    seleccionados.value = props.faltantes.map(prod => {
        let sugerido;
        if (prod.stock_objetivo != null && prod.stock_objetivo > 0) {
            sugerido = prod.stock_objetivo - prod.cantidad_fisica;
        } else {
            sugerido = (prod.stock_minimo * 2) - prod.cantidad_fisica;
        }
        if (sugerido <= 0) sugerido = 1;

        return {
            id: prod.id,
            producto_id: prod.id,
            nombre: prod.nombre,
            codigo_barras: prod.codigo_barras,
            cantidad_fisica: prod.cantidad_fisica,
            stock_minimo: prod.stock_minimo,
            stock_objetivo: prod.stock_objetivo,
            // Valores interactivos (El usuario los puede cambiar en la tabla)
            seleccionado: false,
            proveedor_id: prod.proveedor_id || '', // Pre-seleccionamos el default
            cantidad: sugerido,
            precio_costo: prod.precio_costo
        };
    });
});

const seleccionarTodo = (event) => {
    const checkeado = event.target.checked;
    seleccionados.value.forEach(item => item.seleccionado = checkeado);
};

const enviarPedidos = () => {
    // Filtramos solo los que el usuario tildó
    const aPedir = seleccionados.value.filter(item => item.seleccionado);

    if (aPedir.length === 0) {
        Swal.fire('Atención', 'Debes seleccionar al menos un producto para reponer.', 'warning');
        return;
    }

    // Validamos que todos tengan proveedor asignado
    const sinProveedor = aPedir.find(item => !item.proveedor_id);
    if (sinProveedor) {
        Swal.fire('Falta Proveedor', `Debes elegir un proveedor para: ${sinProveedor.nombre}`, 'error');
        return;
    }

    Swal.fire({
        title: '¿Generar Pre-Órdenes?',
        text: `Se agruparán ${aPedir.length} productos según su proveedor.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#4f46e5',
        confirmButtonText: 'Sí, generar pedidos',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('reposicion.generar'), { productos: aPedir });
        }
    });
};
</script>

<template>
    <Head title="Reposición Inteligente" />

    <AuthenticatedLayout>
        <div class="py-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto bg-slate-50 min-h-screen">
            
            <div class="flex justify-between items-end mb-8">
                <div>
                    <div class="flex items-center gap-3 mb-1">
                        <span class="bg-amber-100 text-amber-700 text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded-md">Módulo Premium</span>
                        <h1 class="text-3xl font-black text-slate-800 tracking-tight">Reposición Inteligente</h1>
                    </div>
                    <p class="text-slate-500 font-medium text-sm">Sucursal actual: <span class="font-bold text-sky-600">{{ sucursalActual?.nombre }}</span></p>
                </div>
                
                <button @click="enviarPedidos" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-2xl font-black text-sm shadow-xl shadow-indigo-600/30 transition-all flex items-center gap-2 group">
                    Generar Órdenes de Compra
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 group-hover:translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                </button>
            </div>

            <div class="bg-white border border-slate-200 shadow-xl shadow-slate-200/40 rounded-3xl overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-800 text-white border-b border-slate-700">
                            <th class="py-4 px-4 text-center w-12">
                                <input type="checkbox" @change="seleccionarTodo" class="w-4 h-4 rounded text-indigo-500 focus:ring-indigo-500 bg-slate-700 border-slate-600">
                            </th>
                            <th class="py-4 px-4 text-[10px] font-black tracking-widest uppercase text-slate-400">Producto Faltante</th>
                            <th class="py-4 px-4 text-[10px] font-black tracking-widest uppercase text-slate-400 text-center">Stock Físico / Mínimo</th>
                            <th class="py-4 px-4 text-[10px] font-black tracking-widest uppercase text-slate-400">Proveedor (Cotizar a)</th>
                            <th class="py-4 px-4 text-[10px] font-black tracking-widest uppercase text-slate-400 text-center">Cantidad a Pedir</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="seleccionados.length === 0">
                            <td colspan="5" class="py-12 text-center text-emerald-500 font-bold bg-emerald-50">
                                ¡Excelente! Tu stock está en niveles óptimos. No hay alertas de reposición.
                            </td>
                        </tr>
                        
                        <tr v-for="(item, index) in seleccionados" :key="item.id" class="hover:bg-slate-50 transition-colors" :class="{'bg-indigo-50/30': item.seleccionado}">
                            
                            <td class="py-4 px-4 text-center">
                                <input type="checkbox" v-model="item.seleccionado" class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500 border-slate-300">
                            </td>

                            <td class="py-4 px-4">
                                <div class="font-bold text-slate-700">{{ item.nombre }}</div>
                                <div class="text-[10px] font-mono text-slate-400">{{ item.codigo_barras }}</div>
                            </td>

                            <td class="py-4 px-4 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <span class="px-2 py-1 bg-rose-100 text-rose-700 font-black rounded text-xs shadow-sm" title="Stock Físico">{{ item.cantidad_fisica }}</span>
                                    <span class="text-slate-300 font-black">/</span>
                                    <span class="text-slate-400 font-bold text-xs" title="Stock Mínimo">{{ item.stock_minimo }}</span>
                                </div>
                            </td>

                            <td class="py-4 px-4">
                                <select v-model="item.proveedor_id" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-1.5 text-sm font-bold text-slate-700 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm">
                                    <option value="" disabled>Seleccionar...</option>
                                    <option v-for="prov in proveedores" :key="prov.id" :value="prov.id">{{ prov.razon_social }}</option>
                                </select>
                            </td>

                            <td class="py-4 px-4 text-center">
                                <input type="number" v-model="item.cantidad" min="1" class="w-20 text-center bg-white border border-slate-200 rounded-xl px-2 py-1.5 text-sm font-black text-indigo-700 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm">
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </AuthenticatedLayout>
</template>