<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, watch, reactive, computed } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({
    consumidores: Object,
    filtros: Object
});

const isModalOpen = ref(false);
const isEditing = ref(false);
const currentId = ref(null);

const isCobroModalOpen = ref(false);
const isHistorialModalOpen = ref(false);
const clienteSeleccionado = ref(null);
const historialMovimientos = ref([]);

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

// ----- VALIDACIÓN EN TIEMPO REAL DEL DNI -----
const documentoStatus = ref(null); // null, 'checking', 'available', 'unavailable'
let documentoDebounceTimer = null;

const checkDocumento = async () => {
    const dni = form.documento?.trim();
    
    if (!dni) {
        documentoStatus.value = null;
        return;
    }

    if (!/^\d{7,8}$/.test(dni)) {
        documentoStatus.value = 'unavailable';
        return;
    }

    documentoStatus.value = 'checking';
    
    try {
        const response = await axios.get(route('consumidores.checkDocumento'), {
            params: {
                documento: dni,
                ignore_id: isEditing.value ? currentId.value : null
            }
        });
        
        documentoStatus.value = response.data.available ? 'available' : 'unavailable';
    } catch (error) {
        console.error('Error verificando DNI:', error);
        documentoStatus.value = null;
    }
};

watch(() => form.documento, () => {
    if (documentoDebounceTimer) clearTimeout(documentoDebounceTimer);
    documentoDebounceTimer = setTimeout(() => {
        checkDocumento();
    }, 500);
});

// Lista de métodos de pago
const paymentMethods = [
    { value: 'EFECTIVO', label: 'Efectivo' },
    { value: 'MERCADO_PAGO', label: 'Mercado pago' },
    { value: 'TRANSFERENCIA', label: 'Transferencia bancaria' },
    { value: 'TARJETA_CREDITO', label: 'Tarjeta de crédito' },
    { value: 'TARJETA_DEBITO', label: 'Tarjeta de débito' }
];

const formCobro = useForm({
    pagos: [
        { monto: '', metodo_pago: 'EFECTIVO' }
    ]
});

const totalPagando = computed(() => {
    return formCobro.pagos.reduce((acc, p) => acc + (parseFloat(p.monto) || 0), 0);
});

const isExcediendoDeuda = computed(() => {
    if (!clienteSeleccionado.value) return false;
    const deuda = clienteSeleccionado.value.cuenta_corriente?.saldo_deudor || 0;
    return totalPagando.value > deuda;
});

let alertaExcedenteMostrada = false;
watch(isExcediendoDeuda, (excede) => {
    if (excede && !alertaExcedenteMostrada) {
        Swal.fire({
            icon: 'warning',
            title: '¡Atención!',
            text: 'El total a pagar supera la deuda del cliente. Revisá los montos.',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000
        });
        alertaExcedenteMostrada = true;
    } else if (!excede) {
        alertaExcedenteMostrada = false;
    }
});

const usedMethodsExcept = (excludeIndex = -1) => {
    return formCobro.pagos
        .map((p, idx) => (idx !== excludeIndex ? p.metodo_pago : null))
        .filter(m => m !== null);
};

const availableMethodsForRow = (index) => {
    const used = usedMethodsExcept(index);
    return paymentMethods.filter(m => !used.includes(m.value));
};

const fixDuplicateMethods = () => {
    let modified = false;
    const used = new Set();
    const newPagos = [...formCobro.pagos];

    for (let i = 0; i < newPagos.length; i++) {
        const method = newPagos[i].metodo_pago;
        if (used.has(method)) {
            const available = paymentMethods.find(m => !used.has(m.value));
            if (available) {
                newPagos[i].metodo_pago = available.value;
                used.add(available.value);
                modified = true;
            } else {
                Swal.fire('Atención', 'No hay más métodos de pago disponibles para esta fila.', 'warning');
            }
        } else {
            used.add(method);
        }
    }

    if (modified) {
        formCobro.pagos = newPagos;
    }
};

watch(() => formCobro.pagos, () => {
    fixDuplicateMethods();
}, { deep: true });

