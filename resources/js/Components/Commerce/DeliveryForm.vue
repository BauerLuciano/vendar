<script setup>
import { computed } from 'vue';

const props = defineProps({
    formData: { type: Object, required: true },
    localizando: { type: Boolean, default: false },
    distanciaClienteKm: { type: Number, default: 0 },
    fueraDeRango: { type: Boolean, default: false },
    comercio: { type: Object, default: null },
});

const emit = defineEmits([
    'update:tipo-entrega',
    'update:direccion',
    'update:piso',
    'update:nombre',
    'update:telefono',
    'update:email',
    'update:notas',
    'usar-gps',
]);

const distanceLabel = computed(() => {
    if (props.distanciaClienteKm <= 0) return '';
    return props.distanciaClienteKm < 1
        ? `A ${Math.round(props.distanciaClienteKm * 1000)} m`
        : `A ${props.distanciaClienteKm.toFixed(1)} km`;
});
</script>

<template>
    <div>
        <p class="text-[10px] font-black tracking-widest uppercase mb-2 transition-colors" :style="{ color: 'var(--text-secondary)' }">
            Tipo de entrega
        </p>
        <div class="grid grid-cols-2 gap-2">
            <button
                @click="$emit('update:tipo-entrega', 'local')"
                :style="formData.tipo_entrega === 'local'
                    ? { backgroundColor: 'var(--color-accent)', color: '#fff', borderColor: 'var(--color-accent)' }
                    : { backgroundColor: 'var(--bg-card)', color: 'var(--text-secondary)', borderColor: 'var(--border-color)' }"
                class="py-2.5 border-2 rounded-xl text-[11px] font-black uppercase flex flex-col items-center gap-1 transition-all"
            >
                <span>🏬</span> Retiro en local
            </button>
            <button
                @click="$emit('update:tipo-entrega', 'delivery')"
                :style="formData.tipo_entrega === 'delivery'
                    ? { backgroundColor: 'var(--color-secondary)', color: '#fff', borderColor: 'var(--color-secondary)' }
                    : { backgroundColor: 'var(--bg-card)', color: 'var(--text-secondary)', borderColor: 'var(--border-color)' }"
                class="py-2.5 border-2 rounded-xl text-[11px] font-black uppercase flex flex-col items-center gap-1 transition-all"
            >
                <span>🛵</span> Delivery
            </button>
        </div>

        <div
            v-if="formData.tipo_entrega === 'delivery'"
            class="space-y-2 p-3 rounded-xl mt-2"
            :style="{
                backgroundColor: 'color-mix(in srgb, var(--color-secondary) 8%, transparent)',
                border: '1px solid color-mix(in srgb, var(--color-secondary) 20%, transparent)',
            }"
        >
            <div class="flex items-center justify-between mb-1">
                <button
                    @click="$emit('usar-gps')"
                    type="button"
                    class="text-[10px] font-bold flex items-center gap-1 transition-colors hover:text-white"
                    :style="{ color: 'var(--color-secondary)' }"
                >
                    <span v-if="localizando" class="animate-spin">⟳</span>
                    <span v-else>📍</span>
                    Fijar mi ubicación GPS
                </button>
                <span
                    v-if="distanciaClienteKm > 0"
                    class="text-[10px] font-bold px-2 py-0.5 rounded-full transition-colors"
                    :style="{
                        color: 'var(--text-muted)',
                        backgroundColor: 'var(--border-subtle)',
                    }"
                >
                    {{ distanceLabel }}
                </span>
            </div>

            <input
                :value="formData.direccion_entrega"
                @input="$emit('update:direccion', $event.target.value)"
                type="text"
                placeholder="Calle y número..."
                class="w-full border rounded-xl p-2.5 text-xs transition-all focus:outline-none placeholder-[var(--text-muted)]"
                :style="{
                    backgroundColor: 'var(--bg-input)',
                    borderColor: 'var(--border-color)',
                    color: 'var(--text-primary)',
                }"
            >
            <input
                :value="formData.piso_depto"
                @input="$emit('update:piso', $event.target.value)"
                type="text"
                placeholder="Casa, Depto, Piso (Opcional)..."
                class="w-full border rounded-xl p-2.5 text-xs transition-all focus:outline-none placeholder-[var(--text-muted)]"
                :style="{
                    backgroundColor: 'var(--bg-input)',
                    borderColor: 'var(--border-color)',
                    color: 'var(--text-primary)',
                }"
            >
            <input
                :value="formData.cliente_nombre"
                @input="$emit('update:nombre', $event.target.value)"
                type="text"
                placeholder="Tu nombre..."
                class="w-full border rounded-xl p-2.5 text-xs transition-all focus:outline-none placeholder-[var(--text-muted)]"
                :style="{
                    backgroundColor: 'var(--bg-input)',
                    borderColor: 'var(--border-color)',
                    color: 'var(--text-primary)',
                }"
            >
            <input
                :value="formData.telefono_contacto"
                @input="$emit('update:telefono', $event.target.value)"
                type="text"
                placeholder="Teléfono de contacto..."
                class="w-full border rounded-xl p-2.5 text-xs transition-all focus:outline-none placeholder-[var(--text-muted)]"
                :style="{
                    backgroundColor: 'var(--bg-input)',
                    borderColor: 'var(--border-color)',
                    color: 'var(--text-primary)',
                }"
            >
            <input
                :value="formData.cliente_email"
                @input="$emit('update:email', $event.target.value)"
                type="email"
                placeholder="Correo electrónico (opcional)..."
                class="w-full border rounded-xl p-2.5 text-xs transition-all focus:outline-none placeholder-[var(--text-muted)]"
                :style="{
                    backgroundColor: 'var(--bg-input)',
                    borderColor: 'var(--border-color)',
                    color: 'var(--text-primary)',
                }"
            >
            <textarea
                :value="formData.notas"
                @input="$emit('update:notas', $event.target.value)"
                placeholder="Observaciones (Ej: Tocar timbre fuerte, sin cebolla)..."
                class="w-full border rounded-xl p-2.5 text-xs transition-all focus:outline-none placeholder-[var(--text-muted)] resize-none"
                rows="2"
                :style="{
                    backgroundColor: 'var(--bg-input)',
                    borderColor: 'var(--border-color)',
                    color: 'var(--text-primary)',
                }"
            />

            <p
                v-if="fueraDeRango"
                class="text-[10px] font-bold flex items-center gap-1 mt-1"
                :style="{ color: 'var(--color-danger)' }"
            >
                ⚠️ Estás fuera de la zona de cobertura (Max: {{ comercio?.envio_radio_km }}km).
            </p>
        </div>
    </div>
</template>
