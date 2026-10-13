<?php

namespace App\Filament\Resources\EstacionCocinaResource\Pages;

use App\Filament\Resources\EstacionCocinaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEstacionesCocina extends ListRecords
{
    protected static string $resource = EstacionCocinaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
