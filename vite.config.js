import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';

// Semua library di-bundle lokal, tanpa CDN (Blueprint §17).
export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                // Landing page produk di domain pusat (30-landing-page): bundel
                // terpisah tanpa jQuery/Livewire/service worker PWA.
                'resources/css/landing.css',
                'resources/js/landing.js',
            ],
            refresh: true,
            fonts: [
                bunny('Inter', {
                    weights: [400, 500, 600, 700, 800],
                }),
            ],
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
