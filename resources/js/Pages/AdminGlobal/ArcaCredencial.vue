<script setup>
import GlobalAdminLayout from '@/Layouts/GlobalAdminLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    configurada: Boolean,
    cuit: String,
});

const page = usePage();
const flash = computed(() => page.props.flash || {});
const errors = computed(() => page.props.errors || {});

const form = useForm({
    cuit: props.cuit || '',
    token: '',
    sign: '',
});

const cuitError = ref('');
const mostrarToken = ref(false);
const mostrarSign = ref(false);

const normalizarCuit = (value) => value.replace(/\D/g, '').slice(0, 11);

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

const validarCuit = () => {
    form.cuit = normalizarCuit(form.cuit);
    if (form.cuit.length !== 11) {
        cuitError.value = 'El CUIT debe tener 11 dígitos.';
        return false;
    }
    if (!esCuitValido(form.cuit)) {
        cuitError.value = 'El CUIT no tiene un dígito verificador válido.';
        return false;
    }
    cuitError.value = '';
    return true;
};

const guardar = () => {
    if (!validarCuit()) return;
    if (!form.token || !form.sign) {
        Swal.fire('Completá los campos', 'Token y Sign son obligatorios.', 'warning');
        return;
    }

    form.post(route('admin.arca.credencial.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('token', 'sign');
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Credencial guardada', showConfirmButton: false, timer: 2000 });
        },
        onError: (err) => {
            Swal.fire('Error', Object.values(err).join('\n') || 'No se pudo guardar la credencial.', 'error');
        },
    });
};
</script>

<template>
    <Head title="Credencial Padrón ARCA" />

    <GlobalAdminLayout>
        <div class="py-8 px-6 max-w-3xl mx-auto">
            <div class="mb-8">
                <h1 class="text-2xl font-bold text-slate-900">Credencial de plataforma — Padrón ARCA</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Configurá la credencial WSAA de VendAR que se usa para consultar el padrón de contribuyentes de ARCA.
                </p>
            </div>

            <div v-if="flash.success" class="mb-6 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm font-bold text-emerald-700">
                {{ flash.success }}
            </div>

            <div class="bg-white rounded-2xl ring-1 ring-slate-900/5 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h2 class="font-black text-slate-800">Estado de la credencial</h2>
                        <p class="text-xs text-slate-500 font-medium mt-0.5">Solo consulta el padrón; nunca emite comprobantes.</p>
                    </div>
                    <span v-if="configurada" class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20 px-2.5 py-1 text-xs font-black">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Configurada
                    </span>
                    <span v-else class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/20 px-2.5 py-1 text-xs font-black">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> No configurada
                    </span>
                </div>

                <div v-if="configurada" class="px-6 py-4 bg-slate-50 border-b border-slate-100 text-sm">
                    <span class="text-slate-500 font-medium">CUIT de la plataforma:</span>
                    <span class="ml-2 font-black text-slate-800 tracking-widest">{{ cuit }}</span>
                    <span class="ml-2 text-[11px] text-slate-400 font-medium">(parcialmente oculto)</span>
                </div>

                <form @submit.prevent="guardar" class="p-6 space-y-5">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">CUIT de la plataforma (VendAR)</label>
                        <input v-model="form.cuit" @input="form.cuit = normalizarCuit(form.cuit)" @blur="validarCuit"
                            type="text" inputmode="numeric" maxlength="11" placeholder="20123456786"
                            class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                        <p v-if="cuitError" class="text-xs text-red-600 font-semibold mt-1">{{ cuitError }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Token WSAA</label>
                        <div class="relative">
                            <input v-model="form.token" :type="mostrarToken ? 'text' : 'password'" autocomplete="off"
                                placeholder="Token del TRA de ARCA"
                                class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm pr-24" />
                            <button type="button" @click="mostrarToken = !mostrarToken"
                                class="absolute inset-y-0 right-2 my-auto h-8 px-3 text-xs font-bold text-slate-500 hover:text-indigo-600 uppercase tracking-widest">
                                {{ mostrarToken ? 'Ocultar' : 'Ver' }}
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Sign WSAA</label>
                        <div class="relative">
                            <input v-model="form.sign" :type="mostrarSign ? 'text' : 'password'" autocomplete="off"
                                placeholder="Sign del TRA de ARCA"
                                class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm pr-24" />
                            <button type="button" @click="mostrarSign = !mostrarSign"
                                class="absolute inset-y-0 right-2 my-auto h-8 px-3 text-xs font-bold text-slate-500 hover:text-indigo-600 uppercase tracking-widest">
                                {{ mostrarSign ? 'Ocultar' : 'Ver' }}
                            </button>
                        </div>
                    </div>

                    <p class="text-[11px] text-slate-400 font-medium">
                        Los valores se cifran con la clave de aplicación y nunca se exponen a los comercios ni a la interfaz.
                    </p>

                    <p v-if="errors.credencial" class="text-xs text-red-600 font-semibold">{{ errors.credencial }}</p>

                    <div class="flex justify-end gap-3 pt-2 border-t border-slate-100">
                        <button type="submit" :disabled="form.processing"
                            class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-indigo-500 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg v-if="form.processing" class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                            {{ form.processing ? 'Guardando…' : (configurada ? 'Actualizar credencial' : 'Guardar credencial') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </GlobalAdminLayout>
</template>
