<?php

namespace App\Domain\Facturacion\Contracts;

final readonly class DriverCapabilities
{
    public function __construct(
        /** El cajero puede cobrar parte en bolívares y parte en divisa. */
        public readonly bool $soportaPagoMixto,
        /** La propina entra al documento y no como línea aparte. */
        public readonly bool $soportaPropinaEnDocumento,
        /** El descuento va dentro de la línea, no como un ítem negativo. */
        public readonly bool $soportaDescuentoEnLinea,
        /** Requiere abrir el documento antes de mandar las líneas. */
        public readonly bool $requiereAperturaPrevia,
        public readonly int $maximoLineas,
        /** El emisor acepta pagos en más de una moneda en el mismo documento. */
        public readonly bool $soportaMultimoneda,
    ) {}

    public static function deImpresoraFiscal(): self
    {
        return new self(
            soportaPagoMixto: true,
            soportaPropinaEnDocumento: false,
            soportaDescuentoEnLinea: true,
            requiereAperturaPrevia: true,
            maximoLineas: 500,
            soportaMultimoneda: true,
        );
    }

    public static function deSimulador(): self
    {
        return new self(
            soportaPagoMixto: true,
            soportaPropinaEnDocumento: true,
            soportaDescuentoEnLinea: true,
            requiereAperturaPrevia: true,
            maximoLineas: 1000,
            soportaMultimoneda: true,
        );
    }

    public static function deFacturacionElectronica(): self
    {
        return new self(
            soportaPagoMixto: true,
            soportaPropinaEnDocumento: true,
            soportaDescuentoEnLinea: true,
            requiereAperturaPrevia: false,
            maximoLineas: 5000,
            soportaMultimoneda: true,
        );
    }

    /** @return array<string, bool|int> */
    public function aArray(): array
    {
        return [
            'soporta_pago_mixto' => $this->soportaPagoMixto,
            'soporta_propina_en_documento' => $this->soportaPropinaEnDocumento,
            'soporta_descuento_en_linea' => $this->soportaDescuentoEnLinea,
            'requiere_apertura_previa' => $this->requiereAperturaPrevia,
            'soporta_multimoneda' => $this->soportaMultimoneda,
            'maximo_lineas' => $this->maximoLineas,
        ];
    }
}
