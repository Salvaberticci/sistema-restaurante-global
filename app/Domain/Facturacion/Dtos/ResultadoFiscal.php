<?php

namespace App\Domain\Facturacion\Dtos;

use App\Domain\Shared\Support\FechaFiscal;
use App\Domain\Shared\ValueObjects\Moneda;
use App\Domain\Shared\ValueObjects\Money;

/**
 * Lo que el emisor devuelve después de emitir.
 *
 * `numeroFiscal` es lo que ve el cliente impreso. `documentoId` es el id interno
 * de Laravel. `serial` y `contador` son los valores que la memoria fiscal del
 * emisor tiene guardados: sin ellos, al comparar el sistema contra la máquina no
 * hay forma de detectar una discrepancy. Ver spec 070.
 *
 * `crudo` guarda la respuesta tal cual del emisor. Es la evidencia para una
 * auditoría y la única forma de depurar un rechazo sin volver a emitir.
 */
final readonly class ResultadoFiscal
{
    public function __construct(
        public ?int $documentoId,
        public string $numeroFiscal,     // '0001-00000123'
        public string $serial,
        public int $contador,
        public string $hash,
        public FechaFiscal $emitidoEn,
        public Moneda $moneda,
        public Money $total,
        public string $driver,
        public array $crudo = [],
        public bool $yaEmitido = false,
    ) {}

    /** Reemisión: el driver ya había emitido este documento antes. */
    public function comoReemision(): self
    {
        return new self(
            documentoId: $this->documentoId,
            numeroFiscal: $this->numeroFiscal,
            serial: $this->serial,
            contador: $this->contador,
            hash: $this->hash,
            emitidoEn: $this->emitidoEn,
            moneda: $this->moneda,
            total: $this->total,
            driver: $this->driver,
            crudo: $this->crudo,
            yaEmitido: true,
        );
    }

    /** @return array<string, mixed> */
    public function aArray(): array
    {
        return [
            'documento_id' => $this->documentoId,
            'numero_fiscal' => $this->numeroFiscal,
            'serial' => $this->serial,
            'contador' => $this->contador,
            'hash' => $this->hash,
            'emitido_en' => $this->emitidoEn->iso(),
            'moneda' => $this->moneda->value,
            'total' => $this->total->formateado(),
            'driver' => $this->driver,
            'ya_emitido' => $this->yaEmitido,
        ];
    }
}
