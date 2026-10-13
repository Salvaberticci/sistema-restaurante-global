<?php

namespace App\Domain\Reservas\Enums;

/**
 * Estado del pago de una reserva.
 *
 * Los tres casos son cerrados a propósito. Un cuarto estado como "reembolsado"
 * se añade el día que exista el flujo de devolución, y no antes: un enum con
 * opciones que el sistema no implementa es una forma elegante de mentir en el
 * formulario.
 */
enum EstadoPago: string
{
    case Pendiente = 'pendiente';
    case Parcial = 'parcial';
    case Pagado = 'pagado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Parcial => 'Parcial',
            self::Pagado => 'Pagado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pendiente => 'danger',
            self::Parcial => 'warning',
            self::Pagado => 'success',
        };
    }

    /**
     * Estado que corresponde a lo ya abonado.
     *
     * Se calcula desde los importes y no se escribe a mano, para que no exista
     * una reserva que diga "pagado" con la mitad adentro.
     */
    public static function desde(string $total, string $pagado): self
    {
        $total = (float) $total;
        $pagado = (float) $pagado;

        return match (true) {
            $pagado <= 0 => self::Pendiente,
            $pagado + 0.0001 >= $total => self::Pagado,
            default => self::Parcial,
        };
    }
}
