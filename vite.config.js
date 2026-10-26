import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.jsx'],
            refresh: true,
        }),
        react(),
    ],
    server: {
        // En Docker (compose.dev.yaml) Vite escucha en 0.0.0.0 y el HMR apunta al host.
        hmr: process.env.VITE_HMR_HOST ? { host: process.env.VITE_HMR_HOST } : undefined,
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    build: {
        chunkSizeWarningLimit: 1500,
    },
});
