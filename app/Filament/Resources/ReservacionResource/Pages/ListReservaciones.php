<?php

namespace App\Filament\Resources\ReservacionResource\Pages;

use App\Filament\Resources\ReservacionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReservaciones extends ListRecords
{
    protected static string $resource = ReservacionResource::class;

    protected static ?string $title = 'Reservaciones';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nueva reserva'),
        ];
    }
}
