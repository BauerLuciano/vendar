<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    comercio: Object,
    planes: Array,
    // Estado de la suscripción calculado en el backend. El cliente no deduce
    // vencimientos por su cuenta para no discrepar con el middleware de corte.
    suscripcion: {
        type: Object,
        default: () => ({
            estado: 'sin_vencimiento',
            dias_restantes: null,
            vencimiento_pago: null,
            puede_renovar: false,
            suspendido: false,
        }),
    },
});

const planCargando = ref(null);
const estadoPago = ref(null);
const polling = ref(null);
const planRenovado = ref(null);
const planObjetivo = ref(null);
const cargandoAccion = ref(false);

// Mensaje real del backend para los errores de confirmación (400/403/502).
// Antes no existía: el `catch` genérico mandaba todo al polling y el usuario
// nunca veía el motivo por el que su pago no se confirmaba.
const mensajeError = ref(null);

const RENOVACION_KEY = 'vendar_renovacion_pendiente';
const RENOVACION_MAX_EDAD_MS = 2 * 60 * 60 * 1000;

const leerRenovacionPendiente = () => {
    try {
        const raw = localStorage.getItem(RENOVACION_KEY);
        if (!raw) {
            return null;
        }
        const datos = JSON.parse(raw);
        if (!datos?.plan_id || !datos?.ts) {
            localStorage.removeItem(RENOVACION_KEY);
            return null;
        }
        if (Date.now() - datos.ts > RENOVACION_MAX_EDAD_MS) {
            localStorage.removeItem(RENOVACION_KEY);
            return null;
        }
        return datos;
    } catch {
        return null;
    }
};

const limpiarUrl = () => {
    if (window.location.search) {
        history.replaceState({}, '', window.location.pathname);
    }
};

const finalizarAprobado = () => {
    localStorage.removeItem(RENOVACION_KEY);
    estadoPago.value = 'aprobado';
    setTimeout(() => {
        limpiarUrl();
        window.location.href = window.location.pathname;
    }, 1500);
};

// El backend resuelve si la suscripción admite renovación. Antes se calculaba
// acá con `new Date(vencimiento_pago) < new Date()`, lo que sólo habilitaba la
// renovación cuando la fecha ya había pasado y dejaba el plan actual sin
// ninguna acción durante toda la ventana de preaviso.
const estadoActual = computed(() => props.suscripcion?.estado ?? 'sin_vencimiento');
const diasRestantes = computed(() => props.suscripcion?.dias_restantes ?? null);
const puedeRenovar = computed(() => props.suscripcion?.puede_renovar === true);
const estaSuspendido = computed(() => props.suscripcion?.suspendido === true);
const vencimientoActual = computed(() => props.suscripcion?.vencimiento_pago ?? null);

// Ventana de renovación anticipada y cuenta regresiva. Los calcula el backend
// (`SuscripcionService::VENTANA_RENOVACION_DIAS`): el 30 no está hardcodeado
// en el cliente para que cambiar la regla no obligue a tocar Vue.
const ventanaRenovacion = computed(() => props.suscripcion?.ventana_renovacion_dias ?? 30);
const diasParaRenovar = computed(() => props.suscripcion?.dias_para_renovar ?? null);

// Renovación "urgente": por vencer, vencida o suspendida. Distinto de
// `puedeRenovar`, que es la ventana de 30 días (a los 20 días se puede
// renovar pero no es urgente y no corresponde pintarlo en ámbar).
const renovacionUrgente = computed(
    () =>
        puedeRenovar.value &&
        (estadoActual.value === 'por_vencer' ||
            estadoActual.value === 'vencida' ||
            estaSuspendido.value)
);

const textoPlazo = computed(() => {
    const d = diasRestantes.value;
    if (d === null) {
        return null;
    }
    if (d < 0) {
        const n = Math.abs(d);
        return `Vencida hace ${n} ${n === 1 ? 'día' : 'días'}`;
    }
    if (d === 0) {
        return 'Vence hoy';
    }
    if (d === 1) {
        return 'Vence mañana';
    }
    return `Vence en ${d} días`;
});

// --- Tarjeta "Tu suscripción" ---------------------------------------------
// Antes eran tres renglones de texto suelto dentro de un div, indistinguibles
// del resto de la tarjeta de plan. Ahora es un estado con encabezado, badge y
// dos métricas (vencimiento / tiempo restante), para que se lea de un vistazo.

const ETIQUETAS_ESTADO = {
    activa: 'Activa',
    por_vencer: 'Por vencer',
    vencida: 'Vencida',
    suspendida: 'Suspendida',
    sin_vencimiento: 'Sin vencimiento',
};

