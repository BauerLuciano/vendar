<script setup>
import { ref, watch, nextTick } from 'vue';
import axios from 'axios';

const props = defineProps({
    show: Boolean,
    sucursalesBackend: Array,
    sucursalElegida: [String, Number],
    geoapifyKey: String,
    tipoEntrega: { type: String, default: 'local' },
    coordenadasGps: Object,
});

const emit = defineEmits([
    'close',
    'update:distancia',
    'update:coordenadas',
    'update:direccion',
    'sucursal-seleccionada',
]);

const mapContainer = ref(null);
let map = null;
let markersLayer = null;
let pinEntrega = null;

const crearIconoSucursal = () => L.divIcon({
    className: 'marcador-sucursal',
    html: `<div style="width:36px;height:36px;background:#00adef;border:3px solid #fff;border-radius:8px;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,.5);cursor:pointer;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="9" width="18" height="12" rx="1"/><path d="M1 9h22l-3-4H4L1 9z"/><rect x="9" y="14" width="6" height="7" rx=".5"/></svg>
    </div>`,
    iconSize: [36, 36],
    iconAnchor: [18, 18],
    popupAnchor: [0, -18],
});

const crearPinEntrega = () => L.divIcon({
    className: 'pin-entrega',
    html: `<div style="position:relative;width:24px;height:36px;">
        <svg width="24" height="36" viewBox="0 0 24 36"><path d="M12 1C5.9 1 1 5.9 1 12c0 7.5 11 23 11 23s11-15.5 11-23C23 5.9 18.1 1 12 1z" fill="#f7941e" stroke="#fff" stroke-width="2"/><circle cx="12" cy="11" r="4" fill="#fff"/></svg>
    </div>`,
    iconSize: [24, 36],
    iconAnchor: [12, 36],
    popupAnchor: [0, -36],
});

const calcularDistanciaFisica = (lat1, lon1, lat2, lon2) => {
    const R = 6371, dLat = (lat2 - lat1) * Math.PI / 180, dLon = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dLat / 2) ** 2 + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLon / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
};

const actualizarDistancia = (latCliente, lngCliente) => {
    if (!props.sucursalesBackend?.length) return;
    if (props.sucursalElegida) {
        const suc = props.sucursalesBackend.find(s => s.id == props.sucursalElegida);
        if (suc && Number(suc.latitud) && Number(suc.longitud)) {
            emit('update:distancia', calcularDistanciaFisica(latCliente, lngCliente, Number(suc.latitud), Number(suc.longitud)));
            return;
        }
    }
    let masCercana = null, distanciaMinima = Infinity;
    props.sucursalesBackend.forEach(suc => {
        const sLat = Number(suc.latitud), sLng = Number(suc.longitud);
        if (sLat !== 0 && sLng !== 0) {
            const d = calcularDistanciaFisica(latCliente, lngCliente, sLat, sLng);
            if (d < distanciaMinima) { distanciaMinima = d; masCercana = suc; }
        }
    });
    if (masCercana) {
        emit('sucursal-seleccionada', masCercana.id);
        emit('update:distancia', distanciaMinima);
    }
};

const dibujarPines = () => {
    if (!markersLayer || !props.sucursalesBackend) return;
    markersLayer.clearLayers();
    props.sucursalesBackend.forEach(suc => {
        const lat = Number(suc.latitud);
        const lng = Number(suc.longitud);
        if (lat !== 0 && lng !== 0) {
            L.marker([lat, lng], { icon: crearIconoSucursal() })
                .addTo(markersLayer)
                .bindPopup(`<div style="padding:4px"><b>${suc.nombre}</b><br><small>${suc.direccion || ''}</small></div>`)
                .on('click', () => {
                    emit('sucursal-seleccionada', suc.id);
                    if (map) map.setView([lat, lng], 15);
                });
        }
    });
};

const initMap = () => {
    const contenedor = mapContainer.value;
    if (!contenedor || map) return;
    map = L.map(contenedor, { zoomControl: false }).setView([-27.367, -55.896], 13);
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', { attribution: '&copy; OSM' }).addTo(map);
    markersLayer = L.layerGroup().addTo(map);
    dibujarPines();
};

const setViewMap = (lat, lng, zoom = 14) => {
    if (map) map.setView([lat, lng], zoom);
};

