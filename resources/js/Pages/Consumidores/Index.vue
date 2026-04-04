<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    consumidores: Array
});

// Estado del Modal
const isModalOpen = ref(false);
const isEditing = ref(false);
const currentId = ref(null);

// Formulario de Inertia
const form = useForm({
    nombre: '',
    dni: '',
    telefono: '',
    direccion: '',
    limite_cuenta_corriente: 0,
});

// Métodos
const openModal = (cliente = null) => {
    if (cliente) {
        isEditing.value = true;
        currentId.value = cliente.id;
        form.nombre = cliente.nombre;
        form.dni = cliente.dni || '';
        form.telefono = cliente.telefono || '';
        form.direccion = cliente.direccion || '';
        form.limite_cuenta_corriente = cliente.limite_cuenta_corriente;
    } else {
        isEditing.value = false;
        currentId.value = null;
        form.reset();
    }
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    form.reset();
    form.clearErrors();
};

const submitForm = () => {
    if (isEditing.value) {
        form.put(route('consumidores.update', currentId.value), {
            onSuccess: () => closeModal()
        });
    } else {
        form.post(route('consumidores.store'), {
            onSuccess: () => closeModal()
        });
    }
};

const formatearDinero = (monto) => {
    return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(monto);
};
</script>

<template>
    <Head title="Clientes" />

    <AuthenticatedLayout>
        <div class="py-6 px-4 sm:px-6 lg:px-8 bg-slate-50 min-h-screen">
            <div class="flex justify-between items-end mb-8">
                <div>
                    <h1 class="text-2xl font-black text-slate-800 uppercase tracking-tight">Directorio de Clientes</h1>
                    <div class="h-1 w-12 bg-sky-500 mt-1"></div>
                </div>
                <button 
                    @click="openModal()"
                    class="bg-sky-600 hover:bg-sky-700 text-white font-bold py-2.5 px-5 rounded-xl shadow-sm shadow-sky-600/30 transition-all flex items-center gap-2"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6zM16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z" /></svg>
                    Nuevo Cliente
                </button>
            </div>

            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100 text-xs uppercase tracking-widest text-slate-400">
                                <th class="p-4 font-black">ID</th>
                                <th class="p-4 font-black">Nombre y Contacto</th>
                                <th class="p-4 font-black">Límite Cta. Cte.</th>
                                <th class="p-4 font-black text-center">Puntos</th>
                                <th class="p-4 font-black text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="consumidores.length === 0">
                                <td colspan="5" class="p-8 text-center text-slate-400 font-bold">No hay clientes registrados.</td>
                            </tr>
                            <tr v-for="cliente in consumidores" :key="cliente.id" class="hover:bg-slate-50/50 transition-colors group">
                                <td class="p-4 font-bold text-slate-400">#{{ cliente.id }}</td>
                                <td class="p-4">
                                    <div class="font-bold text-slate-800">{{ cliente.nombre }}</div>
                                    <div class="text-xs text-slate-400 font-medium mt-1 flex gap-2">
                                        <span v-if="cliente.dni">DNI: {{ cliente.dni }}</span>
                                        <span v-if="cliente.telefono">| Tel: {{ cliente.telefono }}</span>
                                    </div>
                                </td>
                                <td class="p-4 font-bold text-sky-600">
                                    {{ formatearDinero(cliente.limite_cuenta_corriente) }}
                                </td>
                                <td class="p-4 text-center">
                                    <span class="px-2 py-1 bg-amber-100 text-amber-600 font-black rounded-md text-xs shadow-sm">
                                        {{ cliente.puntos_acumulados || 0 }} pts
                                    </span>
                                </td>
                                <td class="p-4 text-right">
                                    <button @click="openModal(cliente)" class="text-slate-400 hover:text-sky-500 transition-colors p-2" title="Editar">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div v-if="isModalOpen" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
            <div class="bg-white rounded-3xl shadow-xl w-full max-w-md overflow-hidden transform transition-all">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                    <h3 class="text-lg font-black text-slate-800 uppercase tracking-tight">
                        {{ isEditing ? 'Editar Cliente' : 'Nuevo Cliente' }}
                    </h3>
                    <button @click="closeModal" class="text-slate-400 hover:text-slate-600 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <form @submit.prevent="submitForm" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Nombre Completo</label>
                        <input v-model="form.nombre" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 focus:border-sky-500 font-medium text-slate-700" required>
                        <div v-if="form.errors.nombre" class="text-rose-500 text-xs mt-1 font-bold">{{ form.errors.nombre }}</div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">DNI / CUIT</label>
                            <input v-model="form.dni" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 focus:border-sky-500 font-medium text-slate-700">
                            <div v-if="form.errors.dni" class="text-rose-500 text-xs mt-1 font-bold">{{ form.errors.dni }}</div>
                        </div>
                        <div>
                            <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Teléfono</label>
                            <input v-model="form.telefono" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 focus:border-sky-500 font-medium text-slate-700">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Dirección</label>
                        <input v-model="form.direccion" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 focus:border-sky-500 font-medium text-slate-700">
                    </div>

                    <div>
                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Límite Cuenta Corriente ($)</label>
                        <input v-model="form.limite_cuenta_corriente" type="number" step="1000" min="0" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 focus:border-sky-500 font-medium text-slate-700" required>
                    </div>

                    <div class="pt-4 flex justify-end gap-3">
                        <button type="button" @click="closeModal" class="px-5 py-2.5 text-sm font-bold text-slate-500 hover:text-slate-700 transition-colors">Cancelar</button>
                        <button type="submit" :disabled="form.processing" class="bg-sky-600 hover:bg-sky-700 disabled:bg-slate-300 text-white font-bold py-2.5 px-6 rounded-xl shadow-sm shadow-sky-600/30 transition-all flex items-center gap-2">
                            {{ isEditing ? 'Guardar Cambios' : 'Registrar Cliente' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>