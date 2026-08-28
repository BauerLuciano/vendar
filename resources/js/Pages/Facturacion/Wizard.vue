<script setup>
import { computed, ref, watch } from 'vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Swal from 'sweetalert2';

const props = defineProps({
    configuracion: Object,
    certificado: Object,
    puntosVenta: Array,
    resultadoConexion: Array,
});

const page = usePage();
const errors = computed(() => page.props.errors || {});
const flash = computed(() => page.props.flash || {});

const pasos = [
    { id: 1, titulo: 'Contribuyente', descripcion: 'CUIT y verificación en ARCA' },
    { id: 2, titulo: 'Confirmación', descripcion: 'Datos del emisor' },
    { id: 3, titulo: 'Certificado', descripcion: 'Carga del .pfx encriptado' },
    { id: 4, titulo: 'Puntos de venta', descripcion: 'Selección de PV habilitados' },
    { id: 5, titulo: 'Probar conexión', descripcion: 'Verificación con ARCA' },
    { id: 6, titulo: 'Activación', descripcion: 'Resumen y activación del módulo' },
];

const estadoActual = computed(() => props.configuracion?.estado_modulo || 'sin_datos');
const listaParaFacturar = computed(() => props.configuracion?.lista_para_facturar || false);

const indiceEstado = computed(() => {
    const secuencia = ['sin_datos', 'datos_cargados', 'datos_validados', 'cert_cargado', 'pv_habilitado', 'listo_para_facturar'];
    const idx = secuencia.indexOf(estadoActual.value);
    return idx >= 0 ? idx : 0;
});

const pasoActual = ref(Math.min(indiceEstado.value, pasos.length - 1));

const pasoCompletado = (indice) => indice < indiceEstado.value;
const pasoDisponible = (indice) => indice <= indiceEstado.value;

const cuitForm = useForm({
    cuit: props.configuracion?.cuit ? props.configuracion.cuit.replaceAll('-', '') : '',
    entorno: props.configuracion?.entorno || 'homologacion',
});

const soloDigitos = (valor) => (valor || '').replace(/\D/g, '').slice(0, 11);

const formatearCuit = (valor) => {
    const d = soloDigitos(valor);
    if (d.length <= 2) return d;
    if (d.length <= 10) return `${d.slice(0, 2)}-${d.slice(2)}`;
    return `${d.slice(0, 2)}-${d.slice(2, 10)}-${d.slice(10)}`;
};

const cuitInput = ref(formatearCuit(props.configuracion?.cuit || ''));

const onCuitInput = (evento) => {
    const el = evento.target;
    const viejo = el.value;
    const posVieja = el.selectionStart ?? viejo.length;
    const nuevo = formatearCuit(viejo);

    el.value = nuevo;
    cuitInput.value = nuevo;
    cuitForm.cuit = soloDigitos(nuevo);

    let pos;
    if (nuevo.length > viejo.length) {
        pos = nuevo.length;
    } else {
        const digitosAntes = (viejo.slice(0, posVieja).match(/\d/g) || []).length;
        pos = 0;
        let d = 0;
        while (pos < nuevo.length && d < digitosAntes) {
            if (/\d/.test(nuevo[pos])) d++;
            pos++;
        }
    }
    el.setSelectionRange(pos, pos);
};

watch(() => props.configuracion?.cuit, (nuevo) => {
    if (nuevo) {
        cuitForm.cuit = soloDigitos(nuevo);
        cuitInput.value = formatearCuit(nuevo);
    }
});

watch(() => props.configuracion?.domicilio_fiscal, (nuevo) => {
    if (nuevo) {
        datosForm.domicilio_fiscal = nuevo;
    }
});

const datosForm = useForm({
    domicilio_fiscal: props.configuracion?.domicilio_fiscal || '',
});

const certificadoForm = useForm({
    archivo_pfx: null,
    password_pfx: '',
});

const puntoVentaForm = useForm({
    punto_venta: props.configuracion?.punto_venta_activo || null,
});

const cuitError = ref('');

const esCuitValido = (cuit) => {
    if (!/^\d{11}$/.test(cuit)) return false;
    const pesos = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
    const digitos = cuit.split('').map(Number);
    const suma = digitos.slice(0, 10).reduce((acc, d, i) => acc + d * pesos[i], 0);
    const resto = suma % 11;
    let verificador = 11 - resto;
    if (verificador === 11) verificador = 0;
    if (verificador === 10) verificador = 9;
    return verificador === digitos[10];
};

