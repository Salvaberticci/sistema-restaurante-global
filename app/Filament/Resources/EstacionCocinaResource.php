<?php

namespace App\Filament\Resources;

use App\Domain\Catalogos\Models\EstacionCocina;
use App\Filament\Navegacion;
use App\Filament\Resources\EstacionCocinaResource\Pages;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
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

class EstacionCocinaResource extends Resource
{
    protected static ?string $model = EstacionCocina::class;

    protected static ?string $modelLabel = 'estación de cocina';

    protected static ?string $pluralModelLabel = 'estaciones de cocina';

    protected static ?string $navigationIcon = 'heroicon-o-fire';

    protected static ?string $navigationGroup = Navegacion::CATALOGOS;

    protected static ?int $navigationSort = 60;

    protected static ?string $slug = 'estaciones-cocina';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Identificación')
                ->columns(2)
                ->schema([
                    TextInput::make('nombre')->label('Nombre')->required()->maxLength(80),
                    TextInput::make('codigo')->label('Código')->required()->maxLength(30)->unique(ignoreRecord: true),
                    Textarea::make('descripcion')->label('Descripción')->rows(2)->maxLength(255)->columnSpanFull(),
                    TextInput::make('icono')->label('Ícono')->maxLength(60),
                    TextInput::make('orden')->label('Orden')->numeric()->default(0),
                ]),

            Section::make('Semáforo de la pantalla de cocina')
                ->description('Una orden se parte en N comandas, una por estación. Estos minutos deciden cuándo el ticket pasa de verde a ámbar y de ámbar a rojo.')
                ->columns(2)
                ->schema([
                    TextInput::make('tiempo_verde_min')
                        ->label('Verde hasta (minutos)')
                        ->numeric()
                        ->minValue(1)
                        ->default(15),

                    TextInput::make('tiempo_ambar_min')
                        ->label('Ámbar hasta (minutos)')
                        ->numeric()
                        ->minValue(2)
                        ->gt('tiempo_verde_min')
                        ->default(20)
                        ->helperText('A partir de aquí el ticket se pone rojo.'),

                    Toggle::make('activo')->label('Activa')->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('orden')
            ->columns([
                TextColumn::make('orden')->label('#')->sortable()->width(45),
                TextColumn::make('nombre')->label('Estación')->searchable()->sortable()->weight('bold'),
                TextColumn::make('codigo')->label('Código')->badge()->color('gray'),
                TextColumn::make('productos_count')->label('Productos')->counts('productos')->badge()->color('info'),
                TextColumn::make('tiempo_verde_min')->label('Verde')->suffix(' min'),
                TextColumn::make('tiempo_ambar_min')->label('Rojo desde')->suffix(' min'),
                TextColumn::make('descripcion')->label('Descripción')->limit(40)->toggleable(),
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
            'index' => Pages\ListEstacionesCocina::route('/'),
            'create' => Pages\CreateEstacionCocina::route('/create'),
            'edit' => Pages\EditEstacionCocina::route('/{record}/edit'),
        ];
    }
}
