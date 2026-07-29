<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { formatearDinero } from '@/Utils/formatters.js';

const props = defineProps({
    mostrar: Boolean,
    venta: Object,
});

const emit = defineEmits(['cerrar', 'completado']);

const itemsADevolver = ref([]);
const enviando = ref(false);

function sanitizar(input) {
    let val = parseFloat(input);
    if (isNaN(val) || val < 0) val = 0;
    return val;
}

watch(() => props.mostrar, (val) => {
    if (val && props.venta?.detalles) {
        itemsADevolver.value = props.venta.detalles.map(d => ({
            detalle_id: d.id,
            producto: d.producto?.nombre || 'Producto',
            precio_unitario: d.precio_unitario,
            subtotal: d.subtotal,
            max: Number(d.cantidad) - Number(d.cantidad_devuelta || 0),
            cantidad: 0,
        }));
    }
});

const totalADevolver = () => itemsADevolver.value.reduce((s, i) => s + (Number(i.precio_unitario) * (Number(i.cantidad) || 0)), 0);

const puedeEnviar = () => !enviando.value && itemsADevolver.value.some(i => (Number(i.cantidad) || 0) > 0);

const enviar = () => {
    if (!puedeEnviar()) return;

    const items = itemsADevolver.value
        .filter(i => (Number(i.cantidad) || 0) > 0)
        .map(i => ({ detalle_id: i.detalle_id, cantidad: Number(i.cantidad) }));

    enviando.value = true;

    router.post(route('ventas.devolver', props.venta.id), { items }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            enviando.value = false;
            itemsADevolver.value = [];
            emit('completado');
        },
        onError: () => {
            enviando.value = false;
        },
    });
};

const cerrarConEscape = (e) => {
    if (e.key === 'Escape' && props.mostrar) {
        emit('cerrar');
    }
};

onMounted(() => document.addEventListener('keydown', cerrarConEscape));
onUnmounted(() => document.removeEventListener('keydown', cerrarConEscape));
</script>

<template>
    <div v-if="mostrar" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-4 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-xl overflow-hidden animate-in fade-in zoom-in duration-200">
            
            <div class="bg-amber-600 p-4 text-white font-black text-center uppercase tracking-widest flex justify-between items-center px-6">
                <span>Devolución — Ticket #{{ venta?.id.toString().padStart(6, '0') }}</span>
                <button @click="$emit('cerrar')" class="hover:rotate-90 transition-transform duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <div class="p-6 space-y-6">
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 px-1">Seleccioná los productos a devolver</label>
                    <div class="border rounded-xl overflow-hidden">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 border-b text-[10px] font-black text-slate-500 uppercase">
                                <tr>
                                    <th class="p-3">Producto</th>
                                    <th class="p-3 text-right w-20">Dev.</th>
                                    <th class="p-3 text-right w-16">Máx</th>
                                    <th class="p-3 text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="item in itemsADevolver" :key="item.detalle_id" class="hover:bg-slate-50 transition-colors">
                                    <td class="p-3">
                                        <p class="font-bold text-slate-700 leading-tight text-sm">{{ item.producto }}</p>
                                    </td>
                                    <td class="p-3">
                                        <input
                                            type="number"
                                            min="0"
                                            :max="item.max"
                                            step="1"
                                            v-model.number="item.cantidad"
                                            @input="item.cantidad = Math.min(Math.max(sanitizar($event.target.value), 0), item.max)"
                                            class="w-20 text-right border border-slate-200 rounded-lg px-2 py-1 font-bold text-slate-800 focus:ring-amber-500 focus:border-amber-500 text-sm"
                                        />
                                    </td>
                                    <td class="p-3 text-right text-slate-400 font-mono text-sm">{{ item.max }}</td>
                                    <td class="p-3 text-right font-black text-slate-800 text-sm">{{ formatearDinero(Number(item.precio_unitario) * (Number(item.cantidad) || 0)) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex justify-between items-center pt-4 border-t-2 border-dashed border-slate-100">
                    <span class="text-slate-400 font-black uppercase tracking-tighter text-lg">Total a Devolver</span>
                    <span class="text-2xl font-black text-amber-600 tracking-tighter">{{ formatearDinero(totalADevolver()) }}</span>
                </div>

                <div class="flex justify-center gap-4 pt-2">
                    <button @click="$emit('cerrar')" class="bg-slate-100 text-slate-600 px-6 py-2.5 rounded-xl font-bold hover:bg-slate-200 transition-all uppercase text-xs tracking-widest">
                        Cancelar
                    </button>
                    <button @click="enviar" :disabled="!puedeEnviar()"
                        class="px-10 py-2.5 rounded-xl font-bold shadow-lg transition-all uppercase text-xs tracking-widest active:scale-95 flex items-center gap-2"
                        :class="puedeEnviar() ? 'bg-amber-500 text-white hover:bg-amber-600' : 'bg-slate-200 text-slate-400 cursor-not-allowed'">
                        <svg v-if="enviando" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                        {{ enviando ? 'Procesando...' : 'Procesar Devolución' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