const agregarFilaPago = () => {
    const used = usedMethodsExcept();
    const availableMethod = paymentMethods.find(m => !used.includes(m.value));
    
    if (!availableMethod) {
        Swal.fire('Límite alcanzado', 'Ya estás usando todos los métodos de pago disponibles.', 'warning');
        return;
    }
    
    formCobro.pagos.push({ monto: '', metodo_pago: availableMethod.value });
};

const quitarFilaPago = (index) => {
    formCobro.pagos.splice(index, 1);
    fixDuplicateMethods();
};

const toggleMenu = (id) => {
    menuAbierto.value = menuAbierto.value === id ? null : id;
};

const cerrarMenu = () => {
    menuAbierto.value = null;
};

const openModal = (cliente = null) => {
    cerrarMenu();
    form.clearErrors();
    documentoStatus.value = null; // Resetear estado del DNI
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
    if (documentoDebounceTimer) clearTimeout(documentoDebounceTimer);
    documentoStatus.value = null;
    isModalOpen.value = false;
    form.reset();
    form.clearErrors();
};

const submitForm = () => {
    // Bloquear si el DNI no está disponible (y no es edición del mismo cliente)
    if (form.documento && documentoStatus.value !== 'available') {
        Swal.fire({
            icon: 'warning',
            title: 'DNI no disponible',
            text: 'El documento ingresado ya pertenece a otro cliente o tiene un formato inválido.',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000
        });
        return;
    }

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
    formCobro.pagos = [{ monto: cliente.cuenta_corriente?.saldo_deudor || 0, metodo_pago: 'EFECTIVO' }];
    alertaExcedenteMostrada = false;
    isCobroModalOpen.value = true;
};

const closeCobroModal = () => {
    isCobroModalOpen.value = false;
    formCobro.reset();
    clienteSeleccionado.value = null;
};

const submitCobro = () => {
    const metodosElegidos = formCobro.pagos.map(p => p.metodo_pago);
    const metodosUnicos = new Set(metodosElegidos);
    
    if (metodosElegidos.length !== metodosUnicos.size) {
        Swal.fire('Atención', 'No puedes seleccionar el mismo método de pago más de una vez.', 'warning');
        return; 
    }

    if (totalPagando.value > clienteSeleccionado.value?.cuenta_corriente?.saldo_deudor) {
        Swal.fire('Atención', 'El total pagando es mayor a la deuda del cliente.', 'warning');
        return;
    }

    formCobro.post(route('consumidores.cobrar', clienteSeleccionado.value.id), {
        onSuccess: () => {
            closeCobroModal();
            Swal.fire({ 
                icon: 'success', 
                title: 'Cobro Registrado', 
                text: 'El pago múltiple impactó en la cuenta y en tu caja abierta.',
                timer: 4000
            });
        },
        onError: (errors) => {
            if(errors.monto) {
                Swal.fire('Atención', errors.monto, 'warning');
            } else if (errors['pagos.0.metodo_pago'] || errors['pagos.1.metodo_pago']) {
                Swal.fire('Atención', 'Revisa los métodos de pago ingresados.', 'warning');
            }
        }
    });
};

