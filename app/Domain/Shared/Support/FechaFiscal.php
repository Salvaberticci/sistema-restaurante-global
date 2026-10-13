<?php

namespace App\Domain\Shared\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Invariante I4 de AGENTS.md: la fecha fiscal manda.
 *
 * Todo cálculo de vigencia (IVA, IGTF, tasa de cambio) recibe esta fecha en vez
 * de llamar a `now()` dentro del servicio. Así los tests son deterministas y el
 * reloj del servidor no puede cambiar un resultado fiscal.
 */
final readonly class FechaFiscal
{
    public function __construct(
        private CarbonImmutable $fecha,
    ) {}

    public static function hoy(): self
    {
        return new self(CarbonImmutable::now(config('app.timezone')));
    }

    public static function de(CarbonInterface|string $fecha): self
    {
        if (is_string($fecha)) {
            $fecha = CarbonImmutable::parse($fecha, config('app.timezone'));
        }

        return new self(CarbonImmutable::parse($fecha));
    }

    public function fecha(): CarbonImmutable
    {
        return $this->fecha;
    }

    public function formato(string $formato): string
    {
        return $this->fecha->format($formato);
    }

    /** `2026-10-12`, que es como se guardan las vigencias en la base. */
    public function iso(): string
    {
        return $this->fecha->toDateString();
    }

    public function conHora(int $hora, int $minuto = 0): CarbonImmutable
    {
        return $this->fecha->setTime($hora, $minuto);
    }

    public function sumarDias(int $dias): self
    {
        return new self($this->fecha->addDays($dias));
    }

    public function esEntre(self $desde, ?self $hasta = null): bool
    {
        if ($this->fecha->lessThan($desde->fecha)) {
            return false;
        }

        return $hasta === null || $this->fecha->lessThanOrEqualTo($hasta->fecha);
    }

    public function __toString(): string
    {
        return $this->iso();
    }
}
