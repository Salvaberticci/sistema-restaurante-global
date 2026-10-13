<?php

namespace App\Domain\Facturacion\Dtos;

use App\Domain\Shared\ValueObjects\Decimal;
use App\Domain\Shared\ValueObjects\Moneda;
use App\Domain\Shared\ValueObjects\Money;

/**
 * Colección de líneas de un documento fiscal.
 *
 * @implements \IteratorAggregate<int, LineaDocumento>
 */
final readonly class LineasDocumento implements \Countable, \IteratorAggregate
{
    /** @var list<LineaDocumento> */
    private array $lineas;

    public function __construct(array $lineas)
    {
        $this->lineas = array_values($lineas);
    }

    public static function de(LineaDocumento ...$lineas): self
    {
        return new self($lineas);
    }

    public function agregar(LineaDocumento $linea): self
    {
        return new self([...$this->lineas, $linea]);
    }

    public function total(): Money
    {
        if ($this->lineas === []) {
            return Money::deCero(Moneda::Dolar);
        }

        return array_reduce(
            $this->lineas,
            fn (Money $acumulado, LineaDocumento $linea) => $acumulado->sumar($linea->importe),
            Money::deCero($this->lineas[0]->moneda()),
        );
    }

    public function baseImponible(): Money
    {
        if ($this->lineas === []) {
            return Money::deCero(Moneda::Dolar);
        }

        return array_reduce(
            $this->lineas,
            fn (Money $acumulado, LineaDocumento $linea) => $acumulado->sumar($linea->baseImponible),
            Money::deCero($this->lineas[0]->moneda()),
        );
    }

    public function impuestos(): Money
    {
        if ($this->lineas === []) {
            return Money::deCero(Moneda::Dolar);
        }

        return array_reduce(
            $this->lineas,
            fn (Money $acumulado, LineaDocumento $linea) => $acumulado->sumar($linea->impuesto),
            Money::deCero($this->lineas[0]->moneda()),
        );
    }

    /**
     * Desglose de impuestos por alícuota. El documento fiscal tiene que
     * imprimirlos separados: "IVA general" y "IVA exonerado" no van juntos.
     *
     * @return array<string, Money>
     */
    public function impuestosPorAlicuota(): array
    {
        $porAlicuota = [];

        foreach ($this->lineas as $linea) {
            $clave = $linea->alicuota;
            $porAlicuota[$clave] = isset($porAlicuota[$clave])
                ? $porAlicuota[$clave]->sumar($linea->impuesto)
                : $linea->impuesto;
        }

        return $porAlicuota;
    }

    /** @return array<string, Decimal> */
    public function cantidadesPorCodigo(): array
    {
        $porCodigo = [];

        foreach ($this->lineas as $linea) {
            $porCodigo[$linea->codigo] = isset($porCodigo[$linea->codigo])
                ? $porCodigo[$linea->codigo]->sumar($linea->cantidad)
                : $linea->cantidad;
        }

        return $porCodigo;
    }

    public function hayExentos(): bool
    {
        foreach ($this->lineas as $linea) {
            if ($linea->exento) {
                return true;
            }
        }

        return false;
    }

    /** @return list<LineaDocumento> */
    public function todas(): array
    {
        return $this->lineas;
    }

    /** @return list<array<string, string>> */
    public function aArray(): array
    {
        return array_map(fn (LineaDocumento $l) => $l->aArray(), $this->lineas);
    }

    public function count(): int
    {
        return count($this->lineas);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->lineas);
    }
}
