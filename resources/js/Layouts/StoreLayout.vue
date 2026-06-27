<script setup>
import { ref, computed, watch, provide, onMounted, onUnmounted } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';

const props = defineProps({
    titulo: String,
    comercio: { type: Object, default: null },
    storeConfig: { type: Object, default: null },
});

const page = usePage();

const seoConfig = computed(() => props.storeConfig?.seo || {});
const comercioData = computed(() => props.comercio || {});

const orgJson = computed(() => {
    const data = {
        '@context': 'https://schema.org',
        '@type': 'Organization',
        name: comercioData.value.nombre || 'Tienda',
        url: page.url,
    };
    const logo = comercioData.value.url_logo;
    if (logo) data.logo = logo;
    return JSON.stringify(data);
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
    const t = props.storeConfig?.theme || {};
    const luz = theme.value === 'light';
    return {
        '--bg-page': luz && t.background_color ? t.background_color : (luz ? '#ffffff' : '#0b1120'),
        '--bg-card': luz ? '#ffffff' : '#162032',
        '--bg-elevated': luz ? '#f5f5f5' : '#0f1929',
        '--bg-input': luz ? '#e8e8e8' : '#111c30',
        '--bg-navbar': luz && t.header_background ? t.header_background : (luz ? '#ffffff' : '#0b1120'),
        '--bg-categories': luz ? '#ffffff' : '#0a1325',
        '--bg-skeleton': luz ? '#e8e8e8' : '#162032',
        '--bg-backdrop': '#000000',
        '--bg-image': '#ffffff',
        '--bg-disabled': luz ? '#cbd5e1' : '#1e293b',
        '--overlay': luz ? 'rgba(0,0,0,0.2)' : 'rgba(0,0,0,0.6)',
        '--text-primary': luz && t.text_color ? t.text_color : (luz ? '#0f172a' : '#ffffff'),
        '--text-secondary': luz ? '#1e293b' : '#cbd5e1',
        '--text-muted': luz ? '#475569' : '#94a3b8',
        '--text-disabled': luz ? '#94a3b8' : '#64748b',
        '--text-on-accent': '#ffffff',
        '--color-accent': t.primary_color || '#00adef',
        '--color-accent-hover': t.primary_color || '#00adef',
        '--color-secondary': t.secondary_color || '#f7941e',
        '--color-secondary-hover': t.secondary_color || '#f7941e',
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

let ldJsonEl = null;

function injectLdJson() {
    if (!ldJsonEl) {
        ldJsonEl = document.createElement('script');
        ldJsonEl.type = 'application/ld+json';
        ldJsonEl.id = 'vendar-ld-json';
        document.head.appendChild(ldJsonEl);
    }
    ldJsonEl.textContent = orgJson.value;
}

function removeLdJson() {
    if (ldJsonEl) {
        ldJsonEl.remove();
        ldJsonEl = null;
    }
}

onMounted(injectLdJson);
watch(orgJson, injectLdJson);
onUnmounted(removeLdJson);

watch(theme, aplicarVariables, { immediate: true });
</script>

<template>
    <Head :title="titulo || 'VendAR'">
        <meta name="description" :content="seoConfig.meta_description || `${comercioData.nombre || 'Tienda'} - Productos y ofertas`" head-key="description" />
        <meta v-if="seoConfig.meta_keywords" name="keywords" :content="seoConfig.meta_keywords" head-key="keywords" />
        <meta property="og:title" :content="seoConfig.og_title || titulo || comercioData.nombre || 'VendAR'" head-key="og:title" />
        <meta property="og:description" :content="seoConfig.og_description || seoConfig.meta_description || `${comercioData.nombre || 'Tienda'} - Productos y ofertas`" head-key="og:description" />
        <meta v-if="seoConfig.og_image || comercioData.url_logo" property="og:image" :content="seoConfig.og_image || comercioData.url_logo" head-key="og:image" />
        <meta property="og:url" :content="page.url" head-key="og:url" />
        <meta property="og:type" content="website" head-key="og:type" />
        <meta name="twitter:card" content="summary_large_image" head-key="twitter:card" />
        <meta name="twitter:title" :content="seoConfig.og_title || titulo || comercioData.nombre || 'VendAR'" head-key="twitter:title" />
        <meta name="twitter:description" :content="seoConfig.og_description || seoConfig.meta_description || `${comercioData.nombre || 'Tienda'} - Productos y ofertas`" head-key="twitter:description" />
        <meta v-if="seoConfig.og_image || comercioData.url_logo" name="twitter:image" :content="seoConfig.og_image || comercioData.url_logo" head-key="twitter:image" />
    </Head>

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
