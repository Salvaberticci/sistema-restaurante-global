import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

// En el servidor la app vive en un subdirectorio (`/sistema-restaurante-global`)
// y el docroot es la raíz del proyecto, no `public/`. Por eso `base` lleva el
// prefijo: el navegador pide `/sistema-restaurante-global/build/...`, que es
// donde el deploy copia el contenido de `public/build`.
// Ver AGENTS.md §4, trampas 5 y 6.
//
// En local, cuando se sirve con Apache de XAMPP, el proyecto cuelga de
// `htdocs/sistema-restaurante-global/public`, y el `public/build` queda un nivel
// más abajo. Por eso el build local necesita otro prefijo.
//   Apache (XAMPP)  → /sistema-restaurante-global/public/build/
//   php artisan serve → /build/
const SUBDIRECTORIO = process.env.APP_SUBDIRECTORIO ?? '/sistema-restaurante-global';
const LOCAL = process.env.APP_URL_LOCAL === '1';
const APACHE = process.env.APP_URL_APACHE === '1';

export default defineConfig({
    base: LOCAL
        ? '/build/'
        : (APACHE ? '/sistema-restaurante-global/public/build/' : `${SUBDIRECTORIO}/build/`),

    plugins: [
        // Tailwind 4 va como plugin de Vite, no por PostCSS.
        //
        // Sin esta línea el build termina sin error y genera un CSS con la capa
        // `theme` y la capa `components` —los `.srg-*` y el `--font-sans`— pero
        // **vacía la capa `utilities`**: no encuentra las clases que están en los
        // `.blade.php` y el sitio se sirve entero sin estilos. Es el peor tipo de
        // fallo, porque nada parece roto: el HTML llega, el CSS llega, el 200
        // llega. Solo falta la capa que le da forma a todo.
        tailwindcss(),
        laravel({
            // `admin` se registra en el panel con `->viteTheme(...)`, que es la
            // vía de Filament 3 para cargar un tema propio sin su compilador de
            // Tailwind 3 — este proyecto usa Tailwind 4.
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/admin/theme.css',
                'resources/js/admin.js',
            ],
            refresh: true,
        }),
    ],

    build: {
        // chart.js entra con el panel y pesa. Separarlo evita que el POS del
        // cajero, que no tiene gráficos, lo descargue.
        rollupOptions: {
            input: {
                app: 'resources/css/app.css',
                appJs: 'resources/js/app.js',
                adminTheme: 'resources/css/admin/theme.css',
                adminJs: 'resources/js/admin.js',
            },
        },
    },
});