<script setup>
defineProps({ mostrar: Boolean, ingreso: Object });
defineEmits(['cerrar']);

const formatearFechaHora = (fecha) => {
    return new Date(fecha).toLocaleString('es-AR', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
};
</script>

<template>
    <div v-if="mostrar && ingreso" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-3xl overflow-hidden flex flex-col max-h-[90vh] border border-slate-200">
            
            <div class="bg-slate-900 p-6 text-white">
                <div class="flex justify-between items-start">
                    <div>
                        <span class="bg-sky-500 text-[10px] font-black px-2 py-0.5 rounded-full uppercase tracking-tighter">Comprobante de Ingreso</span>
                        <h2 class="text-3xl font-black tracking-tighter mt-1">
                            {{ ingreso.numero_remito || 'SIN NÚMERO' }}
                        </h2>
                        <p class="text-slate-400 text-xs font-bold uppercase tracking-widest mt-1">ID Transacción: #{{ ingreso.id }}</p>
                    </div>
                    <button @click="$emit('cerrar')" class="bg-slate-800 hover:bg-rose-500 text-white w-10 h-10 rounded-xl transition-all flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
            </div>
            
            <div class="p-6 bg-white border-b border-slate-100 grid grid-cols-2 md:grid-cols-4 gap-6">
                <div>
                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Fecha Remito</p>
                    <p class="font-bold text-slate-700">{{ ingreso.fecha_ingreso }}</p>
                </div>
                <div>
                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Sucursal</p>
                    <p class="font-bold text-sky-600">{{ ingreso.sucursal?.nombre }}</p>
                </div>
                <div>
                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Cargado Por</p>
                    <p class="font-bold text-slate-700">{{ ingreso.usuario?.name || 'Sistema' }}</p>
                </div>
                <div>
                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Registro Sistema</p>
                    <p class="font-bold text-slate-500 text-xs">{{ formatearFechaHora(ingreso.created_at) }}</p>
                </div>
            </div>

            <div class="px-6 py-4 bg-slate-50 border-b border-slate-100">
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Proveedor</p>
                <p class="font-black text-slate-800 uppercase">{{ ingreso.proveedor?.razon_social || 'Proveedor No Identificado' }}</p>
            </div>

            <div class="p-6 overflow-y-auto flex-1 custom-scrollbar">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-[10px] uppercase tracking-widest text-slate-400 border-b-2 border-slate-100">
                            <th class="pb-3 font-black">Cód. Barras / Producto</th>
                            <th class="pb-3 font-black text-center">Cant.</th>
                            <th class="pb-3 font-black text-right">Costo Unit.</th>
                            <th class="pb-3 font-black text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="det in ingreso.detalles" :key="det.id" class="hover:bg-slate-50 transition-colors">
                            <td class="py-4">
                                <p class="text-[10px] font-mono text-slate-400 leading-none mb-1">{{ det.producto?.codigo_barras }}</p>
                                <p class="font-bold text-slate-700 text-sm uppercase leading-none">{{ det.producto?.nombre }}</p>
                            </td>
                            <td class="py-4 text-center font-black text-sky-600">
                                <span class="bg-sky-50 px-3 py-1 rounded-lg border border-sky-100">{{ det.cantidad_recibida }}</span>
                            </td>
                            <td class="py-4 text-right font-mono text-xs text-slate-500">${{ det.costo_unitario }}</td>
                            <td class="py-4 text-right font-mono font-black text-slate-800">${{ (det.cantidad_recibida * det.costo_unitario).toFixed(2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="p-6 bg-slate-900 text-white flex justify-between items-center">
                <div>
                    <p class="text-xs text-slate-500 font-bold uppercase tracking-tighter italic">Valores expresados en ARS</p>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-4xl font-black font-mono tracking-tighter">${{ ingreso.total_costo }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
</style>