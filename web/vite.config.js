import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.jsx'],
            refresh: true,
            // Fonts are self-hosted with Fontsource (imported in app.jsx), so the demo works offline.
        }),
        react(),
        tailwindcss(),
    ],
    // Vitest: unit tests for the plain JS helpers in resources/js/lib (npm test).
    test: {
        include: ['resources/js/**/*.test.js'],
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
