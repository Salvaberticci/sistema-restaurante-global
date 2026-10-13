<?php

namespace App\Domain\Facturacion\Dtos;

use App\Domain\Shared\Support\FechaFiscal;
use App\Domain\Shared\ValueObjects\Decimal;
use App\Domain\Shared\ValueObjects\Moneda;
use App\Domain\Shared\ValueObjects\Money;

/**
 * Contexto de un documento fiscal a emitir: qué es, cuándo, en qué moneda, con
 * qué tasa y qué lo origina.
 *
 * `idempotencyKey` es la clave de I5. El servicio que arma el documento lo
 * calcula de forma determinista a partir de la orden y el tipo, así un reintento
 * de cola con el mismo documento nunca emite dos veces.
 */
final readonly class DocumentoFiscalContexto
{
    public function __construct(
        public string $tipo,                    // 'factura' | 'nota_credito' | 'nota_debito'
        public string $idempotencyKey,
        public FechaFiscal $fechaFiscal,
        public Moneda $moneda,
        public LineasDocumento $lineas,
        public PagosDocumento $pagos,
        public ?int $sucursalId = null,
        public ?int $ordenId = null,
        public ?int $clienteId = null,
        /** Tasa del día, congelada en el documento. Null si el documento es en USD. */
        public ?Decimal $tasaCambio = null,
        /** Snapshot del cliente: el nombre puede cambiar después. */
        public ?array $clienteSnapshot = null,
        /** Payload crudo que se manda al emisor. Se guarda para auditoría. */
        public array $payload = [],
    ) {}

    public function total(): Money
    {
        return $this->pagos->total();
    }

    public function esNota(): bool
    {
        return $this->tipo !== 'factura';
    }

    /** @return array<string, mixed> */
    public function aArray(): array
    {
        return [
            'tipo' => $this->tipo,
            'idempotency_key' => $this->idempotencyKey,
            'fecha_fiscal' => $this->fechaFiscal->iso(),
            'moneda' => $this->moneda->value,
            'sucursal_id' => $this->sucursalId,
            'orden_id' => $this->ordenId,
            'cliente' => $this->clienteSnapshot,
            'tasa_cambio' => $this->tasaCambio?->__toString(),
            'total' => $this->total()->formateado(),
            'lineas' => $this->lineas->aArray(),
            'pagos' => $this->pagos->aArray(),
            'payload' => $this->payload,
        ];
    }
}
