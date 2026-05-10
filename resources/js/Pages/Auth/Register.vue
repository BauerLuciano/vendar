<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <div class="min-h-screen bg-[#050a15] text-slate-200 font-sans flex flex-col justify-center items-center relative overflow-hidden">
        <Head title="Crear Cuenta | VendAR" />

        <div class="fixed inset-0 pointer-events-none overflow-hidden z-0">
            <div class="absolute w-[600px] h-[600px] bg-[#00adef]/10 rounded-full blur-[120px] -top-20 -left-20"></div>
            <div class="absolute w-[500px] h-[500px] bg-[#f7941e]/5 rounded-full blur-[120px] bottom-0 right-0"></div>
        </div>

        <div class="relative z-10 w-full max-w-md px-6 py-8">
            
            <div class="text-center mb-10">
                <Link href="/" class="inline-block mb-6">
                    <img 
                        src="/img/LogoVendar-Sidebar.png" 
                        alt="VendAR" 
                        class="h-28 w-auto mx-auto hover:scale-110 transition-transform duration-500 drop-shadow-[0_0_15px_rgba(0,172,239,0.3)]"
                    >
                </Link>
                <h1 class="text-3xl font-black text-white uppercase tracking-tighter italic">
                    Unite a Vend<span class="text-[#00adef]">AR</span>
                </h1>
                <p class="text-slate-500 text-[10px] font-black uppercase tracking-[0.3em] mt-2">Gestioná tu comercio como un profesional</p>
            </div>

            <form @submit.prevent="submit" class="space-y-5">
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 mb-2 ml-1">Nombre Completo</label>
                    <input
                        id="name"
                        type="text"
                        v-model="form.name"
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
                        class="w-full bg-[#111c30] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-[#00adef]/50 focus:ring-1 focus:ring-[#00adef]/20 transition-all placeholder-slate-600 shadow-inner"
                        placeholder="tu@email.com"
                        required
                    />
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 mb-2 ml-1">Contraseña</label>
                    <input
                        id="password"
                        type="password"
                        v-model="form.password"
                        class="w-full bg-[#111c30] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-[#00adef]/50 focus:ring-1 focus:ring-[#00adef]/20 transition-all placeholder-slate-600 shadow-inner"
                        placeholder="••••••••"
                        required
                    />
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 mb-2 ml-1">Confirmar Contraseña</label>
                    <input
                        id="password_confirmation"
                        type="password"
                        v-model="form.password_confirmation"
                        class="w-full bg-[#111c30] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-[#00adef]/50 focus:ring-1 focus:ring-[#00adef]/20 transition-all placeholder-slate-600 shadow-inner"
                        placeholder="••••••••"
                        required
                    />
                    <InputError class="mt-2" :message="form.errors.password_confirmation" />
                </div>

                <div class="pt-2">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full bg-[#00adef] hover:bg-[#00adef]/90 text-white font-black py-4 rounded-xl text-xs uppercase tracking-[0.2em] shadow-2xl shadow-[#00adef]/20 active:scale-95 transition-all disabled:opacity-50"
                    >
                        Empezar Ahora
                    </button>
                </div>

                <div class="text-center mt-6">
                    <Link
                        :href="route('login')"
                        class="text-[10px] font-black uppercase tracking-widest text-slate-500 hover:text-white transition-colors"
                    >
                        ¿Ya tenés cuenta? <span class="text-[#00adef]">Ingresar</span>
                    </Link>
                </div>
            </form>
        </div>

        <div class="relative z-10 mt-12 pb-6">
            <p class="text-[9px] font-black text-slate-600 uppercase tracking-[0.3em]">© 2026 VendAR SaaS</p>
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