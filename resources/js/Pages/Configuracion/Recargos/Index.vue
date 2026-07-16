<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({
    recargos: Array,
});

const CUOTAS_FIJAS = [1, 2, 3, 6, 9, 12, 18, 24];

const showForm = ref(false);
const editingBanco = ref(null);
const editingTipo = ref(null);

const formBanco = ref('');
const formTipo = ref('CREDITO');
const formCuotas = ref({});

function openNewForm() {
    editingBanco.value = null;
    editingTipo.value = null;
    formBanco.value = '';
    formTipo.value = 'CREDITO';
    formCuotas.value = Object.fromEntries(CUOTAS_FIJAS.map(n => [n, 0]));
    showForm.value = true;
}

function editGroup(group) {
    editingBanco.value = group.banco;
    editingTipo.value = group.tipo_tarjeta;
    formBanco.value = group.banco;
    formTipo.value = group.tipo_tarjeta;
    formCuotas.value = Object.fromEntries(
        CUOTAS_FIJAS.map(n => [n, group.cuotas[n]?.porcentaje || 0])
    );
    showForm.value = true;
}

function cancelForm() {
    showForm.value = false;
    editingBanco.value = null;
    editingTipo.value = null;
}

async function saveGroup() {
    if (!formBanco.value.trim()) {
        Swal.fire('Error', 'El nombre del banco es requerido', 'error');
        return;
    }

    const cuotasPayload = CUOTAS_FIJAS.map(n => ({
        cuotas: n,
        porcentaje: Number(formCuotas.value[n]) || 0,
    }));

    const hasAny = cuotasPayload.some(c => c.porcentaje > 0);
    if (!hasAny) {
        Swal.fire('Error', 'Configurá al menos una cuota con porcentaje mayor a 0', 'error');
        return;
    }

    try {
        await axios.post(route('recargos.saveGrouped'), {
            banco: formBanco.value.trim(),
            tipo_tarjeta: formTipo.value,
            cuotas: cuotasPayload,
        });
        cancelForm();
        router.reload({ only: ['recargos'] });
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Configuración guardada', showConfirmButton: false, timer: 2000 });
    } catch (e) {
        Swal.fire('Error', e.response?.data?.message || 'Error al guardar', 'error');
    }
}

function deleteGroup(group) {
    Swal.fire({
        title: '¿Eliminar configuración?',
        text: `Se eliminarán todos los recargos de ${group.banco} (${getTipoLabel(group.tipo_tarjeta)}).`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Eliminar',
        cancelButtonText: 'Cancelar',
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                await axios.delete(route('recargos.destroyGrouped'), {
                    data: { banco: group.banco, tipo_tarjeta: group.tipo_tarjeta },
                });
                router.reload({ only: ['recargos'] });
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Eliminado', showConfirmButton: false, timer: 2000 });
            } catch (e) {
                Swal.fire('Error', 'No se pudo eliminar', 'error');
            }
        }
    });
}

function getTipoLabel(tipo) {
    return tipo === 'DEBITO' ? 'Débito' : 'Crédito';
}

function getTipoColor(tipo) {
    return tipo === 'DEBITO'
        ? 'bg-blue-100 text-blue-700 border-blue-200'
        : 'bg-violet-100 text-violet-700 border-violet-200';
}


</script>

