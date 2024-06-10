import {defineConfig} from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import path from 'path';


export default defineConfig({
    plugins: [
        vue(),
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/admin.css',
                'resources/js/app.js',
                'resources/js/admin.js'
            ],
            refresh: true,
        }),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js/src'),
        },
    },
    css: {
        preprocessorOptions: {
            scss: {
                additionalData: `@import "@/variables/variables.scss";`
            }
        }
    },

});
