<script setup>
const props = defineProps({
    mostrar: Boolean,
    orden: Object,
});

const emit = defineEmits(['cerrar']);

const formatearFecha = (fecha) => {
    if (!fecha) return '';
    const d = new Date(fecha);
    return d.toLocaleDateString('es-AR', { day: '2-digit', month: 'short', year: 'numeric' });
};

const formatearHora = (fecha) => {
    if (!fecha) return '';
    const d = new Date(fecha);
    return d.toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' });
};

const colorEstado = (estado) => {
    const colores = {
        'Sugerida':                'bg-slate-400',
        'Borrador':                'bg-amber-400',
        'Enviada':                 'bg-sky-500',
        'Cotizada':                'bg-purple-500',
        'Aprobada':                'bg-indigo-500',
        'Confirmada':              'bg-indigo-500',
        'Parcialmente Recibida':   'bg-amber-500',
        'Recibida':                'bg-emerald-500',
        'Cancelada':               'bg-rose-500',
    };
    return colores[estado] || 'bg-slate-400';
};

const iconoEstado = (estado) => {
    const iconos = {
        'Sugerida':                '📝',
        'Borrador':                '📝',
        'Enviada':                 '📤',
        'Cotizada':                '💰',
        'Aprobada':                '✅',
        'Confirmada':              '✅',
        'Parcialmente Recibida':   '📦',
        'Recibida':                '🏁',
        'Cancelada':               '🚫',
    };
    return iconos[estado] || '●';
};
</script>

<template>
    <div v-if="mostrar" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col max-h-[85vh]">
            <div class="bg-slate-800 p-4 text-white font-bold text-center uppercase tracking-widest flex justify-between items-center">
                <span>Historial — OC #{{ orden?.id?.toString().padStart(4, '0') }}</span>
                <button @click="$emit('cerrar')" class="text-slate-300 hover:text-white font-black text-xl">&times;</button>
            </div>

            <div class="p-6 overflow-y-auto flex-1 bg-slate-50">
                <div v-if="!orden?.historial || orden.historial.length === 0" class="text-center text-slate-400 italic py-8">
                    Sin historial registrado.
                </div>

                <div v-else class="relative pl-8">
                    <div class="absolute left-3 top-2 bottom-2 w-0.5 bg-slate-200 rounded-full"></div>

                    <div v-for="(entry, index) in orden.historial" :key="entry.id" class="relative mb-6 last:mb-0">
                        <div class="absolute -left-5 top-1 w-4 h-4 rounded-full border-2 border-white shadow-sm"
                             :class="colorEstado(entry.estado)"></div>

                        <div class="bg-white rounded-xl border border-slate-200 p-3 shadow-sm">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-sm">{{ iconoEstado(entry.estado) }}</span>
                                <span class="font-black text-sm text-slate-800">{{ entry.estado }}</span>
                            </div>
                            <div v-if="entry.motivo" class="text-xs text-slate-500 font-medium mb-1 pl-6">
                                {{ entry.motivo }}
                            </div>
                            <div class="flex items-center gap-3 text-[10px] text-slate-400 font-bold pl-6">
                                <span>{{ entry.usuario?.name || 'Sistema' }}</span>
                                <span>•</span>
                                <span>{{ formatearFecha(entry.created_at) }}</span>
                                <span>{{ formatearHora(entry.created_at) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-4 border-t border-slate-200 bg-white flex justify-end">
                <button @click="$emit('cerrar')" class="px-6 py-2 text-sm font-bold text-slate-600 hover:text-slate-800 uppercase tracking-widest rounded-lg hover:bg-slate-100 transition-colors">
                    Cerrar
                </button>
            </div>
        </div>
    </div>
</template>
