<?php

namespace App\Domain\Facturacion\Dtos;

use App\Domain\Shared\ValueObjects\Decimal;
use App\Domain\Shared\ValueObjects\Moneda;
use App\Domain\Shared\ValueObjects\Money;

/**
 * Línea de un documento fiscal.
 *
 * Semántica del importe, igual que en una impresora fiscal: `importe` es el
 * precio final de la línea **con el impuesto incluido**, que es lo que el
 * cliente ve impreso. La base imponible y el impuesto salen de ahí. Por eso
 * `importe = baseImponible + impuesto`, y por eso el total del documento es la
 * suma de los importes más el IGTF, la propina y menos los descuentos.
 *
 * El desglose de impuestos llega ya resuelto: quien arma el documento consultó
 * la vigencia correspondiente a la fecha fiscal (invariante I1) y pasa el
 * resultado. El driver no calcula impuestos.
 */
final readonly class LineaDocumento
{
    public function __construct(
        public string $codigo,
        public string $descripcion,
        public Decimal $cantidad,
        public Decimal $precioUnitario,
        /** Precio final de la línea, con impuesto incluido. */
        public Money $importe,
        /** Alícuota aplicada, como código, no como número. Ej: 'IVA_G', 'IVA_E'. */
        public string $alicuota,
        public Money $baseImponible,
        public Money $impuesto,
        public bool $exento = false,
    ) {}

    /** @return array<string, string> */
    public function aArray(): array
    {
        return [
            'codigo' => $this->codigo,
            'descripcion' => $this->descripcion,
            'cantidad' => $this->cantidad->__toString(),
            'precio_unitario' => $this->precioUnitario->__toString(),
            'importe' => $this->importe->formateado(),
            'moneda' => $this->moneda()->value,
            'alicuota' => $this->alicuota,
            'base_imponible' => $this->baseImponible->formateado(),
            'impuesto' => $this->impuesto->formateado(),
            'exento' => $this->exento ? '1' : '0',
        ];
    }

    public function moneda(): Moneda
    {
        return $this->importe->moneda();
    }
}
