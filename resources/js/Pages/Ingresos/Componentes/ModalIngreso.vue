<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    mostrar: Boolean,
    productos: Array,
    proveedores: Array,
    sucursales: Array
});

const emit = defineEmits(['cerrar']);

const form = useForm({
    sucursal_id: '',
    proveedor_id: '',
    fecha_ingreso: new Date().toISOString().split('T')[0],
    numero_remito: '',
    items: []
});

const productoSeleccionado = ref('');
const cantidadInput = ref(1);
const costoInput = ref(0);

// Limpiar modal al abrir
watch(() => props.mostrar, (val) => {
    if (val) {
        form.reset();
        form.items = [];
    }
});

const agregarProducto = () => {
    if (!productoSeleccionado.value || cantidadInput.value < 1 || costoInput.value < 0) {
        Swal.fire('Error', 'Completá bien los datos del producto antes de agregar.', 'warning');
        return;
    }

    const prod = props.productos.find(p => p.id === productoSeleccionado.value);
    const existe = form.items.find(i => i.producto_id === prod.id);
    
    if (existe) {
        existe.cantidad += cantidadInput.value;
        existe.costo = costoInput.value;
    } else {
        form.items.push({
            producto_id: prod.id,
            nombre: prod.nombre,
            codigo: prod.codigo_barras,
            cantidad: cantidadInput.value,
            costo: costoInput.value,
        });
    }

    productoSeleccionado.value = '';
    cantidadInput.value = 1;
    costoInput.value = 0;
};

const quitarProducto = (index) => form.items.splice(index, 1);

const totalRemito = computed(() => form.items.reduce((total, item) => total + (item.cantidad * item.costo), 0));

const guardarIngreso = () => {
    if (form.items.length === 0) {
        Swal.fire('Atención', 'No agregaste ningún producto al remito.', 'warning');
        return;
    }

    form.post(route('ingresos.store'), {
        onSuccess: (page) => {
            Swal.fire('¡Éxito!', 'Stock actualizado correctamente.', 'success');
            emit('cerrar');
            
            if (page.props.flash && page.props.flash.alertas_inflacion?.length > 0) {
                let mensaje = '<ul>';
                page.props.flash.alertas_inflacion.forEach(a => {
                    mensaje += `<li class="text-left text-sm mb-2"><b>${a.producto}</b>: Subió a $${a.costo_nuevo}</li>`;
                });
                mensaje += '</ul>';
                
                Swal.fire({
                    title: '⚠️ Alerta de Aumentos',
                    html: `<p class="text-sm mb-3">Actualizá los precios de venta de estos productos:</p>${mensaje}`,
                    icon: 'warning',
                    confirmButtonColor: '#eab308'
                });
            }
        }
    });
};
</script>

