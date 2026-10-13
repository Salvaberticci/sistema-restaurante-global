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

# Sin Set-StrictMode: en Windows PowerShell 5.1 aborta el script antes de la
# primera linea de salida cuando encuentra una variable no inicializada
# ($LASTEXITCODE, por ejemplo). El script es lineal y falla solo, asi que el
# modo estricto no aporta nada aqui y si estorba.

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

    # PowerShell convierte cualquier linea de stderr de un comando nativo en un
    # error y, con ErrorActionPreference Stop, aborta el script. npm escribe
    # avisos por stderr aunque el build salga bien (la version de Node
    # recomendada, por ejemplo). Aqui lo que decide si fallo es el codigo de
    # salida, no si escribio en stderr.
    $ErrorActionPreference = 'Continue'

    try {
        $salida = Invoke-Expression $Comando 2>&1
        $codigo = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = 'Stop'
    }

    if ($codigo -ne 0) {
        $salida | ForEach-Object { Write-Host "    $_" -ForegroundColor 'DarkRed' }
        Die "$Descripcion fallo (codigo $codigo)"
    }

    $ruidosas = @('Node.js', 'npm fund', 'Deprecation', 'deprecation', 'warning ')

    return $salida | Where-Object {
        $linea = $_.ToString()
        -not ($ruidosas | Where-Object { $linea -like "*$_*" })
    }
}

# ─────────────────────────────────────────────────────────────────────────────
# 0. Comprobaciones previas
# ─────────────────────────────────────────────────────────────────────────────

Say "`nSistema Restaurante Global — despliegue" 'Cyan'
Say "  -> https://$Servidor/$Subdirectorio`n" 'DarkGray'

if (-not (Test-Path $Llave)) { Die "No existe la llave SSH en $Llave" }

