<?php

use App\Http\Middleware\FuerzaRaizDeUrls;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global y lo primero de todo: define dónde cuelga la aplicación, y sin
        // eso Livewire manda sus peticiones a la raíz del dominio y devuelve 404.
        $middleware->prepend(FuerzaRaizDeUrls::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
