<script setup>
import { useForm } from '@inertiajs/vue3';
import { watch, nextTick, ref } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({ mostrar: Boolean, sucursal: Object });
const emit = defineEmits(['cerrar']);

const formulario = useForm({
    id: null,
    nombre: '',
    direccion: '',
    telefono: '',
    tipo: 'punto_de_venta',
    latitud: -27.367,
    longitud: -55.896,
    costo_delivery: 0,
});

let modalMap = null;
let pinMarker = null;
const buscandoDireccion = ref(false);

// 🔥 FUNCIÓN DE GEOCODING MEJORADA
const actualizarDireccionDesdeCoordenadas = async (lat, lng) => {
    buscandoDireccion.value = true;
    try {
        // Agregamos 'addressdetails=1' para forzar el desglose de la dirección
        const response = await axios.get(`https://nominatim.openstreetmap.org/reverse`, {
            params: {
                format: 'jsonv2',
                lat: lat,
                lon: lng,
                addressdetails: 1, 
                zoom: 18 // Zoom alto para intentar captar el número de puerta
            }
        });

        if (response.data && response.data.address) {
            const addr = response.data.address;
            
            // 1. Buscamos la calle (road es lo principal)
            const calle = addr.road || addr.pedestrian || addr.path || addr.cycleway || addr.suburb || 'Calle s/n';
            
            // 2. Buscamos la "altura" (house_number)
            const altura = addr.house_number || '';
            
            // 3. Buscamos la ciudad/localidad de forma limpia
            const ciudad = addr.city || addr.town || addr.village || '';

            // Armamos el string final
            let direccionFinal = calle;
            if (altura) direccionFinal += ` ${altura}`; // Si hay altura, se la pegamos
            if (ciudad) direccionFinal += `, ${ciudad}`;

            formulario.direccion = direccionFinal;
        }
    } catch (error) {
        console.error("Error obteniendo dirección:", error);
    } finally {
        buscandoDireccion.value = false;
    }
};

const destruirMapa = () => {
    if (modalMap) {
        modalMap.remove();
        modalMap = null;
        pinMarker = null;
    }
};

const abrirMapaPin = () => {
    nextTick(() => {
        const contenedor = document.getElementById('modal-mapa-pin');
        if (!contenedor) return;
        destruirMapa();

        modalMap = L.map('modal-mapa-pin').setView([formulario.latitud, formulario.longitud], 15);
        L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', { attribution: '&copy; OSM' }).addTo(modalMap);

        const iconoPin = L.divIcon({
            className: 'custom-pin',
            html: '<div class="w-7 h-7 bg-rose-600 border-2 border-white rounded-full shadow-lg flex items-center justify-center cursor-grab active:cursor-grabbing text-xs">📍</div>',
            iconSize: [28, 28], iconAnchor: [14, 14]
        });

        pinMarker = L.marker([formulario.latitud, formulario.longitud], { icon: iconoPin, draggable: true }).addTo(modalMap);

        pinMarker.on('dragend', () => {
            const posicion = pinMarker.getLatLng();
            formulario.latitud = posicion.lat;
            formulario.longitud = posicion.lng;
            actualizarDireccionDesdeCoordenadas(posicion.lat, posicion.lng);
        });

        if (formulario.id) {
            modalMap.setView([formulario.latitud, formulario.longitud], 16);
            pinMarker.setLatLng([formulario.latitud, formulario.longitud]);
        } else {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition((pos) => {
                    const { latitude, longitude } = pos.coords;
                    formulario.latitud = latitude;
                    formulario.longitud = longitude;
                    modalMap.setView([latitude, longitude], 16);
                    pinMarker.setLatLng([latitude, longitude]);
                    actualizarDireccionDesdeCoordenadas(latitude, longitude);
                });
            }
        }
    });
};

watch(() => props.sucursal, (nuevo) => {
    formulario.clearErrors();
    if (nuevo) {
        formulario.id = nuevo.id;
        formulario.nombre = nuevo.nombre;
        formulario.direccion = nuevo.direccion;
        formulario.telefono = nuevo.telefono || '';
        formulario.tipo = nuevo.tipo;
        formulario.latitud = Number(nuevo.latitud);
        formulario.longitud = Number(nuevo.longitud);
        formulario.costo_delivery = nuevo.costo_delivery || 0;
    } else {
        formulario.reset();
    }
}, { immediate: true });

