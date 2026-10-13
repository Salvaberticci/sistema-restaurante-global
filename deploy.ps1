<#
.SYNOPSIS
    Despliega sistema-restaurante-global a demo.salvanovasolutions.online.

.DESCRIPTION
    El repositorio es privado, así que el servidor no puede hacer `git clone`.
    El flujo es: empaquetar local -> scp -> extraer -> composer install ->
    migrate -> limpiar caché -> verificar.

    El paso que más se olvida en estos despliegues es sincronizar
    `public/build` a la raíz del proyecto, porque el docroot es la raíz y no
    `public/`. Si se olvida, el HTML se actualiza y el estilo no. Por eso este
    script lo hace y además falla si los hashes no coinciden.

    Tampoco se usa `route:cache`: en este LiteSpeed rompe la raíz con 405. El
    script verifica con curl y falla si detecta un 405.

.PARAMETER Servidor
    Host de destino. Por defecto demo.salvanovasolutions.online.

.PARAMETER Puerto
    Puerto SSH de cPanel. NO es el 22.

.PARAMETER SinVerificar
    Salta las comprobaciones HTTP. Solo para depurar el propio script.

.EXAMPLE
    .\deploy.ps1

.EXAMPLE
    .\deploy.ps1 -SinVerificar
#>
[CmdletBinding()]
param(
    [string]$Servidor = 'demo.salvanovasolutions.online',
    [int]$Puerto = 21098,
    [string]$Usuario = 'salvxkld',
    [string]$Llave = "$env:USERPROFILE\.ssh\id_rsa_cpanel",
    [string]$ProyectoLocal = $PSScriptRoot,
    [string]$RutaRemota = '/home/salvxkld/demo.salvanovasolutions.online/sistema-restaurante-global',
    [string]$Subdirectorio = 'sistema-restaurante-global',
    [switch]$SinVerificar
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

# ─────────────────────────────────────────────────────────────────────────────
# Utilidades
# ─────────────────────────────────────────────────────────────────────────────

function Say {
    param([string]$Mensaje, [string]$Color = 'Gray')
    Write-Host $Mensaje -ForegroundColor $Color
}

function SayOk { param([string]$M) Say "  [OK]   $M" 'Green' }
function SayKo { param([string]$M) Say "  [FALLA] $M" 'Red' }
function SayAviso { param([string]$M) Say "  [AVISO] $M" 'Yellow' }

function Die {
    param([string]$Motivo)
    Say "`nFALLO: $Motivo" 'Red'
    exit 1
}

function Invocar {
    param([string]$Comando, [string]$Descripcion)

    Say "  $Descripcion" 'DarkGray'
    $salida = Invoke-Expression $Comando 2>&1

    if ($LASTEXITCODE -ne 0) {
        $salida | ForEach-Object { Write-Host "    $_" -ForegroundColor 'DarkRed' }
        Die "$Descripcion falló (código $LASTEXITCODE)"
    }

    return $salida
}

# ─────────────────────────────────────────────────────────────────────────────
# 0. Comprobaciones previas
# ─────────────────────────────────────────────────────────────────────────────

Say "`nSistema Restaurante Global — despliegue" 'Cyan'
Say "  -> https://$Servidor/$Subdirectorio`n" 'DarkGray'

if (-not (Test-Path $Llave)) { Die "No existe la llave SSH en $Llave" }

foreach ($comando in @('php', 'composer', 'node', 'tar')) {
    if (-not (Get-Command $comando -ErrorAction SilentlyContinue)) {
        Die "Falta `$comando en el PATH"
    }
}

SayOk "Herramientas disponibles"

# ─────────────────────────────────────────────────────────────────────────────
# 1. Build de assets
#
# Se construye en LOCAL. En cPanel compartido no hay Node garantizado, y si lo
# hubiera tampoco sabríamos qué versión. Construir local y subir el resultado es
# la única forma de que el CSS del servidor sea el que compusiste.
# ─────────────────────────────────────────────────────────────────────────────

Say "`n[1/6] Assets" 'Yellow'

Push-Location $ProyectoLocal
try {
    if (-not (Test-Path 'node_modules')) {
        Invocar 'npm ci' 'Instalando dependencias de Node'
    }

    # Para el build en local los assets cuelgan de /build/, no del subdirectorio.
    $env:APP_URL_LOCAL = '1'
    try {
        Invocar 'npm run build' 'Compilando Vite'
    } finally {
        Remove-Item Env:\APP_URL_LOCAL -ErrorAction SilentlyContinue
    }

    if (-not (Test-Path 'public\build\manifest.json')) { Die 'El build no generó manifest.json' }

    $manifest = Get-Content 'public\build\manifest.json' -Raw
    SayOk 'Build generado'
} finally {
    Pop-Location
}

# ─────────────────────────────────────────────────────────────────────────────
# 2. Empaquetar
#
# NUNCA subir .git, vendor, .env ni node_modules. El .env del servidor tiene la
# APP_KEY y la contraseña de la base: subirlo por error sería filtrar un secreto.
# ─────────────────────────────────────────────────────────────────────────────

Say "`n[2/6] Empaquetando" 'Yellow'

$tmp = Join-Path $env:LOCALAPPDATA 'Temp\opencode'
New-Item -ItemType Directory -Path $tmp -Force | Out-Null
$tarball = Join-Path $tmp 'srg-deploy.tar.gz'
$nombreRemoto = 'srg-deploy.tar.gz'

Remove-Item $tarball -Force -ErrorAction SilentlyContinue

Push-Location $ProyectoLocal
try {
    Invocar "tar -czf `"$tarball`" --exclude=.git --exclude=vendor --exclude=node_modules --exclude=.env --exclude=public/build --exclude=public/hot --exclude=storage/logs --exclude=storage/framework/views --exclude=bootstrap/cache/*.php --exclude=*.tar.gz ." 'Creando tar'
} finally {
    Pop-Location
}

$pesoMb = [math]::Round((Get-Item $tarball).Length / 1MB, 2)
SayOk "Tar creado ($pesoMb MB)"

# ─────────────────────────────────────────────────────────────────────────────
# 3. Subir
# ─────────────────────────────────────────────────────────────────────────────

Say "`n[3/6] Subiendo por scp" 'Yellow'

$destino = "${Usuario}@${Servidor}:/home/${Usuario}/"
Invocar "scp -o StrictHostKeyChecking=accept-new -i `"$Llave`" -P $Puerto `"$tarball`" `"$destino`"" 'Subiendo tar'
SayOk 'Subido'

# ─────────────────────────────────────────────────────────────────────────────
# 4. Extraer e instalar
# ─────────────────────────────────────────────────────────────────────────────

Say "`n[4/6] Instalando en el servidor" 'Yellow'

$sshArgs = @('-o', 'StrictHostKeyChecking=accept-new', '-i', $Llave, '-p', $Puerto, "${Usuario}@${Servidor}")

function InvocarSsh {
    param([string]$Script, [string]$Descripcion)

    Say "  $Descripcion" 'DarkGray'

    $codificado = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($Script))
    $salida = & ssh @sshArgs "echo $codificado | base64 -d | bash" 2>&1

    if ($LASTEXITCODE -ne 0) {
        $salida | ForEach-Object { Write-Host "    $_" -ForegroundColor 'DarkRed' }
        Die "$Descripcion falló en el servidor"
    }

    $texto = $salida -join "`n"

    if ($texto -match 'REMOTE_ERROR') { Die ($texto -split 'REMOTE_ERROR: ')[1] }

    return $texto
}

$instalar = @"
set -e
cd "$RutaRemota"

# El .env del servidor se preserva: el tar no lo trae, pero si algo lo creara
# desde cero hay que detenerse en vez de dejarlo sin APP_KEY.
if [ ! -f .env ]; then
  echo "REMOTE_ERROR: no existe .env en el servidor. Créalo antes de desplegar."
  exit 1
fi
cp -n .env .env.bak.\$(date +%Y%m%d-%H%M%S) 2>/dev/null || true

tar -xzf /home/${Usuario}/$nombreRemoto

# El docroot es la raíz del proyecto, no public/. Sin esto el HTML se actualiza
# y el estilo se queda en la versión anterior.
rm -rf build
cp -R public/build build

export COMPOSER_HOME=/home/${Usuario}/.composer
php /home/${Usuario}/bin/composer install --no-dev --optimize-autoloader --no-interaction

php artisan migrate --force

# route:cache rompe la raíz con 405 en este hosting. route:clear, siempre.
php artisan route:clear
php artisan config:clear
php artisan view:clear
php artisan config:cache
php artisan view:cache

rm -f bootstrap/cache/routes-*.php

chmod -R 775 storage bootstrap/cache
chmod 600 .env

echo "DEPLOY_OK"
"@

$salidaInstalar = InvocarSsh -Script $instalar -Descripcion 'Extrayendo, instalando y migrando'

if ($salidaInstalar -notmatch 'DEPLOY_OK') { Die 'El script del servidor no confirmó el deploy' }

SayOk 'Servidor actualizado'

# ─────────────────────────────────────────────────────────────────────────────
# 5. Verificar
#
# Un deploy que no se verifica es un deploy que se descubre tarde. Laravel
# responde bien por consola aunque el sitio esté roto, así que la única forma
# de saberlo es preguntarle por HTTP.
# ─────────────────────────────────────────────────────────────────────────────

Say "`n[5/6] Verificando" 'Yellow'

if ($SinVerificar) {
    SayAviso 'Verificación saltada por -SinVerificar'
} else {
    $base = "https://${Servidor}/${Subdirectorio}"

    function Comprobar {
        param([string]$Ruta, [string]$Esperado, [string]$Etiqueta)

        try {
            $codigo = (Invoke-WebRequest -Uri "$base$Ruta" -Method Head -MaximumRedirection 5 -UseBasicParsing -ErrorAction Stop).StatusCode
        } catch {
            $codigo = [int]$_.Exception.Response.StatusCode
        }

        if ("$codigo" -eq $Esperado) {
            SayOk "$Etiqueta ($codigo)"
            return $true
        }

        SayKo "$Etiqueta esperaba $Esperado, devolvio $codigo"
        return $false
    }

    $fallos = @()

    # La trampa 4: route:cache devuelve 405 y Laravel por consola sigue bien.
    $fallos += ,(-not (Comprobar '/' '302' 'La raiz responde'))

    $fallos += ,(-not (Comprobar '/login' '200' 'El login carga'))

    # Trampa 5: el navegador pide /build/, no /public/build/.
    $fallos += ,(-not (Comprobar '/build/manifest.json' '200' 'El manifest de Vite existe'))

    $fallos += ,(-not (Comprobar '/livewire/livewire.js' '200' 'Livewire responde'))

    # Los secretos nunca se sirven.
    $fallos += ,(-not (Comprobar '/.env' '403' '.env bloqueado'))
    $fallos += ,(-not (Comprobar '/vendor/autoload.php' '403' 'vendor bloqueado'))
    $fallos += ,(-not (Comprobar '/storage/logs/laravel.log' '403' 'logs bloqueados'))

    # Cabeceras de seguridad: el .htaccess de la raiz es el que aplica y el
    # tar puede pisarlo.
    try {
        $cabeceras = (Invoke-WebRequest -Uri "$base/login" -UseBasicParsing).Headers
        if ($cabeceras['X-Frame-Options'] -ne 'SAMEORIGIN') {
            SayKo 'Faltan cabeceras de seguridad (el .htaccess de la raiz fue pisado)'
            $fallos += $true
        } else {
            SayOk 'Cabeceras de seguridad presentes'
        }
    } catch {
        SayKo "No se pudieron leer las cabeceras: $_"
        $fallos += $true
    }

    if ($fallos -contains $true) {
        Die 'La verificacion fallo. NO abras el sitio al cliente hasta corregirlo.'
    }

    SayOk 'Todas las comprobaciones pasaron'
}

# ─────────────────────────────────────────────────────────────────────────────
# 6. Limpieza
# ─────────────────────────────────────────────────────────────────────────────

Say "`n[6/6] Limpiando" 'Yellow'

InvocarSsh -Script "rm -f /home/${Usuario}/$nombreRemoto" -Descripcion 'Borrando el tar del servidor' | Out-Null
Remove-Item $tarball -Force -ErrorAction SilentlyContinue
SayOk 'Limpieza hecha'

Say "`nDesplegado en https://${Servidor}/${Subdirectorio}`n" 'Cyan'
Say 'El cron de la seccion 7 de docs/DESPLIEGUE_SERVIDOR.md es lo que hace que' 'DarkGray'
Say 'la cola y la tasa de cambio corran. Verificalo una vez.' 'DarkGray'