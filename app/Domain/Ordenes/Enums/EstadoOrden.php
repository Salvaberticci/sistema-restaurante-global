<?php

namespace App\Domain\Ordenes\Enums;

enum EstadoOrden: string
{
    case Abierta = 'abierta';
    case EnviadaCocina = 'enviada_cocina';
    case Servida = 'servida';
    case PidioCuenta = 'pidio_cuenta';
    case Cobrada = 'cobrada';
    case Anulada = 'anulada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Abierta => 'Abierta',
            self::EnviadaCocina => 'En cocina',
            self::Servida => 'Servida',
            self::PidioCuenta => 'Pidió la cuenta',
            self::Cobrada => 'Cobrada',
            self::Anulada => 'Anulada',
        };
    }

    /** Color del distintivo en la pantalla del mesero. */
    public function color(): string
    {
        return match ($this) {
            self::Abierta => 'gray',
            self::EnviadaCocina => 'info',
            self::Servida => 'warning',
            self::PidioCuenta => 'danger',
            self::Cobrada => 'success',
            self::Anulada => 'danger',
        };
    }

    public function estaCerrada(): bool
    {
        return $this === self::Cobrada || $this === self::Anulada;
    }

    /**
     * Un orden solo avanza hacia adelante. El sistema no tiene una acción para
     * devolver una orden cobrada a abierta: eso se resuelve con una nota de
     * crédito, que deja rastro (invariante I3).
     *
     * @return list<self>
     */
    public function siguientes(): array
    {
        return match ($this) {
            self::Abierta => [self::EnviadaCocina, self::PidioCuenta, self::Anulada],
            self::EnviadaCocina => [self::Servida, self::PidioCuenta, self::Anulada],
            self::Servida => [self::PidioCuenta, self::Anulada],
            self::PidioCuenta => [self::Cobrada, self::Anulada],
            self::Cobrada, self::Anulada => [],
        };
    }

    public function puedeTransicionarA(self $nuevo): bool
    {
        return in_array($nuevo, $this->siguientes(), true);
    }
}
