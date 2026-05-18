import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import VueDevTools from 'vite-plugin-vue-devtools';

export default defineConfig({
    server: {
        host: '0.0.0.0', // Esto hace que funcione en TODAS las compus
        hmr: {
            host: 'localhost'
        },
        port: 5173,
        cors: true,
        watch: {
            usePolling: true,
        }
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        VueDevTools(),
    ],
});