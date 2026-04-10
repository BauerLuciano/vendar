<script setup>
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
                                type="password"
                                class="block w-full !bg-[#1e293b] !border-slate-700/80 !text-slate-200 rounded-xl pl-12 py-3 focus:!ring-orange-500 focus:!border-orange-500 transition-all placeholder-slate-500 tracking-widest shadow-inner"
                                v-model="form.password"
                                required
                                autocomplete="current-password"
                                placeholder="••••••••"
                            />
                        </div>
                        <InputError class="mt-2 text-[10px] font-bold uppercase text-red-400" :message="form.errors.password" />
                    </div>

                    <div class="pt-6">
                        <button
                            type="submit"
                            class="w-full flex justify-center items-center gap-3 py-4 px-4 text-[13px] font-bold uppercase tracking-[0.15em] text-white bg-transparent hover:bg-white/5 border border-transparent rounded-xl transition-all active:scale-[0.98]"
                            :class="{ 'opacity-70 cursor-not-allowed': form.processing }"
                            :disabled="form.processing"
                        >
                            <svg v-if="form.processing" class="animate-spin h-5 w-5 text-sky-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span>{{ form.processing ? 'Ingresando...' : 'Iniciar Sesión' }}</span>
                            <svg v-if="!form.processing" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"></path></svg>
                        </button>
                    </div>

                    <div class="w-full border-t border-slate-700/50 my-8"></div>

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