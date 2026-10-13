<?php

namespace App\Domain\Configuracion\Exceptions;

final class ParametroNoDefinidoException extends \RuntimeException
{
    public static function para(string $clave): self
    {
        return new self(
            "El parámetro [{$clave}] no está definido. Sin él el cálculo fiscal sería inventado: "
            .'defínelo desde Filament (Parámetros) o con `php artisan demo:reset`.'
        );
    }
}
