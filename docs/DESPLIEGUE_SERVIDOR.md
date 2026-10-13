# Despliegue — `sistema-restaurante-global` en `demo.salvanovasolutions.online`

Ambiente de **pruebas** (demo comercial). Aquí está todo lo que hace falta para
desplegar y dar mantenimiento.

> **Comparte servidor con 17 proyectos más.** No se toca nada fuera de
> `sistema-restaurante-global/`. Ver las trampas de la §4 antes de desplegar.

---

## 1. Resumen rápido

| Dato | Valor |
|---|---|
| **URL** | <https://demo.salvanovasolutions.online/sistema-restaurante-global> |
| **Ruta en el servidor** | `/home/salvxkld/demo.salvanovasolutions.online/sistema-restaurante-global/` |
| **Servidor** | `premium294.web-hosting.com` — LiteSpeed (cPanel de HostGator) |
| **PHP** | 8.2.34 |
| **Laravel** | 12.69.3 |
| **MySQL** | MariaDB 11.4 |
| **Composer** | `/home/salvxkld/bin/composer` |
| **Usuario SSH** | `salvxkld` · **puerto 21098** · llave `id_rsa_cpanel` |
| **PHP (CLI)** | `/usr/local/bin/php` (8.2.34) |
| **Base de datos** | `salvxkld_srg` (usuario `salvxkld_salvatore`, compartido) |
| **Repo** | `git@github.com:Salvaberticci/sistema-restaurante-global.git` (**privado**) |

### Cuentas demo

| Rol | Correo | Contraseña |
|---|---|---|
| Administrador | `admin@casa-mango.test` | `demo1234` |
| Gerente | `gerente@casa-mango.test` | `demo1234` |
| Cajero | `cajero@casa-mango.test` | `demo1234` |
| Mesero | `mesero@casa-mango.test` | `demo1234` |
| Cocina | `cocina@casa-mango.test` | `demo1234` |

---

## 2. Conectarse por SSH

```powershell
ssh -i "$env:USERPROFILE\.ssh\id_rsa_cpanel" -p 21098 salvxkld@demo.salvanovasolutions.online

cd /home/salvxkld/demo.salvanovasolutions.online/sistema-restaurante-global
tail -f storage/logs/laravel.log
```

El puerto **no es el 22**. Es el SSH de cPanel.

---

## 3. Desplegar

El repo es **privado**, así que el servidor **no puede hacer `git clone`**. El flujo
probado es empaquetar local → subir por `scp` → extraer → instalar.

En Windows, de una vez:

```powershell
.\deploy.ps1
```

Y si hay que hacerlo a mano:

```bash
# 1. En Windows: empaquetar (excluye .git, vendor, .env, node_modules)
# 2. scp al servidor
# 3. En el servidor:
cd /home/salvxkld/demo.salvanovasolutions.online/sistema-restaurante-global
tar -xzf /home/salvxkld/srg-deploy.tar.gz
mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/testing storage/framework/views storage/logs bootstrap/cache   # §4.10
cp -R public/build/. build/             # §4.5
cp -R public/js/filament/. js/filament/ # §4.8
cp -R public/css/filament/. css/filament/
cp deploy/root/.htaccess .htaccess       # §4.9
sed "s#__DIR__\.'/\.\./#__DIR__.'/#g" public/index.php > index.php
export COMPOSER_HOME=/home/salvxkld/.composer
php /home/salvxkld/bin/composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan route:clear                 # <- NUNCA route:cache (§4.4)
php artisan config:cache
php artisan view:cache
chmod -R 775 storage bootstrap/cache && chmod 600 .env
```

---

## 4. ⚠️ Las trampas de este servidor

Las primeras cinco ya costaron tiempo en `isp-core`. Las cinco últimas
aparecieron desplegando este proyecto. Todas están automatizadas en
`deploy.ps1`, que **falla** si alguna se cumple.

### 4.1 `Specified key was too long` (error 1071)

`varchar(255)` con `utf8mb4` son **1020 bytes** y el límite del índice es **1000**.

**Prevenido** con `SchemaBuilder::defaultStringLength(191)` en
`AppServiceProvider::boot()`. Hay un test que lo verifica. **No lo borres.**

### 4.2 ENUM modificados con SQL crudo rompen SQLite

Por eso este proyecto **no usa SQLite en ningún lado**, ni local ni en pruebas.
`phpunit.xml` apunta a MySQL a propósito. Si algún día se necesita SQLite,
primero hay que revisar todas las migraciones.

### 4.3 `optimize:clear` se traba con la caché sin tabla

