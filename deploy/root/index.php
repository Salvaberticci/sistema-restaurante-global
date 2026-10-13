<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Front controller de la RAÍZ del proyecto
|--------------------------------------------------------------------------
|
| Copia parcheada de `public/index.php`.
|
| En un Laravel normal este archivo vive en `public/` y sube un nivel para
| encontrar `vendor/` y `storage/`. En este hosting el docroot es la raíz del
| proyecto, así que `vendor/` está al lado y NO hay que subir ningún nivel.
|
| El `deploy.ps1` genera este archivo aplicando un `sed` sobre
| `public/index.php`. Está versionado aquí solo para poder leerlo y revisarlo;
| para cambiar el arranque se edita `public/index.php`.
*/

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
