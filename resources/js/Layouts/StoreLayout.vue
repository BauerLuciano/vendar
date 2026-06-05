<script setup>
import { ref, computed, watch, provide, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    titulo: String,
});

const theme = ref('dark');

onMounted(() => {
    theme.value = localStorage.getItem('vendar_theme') || 'dark';
});

const toggleTheme = () => {
    theme.value = theme.value === 'dark' ? 'light' : 'dark';
    localStorage.setItem('vendar_theme', theme.value);
};

provide('theme', theme);
provide('toggleTheme', toggleTheme);

const themeVariables = computed(() => {
    const luz = theme.value === 'light';
    return {
        '--bg-page': luz ? '#ffffff' : '#0b1120',
        '--bg-card': luz ? '#ffffff' : '#162032',
        '--bg-elevated': luz ? '#f5f5f5' : '#0f1929',
        '--bg-input': luz ? '#e8e8e8' : '#111c30',
        '--bg-navbar': luz ? '#ffffff' : '#0b1120',
        '--bg-categories': luz ? '#ffffff' : '#0a1325',
        '--bg-skeleton': luz ? '#e8e8e8' : '#162032',
        '--bg-backdrop': luz ? '#000000' : '#000000',
        '--bg-image': luz ? '#ffffff' : '#ffffff',
        '--bg-disabled': luz ? '#cbd5e1' : '#1e293b',
        '--overlay': luz ? 'rgba(0,0,0,0.2)' : 'rgba(0,0,0,0.6)',
        '--text-primary': luz ? '#0f172a' : '#ffffff',
        '--text-secondary': luz ? '#1e293b' : '#cbd5e1',
        '--text-muted': luz ? '#475569' : '#94a3b8',
        '--text-disabled': luz ? '#94a3b8' : '#64748b',
        '--text-on-accent': luz ? '#ffffff' : '#ffffff',
        '--color-accent': '#00adef',
        '--color-accent-hover': '#0095d4',
        '--color-secondary': '#f7941e',
        '--color-secondary-hover': '#e0851a',
        '--color-success': '#8cc63f',
        '--color-success-hover': '#7ab336',
        '--color-danger': luz ? '#e11d48' : '#f43f5e',
        '--color-danger-hover': luz ? '#be123c' : '#e11d48',
        '--border-color': luz ? 'rgba(0,0,0,0.12)' : 'rgba(255,255,255,0.07)',
        '--border-subtle': luz ? 'rgba(0,0,0,0.06)' : 'rgba(255,255,255,0.04)',
        '--shadow-sm': luz ? '0 1px 3px rgba(0,0,0,0.08)' : '0 1px 3px rgba(0,0,0,0.3)',
        '--shadow-md': luz ? '0 4px 12px rgba(0,0,0,0.1)' : '0 4px 12px rgba(0,0,0,0.35)',
        '--shadow-lg': luz ? '0 10px 40px rgba(0,0,0,0.12)' : '0 10px 40px rgba(0,0,0,0.5)',
        '--radius-sm': '8px',
        '--radius-md': '12px',
        '--radius-lg': '16px',
        '--radius-xl': '24px',
        '--glow-opacity': luz ? '0' : '1',
    };
});

const aplicarVariables = () => {
    const vars = themeVariables.value;
    Object.entries(vars).forEach(([key, value]) => {
        document.documentElement.style.setProperty(key, value);
    });
    document.documentElement.style.colorScheme = theme.value;
};

watch(theme, aplicarVariables, { immediate: true });
</script>

<template>
    <Head :title="titulo || 'VendAR'" />

    <div class="min-h-screen font-sans relative flex flex-col transition-colors duration-300" :style="{ backgroundColor: 'var(--bg-page)', color: 'var(--text-secondary)' }">
        <div class="fixed inset-0 pointer-events-none overflow-hidden z-0" :style="{ opacity: 'var(--glow-opacity)' }">
            <div class="absolute w-[700px] h-[700px] bg-[#00adef]/6 rounded-full blur-[180px] -top-40 -left-40"></div>
            <div class="absolute w-[500px] h-[500px] bg-[#f7941e]/5 rounded-full blur-[160px] bottom-0 right-0"></div>
            <div class="absolute w-[300px] h-[300px] bg-[#8cc63f]/4 rounded-full blur-[120px] top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2"></div>
            <div class="absolute inset-0" :style="{ backgroundImage: theme === 'dark' ? 'linear-gradient(rgba(0,173,239,0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(0,173,239,0.03) 1px, transparent 1px)' : 'none', backgroundSize: '40px 40px' }"></div>
        </div>

        <slot />
    </div>
</template>
