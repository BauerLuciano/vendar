import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import VueDevTools from 'vite-plugin-vue-devtools';

export default defineConfig({
    server: {
        host: '0.0.0.0', // Escucha en toda tu red local
        cors: true,
        hmr: {
            // 🔥 Lo cambiamos a localhost para que no dependa de tu IP del Wi-Fi
            host: 'localhost' 
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