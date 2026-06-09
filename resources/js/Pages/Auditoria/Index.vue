<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, reactive, watch, computed, onMounted } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    actividades: Object,
    usuarios: Array,
    consumidores: Array,
    filtros: Object,
});

const formFiltros = reactive({
    fecha_desde: props.filtros?.fecha_desde || '',
    fecha_hasta: props.filtros?.fecha_hasta || '',
    usuario_id: props.filtros?.usuario_id || '',
    consumidor_id: props.filtros?.consumidor_id || '',
    evento: props.filtros?.evento || '',
    modelo: props.filtros?.modelo || '',
});

let timeout = null;

watch(formFiltros, (value) => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(route('auditoria.index'), value, {
            preserveState: true,
            replace: true,
        });
    }, 300);
});

const hayFiltrosActivos = computed(() =>
    formFiltros.fecha_desde || formFiltros.fecha_hasta || formFiltros.usuario_id || formFiltros.consumidor_id || formFiltros.evento || formFiltros.modelo
);

const limpiarFiltros = () => {
    formFiltros.fecha_desde = '';
    formFiltros.fecha_hasta = '';
    formFiltros.usuario_id = '';
    formFiltros.consumidor_id = '';
    formFiltros.evento = '';
    formFiltros.modelo = '';
};

const esBrave = ref(false);
const paginaActual = computed(() => props.actividades.current_page || 1);
const ultimaPagina = computed(() => props.actividades.last_page || 1);

const paginasVisibles = computed(() => {
    const links = props.actividades.links || [];
    return links.length ? [links[0], links[links.length - 1]] : [];
});

onMounted(async () => {
    if (navigator.brave && typeof navigator.brave.isBrave === 'function') {
        esBrave.value = await navigator.brave.isBrave();
    }
});

const nombrarNavegador = (navegador) => {
    if (navegador?.nombre === 'Chrome' && esBrave.value) {
        return { ...navegador, nombre: 'Brave', icono: 'brave' };
    }
    return navegador;
};

const badgeAccion = (accion) => {
    const map = {
        created:    { label: 'Creado',    dot: 'bg-emerald-500',  bg: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
        updated:    { label: 'Actualizado', dot: 'bg-sky-500',    bg: 'bg-sky-50 text-sky-700 border-sky-200' },
        deleted:    { label: 'Eliminado',  dot: 'bg-rose-500',   bg: 'bg-rose-50 text-rose-700 border-rose-200' },
        login:      { label: 'Inicio Sesión', dot: 'bg-violet-500', bg: 'bg-violet-50 text-violet-700 border-violet-200' },
        logout:     { label: 'Cierre Sesión', dot: 'bg-amber-500', bg: 'bg-amber-50 text-amber-700 border-amber-200' },
    };
    return map[accion] || { label: accion?.toUpperCase() || '—', dot: 'bg-slate-400', bg: 'bg-slate-50 text-slate-500 border-slate-200' };
};

const formatearValor = (v) => {
    if (v === null || v === undefined) return '<span class="text-slate-400 italic">—</span>';
    if (v === true || v === false) return v ? '<span class="text-emerald-600 font-semibold">Sí</span>' : '<span class="text-rose-500 font-semibold">No</span>';

    if (typeof v === 'object') {
        try {
            const arr = Array.isArray(v) ? v : [v];
            return arr.map(item => {
                if (item.metodo_pago && item.monto !== undefined) {
                    return `<div class="flex items-center gap-2 text-xs"><span class="bg-slate-100 px-2 py-0.5 rounded font-medium">${item.metodo_pago}</span><span class="font-bold text-emerald-600">${new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(item.monto)}</span></div>`;
                }
                return `<span class="text-slate-400 italic">${JSON.stringify(item)}</span>`;
            }).join('');
        } catch {
            return '<span class="text-slate-400 italic">—</span>';
        }
    }

    const str = String(v);

    // ISO datetime: 2026-05-20T00:00:30.000000Z → 20/05/2026 00:00
    if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(str)) {
        return str.replace(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}).*/, '$3/$2/$1 $4:$5');
    }

    // ISO date: 2026-05-20 → 20/05/2026
    if (/^\d{4}-\d{2}-\d{2}$/.test(str)) {
        return str.replace(/^(\d{4})-(\d{2})-(\d{2})$/, '$3/$2/$1');
    }

    // Money: numbers with 2 decimals → $ 1.234,56
    if (/^-?\d+\.\d{2}$/.test(str)) {
        return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(parseFloat(str));
    }

    return str;
};

