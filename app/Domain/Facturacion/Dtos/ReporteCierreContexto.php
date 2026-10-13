<?php

namespace App\Domain\Facturacion\Dtos;

use App\Domain\Shared\Support\FechaFiscal;

/**
 * Contexto de un Reporte X o Z.
 *
 * La diferencia entre X y Z no es un parámetro: es un contrato distinto de
 * memoria fiscal. El X es una lectura y no la altera. El Z graba en la memoria
 * no volátil y resetea el turno. Por eso son dos métodos y no uno con un flag.
 */
final readonly class ReporteCierreContexto
{
    public function __construct(
        public string $tipo,             // 'X' | 'Z'
        public FechaFiscal $fechaFiscal,
        public ?int $cajeroId = null,
    ) {}
}
