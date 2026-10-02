import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
// Tailwind 4 trae su propio plugin de Vite: reemplaza a PostCSS + autoprefixer,
// que ya no hacen falta (el prefijado va incluido).
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/sitio.css',
                'resources/js/app.js',
                'resources/js/tour.js',
                'resources/js/panel.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        hmr: {
            host: 'localhost',
        },
    },
});
