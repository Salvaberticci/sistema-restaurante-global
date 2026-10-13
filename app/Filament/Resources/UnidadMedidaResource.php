<?php

namespace App\Filament\Resources;

use App\Domain\Mesas\Models\UnidadMedida;
use App\Filament\Navegacion;
use App\Filament\Resources\UnidadMedidaResource\Pages;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UnidadMedidaResource extends Resource
{
    protected static ?string $model = UnidadMedida::class;

    protected static ?string $modelLabel = 'unidad de medida';

    protected static ?string $pluralModelLabel = 'unidades de medida';

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationGroup = Navegacion::CATALOGOS;

    protected static ?int $navigationSort = 70;

    protected static ?string $slug = 'unidades-medida';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Unidad')
                ->columns(2)
                ->schema([
                    TextInput::make('nombre')->label('Nombre')->required()->maxLength(40),
                    TextInput::make('codigo')->label('Código')->required()->maxLength(10)->unique(ignoreRecord: true)
                        ->helperText('Ej: KG, LT, UND.'),
                    TextInput::make('plural')->label('Plural')->maxLength(40)
                        ->helperText('Para "2 KG" y "1 KG".'),
                    TextInput::make('decimales')->label('Decimales')->numeric()->minValue(0)->maxValue(4)->default(2),
                    Toggle::make('permite_fraccion')->label('Admite fracciones')
                        ->helperText('El kilogramo sí, la unidad no.'),
                    Toggle::make('activo')->label('Activa')->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nombre')
            ->columns([
                TextColumn::make('nombre')->label('Unidad')->searchable()->sortable()->weight('bold'),
                TextColumn::make('codigo')->label('Código')->badge()->color('gray'),
                TextColumn::make('plural')->label('Plural')->placeholder('—'),
                TextColumn::make('decimales')->label('Decimales'),
                IconColumn::make('permite_fraccion')->label('Fracciones')->boolean(),
                IconColumn::make('activo')->label('Activa')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('activo')->label('Solo activas')->default(true),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Eliminar'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUnidadesMedida::route('/'),
            'create' => Pages\CreateUnidadMedida::route('/create'),
            'edit' => Pages\EditUnidadMedida::route('/{record}/edit'),
        ];
    }
}
