<?php

namespace App\Filament\Resources\AuditoriaResource\Pages;

use App\Filament\Resources\AuditoriaResource;
use Filament\Resources\Pages\ListRecords;

/**
 * La bitácora, de la más reciente a la más antigua.
 *
 * Sin acciones de escritura: el modelo impide editar y borrar, y no tiene sentido
 * ofrecer un botón que revienta con una excepción.
 */
class ListAuditorias extends ListRecords
{
    protected static string $resource = AuditoriaResource::class;

    protected static ?string $title = 'Auditoría';

    protected function getHeaderActions(): array
    {
        return [];
    }
}
