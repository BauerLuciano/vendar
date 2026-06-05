<script setup>
import { ref } from 'vue';

const props = defineProps({
    mostrar: Boolean,
    venta: Object
});

const emit = defineEmits(['cerrar']);

const formatearFecha = (fecha) => {
    return new Date(fecha).toLocaleString('es-AR', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit', hour12: false
    });
};

const formatearDinero = (monto) => {
    return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(monto);
};

const metodoPagoLabel = (metodo) => {
    const labels = {
        'EFECTIVO': 'Efectivo',
        'DEBITO': 'Débito',
        'CREDITO': 'Crédito',
        'TRANSFERENCIA': 'Transferencia',
        'MERCADO_PAGO': 'Mercado Pago',
        'CUENTA_CORRIENTE': 'Cuenta Corriente',
    };
    return labels[metodo] || metodo;
};
</script>

<template>
    <div v-if="mostrar" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-4 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-xl overflow-hidden animate-in fade-in zoom-in duration-200">
            
            <div class="bg-sky-600 p-4 text-white font-black text-center uppercase tracking-widest flex justify-between items-center px-6">
                <span>Detalle de Ticket #{{ venta?.id.toString().padStart(6, '0') }}</span>
                <button @click="$emit('cerrar')" class="hover:rotate-90 transition-transform duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <div class="p-6 space-y-6">
                <div v-if="venta?.estado === 'anulada'" class="bg-rose-50 border-2 border-rose-200 p-4 rounded-xl">
                    <h3 class="text-rose-800 font-black text-xs uppercase tracking-widest mb-1">Venta Anulada</h3>
                    <p class="text-rose-600 font-bold text-sm italic">Motivo: {{ venta?.motivo_anulacion || 'No especificado' }}</p>
                </div>

                <div class="grid grid-cols-2 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-100">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest">Cliente</label>
                        <p class="font-bold text-slate-700">{{ venta?.consumidor?.nombre || 'Consumidor Final' }}</p>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest">Fecha y Hora</label>
                        <p class="font-bold text-slate-700">{{ formatearFecha(venta?.created_at) }}hs</p>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest">Método de Pago</label>
                        <span class="text-xs font-black text-sky-600">{{ metodoPagoLabel(venta?.metodo_pago) }}</span>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest">Atendido por</label>
                        <p class="font-bold text-slate-700">{{ venta?.user?.name || 'Sistema' }}</p>
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 px-1">Productos Vendidos</label>
                    <div class="border rounded-xl overflow-hidden">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 border-b text-[10px] font-black text-slate-500 uppercase">
                                <tr>
                                    <th class="p-3">Cant.</th>
                                    <th class="p-3">Producto</th>
                                    <th class="p-3 text-right">Precio</th>
                                    <th class="p-3 text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="item in venta?.detalles" :key="item.id" class="hover:bg-slate-50 transition-colors">
                                    <td class="p-3 font-bold text-sky-600">{{ item.cantidad }}</td>
                                    <td class="p-3">
                                        <p class="font-bold text-slate-700 leading-tight">{{ item.producto?.nombre }}</p>
                                        <p class="text-[10px] text-slate-400 font-mono">{{ item.producto?.sku }}</p>
                                    </td>
                                    <td class="p-3 text-right text-slate-500">{{ formatearDinero(item.precio_unitario) }}</td>
                                    <td class="p-3 text-right font-black text-slate-800">{{ formatearDinero(item.subtotal) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex justify-between items-center pt-4 border-t-2 border-dashed border-slate-100">
                    <span class="text-slate-400 font-black uppercase tracking-tighter text-xl">Total Cobrado</span>
                    <span class="text-3xl font-black text-slate-900 tracking-tighter">{{ formatearDinero(venta?.total) }}</span>
                </div>

                <div class="flex justify-center pt-2">
                    <button @click="$emit('cerrar')" class="bg-slate-800 text-white px-10 py-2.5 rounded-xl font-bold hover:bg-slate-900 shadow-lg transition-all uppercase text-xs tracking-widest active:scale-95">
                        Cerrar Detalle
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>