<?php

namespace App\Domain\Facturacion\Dtos;

use App\Domain\Shared\ValueObjects\Decimal;
use App\Domain\Shared\ValueObjects\Money;

/**
 * Pago de un documento fiscal. El pago mixto es la norma en Venezuela: el
 * cliente entrega parte en bolívares y parte en divisa en la misma cuenta.
 *
 * Por eso un pago tiene dos importes y no uno:
 *
 *  - `montoNativo` es lo que el cliente entregó, en su propia moneda. Es lo que
 *    sirve para conciliar la caja y para el IGTF, que se calcula sobre la porción
 *    pagada en divisa.
 *  - `montoDocumento` es lo que ese pago cubre del documento, ya siempre en la
 *    moneda del documento. Convertirlo es responsabilidad de quien arma el
 *    documento, con la tasa congelada de la fecha fiscal.
 *
 * `porcentajeEnDivisa` alimenta el cálculo del IGTF: el impuesto se aplica solo
 * sobre esa porción, no sobre el total. Si el IGTF se traslada al cliente o lo
 * asume el restaurante es un parámetro del sistema (`igtf.cobrar_al_cliente`),
 * no una constante del driver.
 */
final readonly class PagoDocumento
{
    public function __construct(
        public string $metodo,
        public string $moneda,
        public Money $montoNativo,
        public Money $montoDocumento,
        public Decimal $porcentajeEnDivisa,
        public ?string $referencia = null,
        public ?Money $igtf = null,
    ) {}

    /** Pago íntegramente en la moneda del documento. */
    public static function simple(string $metodo, Money $monto, ?string $referencia = null): self
    {
        return new self(
            metodo: $metodo,
            moneda: $monto->moneda()->value,
            montoNativo: $monto,
            montoDocumento: $monto,
            porcentajeEnDivisa: Decimal::deCero(),
            referencia: $referencia,
            igtf: $monto->deCero(),
        );
    }

    /**
     * Pago en divisa. `$montoEnDivisa` es lo que entregó el cliente y
     * `$montoEnDocumento` es lo que cubre, ya convertido a la tasa del día.
     */
    public static function enDivisa(
        string $metodo,
        Money $montoEnDivisa,
        Money $montoEnDocumento,
        ?string $referencia = null,
    ): self {
        return new self(
            metodo: $metodo,
            moneda: $montoEnDivisa->moneda()->value,
            montoNativo: $montoEnDivisa,
            montoDocumento: $montoEnDocumento,
            porcentajeEnDivisa: Decimal::deEntero(100),
            referencia: $referencia,
            igtf: $montoEnDocumento->deCero(),
        );
    }

    public function esEnDivisa(): bool
    {
        return $this->porcentajeEnDivisa->esPositivo();
    }

    public function conIgtf(Money $igtf): self
    {
        return new self(
            metodo: $this->metodo,
            moneda: $this->moneda,
            montoNativo: $this->montoNativo,
            montoDocumento: $this->montoDocumento,
            porcentajeEnDivisa: $this->porcentajeEnDivisa,
            referencia: $this->referencia,
            igtf: $igtf,
        );
    }

    /** @return array<string, string|null> */
    public function aArray(): array
    {
        return [
            'metodo' => $this->metodo,
            'moneda' => $this->moneda,
            'monto_nativo' => $this->montoNativo->formateado(),
            'monto_documento' => $this->montoDocumento->formateado(),
            'moneda_documento' => $this->montoDocumento->moneda()->value,
            'porcentaje_en_divisa' => $this->porcentajeEnDivisa->__toString(),
            'referencia' => $this->referencia,
            'igtf' => $this->igtf?->formateado(),
        ];
    }
}
