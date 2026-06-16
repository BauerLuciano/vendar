cle<script setup>
import { Head, Link } from '@inertiajs/vue3';


const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    planes: Array,
});

const faqs = [
    { q: "¿Necesito una computadora potente?", a: "Para nada. VendAR funciona en cualquier navegador. Podés usar una PC vieja, una tablet o incluso tu propio celular. Solo necesitás conexión a internet." },
    { q: "¿Cómo es el soporte técnico?", a: "Ofrecemos asistencia virtual inmediata por WhatsApp y conexión remota. Para configuraciones más complejas o puesta en marcha inicial, coordinamos visitas presenciales para asegurar que todo funcione perfecto." },
    { q: "¿Mis datos están seguros?", a: "Sí, toda tu información se guarda encriptada en la nube. Hacemos copias de seguridad diarias para que nunca pierdas tus ventas ni tu stock." },
    { q: "¿Puedo probar el sistema antes de pagar?", a: "¡Claro! Podés registrarte y configurar tu local. El pago se activa una vez que decidas empezar a operar formalmente con uno de nuestros planes." }
];

const labelsModulos = {
    pos: 'Caja rápida (POS)',
    lotes: 'Control de stock con lotes y vencimientos',
    fiados: 'Gestión de fiados y cuentas corrientes',
    proveedores: 'Gestión de compras y proveedores',
    auditoria: 'Auditoría y reportes avanzados',
    transferencias: 'Transferencias entre sucursales',
};

const coloresPlan = [
    { text: 'text-[#8cc63f]', bg: 'bg-[#8cc63f]' },
    { text: 'text-[#00adef]', bg: 'bg-[#00adef]' },
    { text: 'text-[#f7941e]', bg: 'bg-[#f7941e]' },
];

const precioFormateado = (precio) => {
    return '$' + Math.round(precio).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
};

const featuresDePlan = (plan) => {
    const f = [];
    if (plan.modulos) {
        Object.entries(plan.modulos).forEach(([mod, activo]) => {
            if (activo && labelsModulos[mod]) f.push(labelsModulos[mod]);
        });
    }
    if (plan.sucursales_limit > 1) {
        f.push(`Hasta ${plan.sucursales_limit} sucursales`);
    }
    if (plan.usuarios_limit > 1) {
        f.push(`Hasta ${plan.usuarios_limit} usuarios`);
    }
    return f;
};
</script>

