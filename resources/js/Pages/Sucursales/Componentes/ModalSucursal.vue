<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import { watch, nextTick, ref, onBeforeUnmount } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({ mostrar: Boolean, sucursal: Object });
const emit = defineEmits(['cerrar']);
const page = usePage();

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

const sugerenciasDireccion = ref([]);
const buscandoSugerencias = ref(false);
const mostrarSugerencias = ref(false);
let timerSugerencias = null;

// Arma un string legible "calle altura, ciudad" desde el objeto address de Nominatim
const armarDireccion = (addr) => {
    const calle = addr.road || addr.pedestrian || addr.path || addr.cycleway || addr.suburb || 'Calle s/n';
    const altura = addr.house_number || '';
    const ciudad = addr.city || addr.town || addr.village || '';

    let direccionFinal = calle;
    if (altura) direccionFinal += ` ${altura}`;
    if (ciudad) direccionFinal += `, ${ciudad}`;
    return direccionFinal;
};

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
            formulario.direccion = armarDireccion(response.data.address);
        }
    } catch (error) {
        console.error("Error obteniendo dirección:", error);
    } finally {
        buscandoDireccion.value = false;
    }
};

// Autocomplete: sugiere coincidencias mientras se escribe (forward geocoding)
const buscarDireccion = (texto) => {
    clearTimeout(timerSugerencias);
    if (!texto || texto.trim().length < 3) {
        sugerenciasDireccion.value = [];
        mostrarSugerencias.value = false;
        return;
    }

    timerSugerencias = setTimeout(async () => {
        buscandoSugerencias.value = true;
        try {
            const response = await axios.get('https://nominatim.openstreetmap.org/search', {
                params: {
                    q: texto.trim(),
                    format: 'jsonv2',
                    addressdetails: 1,
                    limit: 5,
                    // Viewbox alrededor de las coordenadas actuales: prioriza resultados cercanos
                    viewbox: `${formulario.longitud - 0.2},${formulario.latitud + 0.2},${formulario.longitud + 0.2},${formulario.latitud - 0.2}`,
                    bounded: 0
                }
            });
            sugerenciasDireccion.value = response.data || [];
            mostrarSugerencias.value = true;
        } catch (error) {
            sugerenciasDireccion.value = [];
            console.error("Error buscando dirección:", error);
        } finally {
            buscandoSugerencias.value = false;
        }
    }, 500);
};

// Al elegir una sugerencia se mueve el pin para mantener coherencia texto ↔ coordenadas
const seleccionarSugerencia = (s) => {
    formulario.direccion = armarDireccion(s.address || {});
    formulario.latitud = Number(s.lat);
    formulario.longitud = Number(s.lon);
    if (pinMarker && modalMap) {
        pinMarker.setLatLng([formulario.latitud, formulario.longitud]);
        modalMap.setView([formulario.latitud, formulario.longitud], 16);
    }
    cerrarSugerencias();
};

const cerrarSugerencias = () => {
    clearTimeout(timerSugerencias);
    sugerenciasDireccion.value = [];
    mostrarSugerencias.value = false;
};

const ocultarSugerenciasConDelay = () => setTimeout(cerrarSugerencias, 150);

// Teléfono: solo números, máximo 15 dígitos (coincide con la validación del backend)
const normalizarTelefono = () => {
    formulario.telefono = (formulario.telefono || '').replace(/\D/g, '').slice(0, 15);
};

// Costo de envío: solo enteros (coincide con 'integer|min:0' del backend)
const normalizarCostoDelivery = () => {
    const val = formulario.costo_delivery;
    if (val === '' || val === null || val === undefined) return;
    const entero = Math.trunc(Number(val));
    formulario.costo_delivery = Number.isFinite(entero) && entero >= 0 ? entero : 0;
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
        formulario.costo_delivery = Math.trunc(Number(nuevo.costo_delivery) || 0);
    } else {
        formulario.reset();
    }
}, { immediate: true });

watch(() => props.mostrar, (v) => {
    if (v) {
        abrirMapaPin();
    } else {
        destruirMapa();
        cerrarSugerencias();
    }
});

onBeforeUnmount(() => {
    clearTimeout(timerSugerencias);
});

const guardar = () => {
    const esEdicion = !!formulario.id;
    const ruta = esEdicion ? route('sucursales.update', formulario.id) : route('sucursales.store');
    formulario[esEdicion ? 'put' : 'post'](ruta, {
        onSuccess: () => {
            // Si el backend bloqueó la acción (límite del plan), NO mostrar éxito falso
            const errorFlash = page.props.flash?.error;
            if (errorFlash) {
                Swal.fire({
                    title: 'No se pudo guardar',
                    text: errorFlash,
                    icon: 'error',
                    confirmButtonColor: '#e11d48'
                });
                return;
            }
            Swal.fire({
                title: '¡Éxito!',
                text: `Local ${esEdicion ? 'actualizado' : 'registrado'} correctamente`,
                icon: 'success',
                confirmButtonColor: '#0284c7'
            });
            emit('cerrar');
        },
        onError: () => {
            // Los errores inline se muestran automáticamente via formulario.errors
        }
    });
};
</script>

