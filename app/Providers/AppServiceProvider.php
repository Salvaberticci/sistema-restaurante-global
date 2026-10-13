<?php

namespace App\Providers;

use App\Domain\Auditoria\Contracts\RegistraAuditoria;
use App\Domain\Auditoria\Services\RegistraAuditoriaBd;
use App\Domain\Configuracion\Contracts\Parametros;
use App\Domain\Configuracion\Services\ParametrosBd;
use App\Domain\Facturacion\Contracts\FiscalDocumentDriver;
use App\Domain\Facturacion\Drivers\SimulatedFiscalDriver;
use App\Domain\Shared\Support\FechaFiscal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Illuminate\Support\Facades\URL;
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

        $this->forzarHttpsEnProduccion();
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
