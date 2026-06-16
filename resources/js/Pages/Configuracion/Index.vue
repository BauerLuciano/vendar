<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    configuraciones: Object,
    comercio: Object,
    paymentMethodConfigs: Array,
    metodoPagoOptions: Array,
});

const tabActiva = ref('general');
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

function editPmc(pmc) {
    editingPmcId.value = pmc.id;
    pmcForm.metodo_pago = pmc.metodo_pago;
    pmcForm.provider = pmc.provider || '';
    pmcForm.display_data = { ...(pmc.display_data || {}) };
    pmcForm.enabled = pmc.enabled;
}

function resetPmcForm() {
    editingPmcId.value = null;
    pmcForm.reset();
    pmcForm.display_data = { alias: '', cvu: '', cbu: '', banco: '', titular: '' };
}

function guardarPmc() {
    pmcForm.post(route('configuracion.metodo-pago.store'), {
        preserveScroll: true,
        onSuccess: () => {
            resetPmcForm();
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Medio de pago guardado', showConfirmButton: false, timer: 2000 });
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

const guardarConfiguracion = () => {
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
                            <button @click="tabActiva = 'general'" :class="tabActiva === 'general' ? 'bg-sky-50 text-sky-700 font-bold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-medium'" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-colors text-left text-sm">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                                General e Identidad
                            </button>
                            <button @click="tabActiva = 'pos'" :class="tabActiva === 'pos' ? 'bg-sky-50 text-sky-700 font-bold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-medium'" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-colors text-left text-sm">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                                Punto de Venta (POS)
                            </button>
                            <button @click="tabActiva = 'sistema'" :class="tabActiva === 'sistema' ? 'bg-sky-50 text-sky-700 font-bold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-medium'" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-colors text-left text-sm">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                Reglas del Sistema
                            </button>
                            <button @click="tabActiva = 'tienda'" :class="tabActiva === 'tienda' ? 'bg-sky-50 text-sky-700 font-bold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-medium'" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-colors text-left text-sm">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                                Tienda Online y Pagos
                            </button>
                            <button @click="tabActiva = 'metodos_pago_pos'" :class="tabActiva === 'metodos_pago_pos' ? 'bg-sky-50 text-sky-700 font-bold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-medium'" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-colors text-left text-sm">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                                POS - Medios de Pago
                            </button>
                        </nav>
                    </div>
                </div>

                <div class="lg:flex-1">
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200">
                        <div v-show="tabActiva === 'general'" class="p-8">
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
                                </div>

                                <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-5">
                                    <div>
                                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Nombre Comercial</label>
                                        <input v-model="form.nombre_empresa" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 focus:border-sky-500 font-medium text-slate-800" required>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">CUIT / RUT</label>
                                        <input v-model="form.cuit" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 focus:border-sky-500 font-medium text-slate-800">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Teléfono Principal</label>
                                        <input v-model="form.telefono" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 focus:border-sky-500 font-medium text-slate-800">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Dirección Central</label>
                                        <input v-model="form.direccion" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 focus:border-sky-500 font-medium text-slate-800">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-show="tabActiva === 'pos'" class="p-8">
                            <h2 class="text-lg font-black text-slate-800 uppercase tracking-widest mb-6 border-b border-slate-100 pb-2">Configuración de Ventas</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Formato de Ticket</label>
                                    <select v-model="form.formato_impresion" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 focus:border-sky-500 font-medium text-slate-800">
                                        <option value="58mm">Impresora Térmica (58mm)</option>
                                        <option value="80mm">Impresora Térmica (80mm)</option>
                                        <option value="A4">Hoja Grande (A4)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Mensaje al Pie del Ticket</label>
                                    <input v-model="form.ticket_mensaje_pie" type="text" placeholder="Ej: ¡Gracias por su compra!" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 focus:border-sky-500 font-medium text-slate-800">
                                </div>
                                <div class="col-span-full mt-2">
                                    <label class="flex items-center gap-3 p-4 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-50 transition-colors">
                                        <input v-model="form.permitir_stock_negativo" type="checkbox" class="w-5 h-5 text-sky-600 rounded focus:ring-sky-500 border-slate-300">
                                        <div>
                                            <p class="font-bold text-slate-800">Permitir facturar sin stock (Stock Negativo)</p>
                                            <p class="text-xs text-slate-500 font-medium">Si está activo, el sistema te dejará cobrar un producto aunque el stock llegue a menos de cero.</p>
                                        </div>
                                    </label>
                                </div>
                                <div class="col-span-full">
                                    <label class="flex items-center gap-3 p-4 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-50 transition-colors">
                                        <input v-model="form.ticket_digital_auto_email" type="checkbox" class="w-5 h-5 text-sky-600 rounded focus:ring-sky-500 border-slate-300">
                                        <div>
                                            <p class="font-bold text-slate-800">Enviar ticket por email automáticamente</p>
                                            <p class="text-xs text-slate-500 font-medium">Si el cliente tiene email registrado, se le envía el ticket en PDF al finalizar la venta. Requiere worker de colas activo.</p>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div v-show="tabActiva === 'sistema'" class="p-8">
                            <h2 class="text-lg font-black text-slate-800 uppercase tracking-widest mb-6 border-b border-slate-100 pb-2">Reglas de Negocio</h2>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Límite de Fiado por Defecto</label>
                                    <div class="relative">
                                        <span class="absolute left-4 top-2.5 font-black text-slate-400">$</span>
                                        <input v-model="form.limite_fiado_defecto" type="number" step="100" class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-8 pr-4 py-2.5 focus:ring-sky-500 focus:border-sky-500 font-medium text-slate-800">
                                    </div>
                                    <p class="text-[10px] text-slate-400 font-bold mt-1 uppercase">Monto inicial para nuevos clientes</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Símbolo de Moneda</label>
                                    <select v-model="form.moneda_defecto" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 focus:border-sky-500 font-medium text-slate-800">
                                        <option value="ARS">Pesos Argentinos ($ ARS)</option>
                                        <option value="USD">Dólares ($ USD)</option>
                                        <option value="EUR">Euros (€ EUR)</option>
                                    </select>
                                </div>

                                <div class="col-span-full mt-6 p-4 bg-sky-50/50 border border-sky-100 rounded-2xl">
                                    <h3 class="text-sm font-black text-sky-800 uppercase tracking-widest mb-4">Tienda Online y Delivery</h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Costo Delivery por Defecto</label>
                                            <div class="relative">
                                                <span class="absolute left-4 top-2.5 font-black text-slate-400">$</span>
                                                <input v-model="form.costo_delivery_defecto" type="number" step="0.01" class="w-full bg-white border border-slate-200 rounded-xl pl-8 pr-4 py-2.5 focus:ring-sky-500 font-medium text-slate-800">
                                            </div>
                                            <p class="text-[10px] text-slate-400 font-bold mt-1 uppercase">Tarifa base para todos tus locales.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <h3 class="text-md font-black text-rose-800 uppercase tracking-widest mt-10 mb-4 border-b border-rose-100 pb-2">Gestión de Cobranzas y Mora</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Días de Gracia</label>
                                    <input v-model="form.mora_dias_gracia" type="number" min="0" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 focus:border-sky-500 font-medium text-slate-800">
                                    <p class="text-[10px] text-slate-400 font-bold mt-1 uppercase">Días de tolerancia antes de castigar la deuda.</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Interés por Mora Automática</label>
                                    <div class="relative">
                                        <input v-model="form.mora_tasa_interes" type="number" step="0.1" min="0" class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-4 pr-10 py-2.5 focus:ring-sky-500 focus:border-sky-500 font-medium text-slate-800">
                                        <span class="absolute right-4 top-2.5 font-black text-slate-400">%</span>
                                    </div>
                                    <p class="text-[10px] text-slate-400 font-bold mt-1 uppercase">Recargo aplicado por el sistema.</p>
                                </div>
                            </div>
                        </div>

                        <div v-show="tabActiva === 'tienda'" class="p-8">
                            <h2 class="text-lg font-black text-slate-800 uppercase tracking-widest mb-6 border-b border-slate-100 pb-2">Logística y Envíos</h2>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
                                <div>
                                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Precio Base de Envío</label>
                                    <div class="relative">
                                        <span class="absolute left-4 top-2.5 font-black text-slate-400">$</span>
                                        <input v-model="form.envio_precio_base" type="number" step="0.01" class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-8 pr-4 py-2.5 focus:ring-sky-500 font-medium text-slate-800">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Precio por Km Extra</label>
                                    <div class="relative">
                                        <span class="absolute left-4 top-2.5 font-black text-slate-400">$</span>
                                        <input v-model="form.envio_precio_km" type="number" step="0.01" class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-8 pr-4 py-2.5 focus:ring-sky-500 font-medium text-slate-800">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Radio Máximo (Km)</label>
                                    <input v-model="form.envio_radio_km" type="number" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 font-medium text-slate-800">
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
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                                <div class="p-4 bg-sky-50/50 border border-sky-100 rounded-2xl">
                                    <h3 class="text-sm font-black text-sky-800 uppercase mb-4">Transferencia Bancaria</h3>
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">CBU / CVU</label>
                                            <input v-model="form.transferencia_cbu" type="text" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2 text-sm font-medium text-slate-800">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Alias</label>
                                            <input v-model="form.transferencia_alias" type="text" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2 text-sm font-medium text-slate-800">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Titular de la Cuenta</label>
                                            <input v-model="form.transferencia_titular" type="text" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2 text-sm font-medium text-slate-800">
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
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Access Token (Producción)</label>
                                        <input v-model="form.mp_access_token" type="password" placeholder="APP_USR-..." class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2 text-sm font-mono text-slate-800">
                                        <p class="text-[10px] text-slate-500 mt-2 font-bold">Requerido para cobrar online desde el catálogo web.</p>
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
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Client ID</label>
                                            <input v-model="form.viumi_client_id" type="text" placeholder="XXXXXXXX-XXX-XXXX-XXX-XXXXXXXXXXXX" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2 text-sm font-mono text-slate-800">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Client Secret</label>
                                            <input v-model="form.viumi_client_secret" type="password" placeholder="Completar solo si se desea cambiar" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2 text-sm font-mono text-slate-800">
                                            <p class="text-[10px] text-slate-500 mt-1 font-bold">No se muestra por seguridad. Dejá vacío para mantener el actual.</p>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Entorno</label>
                                            <select v-model="form.viumi_environment" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2 text-sm font-medium text-slate-800">
                                                <option value="sandbox">Sandbox (Pruebas)</option>
                                                <option value="production">Producción</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

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
                                        <button @click="editPmc(pmc)" class="px-3 py-1.5 text-xs font-bold text-sky-600 bg-sky-50 hover:bg-sky-100 rounded-lg transition-colors">Editar</button>
                                        <button @click="eliminarPmc(pmc)" class="px-3 py-1.5 text-xs font-bold text-rose-500 bg-rose-50 hover:bg-rose-100 rounded-lg transition-colors">✕</button>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-slate-50 border border-slate-200 rounded-2xl p-6">
                                <h3 class="text-sm font-black text-slate-700 uppercase tracking-widest mb-4">{{ editingPmcId ? 'Editar' : 'Nuevo' }} método de pago</h3>
                                <form @submit.prevent="guardarPmc" class="space-y-4">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Método de Pago</label>
                                            <select v-model="pmcForm.metodo_pago" :disabled="!!editingPmcId"
                                                class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 focus:border-sky-500 font-medium text-slate-800"
                                            >
                                                <option v-for="opt in metodoPagoOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Provider (opcional)</label>
                                            <input v-model="pmcForm.provider" type="text" placeholder="mercadopago, viumi..." class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 font-medium text-slate-800">
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Alias</label>
                                            <input v-model="pmcForm.display_data.alias" type="text" placeholder="ej: mercopago.pago.facil" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 font-medium text-slate-800">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">CVU / CBU</label>
                                            <input v-model="pmcForm.display_data.cvu" type="text" placeholder="0000003100000000000000" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 font-medium text-slate-800">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Banco</label>
                                            <input v-model="pmcForm.display_data.banco" type="text" placeholder="Banco Ejemplo" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 font-medium text-slate-800">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Titular</label>
                                            <input v-model="pmcForm.display_data.titular" type="text" placeholder="Nombre del titular" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 font-medium text-slate-800">
                                        </div>
                                    </div>

                                    <label class="flex items-center gap-3 p-3 bg-white rounded-xl border border-slate-200 cursor-pointer">
                                        <input v-model="pmcForm.enabled" type="checkbox" class="w-5 h-5 text-sky-600 rounded focus:ring-sky-500 border-slate-300">
                                        <span class="font-bold text-slate-700 text-sm">Habilitado</span>
                                    </label>

                                    <div class="flex gap-3 pt-2">
                                        <button type="submit" :disabled="pmcForm.processing"
                                            class="bg-sky-600 hover:bg-sky-700 disabled:bg-slate-300 text-white font-black py-2.5 px-6 rounded-xl shadow-lg uppercase tracking-widest transition-all text-sm"
                                        >
                                            {{ pmcForm.processing ? 'Guardando...' : 'Guardar' }}
                                        </button>
                                        <button v-if="editingPmcId" type="button" @click="resetPmcForm"
                                            class="px-4 py-2.5 border border-slate-200 rounded-xl text-slate-600 font-bold text-sm hover:bg-slate-50 transition-colors"
                                        >
                                            Cancelar
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <form @submit.prevent="guardarConfiguracion" class="p-6 bg-slate-50 border-t border-slate-100 flex justify-end gap-3">
                            <button type="submit" :disabled="form.processing" class="bg-slate-900 hover:bg-sky-600 disabled:bg-slate-300 text-white font-black py-3 px-8 rounded-xl shadow-lg uppercase tracking-widest transition-all active:scale-95 flex items-center gap-2">
                                <span v-if="form.processing">Guardando...</span>
                                <span v-else>Guardar Ajustes</span>
                            </button>
                        </form>
                        
                    </div>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>