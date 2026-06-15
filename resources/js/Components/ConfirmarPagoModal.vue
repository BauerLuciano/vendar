<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
    show: Boolean,
    ventaId: Number,
    displayInfo: Array,
});

const emit = defineEmits(['close', 'confirmed']);

const confirmando = ref(false);
const cancelando = ref(false);

const groupedByMethod = computed(() => {
    if (!props.displayInfo) return [];

    const groups = {};
    for (const info of props.displayInfo) {
        if (!groups[info.metodo_pago]) {
            groups[info.metodo_pago] = {
                metodo_pago: info.metodo_pago,
                label: info.label,
                monto: 0,
                configs: [],
            };
        }
        groups[info.metodo_pago].monto += info.monto;
        groups[info.metodo_pago].configs.push(info);
    }

    return Object.values(groups);
});

function confirmarPago() {
    if (!props.ventaId) return;
    confirmando.value = true;

    router.post(route('ventas.confirmar-pago', props.ventaId), {}, {
        onSuccess: () => {
            confirmando.value = false;
            emit('confirmed');
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Pago confirmado',
                showConfirmButton: false,
                timer: 2000,
            });
        },
        onError: (errors) => {
            confirmando.value = false;
            Swal.fire({
                icon: 'error',
                title: 'Error al confirmar',
                text: errors.error || 'Ocurrió un error',
            });
        },
    });
}

function cancelarVenta() {
    if (!props.ventaId) return;

    Swal.fire({
        title: '¿Cancelar venta?',
        text: 'Se devolverá el stock y la venta quedará anulada.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, cancelar',
        cancelButtonText: 'No, mantener',
        confirmButtonColor: '#ef4444',
        input: 'text',
        inputPlaceholder: 'Motivo de anulación (opcional)',
        inputAttributes: { maxlength: 255 },
    }).then((result) => {
        if (!result.isConfirmed) return;

        cancelando.value = true;
        router.patch(route('ventas.cancelar', props.ventaId), {
            motivo: result.value || 'Cancelado desde POS',
        }, {
            onSuccess: () => {
                cancelando.value = false;
                emit('close');
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'info',
                    title: 'Venta cancelada',
                    showConfirmButton: false,
                    timer: 2000,
                });
            },
            onError: () => {
                cancelando.value = false;
                Swal.fire({ icon: 'error', title: 'Error al cancelar' });
            },
        });
    });
}

function formatMonto(monto) {
    return '$' + Number(monto).toLocaleString('es-AR', { minimumFractionDigits: 2 });
}
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md mx-4 overflow-hidden">
            <div class="bg-gradient-to-r from-emerald-500 to-emerald-600 px-6 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-black text-white">Pendiente de Pago</h2>
                            <p class="text-emerald-100 text-sm font-medium">Venta #{{ ventaId }}</p>
                        </div>
                    </div>
                    <button @click="$emit('close')" class="text-white/70 hover:text-white transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="p-6 space-y-6">
                <div v-for="group in groupedByMethod" :key="group.metodo_pago" class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-black text-slate-700 uppercase tracking-wider">{{ group.label }}</h3>
                        <span class="text-lg font-black text-slate-900">{{ formatMonto(group.monto) }}</span>
                    </div>

                    <div v-if="group.configs.length === 1 && group.configs[0].display_data" class="bg-slate-50 rounded-2xl p-4 border border-slate-200 space-y-2">
                        <div v-for="(value, key) in group.configs[0].display_data" :key="key" class="flex justify-between items-center text-sm">
                            <span class="text-slate-500 font-medium capitalize">{{ key }}</span>
                            <span class="text-slate-800 font-bold text-right ml-4 break-all">{{ value }}</span>
                        </div>
                    </div>

                    <div v-else-if="group.configs.length > 1" class="space-y-2">
                        <p class="text-xs text-slate-500 font-medium">Seleccioná el método que usó el cliente:</p>
                        <div v-for="cfg in group.configs" :key="cfg.config_id ?? cfg.provider"
                            class="bg-white border border-slate-200 rounded-xl p-4 hover:border-emerald-300 transition-colors cursor-pointer"
                            @click="confirmarPago()"
                        >
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-bold text-slate-700">{{ cfg.provider || group.label }}</span>
                                <span class="text-sm font-black text-slate-900">{{ formatMonto(cfg.monto) }}</span>
                            </div>
                            <div v-if="cfg.display_data" class="space-y-1">
                                <div v-for="(value, key) in cfg.display_data" :key="key" class="flex justify-between text-xs">
                                    <span class="text-slate-400 capitalize">{{ key }}</span>
                                    <span class="text-slate-600 font-medium">{{ value }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="!groupedByMethod.length" class="text-center py-8 text-slate-400">
                    <p>No hay información de pago disponible</p>
                </div>

                <div class="flex gap-3 pt-4 border-t border-slate-200">
                    <button @click="cancelarVenta()" :disabled="confirmando || cancelando"
                        class="flex-1 px-4 py-3 border-2 border-slate-200 text-slate-600 font-black rounded-xl hover:border-rose-300 hover:text-rose-600 hover:bg-rose-50 transition-all disabled:opacity-50 uppercase tracking-wider text-sm"
                    >
                        {{ cancelando ? 'Cancelando...' : 'Cancelar venta' }}
                    </button>
                    <button @click="confirmarPago()" :disabled="confirmando || cancelando"
                        class="flex-1 px-4 py-3 bg-emerald-600 hover:bg-emerald-700 disabled:bg-slate-300 text-white font-black rounded-xl shadow-lg transition-all disabled:opacity-50 uppercase tracking-wider text-sm"
                    >
                        {{ confirmando ? 'Confirmando...' : 'Confirmar pago' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
