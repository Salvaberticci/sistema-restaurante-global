<?php

namespace App\Domain\Facturacion\Values;

use App\Domain\Shared\Support\FechaFiscal;
use App\Domain\Shared\ValueObjects\Moneda;

/**
 * Sesión abierta en el emisor fiscal. El POS la mantiene entre `abrir()`,
 * `registrarLineas()`, `registrarPagos()` y `cerrar()`.
 *
 * En una impresora fiscal real esto corresponde a un documento abierto en la
 * memoria fiscal. Si la sesión se abandona, el driver debe cerrarla para no
 * dejar un documento colgado.
 */
final readonly class SesionFiscal
{
    public function __construct(
        public string $token,
        public string $tipo,
        public string $serie,
        public FechaFiscal $fechaFiscal,
        public Moneda $moneda,
        public ?int $sucursalId = null,
        public ?int $ordenId = null,
    ) {}

    public function abierta(): bool
    {
        return $this->token !== '';
    }
}
