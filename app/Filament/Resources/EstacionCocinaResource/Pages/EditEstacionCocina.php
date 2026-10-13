<?php

namespace App\Filament\Resources\EstacionCocinaResource\Pages;

use App\Filament\Resources\EstacionCocinaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEstacionCocina extends EditRecord
{
    protected static string $resource = EstacionCocinaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
