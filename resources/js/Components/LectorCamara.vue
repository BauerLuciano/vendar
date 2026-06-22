<script setup>
import { ref, onMounted, onBeforeUnmount, nextTick } from 'vue';
import * as ZXing from 'html5-qrcode/third_party/zxing-js.umd.js';

const emit = defineEmits(['escaneado', 'cerrar']);

const errorCamara = ref('');
const escaneando = ref(false);
const iniciando = ref(true);
const modoDeteccion = ref('');
const camaras = ref([]);
const camaraSeleccionada = ref('');
const videoEl = ref(null);
const debugCanvasEl = ref(null); // <-- EL NUEVO MONITOR DE DEBUG

const codigoManual = ref('');
const inputManualRef = ref(null);

let stream = null;
let detectado = false;
let scanInterval = null;
let audioCtx = null;
let zxingReader = null;

const getAudioContext = () => {
    if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    return audioCtx;
};

const hacerSonidoBeep = () => {
    try {
        const ctx = getAudioContext();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.type = 'square';
        osc.frequency.value = 800;
        gain.gain.setValueAtTime(0, ctx.currentTime);
        gain.gain.linearRampToValueAtTime(1, ctx.currentTime + 0.05);
        gain.gain.linearRampToValueAtTime(0, ctx.currentTime + 0.15);
        osc.start();
        osc.stop(ctx.currentTime + 0.2);
    } catch (e) { /* mudo */ }
};

function detenerCamara() {
    if (scanInterval) {
        clearInterval(scanInterval);
        scanInterval = null;
    }
    if (stream) {
        stream.getTracks().forEach(t => t.stop());
        stream = null;
    }
    if (videoEl.value) {
        videoEl.value.srcObject = null;
    }
    escaneando.value = false;
}

function onDetected(codigo) {
    if (detectado) return;
    detectado = true;
    console.log("¡CÓDIGO DETECTADO EN EL LECTOR!", codigo);
    detenerCamara();
    hacerSonidoBeep();
    emit('escaneado', normalizarCodigo(codigo));
}

function procesarCodigoManual() {
    if (codigoManual.value.trim() !== '') {
        onDetected(codigoManual.value.trim());
    }
}

function validarEAN13(code) {
    if (!/^\d{13}$/.test(code)) return false;
    let sum = 0;
    for (let i = 0; i < 12; i++) sum += parseInt(code[i]) * (i % 2 === 0 ? 1 : 3);
    return (10 - (sum % 10)) % 10 === parseInt(code[12]);
}

function normalizarCodigo(code) {
    if (code.length === 13) {
        const rev = code.split('').reverse().join('');
        if (!validarEAN13(code) && validarEAN13(rev)) return rev;
    }
    return code;
}

// Lector Nativo (Se usa cuando estás en localhost)
function escanearConBarcodeDetector() {
    const detector = new BarcodeDetector();
    
    scanInterval = setInterval(async () => {
        if (!videoEl.value || detectado || !videoEl.value.readyState) return;
        try {
            const codes = await detector.detect(videoEl.value);
            if (codes.length > 0 && codes[0].rawValue) {
                onDetected(codes[0].rawValue);
            }
        } catch (e) {}
    }, 150);
}

// Lector ZXing (Se usa en red local IP)
function escanearConZXingManual() {
    if (!ZXing || typeof ZXing.MultiFormatReader !== 'function') {
        errorCamara.value = "Error crítico: La librería ZXing no se cargó.";
        return;
    }

    if (!zxingReader) {
        zxingReader = new ZXing.MultiFormatReader(); // Sin restricciones, lee todo
    }

    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d', { willReadFrequently: true });

    scanInterval = setInterval(() => {
        if (!videoEl.value || detectado || videoEl.value.readyState !== 4) return;
        
        const MAX_WIDTH = 640; 
        let width = videoEl.value.videoWidth;
        let height = videoEl.value.videoHeight;
        
        if (width === 0 || height === 0) return;

        if (width > MAX_WIDTH) {
            height = Math.floor(height * (MAX_WIDTH / width));
            width = MAX_WIDTH;
        }

        canvas.width = width;
        canvas.height = height;
        
        // 1. Dibujamos el video en nuestro canvas oculto
        ctx.drawImage(videoEl.value, 0, 0, width, height);

        // 2. Mostramos en el monitor de Debug para que vos lo veas
        if (debugCanvasEl.value) {
            debugCanvasEl.value.width = width;
            debugCanvasEl.value.height = height;
            debugCanvasEl.value.getContext('2d').drawImage(canvas, 0, 0);
        }

        try {
            // EL FIX ESTÁ ACÁ: Usamos el helper nativo para HTMLCanvasElement
            // Esto procesa los píxeles correctamente sin deformar el RGBA
            const luminanceSource = new ZXing.HTMLCanvasElementLuminanceSource(canvas);
            const binarizer = new ZXing.HybridBinarizer(luminanceSource);
            const bitmap = new ZXing.BinaryBitmap(binarizer);
            
            const result = zxingReader.decode(bitmap);
            
            if (result && result.text) {
                onDetected(result.text);
            }
        } catch (err) {
            // Silencio de radio, no pasa nada si no encontró código en este frame
        }
    }, 250); // Lo dejamos en 250ms (4 FPS) para que tu procesador no llore
}

