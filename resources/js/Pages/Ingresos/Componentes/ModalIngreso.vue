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

const codigoBarrasInput = ref(''); // 🔥 NUEVO: Para el Escáner
const productoSeleccionado = ref('');
const cantidadInput = ref(1);
const costoInput = ref(0);
const vencimientoInput = ref(''); // 🔥 NUEVO: Fecha de vencimiento

watch(() => props.mostrar, (val) => {
    if (val) {
        form.reset();
        form.items = [];
    }
});

// 🔥 NUEVO: Simulador de Pistola Escáner
const buscarPorCodigo = () => {
    if (!codigoBarrasInput.value) return;
    
    const prod = props.productos.find(p => p.codigo_barras === codigoBarrasInput.value);
    if (prod) {
        productoSeleccionado.value = prod.id;
        costoInput.value = prod.precio_costo || 0; // Le auto-llenamos el costo anterior
        codigoBarrasInput.value = ''; // Limpiamos para el próximo escaneo
    } else {
        Swal.fire('No encontrado', 'El código de barras no pertenece a ningún producto registrado.', 'warning');
        codigoBarrasInput.value = '';
    }
};

const agregarProducto = () => {
    if (!productoSeleccionado.value || cantidadInput.value < 1 || costoInput.value < 0) {
        Swal.fire('Error', 'Completá bien los datos del producto antes de agregar.', 'warning');
        return;
    }

    const prod = props.productos.find(p => p.id === productoSeleccionado.value);
    
    // Si ya existe el producto EN LA MISMA FECHA DE VENCIMIENTO, sumamos. 
    // Si la fecha es distinta, es una fila nueva.
    const existe = form.items.find(i => i.producto_id === prod.id && i.fecha_vencimiento === vencimientoInput.value);
    
    if (existe) {
        existe.cantidad += Number(cantidadInput.value);
        existe.costo = Number(costoInput.value);
    } else {
        form.items.push({
            producto_id: prod.id,
            nombre: prod.nombre,
            codigo: prod.codigo_barras,
            cantidad: Number(cantidadInput.value),
            costo: Number(costoInput.value),
            fecha_vencimiento: vencimientoInput.value || null, // 🔥 Guardamos el lote
        });
    }

    // Limpiamos los inputs
    productoSeleccionado.value = '';
    cantidadInput.value = 1;
    costoInput.value = 0;
    vencimientoInput.value = '';
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
            Swal.fire('¡Éxito!', 'Stock actualizado y lotes generados.', 'success');
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
                            <h3 class="font-black text-slate-700 uppercase tracking-widest mb-3 border-b pb-2 text-xs flex justify-between items-center">
                                Agregar Productos
                                <span class="text-sky-600 flex items-center gap-1 text-[10px] bg-sky-50 px-2 py-1 rounded-md">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 6.75h.75v.75h-.75v-.75ZM6.75 16.5h.75v.75h-.75v-.75ZM16.5 6.75h.75v.75h-.75v-.75ZM13.5 13.5h.75v.75h-.75v-.75ZM13.5 19.5h.75v.75h-.75v-.75ZM19.5 13.5h.75v.75h-.75v-.75ZM19.5 19.5h.75v.75h-.75v-.75ZM16.5 16.5h.75v.75h-.75v-.75Z" /></svg>
                                    Lector Activo
                                </span>
                            </h3>
                            
                            <div class="grid grid-cols-12 gap-2 items-end mb-4">
                                <div class="col-span-12 md:col-span-4">
                                    <label class="block text-[10px] font-bold text-sky-600 uppercase mb-1">Escanear Cód. Barras</label>
                                    <input 
                                        v-model="codigoBarrasInput" 
                                        @keyup.enter="buscarPorCodigo"
                                        type="text" 
                                        placeholder="Escaneá o tipeá y dale Enter..."
                                        class="w-full rounded-lg border-sky-300 bg-sky-50 text-sm font-mono font-bold text-sky-900 focus:ring-sky-500 focus:border-sky-500"
                                    >
                                </div>

                                <div class="col-span-12 md:col-span-8">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">O Buscar Producto</label>
                                    <select v-model="productoSeleccionado" class="w-full rounded-lg border-slate-200 text-sm font-bold text-slate-700 focus:ring-sky-500">
                                        <option value="" disabled>Seleccione producto...</option>
                                        <option v-for="p in productos" :key="p.id" :value="p.id">[{{ p.codigo_barras }}] {{ p.nombre }}</option>
                                    </select>
                                </div>

                                <div class="col-span-4 md:col-span-2 mt-2">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Cant.</label>
                                    <input v-model="cantidadInput" type="number" min="1" class="w-full rounded-lg border-slate-200 text-sm font-bold text-center focus:ring-sky-500">
                                </div>
                                <div class="col-span-4 md:col-span-3 mt-2">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Costo U. ($)</label>
                                    <input v-model="costoInput" type="number" step="0.01" min="0" class="w-full rounded-lg border-slate-200 text-sm font-bold text-rose-700 focus:ring-sky-500">
                                </div>
                                
                                <div class="col-span-4 md:col-span-4 mt-2">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Vencimiento (Opcional)</label>
                                    <input v-model="vencimientoInput" type="date" class="w-full rounded-lg border-slate-200 text-sm font-bold text-slate-700 focus:ring-sky-500">
                                </div>

                                <div class="col-span-12 md:col-span-3 mt-2">
                                    <button @click="agregarProducto" type="button" class="w-full bg-slate-800 text-white font-bold rounded-lg py-2 hover:bg-slate-700 transition-colors uppercase text-xs h-[38px]">
                                        Añadir Fila
                                    </button>
                                </div>
                            </div>

                            <div class="overflow-x-auto border rounded-xl border-slate-100 max-h-[200px] overflow-y-auto custom-scrollbar">
                                <table class="w-full text-left border-collapse">
                                    <thead class="sticky top-0 bg-slate-100 z-10">
                                        <tr class="text-[10px] uppercase tracking-widest text-slate-500 border-b border-slate-200">
                                            <th class="p-2 font-black">Producto</th>
                                            <th class="p-2 font-black text-center">Vence</th> <th class="p-2 font-black text-center">Cant.</th>
                                            <th class="p-2 font-black text-right">Subtotal</th>
                                            <th class="p-2 font-black text-center">X</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="form.items.length === 0">
                                            <td colspan="5" class="p-4 text-center text-slate-400 italic text-xs">Sin productos</td>
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
                                                <span v-else class="text-slate-300">-</span>
                                            </td>
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