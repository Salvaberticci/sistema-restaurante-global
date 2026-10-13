<?php

namespace App\Domain\Facturacion\Dtos;

use App\Domain\Shared\Support\FechaFiscal;
use App\Domain\Shared\ValueObjects\Money;

/**
 * Resultado de un Reporte X o Z.
 *
 * `afectoMemoriaFiscal` dice si el reporte alteró los contadores. El X nunca
 * los altera; el Z siempre. Si un driver devuelve `true` en un X, es un bug del
 * driver, y el test arquitectónico lo revisa.
 */
final readonly class ResultadoReporte
{
    public function __construct(
        public string $tipo,              // 'X' | 'Z'
        public FechaFiscal $fecha,
        public Money $total,
        public int $cantidadDocumentos,
        public ?int $contadorZ,
        public bool $afectoMemoriaFiscal,
        public string $serial,
        public array $crudo = [],
    ) {}

    /** @return array<string, mixed> */
    public function aArray(): array
    {
        return [
            'tipo' => $this->tipo,
            'fecha' => $this->fecha->iso(),
            'total' => $this->total->formateado(),
            'moneda' => $this->total->moneda()->value,
            'cantidad_documentos' => $this->cantidadDocumentos,
            'contador_z' => $this->contadorZ,
            'afecto_memoria_fiscal' => $this->afectoMemoriaFiscal,
            'serial' => $this->serial,
        ];
    }

    public function totalFormateado(): string
    {
        return $this->total->formateadoConSimbolo();
    }
}
