<?php

namespace App\Filament\Resources\ReservacionResource\Pages;

use App\Domain\Reservas\Models\Reservacion;
use App\Domain\Reservas\Services\ConfirmaReserva;
use App\Filament\Resources\ReservacionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateReservacion extends CreateRecord
{
    protected static string $resource = ReservacionResource::class;

    /**
     * El total y el estado de pago se derivan, no se escriben.
     *
     * `precio_unitario × personas` y el estado según lo abonado se calculan
     * siempre después de guardar. Si se confiaran los valores del formulario, un
     * campo mal editado dejaría una reserva que dice "pagada" con la mitad
     * adentro, y el CHECK de la tabla no la frenaría porque los campos serían
     * coherentes entre sí.
     */
    protected function afterCreate(): void
    {
        app(ConfirmaReserva::class)->recalcular($this->reserva());
    }

    /**
     * El registro de esta página, ya con el modelo del dominio.
     *
     * Filament lo tipa como `Model`. Se comprueba en vez de forzarlo con un
     * cast: si algún día el recurso cambiara de modelo, esto revienta con un
     * mensaje claro en lugar de dejar que un cálculo escriba columnas que no
     * existen en otra tabla.
     */
    private function reserva(): Reservacion
    {
        $registro = $this->getRecord();

        if (! $registro instanceof Reservacion) {
            throw new \LogicException('El recurso de reservaciones debe usar el modelo '.Reservacion::class.'.');
        }

        return $registro;
    }
}
