<script setup>
import { ref, watch, onUnmounted } from 'vue';
import QRCode from 'qrcode';

const props = defineProps({
    show: Boolean,
    displayData: Object,
});

const emit = defineEmits(['close']);

const qrDataUrl = ref('');
const copiado = ref('');
const error = ref('');

watch(() => props.show, async (val) => {
    if (!val || !props.displayData) return;
    copiado.value = '';
    error.value = '';
    await generarQR();
});

async function generarQR() {
    const data = props.displayData?.alias || props.displayData?.cvu || '';
    if (!data) {
        error.value = 'No hay datos de Mercado Pago configurados';
        return;
    }
    try {
        qrDataUrl.value = await QRCode.toDataURL(data, {
            width: 280,
            margin: 2,
            color: { dark: '#1e293b', light: '#ffffff' },
        });
    } catch {
        error.value = 'Error al generar el código QR';
    }
}

async function copiar(texto, tipo) {
    try {
        await navigator.clipboard.writeText(texto);
        copiado.value = tipo;
        setTimeout(() => { copiado.value = ''; }, 2000);
    } catch {
        //
    }
}

function handleKeydown(e) {
    if (e.key === 'Escape' && props.show) {
        emit('close');
    }
}

watch(() => props.show, (val) => {
    if (val) window.addEventListener('keydown', handleKeydown);
    else window.removeEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <div
        v-if="show"
        class="bg-gradient-to-br from-blue-50 to-sky-50 border-2 border-sky-200 rounded-2xl p-5 shadow-lg shadow-sky-100/50 space-y-4 relative"
    >
        <button
            @click="$emit('close')"
            class="absolute top-3 right-3 w-7 h-7 flex items-center justify-center rounded-full bg-white/80 hover:bg-white text-slate-400 hover:text-slate-600 border border-slate-200 transition-all shadow-sm"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>

        <div class="flex items-center gap-2">
            <div class="w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center shrink-0">
                <svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="11" fill="currentColor"/>
                    <path d="M8 12c0 2 1 3.5 3 3.5s3-1.5 3-3.5-1-3.5-3-3.5-3 1.5-3 3.5z" fill="white"/>
                </svg>
            </div>
            <div>
                <h3 class="text-sm font-black text-slate-800 uppercase tracking-wider">Mercado Pago</h3>
                <p class="text-[10px] font-medium text-slate-500">Mostrale este QR al cliente para que pague</p>
            </div>
        </div>

        <div v-if="error" class="bg-amber-50 border border-amber-200 rounded-xl p-3 text-center">
            <p class="text-xs font-bold text-amber-700">{{ error }}</p>
        </div>

        <div v-else class="flex flex-col items-center gap-1">
            <div class="bg-white rounded-2xl p-3 shadow-inner border border-sky-100">
                <img
                    v-if="qrDataUrl"
                    :src="qrDataUrl"
                    alt="QR Mercado Pago"
                    class="w-48 h-48"
                />
                <div v-else class="w-48 h-48 flex items-center justify-center">
                    <svg class="animate-spin h-8 w-8 text-sky-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                </div>
            </div>
            <p class="text-[9px] font-mono text-slate-400">Escaneá con la app de Mercado Pago</p>
        </div>

        <div v-if="displayData?.alias" class="space-y-1">
            <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Alias</label>
            <div class="flex items-center gap-2">
                <div class="flex-1 bg-white border border-slate-200 rounded-xl px-3 py-2 text-sm font-mono font-bold text-slate-700 truncate">
                    {{ displayData.alias }}
                </div>
                <button
                    @click="copiar(displayData.alias, 'alias')"
                    class="shrink-0 px-3 py-2 rounded-xl font-bold text-[10px] uppercase tracking-wider transition-all"
                    :class="copiado === 'alias'
                        ? 'bg-emerald-500 text-white shadow-md'
                        : 'bg-white border border-slate-200 text-slate-600 hover:border-sky-300 hover:text-sky-600 hover:shadow-sm'"
                >
                    {{ copiado === 'alias' ? 'Copiado' : 'Copiar' }}
                </button>
            </div>
        </div>

        <div v-if="displayData?.cvu" class="space-y-1">
            <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest">CVU</label>
            <div class="flex items-center gap-2">
                <div class="flex-1 bg-white border border-slate-200 rounded-xl px-3 py-2 text-sm font-mono font-bold text-slate-700 truncate">
                    {{ displayData.cvu }}
                </div>
                <button
                    @click="copiar(displayData.cvu, 'cvu')"
                    class="shrink-0 px-3 py-2 rounded-xl font-bold text-[10px] uppercase tracking-wider transition-all"
                    :class="copiado === 'cvu'
                        ? 'bg-emerald-500 text-white shadow-md'
                        : 'bg-white border border-slate-200 text-slate-600 hover:border-sky-300 hover:text-sky-600 hover:shadow-sm'"
                >
                    {{ copiado === 'cvu' ? 'Copiado' : 'Copiar' }}
                </button>
            </div>
        </div>
    </div>
</template>
