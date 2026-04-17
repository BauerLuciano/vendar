<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    configuraciones: Object
});

const page = usePage();
const tabActiva = ref('general'); // Pestañas: 'general', 'pos', 'sistema'
const logoPreview = ref(props.configuraciones.logo_empresa ? '/storage/' + props.configuraciones.logo_empresa : null);

// Inicializamos el formulario con los datos que trajo el "pluck" del controlador
const form = useForm({
    nombre_empresa: props.configuraciones.nombre_empresa || '',
    cuit: props.configuraciones.cuit || '',
    telefono: props.configuraciones.telefono || '',
    direccion: props.configuraciones.direccion || '',
    ticket_mensaje_pie: props.configuraciones.ticket_mensaje_pie || '',
    formato_impresion: props.configuraciones.formato_impresion || '80mm',
    permitir_stock_negativo: props.configuraciones.permitir_stock_negativo === '1' || props.configuraciones.permitir_stock_negativo === true,
    limite_fiado_defecto: props.configuraciones.limite_fiado_defecto || 0,
    moneda_defecto: props.configuraciones.moneda_defecto || 'ARS',
    logo_empresa: null, // Acá va el archivo físico
});

// Manejar la previsualización de la imagen
const handleLogoUpload = (event) => {
    const file = event.target.files[0];
    if (file) {
        form.logo_empresa = file;
        logoPreview.value = URL.createObjectURL(file);
    }
};

const guardarConfiguracion = () => {
    // Transformamos los booleanos a '1' o '0' para la base de datos
    form.transform((data) => ({
        ...data,
        permitir_stock_negativo: data.permitir_stock_negativo ? '1' : '0'
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
                        </nav>
                    </div>
                </div>

                <div class="lg:w-3/4">
                    <form @submit.prevent="guardarConfiguracion" class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                        
                        <div v-show="tabActiva === 'general'" class="p-8">
                            <h2 class="text-lg font-black text-slate-800 uppercase tracking-widest mb-6 border-b border-slate-100 pb-2">Datos de Identidad</h2>
                            
                            <div class="flex flex-col md:flex-row gap-8 mb-6">
                                <div class="shrink-0 flex flex-col items-center gap-3">
                                    <div class="w-32 h-32 bg-slate-50 border-2 border-dashed border-slate-300 rounded-2xl overflow-hidden flex items-center justify-center relative group">
                                        <img v-if="logoPreview" :src="logoPreview" class="w-full h-full object-contain p-2" />
                                        <div v-else class="text-center p-4">
                                            <svg class="mx-auto h-8 w-8 text-slate-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true"><path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                        </div>
                                        <label class="absolute inset-0 w-full h-full bg-slate-900/50 flex items-center justify-center text-white font-bold opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer">
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
                            </div>
                        </div>

                        <div class="p-6 bg-slate-50 border-t border-slate-100 flex justify-end gap-3">
                            <button type="submit" :disabled="form.processing" class="bg-slate-900 hover:bg-sky-600 disabled:bg-slate-300 text-white font-black py-3 px-8 rounded-xl shadow-lg uppercase tracking-widest transition-all active:scale-95 flex items-center gap-2">
                                <span v-if="form.processing">Guardando...</span>
                                <span v-else>Guardar Ajustes</span>
                            </button>
                        </div>
                        
                    </form>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>