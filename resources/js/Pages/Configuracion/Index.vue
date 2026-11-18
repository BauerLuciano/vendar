<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    configuraciones: Object,
    comercio: Object,
    paymentMethodConfigs: Array,
    metodoPagoOptions: Array,
    storeConfig: Object,
});

const tabActiva = ref('general');
const tabsDelFormPrincipal = ['general', 'pos', 'sistema', 'tienda'];
const camposPorTab = {
    general: ['nombre_empresa', 'cuit', 'telefono', 'direccion', 'logo'],
    pos: ['formato_impresion', 'ticket_mensaje_pie', 'permitir_stock_negativo', 'ticket_digital_auto_email'],
    sistema: ['limite_fiado_defecto', 'moneda_defecto', 'costo_delivery_defecto', 'mora_dias_gracia', 'mora_tasa_interes'],
    tienda: [
        'envio_precio_base',
        'envio_precio_km',
        'envio_radio_km',
        'transferencia_cbu',
        'transferencia_alias',
        'transferencia_titular',
        'acepta_efectivo',
        'mp_enabled',
        'mp_access_token',
        'viumi_enabled',
        'viumi_client_id',
        'viumi_client_secret',
        'viumi_environment',
    ],
};
const logoPreview = ref(props.comercio.url_logo || (props.configuraciones.logo_empresa ? '/storage/' + props.configuraciones.logo_empresa : null));

const form = useForm({
    // --- Configuraciones Globales ---
    nombre_empresa: props.configuraciones.nombre_empresa || '',
    cuit: props.configuraciones.cuit || '',
    telefono: props.configuraciones.telefono || '',
    direccion: props.configuraciones.direccion || '',
    ticket_mensaje_pie: props.configuraciones.ticket_mensaje_pie || '',
    formato_impresion: props.configuraciones.formato_impresion || '80mm',
    ticket_digital_auto_email: props.configuraciones.ticket_digital_auto_email === '1' || props.configuraciones.ticket_digital_auto_email === true,
    permitir_stock_negativo: props.configuraciones.permitir_stock_negativo === '1' || props.configuraciones.permitir_stock_negativo === true,
    limite_fiado_defecto: props.configuraciones.limite_fiado_defecto || 0,
    moneda_defecto: props.configuraciones.moneda_defecto || 'ARS',
    costo_delivery_defecto: props.configuraciones.costo_delivery_defecto || 0,
    mora_dias_gracia: props.configuraciones.mora_dias_gracia || 15,
    mora_tasa_interes: props.configuraciones.mora_tasa_interes || 5,
    logo_empresa: null,
    logo: null,

    // --- 🔥 NUEVO: Configuraciones del Comercio (Para el Catálogo) ---
    envio_precio_base: props.comercio.envio_precio_base || 0,
    envio_precio_km: props.comercio.envio_precio_km || 0,
    envio_radio_km: props.comercio.envio_radio_km || 5,
    transferencia_cbu: props.comercio.transferencia_cbu || '',
    transferencia_alias: props.comercio.transferencia_alias || '',
    transferencia_titular: props.comercio.transferencia_titular || '',
    acepta_efectivo: props.comercio.acepta_efectivo === 1 || props.comercio.acepta_efectivo === true,
    mp_enabled: props.comercio.payment_gateways?.find(g => g.provider === 'mercadopago')?.enabled ?? !!props.comercio.tiene_mp,
    mp_access_token: '',

    // --- viüMi ---
    viumi_enabled: props.comercio.payment_gateways?.find(g => g.provider === 'viumi')?.enabled ?? false,
    viumi_client_id: props.comercio.payment_gateways?.find(g => g.provider === 'viumi')?.configuration?.client_id ?? '',
    viumi_client_secret: '',
    viumi_environment: props.comercio.payment_gateways?.find(g => g.provider === 'viumi')?.configuration?.environment ?? 'sandbox',
});

const handleLogoUpload = (event) => {
    const file = event.target.files[0];
    if (file) {
        form.logo_empresa = file;
        form.logo = file;
        logoPreview.value = URL.createObjectURL(file);
    }
};

const pmcForm = useForm({
    metodo_pago: 'TRANSFERENCIA',
    provider: '',
    display_data: {
        alias: '',
        cvu: '',
        cbu: '',
        banco: '',
        titular: '',
    },
    enabled: true,
});

const editingPmcId = ref(null);

const etiquetasCamposPmc = {
    metodo_pago: 'Método de pago',
    provider: 'Pasarela',
    display_data: 'Datos para mostrar',
    'display_data.alias': 'Alias',
    'display_data.cvu': 'CVU',
    'display_data.cbu': 'CBU',
    'display_data.banco': 'Banco',
    'display_data.titular': 'Titular',
    enabled: 'Habilitado',
    error: 'Error del servidor',
};

const camposPmcConErrorInline = [
    'metodo_pago',
    'provider',
    'display_data',
    'display_data.alias',
    'display_data.cbu',
    'display_data.cvu',
    'display_data.banco',
    'display_data.titular',
    'enabled',
];

const MENSAJE_GRUPO_PMC = 'Completá al menos uno de Alias, CBU o CVU.';
const MENSAJE_TITULAR_PMC = 'Ingresá el nombre del titular.';

const mensajeErrorPmc = (campo) => {
    const error = pmcForm.errors[campo];
    return Array.isArray(error) ? error.join(' ') : (error || '');
};

const valorCompletadoPmc = (valor) => {
    if (valor === null || valor === undefined) return false;
    if (typeof valor === 'string') return valor.trim().length > 0;
    if (Array.isArray(valor)) return valor.length > 0;
    return true;
};

const idCampoPmc = (campo) => `pmc-${campo.replace(/[._]/g, '-')}`;
const idErrorPmc = (campo) => `${idCampoPmc(campo)}-error`;
const idCampoEnfocablePmc = (campo) => idCampoPmc(campo);

const erroresPmc = computed(() => Object.entries(pmcForm.errors).map(([campo, error]) => ({
    campo,
    etiqueta: etiquetasCamposPmc[campo] || 'Formulario',
    mensaje: Array.isArray(error) ? error.join(' ') : error,
})));

const erroresGeneralesPmc = computed(() => erroresPmc.value.filter(
    ({ campo }) => !camposPmcConErrorInline.includes(campo)
));

const esTransferenciaPmc = computed(
    () => pmcForm.metodo_pago === 'TRANSFERENCIA'
);

const faltaTitularPmc = computed(() =>
    esTransferenciaPmc.value &&
    mensajeErrorPmc('display_data.titular') === MENSAJE_TITULAR_PMC
);

const faltaCuentaPmc = computed(() =>
    esTransferenciaPmc.value &&
    mensajeErrorPmc('display_data') === MENSAJE_GRUPO_PMC
);

const hayErrorRequisitoTransferenciaPmc = computed(() =>
    faltaTitularPmc.value || faltaCuentaPmc.value
);

const mensajeResumenPmc = computed(() => {
    if (faltaTitularPmc.value && faltaCuentaPmc.value) {
        return 'Completá el nombre del titular y al menos uno de Alias, CBU o CVU.';
    }

    if (faltaTitularPmc.value) {
        return 'Faltan los datos del titular de la transferencia.';
    }

    if (faltaCuentaPmc.value) {
        return 'Faltan los datos de la cuenta para la transferencia.';
    }

    return 'Revisá los datos marcados y volvé a intentar.';
});

