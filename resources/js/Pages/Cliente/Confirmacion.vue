<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    comercio: Object,
    pedido: Object,
    status_inicial: String,
    tienda_slug: String,
});

const formatearDinero = (monto) => {
    return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(monto || 0);
};

const formatearFecha = (fecha) => {
    return new Date(fecha).toLocaleDateString('es-AR', {
        day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit'
    });
};

const estadoPantalla = ref(props.status_inicial);
const pollActivo = ref(false);
const pollTimeout = ref(null);
const segundosEspera = ref(0);
const POLL_MAX_SEGUNDOS = 60;

const hacerPoll = async () => {
    try {
        const res = await axios.get(`/api/tienda/pedido/${props.pedido.id}/estado`, {
            params: { comercio_id: props.comercio.id }
        });

        if (res.data.estado_pago === 'pagado') {
            estadoPantalla.value = 'approved';
            pollActivo.value = false;
            return;
        }
        if (res.data.estado_pago === 'rechazado' || res.data.estado_pago === 'reembolsado') {
            estadoPantalla.value = 'rejected';
            pollActivo.value = false;
            return;
        }
    } catch (e) {
        // Error de red, reintentar
    }

    segundosEspera.value += 3;
    if (segundosEspera.value >= POLL_MAX_SEGUNDOS) {
        estadoPantalla.value = 'timeout';
        pollActivo.value = false;
        return;
    }

    if (pollActivo.value) {
        pollTimeout.value = setTimeout(hacerPoll, 3000);
    }
};

onMounted(() => {
    if (props.status_inicial === 'pending') {
        pollActivo.value = true;
        pollTimeout.value = setTimeout(hacerPoll, 3000);
    }
});

onUnmounted(() => {
    pollActivo.value = false;
    if (pollTimeout.value) clearTimeout(pollTimeout.value);
});
</script>