<template>
    <div v-if="mostrar" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4 text-slate-800">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col max-h-[90vh]">
            <div class="bg-sky-600 p-4 text-white font-bold text-center uppercase tracking-widest text-sm flex-shrink-0">
                {{ formulario.id ? 'Editar Local' : 'Nuevo Local' }}
            </div>
            
            <form @submit.prevent="guardar" class="p-6 grid grid-cols-2 gap-4 overflow-y-auto flex-grow">
                <div class="col-span-2">
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest">Nombre del Local</label>
                    <input v-model="formulario.nombre" @input="formulario.nombre = formulario.nombre.toUpperCase()" type="text" maxlength="255" class="w-full mt-1 rounded border-gray-300 shadow-sm uppercase font-bold focus:ring-sky-500" required>
                    <p v-if="formulario.errors.nombre" class="text-rose-500 text-[10px] mt-1 font-bold">{{ formulario.errors.nombre }}</p>
                </div>
                
                <div class="col-span-2">
                    <div class="flex justify-between items-center">
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest">Dirección Física</label>
                        <span v-if="buscandoDireccion" class="text-[10px] text-sky-600 font-bold animate-pulse">🛰️ BUSCANDO ALTURA...</span>
                    </div>
                    <div class="relative mt-1">
                        <input 
                            v-model="formulario.direccion" 
                            type="text" 
                            maxlength="255"
                            class="w-full rounded border-gray-300 shadow-sm font-medium transition-colors focus:ring-sky-500" 
                            :class="{'bg-sky-50 border-sky-300': buscandoDireccion, 'pr-8': buscandoSugerencias}"
                            placeholder="Ej: Calle Rivadavia 123"
                            @input="buscarDireccion(formulario.direccion)"
                            @blur="ocultarSugerenciasConDelay"
                            required
                        >
                        <div v-if="buscandoSugerencias" class="absolute right-3 top-1/2 -translate-y-1/2">
                            <svg class="animate-spin h-4 w-4 text-sky-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        </div>

                        <div v-if="mostrarSugerencias && sugerenciasDireccion.length" class="absolute left-0 right-0 top-full mt-1 bg-white border border-slate-200 rounded-xl shadow-2xl z-50 overflow-hidden max-h-56 overflow-y-auto">
                            <button v-for="s in sugerenciasDireccion" :key="s.place_id" type="button" @mousedown.prevent="seleccionarSugerencia(s)"
                                class="w-full text-left px-3 py-2 hover:bg-sky-50 transition-colors border-b border-slate-100 last:border-0 flex flex-col gap-0.5">
                                <span class="text-xs font-bold text-slate-700 leading-tight">{{ armarDireccion(s.address || {}) }}</span>
                                <span class="text-[10px] text-slate-400 truncate">{{ s.display_name }}</span>
                            </button>
                        </div>
                        <p v-if="formulario.errors.direccion" class="text-rose-500 text-[10px] mt-1 font-bold">{{ formulario.errors.direccion }}</p>
                    </div>
                </div>
                
                <div class="col-span-1">
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest">Teléfono</label>
                    <input v-model="formulario.telefono" type="tel" maxlength="15" @input="normalizarTelefono" class="w-full mt-1 rounded border-gray-300 shadow-sm focus:ring-sky-500" placeholder="Ej: 3764123456">
                    <p v-if="formulario.errors.telefono" class="text-rose-500 text-[10px] mt-1 font-bold">{{ formulario.errors.telefono }}</p>
                </div>
                
                <div class="col-span-1">
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest">Tipo</label>
                    <select v-model="formulario.tipo" class="w-full mt-1 rounded border-gray-300 shadow-sm focus:ring-sky-500">
                        <option value="punto_de_venta">Punto de Venta</option>
                        <option value="deposito">Depósito / Almacén</option>
                    </select>
                    <p v-if="formulario.errors.tipo" class="text-rose-500 text-[10px] mt-1 font-bold">{{ formulario.errors.tipo }}</p>
                </div>

                <div class="col-span-2 p-3 bg-orange-50 border border-orange-100 rounded-xl">
                    <label class="block text-[10px] font-black text-orange-700 uppercase tracking-widest">Costo de Envío ($)</label>
                    <div class="relative mt-1">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-orange-400 font-bold">$</span>
                        <input v-model="formulario.costo_delivery" type="number" step="1" min="0" @input="normalizarCostoDelivery" class="w-full pl-7 rounded-lg border-orange-200 font-bold focus:ring-orange-500">
                    </div>
                    <p v-if="formulario.errors.costo_delivery" class="text-rose-500 text-[10px] mt-1 font-bold">{{ formulario.errors.costo_delivery }}</p>
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