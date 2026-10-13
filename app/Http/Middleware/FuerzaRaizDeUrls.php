<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Livewire\Mechanisms\FrontendAssets\FrontendAssets;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registra las rutas de Livewire con el prefijo del subdirectorio.
 *
 * La aplicación vive en un subdirectorio (`/sistema-restaurante-global`) y
 * Livewire no lo tiene en cuenta. Registra dos rutas en la raíz del dominio:
 *
 *   GET  /livewire/livewire[.min].js   → el script que carga la página
 *   POST /livewire/update              → donde viaja cada interacción
 *
 * Con la aplicación en la raíz del dominio no hay problema. En un subdirectorio
 * las dos dan 404.
 *
 * `URL::forceRootUrl()` NO lo resuelve, y además rompe la aplicación: al forzar
 * la raíz, Laravel reinterpreta la ruta de la petición en curso con el prefijo y
 * ninguna deja de coincidir. La solución es registrar las rutas donde toca.
 *
 * El síntoma de no hacerlo es confuso: la página carga, se ven los estilos,
 * todo parece bien, y después ningún botón hace nada. Parece un problema de
 * JavaScript y es de URLs.
 *
 * Va como middleware y no en `boot()` de un provider porque hay que registrar
 * las rutas DESPUÉS de que Livewire haya arrancado y puesto las suyas, que es lo
 * que hace este middleware en cada petición.
 *
 * Ver AGENTS.md §4, trampa 7.
 */
class FuerzaRaizDeUrls
{
    public function handle(Request $request, Closure $next): Response
    {
        $prefijo = trim((string) config('app.subdirectorio'), '/');

        if ($prefijo !== '') {
            $this->registrarRutaDeUpdate($prefijo);
            $this->registrarRutaDelScript($prefijo);
        }

        return $next($request);
    }

    /**
     * El nombre del archivo cambia entre debug y producción. Se elige con el
     * mismo criterio que usa Livewire internamente, porque el servidor de
     * desarrollo y el de producción no pueden pedir archivos distintos.
     */
    private function registrarRutaDelScript(string $prefijo): void
    {
        $archivo = config('app.debug') ? 'livewire.js' : 'livewire.min.js';

        app(FrontendAssets::class)->setScriptRoute(
            fn ($manejador) => Route::get($prefijo.'/livewire/'.$archivo, $manejador)
                ->name('subdirectorio.livewire.asset')
        );
    }

    private function registrarRutaDeUpdate(string $prefijo): void
    {
        Livewire::setUpdateRoute(
            fn ($manejador) => Route::post($prefijo.'/livewire/update', $manejador)
                ->middleware('web')
                ->name('subdirectorio.livewire.update')
        );
    }
}
