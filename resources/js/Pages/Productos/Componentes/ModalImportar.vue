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
                        <input ref="inputFile" type="file" accept=".csv,.txt" class="hidden" @change="manejarArchivo">
                        <svg class="w-10 h-10 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                        <p class="text-sm font-medium text-slate-600">Arrastrá un archivo CSV acá</p>
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

                    <div v-if="archivo" class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 text-xs text-amber-800">
                        <p class="font-medium mb-1">Formato esperado:</p>
                        <code class="text-amber-700">nombre, codigo_barras, categoria, marca, proveedor, precio_costo, precio_venta, stock_minimo, unidad_medida</code>
                        <p class="mt-1">Si el código de barras ya existe, se actualiza el producto. Si no, se crea uno nuevo.</p>
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

const emit = defineEmits(['cerrar', 'completado']);

const dragOver = ref(false);
const archivo = ref(null);
const cargando = ref(false);
const inputFile = ref(null);

const manejarDrop = (e) => {
    dragOver.value = false;
    const file = e.dataTransfer.files[0];
    if (file && (file.type === 'text/csv' || file.name.endsWith('.csv') || file.name.endsWith('.txt'))) {
        archivo.value = file;
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
        await axios.post(route('productos.importar'), form);
        emit('completado');
    } finally {
        cargando.value = false;
    }
};
</script>
