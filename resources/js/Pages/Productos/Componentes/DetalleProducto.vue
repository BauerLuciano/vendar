<script setup>
defineProps({ mostrar: Boolean, producto: Object });
defineEmits(['cerrar']);
</script>

<template>
    <div v-if="mostrar && producto" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
            <div class="bg-slate-800 p-4 text-white text-center font-black uppercase tracking-widest text-xs">
                Ficha Técnica del Producto
            </div>
            
            <div class="p-6">
                <div class="flex justify-center mb-6">
                    <img :src="producto.url_imagen ? producto.url_imagen : '/img/sin-foto.png'" class="w-32 h-32 object-cover rounded-2xl shadow-inner border border-slate-100">
                </div>

                <h3 class="text-2xl font-black text-slate-800 text-center uppercase tracking-tighter leading-none mb-1">{{ producto.nombre }}</h3>
                <p class="text-slate-400 font-mono text-[10px] text-center uppercase tracking-widest mb-6">Cód: {{ producto.codigo_barras }}</p>

                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                        <span class="block text-[9px] font-black text-slate-400 uppercase tracking-widest">Categoría</span>
                        <span class="text-xs font-bold text-slate-700 uppercase">{{ producto.categoria?.nombreCategoria }}</span>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                        <span class="block text-[9px] font-black text-slate-400 uppercase tracking-widest">Marca</span>
                        <span class="text-xs font-bold text-slate-700 uppercase">{{ producto.marca?.nombreMarca }}</span>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-xl border border-slate-100 col-span-2">
                        <span class="block text-[9px] font-black text-slate-400 uppercase tracking-widest">Proveedor Principal</span>
                        <span class="text-xs font-bold text-indigo-700 uppercase">{{ producto.proveedor?.razon_social || 'Sin Proveedor' }}</span>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                        <span class="block text-[9px] font-black text-slate-400 uppercase tracking-widest">Unidad de Medida</span>
                        <span class="text-xs font-bold text-sky-700 uppercase">{{ producto.unidad_medida }}</span>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                        <span class="block text-[9px] font-black text-slate-400 uppercase tracking-widest">¿Es Retornable?</span>
                        <span class="text-xs font-bold uppercase" :class="producto.es_retornable ? 'text-amber-600' : 'text-slate-500'">{{ producto.es_retornable ? 'SÍ' : 'NO' }}</span>
                    </div>
                </div>

                <div class="bg-sky-50 border border-sky-100 p-4 rounded-xl flex justify-between items-center mb-6">
                    <span class="text-xs font-black text-sky-800 uppercase tracking-widest">Precio Público:</span>
                    <span class="text-2xl font-black text-sky-600 font-mono">${{ producto.precio_venta }}</span>
                </div>

                <button @click="$emit('cerrar')" class="w-full bg-slate-800 text-white py-3 rounded-xl font-bold hover:bg-slate-900 transition-all uppercase text-xs tracking-widest shadow-lg">
                    Cerrar Detalle
                </button>
            </div>
        </div>
    </div>
</template>