<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    productos: Array,
    clientes: Array
});

// Estado
const buscar = ref('');
const carrito = ref([]);
const metodoPago = ref('efectivo');
const clienteSeleccionado = ref(1); // Consumidor Final por defecto

// Buscador Reactivo
const productosFiltrados = computed(() => {
    if (buscar.value.length < 2) return [];
    return props.productos.filter(p => 
        p.nombre.toLowerCase().includes(buscar.value.toLowerCase()) || 
        p.sku.includes(buscar.value)
    );
});

const agregarAlCarrito = (producto) => {
    const existe = carrito.value.find(item => item.id === producto.id);
    if (existe) {
        existe.cantidad++;
    } else {
        carrito.value.push({ ...producto, cantidad: 1 });
    }
    buscar.value = '';
};

const eliminarDelCarrito = (index) => {
    carrito.value.splice(index, 1);
};

const totalVenta = computed(() => {
    return carrito.value.reduce((acc, item) => acc + (item.precio_venta * item.cantidad), 0);
});

const finalizarVenta = () => {
    if (carrito.value.length === 0) return;
    
    router.post(route('ventas.store'), {
        cliente_id: clienteSeleccionado.value,
        items: carrito.value,
        total_venta: totalVenta.value,
        metodo_pago: metodoPago.value
    }, {
        onSuccess: () => {
            carrito.value = [];
            alert("¡Venta cobrada con éxito!");
        }
    });
};
</script>

<template>
    <Head title="Ventas (POS)" />

    <AuthenticatedLayout>
        <div class="py-6 px-4 sm:px-6 lg:px-8 bg-slate-50 min-h-screen">
            <div class="mb-8">
                <h1 class="text-2xl font-black text-slate-800 uppercase tracking-tight">Caja / Punto de Venta</h1>
                <div class="h-1 w-12 bg-sky-500 mt-1"></div>
            </div>

            <div class="grid grid-cols-12 gap-6">
                <div class="col-span-12 lg:col-span-8 space-y-6">
                    <div class="bg-white p-2 rounded-2xl shadow-sm border border-slate-200">
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                            </span>
                            <input 
                                v-model="buscar"
                                type="text" 
                                placeholder="Escaneá código de barras o buscá por nombre..."
                                class="block w-full pl-12 pr-4 py-4 bg-transparent border-none focus:ring-0 text-lg font-medium text-slate-700"
                                autofocus
                            />
                        </div>
                    </div>

                    <div v-if="productosFiltrados.length > 0" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        <div 
                            v-for="p in productosFiltrados" :key="p.id"
                            @click="agregarAlCarrito(p)"
                            class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100 hover:border-sky-500 hover:shadow-md transition-all cursor-pointer group"
                        >
                            <div class="flex items-center gap-4">
                                <div class="w-16 h-16 bg-slate-100 rounded-xl overflow-hidden flex items-center justify-center border border-slate-50">
                                    <img v-if="p.imagen" :src="'/storage/' + p.imagen" class="w-full h-full object-cover" />
                                    <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                                </div>
                                <div>
                                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">{{ p.sku }}</p>
                                    <p class="font-bold text-slate-800 leading-tight group-hover:text-sky-600 transition-colors">{{ p.nombre }}</p>
                                    <p class="text-sky-600 font-black mt-1 text-lg">${{ p.precio_venta }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-4">
                    <div class="bg-white rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-100 flex flex-col h-[calc(100vh-180px)] sticky top-6">
                        <div class="p-6 border-b border-slate-50 flex justify-between items-center">
                            <h2 class="text-xl font-black text-slate-800 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-sky-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                                Ticket
                            </h2>
                            <select v-model="clienteSeleccionado" class="text-xs font-bold border-none bg-slate-100 rounded-lg focus:ring-sky-500">
                                <option v-for="c in clientes" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                            </select>
                        </div>

                        <div class="flex-1 overflow-y-auto p-6 space-y-4">
                            <div v-if="carrito.length === 0" class="h-full flex flex-col items-center justify-center text-slate-300">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                                <p class="font-bold">Sin productos</p>
                            </div>

                            <div v-for="(item, index) in carrito" :key="item.id" class="flex justify-between items-start group animate-in fade-in slide-in-from-right-4 duration-200">
                                <div class="flex flex-col">
                                    <span class="font-bold text-slate-700 text-sm leading-tight">{{ item.nombre }}</span>
                                    <span class="text-[10px] text-slate-400 font-black uppercase">{{ item.cantidad }} x ${{ item.precio_venta }}</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="font-black text-slate-800">${{ item.cantidad * item.precio_venta }}</span>
                                    <button @click="eliminarDelCarrito(index)" class="text-slate-300 hover:text-rose-500 transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" /></svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="p-6 bg-slate-50 rounded-b-3xl border-t border-slate-100">
                            <div class="flex justify-between items-center mb-6">
                                <span class="text-slate-400 font-black uppercase tracking-widest text-[10px]">Total a cobrar</span>
                                <span class="text-4xl font-black text-slate-900 tracking-tighter">${{ totalVenta }}</span>
                            </div>

                            <button 
                                @click="finalizarVenta"
                                :disabled="carrito.length === 0"
                                class="w-full bg-sky-600 hover:bg-sky-700 disabled:bg-slate-300 text-white font-black py-4 rounded-2xl shadow-lg shadow-sky-600/30 transition-all uppercase tracking-widest flex items-center justify-center gap-2 active:scale-95"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                                Cobrar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>