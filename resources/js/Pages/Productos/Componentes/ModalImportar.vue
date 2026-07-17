<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="$emit('cerrar')">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-slate-800">Importar Productos</h3>
                    <button @click="$emit('cerrar')" class="text-slate-400 hover:text-slate-600 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="p-6 space-y-4">
                    <div
                        @dragover.prevent="dragOver = true"
                        @dragleave="dragOver = false"
                        @drop.prevent="manejarDrop"
                        :class="['border-2 border-dashed rounded-xl p-8 text-center transition-all cursor-pointer', dragOver ? 'border-blue-400 bg-blue-50' : 'border-slate-200 hover:border-slate-300 bg-slate-50']"
                        @click="$refs.inputFile.click()"
                    >
                        <input ref="inputFile" type="file" accept=".csv,.txt,.xlsx,.xls" class="hidden" @change="manejarArchivo">
                        <svg class="w-10 h-10 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                        <p class="text-sm font-medium text-slate-600">Arrastrá un archivo CSV o Excel acá</p>
                        <p class="text-xs text-slate-400 mt-1">o hacé click para seleccionarlo</p>
                    </div>

                    <div v-if="archivo" class="flex items-center gap-3 bg-slate-50 rounded-lg px-4 py-3">
                        <svg class="w-5 h-5 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span class="text-sm font-medium text-slate-700 truncate">{{ archivo.name }}</span>
                        <span class="text-xs text-slate-400">({{ (archivo.size / 1024).toFixed(1) }} KB)</span>
                        <button @click="archivo = null" class="ml-auto text-slate-400 hover:text-red-500">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <div class="bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-200 rounded-xl px-5 py-4 text-xs">
                        <div class="flex items-center justify-between mb-3">
                            <p class="font-bold text-sm text-slate-700 flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            ¿Cómo preparo mi archivo?
                            </p>
                            <a :href="route('productos.plantilla')" class="inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg px-3 py-1.5 font-medium text-xs shadow-sm transition-all">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                Descargar plantilla
                            </a>
                        </div>

                        <div class="bg-white/60 rounded-lg px-3 py-2 mb-3">
                            <p class="font-semibold text-rose-600 text-[11px] uppercase tracking-wide mb-1">Obligatorios</p>
                            <div class="flex flex-wrap gap-1.5">
                                <span class="inline-flex items-center gap-1 bg-rose-100 text-rose-700 rounded-full px-2.5 py-0.5 font-medium">Nombre</span>
                                <span class="inline-flex items-center gap-1 bg-rose-100 text-rose-700 rounded-full px-2.5 py-0.5 font-medium">Código de barras</span>
                                <span class="text-slate-400 self-center">o</span>
                                <span class="inline-flex items-center gap-1 bg-rose-100 text-rose-700 rounded-full px-2.5 py-0.5 font-medium">PLU</span>
                            </div>
                        </div>

                        <div class="bg-white/60 rounded-lg px-3 py-2 mb-3">
                            <p class="font-semibold text-emerald-600 text-[11px] uppercase tracking-wide mb-1">Opcionales</p>
                            <div class="flex flex-wrap gap-1.5">
                                <span class="inline-flex items-center bg-emerald-100 text-emerald-700 rounded-full px-2.5 py-0.5 font-medium">Categoría</span>
                                <span class="inline-flex items-center bg-emerald-100 text-emerald-700 rounded-full px-2.5 py-0.5 font-medium">Marca</span>
                                <span class="inline-flex items-center bg-emerald-100 text-emerald-700 rounded-full px-2.5 py-0.5 font-medium">Proveedor</span>
                                <span class="inline-flex items-center bg-emerald-100 text-emerald-700 rounded-full px-2.5 py-0.5 font-medium">Precio costo</span>
                                <span class="inline-flex items-center bg-emerald-100 text-emerald-700 rounded-full px-2.5 py-0.5 font-medium">Precio venta</span>
                                <span class="inline-flex items-center bg-emerald-100 text-emerald-700 rounded-full px-2.5 py-0.5 font-medium">Stock mínimo</span>
                                <span class="inline-flex items-center bg-emerald-100 text-emerald-700 rounded-full px-2.5 py-0.5 font-medium">Unidad</span>
                                <span class="inline-flex items-center bg-emerald-100 text-emerald-700 rounded-full px-2.5 py-0.5 font-medium">Descripción</span>
                                <span class="inline-flex items-center bg-emerald-100 text-emerald-700 rounded-full px-2.5 py-0.5 font-medium">Retornable</span>
                                <span class="inline-flex items-center bg-emerald-100 text-emerald-700 rounded-full px-2.5 py-0.5 font-medium">Estado</span>
                            </div>
                        </div>

                        <div class="bg-amber-100/50 border border-amber-200 rounded-lg px-3 py-2">
                            <p class="font-semibold text-amber-700 text-[11px] uppercase tracking-wide mb-1">Tip</p>
                            <p class="text-amber-800 leading-relaxed">
                                Podés exportar tu listado, editarlo en Excel y volver a importarlo.
                                Si el código de barras ya existe, solo se actualizan los campos que tengan datos — los vacíos se ignoran.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-end gap-3">
                    <button @click="$emit('cerrar')" class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-800 transition-colors">Cancelar</button>
                    <button @click="importar" :disabled="!archivo || cargando" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white px-5 py-2.5 rounded-lg font-medium text-sm shadow-sm transition-all">
                        <svg v-if="cargando" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        {{ cargando ? 'Importando...' : 'Importar' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const emit = defineEmits(['cerrar', 'completado']);

const dragOver = ref(false);
const archivo = ref(null);
const cargando = ref(false);
const inputFile = ref(null);

const manejarDrop = (e) => {
    dragOver.value = false;
    const file = e.dataTransfer.files[0];
    if (file) {
        const ext = file.name.split('.').pop().toLowerCase();
        if (['csv', 'txt', 'xlsx', 'xls'].includes(ext)) {
            archivo.value = file;
        }
    }
};

const manejarArchivo = (e) => {
    const file = e.target.files[0];
    if (file) archivo.value = file;
};

const importar = async () => {
    if (!archivo.value) return;
    cargando.value = true;
    try {
        const form = new FormData();
        form.append('archivo', archivo.value);
        const res = await axios.post(route('productos.importar'), form);
        const data = res.data;

        if (data?.success && data?.resumen) {
            const r = data.resumen;
            let html = `
                <div class="text-left text-sm space-y-1.5">
                    <div class="flex justify-between"><span class="text-slate-600">Total filas:</span><span class="font-bold text-slate-800">${r.total_filas}</span></div>
                    <div class="flex justify-between"><span class="text-emerald-600">Productos creados:</span><span class="font-bold text-emerald-700">${r.creados}</span></div>
                    <div class="flex justify-between"><span class="text-sky-600">Productos actualizados:</span><span class="font-bold text-sky-700">${r.actualizados}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Filas omitidas:</span><span class="font-bold text-slate-600">${r.omitidos}</span></div>
                </div>`;

            if (r.conflictos > 0) {
                html += `<div class="flex justify-between mt-1 pt-1 border-t border-slate-200"><span class="text-rose-600">Conflictos:</span><span class="font-bold text-rose-700">${r.conflictos}</span></div>`;
            }
            if (r.warnings > 0) {
                html += `<div class="flex justify-between"><span class="text-amber-600">Warnings:</span><span class="font-bold text-amber-700">${r.warnings}</span></div>`;
            }

            if (data.errores?.length > 0) {
                html += `<div class="mt-3 pt-2 border-t border-slate-200"><p class="font-bold text-rose-700 text-xs mb-1">Detalle de errores:</p><ul class="text-xs text-rose-600 space-y-0.5 max-h-32 overflow-y-auto">`;
                data.errores.forEach(e => {
                    html += `<li>Fila ${e.fila}: ${e.mensaje}</li>`;
                });
                html += `</ul></div>`;
            }

            if (data.conflictos?.length > 0) {
                html += `<div class="mt-2 pt-2 border-t border-slate-200"><p class="font-bold text-rose-700 text-xs mb-1">Conflictos:</p><ul class="text-xs text-rose-600 space-y-0.5 max-h-32 overflow-y-auto">`;
                data.conflictos.forEach(c => {
                    html += `<li>Fila ${c.fila}: ${c.mensaje}</li>`;
                });
                html += `</ul></div>`;
            }

            if (data.warnings?.length > 0) {
                html += `<div class="mt-2 pt-2 border-t border-slate-200"><p class="font-bold text-amber-700 text-xs mb-1">Observaciones:</p><ul class="text-xs text-amber-600 space-y-0.5 max-h-32 overflow-y-auto">`;
                data.warnings.forEach(w => {
                    html += `<li>Fila ${w.fila}: ${w.mensaje}</li>`;
                });
                html += `</ul></div>`;
            }

            const hasErrors = r.conflictos > 0 || (data.errores?.length > 0);
            Swal.fire({
                title: hasErrors ? 'Importación con observaciones' : '¡Importación completada!',
                html: html,
                icon: hasErrors ? 'warning' : 'success',
                showConfirmButton: true,
                confirmButtonText: 'Entendido',
                width: '600px',
            }).then(() => {
                emit('completado');
            });
        } else if (data?.error) {
            Swal.fire({ title: 'Error', text: data.error, icon: 'error' });
        } else {
            Swal.fire({ title: 'Importado', text: 'Importación completada.', icon: 'success', timer: 10000, showConfirmButton: false });
            emit('completado');
        }
    } catch (err) {
        let msg = 'Error al importar el archivo.';
        if (err.response?.status === 422) {
            const errors = err.response.data?.errors;
            if (errors?.archivo) {
                msg = Array.isArray(errors.archivo) ? errors.archivo[0] : errors.archivo;
            } else if (err.response.data?.error) {
                msg = err.response.data.error;
            } else if (err.response.data?.message) {
                msg = err.response.data.message;
            }
        } else if (err.response?.data?.error) {
            msg = err.response.data.error;
        } else if (err.response?.data?.message) {
            msg = err.response.data.message;
        }
        Swal.fire({ title: 'Error', text: msg, icon: 'error' });
    } finally {
        cargando.value = false;
    }
};
</script>
