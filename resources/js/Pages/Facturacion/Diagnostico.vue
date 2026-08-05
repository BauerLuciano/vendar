<script setup>
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Swal from 'sweetalert2';

const props = defineProps({
    diagnostico: {
        type: Object,
        default: () => ({ items: [], indicador: 'incompleto', estado_modulo: 'sin_datos' }),
    },
    pendientes: {
        type: Array,
        default: () => [],
    },
    resultadoConexion: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const errors = computed(() => page.props.errors || {});

const indicadorConfig = {
    listo: { emoji: '🟢', label: 'Listo para facturar', clase: 'bg-emerald-50 border-emerald-300 text-emerald-800' },
    incompleto: { emoji: '🟡', label: 'Incompleto', clase: 'bg-amber-50 border-amber-300 text-amber-800' },
    no_posible: { emoji: '🔴', label: 'No posible', clase: 'bg-red-50 border-red-300 text-red-800' },
};

const indicador = computed(() => indicadorConfig[props.diagnostico.indicador] || indicadorConfig.incompleto);

const estadoItem = (ok) => ({
    nulo: ok === null,
    ok: ok === true,
});

const probando = ref(false);

const probarConexion = () => {
    probando.value = true;
    router.post(route('configuracion.fiscal.diagnostico.probar-conexion'), {}, {
        preserveScroll: true,
        onFinish: () => {
            probando.value = false;
            router.reload({ only: ['diagnostico', 'resultadoConexion'] });
        },
    });
};

const reintentandoId = ref(null);

const reintentar = (pendiente) => {
    Swal.fire({
        title: `Reintentar ${pendiente.tipo_label.toLowerCase()}`,
        text: `Se reintenta la operación completa de la venta #${pendiente.venta_id} (stock, caja y Nota de Crédito). ¿Continuar?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, reintentar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc2626',
    }).then((result) => {
        if (!result.isConfirmed) return;

        reintentandoId.value = pendiente.id;
        router.post(route('configuracion.fiscal.diagnostico.reintentar', { pendiente: pendiente.id }), {}, {
            preserveScroll: true,
            onFinish: () => {
                reintentandoId.value = null;
                router.reload({ only: ['pendientes'] });
            },
            onSuccess: () => {
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Operación reintentada', showConfirmButton: false, timer: 2500 });
            },
        });
    });
};

const formatMonto = (monto) => new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(monto);
</script>

<template>
    <Head title="Diagnóstico fiscal" />

    <AuthenticatedLayout>
        <div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-black text-slate-800">Panel de Diagnóstico Fiscal</h1>
                    <p class="text-slate-500 font-medium text-sm">Estado de la facturación electrónica del comercio</p>
                </div>

                <a :href="route('configuracion.fiscal.wizard')"
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-900 text-white text-sm font-black transition-colors">
                    Abrir asistente de configuración
                </a>
            </div>

            <div v-if="errors.conexion || errors.reintento" class="mb-4 px-4 py-3 rounded-xl bg-red-50 border border-red-200 text-sm text-red-700 font-semibold">
                {{ errors.conexion || errors.reintento }}
            </div>

            <!-- INDICADOR GLOBAL -->
            <div class="mb-6 rounded-2xl border px-6 py-5 flex items-center gap-4" :class="indicador.clase">
                <span class="text-4xl">{{ indicador.emoji }}</span>
                <div>
                    <p class="font-black uppercase tracking-widest text-sm">{{ indicador.label }}</p>
                    <p class="font-medium text-sm opacity-80">Estado del módulo: {{ props.diagnostico.estado_modulo }}</p>
                </div>
            </div>

            <!-- CHECKLIST -->
            <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden mb-6">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h2 class="font-black text-slate-800">Checklist del módulo fiscal</h2>
                </div>
                <div class="divide-y divide-slate-100">
                    <div v-for="item in props.diagnostico.items" :key="item.clave" class="px-6 py-4 flex items-start gap-4">
                        <span class="mt-0.5 w-6 h-6 rounded-full flex items-center justify-center text-xs font-black shrink-0"
                              :class="estadoItem(item.ok).nulo ? 'bg-slate-200 text-slate-500'
                                    : estadoItem(item.ok).ok ? 'bg-emerald-500 text-white' : 'bg-red-500 text-white'">
                            {{ estadoItem(item.ok).nulo ? '—' : estadoItem(item.ok).ok ? '✓' : '✗' }}
                        </span>
                        <div class="flex-1">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="font-black text-slate-800 text-sm">{{ item.etiqueta }}</p>
                                <span class="text-xs text-slate-500 font-semibold">{{ item.accion }}</span>
                            </div>
                            <p class="text-slate-500 text-sm font-medium">{{ item.detalle }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CONECTIVIDAD -->
            <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden mb-6">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                    <h2 class="font-black text-slate-800">Conectividad con ARCA</h2>
                    <button @click="probarConexion" :disabled="probando"
                            class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-black disabled:opacity-50 transition-colors">
                        {{ probando ? 'Probando…' : 'Probar conexión con ARCA' }}
                    </button>
                </div>
                <div class="p-6">
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
                        Ejecutá la suite para verificar certificado, WSAA, WSFE y padrón sin emitir comprobantes de prueba.
                    </p>
                </div>
            </div>

            <!-- PENDIENTES DE NC -->
            <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h2 class="font-black text-slate-800">Pendientes de Notas de Crédito</h2>
                    <p class="text-xs text-slate-500 font-medium mt-0.5">
                        Operaciones de anulación/devolución cuya NC falló. El reintento ejecuta la operación completa.
                    </p>
                </div>

                <div v-if="pendientes.length === 0" class="px-6 py-8 text-center text-sm text-slate-500 font-medium">
                    No hay pendientes de Notas de Crédito.
                </div>

                <div v-else class="divide-y divide-slate-100">
                    <div v-for="p in pendientes" :key="p.id" class="px-6 py-4 flex flex-wrap items-center gap-4">
                        <div class="flex-1 min-w-[220px]">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-black uppercase tracking-widest px-2 py-0.5 rounded-lg bg-rose-100 text-rose-700">{{ p.tipo_label }}</span>
                                <p class="font-black text-slate-800 text-sm">Venta #{{ p.venta_id }}</p>
                            </div>
                            <p class="text-slate-500 text-sm font-medium mt-1">
                                {{ p.consumidor }} · {{ formatMonto(p.monto) }} · Intentos: {{ p.intentos }}
                            </p>
                            <p v-if="p.motivo" class="text-slate-500 text-sm">Motivo: {{ p.motivo }}</p>
                            <p class="text-rose-600 text-xs font-semibold mt-1">{{ p.motivo_fallo }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-slate-400 font-semibold">{{ p.created_at }}</p>
                            <button @click="reintentar(p)" :disabled="reintentandoId === p.id"
                                    class="mt-1 px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-sm font-black disabled:opacity-50 transition-colors">
                                {{ reintentandoId === p.id ? 'Reintentando…' : 'Reintentar operación' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
