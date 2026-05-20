<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
    comercio: Object,
    tienda_slug: String,
});

const email = ref('');
const password = ref('');
const recordar = ref(false);
const enviando = ref(false);
const errorMsg = ref('');

const ingresar = async () => {
    enviando.value = true;
    errorMsg.value = '';
    try {
        const res = await axios.post('/api/tienda/login', {
            email: email.value,
            password: password.value,
            remember: recordar.value,
        });
        if (res.data.consumidor) {
            window.location.href = '/tienda/' + props.tienda_slug;
        }
    } catch (error) {
        const msg = error.response?.data?.error || 'Error al iniciar sesión';
        errorMsg.value = msg;
    } finally {
        enviando.value = false;
    }
};
</script>

<template>
    <Head :title="`Ingresar | ${comercio?.nombre || 'VendAR'}`" />

    <div class="min-h-screen bg-[#080f1e] font-sans flex flex-col relative overflow-hidden">
        <!-- Background effects -->
        <div class="fixed inset-0 pointer-events-none z-0">
            <div class="absolute w-[600px] h-[600px] bg-[#00adef]/6 rounded-full blur-[160px] -top-40 -right-40"></div>
            <div class="absolute w-[500px] h-[500px] bg-[#f7941e]/5 rounded-full blur-[140px] -bottom-40 -left-40"></div>
            <div class="absolute w-[300px] h-[300px] bg-[#8cc63f]/4 rounded-full blur-[120px] top-1/2 left-1/3"></div>
            <div class="absolute inset-0" style="background-image: linear-gradient(rgba(0,173,239,0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(0,173,239,0.03) 1px, transparent 1px); background-size: 40px 40px;"></div>
        </div>

        <div class="relative z-10 flex-1 flex items-center justify-center p-4">
            <div class="w-full max-w-md">
                <!-- Logo -->
                <div class="text-center mb-8">
                    <img src="/img/LogoVendar-Sidebar.png" alt="VendAR" class="h-12 mx-auto mb-3">
                    <h1 class="text-xl font-black text-white tracking-tight">{{ comercio?.nombre }}</h1>
                    <p class="text-xs text-slate-500 mt-1">Ingresá para hacer tu pedido</p>
                </div>

                <!-- Card -->
                <div class="bg-[#0f1929] border border-white/8 rounded-3xl p-8 shadow-2xl shadow-black/50">
                    <form @submit.prevent="ingresar" class="space-y-5">
                        <!-- Error -->
                        <div v-if="errorMsg" class="bg-rose-500/10 border border-rose-500/30 rounded-xl p-3 flex items-center gap-2.5">
                            <svg class="w-4 h-4 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                            <p class="text-xs font-bold text-rose-400">{{ errorMsg }}</p>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5">Email</label>
                            <input
                                v-model="email"
                                type="email"
                                required
                                autocomplete="email"
                                placeholder="tu@email.com"
                                class="w-full bg-[#080f1e] border border-white/8 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-[#00adef]/50 focus:ring-2 focus:ring-[#00adef]/20 transition-all"
                            >
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5">Contraseña</label>
                            <input
                                v-model="password"
                                type="password"
                                required
                                autocomplete="current-password"
                                placeholder="••••••••"
                                class="w-full bg-[#080f1e] border border-white/8 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-[#00adef]/50 focus:ring-2 focus:ring-[#00adef]/20 transition-all"
                            >
                        </div>

                        <div class="flex items-center gap-2">
                            <input
                                v-model="recordar"
                                type="checkbox"
                                id="recordar"
                                class="w-4 h-4 rounded border-white/10 bg-[#080f1e] text-[#00adef] focus:ring-[#00adef]/30"
                            >
                            <label for="recordar" class="text-xs text-slate-400">Recordarme</label>
                        </div>

                        <button
                            type="submit"
                            :disabled="enviando"
                            class="w-full bg-[#00adef] hover:bg-[#00adef]/80 disabled:bg-slate-700 disabled:text-slate-500 text-white font-black py-3 rounded-xl text-xs uppercase tracking-widest shadow-lg shadow-[#00adef]/20 transition-all active:scale-[0.98]"
                        >
                            <span v-if="enviando" class="inline-flex items-center gap-2">
                                <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                Ingresando...
                            </span>
                            <span v-else>Ingresar</span>
                        </button>
                    </form>

                    <div class="mt-6 pt-5 border-t border-white/5 text-center">
                        <p class="text-xs text-slate-500">
                            ¿No tenés cuenta?
                            <Link :href="'/tienda/' + tienda_slug + '/register'" class="text-[#00adef] font-bold hover:text-white transition-colors">Registrate</Link>
                        </p>
                        <Link :href="'/tienda/' + tienda_slug" class="inline-block mt-4 text-[10px] font-bold text-slate-500 hover:text-slate-300 transition-colors">
                            ← Volver a la tienda
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