const mostrarResumenErroresPmc = computed(() => {
    if (!erroresPmc.value.length) return false;
    if (hayErrorRequisitoTransferenciaPmc.value) return true;

    return erroresPmc.value.length > 1 || erroresGeneralesPmc.value.length > 0;
});

const descripcionGrupoPmc = computed(() => {
    if (!esTransferenciaPmc.value) return undefined;

    return mensajeErrorPmc('display_data')
        ? `pmc-display-data-grupo-ayuda ${idErrorPmc('display_data')}`
        : 'pmc-display-data-grupo-ayuda';
});

function limpiarErrorPmc(campo) {
    const campos = campo.startsWith('display_data.') ? [campo, 'display_data'] : [campo];

    if (campos.some((nombre) => pmcForm.errors[nombre])) {
        pmcForm.clearErrors(...campos);
    }
}

function validarTransferenciaPmc() {
    if (!esTransferenciaPmc.value) return true;

    pmcForm.clearErrors('display_data', 'display_data.titular');

    let valido = true;
    const data = pmcForm.display_data || {};

    if (!valorCompletadoPmc(data.titular)) {
        pmcForm.setError('display_data.titular', MENSAJE_TITULAR_PMC);
        valido = false;
    }

    if (![data.alias, data.cbu, data.cvu].some(valorCompletadoPmc)) {
        pmcForm.setError('display_data', MENSAJE_GRUPO_PMC);
        valido = false;
    }

    return valido;
}

function enfocarPrimerErrorPmc() {
    const primerCampoConError = camposPmcConErrorInline.find(
        (campo) => campo !== 'display_data' && Boolean(mensajeErrorPmc(campo))
    );

    if (primerCampoConError) {
        document.getElementById(idCampoEnfocablePmc(primerCampoConError))?.focus();
        return;
    }

    document.getElementById('pmc-error-summary')?.focus();
}

function editPmc(pmc) {
    pmcForm.clearErrors();
    editingPmcId.value = pmc.id;
    pmcForm.metodo_pago = pmc.metodo_pago;
    pmcForm.provider = pmc.provider || '';
    pmcForm.display_data = pmc.metodo_pago === 'TRANSFERENCIA'
        ? {
            ...(pmc.display_data || {}),
            alias: pmc.display_data?.alias ?? '',
            cbu: pmc.display_data?.cbu ?? '',
            cvu: pmc.display_data?.cvu ?? '',
            banco: pmc.display_data?.banco ?? '',
            titular: pmc.display_data?.titular ?? '',
        }
        : { ...(pmc.display_data || {}) };
    pmcForm.enabled = pmc.enabled;
}

function resetPmcForm() {
    editingPmcId.value = null;
    pmcForm.reset();
    pmcForm.clearErrors();
    pmcForm.display_data = { alias: '', cvu: '', cbu: '', banco: '', titular: '' };
}

function cambiarMetodoPagoPmc() {
    limpiarErrorPmc('metodo_pago');

    pmcForm.clearErrors(
        'display_data',
        'display_data.alias',
        'display_data.cbu',
        'display_data.cvu',
        'display_data.banco',
        'display_data.titular'
    );
}

function guardarPmc() {
    if (pmcForm.processing) return;

    if (!validarTransferenciaPmc()) {
        nextTick(enfocarPrimerErrorPmc);
        return;
    }

    pmcForm.post(route('configuracion.metodo-pago.store'), {
        preserveScroll: true,
        onSuccess: () => {
            resetPmcForm();
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Medio de pago guardado', showConfirmButton: false, timer: 2000 });
        },
        onError: (errors) => {
            const campoGeneral = Object.keys(errors).find((campo) => !camposPmcConErrorInline.includes(campo));

            if (campoGeneral) {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'error',
                    title: 'No se pudo guardar el medio de pago',
                    text: mensajeErrorPmc(campoGeneral) || 'Revisá los datos e intentá de nuevo.',
                    showConfirmButton: false,
                    timer: 4000,
                });
            }

            nextTick(enfocarPrimerErrorPmc);
        },
    });
}

function eliminarPmc(pmc) {
    Swal.fire({
        title: '¿Eliminar?',
        text: `Se eliminará la configuración de ${pmc.metodo_pago_label}`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#ef4444',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('configuracion.metodo-pago.destroy', pmc.id), {
                preserveScroll: true,
                onSuccess: () => Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Eliminado', showConfirmButton: false, timer: 2000 }),
            });
        }
    });
}

const showAdvanced = ref(false);

const storefrontForm = useForm({
    theme: { ...(props.storeConfig?.theme || {}) },
    sections: { ...(props.storeConfig?.sections || {}) },
    seo: { ...(props.storeConfig?.seo || {}) },
    content: { ...(props.storeConfig?.content || {}) },
});

function guardarStorefront() {
    storefrontForm.post(route('configuracion.storefront.update'), {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Configuración de tienda guardada', showConfirmButton: false, timer: 2000 });
        },
    });
}

function resetColors() {
    Swal.fire({
        title: '¿Restablecer colores?',
        text: 'Los colores volverán a los valores originales de fábrica.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, restablecer',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#ef4444',
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('configuracion.storefront.reset'), {}, {
                preserveScroll: true,
                onSuccess: () => {
                    storefrontForm.defaults('theme', { ...(window.defaultStoreTheme || {}) });
                    storefrontForm.reset('theme');
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Colores restablecidos', showConfirmButton: false, timer: 2000 });
                },
            });
        }
    });
}

// El formulario usa novalidate para poder activar la pestaña del control
// inválido antes de mostrar el mensaje nativo de un campo oculto por v-show.
const obtenerControlInvalido = (formulario) => {
    return Array.from(formulario.elements).find(
        (control) => control.willValidate
            && typeof control.checkValidity === 'function'
            && !control.checkValidity()
    );
};

const mostrarControlInvalido = (control) => {
    const tab = control.closest('[data-tab]')?.dataset.tab;

    const enfocar = () => {
        control.focus();
        control.reportValidity();
    };

    if (tab && tab !== tabActiva.value) {
        tabActiva.value = tab;
        nextTick(enfocar);
        return;
    }

    enfocar();
};

const guardarConfiguracion = (event) => {
    const formulario = event.currentTarget;

    if (formulario && !formulario.checkValidity()) {
        const controlInvalido = obtenerControlInvalido(formulario);

        if (controlInvalido) {
            mostrarControlInvalido(controlInvalido);
        } else {
            formulario.reportValidity();
        }

        return;
    }

    form.transform((data) => ({
        ...data,
        permitir_stock_negativo: data.permitir_stock_negativo ? '1' : '0',
        ticket_digital_auto_email: data.ticket_digital_auto_email ? '1' : '0',
        acepta_efectivo: data.acepta_efectivo ? 1 : 0 // Adaptamos el booleano para la BD
    })).post(route('configuracion.update'), {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Ajustes guardados correctamente',
                showConfirmButton: false,
                timer: 3000
            });
        },
        onError: (errors) => {
            const tabConError = Object.keys(camposPorTab).find((tab) =>
                camposPorTab[tab].some((campo) => errors[campo])
            );

            if (tabConError) tabActiva.value = tabConError;

            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'error',
                title: 'No se pudieron guardar los ajustes. Revisá los campos marcados.',
                showConfirmButton: false,
                timer: 4000
            });
        }
    });
};
</script>