<template>
    <Head :title="'Confirmación | ' + (comercio?.nombre || 'VendAR')" />

    <div class="min-h-screen bg-[#080f1e] font-sans relative overflow-x-hidden">
        <div class="fixed inset-0 pointer-events-none z-0">
            <div class="absolute w-[700px] h-[700px] bg-[#00adef]/5 rounded-full blur-[180px] -top-40 -left-40"></div>
            <div class="absolute w-[500px] h-[500px] bg-[#8cc63f]/4 rounded-full blur-[160px] -bottom-40 -right-40"></div>
            <div class="absolute inset-0" style="background-image: linear-gradient(rgba(0,173,239,0.02) 1px, transparent 1px), linear-gradient(90deg, rgba(0,173,239,0.02) 1px, transparent 1px); background-size: 40px 40px;"></div>
        </div>

        <header class="relative z-20 border-b border-white/5 bg-[#080f1e]/85 backdrop-blur-2xl sticky top-0">
            <div class="max-w-2xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <img src="/img/LogoVendar-Sidebar.png" alt="VendAR" class="h-9 w-auto">
                    <div class="hidden sm:block text-xs font-medium text-white/10">|</div>
                    <div class="hidden sm:block">
                        <p class="text-sm font-black text-white tracking-tight">{{ comercio?.nombre }}</p>
                        <p class="text-[9px] font-black tracking-widest text-slate-500 uppercase">Confirmación</p>
                    </div>
                </div>
                <Link :href="'/tienda/' + tienda_slug"
                    class="text-[10px] font-bold text-slate-400 bg-white/5 border border-white/10 rounded-xl px-3 py-2 hover:bg-white/10 hover:text-white transition-all">
                    Volver a la tienda
                </Link>
            </div>
        </header>

        <div class="relative z-10 max-w-2xl mx-auto px-4 sm:px-6 py-8">

            <!-- APPROVED -->
            <div v-if="estadoPantalla === 'approved'" class="text-center">
                <div class="w-20 h-20 mx-auto mb-6 bg-emerald-500/15 border border-emerald-500/30 rounded-full flex items-center justify-center">
                    <svg class="w-10 h-10 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                </div>
                <h1 class="text-2xl font-black text-white tracking-tight">¡Pago Aprobado!</h1>
                <p class="text-sm text-slate-400 mt-2 max-w-sm mx-auto">Tu pedido ya está en proceso. El local te va a notificar cuando esté listo.</p>

                <div class="bg-[#0f1929] border border-white/5 rounded-3xl p-6 mt-8 text-left">
                    <div class="flex items-center justify-between mb-5">
                        <div>
                            <p class="text-[9px] font-black tracking-widest text-slate-500 uppercase">Pedido #{{ pedido.id }}</p>
                            <p class="text-xs text-slate-400 mt-0.5">{{ formatearFecha(pedido.created_at) }}</p>
                        </div>
                        <span class="text-[9px] font-black px-2.5 py-1 rounded-full border bg-emerald-500/15 text-emerald-400 border-emerald-500/30 uppercase tracking-wider">Pagado</span>
                    </div>

                    <div class="space-y-2">
                        <div v-for="item in pedido.items" :key="item.id"
                            class="flex items-center justify-between bg-[#080f1e] border border-white/5 rounded-xl px-4 py-2.5">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="w-6 h-6 rounded-lg bg-[#00adef]/10 border border-[#00adef]/20 flex items-center justify-center text-[10px] font-black text-[#00adef] shrink-0">{{ item.cantidad }}</span>
                                <span class="text-sm font-bold text-slate-200 truncate">{{ item.producto?.nombre || 'Producto' }}</span>
                            </div>
                            <span class="text-sm font-black text-white shrink-0">{{ formatearDinero(item.precio_unitario * item.cantidad) }}</span>
                        </div>
                    </div>

                    <div v-if="pedido.costo_envio > 0" class="flex items-center justify-between bg-[#080f1e] border border-white/5 rounded-xl px-4 py-2.5 mt-2">
                        <span class="text-xs text-slate-400 font-bold">Costo de envío</span>
                        <span class="text-sm font-black text-white">{{ formatearDinero(pedido.costo_envio) }}</span>
                    </div>

                    <div class="flex items-center justify-between bg-[#080f1e] border border-white/5 rounded-xl px-4 py-3 mt-3">
                        <span class="text-xs text-slate-400 font-bold">Total</span>
                        <span class="text-lg font-black text-[#8cc63f]">{{ formatearDinero(pedido.total) }}</span>
                    </div>

                    <div class="grid grid-cols-2 gap-3 mt-4">
                        <div class="bg-[#080f1e] border border-white/5 rounded-xl px-4 py-2.5">
                            <p class="text-[8px] font-black tracking-widest text-slate-500 uppercase">Entrega</p>
                            <p class="text-xs font-bold text-white mt-0.5">{{ pedido.cliente_direccion === 'Retiro en local' ? '🏬 Retiro en local' : '🛵 Delivery' }}</p>
                        </div>
                        <div v-if="pedido.cliente_direccion !== 'Retiro en local'" class="bg-[#080f1e] border border-white/5 rounded-xl px-4 py-2.5">
                            <p class="text-[8px] font-black tracking-widest text-slate-500 uppercase">Dirección</p>
                            <p class="text-xs font-bold text-white mt-0.5">{{ pedido.cliente_direccion }}</p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 mt-6 justify-center">
                    <Link :href="'/tienda/' + tienda_slug + '/panel'"
                        class="inline-flex items-center justify-center gap-2 bg-[#00adef] hover:bg-[#00adef]/80 text-white font-black px-6 py-3 rounded-xl text-xs uppercase tracking-widest transition-all shadow-lg shadow-[#00adef]/20">
                        Ver mis pedidos
                    </Link>
                    <Link :href="'/tienda/' + tienda_slug"
                        class="inline-flex items-center justify-center gap-2 bg-white/5 hover:bg-white/10 border border-white/10 text-white font-black px-6 py-3 rounded-xl text-xs uppercase tracking-widest transition-all">
                        Seguir comprando
                    </Link>
                </div>
            </div>

            <!-- PENDING (con polling) -->
            <div v-if="estadoPantalla === 'pending'" class="text-center">
                <div class="w-20 h-20 mx-auto mb-6 bg-amber-500/15 border border-amber-500/30 rounded-full flex items-center justify-center">
                    <svg class="w-10 h-10 text-amber-400 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                </div>
                <h1 class="text-2xl font-black text-white tracking-tight">Estamos confirmando tu pago</h1>
                <p class="text-sm text-slate-400 mt-2 max-w-sm mx-auto">No cierres esta página. Estamos esperando la confirmación de Mercado Pago.</p>

                <div class="mt-8 bg-[#0f1929] border border-white/5 rounded-3xl p-6">
                    <div class="flex items-center gap-4 justify-center">
                        <div class="w-2 h-2 bg-amber-400 rounded-full animate-ping"></div>
                        <span class="text-xs font-bold text-slate-400">{{ segundosEspera }}s esperando confirmación...</span>
                    </div>
                    <p class="text-[10px] text-slate-600 mt-4">Mientras tanto, tu pedido ya fue registrado. Podés cerrar esta página y el pago se confirmará automáticamente.</p>
                </div>

                <Link :href="'/tienda/' + tienda_slug + '/panel'"
                    class="inline-flex items-center justify-center gap-2 mt-6 bg-white/5 hover:bg-white/10 border border-white/10 text-white font-black px-6 py-3 rounded-xl text-xs uppercase tracking-widest transition-all">
                    Ir a mis pedidos
                </Link>
            </div>

            <!-- REJECTED -->
            <div v-if="estadoPantalla === 'rejected'" class="text-center">
                <div class="w-20 h-20 mx-auto mb-6 bg-rose-500/15 border border-rose-500/30 rounded-full flex items-center justify-center">
                    <svg class="w-10 h-10 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                </div>
                <h1 class="text-2xl font-black text-white tracking-tight">El pago fue rechazado</h1>
                <p class="text-sm text-slate-400 mt-2 max-w-sm mx-auto">No se pudo procesar el pago. Podés intentar con otro medio o volver a la tienda.</p>

                <div class="flex flex-col sm:flex-row gap-3 mt-8 justify-center">
                    <Link :href="'/tienda/' + tienda_slug"
                        class="inline-flex items-center justify-center gap-2 bg-[#f7941e] hover:bg-[#f7941e]/80 text-white font-black px-6 py-3 rounded-xl text-xs uppercase tracking-widest transition-all shadow-lg shadow-[#f7941e]/20">
                        Volver a la tienda
                    </Link>
                    <Link :href="'/tienda/' + tienda_slug + '/panel'"
                        class="inline-flex items-center justify-center gap-2 bg-white/5 hover:bg-white/10 border border-white/10 text-white font-black px-6 py-3 rounded-xl text-xs uppercase tracking-widest transition-all">
                        Ver mis pedidos
                    </Link>
                </div>
            </div>

            <!-- TIMEOUT -->
            <div v-if="estadoPantalla === 'timeout'" class="text-center">
                <div class="w-20 h-20 mx-auto mb-6 bg-slate-500/15 border border-slate-500/30 rounded-full flex items-center justify-center">
                    <svg class="w-10 h-10 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h1 class="text-2xl font-black text-white tracking-tight">Tiempo de espera agotado</h1>
                <p class="text-sm text-slate-400 mt-2 max-w-sm mx-auto">La confirmación del pago está demorando más de lo normal. Revisá tus pedidos para ver el estado actualizado.</p>

                <div class="flex flex-col sm:flex-row gap-3 mt-8 justify-center">
                    <Link :href="'/tienda/' + tienda_slug + '/panel'"
                        class="inline-flex items-center justify-center gap-2 bg-[#00adef] hover:bg-[#00adef]/80 text-white font-black px-6 py-3 rounded-xl text-xs uppercase tracking-widest transition-all shadow-lg shadow-[#00adef]/20">
                        Ver mis pedidos
                    </Link>
                    <Link :href="'/tienda/' + tienda_slug"
                        class="inline-flex items-center justify-center gap-2 bg-white/5 hover:bg-white/10 border border-white/10 text-white font-black px-6 py-3 rounded-xl text-xs uppercase tracking-widest transition-all">
                        Volver a la tienda
                    </Link>
                </div>
            </div>

        </div>
    </div>
</template>
