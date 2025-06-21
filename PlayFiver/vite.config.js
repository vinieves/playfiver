import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import vue from "@vitejs/plugin-vue";
import i18n from "laravel-vue-i18n/vite";
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: ["resources/css/app.css", "resources/js/app.js"],
            refresh: true,
        }),
        vue({
            template: {
                base: null,
                includeAbsolute: false,
            },
        }),
        i18n(),
    ],
    resolve: {
        alias: {
            '/public': path.resolve(__dirname, 'public')
        },
    },
    build: {
        chunkSizeWarningLimit: 2024,
    },
    optimizeDeps: {
        esbuildOptions: {
            // Node.js global to browser globalThis
            define: {
                global: "globalThis",
            },
        },
    },
});
