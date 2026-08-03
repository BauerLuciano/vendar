<script setup>
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({ mostrar: Boolean, proveedor: Object });
const emit = defineEmits(['cerrar']);

const formulario = useForm({
    id: null,
    razon_social: '',
    cuit: '',
    telefono: '',
    email: '',
    direccion: '',
});

watch(() => props.proveedor, (nuevo) => {
    formulario.clearErrors();
    if (nuevo) {
        formulario.id = nuevo.id;
        formulario.razon_social = nuevo.razon_social;
        formulario.cuit = nuevo.cuit;
        formulario.telefono = nuevo.telefono || '';
        formulario.email = nuevo.email || '';
        formulario.direccion = nuevo.direccion || '';
    } else {
        formulario.reset();
    }
}, { immediate: true });

const guardar = () => {
    const esEdicion = !!formulario.id;
    const ruta = esEdicion ? route('proveedores.update', formulario.id) : route('proveedores.store');
    const metodo = esEdicion ? 'put' : 'post';
    
    formulario[metodo](ruta, {
        onSuccess: () => {
            Swal.fire('¡Éxito!', `Proveedor guardado correctamente.`, 'success');
            emit('cerrar');
            formulario.reset();
        }
    });
};
</script>

<template>
    <div v-if="mostrar" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden transition-all">
            <div class="bg-sky-600 p-4 text-white font-bold text-center uppercase tracking-widest">
                {{ formulario.id ? 'Editar Proveedor' : 'Registrar Proveedor' }}
            </div>
            
            <form @submit.prevent="guardar" class="p-6 grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Razón Social</label>
                    <input v-model="formulario.razon_social" @input="formulario.razon_social = formulario.razon_social.toUpperCase()" type="text" maxlength="255" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 uppercase font-bold text-slate-700 focus:ring-sky-500" :class="{'border-rose-500': formulario.errors.razon_social}" required>
                    <div v-if="formulario.errors.razon_social" class="flex items-start gap-2.5 bg-rose-50 border border-rose-200 rounded-xl px-3.5 py-2.5 mt-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 shrink-0 text-rose-500 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                        </svg>
                        <p class="text-sm font-semibold text-rose-600 leading-snug">{{ formulario.errors.razon_social }}</p>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">CUIT (11 Números)</label>
                    <input 
                        v-model="formulario.cuit" 
                        type="text" 
                        minlength="11"
                        maxlength="11"
                        @input="formulario.cuit = formulario.cuit.replace(/[^0-9]/g, '')" 
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 font-mono focus:ring-sky-500" 
                        :class="{'border-rose-500': formulario.errors.cuit}" 
                        placeholder="Ej: 30123456789"
                        required
                    >
                    <div v-if="formulario.errors.cuit" class="flex items-start gap-2.5 bg-rose-50 border border-rose-200 rounded-xl px-3.5 py-2.5 mt-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 shrink-0 text-rose-500 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                        </svg>
                        <p class="text-sm font-semibold text-rose-600 leading-snug">{{ formulario.errors.cuit }}</p>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Teléfono</label>
                    <input 
                        v-model="formulario.telefono" 
                        type="tel" 
                        maxlength="20"
                        @input="formulario.telefono = formulario.telefono.replace(/[^0-9]/g, '')"
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 focus:ring-sky-500" 
                        :class="{'border-rose-500': formulario.errors.telefono}"
                        placeholder="Ej: 1123456789"
                    >
                    <p v-if="formulario.errors.telefono" class="text-rose-500 text-[10px] mt-1 font-bold">{{ formulario.errors.telefono }}</p>
                </div>

                <div class="col-span-2">
                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Email</label>
                    <input v-model="formulario.email" type="email" maxlength="255" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 focus:ring-sky-500" :class="{'border-rose-500': formulario.errors.email}">
                    <p v-if="formulario.errors.email" class="text-rose-500 text-[10px] mt-1 font-bold">{{ formulario.errors.email }}</p>
                </div>

                <div class="col-span-2 flex justify-end gap-3 border-t border-slate-100 pt-6 mt-2">
                    <button type="button" @click="$emit('cerrar')" class="px-5 py-2 text-sm font-bold text-slate-500 hover:text-slate-700 uppercase tracking-widest transition-colors">Cancelar</button>
                    <button type="submit" class="bg-sky-600 text-white px-8 py-2.5 rounded-xl font-bold hover:bg-sky-700 shadow-sm shadow-sky-600/30 uppercase text-xs tracking-widest transition-all active:scale-95" :disabled="formulario.processing">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</template>