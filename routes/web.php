<?php

use App\Livewire\Kitchen\Cocina;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas web
|--------------------------------------------------------------------------
|
| La landing pública vive en `/` y el panel de administración en `/admin`.
|
| Nota: las rutas de Livewire NO se registran acá. El middleware
| `FuerzaRaizDeUrls` las registra con el prefijo del subdirectorio, porque
| Livewire solo usa la suya — `/livewire/update` — si la aplicación está en la
| raíz del dominio. Ver AGENTS.md §4, trampa 7.
|
*/

Route::view('/', 'inicio')->name('inicio');

/*
| Cocina.
|
| Livewire y no Filament, por la invariante I7: la pantalla de cocina es
| operación, no backoffice. Va fuera del panel a propósito — con su propia
| pantalla completa y sin panel lateral — porque va colgada de un televisor
| detrás del mostrador.
|
| El acceso es por sesión, no por un token en la URL. Un enlace con token se
| puede compartir por WhatsApp y termina siendo una pantalla de cocina pública.
|
| Aquí solo va `auth`. El permiso NO va como middleware `can:` por dos motivos
| que ya costaron un error: el orden no es el que uno supone —`can:` puede
| correr antes que `auth`, y un visitante sin sesión recibe 403 en vez de ser
| enviado al login— y, más importante, las peticiones de Livewire (`wire:poll`,
| los botones) van a `/livewire/update` y no vuelven a pasar por el middleware
| de la ruta. El permiso se comprueba dentro del componente, donde sí se
| comprueba siempre. Ver `Cocina::mount()`.
*/
Route::middleware(['web', 'auth'])
    ->group(function () {
        Route::get('/cocina', Cocina::class)->name('cocina');
    });
