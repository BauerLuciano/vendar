<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    mostrar: Boolean,
    productos: Array,
    proveedores: Array,
    sucursales: Array,
    ordenParaEditar: { type: Object, default: null },
});

const emit = defineEmits(['cerrar']);

const esEdicion = computed(() => !!props.ordenParaEditar);

const form = useForm({
    proveedor_id: '',
    sucursal_id: '',
    fecha_entrega_esperada: '',
    observaciones: '',
    items: [],
});

const productoSeleccionado = ref('');
const cantidadInput = ref(1);
const costoInput = ref(0);
const vencimientoInput = ref('');

watch(() => props.mostrar, (val) => {
    if (val) {
        if (props.ordenParaEditar) {
            form.proveedor_id = props.ordenParaEditar.proveedor_id;
            form.sucursal_id = props.ordenParaEditar.sucursal_id;
            form.fecha_entrega_esperada = props.ordenParaEditar.fecha_entrega_esperada?.split('T')[0] || '';
            form.observaciones = props.ordenParaEditar.observaciones || '';
            form.items = (props.ordenParaEditar.detalles || []).map(d => ({
                producto_id: d.producto_id,
                nombre: d.producto?.nombre || 'Producto',
                codigo: d.producto?.codigo_barras || '',
                cantidad_pedida: d.cantidad_pedida,
                costo_unitario: d.costo_unitario_estimado,
                fecha_vencimiento: d.fecha_vencimiento?.split('T')[0] || null,
            }));
        } else {
            form.reset();
            form.items = [];
            form.fecha_entrega_esperada = '';
            form.observaciones = '';
        }
        productoSeleccionado.value = '';
        cantidadInput.value = 1;
        costoInput.value = 0;
        vencimientoInput.value = '';
    }
});

watch(productoSeleccionado, (id) => {
    if (id) {
        const prod = props.productos.find(p => p.id === Number(id));
        if (prod) costoInput.value = prod.precio_costo || 0;
    }
});

const productosDisponibles = computed(() => {
    const idsEnLista = form.items.map(i => i.producto_id);
    return props.productos.filter(p => !idsEnLista.includes(p.id));
});

const agregarProducto = () => {
    if (!productoSeleccionado.value || cantidadInput.value < 1) {
        Swal.fire('Error', 'Seleccioná un producto y cantidad válida.', 'warning');
        return;
    }
    const prod = props.productos.find(p => p.id === Number(productoSeleccionado.value));
    if (!prod) return;

    form.items.push({
        producto_id: prod.id,
        nombre: prod.nombre,
        codigo: prod.codigo_barras,
        cantidad_pedida: Number(cantidadInput.value),
        costo_unitario: Number(costoInput.value),
        fecha_vencimiento: vencimientoInput.value || null,
    });

    productoSeleccionado.value = '';
    cantidadInput.value = 1;
    costoInput.value = 0;
    vencimientoInput.value = '';
};

const quitarProducto = (index) => form.items.splice(index, 1);

const totalEstimado = computed(() =>
    form.items.reduce((t, i) => t + (i.cantidad_pedida * i.costo_unitario), 0)
);

const guardar = () => {
    if (form.items.length === 0) {
        Swal.fire('Atención', 'Agregá al menos un producto.', 'warning');
        return;
    }

    const url = esEdicion.value
        ? route('ordenes-compra.update', props.ordenParaEditar.id)
        : route('ordenes-compra.store');

    const method = esEdicion.value ? 'put' : 'post';

    form.submit(method, url, {
        onSuccess: () => {
            Swal.fire(esEdicion.value ? 'Actualizada' : 'Creada', esEdicion.value ? 'Orden actualizada.' : 'Orden de compra creada.', 'success');
            emit('cerrar');
        },
        onError: (errors) => {
            const msgs = Object.values(errors).flat().join('\n');
            Swal.fire('Error', msgs || 'Ocurrió un error.', 'error');
        },
    });
};
</script>

