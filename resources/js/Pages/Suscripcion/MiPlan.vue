<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    comercio: Object,
    planes: Array,
});

const planCargando = ref(null);
const estadoPago = ref(null);
const polling = ref(null);
const planRenovado = ref(null);

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

const requiereRenovacion = computed(() => {
    if (!props.comercio) {
        return false;
    }
    if (props.comercio.status === 'suspendido') {
        return true;
    }
    if (props.comercio.vencimiento_pago) {
        return new Date(props.comercio.vencimiento_pago) < new Date();
    }
    return false;
});

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
                ts: Date.now(),
            }));
            window.location.href = response.data.init_point;
        }
    } catch (error) {
        const msg = error.response?.data?.error || 'Error al conectar con Mercado Pago.';
        alert(msg);
    } finally {
        planCargando.value = null;
    }
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
        } else {
            // El backend todavía no lo confirma; que el webhook lo procese.
            iniciarPolling(planId);
        }
    } catch {
        // Si falla, el webhook lo procesará. Empezamos polling.
        iniciarPolling(planId);
    }
};

const iniciarPolling = (planId) => {
    if (polling.value) {
        return;
    }
    let segundos = 0;
    polling.value = setInterval(async () => {
        segundos += 3;
        try {
            const res = await axios.get(route('suscripcion.plan-actual'));
            const renovacionAplicada = res.data.plan_id === planId && res.data.pending_plan_id === null;
            if (renovacionAplicada) {
                clearInterval(polling.value);
                polling.value = null;
                finalizarAprobado();
                return;
            }
        } catch {
            // silent
        }
        if (segundos >= 90) {
            clearInterval(polling.value);
            polling.value = null;
            estadoPago.value = 'timeout';
        }
    }, 3000);
};

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    const pago = params.get('pago');
    const urlPlanId = params.get('plan_id') ? Number(params.get('plan_id')) : null;
    const paymentId = params.get('payment_id');

    const pendiente = leerRenovacionPendiente();
    const planId = urlPlanId
        ?? pendiente?.plan_id
        ?? props.planes.find(p => p.slug === props.comercio?.plan_pendiente)?.id
        ?? null;

    if (pago === 'error') {
        localStorage.removeItem(RENOVACION_KEY);
        estadoPago.value = 'error';
        return;
    }

    if (planId && (pago === 'exito' || pendiente)) {
        planRenovado.value = props.planes.find(p => p.id === planId) || null;
        estadoPago.value = 'procesando';

        if (pago === 'exito' && paymentId) {
            confirmarUpgrade(planId, paymentId);
        } else {
            iniciarPolling(planId);
        }
        return;
    }

    if (pendiente) {
        estadoPago.value = 'timeout';
        return;
    }

    if (pago === 'pendiente') {
        estadoPago.value = 'pendiente';
    }
});

onUnmounted(() => {
    if (polling.value) {
        clearInterval(polling.value);
    }
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
                </div>
                <div v-if="estadoPago === 'aprobado'" class="mx-4 sm:mx-0 mb-8 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl p-6 text-center">
                    <p class="font-bold text-lg">🎉 ¡Felicitaciones! Se aprobó tu renovación del Plan {{ planRenovado?.nombre || 'Solicitado' }}.</p>
                    <p class="text-sm mt-1">Recargando...</p>
                </div>
                <div v-if="estadoPago === 'error'" class="mx-4 sm:mx-0 mb-8 bg-rose-50 border border-rose-200 text-rose-800 rounded-2xl p-6 text-center">
                    <p class="font-bold text-lg">El pago fue rechazado.</p>
                    <p class="text-sm mt-1">Intentá nuevamente o contactanos si el problema persiste.</p>
                </div>
                <div v-if="estadoPago === 'pendiente'" class="mx-4 sm:mx-0 mb-8 bg-amber-50 border border-amber-200 text-amber-800 rounded-2xl p-6 text-center">
                    <p class="font-bold text-lg">Estamos esperando la confirmación del pago.</p>
                </div>
                <div v-if="estadoPago === 'timeout'" class="mx-4 sm:mx-0 mb-8 bg-amber-50 border border-amber-200 text-amber-800 rounded-2xl p-6 text-center">
                    <p class="font-bold text-lg">Estamos procesando tu pago</p>
                    <p class="text-sm mt-1">Mercado Pago nos notificará cuando confirme tu renovación. Si no ves los cambios en unos minutos, contactanos.</p>
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

                        <button
                            v-if="!esPlanActual(plan.id)"
                            :disabled="planCargando === plan.id"
                            class="w-full py-4 rounded-2xl font-black uppercase tracking-widest text-xs transition-all shadow-lg hover:opacity-90 active:scale-[0.98] disabled:opacity-50 disabled:cursor-wait flex items-center justify-center gap-2"
                            :class="plan.destacado ? 'bg-[#00adef] text-white' : 'bg-slate-800 text-white'"
                            @click="pagarPlan(plan.id)"
                        >
                            <span v-if="planCargando === plan.id">Generando Link... ⏳</span>
                            <span v-else>Elegir {{ plan.nombre }} ⚡</span>
                        </button>

                        <button
                            v-else-if="requiereRenovacion"
                            :disabled="planCargando === plan.id"
                            class="w-full py-4 rounded-2xl font-black uppercase tracking-widest text-xs transition-all shadow-lg hover:opacity-90 active:scale-[0.98] disabled:opacity-50 disabled:cursor-wait flex items-center justify-center gap-2 bg-amber-500 text-white"
                            @click="pagarPlan(plan.id)"
                        >
                            <span v-if="planCargando === plan.id">Generando Link... ⏳</span>
                            <span v-else>Renovar {{ plan.nombre }} 🔄</span>
                        </button>

                        <div
                            v-else
                            class="w-full py-4 rounded-2xl font-black uppercase tracking-widest text-xs text-center border-2 border-slate-100 text-slate-400 bg-slate-50 select-none"
                        >
                            Este es tu plan actual
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
