import {defineConfig} from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    css: {
        devSourcemap: true,
    },
    plugins: [
        laravel({
            input: [
                'resources/js/app.js',
                'resources/js/public-map.js',
                'resources/css/cover-page.css',
                'resources/css/filament/app/theme.css'
            ],
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
    resolve: {
        alias: {
            vue: 'vue/dist/vue.esm-bundler.js',
        },
    },
});
