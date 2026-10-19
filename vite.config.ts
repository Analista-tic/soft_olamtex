import vue from '@vitejs/plugin-vue';
import autoprefixer from 'autoprefixer';
import laravel from 'laravel-vite-plugin';
import path from 'path';
import tailwindcss from 'tailwindcss';
import { resolve } from 'node:path';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.ts'],
            ssr: 'resources/js/ssr.ts',
            refresh: ['resources/views/**', 'routes/**'],
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
    // server: {
    //     host: '0.0.0.0',
    //     hmr: {
    //         host: 'localhost',
    //     },
    //     watch: {
    //         ignored: ['**/storage/**', '**/public/build/**', '**/bootstrap/cache/**'],
    //     },
    // },
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        origin: 'http://192.168.1.90:5173',
        cors: true, // la página (:80) y los módulos (:5173) son orígenes distintos
        hmr: {
            host: '192.168.1.90', // TU IP LOCAL
        },
        watch: {
            ignored: [
                '**/storage/**',
                '**/public/build/**',
                '**/bootstrap/cache/**',
                '**/vendor/**',
                '**/node_modules/**',
                '**/.git/**',
                '**/tests/**',
                '**/database/**',
            ],
        },
    },
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './resources/js'),
            'ziggy-js': resolve(__dirname, 'vendor/tightenco/ziggy'),
        },
    },
    css: {
        postcss: {
            plugins: [tailwindcss, autoprefixer],
        },
    },
});
