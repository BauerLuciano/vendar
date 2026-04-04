<script setup>
defineProps({ mostrar: Boolean, categoria: Object });
defineEmits(['cerrar']);
</script>

<template>
    <div v-if="mostrar && categoria" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
            <div class="bg-slate-800 p-4 text-white text-center font-black uppercase tracking-widest text-xs">Ficha de Categoría</div>
            <div class="p-8 text-center">
                <div class="w-16 h-16 bg-sky-50 text-sky-600 rounded-2xl flex items-center justify-center mx-auto mb-4 border border-sky-100 shadow-inner">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" /></svg>
                </div>
                <h3 class="text-2xl font-black text-slate-800 uppercase">{{ categoria.nombreCategoria }}</h3>
                <p class="text-slate-400 font-mono text-[10px] mt-1 uppercase tracking-widest italic">ID Interno: #{{ categoria.id }}</p>
                
                <div class="mt-4 bg-slate-50 p-4 rounded-xl border border-slate-100 text-left">
                    <span class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Descripción</span>
                    <p class="text-sm text-slate-600 italic">{{ categoria.descripcion || 'Sin descripción detallada.' }}</p>
                </div>
                
                <div class="mt-4 p-3 rounded-xl border" :class="categoria.estado ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-rose-50 text-rose-600 border-rose-100'">
                    <span class="text-[10px] font-black uppercase tracking-widest">Estado: {{ categoria.estado ? 'Activa' : 'Inactiva' }}</span>
                </div>

                <button @click="$emit('cerrar')" class="mt-8 w-full bg-slate-800 text-white py-3 rounded-xl font-bold hover:bg-slate-900 transition-all uppercase text-xs tracking-widest shadow-lg">Cerrar</button>
            </div>
        </div>
    </div>
</template>