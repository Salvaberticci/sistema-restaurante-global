<?php

namespace App\Domain\Reservas\Enums;

/**
 * Estado de la reserva.
 *
 * `confirmada` tiene una condición que no es de este enum sino de la base: la
 * tabla no deja guardar una reserva confirmada sin el pago completo
 * (invariante I8). Aquí solo se declara que el estado existe; la garantía de que
 * no se puede saltar está en el CHECK de la migración.
 */
enum EstadoReserva: string
{
    case Solicitada = 'solicitada';
    case Confirmada = 'confirmada';
    case Realizada = 'realizada';
    case Cancelada = 'cancelada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Solicitada => 'Solicitada',
            self::Confirmada => 'Confirmada',
            self::Realizada => 'Realizada',
            self::Cancelada => 'Cancelada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Solicitada => 'warning',
            self::Confirmada => 'info',
            self::Realizada => 'success',
            self::Cancelada => 'danger',
        };
    }

    /**
     * @return list<self>
     */
    public function siguientes(): array
    {
        return match ($this) {
            self::Solicitada => [self::Confirmada, self::Cancelada],
            self::Confirmada => [self::Realizada, self::Cancelada],
            self::Realizada, self::Cancelada => [],
        };
    }

    public function puedeTransicionarA(self $nuevo): bool
    {
        return in_array($nuevo, $this->siguientes(), true);
    }
}