const verificarCuit = () => {
    cuitForm.cuit = cuitForm.cuit.replace(/\D/g, '').slice(0, 11);
    if (cuitForm.cuit.length !== 11) {
        cuitError.value = 'El CUIT debe tener 11 dígitos.';
        return;
    }
    if (!esCuitValido(cuitForm.cuit)) {
        cuitError.value = 'El CUIT no tiene un dígito verificador válido.';
        return;
    }
    cuitError.value = '';

    const confirmacionProduccion = () => {
        cuitForm.post(route('configuracion.fiscal.cuit'), {
            preserveScroll: true,
            onSuccess: () => {
                cuitForm.reset('cuit');
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'CUIT verificado', showConfirmButton: false, timer: 2000 });
                router.reload({ only: ['configuracion', 'certificado', 'puntosVenta'] });
            },
        });
    };

    if (cuitForm.entorno === 'produccion') {
        Swal.fire({
            title: '¿Verificar en producción?',
            text: 'Estás por verificar el CUIT contra ARCA en el entorno de PRODUCCIÓN. Confirmá que sea intencional.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, verificar en producción',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc2626',
        }).then((result) => {
            if (result.isConfirmed) confirmacionProduccion();
        });
        return;
    }

    confirmacionProduccion();
};

const confirmarDatos = () => {
    datosForm.post(route('configuracion.fiscal.datos'), {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Datos confirmados', showConfirmButton: false, timer: 2000 });
            router.reload({ only: ['configuracion'] });
        },
    });
};

const archivoSeleccionado = (e) => {
    certificadoForm.archivo_pfx = e.target.files[0] || null;
};

const cargarCertificado = () => {
    if (!certificadoForm.archivo_pfx) {
        Swal.fire('Falta el archivo', 'Seleccioná el archivo .pfx del certificado.', 'warning');
        return;
    }
    const nombre = certificadoForm.archivo_pfx.name || '';
    if (!nombre.toLowerCase().endsWith('.pfx')) {
        Swal.fire('Archivo inválido', 'El archivo debe tener extensión .pfx.', 'warning');
        return;
    }
    if (!certificadoForm.password_pfx) {
        Swal.fire('Falta la contraseña', 'Ingresá la contraseña del certificado.', 'warning');
        return;
    }
    certificadoForm.post(route('configuracion.fiscal.certificado'), {
        preserveScroll: true,
        onSuccess: () => {
            certificadoForm.reset('archivo_pfx', 'password_pfx');
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Certificado cargado', showConfirmButton: false, timer: 2000 });
            router.reload({ only: ['configuracion', 'certificado', 'puntosVenta'] });
        },
    });
};

const seleccionarPuntoVenta = () => {
    if (!puntoVentaForm.punto_venta) {
        Swal.fire('Elegí un punto de venta', 'Seleccioná uno de los puntos de venta disponibles.', 'warning');
        return;
    }
    puntoVentaForm.post(route('configuracion.fiscal.punto-venta'), {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Punto de venta seleccionado', showConfirmButton: false, timer: 2000 });
            router.reload({ only: ['configuracion'] });
        },
    });
};

const probando = ref(false);

const probarConexion = () => {
    probando.value = true;
    router.post(route('configuracion.fiscal.probar-conexion'), {}, {
        preserveScroll: true,
        onFinish: () => {
            probando.value = false;
            router.reload({ only: ['resultadoConexion'] });
        },
    });
};

const activando = ref(false);

const activar = () => {
    Swal.fire({
        title: '¿Activar facturación electrónica?',
        text: 'El comercio quedará listo para emitir comprobantes fiscales con ARCA.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, activar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#4f46e5',
    }).then((result) => {
        if (!result.isConfirmed) return;
        activando.value = true;
        router.post(route('configuracion.fiscal.activar'), {}, {
            preserveScroll: true,
            onSuccess: () => {
                activando.value = false;
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Módulo activado', showConfirmButton: false, timer: 2000 });
                router.reload({ only: ['configuracion'] });
            },
            onFinish: () => { activando.value = false; },
        });
    });
};

const conexionOk = computed(() =>
    Array.isArray(props.resultadoConexion) && props.resultadoConexion.length > 0
        ? props.resultadoConexion.every(r => r.ok)
        : null
);

const enviarPaso = () => {
    const paso = pasos[pasoActual.value];
    if (paso.id === 1) verificarCuit();
    if (paso.id === 2) confirmarDatos();
    if (paso.id === 3) cargarCertificado();
    if (paso.id === 4) seleccionarPuntoVenta();
    if (paso.id === 5) probarConexion();
    if (paso.id === 6) activar();
};

const aviso = computed(() => flash.value?.success || flash.value?.error || '');
</script>