async function arrancarCamara(deviceId) {
    detenerCamara();
    iniciando.value = true;
    errorCamara.value = '';
    detectado = false;
    modoDeteccion.value = '';

    if (!deviceId) {
        errorCamara.value = 'No hay cámaras disponibles.';
        iniciando.value = false;
        return;
    }

    escaneando.value = true;

    try {
        stream = await navigator.mediaDevices.getUserMedia({
            video: {
                deviceId: { exact: deviceId },
                width: { ideal: 1280 },
                height: { ideal: 720 },
                advanced: [{ focusMode: "continuous" }] 
            },
            audio: false,
        });

        if (videoEl.value) {
            videoEl.value.srcObject = stream;
            await videoEl.value.play();
        }

        iniciando.value = false;

        // Comprobación de API nativa (Localhost / HTTPS)
        if ('BarcodeDetector' in window) {
            modoDeteccion.value = 'Nativo (Rápido)';
            escanearConBarcodeDetector();
        } else {
            modoDeteccion.value = 'ZXing (LAN)';
            escanearConZXingManual();
        }
    } catch (e) {
        errorCamara.value = 'Error al iniciar cámara: ' + e.message;
        iniciando.value = false;
        escaneando.value = false;
    }
}

async function cambiarCamara() {
    await arrancarCamara(camaraSeleccionada.value);
    inputManualRef.value?.focus();
}

async function listarCamaras() {
    try {
        const devices = await navigator.mediaDevices.enumerateDevices();
        const cams = devices.filter(d => d.kind === 'videoinput');
        if (cams.length === 0) {
            errorCamara.value = 'No se encontraron cámaras.';
            iniciando.value = false;
            return;
        }
        camaras.value = cams;
        camaraSeleccionada.value = cams[0].deviceId;
    } catch (e) {
        errorCamara.value = 'Error al acceder a los dispositivos.';
        iniciando.value = false;
    }
}

onMounted(async () => {
    nextTick(() => { inputManualRef.value?.focus(); });

    if (!navigator.mediaDevices?.getUserMedia) {
        errorCamara.value = 'Navegador sin soporte de cámara (requiere HTTPS o Localhost).';
        iniciando.value = false;
        return;
    }
    
    await listarCamaras();
    if (camaraSeleccionada.value) {
        await arrancarCamara(camaraSeleccionada.value);
    }
});

const cerrarModal = () => { detenerCamara(); emit('cerrar'); };
onBeforeUnmount(() => { detenerCamara(); });
</script>

