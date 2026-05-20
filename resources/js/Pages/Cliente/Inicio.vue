<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
});
</script>

<template>
    <Head title="Inicio | VendAR" />

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

            <div class="w-24 h-24 mx-auto mb-8 bg-[#00adef]/10 border border-[#00adef]/20 rounded-3xl flex items-center justify-center">
                <svg class="w-12 h-12 text-[#00adef]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 0-.621-3.416l-1.71-1.71A3 3 0 0 1 2.25 1.5h19.5a3 3 0 0 1 2.121.879l1.71 1.71a3.004 3.004 0 0 1-.621 3.416M15 12.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                </svg>
            </div>

            <h1 class="text-2xl font-black text-white uppercase tracking-tighter italic mb-3">
                ¡Hola, <span class="text-[#00adef]">{{ $page.props.auth.user.name.split(' ')[0] }}</span>!
            </h1>

            <p class="text-slate-400 text-sm leading-relaxed mb-6">
                Ingresá a la URL de tu comercio favorito para empezar a comprar.
            </p>

            <div class="bg-[#111c30] border border-white/8 rounded-2xl p-5 mb-8">
                <p class="text-slate-300 text-sm">
                    Si tenés el link de una tienda, escribilo en tu navegador como:
                </p>
                <p class="text-[#00adef] font-bold text-xs mt-2 font-mono bg-[#080f1e] px-3 py-2 rounded-lg">
                    vendar.com/tienda/nombre-del-comercio
                </p>
            </div>

            <div class="flex flex-col gap-3">
                <Link
                    href="/"
                    class="w-full bg-[#00adef] hover:bg-[#00adef]/90 text-white font-black py-4 rounded-xl text-xs uppercase tracking-[0.2em] shadow-2xl shadow-[#00adef]/20 active:scale-95 transition-all"
                >
                    Volver al Inicio
                </Link>
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="text-[10px] font-black uppercase tracking-widest text-slate-500 hover:text-white transition-colors"
                >
                    Cerrar Sesión
                </Link>
            </div>
        </div>

        <div class="relative z-10 mt-12 pb-6">
            <p class="text-[9px] font-black text-slate-600 uppercase tracking-[0.3em]">© 2026 VendAR SaaS</p>
        </div>
    </div>
</template>
