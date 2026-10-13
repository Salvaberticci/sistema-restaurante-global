{{--
    Layout de las pantallas de operación fuera del panel (cocina, POS, reservas).

    Livewire 3 exige que un componente de página completa declare un layout, y
    busca `components.layouts.app` por convención. No hay un layout único del
    proyecto porque el panel de Filament tiene el suyo: por eso este es un
    esqueleto aparte y mínimo, no una envoltura del panel.

    Carga `app.css`, que es el Tailwind 4 del proyecto —el mismo que usa la
    landing— y no el tema de Filament. Un componente fuera del panel no puede
    depender de los estilos del panel: si mañana el tema de Filament cambia, la
    pantalla de cocina no debe romperse con él.
--}}
<!DOCTYPE html>
<html lang="es" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name') }}</title>

        {{-- En la cocina no hay color de sistema ni barra de direcciones con
             botones: es un navegador a pantalla completa detrás del mostrador. --}}
        <meta name="theme-color" content="#0a0a0a">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>

    <body class="min-h-screen bg-neutral-950 text-neutral-100 antialiased">
        {{ $slot }}

        @livewireScriptConfig
    </body>
</html>