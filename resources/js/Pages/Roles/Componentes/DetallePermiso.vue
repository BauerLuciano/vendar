<script setup>
defineProps({ mostrar: Boolean, permiso: Object });
defineEmits(['cerrar']);
</script>

<template>
    <div v-if="mostrar" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-sm overflow-hidden transform transition-all border border-slate-100">
            
            <div class="bg-slate-50 p-8 text-center border-b border-slate-100">
                <div class="w-16 h-16 bg-indigo-100 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-sm shadow-indigo-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                    </svg>
                </div>
                <h3 class="text-sm font-black text-slate-400 uppercase tracking-[0.2em]">Detalle del Permiso</h3>
            </div>

            <div class="p-6 space-y-6">
                
                <div>
                    <span class="text-[10px] font-black text-indigo-500 uppercase tracking-widest block mb-1">Identificador del Sistema</span>
                    <div class="bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 font-mono text-sm text-slate-700 break-all">
                        {{ permiso?.name }}
                    </div>
                </div>

                <div>
                    <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest block mb-1">Alcance y Función</span>
                    <p class="text-sm text-slate-600 font-medium leading-relaxed">
                        {{ permiso?.description || 'Este permiso no tiene una descripción detallada cargada todavía.' }}
                    </p>
                </div>

                <div class="pt-4">
                    <button 
                        @click="$emit('cerrar')" 
                        class="w-full bg-slate-900 hover:bg-black text-white px-4 py-3 rounded-2xl text-xs font-black uppercase tracking-widest transition-all shadow-lg shadow-slate-200"
                    >
                        Volver al Listado
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>