<template>
    <div v-if="mostrar" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl overflow-hidden flex flex-col max-h-[95vh]">
            <div class="bg-indigo-600 p-4 text-white font-bold text-center uppercase tracking-widest flex justify-between items-center">
                <span>{{ esEdicion ? 'Editar Orden de Compra' : 'Crear Orden de Compra' }}</span>
                <button @click="$emit('cerrar')" class="text-indigo-200 hover:text-white font-black text-xl">&times;</button>
            </div>

            <div class="p-6 overflow-y-auto flex-1 bg-slate-50">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-1 space-y-4">
                        <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
                            <h3 class="font-black text-slate-700 uppercase tracking-widest mb-3 border-b pb-2 text-xs">Datos de la Orden</h3>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Sucursal *</label>
                                    <select v-model="form.sucursal_id" class="w-full rounded-lg border-slate-200 text-sm font-bold text-slate-700 focus:ring-indigo-500" required>
                                        <option value="" disabled>Seleccionar...</option>
                                        <option v-for="s in sucursales" :key="s.id" :value="s.id">{{ s.nombre }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Proveedor *</label>
                                    <select v-model="form.proveedor_id" class="w-full rounded-lg border-slate-200 text-sm font-bold text-slate-700 focus:ring-indigo-500" required>
                                        <option value="" disabled>Seleccionar...</option>
                                        <option v-for="p in proveedores" :key="p.id" :value="p.id">{{ p.razon_social }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Fecha Entrega Esperada</label>
                                    <input v-model="form.fecha_entrega_esperada" type="date" class="w-full rounded-lg border-slate-200 text-sm font-bold text-slate-700 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Observaciones</label>
                                    <textarea v-model="form.observaciones" rows="3" class="w-full rounded-lg border-slate-200 text-sm font-medium text-slate-700 focus:ring-indigo-500 resize-none" placeholder="Notas internas..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="lg:col-span-2 space-y-4">
                        <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
                            <h3 class="font-black text-slate-700 uppercase tracking-widest mb-3 border-b pb-2 text-xs">Agregar Productos</h3>

                            <div class="grid grid-cols-12 gap-2 items-end mb-4">
                                <div class="col-span-12 md:col-span-5">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Producto</label>
                                    <select v-model="productoSeleccionado" class="w-full rounded-lg border-slate-200 text-sm font-bold text-slate-700 focus:ring-indigo-500">
                                        <option value="" disabled>Seleccione producto...</option>
                                        <option v-for="p in productosDisponibles" :key="p.id" :value="p.id">[{{ p.codigo_barras }}] {{ p.nombre }}</option>
                                    </select>
                                </div>
                                <div class="col-span-4 md:col-span-2">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Cant.</label>
                                    <input v-model="cantidadInput" type="number" min="1" class="w-full rounded-lg border-slate-200 text-sm font-bold text-center focus:ring-indigo-500">
                                </div>
                                <div class="col-span-4 md:col-span-3">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Costo U.</label>
                                    <input v-model="costoInput" type="number" step="0.01" min="0" class="w-full rounded-lg border-slate-200 text-sm font-bold text-rose-700 focus:ring-indigo-500">
                                </div>
                                <div class="col-span-4 md:col-span-2">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Vence</label>
                                    <input v-model="vencimientoInput" type="date" class="w-full rounded-lg border-slate-200 text-sm font-bold text-slate-700 focus:ring-indigo-500">
                                </div>
                                <div class="col-span-12 md:col-span-12">
                                    <button @click="agregarProducto" type="button" class="w-full bg-slate-800 text-white font-bold rounded-lg py-2 hover:bg-slate-700 transition-colors uppercase text-xs">
                                        Añadir Fila
                                    </button>
                                </div>
                            </div>

                            <div class="overflow-x-auto border rounded-xl border-slate-100 max-h-[250px] overflow-y-auto">
                                <table class="w-full text-left border-collapse">
                                    <thead class="sticky top-0 bg-slate-100 z-10">
                                        <tr class="text-[10px] uppercase tracking-widest text-slate-500 border-b border-slate-200">
                                            <th class="p-2 font-black">Producto</th>
                                            <th class="p-2 font-black text-center">Vence</th>
                                            <th class="p-2 font-black text-center">Cant.</th>
                                            <th class="p-2 font-black text-right">Costo U.</th>
                                            <th class="p-2 font-black text-right">Subtotal</th>
                                            <th class="p-2 font-black text-center">X</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="form.items.length === 0">
                                            <td colspan="6" class="p-4 text-center text-slate-400 italic text-xs">Sin productos</td>
                                        </tr>
                                        <tr v-for="(item, index) in form.items" :key="index" class="border-b border-slate-50">
                                            <td class="p-2 font-bold text-slate-700 text-xs">
                                                {{ item.nombre }}
                                                <span class="block text-[9px] text-slate-400 font-mono">{{ item.codigo }}</span>
                                            </td>
                                            <td class="p-2 text-center text-xs">
                                                <span v-if="item.fecha_vencimiento" class="px-2 py-1 bg-amber-100 text-amber-700 rounded text-[10px] font-bold">
                                                    {{ item.fecha_vencimiento.split('-').reverse().join('/') }}
                                                </span>
                                                <span v-else class="text-slate-300">—</span>
                                            </td>
                                            <td class="p-2 text-center font-bold text-indigo-600 text-xs">{{ item.cantidad_pedida }}</td>
                                            <td class="p-2 text-right font-mono font-bold text-slate-700 text-xs">${{ Number(item.costo_unitario).toFixed(2) }}</td>
                                            <td class="p-2 text-right font-mono font-bold text-slate-800 text-xs">${{ (item.cantidad_pedida * item.costo_unitario).toFixed(2) }}</td>
                                            <td class="p-2 text-center">
                                                <button @click="quitarProducto(index)" type="button" class="text-rose-400 hover:text-rose-600 font-bold">&times;</button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-4 border-t border-slate-200 bg-white flex justify-between items-center">
                <button type="button" @click="$emit('cerrar')" class="px-5 py-2 text-sm font-bold text-slate-500 hover:text-slate-700 uppercase tracking-widest">Cancelar</button>
                <div class="flex items-center gap-6">
                    <div class="text-right">
                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block">Total Estimado</span>
                        <span class="text-2xl font-black text-slate-800 font-mono leading-none">${{ totalEstimado.toFixed(2) }}</span>
                    </div>
                    <button @click="guardar" :disabled="form.processing" class="bg-indigo-600 text-white px-8 py-3 rounded-xl font-bold shadow-lg shadow-indigo-600/30 hover:bg-indigo-700 transition-all uppercase tracking-widest text-xs disabled:opacity-50">
                        {{ form.processing ? 'Guardando...' : (esEdicion ? 'Actualizar Orden' : 'Crear Orden') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