const openHistorial = async (cliente) => {
    cerrarMenu();
    clienteSeleccionado.value = cliente;
    historialMovimientos.value = [];
    isHistorialModalOpen.value = true;

    try {
        const res = await axios.get(route('consumidores.cuenta', cliente.id));
        historialMovimientos.value = res.data;
    } catch (e) {
        Swal.fire('Error', 'No se pudo cargar el historial de cuenta.', 'error');
    }
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

const formatearFecha = (fecha) => {
    return new Date(fecha).toLocaleDateString('es-AR', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute:'2-digit' });
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

            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-visible p-4">
                <div class="overflow-visible relative">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100 text-xs uppercase tracking-widest text-slate-400">
                                <th class="p-4 font-black rounded-tl-3xl">ID</th>
                                <th class="p-4 font-black">Cliente</th>
                                <th class="p-4 font-black">Contacto</th>
                                <th class="p-4 font-black text-right">Límite Cta. Cte.</th>
                                <th class="p-4 font-black text-right">Deuda Actual</th>
                                <th class="p-4 font-black text-right">Disponible</th>
                                <th class="p-4 font-black text-center rounded-tr-3xl">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="consumidores.data.length === 0">
                                <td colspan="7" class="p-8 text-center text-slate-400 font-bold">No se encontraron clientes con esos filtros.</td>
                            </tr>
                            
                            <tr v-for="(cliente, index) in consumidores.data" :key="cliente.id" class="hover:bg-slate-50/50 transition-colors group" :class="{'bg-slate-50': !cliente.estado, 'relative z-50': menuAbierto === cliente.id}">
                                
                                <td class="p-4 font-bold text-slate-400" :class="{'opacity-50 grayscale': !cliente.estado}">#{{ cliente.id }}</td>
                                <td class="p-4" :class="{'opacity-50 grayscale': !cliente.estado}">
                                    <div class="font-bold text-slate-800">{{ cliente.nombre }} {{ cliente.apellido }}</div>
                                    <div class="text-xs font-medium mt-1" :class="cliente.estado ? 'text-emerald-500' : 'text-rose-500'">
                                        {{ cliente.estado ? 'Activo' : 'Inactivo' }}
                                    </div>
                                </td>
                                <td class="p-4" :class="{'opacity-50 grayscale': !cliente.estado}">
                                    <div class="text-xs text-slate-500 font-medium space-y-1">
                                        <div v-if="cliente.documento" title="Documento"><span class="font-bold text-slate-400">DOC:</span> {{ cliente.documento }}</div>
                                        <div v-if="cliente.telefono" title="Teléfono"><span class="font-bold text-slate-400">TEL:</span> {{ cliente.telefono }}</div>
                                        <div v-if="cliente.email" title="Email"><span class="font-bold text-slate-400">MAIL:</span> {{ cliente.email }}</div>
                                    </div>
                                </td>
                                
                                <td class="p-4 font-bold text-slate-600 text-right" :class="{'opacity-50 grayscale': !cliente.estado}">
                                    {{ formatearDinero(cliente.limite_cuenta_corriente) }}
                                </td>
                                
                                <td class="p-4 font-black text-right" :class="[{'opacity-50 grayscale': !cliente.estado}, cliente.cuenta_corriente?.saldo_deudor > 0 ? 'text-rose-600' : 'text-slate-400']">
                                    {{ formatearDinero(cliente.cuenta_corriente?.saldo_deudor) }}
                                </td>
                                
                                <td class="p-4 font-black text-right" :class="[{'opacity-50 grayscale': !cliente.estado}, calcularDisponible(cliente.limite_cuenta_corriente, cliente.cuenta_corriente?.saldo_deudor) <= 0 ? 'text-rose-500' : 'text-emerald-600']">
                                    {{ formatearDinero(calcularDisponible(cliente.limite_cuenta_corriente, cliente.cuenta_corriente?.saldo_deudor)) }}
                                </td>

                                <td class="p-4 text-center relative" :class="{'z-50': menuAbierto === cliente.id}">
                                    <button @click.stop="toggleMenu(cliente.id)" class="p-2 rounded-full text-slate-400 hover:text-sky-600 hover:bg-sky-100 transition-colors focus:outline-none">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg>
                                    </button>

                                    <div v-if="menuAbierto === cliente.id" 
                                         class="absolute right-10 w-52 bg-white rounded-xl shadow-2xl border border-slate-100 z-50 py-2 animate-in fade-in zoom-in-95 duration-150"
                                         :class="index === consumidores.data.length - 1 && consumidores.data.length > 2 ? 'bottom-8' : 'top-10'">
                                        
                                        <button v-if="cliente.cuenta_corriente?.saldo_deudor > 0" @click="openCobroModal(cliente)" class="w-full text-left px-4 py-2.5 text-xs font-bold text-emerald-600 hover:bg-emerald-50 flex items-center gap-3 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            Cobrar Deuda
                                        </button>

                                        <button v-if="cliente.cuenta_corriente" @click="openHistorial(cliente)" class="w-full text-left px-4 py-2.5 text-xs font-bold text-indigo-600 hover:bg-indigo-50 flex items-center gap-3 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                            Estado de Cuenta
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

        <!-- Modal Historial -->
        <div v-if="isHistorialModalOpen" class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
            <div class="bg-white rounded-3xl shadow-xl w-full max-w-3xl overflow-hidden flex flex-col max-h-[90vh]">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-indigo-50 shrink-0">
                    <div>
                        <h3 class="text-lg font-black text-indigo-900 uppercase tracking-tight flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                            Estado de Cuenta
                        </h3>
                        <p class="text-sm font-medium text-indigo-700 mt-1">{{ clienteSeleccionado?.nombre }} {{ clienteSeleccionado?.apellido }}</p>
                    </div>
                    <button @click="isHistorialModalOpen = false" class="text-slate-400 hover:text-rose-500 transition-colors bg-white p-2 rounded-xl shadow-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="overflow-y-auto flex-1 p-6 bg-slate-50">
                    <table class="w-full text-left border-collapse bg-white rounded-xl shadow-sm border border-slate-100">
                        <thead class="bg-slate-100">
                            <tr class="text-[10px] uppercase tracking-widest text-slate-500 border-b border-slate-200">
                                <th class="p-3 font-black rounded-tl-xl">Fecha</th>
                                <th class="p-3 font-black">Concepto / Detalle</th>
                                <th class="p-3 font-black text-right text-rose-600">Cargos (-)</th>
                                <th class="p-3 font-black text-right text-emerald-600">Abonos (+)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="historialMovimientos.length === 0">
                                <td colspan="4" class="p-8 text-center text-slate-400 font-bold">No hay movimientos registrados en esta cuenta.</td>
                            </tr>
                            <tr v-for="mov in historialMovimientos" :key="mov.id" class="hover:bg-slate-50 transition-colors">
                                <td class="p-3 font-medium text-slate-600 text-xs">{{ formatearFecha(mov.created_at) }}</td>
                                <td class="p-3">
                                    <div class="text-sm font-bold text-slate-800">{{ mov.descripcion }}</div>
                                    <div v-if="mov.venta" class="text-[10px] font-black text-sky-600 uppercase tracking-widest mt-1">Ticket #{{ mov.venta_id }}</div>
                                </td>
                                <td class="p-3 font-black text-right text-rose-600">
                                    {{ mov.tipo === 'cargo' ? formatearDinero(mov.monto) : '-' }}
                                </td>
                                <td class="p-3 font-black text-right text-emerald-600">
                                    {{ mov.tipo === 'abono' ? formatearDinero(mov.monto) : '-' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="p-4 border-t border-slate-200 bg-white flex justify-between items-center shrink-0">
                    <span class="text-xs font-black uppercase tracking-widest text-slate-400">Saldo Deudor Total</span>
                    <span class="text-2xl font-black text-rose-600">{{ formatearDinero(clienteSeleccionado?.cuenta_corriente?.saldo_deudor) }}</span>
                </div>
            </div>
        </div>

        <!-- Modal ABM Cliente -->
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
                        <!-- Campo Documento con validación en tiempo real -->
                        <div>
                            <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Documento</label>
                            <div class="relative">
                                <input 
                                    v-model="form.documento" 
                                    @input="form.documento = form.documento.replace(/\D/g, '')"
                                    maxlength="8"
                                    type="text" 
                                    placeholder="Ej: 30123456" 
                                    class="w-full bg-slate-50 border rounded-xl px-4 py-2.5 pr-10 focus:ring-sky-500 focus:border-sky-500 font-medium text-slate-700" 
                                    :class="{
                                        'border-rose-500': form.errors.documento || documentoStatus === 'unavailable',
                                        'border-emerald-500': documentoStatus === 'available' && !form.errors.documento,
                                        'border-slate-200': documentoStatus !== 'available' && documentoStatus !== 'unavailable' && !form.errors.documento
                                    }"
                                >
                                <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                                    <svg v-if="documentoStatus === 'checking'" class="w-5 h-5 text-slate-400 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <svg v-else-if="documentoStatus === 'available'" class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <svg v-else-if="documentoStatus === 'unavailable'" class="w-5 h-5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </div>
                            </div>
                            <p v-if="form.errors.documento" class="mt-1 text-xs text-rose-500 font-bold">{{ form.errors.documento }}</p>
                            <p v-if="documentoStatus === 'available' && !form.errors.documento" class="mt-1 text-xs text-emerald-600 font-bold flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                DNI disponible
                            </p>
                            <p v-if="documentoStatus === 'unavailable' && !form.errors.documento" class="mt-1 text-xs text-rose-500 font-bold flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                Este DNI ya está registrado
                            </p>
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

        <!-- Modal Cobro -->
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
                    <div class="bg-slate-50 border border-slate-100 rounded-xl p-4 mb-4">
                        <p class="text-xs font-black uppercase tracking-widest text-slate-500 mb-1">Cliente</p>
                        <p class="font-bold text-slate-800 text-lg">{{ clienteSeleccionado?.nombre }} {{ clienteSeleccionado?.apellido }}</p>
                        <div class="flex justify-between items-center mt-2 pt-2 border-t border-slate-200">
                            <span class="text-sm font-medium text-slate-500">Deuda Total:</span>
                            <span class="font-black text-rose-600 text-xl">{{ formatearDinero(clienteSeleccionado?.cuenta_corriente?.saldo_deudor) }}</span>
                        </div>
                    </div>

                    <form @submit.prevent="submitCobro" class="space-y-4">
                        <div v-for="(pago, idx) in formCobro.pagos" :key="idx" class="flex items-start gap-2 bg-white border border-emerald-100 p-3 rounded-xl relative">
                            <div class="flex-1 space-y-2">
                                <div>
                                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Monto ($)</label>
                                    <input 
                                        v-model="pago.monto" 
                                        type="number" step="0.01" min="1"
                                        class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 focus:ring-emerald-500 focus:border-emerald-500 font-bold text-slate-800 text-sm" 
                                        required
                                    >
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Medio de Pago</label>
                                    <select v-model="pago.metodo_pago" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 focus:ring-emerald-500 focus:border-emerald-500 font-medium text-slate-700 text-sm">
                                        <option 
                                            v-for="metodo in availableMethodsForRow(idx)" 
                                            :key="metodo.value" 
                                            :value="metodo.value"
                                        >
                                            {{ metodo.label }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <button v-if="formCobro.pagos.length > 1" @click.prevent="quitarFilaPago(idx)" type="button" class="mt-6 text-rose-400 hover:text-rose-600 bg-rose-50 p-2 rounded-lg">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            </button>
                        </div>

                        <button type="button" @click="agregarFilaPago" class="w-full py-2 border-2 border-dashed border-emerald-200 text-emerald-600 rounded-xl text-xs font-bold hover:bg-emerald-50 transition-colors uppercase tracking-widest">
                            + Agregar pago mixto
                        </button>

                        <div class="pt-4 mt-4 border-t border-slate-100">
                            <div class="flex justify-between items-center mb-4">
                                <span class="text-sm font-bold text-slate-600">Total a Pagar:</span>
                                <span class="font-black text-xl" :class="isExcediendoDeuda ? 'text-rose-600' : 'text-emerald-600'">
                                    {{ formatearDinero(totalPagando) }}
                                </span>
                            </div>

                            <div v-if="isExcediendoDeuda" class="mb-4 p-3 bg-rose-50 border border-rose-200 rounded-xl text-rose-700 text-sm font-bold flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                El monto total supera la deuda del cliente. Reducí algún importe.
                            </div>

                            <div class="flex justify-end gap-3">
                                <button type="button" @click="closeCobroModal" class="px-4 py-2 text-sm font-bold text-slate-500 hover:bg-slate-100 rounded-lg transition-colors">Cancelar</button>
                                <button type="submit" :disabled="formCobro.processing || totalPagando <= 0 || isExcediendoDeuda" class="bg-emerald-600 hover:bg-emerald-700 disabled:bg-slate-300 text-white font-bold py-2.5 px-6 rounded-xl shadow-sm shadow-emerald-600/30 transition-all flex items-center gap-2">
                                    <span v-if="formCobro.processing">Procesando...</span>
                                    <span v-else>Confirmar Pago</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>