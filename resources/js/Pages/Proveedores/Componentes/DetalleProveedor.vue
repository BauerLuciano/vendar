<script setup>
defineProps({ mostrar: Boolean, proveedor: Object });
defineEmits(['cerrar']);
</script>

<template>
    <div v-if="mostrar && proveedor" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
            <div class="bg-slate-800 p-4 text-white text-center font-black uppercase tracking-widest text-xs">Ficha de Proveedor</div>
            <div class="p-8 text-center">
                <div class="w-16 h-16 bg-sky-50 text-sky-600 rounded-2xl flex items-center justify-center mx-auto mb-4 border border-sky-100 shadow-inner">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                </div>
                <h3 class="text-xl font-black text-slate-800 uppercase">{{ proveedor.razon_social }}</h3>
                <p class="text-slate-500 font-mono text-sm mt-1 mb-4 border-b pb-4">CUIT: {{ proveedor.cuit }}</p>
                
                <div class="space-y-2 text-left text-sm mt-4">
                    <p><strong class="text-slate-400 uppercase text-[10px]">Teléfono:</strong> <br> {{ proveedor.telefono || '-' }}</p>
                    <p><strong class="text-slate-400 uppercase text-[10px]">Email:</strong> <br> {{ proveedor.email || '-' }}</p>
                </div>
                
                <div class="mt-6 p-2 rounded-xl border text-center" :class="proveedor.estado ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-rose-50 text-rose-600 border-rose-100'">
                    <span class="text-[10px] font-black uppercase tracking-widest">Estado: {{ proveedor.estado ? 'Activo' : 'Inactivo' }}</span>
                </div>

                <button @click="$emit('cerrar')" class="mt-8 w-full bg-slate-800 text-white py-3 rounded-xl font-bold hover:bg-slate-900 transition-all uppercase text-xs tracking-widest">Cerrar</button>
            </div>
        </div>
    </div>
</template>