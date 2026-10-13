<?php

namespace App\Domain\Facturacion\Dtos;

use App\Domain\Shared\Support\FechaFiscal;
use App\Domain\Shared\ValueObjects\Decimal;
use App\Domain\Shared\ValueObjects\Money;

/**
 * Contexto de una nota de crédito o débito. Siempre referencia el documento
 * original: una anulación es un documento nuevo, nunca un UPDATE ni un DELETE
 * sobre la factura original. Invariante I3.
 */
final readonly class NotaFiscalContexto
{
    public function __construct(
        public string $tipo,                       // 'nota_credito' | 'nota_debito'
        public string $idempotencyKey,
        public FechaFiscal $fechaFiscal,
        public string $documentoPadreNumeroFiscal,
        public int $documentoPadreId,
        public LineasDocumento $lineas,
        public PagosDocumento $pagos,
        public string $motivo,
        public ?int $sucursalId = null,
        public ?int $ordenId = null,
        public ?Decimal $tasaCambio = null,
        public array $payload = [],
    ) {}

    public function total(): Money
    {
        return $this->pagos->total();
    }

    /** @return array<string, mixed> */
    public function aArray(): array
    {
        return [
            'tipo' => $this->tipo,
            'idempotency_key' => $this->idempotencyKey,
            'fecha_fiscal' => $this->fechaFiscal->iso(),
            'documento_padre' => $this->documentoPadreNumeroFiscal,
            'documento_padre_id' => $this->documentoPadreId,
            'motivo' => $this->motivo,
            'sucursal_id' => $this->sucursalId,
            'orden_id' => $this->ordenId,
            'tasa_cambio' => $this->tasaCambio?->__toString(),
            'total' => $this->total()->formateado(),
            'lineas' => $this->lineas->aArray(),
            'pagos' => $this->pagos->aArray(),
            'payload' => $this->payload,
        ];
    }
}
