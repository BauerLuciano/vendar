<script setup>
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({ mostrar: Boolean, sucursal: Object });
const emit = defineEmits(['cerrar']);

const formulario = useForm({
    id: null,
    name: '',
    address: '',
    phone: '',
    type: 'punto_de_venta',
});

watch(() => props.sucursal, (nuevo) => {
    if (nuevo) {
        formulario.id = nuevo.id;
        formulario.name = nuevo.name;
        formulario.address = nuevo.address;
        formulario.phone = nuevo.phone;
        formulario.type = nuevo.type;
    } else {
        formulario.reset();
    }
}, { immediate: true });

const guardar = () => {
    const esEdicion = !!formulario.id;
    const ruta = esEdicion ? route('sucursales.update', formulario.id) : route('sucursales.store');
    const metodo = esEdicion ? 'put' : 'post';

    formulario[metodo](ruta, {
        onSuccess: () => {
            Swal.fire('¡Éxito!', `Sucursal ${esEdicion ? 'actualizada' : 'registrada'}`, 'success');
            emit('cerrar');
        }
    });
};
</script>

<template>
    <div v-if="mostrar" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden">
            <div class="bg-sky-600 p-4 text-white font-bold text-center uppercase tracking-widest">
                {{ formulario.id ? 'Editar Sucursal' : 'Nueva Sucursal' }}
            </div>
            <form @submit.prevent="guardar" class="p-6 grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-xs font-bold text-gray-500 uppercase">Nombre de Sucursal</label>
                    <input v-model="formulario.name" type="text" class="w-full rounded border-gray-300 shadow-sm uppercase font-bold" required>
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-bold text-gray-500 uppercase">Dirección</label>
                    <input v-model="formulario.address" type="text" class="w-full rounded border-gray-300 shadow-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase">Teléfono</label>
                    <input 
                        v-model="formulario.phone" 
                        type="text" 
                        maxlength="15" 
                        oninput="this.value = this.value.replace(/[^0-9]/g, '');" 
                        class="w-full rounded border-gray-300 shadow-sm font-bold text-slate-700"
                        placeholder="Ej: 3755123456"
                    >
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase">Tipo</label>
                    <select v-model="formulario.type" class="w-full rounded border-gray-300 shadow-sm font-bold text-sky-700">
                        <option value="punto_de_venta">Punto de Venta</option>
                        <option value="deposito">Depósito / Almacén</option>
                        <option value="oficina">Oficina</option>
                    </select>
                </div>
                <div class="col-span-2 flex justify-end gap-3 border-t pt-4 mt-2">
                    <button type="button" @click="$emit('cerrar')" class="text-gray-400 font-bold uppercase text-xs">Cancelar</button>
                    <button type="submit" class="bg-sky-600 text-white px-8 py-2.5 rounded-lg font-bold hover:bg-sky-700 shadow-lg" :disabled="formulario.processing">Confirmar</button>
                </div>
            </form>
        </div>
    </div>
</template>