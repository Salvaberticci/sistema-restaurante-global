<?php

namespace App\Domain\Shared\ValueObjects;

/**
 * Moneda en ISO-4217. Las únicas dos del sistema: el precio se fija en USD y el
 * cobro se hace en bolívares o divisas según el método de pago.
 */
enum Moneda: string
{
    case Dolar = 'USD';
    case Bolivar = 'VES';

    public function simbolo(): string
    {
        return match ($this) {
            self::Dolar => '$',
            self::Bolivar => 'Bs.',
        };
    }

    public function decimales(): int
    {
        return 2;
    }

    /**
     * Unidad menor. USD cotiza a 2 decimales; el bolívar también se presenta a
     * 2 aunque la tasa tenga más, porque es como lo ve el cliente. El importe
     * exacto vive en la columna decimal(18,4).
     */
    public function factor(): int
    {
        return 10 ** $this->decimales();
    }
}
