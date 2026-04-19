<script setup>
import { ref, onMounted, watch } from 'vue'; // Agregamos watch
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    sucursalesBackend: Array,
});

const sucursalElegida = ref('');
const productos = ref([]);
const cargando = ref(false);
const localizando = ref(false);

let map = null;
let markersLayer = null; // Capa separada para los pines

const initMap = () => {
    if (map) return;
    
    // Centrado inicial en Apóstoles
    map = L.map('map-container').setView([-27.361, -55.761], 13);
    
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    markersLayer = L.layerGroup().addTo(map);
    dibujarPines();
};

const dibujarPines = () => {
    if (!markersLayer) return;
    markersLayer.clearLayers(); 

    // DEBUG: Para ver en la consola si los datos traen lat/lng
    console.table(props.sucursalesBackend);

    props.sucursalesBackend.forEach(suc => {
        const lat = Number(suc.latitud);
        const lng = Number(suc.longitud);

        if (lat !== 0 && lng !== 0) {
            L.circleMarker([lat, lng], {
                radius: 12,
                fillColor: "#f97316",
                color: "#ffffff",
                weight: 3,
                opacity: 1,
                fillOpacity: 1
            })
            .addTo(markersLayer)
            .bindPopup(`<div style="color:black; padding:5px;"><b>${suc.nombre}</b><br>Sucursal VendAR</div>`)
            .on('click', () => {
                sucursalElegida.value = suc.id;
                cargarProductos();
            });
        }
    });
};

watch(() => props.sucursalesBackend, () => {
    dibujarPines();
}, { deep: true });

const usarGps = () => {
    if (!navigator.geolocation) return alert("Tu navegador no soporta GPS");
    
    localizando.value = true; 
    navigator.geolocation.getCurrentPosition((position) => {
        const { latitude, longitude } = position.coords;
        
        map.setView([latitude, longitude], 14);
        L.circle([latitude, longitude], { radius: 200, color: '#38bdf8', weight: 2, fillOpacity: 0.2 }).addTo(map);

        let masCercana = null;
        let distanciaMinima = Infinity;

        props.sucursalesBackend.forEach(suc => {
            const sLat = Number(suc.latitud);
            const sLng = Number(suc.longitud);
            
            if (sLat !== 0 && sLng !== 0) {
                const d = calcularDistanciaFisica(latitude, longitude, sLat, sLng);
                if (d < distanciaMinima) {
                    distanciaMinima = d;
                    masCercana = suc;
                }
            }
        });

        if (masCercana) {
            sucursalElegida.value = masCercana.id;
            cargarProductos();
        } else {
            alert("Atención: Tus sucursales en la base de datos no tienen coordenadas cargadas.");
        }
        localizando.value = false;
    }, (err) => { 
        console.error(err);
        localizando.value = false; 
    });
};

const calcularDistanciaFisica = (lat1, lon1, lat2, lon2) => {
    const R = 6371;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
              Math.sin(dLon/2) * Math.sin(dLon/2);
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
};

const cargarProductos = async () => {
    if (!sucursalElegida.value) return;
    cargando.value = true;
    try {
        const response = await axios.get(`/api/catalogo/${sucursalElegida.value}`);
        productos.value = response.data;
    } catch (error) {
        console.error(error);
    } finally {
        cargando.value = false;
    }
};

onMounted(() => {
    setTimeout(initMap, 500);
});
</script>