Con `CACHE_STORE=database` y la tabla `cache` sin crear, limpiar caché hace
deadlock. **Siempre `migrate` antes de limpiar caché.**

### 4.4 🔴 `route:cache` ROMPE EL SITIO

Con `route:cache`, `GET /` responde **405 Method Not Allowed** y el panel queda
inaccesible.

| Comando | `GET /` |
|---|---|
| `route:clear` | 302 ✅ |
| `route:cache` | **405** ❌ |

Lo peor: **Laravel responde bien por consola**. El error solo se ve entrando por
el navegador. Por eso `deploy.ps1` verifica con `curl` y **falla si `/` da 405**.

### 4.5 Los assets no se ven aunque el HTML sí

El docroot es la **raíz del proyecto**, no `public/` (§5). El `tar` sube
`public/build/`, pero el navegador pide `/build/`. Sin copiar, el HTML se
actualiza y **el estilo se queda en la versión anterior**.

```bash
cp -R public/build/. build/
```

`deploy.ps1` lo hace y además **falla si los hashes no coinciden**.

### 4.8 Filament publica sus assets en `public/` y el docroot sirve la raíz

Sin copiar, el panel de Filament entra **sin estilos y sin JavaScript**: la
página carga pero ningún botón responde.

```bash
cp -R public/js/filament/. js/filament/
cp -R public/css/filament/. css/filament/
```

### 4.9 🔴 Sin `.htaccess` ni `index.php` en la raíz, el código queda expuesto

Esta es la más grave. Con el docroot apuntando a la raíz del proyecto,
`vendor/autoload.php`, `config/database.php`, `storage/logs/laravel.log`,
`composer.json` y `.env` **se descargan con un 200**. Cualquiera que sepa la
URL puede leer la contraseña de la base de datos.

Lo que pasó en el primer despliegue de este proyecto: todo lo demás funcionaba
y estos archivos daban 200. El `.htaccess` de `public/` no protege nada cuando el
docroot es la raíz.

```bash
cp deploy/root/.htaccess .htaccess
sed "s#__DIR__\.'/\.\./#__DIR__.'/#g" public/index.php > index.php
```

**Verificalo siempre.** Si alguno de estos deja de dar 403, se filtró un
secreto:

```bash
curl -sI https://demo.salvanovasolutions.online/sistema-restaurante-global/.env | head -1   # 403
curl -sI https://demo.salvanovasolutions.online/sistema-restaurante-global/config/database.php | head -1   # 403
curl -sI https://demo.salvanovasolutions.online/sistema-restaurante-global/storage/logs/laravel.log | head -1   # 403
```

### 4.10 Faltan las carpetas de `storage/`

Git no versiona carpetas vacías, así que `storage/framework/*` y `storage/logs`
no llegan en el tar. Composer aborta antes de empezar:

```
Please provide a valid cache path.
```

```bash
mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions \
         storage/framework/testing storage/framework/views storage/logs bootstrap/cache
```

---

## 5. Layout: por qué el docroot es la raíz

En cPanel compartido el docroot se cambia desde el panel, no por SSH. Como la app
vive **dentro de una carpeta** de un docroot compartido por 17 proyectos, se opted
por sirve la raíz del proyecto directamente:

```
/home/salvxkld/demo.salvanovasolutions.online/sistema-restaurante-global/
├── index.php          <- front controller PARCHEADO
├── .htaccess          <- el que aplica es este, no el de public/
├── build/             <- copia de public/build
├── assets/            <- copia de public/assets
├── app/ bootstrap/ config/ database/ resources/ routes/ storage/ vendor/
└── artisan  .env  composer.json
```

**Consecuencias que hay que tener presentes:**

1. **`index.php` está parcheado.** En el repo apunta a
   `__DIR__.'/../vendor/autoload.php'`, pero en la raíz necesita
   `__DIR__.'/vendor/autoload.php'`. Si alguna vez se copia el `public/index.php`
   original al servidor, hay que aplicar el parche:
   ```bash
   sed -i "s#__DIR__\.'/\.\./#__DIR__.'/#g" index.php
   ```

2. **El `.htaccess` de la raíz es el que aplica.** Si el `tar -xzf` lo pisa,
   desaparecen las cabeceras de seguridad. `deploy.ps1` verifica con `curl`.

3. **`APP_URL` y `ASSET_URL` llevan el prefijo** `/sistema-restaurante-global`.
   Sin eso los assets y los formularios apuntan a la raíz del dominio.

4. **`SESSION_PATH=/sistema-restaurante-global`.** Sin el path, la cookie se
   guarda en `/` y el CSRF de Livewire se rompe: el POS deja de funcionar.

