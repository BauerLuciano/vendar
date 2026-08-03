<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Swal from 'sweetalert2';

const props = defineProps({
    faltantes: Array,
    resumen: Object,
    sin_minimo: Number,
    sin_objetivo: Number,
    todos: Boolean,
    sucursalActual: Object
});

const formatPesos = (value) =>
    new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(value ?? 0);

const unidadLabel = (unidad) => {
    const map = { 'unidad': 'unidad', 'unidades': 'unidades', 'kg': 'kg', 'gramos': 'g', 'caja': 'caja', 'cajas': 'cajas', 'pack': 'pack', 'packs': 'packs' };
    return map[String(unidad ?? '').toLowerCase()] ?? String(unidad ?? '').toLowerCase();
};

const cantidadLabel = (item) => {
    const u = String(item.unidad_medida ?? '').toLowerCase();
    if (['unidad', 'unidades', 'caja', 'cajas', 'pack', 'packs'].includes(u)) {
        return `${item.cantidad_sugerida} ${unidadLabel(u)}${item.cantidad_sugerida > 1 && !['kg', 'gramos'].includes(u) ? 's' : ''}`;
    }
    return `${item.cantidad_sugerida} ${unidadLabel(u)}`;
};

const criticidadBadge = (item) => {
    if (item.cantidad_fisica <= 0) return { texto: 'AGOTADO', clase: 'bg-rose-100 text-rose-700' };
    if (item.criticidad < 0.5) return { texto: 'CRÍTICO', clase: 'bg-amber-100 text-amber-700' };
    return { texto: 'BAJO', clase: 'bg-slate-100 text-slate-600' };
};

const mostrarTotal = computed(() => props.resumen?.total_productos ?? 0);
const hayMas = computed(() => !props.todos && props.faltantes.length < (props.resumen?.total_productos ?? 0));

const verTodos = () => router.get(route('reposicion.index'), { todos: 1 }, { preserveScroll: true });
const verTop10 = () => router.get(route('reposicion.index'), { todos: 0 }, { preserveScroll: true });

