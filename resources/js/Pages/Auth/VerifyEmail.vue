<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <Head title="Verificá tu Email | VendAR" />

    <div class="min-h-screen flex flex-col sm:flex-row bg-[#0b1221] font-sans text-slate-300">

        <div class="hidden sm:flex sm:w-[55%] relative justify-center items-center overflow-hidden bg-[#0b1221]">

            <div class="absolute inset-0 flex justify-center items-center z-0 select-none pointer-events-none">
                <img src="/img/LogoVendar-Sidebar.png" alt="" class="w-[130%] max-w-none opacity-[0.12] blur-sm">
            </div>

            <div class="absolute w-[600px] h-[600px] bg-sky-500/10 rounded-full blur-[150px] -top-32 -left-32 z-0 pointer-events-none"></div>
            <div class="absolute w-[600px] h-[600px] bg-orange-500/10 rounded-full blur-[150px] -bottom-32 -right-32 z-0 pointer-events-none"></div>

            <div class="relative z-10 p-[1px] rounded-[2rem] bg-gradient-to-br from-sky-400/50 via-white/5 to-orange-500/50 shadow-2xl">
                <div class="bg-[#0b1221]/60 backdrop-blur-2xl rounded-[2rem] p-16 flex flex-col items-center text-center w-[450px] border border-white/5">

                    <img src="/img/LogoVendar-Sidebar.png" alt="VendAR Logo" class="w-64 h-auto object-contain mb-10 drop-shadow-[0_0_25px_rgba(255,255,255,0.1)] transition-transform hover:scale-105 duration-700">

                    <h2 class="text-[15px] font-bold text-slate-200 tracking-[0.2em] uppercase leading-relaxed">
                        Solución de gestión y<br>reposición inteligente...
                    </h2>
                </div>
            </div>
        </div>

        <div class="w-full sm:w-[45%] flex flex-col justify-between items-center p-8 sm:p-16 bg-[#0e172a] z-10 relative border-l border-slate-800/50 shadow-[-20px_0_50px_-15px_rgba(0,0,0,0.3)]">

            <a href="/" class="self-start inline-flex items-center gap-2 text-sky-400 font-bold hover:text-sky-300 transition-colors text-xs uppercase tracking-widest mb-4 sm:mb-0 group">
                <svg class="w-4 h-4 transition-transform group-hover:-translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
                Volver al inicio
            </a>

            <div class="w-full max-w-sm flex-1 flex flex-col justify-center">

                <div class="sm:hidden flex justify-center mb-12">
                    <img src="/img/LogoVendar-Sidebar.png" alt="VendAR Logo" class="w-56 h-auto object-contain">
                </div>

                <div class="mb-8 text-center">
                    <div class="w-16 h-16 mx-auto mb-5 rounded-2xl bg-sky-500/15 border border-sky-500/25 flex items-center justify-center shadow-[0_0_35px_rgba(0,173,239,0.2)]">
                        <svg class="w-8 h-8 text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 6.75l9 5.55 9-5.55" />
                        </svg>
                    </div>
                    <h1 class="text-[1.6rem] font-bold text-white tracking-tight mb-2">
                        Verificá tu <span>correo</span><span class="text-sky-400"> electrónico</span>
                    </h1>
                    <p class="text-slate-400 text-[14px] leading-relaxed">
                        Te enviamos un enlace de verificación. Hacé clic en el botón del email para activar tu cuenta.
                    </p>
                </div>

                <div v-if="verificationLinkSent"
                    class="w-full flex items-center gap-3 bg-emerald-500/10 border border-emerald-400/30 rounded-xl px-4 py-3.5 mb-6">
                    <svg class="w-5 h-5 shrink-0 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="text-sm font-bold text-emerald-300 leading-relaxed">
                        ¡Listo! Te enviamos un nuevo enlace de verificación.
                    </p>
                </div>

                <div class="w-full rounded-xl bg-slate-800/40 border border-slate-700/50 px-4 py-3.5 mb-6">
                    <p class="text-[12px] text-slate-400 leading-relaxed">
                        <span class="text-slate-300 font-bold">¿No recibiste el email?</span> Revisá la carpeta de <span class="text-slate-300 font-bold">spam/promociones</span> o pedí que te lo reenviemos.
                    </p>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full flex justify-center items-center gap-3 py-4 px-4 text-[13px] font-black uppercase tracking-[0.2em] text-white bg-[#00adef] hover:bg-[#00adef]/90 rounded-xl shadow-2xl shadow-[#00adef]/20 active:scale-95 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg v-if="form.processing" class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <span>{{ form.processing ? 'Enviando...' : 'Reenviar email de verificación' }}</span>
                        <svg v-if="!form.processing" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"></path></svg>
                    </button>

                    <div class="text-center">
                        <Link
                            :href="route('logout')"
                            method="post"
                            as="button"
                            class="text-[11px] font-bold uppercase tracking-widest text-slate-500 hover:text-white transition-colors"
                        >
                            Cerrar sesión
                        </Link>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