const etiquetaEstado = computed(() =>
    estaSuspendido.value && estadoActual.value !== 'vencida'
        ? ETIQUETAS_ESTADO.suspendida
        : (ETIQUETAS_ESTADO[estadoActual.value] ?? ETIQUETAS_ESTADO.sin_vencimiento)
);

// Paleta de la tarjeta completa, incluido el badge. El esquema crítico (rosa)
// se aplica cuando la suscripción está vencida o suspendida y hay que actuar ya.
const paletaEstado = computed(() => {
    if (estaSuspendido.value || estadoActual.value === 'vencida') {
        return {
            caja: 'border-rose-200 bg-rose-50/70',
            cabecera: 'bg-rose-100/80 text-rose-700',
            badge: 'bg-rose-600 text-white',
            metricas: 'text-rose-900',
            pie: 'bg-rose-100/70 text-rose-700',
            critico: true,
        };
    }
    if (estadoActual.value === 'por_vencer') {
        return {
            caja: 'border-amber-200 bg-amber-50/70',
            cabecera: 'bg-amber-100/80 text-amber-800',
            badge: 'bg-amber-500 text-white',
            metricas: 'text-amber-950',
            pie: 'bg-amber-100/70 text-amber-800',
            critico: false,
        };
    }
    return {
        caja: 'border-slate-200 bg-slate-50',
        cabecera: 'bg-slate-100/80 text-slate-500',
        badge: 'bg-slate-700 text-white',
        metricas: 'text-slate-800',
        pie: 'bg-slate-100/70 text-slate-600',
        critico: false,
    };
});

// Texto del pie: por qué el botón está o no disponible.
const notaRenovacion = computed(() => {
    if (estaSuspendido.value) {
        return { icono: '🔒', texto: 'Renová tu plan para volver a operar' };
    }
    if (estadoActual.value === 'vencida') {
        return { icono: '⚠️', texto: 'Suscripción vencida: renová para seguir operando' };
    }
    if (!puedeRenovar.value) {
        const d = diasParaRenovar.value;
        const n = d === null ? ventanaRenovacion.value : d;
        return {
            icono: '🔒',
            texto: `Renovación disponible en ${n} ${n === 1 ? 'día' : 'días'}`,
        };
    }
    if (renovacionUrgente.value) {
        return { icono: '🔄', texto: 'Podés renovar ahora mismo' };
    }
    return {
        icono: '✅',
        texto: 'Renovación disponible: podés renovarlo cuando quieras',
    };
});

// Espejo local del pago en vuelo que reporta el backend. Se refresca en cada
// tick del polling para que la UI no dependa de recargar la página.
const pagoEnVuelo = ref(props.suscripcion?.pago_en_vuelo ?? null);

const hayPagoEnVuelo = computed(() => pagoEnVuelo.value !== null);

// Mientras hay una preferencia viva en Mercado Pago no se habilita ningún
// botón: evita generar una segunda y cobrar dos veces por la misma intención.
// Ojo: `timeout` NO bloquea, porque el polling ya se agotó y el usuario tiene
// que poder reintentar. `aprobado` se auto-recarga, así que tampoco hace falta.
const hayPagoEnCurso = computed(() =>
    hayPagoEnVuelo.value || ['procesando', 'pendiente', 'en_curso'].includes(estadoPago.value)
);

const formatearDinero = (monto) => {
    return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS', maximumFractionDigits: 0 }).format(monto);
};

const esPlanActual = (planId) => {
    const currentId = typeof props.comercio?.plan_id === 'number'
        ? props.comercio.plan_id
        : (props.planes.find(p => p.slug === props.comercio?.plan)?.id);
    return currentId === planId;
};

const pagarPlan = async (planId) => {
    planCargando.value = planId;
    try {
        const response = await axios.post(route('suscripcion.pagar'), {
            plan_id: planId,
            origin: window.location.origin,
        });
        if (response.data?.init_point) {
            localStorage.setItem(RENOVACION_KEY, JSON.stringify({
                plan_id: planId,
                es_renovacion: response.data?.es_renovacion === true,
                ts: Date.now(),
            }));
            window.location.href = response.data.init_point;
        }
    } catch (error) {
        // 409: ya hay una preferencia de pago en curso para este comercio.
        if (error.response?.status === 409) {
            estadoPago.value = 'en_curso';
            return;
        }

        // 422: el backend rechazó la renovación anticipada por la ventana de
        // 30 días. Aunque el botón ya está deshabilitado y muestra la cuenta
        // regresiva, se puede llegar igual (pestaña abierta antes del cambio,
        // bundle viejo en caché). Se muestra el motivo real del servidor en
        // lugar del "Error al conectar con Mercado Pago" genérico, que recién
        // volvió a aparecer de un error que no tiene nada de pasarela.
        if (error.response?.status === 422) {
            alert(
                error.response.data?.error
                    || 'La renovación anticipada todavía no está disponible.'
            );
            return;
        }

        const msg = error.response?.data?.error || 'Error al conectar con Mercado Pago.';
        alert(msg);
    } finally {
        planCargando.value = null;
    }
};

