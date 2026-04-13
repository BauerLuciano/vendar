<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, watch, reactive } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    consumidores: Object,
    filtros: Object
});

const isModalOpen = ref(false);
const isEditing = ref(false);
const currentId = ref(null);

const isCobroModalOpen = ref(false);
const clienteSeleccionado = ref(null);

const menuAbierto = ref(null);

const formFiltros = reactive({
    search: props.filtros?.search || '',
    estado: props.filtros?.estado || 'all',
    deuda: props.filtros?.deuda || 'all'
});

let timeout = null;

watch(formFiltros, (value) => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(route('consumidores.index'), value, {
            preserveState: true,
            replace: true
        });
    }, 300);
});

const limpiarFiltros = () => {
    formFiltros.search = '';
    formFiltros.estado = 'all';
    formFiltros.deuda = 'all';
};

const form = useForm({
    nombre: '',
    apellido: '',
    documento: '',
    email: '',
    telefono: '',
    direccion: '',
    limite_cuenta_corriente: 0,
    estado: true,
});

const formCobro = useForm({
    monto: '',
    metodo_pago: 'EFECTIVO'
});

const toggleMenu = (id) => {
    menuAbierto.value = menuAbierto.value === id ? null : id;
};

const cerrarMenu = () => {
    menuAbierto.value = null;
};

const openModal = (cliente = null) => {
    cerrarMenu();
    form.clearErrors();
    if (cliente) {
        isEditing.value = true;
        currentId.value = cliente.id;
        form.nombre = cliente.nombre;
        form.apellido = cliente.apellido;
        form.documento = cliente.documento || '';
        form.email = cliente.email || '';
        form.telefono = cliente.telefono || '';
        form.direccion = cliente.direccion || '';
        form.limite_cuenta_corriente = cliente.limite_cuenta_corriente;
        form.estado = Boolean(cliente.estado);
    } else {
        isEditing.value = false;
        currentId.value = null;
        form.reset();
        form.estado = true;
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
            onSuccess: () => {
                closeModal();
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Cliente actualizado', showConfirmButton: false, timer: 3000 });
            }
        });
    } else {
        form.post(route('consumidores.store'), {
            onSuccess: () => {
                closeModal();
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Cliente registrado', showConfirmButton: false, timer: 3000 });
            }
        });
    }
};

const openCobroModal = (cliente) => {
    cerrarMenu();
    clienteSeleccionado.value = cliente;
    formCobro.reset();
    formCobro.monto = cliente.cuenta_corriente?.saldo_deudor || 0;
    isCobroModalOpen.value = true;
};

const closeCobroModal = () => {
    isCobroModalOpen.value = false;
    formCobro.reset();
    clienteSeleccionado.value = null;
};

const submitCobro = () => {
    formCobro.post(route('consumidores.cobrar', clienteSeleccionado.value.id), {
        onSuccess: () => {
            closeCobroModal();
            Swal.fire({ 
                icon: 'success', 
                title: 'Cobro Registrado', 
                text: 'El pago impactó en la cuenta del cliente y en tu caja abierta.',
                timer: 4000
            });
        },
        onError: (errors) => {
            if(errors.monto) {
                Swal.fire('Atención', errors.monto, 'warning');
            }
        }
    });
};

