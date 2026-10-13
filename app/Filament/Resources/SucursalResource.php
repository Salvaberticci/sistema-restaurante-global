<?php

namespace App\Filament\Resources;

use App\Domain\Mesas\Models\Sucursal;
use App\Filament\Resources\SucursalResource\Pages;
use App\Filament\Resources\SucursalResource\Schemas\SucursalForm;
use App\Filament\Resources\SucursalResource\Tables\SucursalesTable;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class SucursalResource extends Resource
{
    protected static ?string $model = Sucursal::class;

    protected static ?string $modelLabel = 'sucursal';

    protected static ?string $pluralModelLabel = 'sucursales';

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $slug = 'sucursales';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return SucursalForm::configure($form);
    }

    public static function table(Table $table): Table
    {
        return SucursalesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSucursales::route('/'),
        ];
    }
}
