<?php

namespace App\Domain\Facturacion\Dtos;

use App\Domain\Shared\ValueObjects\Moneda;
use App\Domain\Shared\ValueObjects\Money;

/**
 * Colección de pagos de un documento. El driver la recibe completa para poder
 * emitirla en un solo bloque.
 *
 * El total se arma sumando `montoDocumento` de cada pago, nunca `montoNativo`:
 * los nativos están en monedas distintas y sumarlos no significa nada. El
 * `montoDocumento` de cada uno ya está en la moneda del documento.
 *
 * @implements \IteratorAggregate<int, PagoDocumento>
 */
final readonly class PagosDocumento implements \Countable, \IteratorAggregate
{
    /** @var list<PagoDocumento> */
    private array $pagos;

    public function __construct(array $pagos)
    {
        $this->pagos = array_values($pagos);
    }

    public static function de(PagoDocumento ...$pagos): self
    {
        return new self($pagos);
    }

    public static function vacio(): self
    {
        return new self([]);
    }

    public function sumar(PagoDocumento $pago): self
    {
        return new self([...$this->pagos, $pago]);
    }

    public function total(): Money
    {
        if ($this->pagos === []) {
            throw new \LogicException('Un documento fiscal no puede cerrarse sin pagos.');
        }

        return array_reduce(
            $this->pagos,
            fn (Money $acumulado, PagoDocumento $pago) => $acumulado->sumar($pago->montoDocumento),
            Money::deCero($this->pagos[0]->montoDocumento->moneda()),
        );
    }

    /** Total entregado por el cliente, por moneda. Para conciliar la caja. */
    public function nativoPor(string $moneda): Money
    {
        $total = null;

        foreach ($this->pagos as $pago) {
            if ($pago->moneda !== $moneda) {
                continue;
            }

            $total = $total === null ? $pago->montoNativo : $total->sumar($pago->montoNativo);
        }

        return $total ?? Money::deCero(Moneda::from($moneda));
    }

    public function igtfTotal(): Money
    {
        $igtf = null;

        foreach ($this->pagos as $pago) {
            if ($pago->igtf === null) {
                continue;
            }

            $igtf = $igtf === null ? $pago->igtf : $igtf->sumar($pago->igtf);
        }

        return $igtf ?? Money::deCero($this->pagos[0]->montoDocumento->moneda());
    }

    public function esPagoMixto(): bool
    {
        $divisas = array_filter($this->pagos, fn (PagoDocumento $p) => $p->esEnDivisa());
        $bolivares = array_filter($this->pagos, fn (PagoDocumento $p) => ! $p->esEnDivisa());

        return $divisas !== [] && $bolivares !== [];
    }

    /** @return list<PagoDocumento> */
    public function todos(): array
    {
        return $this->pagos;
    }

    /** @return list<array<string, string|null>> */
    public function aArray(): array
    {
        return array_map(fn (PagoDocumento $p) => $p->aArray(), $this->pagos);
    }

    public function count(): int
    {
        return count($this->pagos);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->pagos);
    }
}
