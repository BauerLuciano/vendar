<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
    comercio: Object,
    consumidor: Object,
    pedidos: Array,
    tienda_slug: String,
});

// ── ACTIVE TAB ────────────────────────────────────────────────────────────
const tabActivo = ref('pedidos');

// ── PERFIL ────────────────────────────────────────────────────────────────
const editandoPerfil = ref(false);
const cambiandoPass = ref(false);

const formPerfil = useForm({
    nombre: props.consumidor?.nombre || '',
    apellido: props.consumidor?.apellido || '',
    telefono: props.consumidor?.telefono || '',
    direccion: props.consumidor?.direccion || '',
});

const formPassword = useForm({
    password: '',
    password_confirmation: '',
});

const guardarPerfil = async () => {
    try {
        const res = await axios.post('/api/tienda/perfil', {
            nombre: formPerfil.nombre,
            apellido: formPerfil.apellido,
            telefono: formPerfil.telefono,
            direccion: formPerfil.direccion,
        });
        if (res.data.consumidor) {
            Object.assign(props.consumidor, res.data.consumidor);
            editandoPerfil.value = false;
            Swal.fire({ icon: 'success', title: 'Perfil actualizado', toast: true, position: 'top-end', showConfirmButton: false, timer: 2500, background: '#0f1929', color: '#fff' });
        }
    } catch (error) {
        const data = error.response?.data;
        if (data?.errors) {
            const flat = {};
            Object.keys(data.errors).forEach(f => { flat[f] = Array.isArray(data.errors[f]) ? data.errors[f][0] : data.errors[f]; });
            formPerfil.setError(flat);
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: data?.error || 'No se pudo actualizar', background: '#0f1929', color: '#fff' });
        }
    }
};

const cambiarPassword = async () => {
    try {
        const res = await axios.post('/api/tienda/perfil', {
            password: formPassword.password,
            password_confirmation: formPassword.password_confirmation,
        });
        if (res.data.consumidor) {
            Swal.fire({ icon: 'success', title: 'Contraseña actualizada', toast: true, position: 'top-end', showConfirmButton: false, timer: 2500, background: '#0f1929', color: '#fff' });
            formPassword.reset();
            cambiandoPass.value = false;
        }
    } catch (error) {
        const data = error.response?.data;
        if (data?.errors) {
            const flat = {};
            Object.keys(data.errors).forEach(f => { flat[f] = Array.isArray(data.errors[f]) ? data.errors[f][0] : data.errors[f]; });
            formPassword.setError(flat);
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: data?.error || 'No se pudo actualizar', background: '#0f1929', color: '#fff' });
        }
    }
};

// ── PEDIDOS ───────────────────────────────────────────────────────────────
const pedidoExpandido = ref(null);

const stats = computed(() => {
    const total = props.pedidos.length;
    const pendientes = props.pedidos.filter(p => p.estado_pedido === 'nuevo' || p.estado_pedido === 'preparando' || p.estado_pedido === 'en_camino').length;
    const completados = props.pedidos.filter(p => p.estado_pedido === 'entregado').length;
    const totalGastado = props.pedidos.reduce((acc, p) => acc + parseFloat(p.total || 0), 0);
    return { total, pendientes, completados, totalGastado };
});

const togglePedido = (id) => {
    pedidoExpandido.value = pedidoExpandido.value === id ? null : id;
};

const formatearDinero = (monto) => {
    return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(monto || 0);
};

const formatearFecha = (fecha) => {
    return new Date(fecha).toLocaleDateString('es-AR', {
        day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit'
    });
};

const badgeEstado = (pedido) => {
    const map = {
        nuevo:      'Nuevo',
        preparando: pedido.tipo_entrega === 'local' ? 'Listo para retirar' : 'Preparando',
        en_camino:  'En Camino',
        entregado:  'Entregado',
        cancelado:  'Cancelado',
    };
    const clases = {
        nuevo:      ['bg-blue-500/15 text-blue-400 border-blue-500/30', 'Nuevo'],
        preparando: ['bg-amber-500/15 text-amber-400 border-amber-500/30', 'Preparando'],
        en_camino:  ['bg-indigo-500/15 text-indigo-400 border-indigo-500/30', 'En Camino'],
        entregado:  ['bg-emerald-500/15 text-emerald-400 border-emerald-500/30', 'Entregado'],
        cancelado:  ['bg-rose-500/15 text-rose-400 border-rose-500/30', 'Cancelado'],
    };
    const est = pedido.estado_pedido || pedido;
    const label = typeof pedido === 'object' ? (pedido.estado_display || map[est]) : map[pedido];
    const cls = clases[est] || ['bg-slate-500/15 text-slate-400 border-slate-500/30', est];
    return [cls[0], label];
};

