<?php

namespace App\Domain\Shared\ValueObjects;

use App\Domain\Shared\Exceptions\MonedaIncompatibleException;
use App\Domain\Shared\Exceptions\PrecisionExcedidaException;

/**
 * Invariante I2 de AGENTS.md: el dinero nunca es float.
 *
 * El importe se guarda como entero en la unidad menor (centavos, céntimos) y el
 * cálculo exacto se hace con `Decimal`. Los floats solo entran por
 * `Decimal::deFloat()`, que deja rastro para poder rastrearlos.
 */
final readonly class Money implements \JsonSerializable, \Stringable
{
    private function __construct(
        private int $centavos,
        private Moneda $moneda,
    ) {}

    public static function deCero(Moneda $moneda = Moneda::Dolar): self
    {
        return new self(0, $moneda);
    }

    /**
     * @param  int  $centavos  Cantidad en la unidad menor (centavos)
     */
    public static function deCentavos(int $centavos, Moneda $moneda = Moneda::Dolar): self
    {
        return new self($centavos, $moneda);
    }

    /**
     * Construye desde el importe decimal que el usuario ve escrito:
     * `Money::de('7.50', Moneda::Dolar)` son 750 centavos.
     *
     * @throws PrecisionExcedidaException
     */
    public static function de(string|int|Decimal $monto, Moneda $moneda = Moneda::Dolar): self
    {
        $decimal = $monto instanceof Decimal ? $monto : Decimal::deString((string) $monto);

        return new self(
            $decimal->multiplicar(Decimal::deEntero($moneda->factor()))->aInt(),
            $moneda,
        );
    }

    public static function deDolares(string|int|Decimal $monto): self
    {
        return self::de($monto, Moneda::Dolar);
    }

    public static function deBolivares(string|int|Decimal $monto): self
    {
        return self::de($monto, Moneda::Bolivar);
    }

    public function sumar(self $otro): self
    {
        $this->asegurarMismaMoneda($otro);

        return new self($this->centavos + $otro->centavos, $this->moneda);
    }

    public function restar(self $otro): self
    {
        $this->asegurarMismaMoneda($otro);

        return new self($this->centavos - $otro->centavos, $this->moneda);
    }

    /**
     * Multiplica por un factor decimal exacto. `porcentaje()` es el atajo para
     * descuentos y para el IGTF.
     */
    public function multiplicarPor(Decimal $factor): self
    {
        $escalado = $this->aDecimal()->multiplicar($factor);

        return new self($escalado->multiplicar(Decimal::deEntero($this->moneda->factor()))->aInt(), $this->moneda);
    }

    public function porcentaje(Decimal|int|string $porcentaje): self
    {
        $factor = $porcentaje instanceof Decimal
            ? $porcentaje
            : Decimal::deString((string) $porcentaje);

        return $this->multiplicarPor($factor->dividir(Decimal::deEntero(100)));
    }

    public function negativo(): self
    {
        return new self(-$this->centavos, $this->moneda);
    }

    public function absoluto(): self
    {
        return new self(abs($this->centavos), $this->moneda);
    }

    public function esCero(): bool
    {
        return $this->centavos === 0;
    }

    public function esNegativo(): bool
    {
        return $this->centavos < 0;
    }

    public function esPositivo(): bool
    {
        return $this->centavos > 0;
    }

    public function esMayorQue(self $otro): bool
    {
        $this->asegurarMismaMoneda($otro);

        return $this->centavos > $otro->centavos;
    }

    public function esMenorQue(self $otro): bool
    {
        $this->asegurarMismaMoneda($otro);

        return $this->centavos < $otro->centavos;
    }

    public function esIgualA(self $otro): bool
    {
        return $this->centavos === $otro->centavos && $this->moneda === $otro->moneda;
    }

    /** El menor importe que cubre este. Para repartir sin perder centavos. */
    public function redondearAMas(): self
    {
        return $this;
    }

    /** Reparte un importe entre n partes sin perder ni inventar centavos. */
    public function repartirEn(int $partes): array
    {
        if ($partes < 1) {
            throw new \InvalidArgumentException('Hay que repartir en al menos una parte.');
        }

        $base = intdiv($this->centavos, $partes);
        $resto = $this->centavos - ($base * $partes);

        $partidas = [];

        for ($i = 0; $i < $partes; $i++) {
            $partidas[] = new self($base + ($i < abs($resto) ? ($resto < 0 ? -1 : 1) : 0), $this->moneda);
        }

        return $partidas;
    }

    public function centavos(): int
    {
        return $this->centavos;
    }

    public function moneda(): Moneda
    {
        return $this->moneda;
    }

    public function aDecimal(): Decimal
    {
        return Decimal::deEntero($this->centavos)
            ->dividir(Decimal::deEntero($this->moneda->factor()));
    }

    /** Para columnas `decimal(18,4)`. */
    public function aBaseDeDatos(): string
    {
        return $this->aDecimal()->aBaseDeDatos();
    }

    /** "1.234,56" — formato venezolano, sin símbolo. */
    public function formateado(): string
    {
        return number_format(
            $this->centavos / $this->moneda->factor(),
            $this->moneda->decimales(),
            ',',
            '.',
        );
    }

    public function formateadoConSimbolo(): string
    {
        return $this->moneda->simbolo().$this->formateado();
    }

    /**
     * El JSON siempre viaja como string. Un float en la respuesta HTTP es un
     * float perdido en el navegador.
     */
    public function jsonSerialize(): string
    {
        return $this->aDecimal()->__toString();
    }

    public function __toString(): string
    {
        return $this->moneda->value.' '.$this->formateado();
    }

    private function asegurarMismaMoneda(self $otro): void
    {
        if ($this->moneda !== $otro->moneda) {
            throw MonedaIncompatibleException::entre(
                $this->moneda->value,
                $otro->moneda->value,
            );
        }
    }
}
