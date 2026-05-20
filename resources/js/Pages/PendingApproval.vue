<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const plan = computed(() => page.props.auth?.user?.plan_deseado || 'tu plan');
</script>

<template>
    <Head title="Solicitud Pendiente | VendAR" />

    <div class="min-h-screen bg-[#050a15] text-slate-200 font-sans flex flex-col justify-center items-center relative overflow-hidden">
        <div class="fixed inset-0 pointer-events-none overflow-hidden z-0">
            <div class="absolute w-[600px] h-[600px] bg-[#00adef]/10 rounded-full blur-[120px] -top-20 -left-20"></div>
            <div class="absolute w-[500px] h-[500px] bg-[#f7941e]/5 rounded-full blur-[120px] bottom-0 right-0"></div>
        </div>

        <div class="relative z-10 w-full max-w-md px-6 py-8 text-center">
            <Link href="/" class="inline-block mb-10">
                <img
                    src="/img/LogoVendar-Sidebar.png"
                    alt="VendAR"
                    class="h-28 w-auto mx-auto hover:scale-110 transition-transform duration-500 drop-shadow-[0_0_15px_rgba(0,172,239,0.3)]"
                >
            </Link>

            <div class="w-24 h-24 mx-auto mb-8 bg-[#f7941e]/10 border border-[#f7941e]/20 rounded-3xl flex items-center justify-center">
                <svg class="w-12 h-12 text-[#f7941e]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>

            <h1 class="text-2xl font-black text-white uppercase tracking-tighter italic mb-3">
                ¡Gracias por <span class="text-[#00adef]">registrarte</span>!
            </h1>

            <p class="text-slate-400 text-sm leading-relaxed mb-6">
                Tu solicitud para el <strong class="text-[#f7941e]">{{ plan }}</strong> está pendiente de aprobación.
            </p>

            <div class="bg-[#111c30] border border-white/8 rounded-2xl p-5 mb-8">
                <p class="text-slate-300 text-sm">
                    Un asesor se comunicará con vos a la brevedad para activar tu cuenta y configurar tu comercio.
                </p>
            </div>

            <div class="flex items-center justify-center gap-2 text-[#8cc63f] text-xs font-bold">
                <span class="w-2 h-2 rounded-full bg-[#8cc63f] animate-pulse"></span>
                Te avisaremos por email cuando todo esté listo
            </div>

            <div class="mt-10">
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="text-[10px] font-black uppercase tracking-widest text-slate-500 hover:text-white transition-colors"
                >
                    Volver al inicio
                </Link>
            </div>
        </div>

    </div>
</template>
