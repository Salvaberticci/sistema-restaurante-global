<?php

namespace App\Domain\Facturacion\Contracts;

final readonly class EstadoDriver
{
    public function __construct(
        public readonly bool $enLinea,
        public readonly string $nombre,
        public readonly ?int $ultimoCorrelativo = null,
        public readonly ?string $ultimoNumeroFiscal = null,
        public readonly ?int $ultimoContadorZ = null,
        public readonly ?string $serial = null,
        public readonly ?string $detalle = null,
    ) {}

    /**
     * El cajero ve esto si la impresora está caída o el spooler no responde.
     * Nunca se muestra una pantalla en blanco: el sistema sigue vendiendo y
     * deja el documento en `queued` para reintentar.
     */
    public function hayFallo(): bool
    {
        return ! $this->enLinea;
    }
}
