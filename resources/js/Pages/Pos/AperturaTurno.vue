<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Swal from 'sweetalert2';
import { useCajaDiaria } from '@/Composables/useCajaDiaria.js';

const { abrirCajaApi } = useCajaDiaria();

const props = defineProps({
    cajas: Array
});

// Usamos ref normal en lugar de useForm para tener más control con la API
const form = ref({
    caja_id: props.cajas && props.cajas.length > 0 ? props.cajas[0].id : '',
    saldo_inicial: ''
});

const processing = ref(false);

const abrirCaja = async () => {
    if (!form.value.caja_id) {
        Swal.fire({ icon: 'error', title: 'Error', text: 'Debes seleccionar una Caja', confirmButtonColor: '#0284c7' });
        return;
    }

    if (form.value.saldo_inicial === '' || form.value.saldo_inicial < 0) {
        Swal.fire({ icon: 'error', title: 'Monto Inválido', text: 'Debes ingresar el saldo inicial (puede ser 0)', confirmButtonColor: '#0284c7' });
        return;
    }

    processing.value = true;

    try {
        await abrirCajaApi({ caja_id: form.value.caja_id, saldo_inicial_efectivo: form.value.saldo_inicial });
        router.visit(route('pos.index'));
    } catch (error) {
        let errorMsg = 'Ocurrió un error al intentar abrir la caja.';
        if (error.response?.data?.error) {
            errorMsg = error.response.data.error;
        }
        Swal.fire({ icon: 'warning', title: 'No se pudo abrir', text: errorMsg, confirmButtonColor: '#0284c7' });
    } finally {
        processing.value = false;
    }
};
</script>

<template>
    <Head title="Apertura de Caja - VendAR" />
    
    <AuthenticatedLayout>
        <div class="max-w-4xl mx-auto py-12 px-4 sm:px-6 lg:px-8 h-[calc(100vh-4rem)] flex items-center justify-center">
            
            <div class="bg-white w-full rounded-3xl shadow-2xl overflow-hidden border border-slate-100 flex flex-col md:flex-row">
                
                <div class="bg-slate-900 w-full md:w-2/5 p-10 flex flex-col justify-between items-start text-white relative overflow-hidden">
                    <div class="absolute -right-20 -top-20 w-64 h-64 bg-sky-500/20 rounded-full blur-3xl"></div>
                    
                    <div class="z-10">
                        <span class="bg-sky-500 text-[10px] font-black px-3 py-1 rounded-full uppercase tracking-widest shadow-lg shadow-sky-500/30">Punto de Venta</span>
                        <h2 class="text-4xl font-black tracking-tighter mt-4 leading-none">Apertura<br><span class="text-sky-400">de Turno</span></h2>
                        <p class="text-slate-400 text-sm mt-4 font-medium">Iniciá tu jornada declarando el fondo de caja para habilitar el cobro a clientes y la gestión de movimientos.</p>
                    </div>

                    <div class="z-10 mt-12 md:mt-0 flex items-center gap-3">
                        <div class="w-10 h-10 bg-slate-800 rounded-full flex items-center justify-center border border-slate-700">
                            <span class="text-sky-400 font-bold">{{ $page.props.auth.user.name.charAt(0) }}</span>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Cajero Activo</p>
                            <p class="font-bold tracking-tight">{{ $page.props.auth.user.name }}</p>
                        </div>
                    </div>
                </div>

                <div class="w-full md:w-3/5 p-10 bg-slate-50">
                    <form @submit.prevent="abrirCaja" class="space-y-6 h-full flex flex-col justify-center">
                        
                        <div v-if="!cajas || cajas.length === 0" class="bg-rose-50 border border-rose-200 p-4 rounded-2xl flex items-start gap-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-rose-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                            <div>
                                <h4 class="font-bold text-rose-800">No hay cajas disponibles</h4>
                                <p class="text-xs text-rose-600 mt-1">El administrador debe crear una Caja para esta sucursal antes de poder operar.</p>
                            </div>
                        </div>

                        <div v-else class="space-y-6">
                            <div>
                                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Terminal / Caja</label>
                                <select 
                                    v-model="form.caja_id"
                                    class="w-full bg-white border-2 border-slate-200 text-slate-800 font-bold rounded-xl px-4 py-3 focus:ring-0 focus:border-sky-500 transition-colors"
                                >
                                    <option v-for="caja in cajas" :key="caja.id" :value="caja.id">
                                        {{ caja.nombre }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Fondo de Caja (Cambio Inicial)</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <span class="text-slate-400 font-bold text-xl">$</span>
                                    </div>
                                    <input 
                                        type="number" 
                                        v-model="form.saldo_inicial"
                                        step="0.01"
                                        min="0"
                                        placeholder="0.00"
                                        class="w-full bg-white border-2 border-slate-200 text-slate-800 font-black text-2xl rounded-xl pl-10 pr-4 py-4 focus:ring-0 focus:border-sky-500 transition-colors placeholder:text-slate-300"
                                    >
                                </div>
                                <p class="text-[10px] text-slate-400 font-medium mt-2">Ingresá el efectivo con el que contás en el cajón para dar vuelto.</p>
                            </div>

                            <button 
                                type="submit" 
                                :disabled="processing"
                                class="w-full bg-sky-600 hover:bg-sky-500 text-white font-black uppercase tracking-widest py-4 rounded-xl shadow-lg shadow-sky-600/30 transition-all disabled:opacity-50 disabled:cursor-not-allowed mt-4"
                            >
                                <span v-if="processing">Abriendo Turno...</span>
                                <span v-else>Iniciar Jornada</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>