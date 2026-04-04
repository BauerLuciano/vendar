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
                    <input v-model="formulario.razon_social" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 uppercase font-bold text-slate-700 focus:ring-sky-500" :class="{'border-rose-500': formulario.errors.razon_social}" required>
                    <p v-if="formulario.errors.razon_social" class="text-rose-500 text-[10px] mt-1 font-bold">{{ formulario.errors.razon_social }}</p>
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
                    <p v-if="formulario.errors.cuit" class="text-rose-500 text-[10px] mt-1 font-bold">{{ formulario.errors.cuit }}</p>
                </div>

                <div>
                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Teléfono</label>
                    <input 
                        v-model="formulario.telefono" 
                        type="text" 
                        maxlength="15"
                        @input="formulario.telefono = formulario.telefono.replace(/[^0-9]/g, '')"
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 focus:ring-sky-500" 
                        :class="{'border-rose-500': formulario.errors.telefono}"
                        placeholder="Ej: 1123456789"
                    >
                    <p v-if="formulario.errors.telefono" class="text-rose-500 text-[10px] mt-1 font-bold">{{ formulario.errors.telefono }}</p>
                </div>

                <div class="col-span-2">
                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Email</label>
                    <input v-model="formulario.email" type="email" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 focus:ring-sky-500" :class="{'border-rose-500': formulario.errors.email}">
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