<script setup>
defineProps({
    mostrar: Boolean,
    usuario: Object
});
defineEmits(['cerrar']);
</script>

<template>
    <div v-if="mostrar" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden animate-in zoom-in-95 duration-200">
            <div class="bg-gradient-to-r from-[#0369a1] to-[#0ea5e9] p-8 text-center relative">
                <button @click="$emit('cerrar')" class="absolute top-4 right-4 text-white hover:text-sky-200 font-bold text-xl">&times;</button>
                
                <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center text-4xl font-black text-[#0369a1] mx-auto shadow-lg border-4 border-sky-100/50 mb-3">
                    {{ usuario?.name.charAt(0).toUpperCase() }}
                </div>
                <h3 class="text-xl font-bold text-white">{{ usuario?.name }}</h3>
                <p class="text-sky-100 text-xs font-medium">{{ usuario?.email }}</p>
            </div>

            <div class="p-6 space-y-4">
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Rol del Sistema</p>
                    <p class="text-sm font-bold text-slate-700">
                        {{ usuario?.roles?.length > 0 ? usuario.roles[0].name : 'Sin rol asignado' }}
                    </p>
                </div>
                
                <div class="border-t border-slate-100 pt-4">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Sucursales</p>
                    <div v-if="usuario?.sucursales?.length" class="flex flex-wrap gap-1.5 mt-1.5">
                        <span v-for="suc in usuario.sucursales" :key="suc.id"
                            class="text-[11px] font-bold px-2.5 py-1 rounded-full"
                            :class="suc.id === usuario.branch_id ? 'bg-sky-100 text-sky-800' : 'bg-slate-100 text-slate-600'">
                            {{ suc.nombre }}<span v-if="suc.id === usuario.branch_id" class="ml-1 text-[10px] opacity-70">(base)</span>
                        </span>
                    </div>
                    <p v-else class="text-sm font-bold text-slate-700">
                        📍 {{ usuario?.branch?.nombre || 'No asignada' }}
                    </p>
                </div>

                <div class="border-t border-slate-100 pt-4">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Fecha de Registro</p>
                    <p class="text-xs font-semibold text-slate-500">
                        {{ new Date(usuario?.created_at).toLocaleDateString('es-AR', { year: 'numeric', month: 'long', day: 'numeric' }) }}
                    </p>
                </div>
            </div>

            <div class="p-4 bg-slate-50 text-center border-t border-slate-100">
                <button @click="$emit('cerrar')" class="w-full bg-slate-200 text-slate-600 px-4 py-2 rounded-lg text-xs font-bold uppercase tracking-widest hover:bg-slate-300 transition-colors">
                    Cerrar Detalle
                </button>
            </div>
        </div>
    </div>
</template>