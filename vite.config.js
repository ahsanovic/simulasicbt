import {
    defineConfig
} from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/quill-editor.js',
                'resources/js/exam-timer.js',
            ],
            refresh: true,
            fonts: [
                // No preload: the font CSS splits each weight into 4 unicode
                // ranges (latin, latin-ext, vietnamese, cyrillic) and preloading
                // forced all 16 files on every page. The browser now fetches
                // only the ranges the text uses (latin for Indonesian).
                bunny('Plus Jakarta Sans', {
                    weights: [400, 500, 600, 700],
                    preload: false,
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        cors: true,
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