const recordar = (item) => {
    Swal.fire({
        title: '¿Recordar mañana?',
        text: `Ocultamos "${item.nombre}" por hoy. Si sigue faltando, reaparece mañana.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#4f46e5',
        confirmButtonText: 'Sí, recordar mañana',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('reposicion.recordar'), { producto_id: item.id }, { preserveScroll: true });
        }
    });
};
</script>

<template>
    <Head title="Reposición Inteligente" />

    <AuthenticatedLayout>
        <div class="py-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto bg-slate-50 min-h-screen">

            <div class="flex justify-between items-end mb-8">
                <div>
                    <div class="flex items-center gap-3 mb-1">
                        <span class="bg-amber-100 text-amber-700 text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded-md">Inteligente</span>
                        <h1 class="text-3xl font-black text-slate-800 tracking-tight">Reposición Inteligente</h1>
                    </div>
                    <p class="text-slate-500 font-medium text-sm">Sucursal actual: <span class="font-bold text-sky-600">{{ sucursalActual?.nombre }}</span></p>
                </div>
            </div>

            <!-- Resumen económico -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-indigo-600 text-white rounded-2xl p-5 shadow-lg shadow-indigo-600/25">
                    <p class="text-xs font-black uppercase tracking-widest text-indigo-200">A reponer</p>
                    <p class="text-3xl font-black mt-1">{{ mostrarTotal }}<span class="text-sm font-bold text-indigo-200 ml-1">productos</span></p>
                </div>
                <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
                    <p class="text-xs font-black uppercase tracking-widest text-slate-400">Costo estimado</p>
                    <p class="text-3xl font-black mt-1 text-slate-800">{{ formatPesos(resumen?.costo_estimado) }}</p>
                </div>
                <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
                    <p class="text-xs font-black uppercase tracking-widest text-slate-400">Agotados</p>
                    <p class="text-3xl font-black mt-1 text-rose-600">{{ resumen?.agotados ?? 0 }}</p>
                </div>
                <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
                    <p class="text-xs font-black uppercase tracking-widest text-slate-400">Críticos</p>
                    <p class="text-3xl font-black mt-1 text-amber-600">{{ resumen?.criticos ?? 0 }}</p>
                </div>
            </div>

            <!-- Banner productos sin stock mínimo -->
            <div v-if="sin_minimo > 0" class="bg-sky-50 border border-sky-200 rounded-2xl px-5 py-4 mb-3 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <span class="bg-sky-100 text-sky-600 rounded-lg p-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" /></svg>
                    </span>
                    <p class="text-sky-800 font-bold text-sm">
                        {{ sin_minimo }} producto{{ sin_minimo !== 1 ? 's' : '' }} sin stock mínimo definido.
                        <span class="text-sky-600 font-semibold">Configuralos para activar la reposición inteligente.</span>
                    </p>
                </div>
                <a href="/productos" class="shrink-0 bg-sky-600 hover:bg-sky-700 text-white px-4 py-2 rounded-xl font-black text-xs shadow transition-colors">Configurar</a>
            </div>

            <!-- Banner productos sin stock objetivo -->
            <div v-if="sin_objetivo > 0" class="bg-violet-50 border border-violet-200 rounded-2xl px-5 py-4 mb-6 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <span class="bg-violet-100 text-violet-600 rounded-lg p-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12V12.75z" /></svg>
                    </span>
                    <p class="text-violet-800 font-bold text-sm">
                        {{ sin_objetivo }} producto{{ sin_objetivo !== 1 ? 's' : '' }} con stock mínimo definido pero sin stock objetivo.
                        <span class="text-violet-600 font-semibold">Definí el objetivo para saber cuánto reponer de cada uno.</span>
                    </p>
                </div>
                <a href="/productos" class="shrink-0 bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-xl font-black text-xs shadow transition-colors">Configurar</a>
            </div>

            <!-- Lista de productos para reponer -->
            <div class="bg-white border border-slate-200 shadow-xl shadow-slate-200/40 rounded-3xl overflow-hidden">
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
                    <h2 class="font-black text-slate-700 text-lg">Productos para reponer</h2>
                    <div class="flex gap-2">
                        <button v-if="hayMas" @click="verTodos"
                            class="text-xs font-black px-4 py-2 rounded-xl bg-indigo-600 text-white hover:bg-indigo-700 transition-colors">
                            Ver todos ({{ mostrarTotal }})
                        </button>
                        <button v-else-if="todos" @click="verTop10"
                            class="text-xs font-black px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors">
                            Ver Top 10
                        </button>
                    </div>
                </div>

                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-800 text-white border-b border-slate-700">
                            <th class="py-4 px-4 text-[10px] font-black tracking-widest uppercase text-slate-400">Producto</th>
                            <th class="py-4 px-4 text-[10px] font-black tracking-widest uppercase text-slate-400 text-center">Stock / Mínimo</th>
                            <th class="py-4 px-4 text-[10px] font-black tracking-widest uppercase text-slate-400">Comprar</th>
                            <th class="py-4 px-4 text-[10px] font-black tracking-widest uppercase text-slate-400 text-center">Ventas 30d</th>
                            <th class="py-4 px-4 text-[10px] font-black tracking-widest uppercase text-slate-400 text-center">Costo est.</th>
                            <th class="py-4 px-4 text-[10px] font-black tracking-widest uppercase text-slate-400 text-center">Estado</th>
                            <th class="py-4 px-4 text-[10px] font-black tracking-widest uppercase text-slate-400 text-center">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="faltantes.length === 0">
                            <td colspan="7" class="py-12 text-center text-emerald-500 font-bold bg-emerald-50">
                                ¡Excelente! Tu stock está en niveles óptimos. No hay alertas de reposición.
                            </td>
                        </tr>

                        <tr v-for="item in faltantes" :key="item.id" class="hover:bg-slate-50 transition-colors">
                            <td class="py-4 px-4">
                                <div class="font-bold text-slate-700">{{ item.nombre }}</div>
                                <div class="text-[10px] font-mono text-slate-400">{{ item.codigo_barras }}</div>
                            </td>

                            <td class="py-4 px-4 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <span class="px-2 py-1 bg-rose-100 text-rose-700 font-black rounded text-xs shadow-sm" title="Stock Físico">{{ item.cantidad_fisica }}</span>
                                    <span class="text-slate-300 font-black">/</span>
                                    <span class="text-slate-400 font-bold text-xs" title="Stock Mínimo">{{ item.stock_minimo }}</span>
                                </div>
                            </td>

                            <td class="py-4 px-4">
                                <span class="font-black text-indigo-700">Comprar al menos {{ cantidadLabel(item) }}</span>
                                <div class="text-[10px] font-mono text-slate-400">objetivo {{ item.stock_objetivo }} − stock {{ item.cantidad_fisica }}</div>
                            </td>

                            <td class="py-4 px-4 text-center font-bold text-slate-600">{{ item.ventas_30d }}</td>
                            <td class="py-4 px-4 text-center font-bold text-slate-600">{{ formatPesos(item.costo_estimado) }}</td>

                            <td class="py-4 px-4 text-center">
                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider"
                                    :class="criticidadBadge(item).clase">{{ criticidadBadge(item).texto }}</span>
                            </td>

                            <td class="py-4 px-4 text-center">
                                <button @click="recordar(item)"
                                    class="text-xs font-black px-3 py-1.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 transition-colors">
                                    Recordar mañana
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
