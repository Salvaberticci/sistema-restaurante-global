<?php

namespace App\Domain\Configuracion\Support;

use App\Domain\Shared\ValueObjects\Decimal;

/**
 * Convierte el valor crudo de un parámetro al tipo que pide quien lo usa. Un
 * parámetro viene de un input de Filament o de un seed, así que puede llegar
 * como string, como bool o como número según de dónde venga.
 */
final class Valores
{
    public static function entero(mixed $valor, int $porDefecto = 0): int
    {
        if (is_int($valor)) {
            return $valor;
        }

        if (is_bool($valor)) {
            return $valor ? 1 : 0;
        }

        if (is_numeric($valor)) {
            return (int) $valor;
        }

        return $porDefecto;
    }

    public static function decimal(mixed $valor): string
    {
        if (is_bool($valor) || $valor === null) {
            return '0';
        }

        return Decimal::deString((string) $valor)->__toString();
    }

    public static function booleano(mixed $valor, bool $porDefecto = false): bool
    {
        if (is_bool($valor)) {
            return $valor;
        }

        if (is_string($valor)) {
            return in_array(strtolower($valor), ['1', 'true', 'si', 'sí', 'yes'], true);
        }

        return is_numeric($valor) ? (int) $valor === 1 : $porDefecto;
    }
}