<template>
    <div v-if="mostrar" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl overflow-hidden flex flex-col max-h-[95vh]">
            <div class="bg-sky-600 p-4 text-white font-bold text-center uppercase tracking-widest flex justify-between items-center">
                <span>Cargar Remito y Actualizar Stock</span>
                <button @click="$emit('cerrar')" class="text-sky-200 hover:text-white font-black">&times;</button>
            </div>
            
            <div class="p-6 overflow-y-auto flex-1 bg-slate-50">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    <div class="lg:col-span-1 space-y-4">
                        <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
                            <h3 class="font-black text-slate-700 uppercase tracking-widest mb-3 border-b pb-2 text-xs">Datos del Remito</h3>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Sucursal Destino *</label>
                                    <select v-model="form.sucursal_id" class="w-full rounded-lg border-slate-200 text-sm font-bold text-slate-700 focus:ring-sky-500" required>
                                        <option value="" disabled>Seleccionar sucursal...</option>
                                        <option v-for="s in sucursales" :key="s.id" :value="s.id">{{ s.nombre }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Proveedor</label>
                                    <select v-model="form.proveedor_id" class="w-full rounded-lg border-slate-200 text-sm font-bold text-slate-700 focus:ring-sky-500">
                                        <option value="">Sin proveedor</option>
                                        <option v-for="p in proveedores" :key="p.id" :value="p.id">{{ p.razon_social }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Fecha</label>
                                    <input v-model="form.fecha_ingreso" type="date" class="w-full rounded-lg border-slate-200 text-sm font-bold text-slate-700 focus:ring-sky-500">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Nro Remito</label>
                                    <input v-model="form.numero_remito" type="text" class="w-full rounded-lg border-slate-200 text-sm font-bold text-slate-700 focus:ring-sky-500 font-mono">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="lg:col-span-2 space-y-4">
                        <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
                            <h3 class="font-black text-slate-700 uppercase tracking-widest mb-3 border-b pb-2 text-xs">Agregar Productos</h3>
                            <div class="grid grid-cols-12 gap-2 items-end mb-4">
                                <div class="col-span-12 md:col-span-5">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Buscar Producto</label>
                                    <select v-model="productoSeleccionado" class="w-full rounded-lg border-slate-200 text-sm font-bold text-slate-700 focus:ring-sky-500">
                                        <option value="" disabled>Seleccione producto...</option>
                                        <option v-for="p in productos" :key="p.id" :value="p.id">[{{ p.codigo_barras }}] {{ p.nombre }}</option>
                                    </select>
                                </div>
                                <div class="col-span-4 md:col-span-2">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Cant.</label>
                                    <input v-model="cantidadInput" type="number" min="1" class="w-full rounded-lg border-slate-200 text-sm font-bold text-center focus:ring-sky-500">
                                </div>
                                <div class="col-span-4 md:col-span-3">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Costo U. ($)</label>
                                    <input v-model="costoInput" type="number" step="0.01" min="0" class="w-full rounded-lg border-slate-200 text-sm font-bold text-rose-700 focus:ring-sky-500">
                                </div>
                                <div class="col-span-4 md:col-span-2">
                                    <button @click="agregarProducto" type="button" class="w-full bg-slate-800 text-white font-bold rounded-lg py-2 hover:bg-slate-700 transition-colors uppercase text-xs h-[38px]">
                                        Añadir
                                    </button>
                                </div>
                            </div>

                            <div class="overflow-x-auto border rounded-xl border-slate-100 max-h-[250px] overflow-y-auto custom-scrollbar">
                                <table class="w-full text-left border-collapse">
                                    <thead class="sticky top-0 bg-slate-100 z-10">
                                        <tr class="text-[10px] uppercase tracking-widest text-slate-500 border-b border-slate-200">
                                            <th class="p-2 font-black">Cód.</th>
                                            <th class="p-2 font-black">Producto</th>
                                            <th class="p-2 font-black text-center">Cant.</th>
                                            <th class="p-2 font-black text-right">Subtotal</th>
                                            <th class="p-2 font-black text-center">X</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="form.items.length === 0">
                                            <td colspan="5" class="p-4 text-center text-slate-400 italic text-xs">Sin productos</td>
                                        </tr>
                                        <tr v-for="(item, index) in form.items" :key="index" class="border-b border-slate-50">
                                            <td class="p-2 font-mono text-[10px] text-slate-500">{{ item.codigo }}</td>
                                            <td class="p-2 font-bold text-slate-700 text-xs">{{ item.nombre }}</td>
                                            <td class="p-2 text-center font-bold text-sky-600 text-xs">{{ item.cantidad }}</td>
                                            <td class="p-2 text-right font-mono font-bold text-slate-700 text-xs">${{ (item.cantidad * item.costo).toFixed(2) }}</td>
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
                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block">Total</span>
                        <span class="text-2xl font-black text-slate-800 font-mono leading-none">${{ totalRemito.toFixed(2) }}</span>
                    </div>
                    <button @click="guardarIngreso" :disabled="form.processing" class="bg-sky-600 text-white px-8 py-3 rounded-xl font-bold shadow-lg shadow-sky-600/30 hover:bg-sky-700 transition-all uppercase tracking-widest text-xs disabled:opacity-50">
    					{{ form.processing ? 'Procesando...' : 'Confirmar Stock' }}
					</button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
</style>