<template>
    <Head title="Configuración Fiscal — VendAR" />

    <AuthenticatedLayout>
        <div class="py-8 px-4 sm:px-6 lg:px-8 max-w-5xl mx-auto bg-slate-50 min-h-screen">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-black text-slate-800 tracking-tight">Facturación Electrónica</h1>
                <p class="text-slate-500 font-medium text-sm mt-1">Configurá el comercio para emitir comprobantes con ARCA</p>

                <a :href="route('configuracion.fiscal.diagnostico')"
                   class="inline-flex items-center gap-2 mt-3 px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-900 text-white text-sm font-black transition-colors">
                    Ver diagnóstico fiscal
                </a>

                <div v-if="listaParaFacturar" class="mt-4 inline-flex items-center gap-2 bg-emerald-100 text-emerald-700 px-4 py-2 rounded-full text-sm font-black">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    Listo para facturar
                </div>
                <div v-else class="mt-4 inline-flex items-center gap-2 bg-amber-100 text-amber-700 px-4 py-2 rounded-full text-sm font-black">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                    Estado: {{ estadoActual.replaceAll('_', ' ') }}
                </div>
            </div>

            <div v-if="aviso" class="mb-6 px-4 py-3 rounded-xl text-sm font-bold"
                 :class="flash?.success ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200'">
                {{ aviso }}
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                <!-- SIDEBAR -->
                <div class="lg:col-span-4">
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 sticky top-8">
                        <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-4 px-2">Pasos</h3>
                        <div class="space-y-1">
                            <button v-for="(paso, index) in pasos" :key="paso.id"
                                @click="pasoActual = index"
                                :disabled="!pasoDisponible(index)"
                                class="w-full text-left px-3 py-3 rounded-xl flex items-center gap-3 transition-all group disabled:opacity-60"
                                :class="index === pasoActual ? 'bg-indigo-50 border border-indigo-200' : 'hover:bg-slate-50'">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 text-xs font-black"
                                     :class="pasoCompletado(index) ? 'bg-emerald-100 text-emerald-600' : (index === pasoActual ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-500')">
                                    <svg v-if="pasoCompletado(index)" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                    <span v-else>{{ index + 1 }}</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="font-bold text-sm truncate" :class="index === pasoActual ? 'text-indigo-700' : 'text-slate-700'">{{ paso.titulo }}</div>
                                    <div class="text-[11px] text-slate-400 font-medium truncate">{{ paso.descripcion }}</div>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- CONTENIDO -->
                <div class="lg:col-span-8">
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="px-6 py-5 border-b border-slate-100">
                            <h2 class="font-black text-xl text-slate-800">{{ pasos[pasoActual].titulo }}</h2>
                            <p class="text-sm text-slate-500 font-medium">{{ pasos[pasoActual].descripcion }}</p>
                        </div>

                        <div class="p-6 space-y-6">
                            <!-- PASO 1: CUIT -->
                            <div v-if="pasos[pasoActual].id === 1" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-bold text-slate-700 mb-1">CUIT del comercio</label>
                                    <input :value="cuitInput" @input="onCuitInput" type="text" inputmode="numeric" maxlength="14"
                                        placeholder="20-12345678-6"
                                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                                    <p class="text-[11px] text-slate-400 font-medium mt-1 flex justify-between">
                                        <span>Formato XX-XXXXXXXX-X · se envían los 11 dígitos</span>
                                        <span class="font-bold" :class="cuitForm.cuit.length === 11 ? 'text-emerald-600' : ''">{{ cuitForm.cuit.length }}/11</span>
                                    </p>
                                    <p v-if="cuitError" class="text-xs text-red-600 font-semibold mt-1">{{ cuitError }}</p>
                                    <p v-if="errors.cuit" class="text-xs text-red-600 font-semibold mt-1">{{ errors.cuit }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-slate-700 mb-1">Entorno</label>
                                    <select v-model="cuitForm.entorno" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-sm">
                                        <option value="produccion">Producción</option>
                                        <option value="homologacion">Homologación</option>
                                    </select>
                                </div>
                                <div v-if="configuracion?.razon_social" class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-sm">
                                    <div class="text-slate-400 text-xs font-black uppercase tracking-widest mb-1">Datos del padrón</div>
                                    <p class="font-black text-slate-700">{{ configuracion.razon_social }}</p>
                                    <p class="text-slate-500 font-medium">{{ configuracion.condicion_fiscal_label }}</p>
                                    <p class="text-slate-500 font-medium">{{ configuracion.cuit }}</p>
                                </div>
                            </div>

                            <!-- PASO 2: CONFIRMACIÓN -->
                            <div v-else-if="pasos[pasoActual].id === 2" class="space-y-4">
                                <div v-if="configuracion?.razon_social" class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-sm">
                                    <div class="text-slate-400 text-xs font-black uppercase tracking-widest mb-1">Emisor (padrón)</div>
                                    <p class="font-black text-slate-700">{{ configuracion.razon_social }}</p>
                                    <p class="text-slate-500 font-medium">{{ configuracion.condicion_fiscal_label }} · {{ configuracion.cuit }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-slate-700 mb-1">Domicilio comercial</label>
                                    <input v-model="datosForm.domicilio_fiscal" type="text" maxlength="255"
                                        placeholder="Calle, número y localidad"
                                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-sm" />
                                    <p v-if="errors.datos" class="text-xs text-red-600 font-semibold mt-1">{{ errors.datos }}</p>
                                </div>
                            </div>

                            <!-- PASO 3: CERTIFICADO -->
                            <div v-else-if="pasos[pasoActual].id === 3" class="space-y-4">
                                <div v-if="certificado" class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 text-sm">
                                    <div class="text-emerald-500 text-xs font-black uppercase tracking-widest mb-1">Certificado cargado</div>
                                    <p class="font-black text-emerald-800">{{ certificado.distinguished_name }}</p>
                                    <p class="text-emerald-700 font-medium">Serie: {{ certificado.numero_serie }}</p>
                                    <p class="text-emerald-700 font-medium" :class="{ 'text-red-600 font-black': certificado.vencido }">
                                        Vigencia: {{ certificado.vigencia_desde }} → {{ certificado.vigencia_hasta }}
                                        <span v-if="certificado.vencido"> (VENCIDO)</span>
                                    </p>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-slate-700 mb-1">Archivo .pfx del certificado</label>
                                    <input type="file" accept=".pfx" @change="archivoSeleccionado"
                                        class="w-full text-sm text-slate-600 file:mr-3 file:px-4 file:py-2 file:rounded-xl file:border-0 file:bg-indigo-600 file:text-white file:font-bold hover:file:bg-indigo-700" />
                                    <p v-if="errors.certificado" class="text-xs text-red-600 font-semibold mt-1">{{ errors.certificado }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-slate-700 mb-1">Contraseña del certificado</label>
                                    <input v-model="certificadoForm.password_pfx" type="password"
                                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-sm" />
                                    <p class="text-[11px] text-slate-400 font-medium mt-1">Se almacena encriptada; nunca se expone.</p>
                                </div>
                            </div>

                            <!-- PASO 4: PUNTOS DE VENTA -->
                            <div v-else-if="pasos[pasoActual].id === 4" class="space-y-4">
                                <p v-if="puntosVenta.length === 0" class="text-sm text-slate-500 font-medium">
                                    No hay puntos de venta disponibles. Cargá un certificado válido y probá la conexión.
                                </p>
                                <div v-else class="space-y-2">
                                    <button v-for="pv in puntosVenta" :key="pv.nro"
                                        @click="puntoVentaForm.punto_venta = pv.nro"
                                        :disabled="pv.bloqueado"
                                        class="w-full flex items-center justify-between px-4 py-3 rounded-xl border text-left transition-all disabled:opacity-40"
                                        :class="puntoVentaForm.punto_venta === pv.nro ? 'border-indigo-500 bg-indigo-50' : 'border-slate-200 hover:border-indigo-300'">
                                        <span class="font-black text-slate-800">Punto de venta {{ pv.nro }}</span>
                                        <span v-if="pv.bloqueado" class="text-[10px] font-black uppercase text-red-500">Bloqueado</span>
                                        <span v-else-if="puntoVentaForm.punto_venta === pv.nro" class="text-indigo-600 text-xs font-black">Seleccionado</span>
                                    </button>
                                </div>
                                <p v-if="errors.punto_venta" class="text-xs text-red-600 font-semibold">{{ errors.punto_venta }}</p>
                                <p v-if="configuracion?.punto_venta_activo" class="text-xs text-emerald-600 font-black">
                                    Punto de venta activo: {{ configuracion.punto_venta_activo }}
                                </p>
                            </div>

                            <!-- PASO 5: PROBAR CONEXIÓN -->
                            <div v-else-if="pasos[pasoActual].id === 5" class="space-y-4">
                                <div v-if="resultadoConexion && resultadoConexion.length" class="space-y-2">
                                    <div v-for="r in resultadoConexion" :key="r.check"
                                         class="flex items-start gap-3 px-4 py-3 rounded-xl border text-sm"
                                         :class="r.ok ? 'bg-emerald-50 border-emerald-200' : 'bg-red-50 border-red-200'">
                                        <span class="mt-0.5 w-5 h-5 rounded-full flex items-center justify-center text-xs font-black"
                                              :class="r.ok ? 'bg-emerald-500 text-white' : 'bg-red-500 text-white'">
                                            {{ r.ok ? '✓' : '✗' }}
                                        </span>
                                        <div>
                                            <p class="font-black text-slate-700 capitalize">{{ r.check.replaceAll('_', ' ') }}</p>
                                            <p class="text-slate-500 font-medium">{{ r.detalle }}</p>
                                        </div>
                                    </div>
                                </div>
                                <p v-else class="text-sm text-slate-500 font-medium">
                                    Ejecutá la suite para verificar certificado, WSAA, WSFE y padrón sin emitir comprobantes.
                                </p>
                                <p v-if="errors.conexion" class="text-xs text-red-600 font-semibold">{{ errors.conexion }}</p>
                            </div>

                            <!-- PASO 6: ACTIVACIÓN -->
                            <div v-else-if="pasos[pasoActual].id === 6" class="space-y-4">
                                <div class="bg-slate-50 border border-slate-200 rounded-xl p-5 text-sm space-y-2">
                                    <div class="flex justify-between"><span class="text-slate-500 font-medium">CUIT</span><span class="font-black text-slate-800">{{ configuracion?.cuit || '—' }}</span></div>
                                    <div class="flex justify-between"><span class="text-slate-500 font-medium">Razón social</span><span class="font-black text-slate-800">{{ configuracion?.razon_social || '—' }}</span></div>
                                    <div class="flex justify-between"><span class="text-slate-500 font-medium">Condición fiscal</span><span class="font-black text-slate-800">{{ configuracion?.condicion_fiscal_label || '—' }}</span></div>
                                    <div class="flex justify-between"><span class="text-slate-500 font-medium">Domicilio</span><span class="font-black text-slate-800">{{ configuracion?.domicilio_fiscal || '—' }}</span></div>
                                    <div class="flex justify-between"><span class="text-slate-500 font-medium">Entorno</span><span class="font-black text-slate-800 capitalize">{{ configuracion?.entorno || 'homologacion' }}</span></div>
                                    <div class="flex justify-between"><span class="text-slate-500 font-medium">Punto de venta</span><span class="font-black text-slate-800">{{ configuracion?.punto_venta_activo || '—' }}</span></div>
                                    <div class="flex justify-between"><span class="text-slate-500 font-medium">Certificado</span><span class="font-black text-slate-800">{{ certificado ? 'Cargado y encriptado' : '—' }}</span></div>
                                </div>
                                <p v-if="errors.activar" class="text-xs text-red-600 font-semibold">{{ errors.activar }}</p>
                            </div>
                        </div>

                        <!-- ACCIONES -->
                        <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex justify-between items-center">
                            <button @click="pasoActual = Math.max(0, pasoActual - 1)" :disabled="pasoActual === 0"
                                class="px-5 py-2 text-sm font-bold text-slate-500 hover:text-slate-700 uppercase tracking-widest disabled:opacity-30 transition-colors">
                                ← Anterior
                            </button>

                            <button v-if="pasoActual < pasos.length - 1" @click="enviarPaso"
                                :disabled="probando || activando || cuitForm.processing || datosForm.processing || certificadoForm.processing || puntoVentaForm.processing"
                                class="bg-indigo-600 hover:bg-indigo-700 text-white px-8 py-2.5 rounded-xl font-black shadow-lg shadow-indigo-600/30 transition-all uppercase tracking-widest text-sm disabled:opacity-50">
                                {{ pasoActual < pasos.length - 1 && cuitForm.processing ? 'Verificando…' :
                                   (pasoActual < pasos.length - 1 && datosForm.processing ? 'Guardando…' :
                                   (pasoActual < pasos.length - 1 && certificadoForm.processing ? 'Subiendo…' :
                                   (pasoActual < pasos.length - 1 && puntoVentaForm.processing ? 'Guardando…' :
                                   (pasos[pasoActual].id === 5 ? (probando ? 'Probando…' : 'Probar conexión') :
                                   (pasos[pasoActual].id === 4 ? 'Seleccionar PV' : 'Continuar'))))) }}
                            </button>

                            <button v-else @click="activar" :disabled="activando || listaParaFacturar"
                                class="bg-emerald-600 hover:bg-emerald-700 text-white px-8 py-2.5 rounded-xl font-black shadow-lg shadow-emerald-600/30 transition-all uppercase tracking-widest text-sm disabled:opacity-50">
                                {{ activando ? 'Activando…' : (listaParaFacturar ? 'Módulo activo' : 'Activar módulo') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