const recargarPagina = () => {
    limpiarUrl();
    window.location.href = window.location.pathname;
};

const confirmarUpgrade = async (planId, paymentId) => {
    try {
        const response = await axios.post(route('suscripcion.confirmar-upgrade'), {
            plan_id: planId,
            payment_id: paymentId,
        });
        if (response.data?.status === 'ok' || response.data?.status === 'already_upgraded') {
            if (response.data?.plan) {
                planRenovado.value = response.data.plan;
            }
            finalizarAprobado();
            return;
        }

        // El backend todavía no lo confirma (el webhook va a procesarlo):
        // acá sí tiene sentido esperar, porque el pago puede estar approving.
        estadoPago.value = 'procesando';
        iniciarPolling(planId);
    } catch (error) {
        const status = error.response?.status;
        const mensaje = error.response?.data?.error;

        // El backend responde con un mensaje explícito y accionable. Antes se
        // descartaba: cualquier 4xx/5xx caía al polling y el usuario veía
        // "Estamos procesando tu pago" durante 90 segundos para un pago que ya
        // estaba rechazado, o que nunca iba a confirmarse.
        if (status === 403) {
            // El pago pertenece a otro comercio: reintentar no sirve y sí
            // expondría el pago ajeno.
            mensajeError.value = mensaje || 'El pago no corresponde a este comercio.';
            estadoPago.value = 'error_fatal';
            return;
        }

        if (status === 400) {
            // El pago no está aprobado, o el plan no coincide con la
            // intención. El webhook no va a cambiar nada: hay que rehacerlo.
            mensajeError.value = mensaje || 'El pago no pudo confirmarse.';
            estadoPago.value = 'error';
            return;
        }

        if (status === 502) {
            // Falló la consulta a Mercado Pago. El pago puede estar aprobado
            // igual, así que no se descarta: se informa el motivo y se ofrece
            // consultar el estado, sin arrancar una espera automática.
            mensajeError.value = mensaje || 'No se pudo verificar el pago con Mercado Pago.';
            estadoPago.value = 'verificar';
            return;
        }

        if (!error.response) {
            // Sin respuesta del servidor: no sabemos nada. Puede ser un corte
            // de red con el pago ya hecho, así que se ofrece verificar en
            // lugar de afirmar que falló.
            mensajeError.value = 'No pudimos contactarte con el servidor. Revisá tu conexión.';
            estadoPago.value = 'verificar';
            return;
        }

        mensajeError.value = mensaje || 'Ocurrió un error inesperado al confirmar el pago.';
        estadoPago.value = 'error';
    }
};

/**
 * Consulta el estado del pago bajo demanda. Es la salida para el 502 y para
 * los errores de red: el polling queda a decisión del usuario en vez de
 * arrancarse solo y bloquear la pantalla.
 */
const verificarEstadoPago = () => {
    if (!planObjetivo.value) {
        recargarPagina();
        return;
    }
    mensajeError.value = null;
    estadoPago.value = 'procesando';
    iniciarPolling(planObjetivo.value);
};

const detenerPolling = () => {
    if (polling.value) {
        clearInterval(polling.value);
        polling.value = null;
    }
};

const iniciarPolling = (planId) => {
    if (polling.value) {
        return;
    }
    planObjetivo.value = planId;
    let segundos = 0;
    polling.value = setInterval(async () => {
        segundos += 3;
        try {
            const res = await axios.get(route('suscripcion.plan-actual'));

            // El backend es la fuente de verdad. `plan_id === planId` con
            // `pending_plan_id` limpio significa que el pago ya se aplicó.
            if (res.data.plan_id === planId && res.data.pending_plan_id === null) {
                detenerPolling();
                finalizarAprobado();
                return;
            }

            const vuelo = res.data.suscripcion?.pago_en_vuelo ?? null;
            pagoEnVuelo.value = vuelo;

            // Si ya no queda ninguna preferencia viva y `pending_plan_id`
            // tampoco está, el pago fue liberado (rechazado, cancelado,
            // expirado o cancelado desde esta pantalla). No va a llegar
            // nunca: hay que devolverle el control al usuario en vez de
            // dejarlo mirando "Estamos procesando tu pago" para siempre.
            if (!vuelo && res.data.pending_plan_id === null) {
                detenerPolling();
                localStorage.removeItem(RENOVACION_KEY);
                limpiarUrl();
                estadoPago.value = 'liberado';
                return;
            }
        } catch {
            // silent
        }
        if (segundos >= 90) {
            detenerPolling();
            estadoPago.value = 'timeout';
        }
    }, 3000);
};

