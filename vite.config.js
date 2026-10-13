import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

// En el servidor la app vive en un subdirectorio (`/sistema-restaurante-global`)
// y el docroot es la raíz del proyecto, no `public/`. Por eso `base` lleva el
// prefijo: el navegador pide `/sistema-restaurante-global/build/...`, que es
// donde el deploy copia el contenido de `public/build`.
// Ver AGENTS.md §4, trampas 5 y 6.
const SUBDIRECTORIO = process.env.APP_SUBDIRECTORIO ?? '/sistema-restaurante-global';

export default defineConfig({
    base: process.env.APP_URL_LOCAL === '1'
        ? '/build/'
        : `${SUBDIRECTORIO}/build/`,

    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
});