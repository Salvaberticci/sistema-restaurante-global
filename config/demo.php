<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Modo demostración
    |--------------------------------------------------------------------------
    |
    | Cuando está activo la interfaz muestra una cinta de "modo demo". Sirve
    | para que el cliente no confunda los datos con datos reales, y para que
    | quede claro qué partes de la información fiscal son valores de ejemplo
    | pendientes de confirmar con un contador.
    |
    */

    'modo' => (bool) env('DEMO_MODO', false),

    /*
    | La pantalla de cocina consulta al servidor cada KDS_POLL_MS milisegundos.
    | Un servidor compartido con 17 proyectos puede pedir menos. El mínimo
    | razonable es 1000: por debajo la pantalla consume más CPU de la que
    | ahorra.
    |
    | Para activar websockets en vez de polling se documenta en
    | specs/055-comandas-cocina/spec.md; el transporte está encapsulado.
    */

    'kds' => [
        'poll_ms' => max(1000, (int) env('KDS_POLL_MS', 2000)),
        'estacion_por_defecto' => env('KDS_ESTACION_DEFECTO', 'cocina_caliente'),
    ],

    /*
    | Cuenta de demo de respaldo. Si el cliente deja la base sin datos, el
    | comando `php artisan demo:reset` reconstruye todo.
    */

    'cuenta_por_defecto' => env('DEMO_CUENTA', 'admin@casa-mango.test'),

];