5. **Los secretos están negados en `.htaccess`.** Debe seguir dando 403:
   `/.env`, `/vendor/autoload.php`, `/storage/logs/laravel.log`,
   `/config/database.php`, `/composer.json`, `/artisan`.

---

## 6. `.env` del servidor

Nunca se versiona. Contiene `APP_KEY` y la contraseña de la base.

```bash
grep -E '^(APP_URL|ASSET_URL|SESSION_PATH|DB_DATABASE)=' .env
```

Si hay que ver la contraseña de la BD:

```bash
grep DB_PASSWORD .env
```

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://demo.salvanovasolutions.online/sistema-restaurante-global
ASSET_URL=https://demo.salvanovasolutions.online/sistema-restaurante-global
SESSION_PATH=/sistema-restaurante-global
SESSION_DOMAIN=null
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=salvxkld_restaurante
DB_USERNAME=salvxkld_salvatore
FISCAL_DRIVER=simulated
KDS_POLL_MS=2000
```

---

## 7. Cron (obligatorio para la cola y la tasa)

cPanel compartido **no permite `queue:work --daemon`**. La cola la corre el
scheduler:

```
* * * * * cd /home/salvxkld/demo.salvanovasolutions.online/sistema-restaurante-global && /opt/alt/php82/php artisan schedule:run >> /dev/null 2>&1
```

Sin cron: la cola acumula y el POS sigue funcionando en modo `sync` (más lento,
pero no se rompe).

---

## 8. Verificar el deploy

```bash
# 1. La raíz responde bien y NUNCA 405
curl -sI https://demo.salvanovasolutions.online/sistema-restaurante-global/ | head -1     # 302

# 2. El login carga y trae el CSRF
curl -sI https://demo.salvanovasolutions.online/sistema-restaurante-global/login | head -1  # 200
curl -s  https://demo.salvanovasolutions.online/sistema-restaurante-global/login | grep -c 'name="_token"'  # 1

# 3. Los assets existen CON el prefijo
curl -sI https://demo.salvanovasolutions.online/sistema-restaurante-global/build/manifest.json | head -1  # 200

# 4. Livewire responde
curl -sI https://demo.salvanovasolutions.online/sistema-restaurante-global/livewire/livewire.js | head -1  # 200

# 5. Los secretos siguen negados (403 en TODOS)
curl -sI https://demo.salvanovasolutions.online/sistema-restaurante-global/.env | head -1                     # 403
curl -sI https://demo.salvanovasolutions.online/sistema-restaurante-global/vendor/autoload.php | head -1       # 403
curl -sI https://demo.salvanovasolutions.online/sistema-restaurante-global/storage/logs/laravel.log | head -1  # 403

# 6. Cabeceras de seguridad
curl -sI https://demo.salvanovasolutions.online/sistema-restaurante-global/login | grep -i x-frame  # SAMEORIGIN

# 7. Los proyectos vecinos NO se tocaron
curl -sI https://demo.salvanovasolutions.online/isp-core/login | head -1
```

---

## 9. Problemas frecuentes

| Síntoma | Causa | Qué hacer |
|---|---|---|
| **`GET /` da 405** | `route:cache` activo | `php artisan route:clear` |
| Pantalla sin estilos | `public/build` no se copió a `build/` | `cp -R public/build/. build/` |
| Login entra y rebota | `SESSION_PATH` mal | Poner `/sistema-restaurante-global` y limpiar caché |
| `Access denied … database` (1044) | Permisos de cPanel sin aplicar | Esperar unos segundos y reintentar |
| `Access denied … password` (1045) | Contraseña mal en `.env` | Verificarla por SSH |
| `Specified key was too long` (1071) | Se quitó `defaultStringLength(191)` | Restaurarlo; hay un test que lo cubre |
| `Table 'cache' doesn't exist` | `optimize:clear` sin migrar | Correr `migrate` primero |
| Pantalla en blanco | Error de PHP | `tail -50 storage/logs/laravel.log` |
| Los documentos se quedan en cola | El cron no corre | Verificar §7 |

---

## 10. Empezar de cero

1. Crear la base `salvxkld_restaurante` en cPanel y **agregarla al usuario
   `salvxkld_salvatore`** con todos los permisos. **No** se le quitan permisos a
   ese usuario: lo usan 17 proyectos más.
2. Subir el `.env` con la `APP_KEY` generada localmente.
3. `php artisan migrate --force && php artisan demo:reset`
4. Agregar el cron de §7.
5. Verificar con §8.

---

## 11. Verificación actual

**Pendiente del primer despliegue.** Esta sección se completa después del primer
deploy con el commit, la fecha y el resultado de cada punto de §8.