/**
 * Libera el pago en vuelo desde la pantalla: descarta la preferencia abierta
 * en Mercado Pago y limpia `pending_plan_id`. Sin esto el usuario que
 * seleccionó un plan por error y nunca pagó quedaba bloqueado por el 409
 * durante toda la ventana de 30 minutos, sin salida.
 */
const cancelarPago = async () => {
    cargandoAccion.value = true;
    try {
        const response = await axios.post(route('suscripcion.cancelar-pago'));
        pagoEnVuelo.value = response.data?.suscripcion?.pago_en_vuelo ?? null;
    } catch {
        pagoEnVuelo.value = null;
    } finally {
        cargandoAccion.value = false;
        detenerPolling();
        localStorage.removeItem(RENOVACION_KEY);
        limpiarUrl();
        estadoPago.value = 'liberado';
    }
};

const reintentarPago = () => {
    mensajeError.value = null;
    if (planObjetivo.value) {
        estadoPago.value = null;
        pagarPlan(planObjetivo.value);
    } else {
        recargarPagina();
    }
};

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    const pago = params.get('pago');
    const urlPlanId = params.get('plan_id') ? Number(params.get('plan_id')) : null;
    const paymentId = params.get('payment_id');

    const pendiente = leerRenovacionPendiente();
    const vuelo = pagoEnVuelo.value;

    // El navegador recuerda una renovación, pero el backend ya no tiene ninguna
    // preferencia viva. Eso significa que el pago se resolvió en otra pestaña o
    // sesión (o que nunca llegó a generarse). El marcador local es basura y hay
    // que descartarlo: antes se conservaba hasta 2 horas y re-armaba el banner
    // "Estamos procesando tu pago" en cada navegación, sin que hubiera nada
    // procesándose realmente.
    if (pendiente && !vuelo && !params.has('pago')) {
        localStorage.removeItem(RENOVACION_KEY);
        limpiarUrl();
        return;
    }

    const planId = urlPlanId
        ?? (vuelo ? vuelo.plan_id : null)
        ?? pendiente?.plan_id
        ?? props.planes.find(p => p.slug === props.comercio?.plan_pendiente)?.id
        ?? null;

    if (pago === 'error') {
        localStorage.removeItem(RENOVACION_KEY);
        limpiarUrl();
        estadoPago.value = 'error';
        return;
    }

    // El backend tiene una preferencia viva: hay un pago pendiente real. Se
    // muestra el banner y se espera la confirmación de Mercado Pago.
    if (planId && (pago === 'exito' || vuelo)) {
        planRenovado.value = props.planes.find(p => p.id === planId) || null;
        planObjetivo.value = planId;
        estadoPago.value = 'procesando';

        if (pago === 'exito' && paymentId) {
            confirmarUpgrade(planId, paymentId);
        } else {
            iniciarPolling(planId);
        }
        return;
    }

    if (pendiente) {
        planObjetivo.value = planId;
        estadoPago.value = 'timeout';
        return;
    }

    if (pago === 'pendiente') {
        estadoPago.value = 'pendiente';
    }
});

