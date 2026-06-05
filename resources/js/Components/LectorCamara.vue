<script setup>
import { Html5Qrcode, Html5QrcodeSupportedFormats } from "html5-qrcode";
import { ref, onMounted, onBeforeUnmount } from 'vue';

const emit = defineEmits(['escaneado', 'cerrar']);

const html5QrCode = ref(null);
const camaras = ref([]);
const camaraSeleccionada = ref('');
const escaneando = ref(false);
const errorCamara = ref('');

let audioContext = null;
const getAudioContext = () => {
    if (!audioContext) {
        audioContext = new (window.AudioContext || window.webkitAudioContext)();
    }
    return audioContext;
};

onMounted(async () => {
    try {
        const devices = await Html5Qrcode.getCameras();
        
        if (devices && devices.length) {
            camaras.value = devices;
            const camaraTrasera = devices.find(d => d.label.toLowerCase().includes('back') || d.label.toLowerCase().includes('trasera'));
            camaraSeleccionada.value = camaraTrasera ? camaraTrasera.id : devices[0].id;
            
            iniciarEscaneo();
        } else {
            errorCamara.value = "No se detectaron cámaras en tu dispositivo.";
        }
    } catch (err) {
        console.error("Error al obtener cámaras: ", err);
        errorCamara.value = "No se pudo acceder a la cámara. Verificá los permisos del navegador.";
    }
});

const iniciarEscaneo = async () => {
    if (!camaraSeleccionada.value) return;
    
    errorCamara.value = '';
    escaneando.value = true;
    
    if (html5QrCode.value) {
        try {
            await html5QrCode.value.stop();
            html5QrCode.value.clear();
        } catch (e) { /* ignore */ }
    }
    
    html5QrCode.value = new Html5Qrcode("lector-codigo");
    
    html5QrCode.value.start(
        camaraSeleccionada.value,
        {
            fps: 15,
            qrbox: { width: 300, height: 120 },
            formatsToSupport: [ 
                Html5QrcodeSupportedFormats.EAN_8,
                Html5QrcodeSupportedFormats.EAN_13,
                Html5QrcodeSupportedFormats.UPC_A,
                Html5QrcodeSupportedFormats.CODE_128
            ]
        },
        (textoDecodificado) => {
            hacerSonidoBeep();
            detenerEscaneo();
            emit('escaneado', textoDecodificado);
        },
        (mensajeError) => {
            // Silencio
        }
    ).catch((err) => {
        errorCamara.value = "Error al iniciar la cámara: permisos denegados o cámara ocupada.";
        escaneando.value = false;
    });
};

const detenerEscaneo = async () => {
    if (html5QrCode.value && escaneando.value) {
        try {
            await html5QrCode.value.stop();
            html5QrCode.value.clear();
            escaneando.value = false;
        } catch (err) {
            console.error("Error al detener", err);
        }
    }
};

const hacerSonidoBeep = () => {
    const context = getAudioContext();
    const osc = context.createOscillator();
    const gain = context.createGain();
    osc.connect(gain);
    gain.connect(context.destination);
    osc.type = 'sine';
    osc.frequency.value = 800;
    gain.gain.setValueAtTime(0, context.currentTime);
    gain.gain.linearRampToValueAtTime(1, context.currentTime + 0.05);
    gain.gain.linearRampToValueAtTime(0, context.currentTime + 0.15);
    osc.start();
    osc.stop(context.currentTime + 0.2);
};

const cerrarModal = () => {
    detenerEscaneo();
    emit('cerrar');
};

onBeforeUnmount(() => {
    detenerEscaneo();
});
</script>

<template>
    <div class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/80 backdrop-blur-sm p-4 animate-in fade-in duration-200">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden flex flex-col border border-slate-200 animate-in zoom-in-95 duration-200">
            
            <div class="bg-slate-900 p-4 flex justify-between items-center text-white">
                <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    <h3 class="font-black tracking-widest uppercase text-sm">Escáner Inteligente</h3>
                </div>
                <button @click="cerrarModal" class="text-slate-400 hover:text-white transition-colors bg-slate-800 hover:bg-rose-500 rounded-full w-8 h-8 flex items-center justify-center">
                    ✕
                </button>
            </div>

            <div class="p-6 bg-slate-50 flex flex-col items-center">
                
                <div v-if="errorCamara" class="bg-rose-100 text-rose-700 p-4 rounded-xl text-sm font-bold text-center w-full mb-4 border border-rose-200">
                    {{ errorCamara }}
                </div>

                <div id="lector-codigo" class="w-full max-w-[300px] bg-slate-200 rounded-2xl overflow-hidden border-4 border-slate-900 shadow-inner min-h-[200px] relative flex justify-center items-center">
                    <span class="text-slate-400 font-bold absolute -z-10">Iniciando cámara...</span>
                </div>

                <div class="mt-6 w-full space-y-2">
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Cambiar Cámara (Opcional)</label>
                    <select v-model="camaraSeleccionada" @change="iniciarEscaneo" class="w-full text-sm font-bold text-slate-700 bg-white border-slate-200 rounded-xl focus:ring-sky-500 text-center">
                        <option v-for="cam in camaras" :key="cam.id" :value="cam.id">
                            {{ cam.label || 'Cámara detectada' }}
                        </option>
                    </select>
                </div>

            </div>
            
            <div class="p-4 bg-slate-900 text-center">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest animate-pulse">Apunta el código en el recuadro ↑</p>
            </div>
        </div>
    </div>
</template>

<style>
#lector-codigo img, #lector-codigo span, #lector-codigo a { display: none !important; }
</style>