<template>
    <div class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/80 backdrop-blur-sm p-4" @click.self="cerrarModal">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden flex flex-col border border-slate-200 relative">

            <div class="bg-slate-900 p-4 flex justify-between items-center text-white">
                <div class="flex items-center gap-2">
                    <svg class="h-5 w-5 text-emerald-400" fill="currentColor" viewBox="0 0 24 24"><rect x="2" y="4" width="2" height="16" rx="0.5"/><rect x="5" y="5" width="1" height="14" rx="0.3"/><rect x="7" y="3" width="3" height="18" rx="0.5"/><rect x="11" y="6" width="1" height="12" rx="0.3"/><rect x="13" y="4" width="2" height="16" rx="0.5"/><rect x="16" y="7" width="1" height="10" rx="0.3"/><rect x="18" y="3" width="3" height="18" rx="0.5"/><rect x="22" y="5" width="1" height="14" rx="0.3"/></svg>
                    <h3 class="font-black tracking-widest uppercase text-sm">Escanear Código</h3>
                </div>
                <div class="flex items-center gap-2">
                    <span v-if="modoDeteccion" class="text-[10px] font-bold text-slate-900 bg-emerald-400 px-2 py-1 rounded-full uppercase tracking-wider">{{ modoDeteccion }}</span>
                    <button @click="cerrarModal" class="text-slate-400 hover:text-white transition-colors bg-slate-800 hover:bg-rose-500 rounded-full w-8 h-8 flex items-center justify-center">✕</button>
                </div>
            </div>

            <div class="p-6 bg-slate-900 flex flex-col items-center relative">
                
                <canvas 
                    ref="debugCanvasEl" 
                    class="absolute bottom-[40%] right-6 w-20 h-auto border-2 border-rose-500 rounded bg-black z-[60] shadow-lg opacity-80" 
                    title="Visión interna del algoritmo"
                    v-show="modoDeteccion === 'ZXing (LAN)'"
                ></canvas>

                <div v-if="errorCamara" class="bg-rose-500/10 text-rose-400 p-3 rounded-xl text-xs font-bold text-center w-full mb-4 border border-rose-500/20">
                    {{ errorCamara }}
                </div>

                <div id="barcode-scanner" class="w-full max-w-sm aspect-video bg-black rounded-2xl overflow-hidden relative flex items-center justify-center border border-slate-800 shadow-inner">
                    <video ref="videoEl" autoplay playsinline muted class="absolute inset-0 w-full h-full object-cover"></video>

                    <div v-if="escaneando" class="absolute inset-0 pointer-events-none">
                        <div class="absolute inset-0 bg-black/50"></div>
                        <div class="absolute inset-y-[12%] inset-x-[8%] bg-transparent">
                            <div class="absolute inset-0 shadow-[0_0_0_9999px_rgba(0,0,0,0.5)]"></div>
                        </div>
                    </div>

                    <div v-if="escaneando" class="absolute inset-x-[10%] pointer-events-none scan-line">
                        <div class="w-full h-[2px] bg-gradient-to-r from-transparent via-emerald-400 to-transparent shadow-[0_0_8px_theme(colors.emerald.400)]"></div>
                    </div>

                    <div v-if="iniciando" class="absolute inset-0 flex items-center justify-center bg-slate-900/80 z-10">
                        <div class="text-center">
                            <svg class="animate-spin h-8 w-8 text-emerald-400 mx-auto mb-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <p class="text-xs font-bold text-emerald-400 animate-pulse">Iniciando cámara...</p>
                        </div>
                    </div>
                </div>

                <div v-if="camaras.length > 1" class="mt-4 w-full space-y-1.5">
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest text-center mb-1">Cámara Seleccionada</label>
                    <select v-model="camaraSeleccionada" @change="cambiarCamara" class="w-full text-xs font-bold text-slate-300 bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 focus:ring-2 focus:ring-emerald-500 text-center cursor-pointer">
                        <option v-for="cam in camaras" :key="cam.deviceId" :value="cam.deviceId">{{ cam.label || 'Cámara ' + (camaras.indexOf(cam) + 1) }}</option>
                    </select>
                </div>

                <div class="mt-6 w-full pt-4 border-t border-slate-800">
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest text-center mb-2">Pistola Láser / Teclado</label>
                    <div class="flex gap-2">
                        <input 
                            ref="inputManualRef"
                            type="text" 
                            v-model="codigoManual"
                            @keyup.enter="procesarCodigoManual"
                            placeholder="Gatillá acá o escribí..." 
                            class="w-full text-sm font-bold text-slate-200 bg-slate-800 border border-slate-700 rounded-xl px-4 py-3 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 placeholder-slate-500 text-center"
                            autocomplete="off"
                        >
                        <button 
                            @click="procesarCodigoManual"
                            class="bg-emerald-500 hover:bg-emerald-600 text-slate-900 px-4 py-2 rounded-xl font-black transition-colors flex items-center justify-center"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </button>
                    </div>
                </div>

            </div>

            <div class="p-3 bg-slate-900 border-t border-slate-800 text-center flex justify-center">
                <button @click="cerrarModal" class="text-[10px] font-black text-slate-500 hover:text-rose-400 uppercase tracking-widest transition-colors">Cancelar Operación</button>
            </div>
        </div>
    </div>
</template>

<style>
#barcode-scanner video {
    object-fit: contain !important;
    width: 100% !important;
    height: 100% !important;
}
</style>

<style scoped>
.scan-line {
    top: 12%;
    animation: scanMove 2s ease-in-out infinite;
}
@keyframes scanMove {
    0%, 100% { top: 12%; }
    50% { top: calc(88% - 2px); }
}
</style>