<?php

namespace App\Domain\Facturacion\Values;

use App\Domain\Shared\ValueObjects\Money;

/**
 * Acumulado del turno en curso. Lo que lee el Reporte X.
 */
final readonly class TurnoFiscal
{
    public function __construct(
        public Money $total,
        public int $cantidad,
    ) {}
}