foreach ($comando in @('php', 'composer', 'node', 'tar')) {
    if (-not (Get-Command $comando -ErrorAction SilentlyContinue)) {
        Die ('Falta ' + $comando + ' en el PATH')
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
    # `public/build` SI se incluye aunque esté en .gitignore: se compiló hace un
    # momento y el servidor lo necesita para copiarlo a la raíz del docroot.
    # `.gitignore` es para git, no para el paquete de despliegue.
    Invocar "tar -czf `"$tarball`" --exclude=.git --exclude=vendor --exclude=node_modules --exclude=.env --exclude=public/hot --exclude=storage/logs --exclude=storage/framework/views --exclude=bootstrap/cache/*.php --exclude=*.tar.gz ." 'Creando tar'
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

    $ErrorActionPreference = 'Continue'
    try {
        $salida = & ssh @sshArgs "echo $codificado | base64 -d | bash" 2>&1
        $codigo = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = 'Stop'
    }

    $texto = $salida -join "`n"

    if ($codigo -ne 0 -or $texto -match 'REMOTE_ERROR') {
        $salida | Where-Object { $_ -notmatch 'Composer|Downloading|Generating|Package operations|Installing|Updating|Locking|Nothing to modify|Do not run|No security advisory' } |
            ForEach-Object { Write-Host "    $_" -ForegroundColor 'DarkGray' }
        Die "$Descripcion fallo en el servidor"
    }

    return $texto
}

# Here-string LITERAL a proposito: un `@"..."@` expande el `$(...)` de bash en
# PowerShell y manda el comando roto al servidor. Los valores llegan por
# marcadores, no por interpolacion.
$instalar = @'
set -e
cd "__RUTA__"

# El .env del servidor se preserva: el tar no lo trae, pero si algo lo creara
# desde cero hay que detenerse en vez de dejarlo sin APP_KEY.
if [ ! -f .env ]; then
  echo "REMOTE_ERROR: no existe .env en el servidor. Crealo antes de desplegar."
  exit 1
fi
cp .env .env.bak

# `tar -xzf` sobrescribe pero NO borra. Una migración que se eliminó del repo
# seguiría en el servidor y Laravel volvería a correrla, dejando tablas que ya no
# existen en el código. Por eso las migraciones se limpian antes de extraer.
rm -f database/migrations/*.php

tar -xzf __TAR__

# Git no versiona carpetas vacias, asi que storage/framework y storage/logs no
# llegan en el tar. Sin ellas composer falla con "Please provide a valid cache
# path". Se crean siempre antes de instalar.
mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/testing storage/framework/views storage/logs bootstrap/cache

# ── Docroot de la raíz ───────────────────────────────────────────────────────
# El docroot es la raíz del proyecto, no public/. Eso obliga a dos cosas:
#
#  1. Copiar los assets de public/ a la raíz. Sin esto el HTML se actualiza y el
#     estilo se queda en la versión anterior.
#  2. Poner el index.php y el .htaccess en la raíz. El .htaccess de public/ no
#     aplica desde ahí, y sin el de la raíz `vendor/`, `config/` y `.env`
#     quedan descargables por el navegador.
rm -rf build
cp -R public/build build

if [ -d public/js/filament ]; then
  mkdir -p js/filament css/filament
  cp -R public/js/filament/. js/filament/
  cp -R public/css/filament/. css/filament/
fi

cp deploy/root/.htaccess .htaccess
sed "s#__DIR__\.'/\.\./#__DIR__.'/#g" public/index.php > index.php
chmod 644 .htaccess index.php

export COMPOSER_HOME=__HOME__/.composer
php __HOME__/bin/composer install --no-dev --optimize-autoloader --no-interaction

php artisan migrate --force
__SEMILLA__

# route:cache rompe la raiz con 405 en este hosting. route:clear, siempre.
php artisan route:clear
php artisan config:clear
php artisan view:clear
php artisan config:cache
php artisan view:cache

rm -f bootstrap/cache/routes-*.php

chmod -R 775 storage bootstrap/cache
chmod 600 .env

echo "DEPLOY_OK"
'@

$homeUsuario = "/home/$Usuario"
$instalar = $instalar.Replace('__RUTA__', $RutaRemota)
$instalar = $instalar.Replace('__TAR__', "$homeUsuario/$nombreRemoto")
$instalar = $instalar.Replace('__HOME__', $homeUsuario)

# Sembrar es opcional porque `db:seed` sobre una base ya sembrada duplica
# catálogos y ensucia el demo. Solo entra en la primera carga o a petición
# expresa, y por eso no viene dentro de la ruta normal del deploy.
$semilla = if ($Semillar) { 'php artisan db:seed --force' } else { '' }
$instalar = $instalar.Replace('__SEMILLA__', $semilla)

$salidaInstalar = InvocarSsh -Script $instalar -Descripcion 'Extrayendo, instalando y migrando'

if ($salidaInstalar -notmatch 'DEPLOY_OK') { Die 'El script del servidor no confirmo el deploy' }

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
            $codigo = (Invoke-WebRequest -Uri "$base$Ruta" -Method Head -MaximumRedirection 0 -UseBasicParsing -ErrorAction Stop).StatusCode
        } catch {
            $codigo = [int]$_.Exception.Response.StatusCode
        }

        # '302|200' acepta cualquiera de los dos: sirve donde la respuesta
        # depende de si hay sesión iniciada.
        if ($Esperado -eq '302|200') {
            if ("$codigo" -in @('302', '200')) {
                SayOk "$Etiqueta ($codigo)"
                return $true
            }
        } elseif ("$codigo" -eq $Esperado) {
            SayOk "$Etiqueta ($codigo)"
            return $true
        }

        SayKo "$Etiqueta esperaba $Esperado, devolvio $codigo"
        return $false
    }

    $fallos = @()

    # La raíz es la landing pública y debe responder 200. El 405 es el síntoma
    # de `route:cache`, que está prohibido en este hosting (AGENTS.md §4.4).
    $fallos += ,(-not (Comprobar '/' '200' 'La landing publica responde'))

    $fallos += ,(-not (Comprobar '/admin/login' '200' 'El login del panel carga'))

    # Trampa 5: el navegador pide /build/, no /public/build/.
    $fallos += ,(-not (Comprobar '/build/manifest.json' '200' 'El manifest de Vite existe'))

    # En el servidor solo se registra el archivo que corresponde al modo actual:
    # Livewire cambia de nombre entre debug y producción y no publica los dos.
    $fallos += ,(-not (Comprobar '/livewire/livewire.min.js' '200' 'Livewire responde'))

    # El panel exige sesión: sin ella redirige al login. Con sesión devuelve la
    # página, así que se aceptan las dos.
    $fallos += ,(-not (Comprobar '/admin' '302|200' 'El panel responde'))

    # Los secretos nunca se sirven.
    $fallos += ,(-not (Comprobar '/.env' '403' '.env bloqueado'))
    $fallos += ,(-not (Comprobar '/vendor/autoload.php' '403' 'vendor bloqueado'))
    $fallos += ,(-not (Comprobar '/storage/logs/laravel.log' '403' 'logs bloqueados'))

    # Cabeceras de seguridad: el .htaccess de la raiz es el que aplica y el
    # tar puede pisarlo.
    try {
        $cabeceras = (Invoke-WebRequest -Uri "$base/admin/login" -UseBasicParsing).Headers
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