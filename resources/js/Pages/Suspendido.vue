<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AlertaAyuda from '@/Components/AlertaAyuda.vue';

const page = usePage();
const estadoCuenta = computed(() => page.props.estadoCuenta ?? null);

const vencimiento = computed(() => estadoCuenta.value?.vencimiento ?? null);
const diasMora = computed(() => estadoCuenta.value?.dias_mora ?? null);
</script>

<template>
    <Head title="Cuenta Suspendida" />

    <div class="min-h-screen bg-slate-950 flex items-center justify-center p-4">
        <div class="max-w-md w-full bg-slate-900 border border-slate-800 rounded-2xl p-8 text-center shadow-2xl animate-in fade-in zoom-in duration-300">
            <div class="w-20 h-20 bg-rose-500/10 border border-rose-500/20 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>

            <h1 class="text-2xl font-black text-white uppercase tracking-wider mb-2">Servicio Suspendido</h1>
            <AlertaAyuda class="mb-6">
                Superaste los {{ diasMora ?? 'días de' }} de gracia luego del vencimiento
                <template v-if="vencimiento">({{ vencimiento }})</template>
                y tu cuenta fue bloqueada temporalmente hasta que regularices tu situación.
            </AlertaAyuda>

            <Link
                :href="route('suscripcion.mi-plan')"
                class="w-full bg-[#00adef] hover:bg-[#00adef]/90 text-white font-black py-3 rounded-xl uppercase tracking-widest text-xs mb-3 transition-colors shadow-lg shadow-[#00adef]/20 inline-block"
            >
                Pagar y Reactivar
            </Link>

            <div class="bg-slate-950 p-4 rounded-xl border border-slate-800/80 mb-6 text-left">
                <p class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-1">Contacto Comercial</p>
                <p class="text-sm font-bold text-sky-400">ventas@vendar.com.ar</p>
                <p class="text-sm font-bold text-emerald-400">+54 9 3755 123456</p>
            </div>

            <Link
                :href="route('logout')"
                method="post"
                as="button"
                class="w-full bg-rose-600 hover:bg-rose-700 text-white font-bold py-3 rounded-xl uppercase tracking-widest text-xs transition-colors shadow-lg shadow-rose-600/20"
            >
                Cerrar Sesión
            </Link>
        </div>
    </div>
</template>