const badgeAccionModal = (accion) => {
    const map = {
        created: { label: 'Nuevo', dot: 'bg-emerald-500', bg: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
        updated: { label: 'Modificado', dot: 'bg-sky-500', bg: 'bg-sky-50 text-sky-700 border-sky-200' },
        deleted: { label: 'Eliminado', dot: 'bg-rose-500', bg: 'bg-rose-50 text-rose-700 border-rose-200' },
        login:   { label: 'Sesión', dot: 'bg-violet-500', bg: 'bg-violet-50 text-violet-700 border-violet-200' },
        logout:  { label: 'Sesión', dot: 'bg-amber-500', bg: 'bg-amber-50 text-amber-700 border-amber-200' },
    };
    return map[accion] || { label: accion, dot: 'bg-slate-400', bg: 'bg-slate-100 text-slate-600 border-slate-200' };
};

const verCambios = (actividad) => {
    let contenido;
    const badge = badgeAccionModal(actividad.accion);

    if (actividad.diff && actividad.diff.length > 0) {
        let filas = actividad.diff.map((d, idx) => {
            const esNuevo = d.antes === null && d.despues !== null;
            const esEliminado = d.despues === null && d.antes !== null;
            const esModificado = d.antes !== null && d.despues !== null;
            const bgFila = idx % 2 === 0 ? 'bg-white' : 'bg-slate-50/50';

            return `<tr class="${bgFila} hover:bg-blue-50/30 transition-colors">
                <td class="px-5 py-3 text-sm font-bold text-slate-700 border-b border-slate-100 whitespace-nowrap">${d.campo}</td>
                <td class="px-5 py-3 text-sm border-b border-slate-100">${
                    esNuevo
                        ? '<span class="text-slate-300 italic text-xs">—</span>'
                        : `<span class="line-through text-rose-500 font-medium">${formatearValor(d.antes)}</span>`
                }</td>
                <td class="px-5 py-3 text-sm font-bold border-b border-slate-100 ${
                    esNuevo ? 'text-emerald-600' : esEliminado ? 'text-rose-500' : 'text-emerald-700'
                }">${formatearValor(d.despues)}</td>
            </tr>`;
        }).join('');

        contenido = `<div class="max-h-[420px] overflow-y-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b-2 border-slate-200 bg-slate-50/80">
                        <th class="px-5 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">Campo</th>
                        <th class="px-5 py-3 text-[10px] font-bold uppercase tracking-widest text-rose-400">Valor anterior</th>
                        <th class="px-5 py-3 text-[10px] font-bold uppercase tracking-widest text-emerald-500">Valor nuevo</th>
                    </tr>
                </thead>
                <tbody>${filas}</tbody>
            </table>
        </div>`;
    } else {
        const colores = {
            created: 'text-emerald-600',
            updated: 'text-sky-600',
            deleted: 'text-rose-600',
            login: 'text-violet-600',
            logout: 'text-amber-600',
        };
        contenido = `<div class="py-8 text-center">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-100 text-slate-500 text-xs font-bold uppercase tracking-widest mb-3">Sin cambios detallados</div>
            <p class="${colores[actividad.accion] || 'text-slate-600'} font-bold text-sm">${actividad.descripcion}</p>
            <div class="mt-3 flex items-center justify-center gap-3 text-xs text-slate-400">
                <span class="bg-slate-100 px-2 py-1 rounded font-mono">IP: ${actividad.ip}</span>
                <span>·</span>
                <span>${nombrarNavegador(actividad.navegador)?.nombre || '—'}</span>
            </div>
        </div>`;
    }

    Swal.fire({
        html: `
            <div class="text-left">
                <div class="px-6 pt-5 pb-4 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white">
                    <div class="flex items-start justify-between">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="${badge.bg} inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold border">
                                    <span class="${badge.dot} w-1.5 h-1.5 rounded-full"></span>
                                    ${badge.label}
                                </span>
                                ${actividad.modelo_id ? `<span class="text-[10px] font-mono text-slate-400 bg-slate-100 px-2 py-0.5 rounded">#${actividad.modelo_id}</span>` : ''}
                            </div>
                            <p class="text-sm font-black text-slate-800 truncate">${actividad.descripcion}</p>
                            <p class="text-xs text-slate-400 mt-1">
                                <span class="font-medium text-slate-500">${actividad.usuario}</span>
                                <span class="mx-1">·</span>
                                ${actividad.fecha}
                                ${actividad.modelo !== '—' ? '<span class="mx-1">·</span> ' + actividad.modelo : ''}
                            </p>
                        </div>
                        <div class="shrink-0 ml-4 hidden sm:block">
                            ${actividad.modelo !== '—'
                                ? `<span class="inline-flex items-center justify-center min-w-[4rem] h-10 px-3 rounded-xl bg-slate-100 text-slate-500 font-bold text-[9px] uppercase tracking-wider border border-slate-200 text-center leading-tight">${actividad.modelo}</span>`
                                : `<span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-slate-100 text-slate-400 border border-slate-200">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                </span>`}
                        </div>
                    </div>
                </div>
                ${contenido}
            </div>
        `,
        showConfirmButton: true,
        confirmButtonText: 'Cerrar',
        confirmButtonColor: '#475569',
        width: 720,
        padding: '0',
        customClass: {
            popup: 'rounded-2xl overflow-hidden shadow-2xl',
            confirmButton: '!rounded-xl !text-xs !font-bold !px-6 !py-2.5 !mb-5 !shadow-sm hover:!shadow-md !transition-all',
        },
    });
};

const iconoNavegador = (navegador) => {
    const cls = 'w-4 h-4 shrink-0';
    const base = 'https://cdn.jsdelivr.net/npm/simple-icons@latest/icons';
    const map = {
        chrome:  `<img src="${base}/googlechrome.svg" class="${cls}" alt="Chrome" />`,
        firefox: `<img src="${base}/firefoxbrowser.svg" class="${cls}" alt="Firefox" />`,
        safari:  `<img src="${base}/safari.svg" class="${cls}" alt="Safari" />`,
        edge:    `<img src="${base}/microsoftedge.svg" class="${cls}" alt="Edge" />`,
        opera:   `<img src="${base}/opera.svg" class="${cls}" alt="Opera" />`,
        vivaldi: `<img src="${base}/vivaldi.svg" class="${cls}" alt="Vivaldi" />`,
        brave:   `<img src="${base}/brave.svg" class="${cls}" alt="Brave" />`,
        samsung: `<img src="${base}/samsung.svg" class="${cls}" alt="Samsung" />`,
    };
    return map[navegador?.icono] || `<svg viewBox="0 0 24 24" fill="none" class="${cls}"><circle cx="12" cy="12" r="10" stroke="#94a3b8" stroke-width="1.5"/><path d="M4 4l16 16M20 4L4 20" stroke="#94a3b8" stroke-width="1.5"/></svg>`;
};
</script>

<template>
    <Head title="Auditoría | VendAR" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Seguridad</p>
                    <h2 class="text-xl font-semibold text-slate-800 mt-0.5">Auditoría del Sistema</h2>
                </div>
                <span class="text-xs text-slate-500 bg-white border border-slate-200 px-3 py-1.5 rounded-lg font-medium">
                    {{ actividades.total || 0 }} registros
                </span>
            </div>
        </template>

        <div class="py-8 bg-slate-50 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-5">

                <div class="bg-white border border-slate-200 rounded-xl px-5 py-4 shadow-sm">
                    <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
                        <div>
                            <label class="block text-[10px] font-medium uppercase tracking-widest text-slate-400 mb-1.5">Fecha desde</label>
                            <input type="date" v-model="formFiltros.fecha_desde" :max="formFiltros.fecha_hasta"
                                class="w-full px-3 py-2.5 text-sm border border-slate-200 rounded-lg bg-slate-50 text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-medium uppercase tracking-widest text-slate-400 mb-1.5">Fecha hasta</label>
                            <input type="date" v-model="formFiltros.fecha_hasta" :min="formFiltros.fecha_desde"
                                class="w-full px-3 py-2.5 text-sm border border-slate-200 rounded-lg bg-slate-50 text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-medium uppercase tracking-widest text-slate-400 mb-1.5">Usuario</label>
                            <select v-model="formFiltros.usuario_id"
                                class="w-full px-3 py-2.5 text-sm border border-slate-200 rounded-lg bg-slate-50 text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent appearance-none">
                                <option value="">Todos</option>
                                <option v-for="u in usuarios" :key="u.id" :value="u.id">{{ u.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-medium uppercase tracking-widest text-slate-400 mb-1.5">Cliente</label>
                            <select v-model="formFiltros.consumidor_id"
                                class="w-full px-3 py-2.5 text-sm border border-slate-200 rounded-lg bg-slate-50 text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent appearance-none">
                                <option value="">Todos</option>
                                <option v-for="c in consumidores" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-medium uppercase tracking-widest text-slate-400 mb-1.5">Acción</label>
                            <select v-model="formFiltros.evento"
                                class="w-full px-3 py-2.5 text-sm border border-slate-200 rounded-lg bg-slate-50 text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent appearance-none">
                                <option value="">Todas</option>
                                <option value="created">Creado</option>
                                <option value="updated">Actualizado</option>
                                <option value="deleted">Eliminado</option>
                                <option value="login">Inicio sesión</option>
                                <option value="logout">Cierre sesión</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-medium uppercase tracking-widest text-slate-400 mb-1.5">Modelo</label>
                            <select v-model="formFiltros.modelo"
                                class="w-full px-3 py-2.5 text-sm border border-slate-200 rounded-lg bg-slate-50 text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent appearance-none">
                                <option value="">Todos</option>
                                <option value="App\Models\Venta">Venta</option>
                                <option value="App\Models\Producto">Producto</option>
                                <option value="App\Models\Categoria">Categoría</option>
                                <option value="App\Models\Proveedor">Proveedor</option>
                                <option value="App\Models\Marca">Marca</option>
                                <option value="App\Models\Sucursal">Sucursal</option>
                                <option value="App\Models\Caja">Caja</option>
                                <option value="App\Models\Consumidor">Consumidor</option>
                                <option value="App\Models\Lote">Lote</option>
                                <option value="App\Models\OrdenCompra">Orden Compra</option>
                                <option value="App\Models\IngresoMercaderia">Ingreso</option>
                                <option value="App\Models\User">Usuario</option>
                                <option value="App\Models\Comercio">Comercio</option>
                                <option value="App\Models\Configuracion">Configuración</option>
                                <option value="App\Models\TransferenciaSugerida">Transferencia</option>
                                <option value="App\Models\PedidoWeb">Pedido Web</option>
                            </select>
                        </div>
                    </div>
                    <button v-if="hayFiltrosActivos" @click="limpiarFiltros"
                        class="mt-3 flex items-center gap-2 px-4 py-2 rounded-lg bg-red-50 text-red-600 border border-red-100 text-xs font-bold hover:bg-red-100 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Limpiar Filtros
                    </button>
                </div>

                <div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50/80 text-[10px] font-semibold text-slate-400 uppercase tracking-widest">
                                    <th class="px-5 py-4">Usuario</th>
                                    <th class="px-5 py-4">Acción</th>
                                    <th class="px-5 py-4">Descripción</th>
                                    <th class="px-5 py-4">Modelo</th>
                                    <th class="px-5 py-4">Navegador</th>
                                    <th class="px-5 py-4">Dirección IP</th>
                                    <th class="px-5 py-4">Fecha</th>
                                    <th class="px-5 py-4 text-center">Cambios</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-if="actividades.data.length === 0">
                                    <td colspan="8" class="py-20 text-center">
                                        <div class="flex flex-col items-center gap-3 text-slate-300">
                                            <svg class="h-14 w-14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <div>
                                                <p class="text-sm font-medium text-slate-400">Sin registros de auditoría</p>
                                                <p class="text-xs text-slate-300 mt-1">No se encontraron registros con los filtros aplicados.</p>
                                            </div>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-for="act in actividades.data" :key="act.id" class="hover:bg-blue-50/30 group">
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-9 h-9 shrink-0 rounded-full bg-slate-100 flex items-center justify-center text-xs font-bold text-slate-600 border border-slate-200">
                                                {{ act.usuario.charAt(0).toUpperCase() }}
                                            </div>
                                            <div>
                                                <p class="text-sm font-semibold text-slate-800 leading-snug">{{ act.usuario }}</p>
                                                <p class="text-[10px] text-slate-400">{{ act.email }}</p>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-5 py-4">
                                        <span :class="badgeAccion(act.accion).bg"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-semibold border whitespace-nowrap">
                                            <span :class="badgeAccion(act.accion).dot" class="w-1.5 h-1.5 rounded-full"></span>
                                            {{ badgeAccion(act.accion).label }}
                                        </span>
                                    </td>

                                    <td class="px-5 py-4 max-w-[220px]">
                                        <p class="text-sm text-slate-700 truncate font-medium">{{ act.descripcion }}</p>
                                    </td>

                                    <td class="px-5 py-4">
                                        <span v-if="act.modelo !== '—'"
                                            class="font-mono text-[11px] font-semibold text-blue-700 bg-blue-50 border border-blue-100 px-2.5 py-1 rounded-md tracking-tight whitespace-nowrap">
                                            {{ act.modelo }} <span class="text-blue-400">#{{ act.modelo_id }}</span>
                                        </span>
                                        <span v-else class="text-xs text-slate-400 italic">—</span>
                                    </td>

                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-2" v-if="act.navegador?.nombre !== '—'">
                                            <span v-html="iconoNavegador(nombrarNavegador(act.navegador))"></span>
                                            <span class="text-xs font-medium text-slate-600">{{ nombrarNavegador(act.navegador).nombre }}</span>
                                        </div>
                                        <span v-else class="text-xs text-slate-400 italic">—</span>
                                    </td>

                                    <td class="px-5 py-4">
                                        <span class="font-mono text-[11px] text-slate-500 bg-slate-50 border border-slate-200 px-2 py-1 rounded-md whitespace-nowrap">
                                            {{ act.ip }}
                                        </span>
                                    </td>

                                    <td class="px-5 py-4 whitespace-nowrap">
                                        <p class="text-xs text-slate-500 font-medium">{{ act.fecha }}</p>
                                    </td>

                                    <td class="px-5 py-4 text-center">
                                        <button @click="verCambios(act)"
                                            class="text-[10px] font-semibold text-blue-600 bg-blue-50 border border-blue-100 hover:bg-blue-100 px-3 py-1.5 rounded-lg transition-all whitespace-nowrap">
                                            Ver Cambios
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="actividades.last_page > 1" class="px-5 py-4 border-t border-slate-100 bg-slate-50 flex flex-col sm:flex-row items-center justify-between gap-4">
                        <span class="text-sm text-slate-500 font-medium">
                            Mostrando {{ actividades.from }} a {{ actividades.to }} de {{ actividades.total }} registros
                        </span>
                        <div class="flex flex-wrap justify-center gap-1">
                            <Link v-for="(link, i) in paginasVisibles" :key="i"
                                :href="link.url || '#'"
                                class="px-4 py-1.5 text-sm font-bold rounded-lg transition-colors border"
                                :class="link.url
                                    ? 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100'
                                    : 'opacity-40 cursor-not-allowed bg-slate-50 text-slate-400 border-slate-200'"
                            >
                                <span v-if="i === 0" class="flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                                    Anterior
                                </span>
                                <span v-else class="flex items-center gap-1">
                                    Siguiente
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                                </span>
                            </Link>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
