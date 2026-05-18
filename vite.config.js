import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import VueDevTools from 'vite-plugin-vue-devtools';

export default defineConfig({
    server: {
        host: '0.0.0.0',
        port: 5174, // CAMBIAMOS ACÁ PARA EVITAR EL CHOQUE CON DOCKER
        strictPort: true,
        hmr: {
            host: 'localhost',
        }
    },
    plugins: [
        // LE AGREGAMOS ESTA OPCIÓN OFICIAL PARA ENTRAR EN EL FLUJO DE LARAVEL
        VueDevTools({
            appendTo: 'resources/js/app.js'
        }),
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
    ],
});