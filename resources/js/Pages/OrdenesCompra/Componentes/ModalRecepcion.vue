<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    mostrar: Boolean,
    orden: Object,
});

const emit = defineEmits(['cerrar']);

const form = useForm({
    items: [],
});

const itemsFormateados = ref([]);

watch(() => props.mostrar, (val) => {
    if (val && props.orden) {
        itemsFormateados.value = (props.orden.detalles || []).map(d => ({
            orden_compra_detalle_id: d.id,
            producto_id: d.producto_id,
            nombre: d.producto?.nombre || 'Producto',
            codigo: d.producto?.codigo_barras || '',
            cantidad_pedida: d.cantidad_pedida,
            cantidad_ya_recibida: d.cantidad_recibida || 0,
            cantidad_pendiente: d.cantidad_pedida - (d.cantidad_recibida || 0),
            cantidad_recibir: d.cantidad_pedida - (d.cantidad_recibida || 0),
            costo_unitario_estimado: d.costo_unitario_estimado,
            fecha_vencimiento: d.fecha_vencimiento,
        }));
    }
});

const totalItems = computed(() => itemsFormateados.value.length);
const itemsConCantidad = computed(() =>
    itemsFormateados.value.filter(i => i.cantidad_recibir > 0).length
);
const esRecepcionTotal = computed(() =>
    itemsFormateados.value.every(i => i.cantidad_recibir >= i.cantidad_pendiente)
);

const setearCantidad = (item, cantidad) => {
    item.cantidad_recibir = Math.max(0, Math.min(Number(cantidad), item.cantidad_pendiente));
};

const setearTodo = (item) => {
    item.cantidad_recibir = item.cantidad_pendiente;
};

const limpiarItem = (item) => {
    item.cantidad_recibir = 0;
};

const recibir = () => {
    const itemsARecibir = itemsFormateados.value.filter(i => i.cantidad_recibir > 0);

    if (itemsARecibir.length === 0) {
        Swal.fire('Atención', 'Indicá al menos una cantidad a recibir.', 'warning');
        return;
    }

    const textoResumen = itemsARecibir.map(i =>
        `${i.cantidad_recibir}x ${i.nombre}`
    ).join(', ');

    const titulo = esRecepcionTotal.value
        ? '¿Confirmar recepción total?'
        : '¿Confirmar recepción parcial?';

    Swal.fire({
        title: titulo,
        html: `<p class="text-sm text-slate-600">Vas a recibir: <b>${textoResumen}</b></p>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, recibir',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#10b981',
    }).then((result) => {
        if (result.isConfirmed) {
            form.items = itemsARecibir.map(i => ({
                orden_compra_detalle_id: i.orden_compra_detalle_id,
                cantidad_recibir: i.cantidad_recibir,
            }));

            form.post(route('ordenes-compra.recibir', props.orden.id), {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire('¡Recibido!', 'Stock actualizado.', 'success');
                    emit('cerrar');
                },
                onError: (errors) => {
                    const msgs = Object.values(errors).flat().join('\n');
                    Swal.fire('Error', msgs || 'Ocurrió un error.', 'error');
                },
            });
        }
    });
};
</script>

<template>
    <div v-if="mostrar" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl overflow-hidden flex flex-col max-h-[95vh]">
            <div class="bg-emerald-600 p-4 text-white font-bold text-center uppercase tracking-widest flex justify-between items-center">
                <span>Recepción de Mercadería — OC #{{ orden?.id?.toString().padStart(4, '0') }}</span>
                <button @click="$emit('cerrar')" class="text-emerald-200 hover:text-white font-black text-xl">&times;</button>
            </div>

            <div class="p-6 overflow-y-auto flex-1 bg-slate-50">
                <div class="mb-4 bg-emerald-50 border border-emerald-200 rounded-xl p-4 flex items-center gap-3">
                    <svg class="w-6 h-6 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <div class="text-sm">
                        <p class="font-bold text-emerald-800">Ingresá las cantidades realmente recibidas.</p>
                        <p class="text-emerald-600 text-xs mt-0.5">El stock se actualizará únicamente con las cantidades indicadas.</p>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-100 border-b">
                                <th class="py-3 px-4 text-[10px] font-black uppercase text-slate-500">Producto</th>
                                <th class="py-3 px-4 text-[10px] font-black uppercase text-center text-slate-500">Pedido</th>
                                <th class="py-3 px-4 text-[10px] font-black uppercase text-center text-slate-500">Ya Recibido</th>
                                <th class="py-3 px-4 text-[10px] font-black uppercase text-center text-slate-500">Pendiente</th>
                                <th class="py-3 px-4 text-[10px] font-black uppercase text-center text-slate-500 bg-emerald-50">Recibir Ahora</th>
                                <th class="py-3 px-4 text-[10px] font-black uppercase text-center text-slate-500">Acc.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in itemsFormateados" :key="item.orden_compra_detalle_id"
                                class="border-b border-slate-100"
                                :class="{ 'bg-emerald-50/50': item.cantidad_recibir > 0 }"
                            >
                                <td class="py-3 px-4">
                                    <div class="font-bold text-slate-700 text-sm">{{ item.nombre }}</div>
                                    <div class="text-[10px] text-slate-400 font-mono">{{ item.codigo }}</div>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="bg-slate-100 text-slate-600 px-2 py-1 rounded-lg font-black text-xs">{{ item.cantidad_pedida }}</span>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="text-slate-500 font-bold text-xs">{{ item.cantidad_ya_recibida }}</span>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="bg-amber-100 text-amber-700 px-2 py-1 rounded-lg font-black text-xs">{{ item.cantidad_pendiente }}</span>
                                </td>
                                <td class="py-3 px-4 text-center bg-emerald-50/50">
                                    <input
                                        type="number"
                                        :value="item.cantidad_recibir"
                                        @input="setearCantidad(item, $event.target.value)"
                                        :max="item.cantidad_pendiente"
                                        min="0"
                                        class="w-20 text-center rounded-lg border-emerald-300 text-sm font-bold text-emerald-700 focus:ring-emerald-500 focus:border-emerald-500"
                                    >
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <div class="flex gap-1 justify-center">
                                        <button @click="setearTodo(item)" class="text-[10px] bg-emerald-100 text-emerald-700 px-2 py-1 rounded font-bold hover:bg-emerald-200 transition-colors" title="Recibir todo lo pendiente">Todo</button>
                                        <button @click="limpiarItem(item)" class="text-[10px] bg-slate-100 text-slate-500 px-2 py-1 rounded font-bold hover:bg-slate-200 transition-colors" title="No recibir nada">0</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 text-center text-xs text-slate-400">
                    {{ itemsConCantidad }} de {{ totalItems }} items con cantidad a recibir
                </div>
            </div>

            <div class="p-4 border-t border-slate-200 bg-white flex justify-between items-center">
                <button type="button" @click="$emit('cerrar')" class="px-5 py-2 text-sm font-bold text-slate-500 hover:text-slate-700 uppercase tracking-widest">Cancelar</button>
                <button @click="recibir" :disabled="form.processing || itemsConCantidad === 0" class="bg-emerald-600 text-white px-8 py-3 rounded-xl font-bold shadow-lg shadow-emerald-600/30 hover:bg-emerald-700 transition-all uppercase tracking-widest text-xs disabled:opacity-50">
                    {{ form.processing ? 'Procesando...' : (esRecepcionTotal ? 'Confirmar Recepción Total' : 'Confirmar Recepción Parcial') }}
                </button>
            </div>
        </div>
    </div>
</template>
