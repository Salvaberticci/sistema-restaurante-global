<?php

namespace App\Filament\Resources\ParametroResource\Pages;

use App\Filament\Resources\ParametroResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditParametro extends EditRecord
{
    protected static string $resource = ParametroResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