const toggleEstado = (c) => {
    cerrarMenu();
    const accion = c.estado ? 'desactivar' : 'activar';
    const resultado = c.estado ? 'desactivado' : 'activado';
    const colorConfirm = c.estado ? '#ef4444' : '#10b981';

    Swal.fire({
        title: `¿${accion.toUpperCase()} cliente?`,
        text: `El cliente "${c.nombre} ${c.apellido}" cambiará su estado a ${resultado}.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: colorConfirm,
        cancelButtonColor: '#6b7280',
        confirmButtonText: `Sí, ${accion}`,
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            router.patch(route('consumidores.status', c.id), {}, {
                onSuccess: () => {
                    Swal.fire({ title: '¡Listo!', text: `Cliente ${resultado}.`, icon: 'success', confirmButtonColor: '#0284c7' });
                }
            });
        }
    });
};

const formatearDinero = (monto) => {
    return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(monto || 0);
};

const calcularDisponible = (limite, deuda) => {
    return limite - (deuda || 0);
};
</script>

<template>
    <Head title="Clientes" />

    <AuthenticatedLayout>
        <div v-if="menuAbierto" @click="cerrarMenu" class="fixed inset-0 z-30"></div>

        <div class="py-6 px-4 sm:px-6 lg:px-8 bg-slate-50 min-h-screen">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end mb-8 gap-4">
                <div>
                    <h1 class="text-2xl font-black text-slate-800 uppercase tracking-tight">Directorio de Clientes</h1>
                    <div class="h-1 w-12 bg-sky-500 mt-1"></div>
                </div>
                <button 
                    @click="openModal()"
                    class="bg-sky-600 hover:bg-sky-700 text-white font-bold py-2.5 px-5 rounded-xl shadow-sm shadow-sky-600/30 transition-all flex items-center justify-center gap-2 w-full sm:w-auto"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6zM16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z" /></svg>
                    Nuevo Cliente
                </button>
            </div>

            <div class="mb-6 bg-white p-4 rounded-2xl shadow-sm border border-slate-100 flex flex-col gap-4">
                <div class="flex flex-col sm:flex-row gap-4 w-full">
                    <div class="relative w-full sm:w-1/2">
                        <input 
                            v-model="formFiltros.search" 
                            type="text" 
                            placeholder="Buscar por Nombre, DNI o ID..." 
                            class="w-full pl-10 pr-4 py-2.5 border border-slate-200 rounded-xl focus:ring-sky-500 focus:border-sky-500 transition-all font-medium text-sm"
                        >
                        <svg class="w-5 h-5 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    
                    <div class="w-full sm:w-1/4">
                        <select v-model="formFiltros.estado" class="w-full border border-slate-200 rounded-xl py-2.5 px-4 focus:ring-sky-500 focus:border-sky-500 text-slate-600 bg-slate-50 cursor-pointer font-medium text-sm">
                            <option value="all">Todos los estados</option>
                            <option value="activos">Solo Activos</option>
                            <option value="inactivos">Solo Inactivos</option>
                        </select>
                    </div>

                    <div class="w-full sm:w-1/4">
                        <select v-model="formFiltros.deuda" class="w-full border border-slate-200 rounded-xl py-2.5 px-4 focus:ring-sky-500 focus:border-sky-500 text-slate-600 bg-slate-50 cursor-pointer font-medium text-sm">
                            <option value="all">Todos (Con y sin deuda)</option>
                            <option value="con_deuda">Solo con Deuda Activa</option>
                        </select>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button v-if="formFiltros.search || formFiltros.estado !== 'all' || formFiltros.deuda !== 'all'" @click="limpiarFiltros" class="text-sm text-slate-500 hover:text-rose-500 font-bold px-4 transition-colors">
                        Limpiar Filtros
                    </button>
                </div>
            </div>

            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-4">
                <div class="overflow-visible">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100 text-xs uppercase tracking-widest text-slate-400">
                                <th class="p-4 font-black rounded-l-xl">ID</th>
                                <th class="p-4 font-black">Cliente</th>
                                <th class="p-4 font-black">Contacto</th>
                                <th class="p-4 font-black text-right">Límite Cta. Cte.</th>
                                <th class="p-4 font-black text-right">Deuda Actual</th>
                                <th class="p-4 font-black text-right">Disponible</th>
                                <th class="p-4 font-black text-center rounded-r-xl">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="consumidores.data.length === 0">
                                <td colspan="7" class="p-8 text-center text-slate-400 font-bold">No se encontraron clientes con esos filtros.</td>
                            </tr>
                            <tr v-for="cliente in consumidores.data" :key="cliente.id" class="hover:bg-slate-50/50 transition-colors group" :class="{'opacity-50 grayscale bg-slate-50': !cliente.estado}">
                                <td class="p-4 font-bold text-slate-400">#{{ cliente.id }}</td>
                                <td class="p-4">
                                    <div class="font-bold text-slate-800">{{ cliente.nombre }} {{ cliente.apellido }}</div>
                                    <div class="text-xs font-medium mt-1" :class="cliente.estado ? 'text-emerald-500' : 'text-rose-500'">
                                        {{ cliente.estado ? 'Activo' : 'Inactivo' }}
                                    </div>
                                </td>
                                <td class="p-4">
                                    <div class="text-xs text-slate-500 font-medium space-y-1">
                                        <div v-if="cliente.documento" title="Documento"><span class="font-bold text-slate-400">DOC:</span> {{ cliente.documento }}</div>
                                        <div v-if="cliente.telefono" title="Teléfono"><span class="font-bold text-slate-400">TEL:</span> {{ cliente.telefono }}</div>
                                        <div v-if="cliente.email" title="Email"><span class="font-bold text-slate-400">MAIL:</span> {{ cliente.email }}</div>
                                    </div>
                                </td>
                                
                                <td class="p-4 font-bold text-slate-600 text-right">
                                    {{ formatearDinero(cliente.limite_cuenta_corriente) }}
                                </td>
                                
                                <td class="p-4 font-black text-right" :class="cliente.cuenta_corriente?.saldo_deudor > 0 ? 'text-rose-600' : 'text-slate-400'">
                                    {{ formatearDinero(cliente.cuenta_corriente?.saldo_deudor) }}
                                </td>
                                
                                <td class="p-4 font-black text-right" :class="calcularDisponible(cliente.limite_cuenta_corriente, cliente.cuenta_corriente?.saldo_deudor) <= 0 ? 'text-rose-500' : 'text-emerald-600'">
                                    {{ formatearDinero(calcularDisponible(cliente.limite_cuenta_corriente, cliente.cuenta_corriente?.saldo_deudor)) }}
                                </td>

                                <td class="p-4 text-center relative">
                                    <button @click.stop="toggleMenu(cliente.id)" class="p-2 rounded-full text-slate-400 hover:text-sky-600 hover:bg-sky-100 transition-colors focus:outline-none">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg>
                                    </button>

                                    <div v-if="menuAbierto === cliente.id" class="absolute right-10 top-10 w-48 bg-white rounded-xl shadow-2xl border border-slate-100 z-40 py-2 animate-in fade-in zoom-in-95 duration-150">
                                        
                                        <button v-if="cliente.cuenta_corriente?.saldo_deudor > 0" @click="openCobroModal(cliente)" class="w-full text-left px-4 py-2.5 text-xs font-bold text-emerald-600 hover:bg-emerald-50 flex items-center gap-3 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            Cobrar Deuda
                                        </button>

                                        <button @click="openModal(cliente)" class="w-full text-left px-4 py-2.5 text-xs font-bold text-sky-600 hover:bg-sky-50 flex items-center gap-3 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                            Editar Datos
                                        </button>

                                        <div class="border-t border-slate-100 my-1"></div>

                                        <button @click="toggleEstado(cliente)" 
                                            class="w-full text-left px-4 py-2.5 text-xs font-bold flex items-center gap-3 transition-colors"
                                            :class="cliente.estado ? 'text-rose-600 hover:bg-rose-50' : 'text-emerald-600 hover:bg-emerald-50'">
                                            <svg v-if="cliente.estado" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                                            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            {{ cliente.estado ? 'Dar de Baja' : 'Activar Cliente' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="consumidores.links && consumidores.data.length > 0" class="p-4 bg-slate-50 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <span class="text-sm text-slate-500 font-medium">
                        Mostrando {{ consumidores.from }} a {{ consumidores.to }} de {{ consumidores.total }} clientes
                    </span>
                    <div class="flex flex-wrap justify-center gap-1">
                        <component
                            :is="link.url ? 'a' : 'span'"
                            v-for="(link, index) in consumidores.links"
                            :key="index"
                            :href="link.url"
                            @click.prevent="link.url ? router.get(link.url, formFiltros, { preserveState: true }) : null"
                            v-html="link.label.replace('Previous', 'Anterior').replace('Next', 'Siguiente')"
                            class="px-3 py-1.5 text-sm rounded-lg transition-colors border"
                            :class="[
                                link.active ? 'bg-sky-600 text-white font-bold border-sky-600 shadow-sm' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-100',
                                !link.url ? 'opacity-50 cursor-not-allowed bg-slate-50' : 'cursor-pointer'
                            ]"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div v-if="isModalOpen" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
            <div class="bg-white rounded-3xl shadow-xl w-full max-w-2xl overflow-hidden transform transition-all">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                    <h3 class="text-lg font-black text-slate-800 uppercase tracking-tight">
                        {{ isEditing ? 'Editar Cliente' : 'Nuevo Cliente' }}
                    </h3>
                    <button @click="closeModal" class="text-slate-400 hover:text-slate-600 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <form @submit.prevent="submitForm" class="p-6 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Nombre</label>
                            <input 
                                v-model="form.nombre" 
                                @input="form.nombre = form.nombre.replace(/[0-9]/g, '')"
                                maxlength="50"
                                type="text" 
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 focus:border-sky-500 font-medium text-slate-700" 
                                :class="{'border-rose-500': form.errors.nombre}" 
                                required
                            >
                            <p v-if="form.errors.nombre" class="mt-1 text-xs text-rose-500 font-bold">{{ form.errors.nombre }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Apellido</label>
                            <input 
                                v-model="form.apellido" 
                                @input="form.apellido = form.apellido.replace(/[0-9]/g, '')"
                                maxlength="50"
                                type="text" 
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 focus:border-sky-500 font-medium text-slate-700" 
                                :class="{'border-rose-500': form.errors.apellido}" 
                                required
                            >
                            <p v-if="form.errors.apellido" class="mt-1 text-xs text-rose-500 font-bold">{{ form.errors.apellido }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Documento</label>
                            <input 
                                v-model="form.documento" 
                                @input="form.documento = form.documento.replace(/\D/g, '')"
                                maxlength="8"
                                type="text" 
                                placeholder="Ej: 30123456" 
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 focus:border-sky-500 font-medium text-slate-700" 
                                :class="{'border-rose-500': form.errors.documento}"
                            >
                            <p v-if="form.errors.documento" class="mt-1 text-xs text-rose-500 font-bold">{{ form.errors.documento }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Email</label>
                            <input 
                                v-model="form.email" 
                                maxlength="255"
                                type="email" 
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 focus:border-sky-500 font-medium text-slate-700" 
                                :class="{'border-rose-500': form.errors.email}"
                            >
                            <p v-if="form.errors.email" class="mt-1 text-xs text-rose-500 font-bold">{{ form.errors.email }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Teléfono</label>
                            <input 
                                v-model="form.telefono" 
                                @input="form.telefono = form.telefono.replace(/\D/g, '')"
                                maxlength="15"
                                type="text" 
                                placeholder="Ej: 3758445566"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 focus:border-sky-500 font-medium text-slate-700" 
                                :class="{'border-rose-500': form.errors.telefono}"
                            >
                            <p v-if="form.errors.telefono" class="mt-1 text-xs text-rose-500 font-bold">{{ form.errors.telefono }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Dirección</label>
                            <input 
                                v-model="form.direccion" 
                                maxlength="255"
                                type="text" 
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 focus:border-sky-500 font-medium text-slate-700" 
                                :class="{'border-rose-500': form.errors.direccion}"
                            >
                            <p v-if="form.errors.direccion" class="mt-1 text-xs text-rose-500 font-bold">{{ form.errors.direccion }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Límite Cta. Corriente ($)</label>
                            <input 
                                v-model="form.limite_cuenta_corriente" 
                                type="number" 
                                step="100" 
                                min="0" 
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 focus:border-sky-500 font-medium text-slate-700" 
                                :class="{'border-rose-500': form.errors.limite_cuenta_corriente}" 
                                required
                            >
                            <p v-if="form.errors.limite_cuenta_corriente" class="mt-1 text-xs text-rose-500 font-bold">{{ form.errors.limite_cuenta_corriente }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Estado Operativo</label>
                            <select v-model="form.estado" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 focus:border-sky-500 font-medium text-slate-700" :class="{'border-rose-500': form.errors.estado}">
                                <option :value="true">Activo (Habilitado)</option>
                                <option :value="false">Inactivo (Suspendido)</option>
                            </select>
                            <p v-if="form.errors.estado" class="mt-1 text-xs text-rose-500 font-bold">{{ form.errors.estado }}</p>
                        </div>
                    </div>

                    <div class="pt-6 flex justify-end gap-3 border-t border-slate-100 mt-4">
                        <button type="button" @click="closeModal" class="px-5 py-2.5 text-sm font-bold text-slate-500 hover:text-slate-700 transition-colors">Cancelar</button>
                        <button type="submit" :disabled="form.processing" class="bg-sky-600 hover:bg-sky-700 disabled:bg-slate-300 text-white font-bold py-2.5 px-6 rounded-xl shadow-sm shadow-sky-600/30 transition-all flex items-center gap-2">
                            <span v-if="form.processing">Guardando...</span>
                            <span v-else>{{ isEditing ? 'Guardar Cambios' : 'Registrar Cliente' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="isCobroModalOpen" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-emerald-50">
                    <h3 class="text-lg font-black text-emerald-800 uppercase tracking-tight flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                        Cobrar Deuda
                    </h3>
                    <button @click="closeCobroModal" class="text-slate-400 hover:text-rose-500 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="p-6">
                    <p class="text-sm text-slate-500 mb-4">
                        Estás a punto de registrar un pago para <strong>{{ clienteSeleccionado?.nombre }} {{ clienteSeleccionado?.apellido }}</strong>.
                        <br>Deuda actual: <span class="font-bold text-rose-600">{{ formatearDinero(clienteSeleccionado?.cuenta_corriente?.saldo_deudor) }}</span>
                    </p>

                    <form @submit.prevent="submitCobro" class="space-y-4">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Monto a abonar</label>
                            <div class="relative">
                                <span class="absolute left-3 top-2.5 font-bold text-slate-400">$</span>
                                <input 
                                    v-model="formCobro.monto" 
                                    type="number" 
                                    step="0.01" 
                                    min="1"
                                    :max="clienteSeleccionado?.cuenta_corriente?.saldo_deudor"
                                    class="w-full pl-8 bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-emerald-500 focus:border-emerald-500 font-bold text-slate-800" 
                                    required
                                >
                            </div>
                            <p v-if="formCobro.errors.monto" class="mt-1 text-xs text-rose-500 font-bold">{{ formCobro.errors.monto }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Medio de Pago</label>
                            <select v-model="formCobro.metodo_pago" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-emerald-500 focus:border-emerald-500 font-medium text-slate-700">
                                <option value="EFECTIVO">Efectivo</option>
                                <option value="MERCADO_PAGO">Mercado Pago</option>
                                <option value="TRANSFERENCIA">Transferencia Bancaria</option>
                            </select>
                        </div>

                        <div class="pt-4 flex justify-end gap-3 mt-4">
                            <button type="button" @click="closeCobroModal" class="px-4 py-2 text-sm font-bold text-slate-500 hover:bg-slate-100 rounded-lg transition-colors">Cancelar</button>
                            <button type="submit" :disabled="formCobro.processing" class="bg-emerald-600 hover:bg-emerald-700 disabled:bg-slate-300 text-white font-bold py-2 px-6 rounded-xl shadow-sm shadow-emerald-600/30 transition-all flex items-center gap-2">
                                <span v-if="formCobro.processing">Procesando...</span>
                                <span v-else>Confirmar Pago</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>