watch(() => props.mostrar, (v) => { if (v) abrirMapaPin(); else destruirMapa(); });

const guardar = () => {
    const esEdicion = !!formulario.id;
    const ruta = esEdicion ? route('sucursales.update', formulario.id) : route('sucursales.store');
    formulario[esEdicion ? 'put' : 'post'](ruta, {
        onSuccess: () => {
            Swal.fire({
                title: '¡Éxito!',
                text: `Sucursal ${esEdicion ? 'actualizada' : 'registrada'} correctamente`,
                icon: 'success',
                confirmButtonColor: '#0284c7'
            });
            emit('cerrar');
        }
    });
};
</script>

<template>
    <div v-if="mostrar" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4 text-slate-800">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col max-h-[90vh]">
            <div class="bg-sky-600 p-4 text-white font-bold text-center uppercase tracking-widest text-sm flex-shrink-0">
                {{ formulario.id ? 'Editar Sucursal' : 'Nueva Sucursal' }}
            </div>
            
            <form @submit.prevent="guardar" class="p-6 grid grid-cols-2 gap-4 overflow-y-auto flex-grow">
                <div class="col-span-2">
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest">Nombre de Sucursal</label>
                    <input v-model="formulario.nombre" type="text" class="w-full mt-1 rounded border-gray-300 shadow-sm uppercase font-bold focus:ring-sky-500" required>
                </div>
                
                <div class="col-span-2">
                    <div class="flex justify-between items-center">
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest">Dirección Física</label>
                        <span v-if="buscandoDireccion" class="text-[10px] text-sky-600 font-bold animate-pulse">🛰️ BUSCANDO ALTURA...</span>
                    </div>
                    <input 
                        v-model="formulario.direccion" 
                        type="text" 
                        class="w-full mt-1 rounded border-gray-300 shadow-sm font-medium transition-colors focus:ring-sky-500" 
                        :class="{'bg-sky-50 border-sky-300': buscandoDireccion}"
                        placeholder="Ej: Calle Rivadavia 123"
                        required
                    >
                </div>
                
                <div class="col-span-1">
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest">Teléfono</label>
                    <input v-model="formulario.telefono" type="text" class="w-full mt-1 rounded border-gray-300 shadow-sm focus:ring-sky-500">
                </div>
                
                <div class="col-span-1">
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest">Tipo</label>
                    <select v-model="formulario.tipo" class="w-full mt-1 rounded border-gray-300 shadow-sm focus:ring-sky-500">
                        <option value="punto_de_venta">Punto de Venta</option>
                        <option value="deposito">Depósito / Almacén</option>
                    </select>
                </div>

                <div class="col-span-2 p-3 bg-orange-50 border border-orange-100 rounded-xl">
                    <label class="block text-[10px] font-black text-orange-700 uppercase tracking-widest">Costo de Envío ($)</label>
                    <div class="relative mt-1">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-orange-400 font-bold">$</span>
                        <input v-model="formulario.costo_delivery" type="number" step="0.01" class="w-full pl-7 rounded-lg border-orange-200 font-bold focus:ring-orange-500">
                    </div>
                </div>

                <div class="col-span-2 mt-1">
                    <p class="text-[11px] text-orange-600 font-bold mb-2">📌 Arrastrá el pin: el sistema intentará escribir la altura.</p>
                    <div id="modal-mapa-pin" class="w-full h-72 rounded-lg border border-slate-300 shadow-inner overflow-hidden"></div>
                    <div class="flex justify-between mt-1 px-1 text-[9px] text-slate-400 font-mono">
                        <span>LAT: {{ formulario.latitud.toFixed(5) }}</span>
                        <span>LNG: {{ formulario.longitud.toFixed(5) }}</span>
                    </div>
                </div>

                <div class="col-span-2 flex justify-end gap-3 border-t pt-4 mt-2">
                    <button type="button" @click="$emit('cerrar')" class="text-gray-400 font-bold uppercase text-xs hover:text-slate-600">Cancelar</button>
                    <button type="submit" class="bg-sky-600 text-white px-8 py-2.5 rounded-lg font-bold hover:bg-sky-700 shadow-md active:scale-95 transition-all" :disabled="formulario.processing">Confirmar</button>
                </div>
            </form>
        </div>
    </div>
</template>