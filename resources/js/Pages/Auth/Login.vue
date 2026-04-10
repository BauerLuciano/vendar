<script setup>
import { ref } from 'vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const showPassword = ref(false); 

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Iniciar Sesión | VendAR" />

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
            
            <div class="w-full max-w-sm flex-1 flex flex-col justify-center">
                
                <div class="sm:hidden flex justify-center mb-12">
                    <img src="/img/LogoVendar-Sidebar.png" alt="VendAR Logo" class="w-56 h-auto object-contain">
                </div>

                <div class="mb-10 text-center">
                    <h1 class="text-[2rem] font-bold text-white tracking-tight mb-2">
                        Bienvenido a <span>Vend</span><span class="text-orange-500">AR</span>
                    </h1>
                    <p class="text-slate-400 text-[15px]">Ingresa tus credenciales para continuar</p>
                </div>

                <div v-if="status" class="mb-6 text-sm font-bold text-emerald-400 bg-emerald-400/10 p-4 rounded-xl border border-emerald-400/20 text-center">
                    {{ status }}
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    
                    <div>
                        <InputLabel for="email" value="CORREO ELECTRÓNICO" class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1" />
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors group-focus-within:text-sky-400 text-slate-500">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                                </svg>
                            </div>
                            <TextInput
                                id="email"
                                type="email"
                                class="block w-full !bg-[#1e293b] !border-slate-700/80 !text-slate-200 rounded-xl pl-12 py-3 focus:!ring-sky-500 focus:!border-sky-500 transition-all placeholder-slate-500 shadow-inner"
                                v-model="form.email"
                                required
                                autofocus
                                autocomplete="username"
                                placeholder="ejemplo@vendar.test"
                            />
                        </div>
                        <InputError class="mt-2 text-[10px] font-bold uppercase text-red-400" :message="form.errors.email" />
                    </div>

                    <div>
                        <div class="flex justify-between items-center mb-2 ml-1">
                            <InputLabel for="password" value="CONTRASEÑA" class="text-[11px] font-bold text-slate-400 uppercase tracking-widest" />
                            <Link
                                v-if="canResetPassword"
                                :href="route('password.request')"
                                class="text-[12px] font-medium text-orange-400/90 hover:text-orange-300 transition-colors"
                            >
                                ¿Olvidaste tu contraseña?
                            </Link>
                        </div>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors group-focus-within:text-orange-400 text-slate-500">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                                </svg>
                            </div>
                            
                            <TextInput
                                id="password"
                                :type="showPassword ? 'text' : 'password'"
                                class="block w-full !bg-[#1e293b] !border-slate-700/80 !text-slate-200 rounded-xl pl-12 pr-12 py-3 focus:!ring-orange-500 focus:!border-orange-500 transition-all placeholder-slate-500 tracking-widest shadow-inner"
                                v-model="form.password"
                                required
                                autocomplete="current-password"
                                placeholder="••••••••"
                            />

                            <button 
                                type="button" 
                                @click="showPassword = !showPassword" 
                                class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-500 hover:text-orange-400 transition-colors focus:outline-none"
                            >
                                <svg v-if="showPassword" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                                <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                            </button>
                        </div>
                        <InputError class="mt-2 text-[10px] font-bold uppercase text-red-400" :message="form.errors.password" />
                    </div>

                    <div class="pt-2">
                        <button
                            type="submit"
                            class="w-full flex justify-center items-center gap-3 py-3.5 px-4 text-[13px] font-bold uppercase tracking-[0.15em] text-white bg-transparent hover:bg-white/5 border border-transparent rounded-xl transition-all active:scale-[0.98]"
                            :class="{ 'opacity-70 cursor-not-allowed': form.processing }"
                            :disabled="form.processing"
                        >
                            <svg v-if="form.processing" class="animate-spin h-5 w-5 text-sky-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span>{{ form.processing ? 'Ingresando...' : 'Iniciar Sesión' }}</span>
                            <svg v-if="!form.processing" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"></path></svg>
                        </button>

                        <a 
                            href="/auth/google" 
                            class="mt-3 w-full flex justify-center items-center gap-3 py-3.5 px-4 text-[13px] font-bold uppercase tracking-[0.1em] text-slate-300 bg-[#1e293b] hover:bg-slate-800 border border-slate-700/80 rounded-xl transition-all active:scale-[0.98] shadow-inner"
                        >
                            <svg class="w-5 h-5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
                            <span>Continuar con Google</span>
                        </a>
                    </div>

                    <div class="w-full border-t border-slate-700/50 my-6"></div>

                    <div class="text-center">
                        <p class="text-[13px] text-slate-400">
                            ¿Eres nuevo aquí? 
                            <Link v-if="route().has('register')" :href="route('register')" class="text-sky-400 font-bold hover:text-sky-300 transition-colors ml-1">Crear una cuenta</Link>
                            <span v-else class="text-white font-bold ml-1 cursor-not-allowed">Crear una cuenta</span>
                        </p>
                    </div>
                </form>
            </div>

            <div class="w-full text-center mt-12">
                <p class="text-[11px] font-medium text-slate-500 tracking-wider">Copyright © VendAR</p>
            </div>
        </div>
    </div>
</template>

<style scoped>
:deep(input[type="password"]::-ms-reveal),
:deep(input[type="password"]::-ms-clear) {
    display: none;
}
</style>