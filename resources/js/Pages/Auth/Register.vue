<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import axios from 'axios';

const props = defineProps({
    tienda_slug: String,
    planes: Array,
});

const showPassword = ref(false);
const showConfirmacion = ref(false);

const form = useForm({
    name: '',
    nombre_comercio: '',
    email: '',
    password: '',
    password_confirmation: '',
    plan_deseado: '',
    telefono: '',
    direccion: '',
    latitud: null,
    longitud: null,
});

const modulosLabels = {
    pos: 'Punto de Venta Base',
    proveedores: 'Gestión de Proveedores y Compras',
    lotes: 'Gestión de Stock Avanzada (Lotes)',
    fiados: 'Cuentas Corrientes (Fiados)',
    auditoria: 'Auditoría de Caja y Stock',
    transferencias: 'Optimización de Stock (Sugerencias)',
    pedidos_web: 'Tienda Online y Pedidos Web',
    promociones: 'Promociones y Ofertas',
};

const planInfo = computed(() => props.planes?.find((p) => p.nombre === form.plan_deseado) ?? null);

const modulosActivos = computed(() => {
    if (!planInfo.value?.modulos) return [];
    return Object.entries(planInfo.value.modulos)
        .filter(([, activo]) => activo)
        .map(([id]) => modulosLabels[id] ?? id);
});

const diasTrial = computed(() => {
    const plan = planInfo.value;
    return plan?.trial_activo && plan?.trial_dias > 0 ? plan.trial_dias : null;
});

const textoTrial = computed(() => {
    if (props.tienda_slug) return null;
    if (diasTrial.value) return `Empezá con ${diasTrial.value} días de prueba gratis`;
    if (planInfo.value) return 'Este plan no incluye prueba gratis';
    return 'Empezá con días de prueba gratis según el plan que elijas';
});

const formatPrecio = (n) => (n != null ? `$ ${new Intl.NumberFormat('es-AR').format(n)}` : null);

const textoLocales = computed(() => {
    const n = planInfo.value?.sucursales_limit;
    if (n == null) return null;
    return n === 1 ? '1 local' : `Hasta ${n} locales`;
});

const textoUsuarios = computed(() => {
    const n = planInfo.value?.usuarios_limit;
    if (n == null) return null;
    return n === 1 ? '1 usuario' : `Hasta ${n} usuarios`;
});

const passwordMinima = computed(() => form.password.length >= 8);

const confirmacionEscrita = computed(() => form.password_confirmation.length > 0);

const passwordsCoinciden = computed(
    () => confirmacionEscrita.value && form.password === form.password_confirmation,
);

const submit = () => {
    form.post(route('register', { tienda: props.tienda_slug }), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};

// --- Autocomplete de Dirección (Nominatim, igual que en Sucursales) ---
const sugerenciasDireccion = ref([]);
const buscandoSugerencias = ref(false);
const mostrarSugerencias = ref(false);
let timerSugerencias = null;

const armarDireccion = (addr) => {
    const calle = addr.road || addr.pedestrian || addr.path || addr.cycleway || addr.suburb || 'Calle s/n';
    const altura = addr.house_number || '';
    const ciudad = addr.city || addr.town || addr.village || '';

    let direccionFinal = calle;
    if (altura) direccionFinal += ` ${altura}`;
    if (ciudad) direccionFinal += `, ${ciudad}`;
    return direccionFinal;
};

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
                params: { q: texto.trim(), format: 'jsonv2', addressdetails: 1, limit: 5 }
            });
            sugerenciasDireccion.value = response.data || [];
            mostrarSugerencias.value = true;
        } catch (error) {
            sugerenciasDireccion.value = [];
            console.error('Error buscando dirección:', error);
        } finally {
            buscandoSugerencias.value = false;
        }
    }, 500);
};

const seleccionarDireccion = (s) => {
    form.direccion = armarDireccion(s.address || {});
    form.latitud = Number(s.lat);
    form.longitud = Number(s.lon);
    cerrarSugerencias();
};

const cerrarSugerencias = () => {
    clearTimeout(timerSugerencias);
    sugerenciasDireccion.value = [];
    mostrarSugerencias.value = false;
};

const ocultarSugerenciasConDelay = () => setTimeout(cerrarSugerencias, 150);

const normalizarTelefono = () => {
    form.telefono = (form.telefono || '').replace(/\D/g, '').slice(0, 15);
};
</script>