const badgePago = (estado) => {
    const map = {
        pendiente:  ['bg-amber-500/15 text-amber-400 border-amber-500/30', 'Pendiente'],
        pagado:     ['bg-emerald-500/15 text-emerald-400 border-emerald-500/30', 'Pagado'],
        rechazado:  ['bg-rose-500/15 text-rose-400 border-rose-500/30', 'Rechazado'],
    };
    return map[estado] || ['bg-slate-500/15 text-slate-400 border-slate-500/30', estado];
};

const iconoEstado = (estado) => {
    const map = {
        nuevo:      '🆕',
        preparando: '👨‍🍳',
        en_camino:  '🛵',
        entregado:  '✅',
        cancelado:  '❌',
    };
    return map[estado] || '📋';
};
</script>

<template>
    <Head :title="`Mi Panel | ${comercio?.nombre || 'VendAR'}`" />

    <div class="min-h-screen bg-[#080f1e] font-sans relative overflow-x-hidden">
        <!-- Background fx -->
        <div class="fixed inset-0 pointer-events-none z-0">
            <div class="absolute w-[700px] h-[700px] bg-[#00adef]/5 rounded-full blur-[180px] -top-40 -left-40"></div>
            <div class="absolute w-[500px] h-[500px] bg-[#8cc63f]/4 rounded-full blur-[160px] -bottom-40 -right-40"></div>
            <div class="absolute inset-0" style="background-image: linear-gradient(rgba(0,173,239,0.02) 1px, transparent 1px), linear-gradient(90deg, rgba(0,173,239,0.02) 1px, transparent 1px); background-size: 40px 40px;"></div>
        </div>

        <!-- Header -->
        <header class="relative z-20 border-b border-white/5 bg-[#080f1e]/85 backdrop-blur-2xl sticky top-0">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <img src="/img/LogoVendar-Sidebar.png" alt="VendAR" class="h-9 w-auto">
                    <div class="hidden sm:block text-xs font-medium text-white/10">|</div>
                    <div class="hidden sm:block">
                        <p class="text-sm font-black text-white tracking-tight">{{ comercio?.nombre }}</p>
                        <p class="text-[9px] font-black tracking-widest text-slate-500 uppercase">Panel del Cliente</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="'/tienda/' + tienda_slug"
                        class="inline-flex items-center gap-1.5 text-[10px] font-black text-[#f7941e] bg-[#f7941e]/10 border border-[#f7941e]/30 rounded-xl px-3 py-2 hover:bg-[#f7941e] hover:text-white transition-all uppercase tracking-wider">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        Hacer Pedido
                    </Link>
                    <Link :href="'/tienda/logout-consumidor'"
                        class="text-[10px] font-bold text-rose-500 bg-rose-500/10 border border-rose-500/20 rounded-xl px-3 py-2 hover:bg-rose-500 hover:text-white transition-all">
                        Salir
                    </Link>
                </div>
            </div>
        </header>

        <div class="relative z-10 max-w-6xl mx-auto px-4 sm:px-6 py-6 space-y-6">

            <!-- Hero / Bienvenida -->
            <div class="bg-gradient-to-br from-[#0f1929] to-[#0a1325] border border-white/5 rounded-3xl p-6 overflow-hidden relative">
                <div class="absolute top-0 right-0 w-40 h-40 bg-[#00adef]/5 rounded-full blur-3xl"></div>
                <div class="flex items-center gap-4 relative">
                    <div class="w-14 h-14 rounded-full bg-gradient-to-br from-[#8cc63f] to-[#6aad1f] flex items-center justify-center text-white shrink-0 shadow-lg shadow-[#8cc63f]/30">
                        <span class="text-lg font-black">{{ consumidor.nombre?.charAt(0) }}{{ consumidor.apellido?.charAt(0) }}</span>
                    </div>
                    <div class="flex-1">
                        <h1 class="text-xl font-black text-white tracking-tight">Hola, {{ consumidor.nombre }} {{ consumidor.apellido }}</h1>
                        <p class="text-xs text-slate-400 mt-0.5">{{ consumidor.email }}</p>
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="bg-[#0f1929] border border-white/5 rounded-2xl p-4">
                    <p class="text-[9px] font-black tracking-widest text-slate-500 uppercase">Total Pedidos</p>
                    <p class="text-2xl font-black text-white mt-1">{{ stats.total }}</p>
                </div>
                <div class="bg-[#0f1929] border border-white/5 rounded-2xl p-4">
                    <p class="text-[9px] font-black tracking-widest text-amber-400 uppercase">Pendientes</p>
                    <p class="text-2xl font-black text-white mt-1">{{ stats.pendientes }}</p>
                </div>
                <div class="bg-[#0f1929] border border-white/5 rounded-2xl p-4">
                    <p class="text-[9px] font-black tracking-widest text-emerald-400 uppercase">Entregados</p>
                    <p class="text-2xl font-black text-white mt-1">{{ stats.completados }}</p>
                </div>
                <div class="bg-[#0f1929] border border-white/5 rounded-2xl p-4">
                    <p class="text-[9px] font-black tracking-widest text-[#00adef] uppercase">Total Gastado</p>
                    <p class="text-2xl font-black text-white mt-1">{{ formatearDinero(stats.totalGastado) }}</p>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex gap-1 bg-[#0f1929] border border-white/5 rounded-2xl p-1">
                <button @click="tabActivo = 'pedidos'" :class="tabActivo === 'pedidos' ? 'bg-[#00adef] text-white shadow-lg shadow-[#00adef]/20' : 'text-slate-500 hover:text-white'"
                    class="flex-1 py-2.5 rounded-xl text-[10px] font-black tracking-widest uppercase transition-all text-center">
                    📋 Pedidos
                </button>
                <button @click="tabActivo = 'perfil'" :class="tabActivo === 'perfil' ? 'bg-[#8cc63f] text-white shadow-lg shadow-[#8cc63f]/20' : 'text-slate-500 hover:text-white'"
                    class="flex-1 py-2.5 rounded-xl text-[10px] font-black tracking-widest uppercase transition-all text-center">
                    👤 Mi Perfil
                </button>
            </div>

            <!-- ── TAB: PEDIDOS ── -->
            <div v-if="tabActivo === 'pedidos'">
                <div v-if="pedidos.length === 0" class="bg-[#0f1929] border border-white/5 rounded-3xl py-20 text-center">
                    <div class="w-20 h-20 mx-auto mb-5 bg-[#f7941e]/10 border border-[#f7941e]/20 rounded-3xl flex items-center justify-center">
                        <svg class="w-10 h-10 text-[#f7941e]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    </div>
                    <h3 class="text-lg font-black text-white mb-2">Todavía no pediste nada</h3>
                    <p class="text-sm text-slate-500 max-w-xs mx-auto">Elegí tus productos favoritos y hacé tu primer pedido ahora.</p>
                    <Link :href="'/tienda/' + tienda_slug" class="inline-flex items-center gap-2 mt-5 bg-[#f7941e] hover:bg-[#f7941e]/80 text-white font-black px-5 py-2.5 rounded-xl text-xs uppercase tracking-widest transition-all">
                        🛒 Hacer mi primer pedido
                    </Link>
                </div>

                <div v-else class="space-y-3">
                    <div v-for="pedido in pedidos" :key="pedido.id"
                        class="bg-[#0f1929] border border-white/5 rounded-2xl overflow-hidden transition-all duration-200 hover:border-[#00adef]/20">
                        <!-- Cabecera del pedido (click para expandir) -->
                        <button @click="togglePedido(pedido.id)" class="w-full text-left px-5 py-4 flex items-center justify-between gap-4">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="text-xl">{{ iconoEstado(pedido.estado_pedido) }}</div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider">#{{ pedido.id }}</span>
                                        <span :class="badgeEstado(pedido)[0]" class="text-[9px] font-black px-2 py-0.5 rounded-full border">
                                            {{ badgeEstado(pedido)[1] }}
                                        </span>
                                        <span :class="badgePago(pedido.estado_pago)[0]" class="text-[9px] font-black px-2 py-0.5 rounded-full border">
                                            {{ badgePago(pedido.estado_pago)[1] }}
                                        </span>
                                    </div>
                                    <p class="text-[11px] text-slate-500 mt-1">{{ formatearFecha(pedido.created_at) }}</p>
                                </div>
                            </div>
                            <div class="text-right shrink-0 flex items-center gap-3">
                                <div>
                                    <p class="text-sm font-black text-white">{{ formatearDinero(pedido.total) }}</p>
                                    <p class="text-[9px] text-slate-500 uppercase tracking-wider">{{ pedido.metodo_pago }}</p>
                                </div>
                                <svg :class="pedidoExpandido === pedido.id ? 'rotate-180' : ''" class="w-4 h-4 text-slate-500 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                            </div>
                        </button>

                        <!-- Detalle expandido -->
                        <Transition name="slide">
                            <div v-if="pedidoExpandido === pedido.id" class="border-t border-white/5 px-5 py-4 bg-[#0a1325] space-y-3">
                                <p class="text-[9px] font-black tracking-widest text-slate-500 uppercase">Productos</p>
                                <div v-if="pedido.items?.length" class="space-y-2">
                                    <div v-for="item in pedido.items" :key="item.id"
                                        class="flex items-center justify-between bg-white/3 border border-white/5 rounded-xl px-4 py-2.5">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <span class="w-6 h-6 rounded-lg bg-[#00adef]/10 border border-[#00adef]/20 flex items-center justify-center text-[10px] font-black text-[#00adef] shrink-0">{{ item.cantidad }}</span>
                                            <span class="text-sm font-bold text-slate-200 truncate">{{ item.producto?.nombre || 'Producto' }}</span>
                                        </div>
                                        <span class="text-sm font-black text-white shrink-0">{{ formatearDinero(item.precio_unitario * item.cantidad) }}</span>
                                    </div>
                                </div>

                                <div v-if="pedido.cliente_direccion || pedido.tipo_entrega" class="grid grid-cols-2 gap-3 pt-2">
                                    <div v-if="pedido.tipo_entrega" class="bg-white/3 border border-white/5 rounded-xl px-4 py-2.5">
                                        <p class="text-[8px] font-black tracking-widest text-slate-500 uppercase">Entrega</p>
                                        <p class="text-xs font-bold text-white mt-0.5 capitalize">{{ pedido.tipo_entrega === 'delivery' ? '🛵 Delivery' : '🏬 Retiro en local' }}</p>
                                    </div>
                                    <div v-if="pedido.cliente_direccion && pedido.cliente_direccion !== 'Retiro en local'" class="bg-white/3 border border-white/5 rounded-xl px-4 py-2.5">
                                        <p class="text-[8px] font-black tracking-widest text-slate-500 uppercase">Dirección</p>
                                        <p class="text-xs font-bold text-white mt-0.5">{{ pedido.cliente_direccion }}</p>
                                    </div>
                                </div>

                                <div class="flex justify-between items-center bg-[#080f1e] border border-white/5 rounded-xl px-4 py-3">
                                    <span class="text-xs text-slate-400 font-bold">Total</span>
                                    <span class="text-lg font-black text-[#8cc63f]">{{ formatearDinero(pedido.total) }}</span>
                                </div>
                            </div>
                        </Transition>
                    </div>
                </div>
            </div>

            <!-- ── TAB: PERFIL ── -->
            <div v-if="tabActivo === 'perfil'" class="space-y-4">
                <!-- Datos personales -->
                <div class="bg-[#0f1929] border border-white/5 rounded-3xl p-6">
                    <div class="flex items-center justify-between mb-5">
                        <div>
                            <h2 class="text-base font-black text-white">Mis Datos</h2>
                            <p class="text-[10px] text-slate-500 mt-0.5">Información de tu cuenta</p>
                        </div>
                        <button @click="editandoPerfil = !editandoPerfil; if(!editandoPerfil) formPerfil.reset()"
                            class="text-[10px] font-black text-[#00adef] bg-[#00adef]/10 border border-[#00adef]/20 rounded-xl px-3 py-2 hover:bg-[#00adef] hover:text-white transition-all uppercase tracking-wider">
                            {{ editandoPerfil ? 'Cancelar' : 'Editar' }}
                        </button>
                    </div>

                    <form @submit.prevent="guardarPerfil" v-if="editandoPerfil" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[9px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Nombre</label>
                                <input v-model="formPerfil.nombre" type="text" required
                                    class="w-full bg-[#080f1e] border border-white/8 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-[#00adef]/50 transition-all"
                                    :class="{'border-rose-500/50': formPerfil.errors.nombre}">
                                <p v-if="formPerfil.errors.nombre" class="text-[10px] text-rose-400 mt-1">{{ formPerfil.errors.nombre }}</p>
                            </div>
                            <div>
                                <label class="block text-[9px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Apellido</label>
                                <input v-model="formPerfil.apellido" type="text" required
                                    class="w-full bg-[#080f1e] border border-white/8 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-[#00adef]/50 transition-all"
                                    :class="{'border-rose-500/50': formPerfil.errors.apellido}">
                                <p v-if="formPerfil.errors.apellido" class="text-[10px] text-rose-400 mt-1">{{ formPerfil.errors.apellido }}</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[9px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Teléfono</label>
                                <input v-model="formPerfil.telefono" type="text"
                                    class="w-full bg-[#080f1e] border border-white/8 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-[#00adef]/50 transition-all">
                            </div>
                            <div>
                                <label class="block text-[9px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Dirección</label>
                                <input v-model="formPerfil.direccion" type="text"
                                    class="w-full bg-[#080f1e] border border-white/8 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-[#00adef]/50 transition-all">
                            </div>
                        </div>
                        <div class="flex justify-end pt-2">
                            <button type="submit"
                                class="bg-[#00adef] hover:bg-[#00adef]/80 text-white font-black px-6 py-2.5 rounded-xl text-[10px] uppercase tracking-widest transition-all shadow-lg shadow-[#00adef]/20">
                                Guardar cambios
                            </button>
                        </div>
                    </form>

                    <!-- Vista de datos (no editando) -->
                    <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="bg-white/3 border border-white/5 rounded-xl px-4 py-3">
                            <p class="text-[8px] font-black tracking-widest text-slate-500 uppercase">Nombre</p>
                            <p class="text-sm font-bold text-white mt-0.5">{{ consumidor.nombre }} {{ consumidor.apellido }}</p>
                        </div>
                        <div class="bg-white/3 border border-white/5 rounded-xl px-4 py-3">
                            <p class="text-[8px] font-black tracking-widest text-slate-500 uppercase">Email</p>
                            <p class="text-sm font-bold text-white mt-0.5">{{ consumidor.email }}</p>
                        </div>
                        <div class="bg-white/3 border border-white/5 rounded-xl px-4 py-3">
                            <p class="text-[8px] font-black tracking-widest text-slate-500 uppercase">Teléfono</p>
                            <p class="text-sm font-bold text-white mt-0.5">{{ consumidor.telefono || '—' }}</p>
                        </div>
                        <div class="bg-white/3 border border-white/5 rounded-xl px-4 py-3">
                            <p class="text-[8px] font-black tracking-widest text-slate-500 uppercase">Dirección</p>
                            <p class="text-sm font-bold text-white mt-0.5">{{ consumidor.direccion || '—' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Cambiar contraseña -->
                <div class="bg-[#0f1929] border border-white/5 rounded-3xl p-6">
                    <div class="flex items-center justify-between mb-5">
                        <div>
                            <h2 class="text-base font-black text-white">Contraseña</h2>
                            <p class="text-[10px] text-slate-500 mt-0.5">Actualizá tu clave de acceso</p>
                        </div>
                        <button @click="cambiandoPass = !cambiandoPass; if(!cambiandoPass) formPassword.reset()"
                            class="text-[10px] font-black text-[#f7941e] bg-[#f7941e]/10 border border-[#f7941e]/20 rounded-xl px-3 py-2 hover:bg-[#f7941e] hover:text-white transition-all uppercase tracking-wider">
                            {{ cambiandoPass ? 'Cancelar' : 'Cambiar' }}
                        </button>
                    </div>

                    <form @submit.prevent="cambiarPassword" v-if="cambiandoPass" class="max-w-md space-y-4">
                        <div>
                            <label class="block text-[9px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Nueva Contraseña</label>
                            <input v-model="formPassword.password" type="password" minlength="6" required placeholder="Mínimo 6 caracteres"
                                class="w-full bg-[#080f1e] border border-white/8 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-[#f7941e]/50 transition-all"
                                :class="{'border-rose-500/50': formPassword.errors.password}">
                            <p v-if="formPassword.errors.password" class="text-[10px] text-rose-400 mt-1">{{ formPassword.errors.password }}</p>
                        </div>
                        <div>
                            <label class="block text-[9px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Repetir Contraseña</label>
                            <input v-model="formPassword.password_confirmation" type="password" minlength="6" required placeholder="Repetí la contraseña"
                                class="w-full bg-[#080f1e] border border-white/8 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-[#f7941e]/50 transition-all">
                        </div>
                        <button type="submit"
                            class="bg-[#f7941e] hover:bg-[#f7941e]/80 text-white font-black px-6 py-2.5 rounded-xl text-[10px] uppercase tracking-widest transition-all shadow-lg shadow-[#f7941e]/20">
                            Guardar Contraseña
                        </button>
                    </form>

                    <div v-else class="flex items-center gap-3 text-sm text-slate-400">
                        <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        <span>Tu contraseña está segura. Podés cambiarla cuando quieras.</span>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>

<style>
/* Transitions */
.slide-enter-active, .slide-leave-active {
    transition: all 0.25s ease;
}
.slide-enter-from, .slide-leave-to {
    opacity: 0;
    max-height: 0;
}

/* Scrollbar */
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 4px; }

/* Hide number input spinner */
input[type=number]::-webkit-inner-spin-button,
input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
input[type=number] { -moz-appearance: textfield; }
</style>