onUnmounted(() => {
    detenerPolling();
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Mi Plan | VendAR" />

        <div class="py-12 bg-slate-50 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <header class="mb-10 px-4 sm:px-0">
                    <h1 class="text-3xl font-black text-slate-800 uppercase tracking-tighter italic">
                        Configuración de <span class="text-[#00adef]">Suscripción</span>
                    </h1>
                    <p class="text-slate-500 font-bold text-sm uppercase tracking-widest mt-1">
                        Gestioná tu plan y habilitá nuevas funciones para tu negocio
                    </p>
                </header>

                <!-- Payment Status Banner -->
                <div v-if="estadoPago === 'procesando'" class="mx-4 sm:mx-0 mb-8 bg-blue-50 border border-blue-200 text-blue-800 rounded-2xl p-6 text-center">
                    <p class="font-bold text-lg">Procesando tu pago...</p>
                    <p class="text-sm mt-1">Estamos verificando tu pago con Mercado Pago. Tu plan se actualizará en segundos.</p>
                    <p v-if="pagoEnVuelo" class="text-xs font-bold mt-2 opacity-70">
                        Pago pendiente de confirmación · te quedan {{ pagoEnVuelo.expira_en_minutos }} min
                    </p>
                </div>
                <div v-if="estadoPago === 'aprobado'" class="mx-4 sm:mx-0 mb-8 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl p-6 text-center">
                    <p class="font-bold text-lg">🎉 ¡Felicitaciones! Se aprobó tu renovación del Plan {{ planRenovado?.nombre || 'Solicitado' }}.</p>
                    <p class="text-sm mt-1">Recargando...</p>
                </div>
                <div v-if="estadoPago === 'error' || estadoPago === 'error_fatal'" class="mx-4 sm:mx-0 mb-8 bg-rose-50 border border-rose-200 text-rose-800 rounded-2xl p-6 text-center">
                    <p class="font-bold text-lg">
                        {{ estadoPago === 'error_fatal' ? 'No pudimos validar este pago' : 'El pago fue rechazado.' }}
                    </p>
                    <p v-if="mensajeError" class="text-sm mt-1">{{ mensajeError }}</p>
                    <p class="text-sm mt-1">No se cobró nada y tu plan sigue igual.</p>
                    <div class="mt-4 flex flex-col sm:flex-row gap-3 justify-center">
                        <button
                            v-if="estadoPago === 'error'"
                            class="py-3 px-6 rounded-2xl font-black uppercase tracking-widest text-xs bg-rose-600 text-white hover:opacity-90 active:scale-[0.98] transition-all disabled:opacity-50"
                            :disabled="cargandoAccion"
                            @click="reintentarPago"
                        >
                            Reintentar pago
                        </button>
                        <button
                            class="py-3 px-6 rounded-2xl font-black uppercase tracking-widest text-xs bg-white text-rose-700 border-2 border-rose-200 hover:bg-rose-50 active:scale-[0.98] transition-all"
                            @click="recargarPagina"
                        >
                            Volver a los planes
                        </button>
                    </div>
                </div>
                <div v-if="estadoPago === 'verificar'" class="mx-4 sm:mx-0 mb-8 bg-amber-50 border border-amber-200 text-amber-800 rounded-2xl p-6 text-center">
                    <p class="font-bold text-lg">No pudimos verificar tu pago</p>
                    <p v-if="mensajeError" class="text-sm mt-1">{{ mensajeError }}</p>
                    <p class="text-sm mt-1">
                        Si ya pagaste, tu plan se actualiza solo en cuanto Mercado Pago nos avise.
                        Podés consultar el estado ahora o reintentar el pago.
                    </p>
                    <div class="mt-4 flex flex-col sm:flex-row gap-3 justify-center">
                        <button
                            class="py-3 px-6 rounded-2xl font-black uppercase tracking-widest text-xs bg-amber-600 text-white hover:opacity-90 active:scale-[0.98] transition-all disabled:opacity-50"
                            :disabled="cargandoAccion"
                            @click="verificarEstadoPago"
                        >
                            Consultar estado
                        </button>
                        <button
                            class="py-3 px-6 rounded-2xl font-black uppercase tracking-widest text-xs bg-white text-amber-700 border-2 border-amber-200 hover:bg-amber-50 active:scale-[0.98] transition-all disabled:opacity-50"
                            :disabled="cargandoAccion"
                            @click="reintentarPago"
                        >
                            Reintentar pago
                        </button>
                    </div>
                </div>
                <div v-if="estadoPago === 'pendiente'" class="mx-4 sm:mx-0 mb-8 bg-amber-50 border border-amber-200 text-amber-800 rounded-2xl p-6 text-center">
                    <p class="font-bold text-lg">Estamos esperando la confirmación del pago.</p>
                </div>
                <div v-if="estadoPago === 'timeout'" class="mx-4 sm:mx-0 mb-8 bg-amber-50 border border-amber-200 text-amber-800 rounded-2xl p-6 text-center">
                    <p class="font-bold text-lg">No pudimos confirmar tu pago todavía</p>
                    <p class="text-sm mt-1">
                        Mercado Pago todavía no nos-notificó. Si ya pagaste, no hace falta que hagas nada: la suscripción se actualiza sola.
                        Si no llegaste a pagar, podés reintentar o liberar el pago pendiente.
                    </p>
                    <div class="mt-4 flex flex-col sm:flex-row gap-3 justify-center">
                        <button
                            class="py-3 px-6 rounded-2xl font-black uppercase tracking-widest text-xs bg-amber-600 text-white hover:opacity-90 active:scale-[0.98] transition-all disabled:opacity-50"
                            :disabled="cargandoAccion"
                            @click="reintentarPago"
                        >
                            Reintentar pago
                        </button>
                        <button
                            class="py-3 px-6 rounded-2xl font-black uppercase tracking-widest text-xs bg-white text-amber-700 border-2 border-amber-200 hover:bg-amber-50 active:scale-[0.98] transition-all disabled:opacity-50"
                            :disabled="cargandoAccion"
                            @click="cancelarPago"
                        >
                            Liberar pago pendiente
                        </button>
                    </div>
                </div>
                <div v-if="estadoPago === 'liberado'" class="mx-4 sm:mx-0 mb-8 bg-slate-50 border border-slate-200 text-slate-700 rounded-2xl p-6 text-center">
                    <p class="font-bold text-lg">El pago pendiente fue cancelado</p>
                    <p class="text-sm mt-1">
                        No se cobró nada y tu plan sigue igual. Podés volver a elegir un plan cuando quieras.
                    </p>
                    <button
                        class="mt-4 py-3 px-6 rounded-2xl font-black uppercase tracking-widest text-xs bg-slate-800 text-white hover:opacity-90 active:scale-[0.98] transition-all disabled:opacity-50"
                        :disabled="cargandoAccion"
                        @click="recargarPagina"
                    >
                        Volver a los planes
                    </button>
                </div>
                <div v-if="estadoPago === 'en_curso'" class="mx-4 sm:mx-0 mb-8 bg-sky-50 border border-sky-200 text-sky-800 rounded-2xl p-6 text-center">
                    <p class="font-bold text-lg">Ya hay un pago en proceso</p>
                    <p class="text-sm mt-1">Te enviamos a Mercado Pago hace instantes. Esperá la confirmación antes de generar otro pago, así no se duplica el cobro.</p>
                    <div class="mt-4 flex flex-col sm:flex-row gap-3 justify-center">
                        <button
                            class="py-3 px-6 rounded-2xl font-black uppercase tracking-widest text-xs bg-sky-700 text-white hover:opacity-90 active:scale-[0.98] transition-all disabled:opacity-50"
                            :disabled="cargandoAccion"
                            @click="reintentarPago"
                        >
                            Reintentar pago
                        </button>
                        <button
                            class="py-3 px-6 rounded-2xl font-black uppercase tracking-widest text-xs bg-white text-sky-700 border-2 border-sky-200 hover:bg-sky-50 active:scale-[0.98] transition-all disabled:opacity-50"
                            :disabled="cargandoAccion"
                            @click="cancelarPago"
                        >
                            Liberar pago pendiente
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 px-4 sm:px-0">
                    <div
                        v-for="plan in planes"
                        :key="plan.id"
                        class="relative bg-white rounded-3xl p-8 shadow-xl transition-all duration-300 border-2 flex flex-col justify-between"
                        :class="esPlanActual(plan.id) ? 'border-[#00adef] shadow-[#00adef]/10 scale-105 z-10' : 'border-transparent hover:border-slate-200'"
                    >
                        <div v-if="esPlanActual(plan.id)" class="absolute -top-4 left-1/2 -translate-x-1/2 bg-[#00adef] text-white text-[10px] font-black uppercase px-4 py-1.5 rounded-full tracking-widest shadow-lg">
                            Tu Plan Actual
                        </div>
                        <div v-if="plan.destacado && !esPlanActual(plan.id)" class="absolute -top-4 left-1/2 -translate-x-1/2 bg-amber-500 text-white text-[10px] font-black uppercase px-4 py-1.5 rounded-full tracking-widest shadow-lg">
                            Más Elegido
                        </div>

                        <div>
                            <div class="text-center mb-8">
                                <h3 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-2">{{ plan.nombre }}</h3>
                                <div v-if="plan.descripcion" class="text-xs text-slate-500 mb-4">{{ plan.descripcion }}</div>
                                <div class="flex items-baseline justify-center gap-1">
                                    <span class="text-4xl font-black text-slate-800">{{ formatearDinero(plan.precio_mensual) }}</span>
                                    <span class="text-slate-400 text-xs font-bold uppercase">/ mes</span>
                                </div>
                            </div>

                            <!-- Estado de la suscripción: tarjeta destacada, sólo en
                                 el plan contratado. Reemplaza los tres renglones de
                                 texto suelto que no se distinguían del resto. -->
                            <div
                                v-if="esPlanActual(plan.id) && (vencimientoActual || estaSuspendido)"
                                class="mb-8 rounded-2xl border overflow-hidden"
                                :class="[
                                    paletaEstado.caja,
                                    paletaEstado.critico && 'ring-2 ring-rose-300/70'
                                ]"
                            >
                                <div
                                    class="flex items-center justify-between gap-2 px-4 py-2.5 border-b border-black/5"
                                    :class="paletaEstado.cabecera"
                                >
                                    <span class="text-[10px] font-black uppercase tracking-widest">
                                        Tu suscripción
                                    </span>
                                    <span
                                        class="text-[10px] font-black uppercase tracking-widest px-2.5 py-1 rounded-full"
                                        :class="paletaEstado.badge"
                                    >
                                        {{ etiquetaEstado }}
                                    </span>
                                </div>

                                <dl class="grid grid-cols-2 divide-x divide-black/5">
                                    <div class="px-4 py-3">
                                        <dt class="text-[9px] font-black uppercase tracking-widest text-slate-400">
                                            Fecha de vencimiento
                                        </dt>
                                        <dd class="text-sm font-black mt-0.5 font-mono" :class="paletaEstado.metricas">
                                            {{ vencimientoActual || '—' }}
                                        </dd>
                                    </div>
                                    <div class="px-4 py-3">
                                        <dt class="text-[9px] font-black uppercase tracking-widest text-slate-400">
                                            Tiempo restante
                                        </dt>
                                        <dd class="text-sm font-black mt-0.5" :class="paletaEstado.metricas">
                                            {{ textoPlazo || 'Sin fecha' }}
                                        </dd>
                                    </div>
                                </dl>

                                <div
                                    class="flex items-center gap-2 px-4 py-2.5 border-t border-black/5 text-[11px] font-bold"
                                    :class="paletaEstado.pie"
                                >
                                    <span aria-hidden="true">{{ notaRenovacion.icono }}</span>
                                    <span>{{ notaRenovacion.texto }}</span>
                                </div>
                            </div>

                            <ul class="space-y-4 mb-10">
                                <li class="flex items-center gap-3 text-sm font-bold text-slate-600">
                                    <span class="text-[#8cc63f] text-lg font-black">✓</span>
                                    {{ plan.sucursales_limit >= 10 ? 'Sucursales ilimitadas' : 'Hasta ' + plan.sucursales_limit + ' sucursal' + (plan.sucursales_limit !== 1 ? 'es' : '') }}
                                </li>
                                <li class="flex items-center gap-3 text-sm font-bold text-slate-600">
                                    <span class="text-[#8cc63f] text-lg font-black">✓</span>
                                    {{ plan.usuarios_limit >= 10 ? 'Usuarios ilimitados' : 'Hasta ' + plan.usuarios_limit + ' usuario' + (plan.usuarios_limit !== 1 ? 's' : '') }}
                                </li>
                                <li v-if="plan.modulos?.pos" class="flex items-center gap-3 text-sm font-bold text-slate-600">
                                    <span class="text-[#8cc63f] text-lg font-black">✓</span>
                                    Punto de Venta (POS)
                                </li>
                                <li v-if="plan.modulos?.lotes" class="flex items-center gap-3 text-sm font-bold text-slate-600">
                                    <span class="text-[#8cc63f] text-lg font-black">✓</span>
                                    Stock Avanzado con Lotes
                                </li>
                                <li v-if="plan.modulos?.fiados" class="flex items-center gap-3 text-sm font-bold text-slate-600">
                                    <span class="text-[#8cc63f] text-lg font-black">✓</span>
                                    Cuentas Corrientes (Fiados)
                                </li>
                                <li v-if="plan.modulos?.proveedores" class="flex items-center gap-3 text-sm font-bold text-slate-600">
                                    <span class="text-[#8cc63f] text-lg font-black">✓</span>
                                    Gestión de Proveedores
                                </li>
                                <li v-if="plan.modulos?.auditoria" class="flex items-center gap-3 text-sm font-bold text-slate-600">
                                    <span class="text-[#8cc63f] text-lg font-black">✓</span>
                                    Auditoría Completa
                                </li>
                                <li v-if="plan.modulos?.transferencias" class="flex items-center gap-3 text-sm font-bold text-slate-600">
                                    <span class="text-[#8cc63f] text-lg font-black">✓</span>
                                    Optimización de Stock
                                </li>
                            </ul>
                        </div>

                        <!-- Cambio de plan: cualquier plan distinto del contratado -->
                        <button
                            v-if="!esPlanActual(plan.id)"
                            :disabled="planCargando === plan.id || hayPagoEnCurso"
                            class="w-full py-4 rounded-2xl font-black uppercase tracking-widest text-xs transition-all shadow-lg hover:opacity-90 active:scale-[0.98] disabled:opacity-50 disabled:cursor-wait flex items-center justify-center gap-2"
                            :class="plan.destacado ? 'bg-[#00adef] text-white' : 'bg-slate-800 text-white'"
                            @click="pagarPlan(plan.id)"
                        >
                            <span v-if="planCargando === plan.id">Generando Link... ⏳</span>
                            <span v-else>Elegir {{ plan.nombre }} ⚡</span>
                        </button>

                        <!-- Renovación urgente del plan actual: por vencer, vencida o suspendida -->
                        <button
                            v-else-if="renovacionUrgente"
                            :disabled="planCargando === plan.id || hayPagoEnCurso"
                            class="w-full py-4 rounded-2xl font-black uppercase tracking-widest text-xs transition-all shadow-lg hover:opacity-90 active:scale-[0.98] disabled:opacity-50 disabled:cursor-wait flex items-center justify-center gap-2"
                            :class="estadoActual === 'vencida' || estaSuspendido ? 'bg-rose-600 text-white' : 'bg-amber-500 text-white'"
                            @click="pagarPlan(plan.id)"
                        >
                            <span v-if="planCargando === plan.id">Generando Link... ⏳</span>
                            <span v-else>Renovar {{ plan.nombre }} 🔄</span>
                        </button>

                        <!-- Plan vigente. Dentro de la ventana de 30 días se puede
                             renovar anticipadamente; antes de abrirla se explica por
                             qué no, en lugar de dejar un botón inerte sin motivo. -->
                        <div v-else class="w-full">
                            <div
                                class="w-full py-4 rounded-2xl font-black uppercase tracking-widest text-xs text-center border-2 select-none"
                                :class="puedeRenovar
                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                    : 'border-slate-100 text-slate-400 bg-slate-50'"
                            >
                                Este es tu plan actual
                            </div>

                            <button
                                v-if="puedeRenovar"
                                :disabled="planCargando === plan.id || hayPagoEnCurso"
                                class="w-full mt-3 py-2.5 rounded-2xl font-black uppercase tracking-widest text-[10px] text-[#00adef] border-2 border-[#00adef]/30 hover:bg-[#00adef]/5 active:scale-[0.98] transition-all disabled:opacity-40 disabled:cursor-wait"
                                @click="pagarPlan(plan.id)"
                            >
                                <span v-if="planCargando === plan.id">Generando Link... ⏳</span>
                                <span v-else>Renovar ahora</span>
                            </button>

                            <!-- Fuera de la ventana: información accionable en lugar
                                 de un botón deshabilitado sin explicación. -->
                            <div
                                v-else-if="diasParaRenovar !== null"
                                class="w-full mt-3 rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50 px-3 py-3 text-center select-none"
                            >
                                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">
                                    Renovación anticipada
                                </p>
                                <p class="text-xs font-black text-slate-600 mt-1">
                                    Disponible en {{ diasParaRenovar }} {{ diasParaRenovar === 1 ? 'día' : 'días' }}
                                </p>
                                <p class="text-[10px] font-bold text-slate-400 mt-0.5">
                                    Podés renovar dentro de los {{ ventanaRenovacion }} días previos al vencimiento
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-16 mx-4 sm:mx-0 bg-slate-900 rounded-3xl p-8 text-white flex flex-col md:flex-row items-center justify-between gap-6 shadow-2xl overflow-hidden relative">
                    <div class="absolute right-0 top-0 opacity-5 text-[120px] font-black italic select-none leading-none pointer-events-none">V-AR</div>
                    <div class="relative z-10">
                        <h4 class="text-xl font-black uppercase italic tracking-tight">¿Necesitás un plan a medida?</h4>
                        <p class="text-slate-400 text-sm font-bold mt-1">Si tenés más de 10 sucursales, contactanos para un presupuesto personalizado.</p>
                    </div>
                    <button class="relative z-10 bg-white text-slate-900 px-8 py-3.5 rounded-xl font-black uppercase text-xs tracking-widest hover:bg-[#00adef] hover:text-white transition-all shadow-lg">
                        Hablar con Soporte
                    </button>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
