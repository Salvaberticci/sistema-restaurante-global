<?php

namespace App\Filament\Resources\ReservacionResource\Pages;

use App\Domain\Reservas\Models\Reservacion;
use App\Domain\Reservas\Services\ConfirmaReserva;
use App\Filament\Resources\ReservacionResource;
use Filament\Resources\Pages\EditRecord;

class EditReservacion extends EditRecord
{
    protected static string $resource = ReservacionResource::class;

    /**
     * Recalcular después de editar tiene una consecuencia que conviene tener
     * presente: si alguien baja el número de personas o el precio de una reserva
     * ya confirmada, el pago deja de cubrir el total y la reserva vuelve a
     * "solicitada" sola. Es lo correcto —no se puede confirmar un saldo — pero
     * conviene que quien lo vea entienda por qué.
     */
    protected function afterSave(): void
    {
        app(ConfirmaReserva::class)->recalcular($this->reserva());
    }

    /** El registro ya con el modelo del dominio; ver `CreateReservacion`. */
    private function reserva(): Reservacion
    {
        $registro = $this->getRecord();

        if (! $registro instanceof Reservacion) {
            throw new \LogicException('El recurso de reservaciones debe usar el modelo '.Reservacion::class.'.');
        }

        return $registro;
    }
}
