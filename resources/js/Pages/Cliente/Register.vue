<script setup>
import { ref, reactive } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    comercio: Object,
    tienda_slug: String,
});

const form = reactive({
    nombre: '',
    apellido: '',
    email: '',
    telefono: '',
    direccion: '',
    password: '',
    password_confirmation: '',
});

const errors = reactive({});
const enviando = ref(false);
const errorGeneral = ref('');

const registrar = async () => {
    enviando.value = true;
    errorGeneral.value = '';
    Object.keys(errors).forEach(k => delete errors[k]);

    try {
        const res = await axios.post('/api/tienda/register', { ...form });
        if (res.data.consumidor) {
            window.location.href = '/tienda/' + props.tienda_slug;
        }
    } catch (error) {
        const data = error.response?.data;
        if (data?.errors) {
            Object.entries(data.errors).forEach(([field, msgs]) => {
                errors[field] = Array.isArray(msgs) ? msgs[0] : msgs;
            });
        } else {
            errorGeneral.value = data?.error || 'Error al registrarse';
        }
    } finally {
        enviando.value = false;
    }
};
</script>

<template>
    <Head :title="`Crear cuenta | ${comercio?.nombre || 'VendAR'}`" />

    <div class="min-h-screen bg-[#080f1e] font-sans flex flex-col relative overflow-hidden">
        <div class="fixed inset-0 pointer-events-none z-0">
            <div class="absolute w-[600px] h-[600px] bg-[#8cc63f]/6 rounded-full blur-[160px] -top-40 -left-40"></div>
            <div class="absolute w-[500px] h-[500px] bg-[#00adef]/5 rounded-full blur-[140px] -bottom-40 -right-40"></div>
            <div class="absolute w-[300px] h-[300px] bg-[#f7941e]/4 rounded-full blur-[120px] top-1/2 right-1/3"></div>
            <div class="absolute inset-0" style="background-image: linear-gradient(rgba(0,173,239,0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(0,173,239,0.03) 1px, transparent 1px); background-size: 40px 40px;"></div>
        </div>

        <div class="relative z-10 flex-1 flex items-center justify-center p-4">
            <div class="w-full max-w-lg">
                <div class="text-center mb-8">
                    <img src="/img/LogoVendar-Sidebar.png" alt="VendAR" class="h-12 mx-auto mb-3">
                    <h1 class="text-xl font-black text-white tracking-tight">{{ comercio?.nombre }}</h1>
                    <p class="text-xs text-slate-500 mt-1">Creá tu cuenta y empezá a pedir</p>
                </div>

                <div class="bg-[#0f1929] border border-white/8 rounded-3xl p-8 shadow-2xl shadow-black/50">
                    <form @submit.prevent="registrar" class="space-y-4">
                        <div v-if="errorGeneral" class="bg-rose-500/10 border border-rose-500/30 rounded-xl p-3">
                            <p class="text-xs font-bold text-rose-400">{{ errorGeneral }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5">Nombre</label>
                                <input v-model="form.nombre" type="text" maxlength="255" required placeholder="Juan"
                                    class="w-full bg-[#080f1e] border border-white/8 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-[#8cc63f]/50 focus:ring-2 focus:ring-[#8cc63f]/20 transition-all"
                                    :class="{'border-rose-500/50': errors.nombre}">
                                <p v-if="errors.nombre" class="text-[10px] text-rose-400 mt-1">{{ errors.nombre }}</p>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5">Apellido</label>
                                <input v-model="form.apellido" type="text" maxlength="255" required placeholder="Pérez"
                                    class="w-full bg-[#080f1e] border border-white/8 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-[#8cc63f]/50 focus:ring-2 focus:ring-[#8cc63f]/20 transition-all"
                                    :class="{'border-rose-500/50': errors.apellido}">
                                <p v-if="errors.apellido" class="text-[10px] text-rose-400 mt-1">{{ errors.apellido }}</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5">Email</label>
                            <input v-model="form.email" type="email" required placeholder="juan@email.com"
                                class="w-full bg-[#080f1e] border border-white/8 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-[#8cc63f]/50 focus:ring-2 focus:ring-[#8cc63f]/20 transition-all"
                                :class="{'border-rose-500/50': errors.email}">
                            <p v-if="errors.email" class="text-[10px] text-rose-400 mt-1">{{ errors.email }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5">Teléfono</label>
                                <input v-model="form.telefono" type="tel" placeholder="3764 123456"
                                    class="w-full bg-[#080f1e] border border-white/8 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-[#8cc63f]/50 focus:ring-2 focus:ring-[#8cc63f]/20 transition-all">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5">Dirección</label>
                                <input v-model="form.direccion" type="text" placeholder="Av. Siempre Viva 742"
                                    class="w-full bg-[#080f1e] border border-white/8 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-[#8cc63f]/50 focus:ring-2 focus:ring-[#8cc63f]/20 transition-all">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5">Contraseña</label>
                                <input v-model="form.password" type="password" required minlength="6" placeholder="••••••"
                                    class="w-full bg-[#080f1e] border border-white/8 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-[#8cc63f]/50 focus:ring-2 focus:ring-[#8cc63f]/20 transition-all"
                                    :class="{'border-rose-500/50': errors.password}">
                                <p v-if="errors.password" class="text-[10px] text-rose-400 mt-1">{{ errors.password }}</p>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5">Repetir contraseña</label>
                                <input v-model="form.password_confirmation" type="password" required minlength="6" placeholder="••••••"
                                    class="w-full bg-[#080f1e] border border-white/8 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-[#8cc63f]/50 focus:ring-2 focus:ring-[#8cc63f]/20 transition-all">
                            </div>
                        </div>

                        <button
                            type="submit"
                            :disabled="enviando"
                            class="w-full bg-[#8cc63f] hover:bg-[#8cc63f]/80 disabled:bg-slate-700 disabled:text-slate-500 text-white font-black py-3 rounded-xl text-xs uppercase tracking-widest shadow-lg shadow-[#8cc63f]/20 transition-all active:scale-[0.98]"
                        >
                            <span v-if="enviando" class="inline-flex items-center gap-2">
                                <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                Creando cuenta...
                            </span>
                            <span v-else>Crear cuenta</span>
                        </button>
                    </form>

                    <div class="mt-6 pt-5 border-t border-white/5 text-center">
                        <p class="text-xs text-slate-500">
                            ¿Ya tenés cuenta?
                            <Link :href="'/tienda/' + tienda_slug + '/login'" class="text-[#8cc63f] font-bold hover:text-white transition-colors">Ingresá</Link>
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