<template>
    <Head title="Recargos por Tarjeta" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Recargos por Tarjeta</h2>
                    <p class="text-sm text-gray-500 mt-0.5">Configurá los recargos por banco y tipo de tarjeta</p>
                </div>
                <button
                    @click="openNewForm"
                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-900 text-white rounded-xl text-sm font-bold hover:bg-slate-800 transition-colors shadow-sm"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                    Nuevo Banco
                </button>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">

                <!-- Formulario inline -->
                <div v-if="showForm" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 mb-6">
                    <div class="flex items-center justify-between mb-5">
                        <h3 class="text-lg font-bold text-slate-900">
                            {{ editingBanco ? 'Editar' : 'Nuevo' }} Banco
                        </h3>
                        <button @click="cancelForm" class="text-slate-400 hover:text-slate-600 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <!-- Banco + Tipo -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Nombre del banco</label>
                            <input
                                v-model="formBanco"
                                type="text"
                                placeholder="Ej: Macro, Galicia..."
                                class="w-full px-4 py-2.5 border-2 border-slate-200 rounded-xl text-sm font-bold text-slate-800 focus:border-slate-900 focus:ring-0 transition-colors"
                                :disabled="!!editingBanco"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Tipo de tarjeta</label>
                            <div class="flex gap-2">
                                <button
                                    @click="formTipo = 'DEBITO'"
                                    class="flex-1 py-2.5 rounded-xl border-2 text-sm font-bold transition-all"
                                    :class="formTipo === 'DEBITO'
                                        ? 'bg-blue-500 border-blue-500 text-white'
                                        : 'bg-white border-slate-200 text-slate-600 hover:border-blue-300'"
                                >
                                    Débito
                                </button>
                                <button
                                    @click="formTipo = 'CREDITO'"
                                    class="flex-1 py-2.5 rounded-xl border-2 text-sm font-bold transition-all"
                                    :class="formTipo === 'CREDITO'
                                        ? 'bg-violet-500 border-violet-500 text-white'
                                        : 'bg-white border-slate-200 text-slate-600 hover:border-violet-300'"
                                >
                                    Crédito
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Cuotas grid (solo Crédito) -->
                    <div v-if="formTipo === 'CREDITO'" class="mb-6">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Cuotas y recargos</label>
                        <div class="grid grid-cols-4 sm:grid-cols-8 gap-3">
                            <div v-for="n in CUOTAS_FIJAS" :key="n" class="text-center">
                                <div class="text-xs font-bold text-slate-600 mb-1">{{ n }}</div>
                                <div class="relative">
                                    <input
                                        v-model.number="formCuotas[n]"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.5"
                                        placeholder="0"
                                        class="w-full text-center px-2 py-2 border-2 border-slate-200 rounded-xl text-sm font-bold text-slate-800 focus:border-slate-900 focus:ring-0 transition-colors"
                                    />
                                    <span class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-slate-400 font-bold">%</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Acciones -->
                    <div class="flex items-center justify-end gap-3">
                        <button
                            @click="cancelForm"
                            class="px-4 py-2 text-sm font-bold text-slate-600 hover:text-slate-800 transition-colors"
                        >
                            Cancelar
                        </button>
                        <button
                            @click="saveGroup"
                            class="px-6 py-2.5 bg-slate-900 text-white rounded-xl text-sm font-bold hover:bg-slate-800 transition-colors shadow-sm"
                        >
                            {{ editingBanco ? 'Actualizar' : 'Guardar' }}
                        </button>
                    </div>
                </div>

                <!-- Lista de bancos -->
                <div v-if="recargos.length === 0 && !showForm" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-12 text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-slate-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                    <p class="text-slate-500 font-bold mb-1">No hay bancos configurados</p>
                    <p class="text-sm text-slate-400">Creá tu primer banco para empezar a configurar recargos</p>
                </div>

                <div class="space-y-4">
                    <div
                        v-for="group in recargos"
                        :key="group.banco + group.tipo_tarjeta"
                        class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden"
                    >
                        <!-- Header de la card -->
                        <div class="px-6 py-4 flex items-center justify-between border-b border-slate-100">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-slate-900 flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-slate-900">{{ group.banco }}</h3>
                                    <span class="inline-block text-[10px] font-bold px-2 py-0.5 rounded-full border mt-0.5" :class="getTipoColor(group.tipo_tarjeta)">
                                        {{ getTipoLabel(group.tipo_tarjeta) }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button
                                    @click="editGroup(group)"
                                    class="px-3 py-1.5 text-xs font-bold text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-lg transition-colors"
                                >
                                    Editar
                                </button>
                                <button
                                    @click="deleteGroup(group)"
                                    class="px-3 py-1.5 text-xs font-bold text-red-500 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors"
                                >
                                    Eliminar
                                </button>
                            </div>
                        </div>

                        <!-- Cuotas configuradas (solo Crédito) -->
                        <div v-if="group.tipo_tarjeta === 'CREDITO'" class="px-6 py-4">
                            <div class="flex flex-wrap gap-2">
                                <div
                                    v-for="n in CUOTAS_FIJAS.filter(c => c === 1 || (group.cuotas[c]?.porcentaje || 0) > 0)"
                                    :key="n"
                                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border"
                                    :class="n === 1 || group.cuotas[n]?.porcentaje > 0
                                        ? 'bg-slate-50 border-slate-200'
                                        : 'bg-transparent border-transparent'"
                                >
                                    <span class="text-sm font-bold text-slate-700">{{ n }}</span>
                                    <span class="text-[10px] text-slate-400">{{ n === 1 ? 'cuota' : 'cuotas' }}</span>
                                    <template v-if="n > 0 && group.cuotas[n]?.porcentaje > 0">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                        <span class="text-sm font-black text-slate-900">{{ group.cuotas[n].porcentaje }}%</span>
                                    </template>
                                    <template v-else-if="n === 1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                        <span class="text-sm font-black text-emerald-600">0%</span>
                                    </template>
                                </div>
                            </div>
                        </div>
                        <div v-else class="px-6 py-4">
                            <span class="text-sm text-slate-400 font-medium">Sin cuotas — pago al contado</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
