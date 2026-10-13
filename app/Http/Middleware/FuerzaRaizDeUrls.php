<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registra las rutas de Livewire con el prefijo del subdirectorio.
 *
 * La aplicación vive en un subdirectorio (`/sistema-restaurante-global`) y
 * Livewire no lo tiene en cuenta: registra su POST en `/livewire/update` y lo
 * inyecta en la página como `data-update-uri`, que da por supuesto que la app
 * está en la raíz del dominio.
 *
 * `URL::forceRootUrl()` NO sirve para arreglarlo, y además rompe la app: al
 * forzar la raíz, Laravel reinterpreta la ruta de la petición en curso con el
 * prefijo y ninguna ruta deja de coincidir. La solución es registrar la ruta
 * donde toca.
 *
 * El síntoma de no hacerlo es muy confuso: la página carga con estilos y todo
 * se ve bien, pero cada formulario devuelve 404 y ningún botón responde. Parece
 * un problema de JavaScript y es de URLs.
 *
 * Va como middleware y no en `boot()` de un provider porque hay que registrar
 * la ruta DESPUÉS de que Livewire haya arrancado y puesto la suya, que es lo que
 * hace este middleware en cada petición.
 *
 * Ver AGENTS.md §4, trampa 7.
 */
class FuerzaRaizDeUrls
{
    public function handle(Request $request, Closure $next): Response
    {
        $prefijo = trim((string) config('app.subdirectorio'), '/');

        if ($prefijo !== '') {
            Livewire::setUpdateRoute(fn ($manejador) => Route::post($prefijo.'/livewire/update', $manejador)
                ->middleware('web')
                ->name('subdirectorio.livewire.update'));
        }

        return $next($request);
    }
}
