<script setup>
defineProps({ mostrar: Boolean, sucursal: Object });
defineEmits(['cerrar']);
</script>

<template>
    <div v-if="mostrar && sucursal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden border border-slate-100">
            <div class="bg-slate-800 p-4 text-white text-center font-black uppercase tracking-widest text-xs">
                Ficha Técnica de Sucursal
            </div>

            <div class="p-8">
                <div class="w-20 h-20 bg-sky-50 text-sky-600 rounded-2xl flex items-center justify-center mx-auto mb-6 shadow-inner border border-sky-100">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                </div>

                <div class="space-y-4 text-center">
                    <div>
                        <h3 class="text-2xl font-black text-slate-800 uppercase tracking-tighter leading-none">{{ sucursal.name }}</h3>
                        <p class="text-slate-400 font-mono text-[10px] mt-1 uppercase tracking-widest italic">ID de Registro: #{{ sucursal.id }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4 pt-4">
                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-100 text-left">
                            <span class="block text-[9px] font-black text-slate-400 uppercase tracking-widest">Tipo de Local</span>
                            <span class="text-xs font-bold text-sky-700 uppercase">{{ sucursal.type.replace('_', ' ') }}</span>
                        </div>
                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-100 text-left">
                            <span class="block text-[9px] font-black text-slate-400 uppercase tracking-widest">Teléfono</span>
                            <span class="text-xs font-bold text-slate-700">{{ sucursal.phone || 'N/A' }}</span>
                        </div>
                    </div>

                    <div class="bg-slate-50 p-3 rounded-xl border border-slate-100 text-left">
                        <span class="block text-[9px] font-black text-slate-400 uppercase tracking-widest">Dirección Física</span>
                        <span class="text-xs font-bold text-slate-700 uppercase italic">{{ sucursal.address || 'Sin dirección registrada' }}</span>
                    </div>

                    <div class="mt-4">
                        <span :class="sucursal.is_active ? 'text-emerald-600 bg-emerald-50 border-emerald-200' : 'text-rose-600 bg-rose-50 border-rose-200'" 
                              class="px-6 py-1.5 rounded-full text-[10px] font-black uppercase tracking-[0.2em] border shadow-sm">
                            {{ sucursal.is_active ? 'Sucursal Operativa' : 'Sucursal Inactiva' }}
                        </span>
                    </div>
                </div>

                <button @click="$emit('cerrar')" class="mt-8 w-full bg-slate-800 text-white py-3 rounded-xl font-bold hover:bg-slate-900 transition-all active:scale-95 shadow-lg shadow-slate-200 uppercase text-xs tracking-widest">
                    Cerrar Detalle
                </button>
            </div>
        </div>
    </div>
</template>