const agregarPinEntrega = (lat, lng) => {
    if (!map) return;
    if (pinEntrega) map.removeLayer(pinEntrega);
    pinEntrega = L.marker([lat, lng], { icon: crearPinEntrega(), draggable: true }).addTo(map);
    pinEntrega.on('dragend', async function () {
        const pos = this.getLatLng();
        emit('update:coordenadas', { lat: pos.lat, lng: pos.lng });
        actualizarDistancia(pos.lat, pos.lng);
        await reverseGeocode(pos.lat, pos.lng);
    });
};

const reverseGeocode = async (lat, lng) => {
    if (!props.geoapifyKey) return;
    try {
        const url = `https://api.geoapify.com/v1/geocode/reverse?lat=${lat}&lon=${lng}&apiKey=${props.geoapifyKey}`;
        const resp = await axios.get(url, { headers: { 'X-Requested-With': null } });
        if (resp.data?.features?.[0]) {
            emit('update:direccion', resp.data.features[0].properties.formatted);
        }
    } catch (e) {
        console.error('Error en reverse geocode:', e);
    }
};

const cleanupMap = () => {
    if (pinEntrega) { map?.removeLayer(pinEntrega); pinEntrega = null; }
    if (markersLayer) { map?.removeLayer(markersLayer); markersLayer = null; }
    if (map) { map.remove(); map = null; }
};

watch(() => props.sucursalesBackend, () => dibujarPines(), { deep: true });

watch(() => props.show, async (newVal) => {
    if (newVal) {
        await nextTick();
        setTimeout(() => {
            initMap();
            if (props.coordenadasGps) {
                const { lat, lng } = props.coordenadasGps;
                setViewMap(lat, lng, 14);
                agregarPinEntrega(lat, lng);
                actualizarDistancia(lat, lng);
                reverseGeocode(lat, lng);
            }
        }, 150);
    } else {
        cleanupMap();
    }
});
</script>

<template>
    <Transition name="backdrop">
        <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4" :style="{ backgroundColor: 'var(--overlay)', backdropFilter: 'blur(2px)' }" @click.self="emit('close')">
            <Transition name="modal" appear>
                <div class="border rounded-3xl w-full max-w-2xl max-h-[90vh] flex flex-col shadow-2xl transition-colors duration-300" :style="{ backgroundColor: 'var(--bg-elevated)', borderColor: 'var(--border-color)' }">
                    <div class="flex items-center justify-between px-6 py-4 border-b shrink-0" :style="{ borderColor: 'var(--border-subtle)' }">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-[#00adef]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                            <h2 class="text-sm font-black tracking-widest uppercase transition-colors" :style="{ color: 'var(--text-primary)' }">Elegí tu sucursal</h2>
                        </div>
                        <button @click="emit('close')" class="w-8 h-8 flex items-center justify-center rounded-xl text-lg transition-all" :style="{ backgroundColor: 'var(--bg-input)', color: 'var(--text-muted)' }">&times;</button>
                    </div>

                    <div ref="mapContainer" class="flex-1 min-h-[360px]" :style="{ backgroundColor: 'var(--bg-input)' }"></div>

                    <div class="flex items-center justify-between px-6 py-4 border-t shrink-0" :style="{ borderColor: 'var(--border-subtle)' }">
                        <p class="text-[11px] font-bold transition-colors" :style="{ color: 'var(--text-muted)' }">
                            <span v-if="props.sucursalElegida">📍 Sucursal seleccionada</span>
                            <span v-else>Hacé clic en una sucursal o usá el GPS</span>
                        </p>
                        <button
                            @click="emit('close')"
                            class="bg-[#00adef] hover:bg-[#00adef]/80 text-white font-black text-[10px] uppercase tracking-widest px-6 py-2.5 rounded-xl shadow-lg shadow-[#00adef]/20 transition-all active:scale-95"
                        >Listo</button>
                    </div>
                </div>
            </Transition>
        </div>
    </Transition>
</template>

<style scoped>
.backdrop-enter-active, .backdrop-leave-active { transition: opacity 0.2s ease; }
.backdrop-enter-from, .backdrop-leave-to { opacity: 0; }

.modal-enter-active { transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); }
.modal-leave-active { transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1); }
.modal-enter-from { opacity: 0; transform: scale(0.95) translateY(10px); }
.modal-leave-to { opacity: 0; transform: scale(0.95) translateY(10px); }
</style>
