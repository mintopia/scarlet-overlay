import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import path from 'path';

export default defineConfig({
    build: {
        sourcemap: true,
    },
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    server: {
        allowedHosts: true,
        cors: true,
        hmr: {
            host: 'scarlet-vite.slow-sheppard.ws.cloudagent.mintopia.net',
            protocol: 'wss',
            clientPort: 443,
        },
    },
    resolve: {
        alias: {
            '@': '/resources/js',
            'ziggy-js': path.resolve(__dirname, 'vendor/tightenco/ziggy/src/js'),
        },
    },
});
