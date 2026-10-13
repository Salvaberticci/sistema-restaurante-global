<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | En el servidor la app vive en un subdirectorio, así que esta URL lo lleva
    | dentro: https://dominio/sistema-restaurante-global
    |
    | No es cosmético. Laravel genera las rutas a partir de aquí, y si le falta
    | el prefijo todas apuntan a la raíz del dominio y devuelven 404.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Asset URL
    |--------------------------------------------------------------------------
    |
    | Los assets se sirven desde el mismo prefijo que la aplicación. Sin esto,
    | el CSS y el JS de Vite y de Filament dan 404 y la página sale sin estilo.
    | Ver AGENTS.md §4, trampa 6.
    |
    */

    'asset_url' => env('ASSET_URL'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Invariante I4: la fecha fiscal manda. America/Caracas, no UTC. Cualquier
    | corte, cierre o emisión de documento usa esta zona, nunca la del servidor.
    |
    | Si esto queda en UTC, el "hoy" del sistema se corre un día al pasar las
    | 20:00 y los reportes del día salen vacíos sin que nada falle.
    |
    */

    'timezone' => env('APP_TIMEZONE', 'America/Caracas'),

    /*
    |--------------------------------------------------------------------------
    | Application Subdirectory
    |--------------------------------------------------------------------------
    |
    | Carpeta en la que cuelga la aplicación dentro del dominio.
    |
    |   servidor  → sistema-restaurante-global         (docroot = raíz del proyecto)
    |   Apache    → sistema-restaurante-global/public
    |
    | Lo usa el middleware FuerzaRaizDeUrls para registrar las rutas de Livewire
    | con el prefijo. Sin esto el panel carga pero ningún formulario responde,
    | porque los POST van a la raíz del dominio y vuelven 404.
    |
    | Vacío = la app está en la raíz del dominio, que es lo normal en cualquier
    | instalación que no sea esta.
    |
    */

    'subdirectorio' => env('APP_SUBDIRECTORIO', ''),

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    */

    'locale' => env('APP_LOCALE', 'es'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'es'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'es_VE'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', '')),
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
