<?php

namespace App\Domain\Shared\ValueObjects;

use App\Domain\Shared\Exceptions\PrecisionExcedidaException;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Envoltura de decimal exacto. PHP no tiene decimal nativo y `float` no sirve
 * para dinero: 0.1 + 0.2 !== 0.3. Invariante I2 de AGENTS.md.
 *
 * Todas las conversiones desde float pasan por `deFloat()` y quedan marcadas,
 * para que un float sneaking en el código sea rastreable con un grep.
 */
final readonly class Decimal implements \Stringable
{
    /** Escala de las columnas `decimal(18,4)` del esquema. */
    public const ESCALA_BASE = 4;

    private function __construct(
        private BigDecimal $valor,
    ) {}

    public static function deCero(): self
    {
        return new self(BigDecimal::zero());
    }

    public static function deEntero(int|float $valor): self
    {
        return self::deString((string) $valor);
    }

    public static function deString(string $valor): self
    {
        return new self(BigDecimal::of($valor));
    }

    /**
     * Única puerta de entrada para float. Existe para poder localizar en el
     * código dónde seLostó la precisión exacta.
     */
    public static function deFloat(float $valor): self
    {
        return new self(BigDecimal::of((string) $valor));
    }

    public function sumar(self $otro): self
    {
        return new self($this->valor->plus($otro->valor));
    }

    public function restar(self $otro): self
    {
        return new self($this->valor->minus($otro->valor));
    }

    public function multiplicar(self $otro): self
    {
        return new self($this->valor->multipliedBy($otro->valor));
    }

    public function dividir(self $otro): self
    {
        if ($otro->esCero()) {
            throw new \DivisionByZeroError('No se puede dividir un decimal entre cero.');
        }

        return new self($this->valor->dividedBy($otro->valor, self::ESCALA_BASE, RoundingMode::HALF_UP));
    }

    /**
     * @return self Porcentaje listo para multiplicar: 16% -> self::porcentaje(16)
     */
    public static function porcentaje(int|string|float $porcentaje): self
    {
        return self::deString((string) $porcentaje)->dividir(self::deEntero(100));
    }

    public function negativo(): self
    {
        return new self($this->valor->multipliedBy(-1));
    }

    public function absoluto(): self
    {
        return new self($this->valor->abs());
    }

    public function redondeadoAScala(int $escala): self
    {
        return new self($this->valor->toScale($escala, RoundingMode::HALF_UP));
    }

    public function aEscalaBase(): self
    {
        return $this->redondeadoAScala(self::ESCALA_BASE);
    }

    public function esCero(): bool
    {
        return $this->valor->isZero();
    }

    public function esNegativo(): bool
    {
        return $this->valor->isNegative();
    }

    public function esPositivo(): bool
    {
        return $this->valor->isPositive();
    }

    public function esMayorQue(self $otro): bool
    {
        return $this->valor->isGreaterThan($otro->valor);
    }

    public function esMenorQue(self $otro): bool
    {
        return $this->valor->isLessThan($otro->valor);
    }

    public function esIgualA(self $otro): bool
    {
        return $this->valor->isEqualTo($otro->valor);
    }

    public function compararCon(self $otro): int
    {
        return $this->valor->compareTo($otro->valor);
    }

    public function aInt(): int
    {
        return $this->redondeadoAScala(0)->valor->toInt();
    }

    public function aFloat(): float
    {
        return (float) $this->valor->__toString();
    }

    /**
     * Conversión a la columna decimal(18,4). Lanza si no cabe, en vez de
     * truncar en silencio y descuadrar un total.
     */
    public function aBaseDeDatos(): string
    {
        $ajustado = $this->aEscalaBase();

        if (mb_strlen(ltrim($ajustado->valor->abs()->toScale(0)->__toString(), '0')) > 14) {
            throw new PrecisionExcedidaException(
                'El valor '.$this->valor.' no cabe en decimal(18,4).'
            );
        }

        return $ajustado->valor->__toString();
    }

    public function raw(): BigDecimal
    {
        return $this->valor;
    }

    public function __toString(): string
    {
        return $this->valor->__toString();
    }
}