<template>
    <Head title="Catálogo | VendAR" />

    <div class="min-h-screen bg-[#0b1221] font-sans text-slate-300 relative overflow-hidden flex flex-col">
        
        <div class="absolute w-[600px] h-[600px] bg-sky-500/10 rounded-full blur-[150px] -top-32 -left-32 z-0 pointer-events-none"></div>
        <div class="absolute w-[600px] h-[600px] bg-orange-500/10 rounded-full blur-[150px] top-1/2 -right-32 z-0 pointer-events-none"></div>

        <nav class="relative z-10 border-b border-slate-800/50 bg-[#0b1221]/60 backdrop-blur-xl">
            <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <img src="/img/LogoVendar-Sidebar.png" alt="VendAR Logo" class="h-8 w-auto object-contain">
                    <span class="text-xl font-bold text-white tracking-tight">Vend<span class="text-orange-500">AR</span></span>
                </div>
                
                <div v-if="canLogin" class="flex gap-4">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="route('dashboard')"
                        class="text-[13px] font-bold uppercase tracking-[0.15em] text-white bg-white/5 hover:bg-white/10 border border-slate-700/50 rounded-xl px-5 py-2 transition-all"
                    >
                        Mi Panel
                    </Link>
                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="text-[13px] font-bold uppercase tracking-[0.1em] text-sky-400 hover:text-sky-300 px-4 py-2 transition-colors"
                        >
                            Ingresar
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="text-[13px] font-bold uppercase tracking-[0.15em] text-white bg-gradient-to-r from-sky-500 to-sky-600 hover:from-sky-400 hover:to-sky-500 rounded-xl px-5 py-2 shadow-[0_0_15px_rgba(14,165,233,0.3)] transition-all"
                        >
                            Registrarse
                        </Link>
                    </template>
                </div>
            </div>
        </nav>

        <main class="flex-grow relative z-10 max-w-7xl mx-auto w-full px-6 py-12 flex flex-col items-center">
            
            <div class="text-center max-w-2xl mb-12">
                <h1 class="text-[2.5rem] font-bold text-white tracking-tight mb-4">
                    El stock de tu kiosco, <br><span class="text-transparent bg-clip-text bg-gradient-to-r from-sky-400 to-orange-400">en tiempo real.</span>
                </h1>
                
                <div class="relative group max-w-md mx-auto text-left space-y-4">
                    <div>
                        <label class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1 block">SUCURSAL</label>
                        <div class="flex gap-2">
                            <div class="relative flex-grow">
                                <select 
                                    v-model="sucursalElegida" 
                                    @change="cargarProductos"
                                    class="block w-full bg-[#1e293b] border border-slate-700/80 text-slate-200 rounded-xl px-4 py-3.5 focus:ring-sky-500 focus:border-sky-500 transition-all shadow-inner appearance-none cursor-pointer outline-none"
                                >
                                    <option value="" disabled>📍 Elegí una sucursal...</option>
                                    <option v-for="sucursal in props.sucursalesBackend" :key="sucursal.id" :value="sucursal.id">
                                        {{ sucursal.nombre }}
                                    </option>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-slate-500">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                </div>
                            </div>
                            
                            <button 
                                @click="usarGps"
                                title="Buscar sucursal más cercana"
                                class="bg-[#1e293b] border border-slate-700/80 text-sky-400 hover:text-white hover:bg-sky-500 p-3.5 rounded-xl transition-all shadow-inner active:scale-95"
                            >
                                <svg v-if="!localizando" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                <svg v-else class="animate-spin h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div id="map-container" class="w-full h-[300px] rounded-[2rem] border border-white/5 mb-12 shadow-2xl relative z-10 overflow-hidden bg-[#1e293b]"></div>

            <div class="w-full">
                <div v-if="cargando" class="flex flex-col items-center justify-center py-20">
                    <svg class="animate-spin h-10 w-10 text-sky-500 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <p class="text-slate-400 font-medium tracking-widest uppercase text-xs">Cargando catálogo...</p>
                </div>

                <div v-else-if="!sucursalElegida" class="rounded-[2rem] border border-white/5 bg-[#1e293b]/50 backdrop-blur-sm p-12 text-center">
                    <svg class="w-16 h-16 mx-auto text-slate-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.809c0-.636-.28-1.239-.762-1.636l-7.4-6.07a1.97 1.97 0 00-2.484 0l-7.4 6.07C2.64 8.57 2.36 9.172 2.36 9.81V21M6 10.5h.008v.008H6v-.008z"></path></svg>
                    <h3 class="text-lg font-bold text-slate-300 mb-1">Tu vidriera está esperando</h3>
                    <p class="text-slate-500 text-sm">Seleccioná una sucursal en el mapa o en el menú.</p>
                </div>

                <div v-else-if="productos.length === 0" class="rounded-[2rem] border border-white/5 bg-[#1e293b]/50 backdrop-blur-sm p-12 text-center">
                    <p class="text-orange-400 font-bold mb-1">¡Ups!</p>
                    <p class="text-slate-400 text-sm">No encontramos productos disponibles para esta sucursal.</p>
                </div>

                <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <div v-for="producto in productos" :key="producto.id" class="bg-[#1e293b]/80 border border-slate-700/50 rounded-2xl overflow-hidden hover:border-sky-500/50 transition-colors shadow-lg flex flex-col">
                        <div class="h-40 bg-white/5 p-4 flex items-center justify-center">
                            <img :src="producto.imagen_url || '/img/LogoVendar-Sidebar.png'" class="max-h-full object-contain opacity-80" :alt="producto.nombre">
                        </div>
                        <div class="p-5 flex flex-col flex-grow">
                            <h3 class="text-sm font-bold text-slate-200 truncate">{{ producto.nombre }}</h3>
                            <p class="mt-2 text-xl font-black text-sky-400">$ {{ producto.precio }}</p>
                            <button class="mt-4 w-full bg-slate-800 hover:bg-sky-500 text-white font-bold text-xs tracking-wider uppercase py-3 rounded-xl transition-colors border border-slate-700 hover:border-sky-400">
                                Agregar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>

<style>
.leaflet-popup-content-wrapper { background: #1e293b !important; color: #cbd5e1 !important; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px !important; }
.leaflet-popup-tip { background: #1e293b !important; }
</style>