<?php

namespace App\Domain\Reservas\Services;

use App\Domain\Reservas\Enums\EstadoPago;
use App\Domain\Reservas\Enums\EstadoReserva;
use App\Domain\Reservas\Models\Reservacion;
use App\Domain\Shared\Support\FechaFiscal;

/**
 * Confirmación de reservas, con la regla del 100% por adelantado.
 *
 * El servicio existe para que la invariante I8 tenga **un solo lugar** donde se
 * cumple. Si la validación viviera en el formulario, otro camino —un importador,
 * una cola, un comando de consola— podría confirmar sin pagar y nadie lo notaría
 * hasta que el evento ya pasó.
 */
final class ConfirmaReserva
{
    /**
     * Confirma una reserva.
     *
     * @throws \DomainException si el pago no está completo
     */
    public function ejecutar(Reservacion $reserva): Reservacion
    {
        if (! $reserva->estado->puedeTransicionarA(EstadoReserva::Confirmada)) {
            throw new \DomainException(
                "La reserva «{$reserva->nombre}» está en estado «{$reserva->estado->etiqueta()}» y no se puede confirmar."
            );
        }

        // I8: evento y curso se pagan 100% por adelantado. Sin esta condición la
        // reserva quedaría confirmada con un abono del 30%, que es exactamente lo
        // que la regla del negocio prohíbe.
        if (! $reserva->estaPagada()) {
            throw new \DomainException(sprintf(
                'No se puede confirmar «%s»: falta el pago completo. Va %d%% de Bs %s. '
                .'Eventos y cursos se pagan 100%% por adelantado.',
                $reserva->nombre,
                $reserva->avancePago(),
                number_format((float) $reserva->pagado, 2),
            ));
        }

        $reserva->estado = EstadoReserva::Confirmada;
        $reserva->confirmada_at = FechaFiscal::hoy()->fecha();

        $reserva->save();

        return $reserva;
    }

    /**
     * Recalcula `total` y `estado_pago` desde personas y precio.
     *
     * El total se recalcula antes que el estado: si el número de personas cambia,
     * el estado de pago anterior quedaría describiendo un monto que ya no existe.
     */
    public function recalcular(Reservacion $reservacion): Reservacion
    {
        $total = round((float) $reservacion->precio_unitario * $reservacion->personas, 2);

        $reservacion->total = number_format($total, 2, '.', '');
        $reservacion->estado_pago = EstadoPago::desde(
            $reservacion->total,
            $reservacion->pagado,
        );

        // Si al recalcular el pago deja de estar completo y la reserva ya estaba
        // confirmada, se devuelve a "solicitada". Es lo contrario de dejar una
        // reserva confirmada con una deuda escondida.
        if (! $reservacion->estaPagada() && $reservacion->estado === EstadoReserva::Confirmada) {
            $reservacion->estado = EstadoReserva::Solicitada;
            $reservacion->confirmada_at = null;
        }

        $reservacion->save();

        return $reservacion;
    }
}
