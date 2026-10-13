# Servidor local de desarrollo para el panel.
#
# `php artisan serve` sin concurrencia se bloquea: el login de Filament va por
# Livewire, que encadena varias peticiones, y el servidor PHP integrado atiende
# una a la vez. Con una sola, la segunda espera a la primera y la página se
# queda cargando hasta que expira.
#
# PHP_CLI_SERVER_WORKERS abre varios procesos. En XAMPP la variable tiene que
# estar en el entorno antes de arrancar el proceso, no después.
#
# `--no-reload` no es opcional: sin él Laravel ignora PHP_CLI_SERVER_WORKERS
# (lo avisa por consola) y queda de un solo hilo, y además cada cambio de
# archivo reinicia el servidor y le corta la conexión al navegador.
#
# La contrapartida es que hay que reiniciar este script después de tocar una
# ruta o un provider. Cambiar una vista o un CSS no lo necesita.

$ErrorActionPreference = 'Stop'
$raiz = Split-Path -Parent $MyInvocation.MyCommand.Path

$env:PHP_CLI_SERVER_WORKERS = '6'

Write-Host 'Arrancando http://127.0.0.1:8000  (Ctrl+C para detener)' -ForegroundColor Cyan
Write-Host 'Workers: 6 · sin recarga automática' -ForegroundColor DarkGray
Write-Host '' -ForegroundColor Cyan

Set-Location $raiz
php artisan serve --host=127.0.0.1 --port=8000 --no-reload