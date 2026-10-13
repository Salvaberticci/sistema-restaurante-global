<?php

namespace App\Providers;

use App\Domain\Auditoria\Contracts\RegistraAuditoria;
use App\Domain\Auditoria\Services\RegistraAuditoriaBd;
use App\Domain\Configuracion\Contracts\Parametros;
use App\Domain\Configuracion\Services\ParametrosBd;
use App\Domain\Facturacion\Contracts\FiscalDocumentDriver;
use App\Domain\Facturacion\Drivers\SimulatedFiscalDriver;
use App\Domain\Shared\Support\FechaFiscal;
use Filament\Facades\Filament;
use Filament\Infolists\Infolist;
use Filament\Tables\Table;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Number;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Los drivers del emisor fiscal. La clave es el valor de FISCAL_DRIVER.
     * Agregar un driver nuevo es agregar una entrada acá y su clase: el código
     * de negocio no se toca. Ver specs/000-fundaciones/spec.md FR-06.
     *
     * @var array<string, class-string<FiscalDocumentDriver>>
     */
    private const DRIVERS_FISCALES = [
        'simulated' => SimulatedFiscalDriver::class,
    ];

    public function register(): void
    {
        $this->app->singleton(FiscalDocumentDriver::class, function ($app) {
            $driver = config('facturacion.driver');

            if (! isset(self::DRIVERS_FISCALES[$driver])) {
                throw new InvalidArgumentException(
                    "El driver fiscal [{$driver}] no existe. Disponibles: "
                    .implode(', ', array_keys(self::DRIVERS_FISCALES))
                    .'. Revisa FISCAL_DRIVER en el .env.'
                );
            }

            return $app->make(self::DRIVERS_FISCALES[$driver]);
        });

        $this->app->bind(FechaFiscal::class, fn () => FechaFiscal::hoy());

        $this->app->singleton(RegistraAuditoria::class, RegistraAuditoriaBd::class);
        $this->app->singleton(Parametros::class, ParametrosBd::class);
    }

    public function boot(): void
    {
        // AGENTS.md §4 trampa 1: con utf8mb4 un varchar(255) son 1020 bytes y el
        // límite del índice es 1000. Toda columna string sin largo explícito
        // baja a 191 (764 bytes). Sin esto, migraciones fallan en el servidor
        // con "Specified key was too long".
        SchemaBuilder::defaultStringLength(191);

        // Invariante I2: un acceso a atributo faltante en producción debe
        // revisar los logs, no devolver null y romper un cálculo en silencio.
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        $this->numerosEnFormatoAmericano();

        $this->loginEsElDeFilament();

        $this->forzarHttpsEnProduccion();
    }

    /**
     * A dónde va quien llega sin sesión: al login de Filament.
     *
     * El middleware `auth` redirige a la ruta llamada `login`. En este proyecto
     * esa ruta no existe —el login es el de Filament, que se llama
     * `filament.admin.auth.login`— así que sin esto abrir `/cocina` sin sesión
     * termina en una excepción en vez de en la pantalla de acceso.
     *
     * El panel se pide explícitamente y no con `getCurrentPanel()`, porque fuera
     * del panel —precisamente donde hace falta redirigir— `getLoginUrl()`
     * devuelve `null` y se vuelve a caer en la ruta inexistente.
     *
     * Es la forma más incómoda de fallar que hay: un error 500 en la puerta de
     * entrada, en la primera pantalla que el usuario toca.
     */
    private function loginEsElDeFilament(): void
    {
        Authenticate::redirectUsing(fn () => Filament::getPanel('admin')->getLoginUrl());
    }

    /**
     * Los importes se presentan como `$110.00`, no como `110,00 US$`.
     *
     * Con el locale `es`, `Number::currency` devuelve `110,00 US$`, y entonces las
     * tablas de Filament y las gráficas del panel dicen el mismo número de dos
     * formas distintas. Frente a un cliente eso no parece un detalle de formato:
     * parece que un número está mal.
     *
     * En Venezuela el dólar se escribe `$110.00`, así que el formato americano es
     * además el correcto. Y no basta con `Number::useLocale()`: Filament usa su
     * propio estático y, si está en `null`, cae en `config('app.locale')` — o sea,
     * que hay que tocar los dos.
     *
     * Las fechas no se ven afectadas porque el proyecto las pide siempre
     * explícitas (`d/m/Y`); el locale `es` sigue sirviendo para los textos.
     */
    private function numerosEnFormatoAmericano(): void
    {
        Number::useLocale('en');
        Table::$defaultNumberLocale = 'en';
        Infolist::$defaultNumberLocale = 'en';
    }

    /**
     * En el servidor el dominio sirve por HTTPS detrás de un proxy de LiteSpeed.
     * Sin esto Laravel cree que está en HTTP, marca la sesión como no segura y
     * el POS pierde el CSRF en cuanto se entra por el dominio.
     */
    private function forzarHttpsEnProduccion(): void
    {
        if (! $this->app->environment('production')) {
            return;
        }

        if ($this->app->runningInConsole()) {
            return;
        }

        URL::forceScheme('https');
    }
}
