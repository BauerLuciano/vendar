<script setup>
defineProps({ mostrar: Boolean, rol: Object });
defineEmits(['cerrar']);
</script>

<template>
    <div v-if="mostrar" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all border border-slate-100">
            
            <div class="bg-slate-50 p-8 text-center border-b border-slate-100 relative">
                <div v-if="['SuperAdmin', 'Administrador Global'].includes(rol?.name)" 
                     class="absolute top-4 right-4 bg-rose-100 text-rose-600 text-[9px] font-black uppercase px-2 py-1 rounded-lg border border-rose-200">
                    Sistema
                </div>

                <div class="w-16 h-16 bg-sky-100 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-sm shadow-sky-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <h3 class="text-xl font-black text-slate-800 uppercase tracking-tight">{{ rol?.name }}</h3>
                <p class="text-xs text-slate-500 font-bold uppercase tracking-[0.1em] mt-1">Perfil de Usuario</p>
            </div>

            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Capacidades Asignadas</span>
                    <span class="bg-slate-100 text-slate-600 text-[10px] font-black px-2 py-0.5 rounded-md border border-slate-200">
                        {{ rol?.permissions?.length || 0 }} TOTAL
                    </span>
                </div>

                <div class="max-h-60 overflow-y-auto pr-2 custom-scrollbar">
                    <div v-if="rol?.permissions?.length > 0" class="flex flex-wrap gap-2">
                        <div v-for="permiso in rol.permissions" :key="permiso.id" 
                             class="flex items-center gap-1.5 bg-emerald-50 text-emerald-700 px-3 py-1.5 rounded-xl text-xs font-bold border border-emerald-100 hover:bg-emerald-100 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                            {{ permiso.name }}
                        </div>
                    </div>

                    <div v-else class="text-center py-8">
                        <div class="text-3xl mb-2">🚫</div>
                        <p class="text-sm text-slate-400 font-medium italic">
                            Este rol no cuenta con permisos de acceso configurados.
                        </p>
                    </div>
                </div>

                <div class="pt-6 border-t border-slate-100 mt-6">
                    <button 
                        @click="$emit('cerrar')" 
                        class="w-full bg-slate-900 hover:bg-black text-white px-4 py-3 rounded-2xl text-xs font-black uppercase tracking-widest transition-all shadow-lg shadow-slate-200"
                    >
                        Entendido
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent; 
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #e2e8f0; 
  border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: #cbd5e1; 
}
</style>