<template>
    <Head title="VendAR | Sistema de Gestión Multi-sucursal" />

    <div class="min-h-screen bg-[#050a15] text-slate-200 font-sans selection:bg-[#00adef] selection:text-white">
        
        <div class="fixed inset-0 pointer-events-none overflow-hidden z-0">
            <div class="absolute w-[1000px] h-[1000px] bg-[#00adef]/5 rounded-full blur-[150px] -top-80 -left-60 animate-pulse"></div>
            <div class="absolute w-[800px] h-[800px] bg-[#f7941e]/5 rounded-full blur-[130px] bottom-[-20%] right-[-10%]"></div>
        </div>

        <nav class="relative z-50 border-b border-white/5 bg-[#050a15]/60 backdrop-blur-xl sticky top-0">
            <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <img src="/img/LogoVendar-Sidebar.png" alt="VendAR" class="h-10 w-auto">
                    <span class="text-2xl font-black text-white uppercase tracking-tighter italic">Vend<span class="text-[#00adef]">AR</span></span>
                </div>
                
                <div class="hidden md:flex items-center gap-8 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">
                    <a href="#beneficios" class="hover:text-[#00adef] transition-colors">Beneficios</a>
                    <a href="#como-funciona" class="hover:text-[#00adef] transition-colors">Cómo Funciona</a>
                    <a href="#planes" class="hover:text-[#00adef] transition-colors">Precios</a>
                </div>

                <div class="flex items-center gap-4">
                    <Link v-if="canLogin" :href="route('login')" class="text-[10px] font-black uppercase tracking-widest hover:text-white transition-all">Ingresar</Link>
                    <Link v-if="canRegister" :href="route('register')" class="bg-white text-black px-6 py-2.5 rounded-full font-black text-[10px] uppercase tracking-widest hover:bg-[#00adef] hover:text-white transition-all shadow-xl">
                        Registrar local
                    </Link>
                </div>
            </div>
        </nav>

        <header class="relative z-10 max-w-7xl mx-auto px-6 pt-28 pb-24 text-center">
            <div class="inline-flex items-center gap-2 px-4 py-1.5 mb-8 rounded-full bg-white/5 border border-white/10 backdrop-blur-md">
                <span class="w-2 h-2 rounded-full bg-[#8cc63f] animate-ping"></span>
                <span class="text-[9px] font-black text-slate-300 uppercase tracking-[0.3em]">Gestión profesional para tu comercio</span>
            </div>
            
            <h1 class="text-6xl md:text-8xl font-black text-white mb-8 tracking-tighter leading-[0.85]">
                Controlá tu local <br>
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-[#00adef] via-[#8cc63f] to-[#f7941e]">
                    desde donde estés.
                </span>
            </h1>

            <p class="text-xl text-slate-400 max-w-2xl mx-auto mb-12 font-medium leading-relaxed">
                VendAR es la herramienta definitiva para dueños de negocios. 
                Ventas, stock, cuentas de fiados y tu propia tienda online en una sola plataforma que usás desde tu <span class="text-white font-bold">Celular, Tablet o PC</span>.
            </p>

            <div class="flex flex-col sm:flex-row justify-center items-center gap-5">
                <Link :href="route('register')" class="w-full sm:w-auto bg-[#00adef] text-white px-12 py-5 rounded-full font-black text-xs uppercase tracking-[0.2em] shadow-2xl shadow-[#00adef]/20 hover:scale-105 active:scale-95 transition-all">
                    Empezar ahora
                </Link>
                <div class="flex items-center gap-3 text-slate-500 text-[10px] font-black uppercase">
                    <span>✓ Sin contratos</span>
                    <span>✓ Soporte dedicado</span>
                </div>
            </div>
        </header>

        <section id="beneficios" class="relative z-10 max-w-7xl mx-auto px-6 py-32 border-t border-white/5">
            <div class="mb-20">
                <h2 class="text-4xl font-black text-white mb-4 tracking-tighter uppercase italic">Todo bajo control, <br> sin complicaciones.</h2>
                <p class="text-slate-500 max-w-md">Diseñamos VendAR para solucionar los problemas diarios de los comerciantes.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <div class="group p-10 rounded-[3rem] bg-white/[0.02] border border-white/5 hover:bg-white/[0.04] transition-all duration-500 shadow-xl">
                    <div class="w-14 h-14 bg-[#00adef]/20 rounded-2xl flex items-center justify-center text-2xl mb-8 group-hover:scale-110 transition-transform">🏪</div>
                    <h3 class="text-xl font-black text-white mb-4 italic">Tienda Online Propia</h3>
                    <p class="text-slate-500 text-sm leading-relaxed font-medium">Tus clientes acceden a tu catálogo desde su celular, ven tus precios reales y te hacen el pedido por WhatsApp. Automatizá tus ventas por internet hoy mismo.</p>
                </div>

                <div class="group p-10 rounded-[3rem] bg-white/[0.02] border border-white/5 hover:bg-white/[0.04] transition-all duration-500 shadow-xl">
                    <div class="w-14 h-14 bg-[#8cc63f]/20 rounded-2xl flex items-center justify-center text-2xl mb-8 group-hover:scale-110 transition-transform">📊</div>
                    <h3 class="text-xl font-black text-white mb-4 italic">Stock de Precisión</h3>
                    <p class="text-slate-500 text-sm leading-relaxed font-medium">Gestión de lotes y fechas de vencimiento. El sistema te avisa cuando un producto se está por terminar o vencer para que no pierdas mercadería ni dinero.</p>
                </div>

                <div class="group p-10 rounded-[3rem] bg-white/[0.02] border border-white/5 hover:bg-white/[0.04] transition-all duration-500 shadow-xl">
                    <div class="w-14 h-14 bg-[#f7941e]/20 rounded-2xl flex items-center justify-center text-2xl mb-8 group-hover:scale-110 transition-transform">📓</div>
                    <h3 class="text-xl font-black text-white mb-4 italic">Cuentas Corrientes</h3>
                    <p class="text-slate-500 text-sm leading-relaxed font-medium">Olvidate del cuadernito de fiados. Llevá la cuenta digital de cada cliente, registrá pagos parciales y mirá el saldo total adeudado al instante.</p>
                </div>

                <div class="group p-10 rounded-[3rem] bg-white/[0.02] border border-white/5 hover:bg-white/[0.04] transition-all duration-500 shadow-xl">
                    <div class="w-14 h-14 bg-white/10 rounded-2xl flex items-center justify-center text-2xl mb-8 group-hover:scale-110 transition-transform">⚡</div>
                    <h3 class="text-xl font-black text-white mb-4 italic">Caja Rápida (POS)</h3>
                    <p class="text-slate-500 text-sm leading-relaxed font-medium">Sistema de cobro ágil compatible con lectores de barras. Generá tickets profesionales y cerrá tu caja diaria sin descuadres.</p>
                </div>

                <div class="group p-10 rounded-[3rem] bg-white/[0.02] border border-white/5 hover:bg-white/[0.04] transition-all duration-500 shadow-xl">
                    <div class="w-14 h-14 bg-white/10 rounded-2xl flex items-center justify-center text-2xl mb-8 group-hover:scale-110 transition-transform">📍</div>
                    <h3 class="text-xl font-black text-white mb-4 italic">Multi-sucursal</h3>
                    <p class="text-slate-500 text-sm leading-relaxed font-medium">Controlá todos tus locales desde una sola cuenta. Consultá el stock de una sucursal desde otra y centralizá tus ganancias en tiempo real.</p>
                </div>

                <div class="group p-10 rounded-[3rem] bg-white/[0.02] border border-white/5 hover:bg-white/[0.04] transition-all duration-500 shadow-xl">
                    <div class="w-14 h-14 bg-[#8cc63f]/20 rounded-2xl flex items-center justify-center text-2xl mb-8 group-hover:scale-110 transition-transform">📞</div>
                    <h3 class="text-xl font-black text-white mb-4 italic">Soporte Cercano</h3>
                    <p class="text-slate-500 text-sm leading-relaxed font-medium">Asistencia humana por WhatsApp y conexión remota. También coordinamos visitas presenciales para dejar tu local funcionando al 100%. Estamos con vos en cada paso.</p>
                </div>
            </div>
        </section>

        <section id="como-funciona" class="relative z-10 bg-white/[0.02] py-32 border-y border-white/5">
            <div class="max-w-7xl mx-auto px-6">
                <div class="text-center mb-20">
                    <h2 class="text-4xl font-black text-white uppercase italic tracking-tighter">Ordená tu negocio en 3 pasos</h2>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-16 relative">
                    <div class="hidden md:block absolute top-12 left-[20%] right-[20%] h-[2px] bg-gradient-to-r from-[#00adef] to-[#f7941e] opacity-20"></div>
                    
                    <div class="relative text-center">
                        <div class="w-24 h-24 bg-slate-900 border-4 border-[#00adef] rounded-full flex items-center justify-center text-3xl font-black text-white mx-auto mb-8 relative z-10 shadow-[0_0_40px_rgba(0,173,239,0.2)]">1</div>
                        <h4 class="text-xl font-black text-white mb-4 uppercase italic">Registrate</h4>
                        <p class="text-slate-500 text-sm font-medium">Creás tu cuenta con los datos de tu comercio y sucursales en menos de un minuto.</p>
                    </div>

                    <div class="relative text-center">
                        <div class="w-24 h-24 bg-slate-900 border-4 border-[#8cc63f] rounded-full flex items-center justify-center text-3xl font-black text-white mx-auto mb-8 relative z-10 shadow-[0_0_40px_rgba(140,198,63,0.2)]">2</div>
                        <h4 class="text-xl font-black text-white mb-4 uppercase italic">Subí tu Stock</h4>
                        <p class="text-slate-500 text-sm font-medium">Cargás tus productos manualmente o vía Excel. Tu catálogo online se genera automáticamente.</p>
                    </div>

                    <div class="relative text-center">
                        <div class="w-24 h-24 bg-slate-900 border-4 border-[#f7941e] rounded-full flex items-center justify-center text-3xl font-black text-white mx-auto mb-8 relative z-10 shadow-[0_0_40px_rgba(247,148,30,0.2)]">3</div>
                        <h4 class="text-xl font-black text-white mb-4 uppercase italic">Empezá a cobrar</h4>
                        <p class="text-slate-500 text-sm font-medium">Vendés en el mostrador o recibís pedidos web. Toda la información se sincroniza al instante.</p>
                    </div>
                </div>
            </div>
        </section>

    <section id="funcionalidades" class="relative z-10 max-w-7xl mx-auto px-6 py-24 border-t border-white/5">
        <div class="text-center mb-20">
            <h2 class="text-4xl font-black text-white uppercase italic tracking-tighter">Un sistema potente, <br> diseñado para ser simple.</h2>
            <p class="text-slate-500 mt-4 max-w-2xl mx-auto">No necesitás ser un experto en computación. Si sabés usar WhatsApp, sabés usar VendAR.</p>
        </div>

        <div class="space-y-32">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
                <div>
                    <div class="inline-block px-3 py-1 rounded-lg bg-[#00adef]/10 border border-[#00adef]/20 text-[#00adef] text-[10px] font-black uppercase mb-4">La Caja Inteligente</div>
                    <h3 class="text-3xl font-black text-white mb-6 italic">Cobrá en segundos, sin errores.</h3>
                    <p class="text-slate-400 leading-relaxed mb-8">
                        Nuestra pantalla de venta está optimizada para ser veloz. Buscá productos por nombre o usá un <strong>lector de códigos de barras</strong>. 
                        Podés aplicar descuentos, elegir el método de pago y entregar un ticket profesional al instante.
                    </p>
                    <ul class="space-y-3">
                        <li class="flex items-center gap-3 text-sm text-slate-300 font-medium"><span class="text-[#00adef]">✔</span> Compatible con pistolas láser.</li>
                        <li class="flex items-center gap-3 text-sm text-slate-300 font-medium"><span class="text-[#00adef]">✔</span> Cierre de caja diario automático.</li>
                    </ul>
                </div>
                <div class="relative group">
                    <div class="absolute inset-0 bg-[#00adef]/20 blur-[80px] rounded-full group-hover:bg-[#00adef]/30 transition-all"></div>
                    <div class="relative bg-[#111c30] border border-white/10 rounded-[2rem] p-2 shadow-2xl">
                        <img src="/img/capturas/pos-preview.png" alt="Caja rápida" class="rounded-[1.5rem] w-full grayscale-[20%] group-hover:grayscale-0 transition-all shadow-inner">
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
                <div class="lg:order-2">
                    <div class="inline-block px-3 py-1 rounded-lg bg-[#f7941e]/10 border border-[#f7941e]/20 text-[#f7941e] text-[10px] font-black uppercase mb-4">Gestión de Fiados</div>
                    <h3 class="text-3xl font-black text-white mb-6 italic">Digitalizá tus Cuentas Corrientes.</h3>
                    <p class="text-slate-400 leading-relaxed mb-8">
                        ¿Cansado de anotar en papel? Buscá a tu cliente, cargale la compra y mirá su deuda total en tiempo real. 
                        El sistema te permite registrar pagos parciales y tener un historial claro de cada movimiento.
                    </p>
                    <ul class="space-y-3">
                        <li class="flex items-center gap-3 text-sm text-slate-300 font-medium"><span class="text-[#f7941e]">✔</span> Alerta de clientes con deuda alta.</li>
                        <li class="flex items-center gap-3 text-sm text-slate-300 font-medium"><span class="text-[#f7941e]">✔</span> Exportá el estado de cuenta a PDF.</li>
                    </ul>
                </div>
                <div class="relative group lg:order-1">
                    <div class="absolute inset-0 bg-[#f7941e]/20 blur-[80px] rounded-full group-hover:bg-[#f7941e]/30 transition-all"></div>
                    <div class="relative bg-[#111c30] border border-white/10 rounded-[2rem] p-2 shadow-2xl">
                        <img src="/img/capturas/clientes-preview.png" alt="Cuentas corrientes" class="rounded-[1.5rem] w-full shadow-inner">
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
                <div>
                    <div class="inline-block px-3 py-1 rounded-lg bg-[#8cc63f]/10 border border-[#8cc63f]/20 text-[#8cc63f] text-[10px] font-black uppercase mb-4">Control de Mercadería</div>
                    <h3 class="text-3xl font-black text-white mb-6 italic">Que nunca te falte nada.</h3>
                    <p class="text-slate-400 leading-relaxed mb-8">
                        Configurá el stock mínimo para cada producto. Cuando te queden pocas unidades, VendAR te avisará con un ícono rojo en tu panel. 
                        También podés gestionar <strong>lotes y vencimientos</strong> para evitar pérdidas innecesarias.
                    </p>
                    <ul class="space-y-3">
                        <li class="flex items-center gap-3 text-sm text-slate-300 font-medium"><span class="text-[#8cc63f]">✔</span> Alertas visuales de stock bajo.</li>
                        <li class="flex items-center gap-3 text-sm text-slate-300 font-medium"><span class="text-[#8cc63f]">✔</span> Control de mercadería por sucursal.</li>
                    </ul>
                </div>
                <div class="relative group">
                    <div class="absolute inset-0 bg-[#8cc63f]/20 blur-[80px] rounded-full group-hover:bg-[#8cc63f]/30 transition-all"></div>
                    <div class="relative bg-[#111c30] border border-white/10 rounded-[2rem] p-2 shadow-2xl">
                        <img src="/img/capturas/stock-preview.png" alt="Control de stock" class="rounded-[1.5rem] w-full shadow-inner">
                    </div>
                </div>
            </div>
        </div>
    </section>

        <section id="planes" class="relative z-10 max-w-7xl mx-auto px-6 py-32">
            <div class="text-center mb-20">
                <h2 class="text-5xl font-black text-white uppercase italic tracking-tighter mb-4">Planes para tu crecimiento</h2>
                <p class="text-slate-500 font-bold uppercase tracking-widest text-[10px]">Sin contratos forzosos. Cancelá cuando quieras.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div v-for="(plan, index) in planes" :key="plan.id" 
                    class="relative bg-[#091122] border rounded-[3.5rem] p-12 flex flex-col justify-between transition-all hover:scale-[1.02] duration-500 overflow-hidden shadow-2xl"
                    :class="plan.destacado ? 'border-[#00adef] scale-105 z-20 shadow-[#00adef]/10' : 'border-white/5'"
                >
                    <div v-if="plan.destacado" class="absolute top-0 right-0 bg-[#00adef] text-white text-[10px] font-black uppercase px-6 py-2 rounded-bl-3xl">Más Elegido</div>
                    
                    <div>
                        <h4 class="text-xl font-black text-white mb-2 tracking-tight uppercase italic">{{ plan.nombre }}</h4>
                        <p class="text-xs text-slate-500 font-bold mb-8 leading-relaxed">{{ plan.descripcion }}</p>
                        <div class="flex items-baseline gap-1 mb-10 border-b border-white/5 pb-8">
                            <span class="text-5xl font-black text-white tracking-tighter">{{ precioFormateado(plan.precio_mensual) }}</span>
                            <span v-if="plan.precio_mensual > 0" class="text-xs text-slate-500 font-bold uppercase">/mes</span>
                        </div>
                        <ul class="space-y-5">
                            <li v-for="feat in featuresDePlan(plan)" :key="feat" class="flex items-center gap-4 text-xs font-bold text-slate-400 leading-tight">
                                <span class="text-lg" :class="coloresPlan[index % 3].text">✔</span> {{ feat }}
                            </li>
                        </ul>
                    </div>
                    
                    <Link :href="plan.slug === 'premium' ? '#' : route('register')" :class="['mt-12 w-full py-5 rounded-full text-white font-black text-[11px] uppercase tracking-[0.2em] shadow-xl transition-all active:scale-95 inline-block text-center', coloresPlan[index % 3].bg]">
                        {{ plan.slug === 'premium' ? 'Consultar con ventas' : 'Elegir Plan' }}
                    </Link>
                </div>
            </div>
        </section>

        <section class="relative z-10 max-w-4xl mx-auto px-6 py-32 border-t border-white/5">
            <h2 class="text-3xl font-black text-white text-center mb-16 uppercase italic">Dudas frecuentes</h2>
            <div class="space-y-6">
                <div v-for="item in faqs" :key="item.q" class="bg-white/[0.03] border border-white/5 p-8 rounded-3xl">
                    <h4 class="text-lg font-black text-white mb-3">{{ item.q }}</h4>
                    <p class="text-slate-500 text-sm leading-relaxed font-medium">{{ item.a }}</p>
                </div>
            </div>
        </section>

        <section class="relative z-10 max-w-7xl mx-auto px-6 py-32 text-center">
            <div class="bg-gradient-to-br from-[#00adef]/20 to-transparent p-20 rounded-[4rem] border border-[#00adef]/20 shadow-2xl">
                <h2 class="text-5xl font-black text-white mb-8 tracking-tighter italic uppercase">¿Listo para transformar <br> tu local?</h2>
                <Link :href="route('register')" class="bg-[#f7941e] text-white px-12 py-6 rounded-full font-black text-sm uppercase tracking-[0.2em] shadow-2xl shadow-[#f7941e]/30 hover:scale-105 active:scale-95 transition-all">
                    Registrar mi comercio hoy
                </Link>
            </div>
        </section>

        <footer class="relative z-10 border-t border-white/5 py-12 bg-black/40">
            <div class="max-w-7xl mx-auto px-6 flex flex-col md:flex-row justify-between items-center gap-8">
                <div class="flex items-center gap-3 grayscale opacity-60">
                    <img src="/img/LogoVendar-Sidebar.png" alt="VendAR" class="h-8 w-auto">
                    <span class="text-xl font-black text-white uppercase italic tracking-tighter">VendAR</span>
                </div>
                <p class="text-slate-600 text-[10px] font-black uppercase tracking-[0.3em]">
                    © 2026 VendAR SaaS. Asistencia profesional personalizada.
                </p>
            </div>
        </footer>

    </div>
</template>

<style scoped>
html { scroll-behavior: smooth; }
::-webkit-scrollbar { width: 8px; }
::-webkit-scrollbar-track { background: #050a15; }
::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 10px; }
::-webkit-scrollbar-thumb:hover { background: #334155; }
</style>