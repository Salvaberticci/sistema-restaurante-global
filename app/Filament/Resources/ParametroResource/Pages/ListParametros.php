<?php

namespace App\Filament\Resources\ParametroResource\Pages;

use App\Filament\Resources\ParametroResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListParametros extends ListRecords
{
    protected static string $resource = ParametroResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
