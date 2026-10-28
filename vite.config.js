import {defineConfig} from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    css: {
        devSourcemap: true,
    },
    server: {
        // vendor/stats4sd/* are symlinks into packages/ (composer path repositories);
        // watching them makes the dev server run out of memory after a few minutes.
        watch: {
            ignored: ['**/vendor/**'],
        },
    },
    plugins: [
        tailwindcss(),
        laravel({
            input: [
                "resources/js/app.js",
                "resources/css/filament/app/theme.css",
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
            vue: "vue/dist/vue.esm-bundler.js",
        },
    },
});