<template>
    <Head title="Configuración de la Empresa" />

    <AuthenticatedLayout>
        <div class="py-6 px-4 sm:px-6 lg:px-8 bg-slate-50 min-h-screen">
            
            <div class="mb-8">
                <h1 class="text-2xl font-black text-slate-800 uppercase tracking-tight">Ajustes Globales</h1>
                <div class="h-1 w-16 bg-sky-500 mt-2"></div>
                <p class="text-sm text-slate-500 mt-2 font-medium">Administrá la identidad y reglas operativas de tu negocio.</p>
            </div>

            <div class="flex flex-col lg:flex-row gap-8">
                
                <div class="lg:w-1/4">
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden sticky top-6">
                        <nav class="flex flex-col p-2 space-y-1">
                            <button type="button" @click="tabActiva = 'general'" :class="tabActiva === 'general' ? 'bg-sky-50 text-sky-700 font-bold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-medium'" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-colors text-left text-sm">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                                General e Identidad
                            </button>
                            <button type="button" @click="tabActiva = 'pos'" :class="tabActiva === 'pos' ? 'bg-sky-50 text-sky-700 font-bold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-medium'" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-colors text-left text-sm">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                                Punto de Venta (POS)
                            </button>
                            <button type="button" @click="tabActiva = 'sistema'" :class="tabActiva === 'sistema' ? 'bg-sky-50 text-sky-700 font-bold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-medium'" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-colors text-left text-sm">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                Reglas del Sistema
                            </button>
                            <button type="button" @click="tabActiva = 'tienda'" :class="tabActiva === 'tienda' ? 'bg-sky-50 text-sky-700 font-bold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-medium'" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-colors text-left text-sm">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                                Tienda Online y Pagos
                            </button>
                            <button type="button" @click="tabActiva = 'metodos_pago_pos'" :class="tabActiva === 'metodos_pago_pos' ? 'bg-sky-50 text-sky-700 font-bold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-medium'" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-colors text-left text-sm">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                                POS - Medios de Pago
                            </button>
                            <button type="button" @click="tabActiva = 'storefront'" :class="tabActiva === 'storefront' ? 'bg-sky-50 text-sky-700 font-bold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-medium'" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-colors text-left text-sm">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg>
                                Storefront
                            </button>
                            <button type="button" @click="router.visit(route('recargos.index'))" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-colors text-left text-sm text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-medium">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                                Recargos por Tarjeta
                            </button>
                        </nav>
                    </div>
                </div>

                <div class="lg:flex-1">
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200">
                        <form v-show="tabsDelFormPrincipal.includes(tabActiva)" novalidate @submit.prevent="guardarConfiguracion">
                        <div v-show="tabActiva === 'general'" data-tab="general" class="p-8">
                            <div class="flex gap-6">
                                <div class="flex-shrink-0">
                                    <div class="relative group">
                                        <img v-if="logoPreview" :src="logoPreview" class="w-24 h-24 object-cover rounded-xl border border-slate-200" />
                                        <div v-else class="w-24 h-24 bg-slate-100 rounded-xl flex items-center justify-center text-slate-400">
                                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                        </div>
                                        <label class="absolute inset-0 w-full h-full bg-slate-900/50 flex items-center justify-center text-white font-bold opacity-0 group-hover:opacity-100 rounded-xl transition-opacity cursor-pointer">
                                            Cambiar
                                            <input type="file" class="hidden" accept="image/*" @change="handleLogoUpload">
                                        </label>
                                    </div>
                                    <span class="text-[10px] uppercase font-bold text-slate-400 tracking-widest">Logo (PNG/JPG)</span>
                                    <p v-if="form.errors.logo" class="mt-1 text-xs font-bold text-rose-500">{{ form.errors.logo }}</p>
                                </div>

                                <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-5">
                                    <div>
                                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Nombre Comercial</label>
                                        <input v-model="form.nombre_empresa" type="text" maxlength="255" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 focus:border-sky-500 font-medium text-slate-800">
                                        <p v-if="form.errors.nombre_empresa" class="mt-1 text-xs font-bold text-rose-500">{{ form.errors.nombre_empresa }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">CUIT / RUT</label>
                                        <input v-model="form.cuit" type="text" maxlength="20" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 focus:border-sky-500 font-medium text-slate-800">
                                        <p v-if="form.errors.cuit" class="mt-1 text-xs font-bold text-rose-500">{{ form.errors.cuit }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Teléfono Principal</label>
                                        <input v-model="form.telefono" type="tel" maxlength="50" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 focus:border-sky-500 font-medium text-slate-800">
                                        <p v-if="form.errors.telefono" class="mt-1 text-xs font-bold text-rose-500">{{ form.errors.telefono }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Dirección Central</label>
                                        <input v-model="form.direccion" type="text" maxlength="255" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 focus:border-sky-500 font-medium text-slate-800">
                                        <p v-if="form.errors.direccion" class="mt-1 text-xs font-bold text-rose-500">{{ form.errors.direccion }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-show="tabActiva === 'pos'" data-tab="pos" class="p-8">
                            <h2 class="text-lg font-black text-slate-800 uppercase tracking-widest mb-6 border-b border-slate-100 pb-2">Configuración de Ventas</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Formato de Ticket</label>
                                    <select v-model="form.formato_impresion" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 focus:border-sky-500 font-medium text-slate-800">
                                        <option value="58mm">Impresora Térmica (58mm)</option>
                                        <option value="80mm">Impresora Térmica (80mm)</option>
                                    </select>
                                    <p v-if="form.errors.formato_impresion" class="mt-1 text-xs font-bold text-rose-500">{{ form.errors.formato_impresion }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Mensaje al Pie del Ticket</label>
                                    <input v-model="form.ticket_mensaje_pie" type="text" maxlength="500" placeholder="Ej: ¡Gracias por su compra!" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 focus:border-sky-500 font-medium text-slate-800">
                                    <p v-if="form.errors.ticket_mensaje_pie" class="mt-1 text-xs font-bold text-rose-500">{{ form.errors.ticket_mensaje_pie }}</p>
                                </div>
                                <div class="col-span-full mt-2">
                                    <label class="flex items-center gap-3 p-4 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-50 transition-colors">
                                        <input v-model="form.permitir_stock_negativo" type="checkbox" class="w-5 h-5 text-sky-600 rounded focus:ring-sky-500 border-slate-300">
                                        <div>
                                            <p class="font-bold text-slate-800">Permitir facturar sin stock (Stock Negativo)</p>
                                            <p class="text-xs text-slate-500 font-medium">Si está activo, el sistema te dejará cobrar un producto aunque el stock llegue a menos de cero.</p>
                                        </div>
                                    </label>
                                    <p v-if="form.errors.permitir_stock_negativo" class="mt-1 text-xs font-bold text-rose-500">{{ form.errors.permitir_stock_negativo }}</p>
                                </div>
                                <div class="col-span-full">
                                    <label class="flex items-center gap-3 p-4 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-50 transition-colors">
                                        <input v-model="form.ticket_digital_auto_email" type="checkbox" class="w-5 h-5 text-sky-600 rounded focus:ring-sky-500 border-slate-300">
                                        <div>
                                            <p class="font-bold text-slate-800">Enviar ticket por email automáticamente</p>
                                            <p class="text-xs text-slate-500 font-medium">Si el cliente tiene email registrado, se le envía el ticket en PDF al finalizar la venta. Requiere worker de colas activo.</p>
                                        </div>
                                    </label>
                                    <p v-if="form.errors.ticket_digital_auto_email" class="mt-1 text-xs font-bold text-rose-500">{{ form.errors.ticket_digital_auto_email }}</p>
                                </div>
                            </div>
                        </div>

                        <div v-show="tabActiva === 'sistema'" data-tab="sistema" class="p-8">
                            <h2 class="text-lg font-black text-slate-800 uppercase tracking-widest mb-6 border-b border-slate-100 pb-2">Reglas de Negocio</h2>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Límite de Fiado por Defecto</label>
                                    <div class="relative">
                                        <span class="absolute left-4 top-2.5 font-black text-slate-400">$</span>
                                        <input v-model="form.limite_fiado_defecto" type="number" step="0.01" min="0" class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-8 pr-4 py-2.5 focus:ring-sky-500 focus:border-sky-500 font-medium text-slate-800">
                                    </div>
                                    <p class="text-[10px] text-slate-400 font-bold mt-1 uppercase">Monto inicial para nuevos clientes</p>
                                    <p v-if="form.errors.limite_fiado_defecto" class="mt-1 text-xs font-bold text-rose-500">{{ form.errors.limite_fiado_defecto }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Símbolo de Moneda</label>
                                    <select v-model="form.moneda_defecto" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 focus:border-sky-500 font-medium text-slate-800">
                                        <option value="ARS">Pesos Argentinos ($ ARS)</option>
                                        <option value="USD">Dólares ($ USD)</option>
                                        <option value="EUR">Euros (€ EUR)</option>
                                    </select>
                                    <p v-if="form.errors.moneda_defecto" class="mt-1 text-xs font-bold text-rose-500">{{ form.errors.moneda_defecto }}</p>
                                </div>

                                <div class="col-span-full mt-6 p-4 bg-sky-50/50 border border-sky-100 rounded-2xl">
                                    <h3 class="text-sm font-black text-sky-800 uppercase tracking-widest mb-4">Tienda Online y Delivery</h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Costo Delivery por Defecto</label>
                                            <div class="relative">
                                                <span class="absolute left-4 top-2.5 font-black text-slate-400">$</span>
                                                <input v-model="form.costo_delivery_defecto" type="number" step="0.01" min="0" class="w-full bg-white border border-slate-200 rounded-xl pl-8 pr-4 py-2.5 focus:ring-sky-500 font-medium text-slate-800">
                                            </div>
                                            <p class="text-[10px] text-slate-400 font-bold mt-1 uppercase">Tarifa base para todos tus locales.</p>
                                            <p v-if="form.errors.costo_delivery_defecto" class="mt-1 text-xs font-bold text-rose-500">{{ form.errors.costo_delivery_defecto }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <h3 class="text-md font-black text-rose-800 uppercase tracking-widest mt-10 mb-4 border-b border-rose-100 pb-2">Gestión de Cobranzas y Mora</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Días de Gracia</label>
                                    <input v-model="form.mora_dias_gracia" type="number" min="0" max="365" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 focus:border-sky-500 font-medium text-slate-800">
                                    <p class="text-[10px] text-slate-400 font-bold mt-1 uppercase">Días de tolerancia antes de castigar la deuda.</p>
                                    <p v-if="form.errors.mora_dias_gracia" class="mt-1 text-xs font-bold text-rose-500">{{ form.errors.mora_dias_gracia }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Interés por Mora Automática</label>
                                    <div class="relative">
                                        <input v-model="form.mora_tasa_interes" type="number" step="0.1" min="0" max="100" class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-4 pr-10 py-2.5 focus:ring-sky-500 focus:border-sky-500 font-medium text-slate-800">
                                        <span class="absolute right-4 top-2.5 font-black text-slate-400">%</span>
                                    </div>
                                    <p class="text-[10px] text-slate-400 font-bold mt-1 uppercase">Recargo aplicado por el sistema.</p>
                                    <p v-if="form.errors.mora_tasa_interes" class="mt-1 text-xs font-bold text-rose-500">{{ form.errors.mora_tasa_interes }}</p>
                                </div>
                            </div>
                        </div>

                        <div v-show="tabActiva === 'tienda'" data-tab="tienda" class="p-8">
                            <h2 class="text-lg font-black text-slate-800 uppercase tracking-widest mb-6 border-b border-slate-100 pb-2">Logística y Envíos</h2>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
                                <div>
                                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Precio Base de Envío</label>
                                    <div class="relative">
                                        <span class="absolute left-4 top-2.5 font-black text-slate-400">$</span>
                                        <input v-model="form.envio_precio_base" type="number" step="0.01" min="0" class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-8 pr-4 py-2.5 focus:ring-sky-500 font-medium text-slate-800">
                                    </div>
                                    <p v-if="form.errors.envio_precio_base" class="mt-1 text-xs font-bold text-rose-500">{{ form.errors.envio_precio_base }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Precio por Km Extra</label>
                                    <div class="relative">
                                        <span class="absolute left-4 top-2.5 font-black text-slate-400">$</span>
                                        <input v-model="form.envio_precio_km" type="number" step="0.01" min="0" class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-8 pr-4 py-2.5 focus:ring-sky-500 font-medium text-slate-800">
                                    </div>
                                    <p v-if="form.errors.envio_precio_km" class="mt-1 text-xs font-bold text-rose-500">{{ form.errors.envio_precio_km }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Radio Máximo (Km)</label>
                                    <input v-model="form.envio_radio_km" type="number" min="0" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 font-medium text-slate-800">
                                    <p v-if="form.errors.envio_radio_km" class="mt-1 text-xs font-bold text-rose-500">{{ form.errors.envio_radio_km }}</p>
                                </div>
                            </div>

                            <h2 class="text-lg font-black text-slate-800 uppercase tracking-widest mb-6 border-b border-slate-100 pb-2">Medios de Pago</h2>
                            <div class="grid grid-cols-1 gap-6 mb-8">
                                <label class="flex items-center gap-3 p-4 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-50 transition-colors">
                                    <input v-model="form.acepta_efectivo" type="checkbox" class="w-5 h-5 text-sky-600 rounded focus:ring-sky-500 border-slate-300">
                                    <div>
                                        <p class="font-bold text-slate-800">Aceptar Efectivo contra entrega</p>
                                        <p class="text-xs text-slate-500 font-medium">El cliente paga al cadete cuando recibe el pedido.</p>
                                    </div>
                                </label>
                                <p v-if="form.errors.acepta_efectivo" class="mt-1 text-xs font-bold text-rose-500">{{ form.errors.acepta_efectivo }}</p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                                <div class="p-4 bg-sky-50/50 border border-sky-100 rounded-2xl">
                                    <h3 class="text-sm font-black text-sky-800 uppercase mb-4">Transferencia Bancaria</h3>
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">CBU / CVU</label>
                                            <input v-model="form.transferencia_cbu" type="text" maxlength="50" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2 text-sm font-medium text-slate-800">
                                            <p v-if="form.errors.transferencia_cbu" class="mt-1 text-xs font-bold text-rose-500">{{ form.errors.transferencia_cbu }}</p>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Alias</label>
                                            <input v-model="form.transferencia_alias" type="text" maxlength="50" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2 text-sm font-medium text-slate-800">
                                            <p v-if="form.errors.transferencia_alias" class="mt-1 text-xs font-bold text-rose-500">{{ form.errors.transferencia_alias }}</p>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Titular de la Cuenta</label>
                                            <input v-model="form.transferencia_titular" type="text" maxlength="100" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2 text-sm font-medium text-slate-800">
                                            <p v-if="form.errors.transferencia_titular" class="mt-1 text-xs font-bold text-rose-500">{{ form.errors.transferencia_titular }}</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="p-4 bg-blue-50/50 border border-blue-100 rounded-2xl">
                                    <h3 class="text-sm font-black text-blue-800 uppercase mb-4">Mercado Pago</h3>
                                    <label class="flex items-center gap-3 mb-4 p-3 bg-white rounded-xl border border-blue-100 cursor-pointer hover:bg-blue-50 transition-colors">
                                        <input v-model="form.mp_enabled" type="checkbox" class="w-5 h-5 text-blue-600 rounded focus:ring-blue-500 border-slate-300">
                                        <div>
                                            <p class="font-bold text-slate-800 text-sm">Habilitar Mercado Pago</p>
                                            <p class="text-xs text-slate-500">Mostrar MP como opción de pago en la tienda online</p>
                                        </div>
                                    </label>
                                    <p v-if="form.errors.mp_enabled" class="mt-1 text-xs font-bold text-rose-500">{{ form.errors.mp_enabled }}</p>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Access Token (Producción)</label>
                                        <input v-model="form.mp_access_token" type="password" maxlength="255" placeholder="APP_USR-..." class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2 text-sm font-mono text-slate-800">
                                        <p class="text-[10px] text-slate-500 mt-2 font-bold">Requerido para cobrar online desde el catálogo web.</p>
                                        <p v-if="form.errors.mp_access_token" class="mt-1 text-xs font-bold text-rose-500">{{ form.errors.mp_access_token }}</p>
                                    </div>
                                </div>

                                <div class="p-4 bg-violet-50/50 border border-violet-100 rounded-2xl">
                                    <h3 class="text-sm font-black text-violet-800 uppercase mb-4">viüMi</h3>
                                    <label class="flex items-center gap-3 mb-4 p-3 bg-white rounded-xl border border-violet-100 cursor-pointer hover:bg-violet-50 transition-colors">
                                        <input v-model="form.viumi_enabled" type="checkbox" class="w-5 h-5 text-violet-600 rounded focus:ring-violet-500 border-slate-300">
                                        <div>
                                            <p class="font-bold text-slate-800 text-sm">Habilitar viüMi</p>
                                            <p class="text-xs text-slate-500">Mostrar viüMi como opción de pago en la tienda online</p>
                                        </div>
                                    </label>
                                    <p v-if="form.errors.viumi_enabled" class="mt-1 text-xs font-bold text-rose-500">{{ form.errors.viumi_enabled }}</p>
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Client ID</label>
                                            <input v-model="form.viumi_client_id" type="text" maxlength="255" placeholder="XXXXXXXX-XXX-XXXX-XXX-XXXXXXXXXXXX" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2 text-sm font-mono text-slate-800">
                                            <p v-if="form.errors.viumi_client_id" class="mt-1 text-xs font-bold text-rose-500">{{ form.errors.viumi_client_id }}</p>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Client Secret</label>
                                            <input v-model="form.viumi_client_secret" type="password" maxlength="255" placeholder="Completar solo si se desea cambiar" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2 text-sm font-mono text-slate-800">
                                            <p class="text-[10px] text-slate-500 mt-1 font-bold">No se muestra por seguridad. Dejá vacío para mantener el actual.</p>
                                            <p v-if="form.errors.viumi_client_secret" class="mt-1 text-xs font-bold text-rose-500">{{ form.errors.viumi_client_secret }}</p>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Entorno</label>
                                            <select v-model="form.viumi_environment" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2 text-sm font-medium text-slate-800">
                                                <option value="sandbox">Sandbox (Pruebas)</option>
                                                <option value="production">Producción</option>
                                            </select>
                                            <p v-if="form.errors.viumi_environment" class="mt-1 text-xs font-bold text-rose-500">{{ form.errors.viumi_environment }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <footer v-show="tabsDelFormPrincipal.includes(tabActiva)" class="p-6 bg-slate-50 border-t border-slate-100 flex justify-end gap-3">
                            <button type="submit" :disabled="form.processing" class="bg-slate-900 hover:bg-sky-600 disabled:bg-slate-300 text-white font-black py-3 px-8 rounded-xl shadow-lg uppercase tracking-widest transition-all active:scale-95 flex items-center gap-2">
                                <span v-if="form.processing">Guardando...</span>
                                <span v-else>Guardar Ajustes</span>
                            </button>
                        </footer>
                        </form>

                        <div v-show="tabActiva === 'metodos_pago_pos'" class="p-8">
                            <h2 class="text-lg font-black text-slate-800 uppercase tracking-widest mb-6 border-b border-slate-100 pb-2">Medios de Pago para POS</h2>
                            <p class="text-sm text-slate-500 mb-6">Configurá los métodos de pago que requieren confirmación manual en el POS (transferencias, Mercado Pago, etc.).</p>

                            <div v-if="paymentMethodConfigs && paymentMethodConfigs.length" class="mb-8 space-y-3">
                                <div v-for="pmc in paymentMethodConfigs" :key="pmc.id"
                                    class="flex items-center justify-between p-4 bg-white border border-slate-200 rounded-xl hover:border-sky-200 transition-colors"
                                >
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-slate-800">{{ pmc.metodo_pago_label }}</span>
                                            <span v-if="pmc.provider" class="text-xs font-mono text-slate-400">({{ pmc.provider }})</span>
                                            <span v-if="!pmc.enabled" class="text-xs font-bold text-rose-500 bg-rose-50 px-2 py-0.5 rounded">DESACTIVADO</span>
                                        </div>
                                        <div v-if="pmc.display_data" class="mt-1 text-xs text-slate-500 flex flex-wrap gap-x-4">
                                            <span v-if="pmc.display_data.alias">Alias: {{ pmc.display_data.alias }}</span>
                                            <span v-if="pmc.display_data.cvu">CVU: {{ pmc.display_data.cvu }}</span>
                                            <span v-if="pmc.display_data.cbu">CBU: {{ pmc.display_data.cbu }}</span>
                                            <span v-if="pmc.display_data.banco">Banco: {{ pmc.display_data.banco }}</span>
                                            <span v-if="pmc.display_data.titular">Titular: {{ pmc.display_data.titular }}</span>
                                        </div>
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="button" @click="editPmc(pmc)" class="px-3 py-1.5 text-xs font-bold text-sky-600 bg-sky-50 hover:bg-sky-100 rounded-lg transition-colors">Editar</button>
                                        <button type="button" @click="eliminarPmc(pmc)" class="px-3 py-1.5 text-xs font-bold text-rose-500 bg-rose-50 hover:bg-rose-100 rounded-lg transition-colors">✕</button>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-slate-50 border border-slate-200 rounded-2xl p-6">
                                <h3 class="text-sm font-black text-slate-700 uppercase tracking-widest mb-4">{{ editingPmcId ? 'Editar' : 'Nuevo' }} método de pago</h3>
                                <form @submit.prevent="guardarPmc" class="space-y-4" novalidate>
                                    <div v-if="mostrarResumenErroresPmc" id="pmc-error-summary" role="alert" aria-live="assertive" tabindex="-1"
                                        class="rounded-xl border border-rose-200 bg-rose-50 p-4 outline-none focus:ring-2 focus:ring-rose-400"
                                    >
                                        <p class="text-sm font-black text-rose-700">No se pudo guardar el medio de pago.</p>
                                        <p class="mt-1 text-xs font-medium text-rose-600">{{ mensajeResumenPmc }}</p>
                                        <ul v-if="erroresGeneralesPmc.length" class="mt-2 space-y-1 text-xs text-rose-700">
                                            <li v-for="error in erroresGeneralesPmc" :key="error.campo">
                                                <span class="font-bold">{{ error.etiqueta }}:</span> {{ error.mensaje }}
                                            </li>
                                        </ul>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label for="pmc-metodo-pago" class="block text-xs font-bold text-slate-500 uppercase mb-1">Método de Pago</label>
                                            <select id="pmc-metodo-pago" v-model="pmcForm.metodo_pago" :disabled="!!editingPmcId"
                                                class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 focus:border-sky-500 font-medium text-slate-800"
                                                :class="{'border-rose-500 ring-1 ring-rose-200': mensajeErrorPmc('metodo_pago')}"
                                                :aria-invalid="Boolean(mensajeErrorPmc('metodo_pago'))"
                                                :aria-describedby="mensajeErrorPmc('metodo_pago') ? idErrorPmc('metodo_pago') : undefined"
                                                @change="cambiarMetodoPagoPmc()"
                                            >
                                                <option v-for="opt in metodoPagoOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                            </select>
                                            <p v-if="mensajeErrorPmc('metodo_pago')" :id="idErrorPmc('metodo_pago')" class="mt-1 text-xs font-bold text-rose-500">
                                                {{ mensajeErrorPmc('metodo_pago') }}
                                            </p>
                                        </div>
                                        <div>
                                            <label for="pmc-provider" class="block text-xs font-bold text-slate-500 uppercase mb-1">Pasarela (opcional)</label>
                                            <input id="pmc-provider" v-model="pmcForm.provider" type="text" placeholder="mercadopago, viumi..." class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 font-medium text-slate-800"
                                                :class="{'border-rose-500 ring-1 ring-rose-200': mensajeErrorPmc('provider')}"
                                                :aria-invalid="Boolean(mensajeErrorPmc('provider'))"
                                                :aria-describedby="mensajeErrorPmc('provider') ? idErrorPmc('provider') : undefined"
                                                @input="limpiarErrorPmc('provider')"
                                            >
                                            <p v-if="mensajeErrorPmc('provider')" :id="idErrorPmc('provider')" class="mt-1 text-xs font-bold text-rose-500">
                                                {{ mensajeErrorPmc('provider') }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div class="md:col-span-2" role="group" aria-label="Alias, CBU o CVU" :aria-describedby="descripcionGrupoPmc">
                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                                <div>
                                                    <label for="pmc-display-data-alias" class="block text-xs font-bold text-slate-500 uppercase mb-1">Alias</label>
                                                    <input id="pmc-display-data-alias" v-model="pmcForm.display_data.alias" type="text" placeholder="ej: mercopago.pago.facil" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 font-medium text-slate-800"
                                                        :class="{'border-rose-500 ring-1 ring-rose-200': mensajeErrorPmc('display_data.alias')}"
                                                        :aria-invalid="Boolean(mensajeErrorPmc('display_data.alias'))"
                                                        :aria-describedby="mensajeErrorPmc('display_data.alias') ? idErrorPmc('display_data.alias') : undefined"
                                                        @input="limpiarErrorPmc('display_data.alias')"
                                                    >
                                                    <p v-if="mensajeErrorPmc('display_data.alias')" :id="idErrorPmc('display_data.alias')" class="mt-1 text-xs font-bold text-rose-500">
                                                        {{ mensajeErrorPmc('display_data.alias') }}
                                                    </p>
                                                </div>
                                                <div>
                                                    <label for="pmc-display-data-cbu" class="block text-xs font-bold text-slate-500 uppercase mb-1">CBU</label>
                                                    <input id="pmc-display-data-cbu" v-model="pmcForm.display_data.cbu" type="text" placeholder="0000000000000000000000" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 font-medium text-slate-800"
                                                        :class="{'border-rose-500 ring-1 ring-rose-200': mensajeErrorPmc('display_data.cbu')}"
                                                        :aria-invalid="Boolean(mensajeErrorPmc('display_data.cbu'))"
                                                        :aria-describedby="mensajeErrorPmc('display_data.cbu') ? idErrorPmc('display_data.cbu') : undefined"
                                                        @input="limpiarErrorPmc('display_data.cbu')"
                                                    >
                                                    <p v-if="mensajeErrorPmc('display_data.cbu')" :id="idErrorPmc('display_data.cbu')" class="mt-1 text-xs font-bold text-rose-500">
                                                        {{ mensajeErrorPmc('display_data.cbu') }}
                                                    </p>
                                                </div>
                                                <div>
                                                    <label for="pmc-display-data-cvu" class="block text-xs font-bold text-slate-500 uppercase mb-1">CVU</label>
                                                    <input id="pmc-display-data-cvu" v-model="pmcForm.display_data.cvu" type="text" placeholder="0000003100000000000000" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 font-medium text-slate-800"
                                                        :class="{'border-rose-500 ring-1 ring-rose-200': mensajeErrorPmc('display_data.cvu')}"
                                                        :aria-invalid="Boolean(mensajeErrorPmc('display_data.cvu'))"
                                                        :aria-describedby="mensajeErrorPmc('display_data.cvu') ? idErrorPmc('display_data.cvu') : undefined"
                                                        @input="limpiarErrorPmc('display_data.cvu')"
                                                    >
                                                    <p v-if="mensajeErrorPmc('display_data.cvu')" :id="idErrorPmc('display_data.cvu')" class="mt-1 text-xs font-bold text-rose-500">
                                                        {{ mensajeErrorPmc('display_data.cvu') }}
                                                    </p>
                                                </div>
                                            </div>
                                            <p v-if="esTransferenciaPmc" id="pmc-display-data-grupo-ayuda" class="mt-1 text-xs text-slate-500">
                                                Completá al menos uno de Alias, CBU o CVU.
                                            </p>
                                            <p v-if="mensajeErrorPmc('display_data')" :id="idErrorPmc('display_data')" class="mt-1 text-xs font-bold text-rose-500">
                                                {{ mensajeErrorPmc('display_data') }}
                                            </p>
                                        </div>
                                        <div>
                                            <label for="pmc-display-data-banco" class="block text-xs font-bold text-slate-500 uppercase mb-1">Banco <span v-if="esTransferenciaPmc" class="text-slate-400 normal-case">(opcional)</span></label>
                                            <input id="pmc-display-data-banco" v-model="pmcForm.display_data.banco" type="text" placeholder="Banco Ejemplo" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 font-medium text-slate-800"
                                                :class="{'border-rose-500 ring-1 ring-rose-200': mensajeErrorPmc('display_data.banco')}"
                                                :aria-invalid="Boolean(mensajeErrorPmc('display_data.banco'))"
                                                :aria-describedby="mensajeErrorPmc('display_data.banco') ? idErrorPmc('display_data.banco') : undefined"
                                                @input="limpiarErrorPmc('display_data.banco')"
                                            >
                                            <p v-if="mensajeErrorPmc('display_data.banco')" :id="idErrorPmc('display_data.banco')" class="mt-1 text-xs font-bold text-rose-500">
                                                {{ mensajeErrorPmc('display_data.banco') }}
                                            </p>
                                        </div>
                                        <div>
                                            <label for="pmc-display-data-titular" class="block text-xs font-bold text-slate-500 uppercase mb-1">Titular <span v-if="esTransferenciaPmc" class="text-rose-500">*</span></label>
                                            <input id="pmc-display-data-titular" v-model="pmcForm.display_data.titular" type="text" placeholder="Nombre del titular" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 font-medium text-slate-800"
                                                :class="{'border-rose-500 ring-1 ring-rose-200': mensajeErrorPmc('display_data.titular')}"
                                                :aria-invalid="Boolean(mensajeErrorPmc('display_data.titular'))"
                                                :aria-required="esTransferenciaPmc ? 'true' : undefined"
                                                :aria-describedby="mensajeErrorPmc('display_data.titular') ? idErrorPmc('display_data.titular') : undefined"
                                                @input="limpiarErrorPmc('display_data.titular')"
                                            >
                                            <p v-if="mensajeErrorPmc('display_data.titular')" :id="idErrorPmc('display_data.titular')" class="mt-1 text-xs font-bold text-rose-500">
                                                {{ mensajeErrorPmc('display_data.titular') }}
                                            </p>
                                        </div>
                                    </div>

                                    <label for="pmc-enabled" class="flex items-center gap-3 p-3 bg-white rounded-xl border border-slate-200 cursor-pointer"
                                        :class="{'border-rose-300 bg-rose-50': mensajeErrorPmc('enabled')}"
                                    >
                                        <input id="pmc-enabled" v-model="pmcForm.enabled" type="checkbox" class="w-5 h-5 text-sky-600 rounded focus:ring-sky-500 border-slate-300"
                                            :aria-invalid="Boolean(mensajeErrorPmc('enabled'))"
                                            :aria-describedby="mensajeErrorPmc('enabled') ? idErrorPmc('enabled') : undefined"
                                            @change="limpiarErrorPmc('enabled')"
                                        >
                                        <span class="font-bold text-slate-700 text-sm">Habilitado</span>
                                    </label>
                                    <p v-if="mensajeErrorPmc('enabled')" :id="idErrorPmc('enabled')" class="mt-1 text-xs font-bold text-rose-500">
                                        {{ mensajeErrorPmc('enabled') }}
                                    </p>

                                    <div class="flex gap-3 pt-2">
                                        <button type="submit" :disabled="pmcForm.processing" :aria-busy="pmcForm.processing"
                                            class="bg-sky-600 hover:bg-sky-700 disabled:bg-slate-300 disabled:cursor-not-allowed text-white font-black py-2.5 px-6 rounded-xl shadow-lg uppercase tracking-widest transition-all text-sm"
                                        >
                                            {{ pmcForm.processing ? 'Guardando...' : 'Guardar' }}
                                        </button>
                                        <button v-if="editingPmcId" type="button" @click="resetPmcForm" :disabled="pmcForm.processing"
                                            class="px-4 py-2.5 border border-slate-200 rounded-xl text-slate-600 font-bold text-sm hover:bg-slate-50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                        >
                                            Cancelar
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div v-show="tabActiva === 'storefront'" class="p-8">
                            <form @submit.prevent="guardarStorefront">
                                <div class="mb-8">
                                    <h2 class="text-lg font-black text-slate-800 uppercase tracking-widest mb-6 border-b border-slate-100 pb-2">Personalizá tu tienda</h2>

                                    <div class="max-w-lg space-y-5">
                                        <div>
                                            <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1.5">Color principal</label>
                                            <p class="text-xs text-slate-400 mb-2">Se usa en botones, ofertas y banners</p>
                                            <div class="flex gap-2">
                                                <input v-model="storefrontForm.theme.primary_color" type="color" class="w-10 h-10 rounded-lg border border-slate-200 cursor-pointer shrink-0">
                                                <input v-model="storefrontForm.theme.primary_color" type="text" class="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm font-mono focus:ring-sky-500">
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1.5">Color de fondo de la página</label>
                                            <div class="flex gap-2">
                                                <input v-model="storefrontForm.theme.background_color" type="color" class="w-10 h-10 rounded-lg border border-slate-200 cursor-pointer shrink-0">
                                                <input v-model="storefrontForm.theme.background_color" type="text" class="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm font-mono focus:ring-sky-500">
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1.5">Mensaje de WhatsApp</label>
                                            <p class="text-xs text-slate-400 mb-2">Texto que se envía cuando alguien hace clic en WhatsApp</p>
                                            <input v-model="storefrontForm.sections.whatsapp.greeting" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-sky-500">
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-8">
                                    <h2 class="text-lg font-black text-slate-800 uppercase tracking-widest mb-6 border-b border-slate-100 pb-2">Secciones</h2>

                                    <div class="space-y-6">
                                        <div class="border border-slate-200 rounded-2xl p-5">
                                            <label class="flex items-center gap-3 mb-4 cursor-pointer">
                                                <input v-model="storefrontForm.sections.hero.enabled" type="checkbox" class="w-5 h-5 text-sky-600 rounded focus:ring-sky-500 border-slate-300">
                                                <span class="font-bold text-slate-800 text-lg">Portada</span>
                                            </label>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Título</label>
                                                    <input v-model="storefrontForm.sections.hero.title" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-sky-500">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Subtítulo</label>
                                                    <input v-model="storefrontForm.sections.hero.subtitle" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-sky-500">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Texto del Botón</label>
                                                    <input v-model="storefrontForm.sections.hero.button_text" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-sky-500">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Enlace del botón</label>
                                                    <input v-model="storefrontForm.sections.hero.button_url" type="text" placeholder="#productos" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-mono focus:ring-sky-500">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Color de Fondo</label>
                                                    <div class="flex gap-2">
                                                        <input v-model="storefrontForm.sections.hero.background_color" type="color" class="w-10 h-10 rounded-lg border border-slate-200 cursor-pointer">
                                                        <input v-model="storefrontForm.sections.hero.background_color" type="text" class="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm font-mono focus:ring-sky-500">
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Color del Botón</label>
                                                    <div class="flex gap-2">
                                                        <input v-model="storefrontForm.sections.hero.button_color" type="color" class="w-10 h-10 rounded-lg border border-slate-200 cursor-pointer">
                                                        <input v-model="storefrontForm.sections.hero.button_color" type="text" class="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm font-mono focus:ring-sky-500">
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Dirección de la imagen</label>
                                                    <input v-model="storefrontForm.sections.hero.image_url" type="text" placeholder="https://..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-mono focus:ring-sky-500">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="border border-slate-200 rounded-2xl p-5">
                                            <label class="flex items-center gap-3 mb-4 cursor-pointer">
                                                <input v-model="storefrontForm.sections.categories.enabled" type="checkbox" class="w-5 h-5 text-sky-600 rounded focus:ring-sky-500 border-slate-300">
                                                <span class="font-bold text-slate-800 text-lg">Categorías</span>
                                            </label>
                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Título</label>
                                                    <input v-model="storefrontForm.sections.categories.title" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-sky-500">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Vista</label>
                                                    <select v-model="storefrontForm.sections.categories.layout" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-sky-500">
                                                        <option value="grid">Grilla</option>
                                                        <option value="list">Lista</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Columnas</label>
                                                    <select v-model="storefrontForm.sections.categories.columns" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-sky-500">
                                                        <option :value="2">2</option>
                                                        <option :value="3">3</option>
                                                        <option :value="4">4</option>
                                                        <option :value="6">6</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="border border-slate-200 rounded-2xl p-5">
                                            <span class="font-bold text-slate-800 text-lg block mb-4">Productos Destacados</span>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Título</label>
                                                    <input v-model="storefrontForm.sections.products.title" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-sky-500">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Columnas</label>
                                                    <select v-model="storefrontForm.sections.products.columns" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-sky-500">
                                                        <option :value="2">2</option>
                                                        <option :value="3">3</option>
                                                        <option :value="4">4</option>
                                                        <option :value="6">6</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="flex flex-wrap gap-4 mt-4">
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <input v-model="storefrontForm.sections.products.show_prices" type="checkbox" class="w-4 h-4 text-sky-600 rounded focus:ring-sky-500 border-slate-300">
                                                    <span class="text-sm font-medium text-slate-700">Mostrar precios</span>
                                                </label>
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <input v-model="storefrontForm.sections.products.show_discounts" type="checkbox" class="w-4 h-4 text-sky-600 rounded focus:ring-sky-500 border-slate-300">
                                                    <span class="text-sm font-medium text-slate-700">Mostrar descuentos</span>
                                                </label>
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <input v-model="storefrontForm.sections.products.show_add_to_cart" type="checkbox" class="w-4 h-4 text-sky-600 rounded focus:ring-sky-500 border-slate-300">
                                                    <span class="text-sm font-medium text-slate-700">Botón agregar al carrito</span>
                                                </label>
                                            </div>
                                        </div>

                                        <div class="border border-slate-200 rounded-2xl p-5">
                                            <label class="flex items-center gap-3 mb-4 cursor-pointer">
                                                <input v-model="storefrontForm.sections.promotions.enabled" type="checkbox" class="w-5 h-5 text-sky-600 rounded focus:ring-sky-500 border-slate-300">
                                                <span class="font-bold text-slate-800 text-lg">Promociones</span>
                                            </label>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Título</label>
                                                    <input v-model="storefrontForm.sections.promotions.title" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-sky-500">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Forma de mostrar</label>
                                                    <select v-model="storefrontForm.sections.promotions.layout" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-sky-500">
                                                        <option value="grid">Grilla</option>
                                                        <option value="carousel">Carrusel</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="border border-slate-200 rounded-2xl p-5">
                                            <label class="flex items-center gap-3 mb-4 cursor-pointer">
                                                <input v-model="storefrontForm.sections.footer.enabled" type="checkbox" class="w-5 h-5 text-sky-600 rounded focus:ring-sky-500 border-slate-300">
                                                <span class="font-bold text-slate-800 text-lg">Pie de Página</span>
                                            </label>
                                            <div>
                                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Texto</label>
                                                <input v-model="storefrontForm.sections.footer.text" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-sky-500 mb-4">
                                            </div>
                                            <div class="flex flex-wrap gap-4">
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <input v-model="storefrontForm.sections.footer.show_contact" type="checkbox" class="w-4 h-4 text-sky-600 rounded focus:ring-sky-500 border-slate-300">
                                                    <span class="text-sm font-medium text-slate-700">Mostrar contacto</span>
                                                </label>
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <input v-model="storefrontForm.sections.footer.show_social" type="checkbox" class="w-4 h-4 text-sky-600 rounded focus:ring-sky-500 border-slate-300">
                                                    <span class="text-sm font-medium text-slate-700">Mostrar redes sociales</span>
                                                </label>
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <input v-model="storefrontForm.sections.footer.show_payment_methods" type="checkbox" class="w-4 h-4 text-sky-600 rounded focus:ring-sky-500 border-slate-300">
                                                    <span class="text-sm font-medium text-slate-700">Mostrar medios de pago</span>
                                                </label>
                                            </div>
                                        </div>

                                        <div class="border border-slate-200 rounded-2xl p-5">
                                            <label class="flex items-center gap-3 mb-4 cursor-pointer">
                                                <input v-model="storefrontForm.sections.whatsapp.enabled" type="checkbox" class="w-5 h-5 text-sky-600 rounded focus:ring-sky-500 border-slate-300">
                                                <span class="font-bold text-slate-800 text-lg">WhatsApp</span>
                                            </label>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Número de Teléfono</label>
                                                    <input v-model="storefrontForm.sections.whatsapp.phone" type="text" placeholder="5491122334455" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-mono focus:ring-sky-500">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-8 border border-slate-200 rounded-2xl overflow-hidden">
                                    <button type="button" @click="showAdvanced = !showAdvanced" class="w-full flex items-center justify-between px-5 py-4 bg-slate-50 hover:bg-slate-100 transition-colors">
                                        <span class="font-bold text-slate-700 text-sm uppercase tracking-widest">⚙️ Avanzado</span>
                                        <svg class="w-5 h-5 text-slate-400 transition-transform" :class="showAdvanced ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                    <div v-show="showAdvanced" class="p-5 space-y-6">
                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                            <div>
                                                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Color secundario</label>
                                                <div class="flex gap-2">
                                                    <input v-model="storefrontForm.theme.secondary_color" type="color" class="w-10 h-10 rounded-lg border border-slate-200 cursor-pointer">
                                                    <input v-model="storefrontForm.theme.secondary_color" type="text" class="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm font-mono focus:ring-sky-500">
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Color de texto</label>
                                                <div class="flex gap-2">
                                                    <input v-model="storefrontForm.theme.text_color" type="color" class="w-10 h-10 rounded-lg border border-slate-200 cursor-pointer">
                                                    <input v-model="storefrontForm.theme.text_color" type="text" class="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm font-mono focus:ring-sky-500">
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Barra superior</label>
                                                <div class="flex gap-2">
                                                    <input v-model="storefrontForm.theme.header_background" type="color" class="w-10 h-10 rounded-lg border border-slate-200 cursor-pointer">
                                                    <input v-model="storefrontForm.theme.header_background" type="text" class="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm font-mono focus:ring-sky-500">
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Barra inferior</label>
                                                <div class="flex gap-2">
                                                    <input v-model="storefrontForm.theme.footer_background" type="color" class="w-10 h-10 rounded-lg border border-slate-200 cursor-pointer">
                                                    <input v-model="storefrontForm.theme.footer_background" type="text" class="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm font-mono focus:ring-sky-500">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <div>
                                                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Redondeo de bordes</label>
                                                <input v-model="storefrontForm.theme.border_radius" type="text" placeholder="0.5rem" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-mono focus:ring-sky-500">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Tipografía</label>
                                                <input v-model="storefrontForm.theme.font_family" type="text" placeholder="Inter, system-ui, sans-serif" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-mono focus:ring-sky-500">
                                            </div>
                                        </div>
                                        <div>
                                            <h3 class="text-xs font-black text-slate-500 uppercase tracking-widest mb-3">Qué ven Google y las redes sociales</h3>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Título para Google</label>
                                                    <input v-model="storefrontForm.seo.meta_title" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-sky-500">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Descripción para Google</label>
                                                    <input v-model="storefrontForm.seo.meta_description" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-sky-500">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Imagen al compartir en redes</label>
                                                    <input v-model="storefrontForm.seo.og_image" type="text" placeholder="https://..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-mono focus:ring-sky-500">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                                    <button type="button" @click="resetColors" class="border border-red-200 text-red-600 hover:bg-red-50 font-bold py-3 px-6 rounded-xl transition-all text-sm">
                                        🔄 Restablecer colores
                                    </button>
                                    <button type="submit" :disabled="storefrontForm.processing"
                                        class="bg-sky-600 hover:bg-sky-700 disabled:bg-slate-300 text-white font-black py-3 px-8 rounded-xl shadow-lg uppercase tracking-widest transition-all active:scale-95 flex items-center gap-2"
                                    >
                                        {{ storefrontForm.processing ? 'Guardando...' : 'Guardar Configuración de Tienda' }}
                                    </button>
                                </div>
                            </form>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>