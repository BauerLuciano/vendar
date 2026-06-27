<script setup>
defineProps({
    section: { type: Object, default: () => ({}) },
    comercio: Object,
});
</script>

<template>
    <footer class="border-t mt-10 py-8 px-4 sm:px-6 transition-colors" :style="{ backgroundColor: 'var(--bg-elevated)', borderColor: 'var(--border-subtle)' }">
        <div class="max-w-7xl mx-auto">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div v-if="section.show_contact">
                    <h4 class="text-xs font-black uppercase tracking-widest mb-3 transition-colors" :style="{ color: 'var(--text-primary)' }">Contacto</h4>
                    <p class="text-xs transition-colors" :style="{ color: 'var(--text-muted)' }">
                        {{ comercio?.nombre || 'Tienda' }}
                    </p>
                </div>
                <div v-if="section.show_social">
                    <h4 class="text-xs font-black uppercase tracking-widest mb-3 transition-colors" :style="{ color: 'var(--text-primary)' }">Seguinos</h4>
                    <p class="text-xs transition-colors" :style="{ color: 'var(--text-muted)' }">Instagram | Facebook</p>
                </div>
                <div v-if="section.show_payment_methods">
                    <h4 class="text-xs font-black uppercase tracking-widest mb-3 transition-colors" :style="{ color: 'var(--text-primary)' }">Medios de pago</h4>
                    <p class="text-xs transition-colors" :style="{ color: 'var(--text-muted)' }">Efectivo | Transferencia | Mercado Pago</p>
                </div>
            </div>
            <div class="mt-8 pt-6 border-t text-center transition-colors" :style="{ borderColor: 'var(--border-subtle)' }">
                <p class="text-[10px] transition-colors" :style="{ color: 'var(--text-muted)' }">
                    {{ section.text || '© 2026 Tienda - Todos los derechos reservados' }}
                </p>
            </div>
        </div>
    </footer>
</template>