<template>
    <Head :title="tienda_slug ? 'Crear Cuenta | VendAR' : 'Creá tu Cuenta | VendAR'" />

    <div class="min-h-screen bg-[#0b1221] font-sans text-slate-300 relative overflow-hidden">

        <div class="absolute inset-0 flex justify-center items-center z-0 select-none pointer-events-none">
            <img src="/img/LogoVendar-Sidebar.png" alt="" class="w-[130%] max-w-none opacity-[0.06] blur-sm">
        </div>
        <div class="absolute w-[600px] h-[600px] bg-sky-500/10 rounded-full blur-[150px] -top-32 -left-32 z-0 pointer-events-none"></div>
        <div class="absolute w-[600px] h-[600px] bg-orange-500/10 rounded-full blur-[150px] -bottom-32 -right-32 z-0 pointer-events-none"></div>

        <div class="relative z-10 flex flex-col items-center px-4 py-10 sm:py-14">
            <div class="w-full max-w-3xl">

                <a href="/" class="self-start inline-flex items-center gap-2 text-sky-400 font-bold hover:text-sky-300 transition-colors text-xs uppercase tracking-widest mb-8 group">
                    <svg class="w-4 h-4 transition-transform group-hover:-translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
                    Volver al inicio
                </a>

                <div class="flex justify-center mb-8">
                    <img src="/img/LogoVendar-Sidebar.png" alt="VendAR Logo" class="w-52 h-auto object-contain drop-shadow-[0_0_25px_rgba(255,255,255,0.1)]">
                </div>

                <div class="mb-8 text-center">
                    <h1 class="text-[2rem] font-bold text-white tracking-tight mb-2">
                        <template v-if="tienda_slug">Crear Cuenta</template>
                        <template v-else>Creá tu Cuenta</template>
                        <span> Vend</span><span class="text-orange-500">AR</span>
                    </h1>
                    <p class="text-slate-400 text-[15px]">
                        {{ tienda_slug ? 'Para comprar en esta tienda' : 'Empezá a gestionar tu negocio en minutos.' }}
                    </p>
                </div>

                <div class="rounded-2xl border border-white/10 bg-[#0e172a]/80 backdrop-blur p-6 sm:p-8 shadow-2xl">
                    <form @submit.prevent="submit" class="space-y-8">

                        <section>
                            <h2 class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.3em] text-sky-400 mb-5">
                                <span class="h-1.5 w-1.5 rounded-full bg-sky-400"></span>
                                Datos de la Cuenta
                            </h2>
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 mb-2 ml-1">Nombre Completo</label>
                                    <input
                                        id="name"
                                        type="text"
                                        v-model="form.name"
                                        maxlength="255"
                                        @input="form.name = form.name.replace(/[0-9]/g, '')"
                                        class="w-full bg-[#111c30] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-[#00adef]/50 focus:ring-1 focus:ring-[#00adef]/20 transition-all placeholder-slate-600 shadow-inner"
                                        placeholder="Ej: Juan Pérez"
                                        required
                                        autofocus
                                    />
                                    <InputError class="mt-2" :message="form.errors.name" />
                                </div>

                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 mb-2 ml-1">Correo Electrónico</label>
                                    <input
                                        id="email"
                                        type="email"
                                        v-model="form.email"
                                        maxlength="255"
                                        class="w-full bg-[#111c30] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-[#00adef]/50 focus:ring-1 focus:ring-[#00adef]/20 transition-all placeholder-slate-600 shadow-inner"
                                        placeholder="tu@email.com"
                                        required
                                    />
                                    <InputError class="mt-2" :message="form.errors.email" />
                                </div>

                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 mb-2 ml-1">Contraseña</label>
                                    <div class="relative">
                                        <input
                                            id="password"
                                            :type="showPassword ? 'text' : 'password'"
                                            v-model="form.password"
                                            minlength="8"
                                            maxlength="255"
                                            class="w-full bg-[#111c30] border border-white/10 rounded-xl px-4 py-3 pr-12 text-sm text-white focus:outline-none focus:border-[#00adef]/50 focus:ring-1 focus:ring-[#00adef]/20 transition-all placeholder-slate-600 shadow-inner"
                                            placeholder="••••••••"
                                            required
                                        />
                                        <button
                                            type="button"
                                            @click="showPassword = !showPassword"
                                            class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-500 hover:text-[#00adef] transition-colors focus:outline-none"
                                        >
                                            <svg v-if="showPassword" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                                            <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                        </button>
                                    </div>
                                    <div v-if="form.password.length > 0" class="mt-2 flex items-center gap-2">
                                        <span
                                            class="text-[10px] font-black uppercase tracking-widest"
                                            :class="passwordMinima ? 'text-emerald-400' : 'text-red-400'"
                                        >
                                            {{ passwordMinima ? '✓ Mínimo 8 caracteres' : '✗ Mínimo 8 caracteres' }}
                                        </span>
                                    </div>
                                    <InputError class="mt-2" :message="form.errors.password" />
                                </div>

                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 mb-2 ml-1">Confirmar Contraseña</label>
                                    <div class="relative">
                                        <input
                                            id="password_confirmation"
                                            :type="showConfirmacion ? 'text' : 'password'"
                                            v-model="form.password_confirmation"
                                            minlength="8"
                                            maxlength="255"
                                            class="w-full bg-[#111c30] border border-white/10 rounded-xl px-4 py-3 pr-12 text-sm text-white focus:outline-none focus:border-[#00adef]/50 focus:ring-1 focus:ring-[#00adef]/20 transition-all placeholder-slate-600 shadow-inner"
                                            placeholder="••••••••"
                                            required
                                        />
                                        <button
                                            type="button"
                                            @click="showConfirmacion = !showConfirmacion"
                                            class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-500 hover:text-[#00adef] transition-colors focus:outline-none"
                                        >
                                            <svg v-if="showConfirmacion" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                                            <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                        </button>
                                    </div>
                                    <div v-if="confirmacionEscrita" class="mt-2 flex items-center gap-2">
                                        <span
                                            class="text-[10px] font-black uppercase tracking-widest"
                                            :class="passwordsCoinciden ? 'text-emerald-400' : 'text-red-400'"
                                        >
                                            {{ passwordsCoinciden ? '✓ Las contraseñas coinciden' : '✗ Las contraseñas no coinciden' }}
                                        </span>
                                    </div>
                                    <InputError class="mt-2" :message="form.errors.password_confirmation" />
                                </div>
                            </div>
                        </section>

                        <template v-if="!props.tienda_slug">
                            <section class="border-t border-white/10 pt-7">
                                <h2 class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.3em] text-sky-400 mb-5">
                                    <span class="h-1.5 w-1.5 rounded-full bg-sky-400"></span>
                                    Datos del Negocio
                                </h2>
                                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                    <div class="sm:col-span-2">
                                        <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 mb-2 ml-1">Nombre de tu Negocio</label>
                                        <input
                                            id="nombre_comercio"
                                            type="text"
                                            v-model="form.nombre_comercio"
                                            maxlength="255"
                                            class="w-full bg-[#111c30] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-[#00adef]/50 focus:ring-1 focus:ring-[#00adef]/20 transition-all placeholder-slate-600 shadow-inner"
                                            placeholder="Ej: Kiosco 24hs"
                                            required
                                        />
                                        <InputError class="mt-2" :message="form.errors.nombre_comercio" />
                                    </div>

                                    <div class="sm:col-span-2">
                                        <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 mb-2 ml-1">Teléfono del Negocio</label>
                                        <input
                                            id="telefono"
                                            type="tel"
                                            v-model="form.telefono"
                                            maxlength="15"
                                            @input="normalizarTelefono"
                                            class="w-full bg-[#111c30] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-[#00adef]/50 focus:ring-1 focus:ring-[#00adef]/20 transition-all placeholder-slate-600 shadow-inner"
                                            placeholder="Ej: 3755550471"
                                            required
                                        />
                                        <InputError class="mt-2" :message="form.errors.telefono" />
                                    </div>

                                    <div class="relative sm:col-span-2">
                                        <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 mb-2 ml-1">Dirección del Negocio</label>
                                        <input
                                            id="direccion"
                                            type="text"
                                            v-model="form.direccion"
                                            maxlength="255"
                                            @input="buscarDireccion(form.direccion)"
                                            @focus="form.direccion.trim().length >= 3 && buscarDireccion(form.direccion)"
                                            @blur="ocultarSugerenciasConDelay"
                                            class="w-full bg-[#111c30] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-[#00adef]/50 focus:ring-1 focus:ring-[#00adef]/20 transition-all placeholder-slate-600 shadow-inner"
                                            placeholder="Buscá tu dirección..."
                                            required
                                            autocomplete="off"
                                        />
                                        <InputError class="mt-2" :message="form.errors.direccion" />

                                        <div v-if="mostrarSugerencias" class="absolute left-0 right-0 top-full mt-1 bg-[#111c30] border border-white/10 rounded-xl overflow-hidden z-50 shadow-2xl">
                                            <div v-if="buscandoSugerencias" class="px-4 py-3 text-xs text-slate-400">Buscando...</div>
                                            <button
                                                v-for="s in sugerenciasDireccion"
                                                :key="s.place_id"
                                                type="button"
                                                @mousedown.prevent="seleccionarDireccion(s)"
                                                class="w-full text-left px-4 py-3 text-sm text-slate-300 hover:bg-[#00adef]/10 hover:text-white transition-colors border-b border-white/5 last:border-0"
                                            >
                                                {{ s.display_name }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section class="border-t border-white/10 pt-7">
                                <h2 class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.3em] text-sky-400 mb-5">
                                    <span class="h-1.5 w-1.5 rounded-full bg-sky-400"></span>
                                    Elegí tu Plan
                                </h2>
                                <div class="space-y-5">
                                    <div>
                                        <select
                                            id="plan_deseado"
                                            v-model="form.plan_deseado"
                                            class="w-full bg-[#111c30] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-[#00adef]/50 focus:ring-1 focus:ring-[#00adef]/20 transition-all shadow-inner appearance-none"
                                            required
                                        >
                                            <option value="" disabled selected>Elegí tu plan...</option>
                                            <option v-for="plan in props.planes" :key="plan.id" :value="plan.nombre">
                                                {{ plan.nombre }}
                                            </option>
                                        </select>
                                        <InputError class="mt-2" :message="form.errors.plan_deseado" />
                                    </div>

                                    <div v-if="planInfo" class="rounded-xl border border-sky-500/20 bg-sky-500/10 p-5">
                                        <div class="flex items-center gap-2 mb-3">
                                            <span class="inline-flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.2em] text-sky-400 bg-sky-500/10 border border-sky-500/20 rounded-full px-3 py-1">
                                                {{ planInfo.nombre }}
                                            </span>
                                            <span v-if="planInfo.descripcion" class="text-[11px] text-slate-400 ml-auto">{{ planInfo.descripcion }}</span>
                                        </div>

                                        <div class="flex items-baseline gap-2 mb-3">
                                            <span v-if="formatPrecio(planInfo.precio_mensual)" class="text-3xl font-black text-white italic tracking-tight">
                                                {{ formatPrecio(planInfo.precio_mensual) }}
                                            </span>
                                            <span v-if="formatPrecio(planInfo.precio_mensual)" class="text-xs font-bold text-slate-500 uppercase tracking-widest">/mes</span>
                                        </div>

                                        <p v-if="diasTrial" class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/15 border border-emerald-500/30 px-3 py-1 text-[11px] font-bold text-emerald-400 mb-4">
                                            🎁 {{ diasTrial }} días de prueba gratis
                                        </p>
                                        <p v-else-if="planInfo" class="inline-flex items-center rounded-full bg-slate-500/10 border border-white/10 px-3 py-1 text-[11px] font-bold text-slate-400 mb-4">
                                            Este plan no incluye prueba gratis
                                        </p>

                                        <div class="flex flex-wrap gap-x-5 gap-y-2 text-[12px] text-slate-300">
                                            <span v-if="planInfo.sucursales_limit != null">
                                                <span class="text-slate-500 font-bold">Locales:</span> <span class="text-white font-bold">{{ textoLocales }}</span>
                                            </span>
                                            <span v-if="planInfo.usuarios_limit != null">
                                                <span class="text-slate-500 font-bold">Usuarios:</span> <span class="text-white font-bold">{{ textoUsuarios }}</span>
                                            </span>
                                        </div>

                                        <div v-if="modulosActivos.length" class="mt-4 pt-4 border-t border-white/10">
                                            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 mb-2.5">Incluye</p>
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                                <div v-for="mod in modulosActivos" :key="mod" class="flex items-center gap-2.5 text-[12px] text-slate-300">
                                                    <svg class="w-4 h-4 shrink-0 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                                    {{ mod }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </template>

                        <div class="pt-2">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="w-full bg-[#00adef] hover:bg-[#00adef]/90 text-white font-black py-4 rounded-xl text-xs uppercase tracking-[0.2em] shadow-2xl shadow-[#00adef]/20 active:scale-95 transition-all disabled:opacity-50"
                            >
                                {{ props.tienda_slug ? 'Crear Cuenta' : 'Crear Cuenta y Empezar' }}
                            </button>
                            <div v-if="!props.tienda_slug" class="mt-4 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-center">
                                <p class="text-sm text-slate-300 leading-relaxed">
                                    Después de crear tu cuenta te enviaremos un email para verificarla.
                                </p>
                                <p v-if="textoTrial" class="mt-1.5 text-[13px] font-black uppercase tracking-wide text-emerald-400 leading-snug">
                                    {{ textoTrial }}
                                </p>
                            </div>
                        </div>

                        <div class="text-center">
                            <Link
                                :href="route('login')"
                                class="text-[10px] font-black uppercase tracking-widest text-slate-500 hover:text-white transition-colors"
                            >
                                ¿Ya tenés cuenta? <span class="text-[#00adef]">Ingresar</span>
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
input:-webkit-autofill,
input:-webkit-autofill:hover,
input:-webkit-autofill:focus {
  -webkit-text-fill-color: white;
  -webkit-box-shadow: 0 0 0px 1000px #111c30 inset;
  transition: background-color 5000s ease-in-out 0s;
}
</style>
