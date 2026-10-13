<?php

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
