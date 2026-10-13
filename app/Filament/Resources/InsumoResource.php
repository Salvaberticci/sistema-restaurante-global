<?php

namespace App\Filament\Resources;

use App\Domain\Catalogos\Models\Insumo;
use App\Filament\Navegacion;
use App\Filament\Resources\InsumoResource\Pages;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class InsumoResource extends Resource
{
    protected static ?string $model = Insumo::class;

    protected static ?string $modelLabel = 'insumo';

    protected static ?string $pluralModelLabel = 'insumos';

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = Navegacion::CATALOGOS;

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'insumos';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Identificación')
                ->columns(2)
                ->schema([
                    TextInput::make('codigo')->label('Código')->required()->maxLength(30)->unique(ignoreRecord: true),
                    TextInput::make('nombre')->label('Nombre')->required()->maxLength(120),
                    Select::make('unidad_medida_id')
                        ->label('Unidad')
                        ->relationship('unidadMedida', 'nombre')
                        ->searchable()->preload()
                        ->required(),
                    TextInput::make('proveedor')->label('Proveedor')->maxLength(120),
                    Textarea::make('descripcion')->label('Descripción')->rows(2)->maxLength(500)->columnSpanFull(),
                ]),

            Section::make('Costo')
                ->columns(3)
                ->schema([
                    TextInput::make('precio_compra')->label('Precio de compra')->numeric()->step(0.0001)->prefix('$'),
                    TextInput::make('merma_porcentaje')->label('Merma %')->numeric()->step(0.01)->default(5),
                    Select::make('alicuota_codigo')
                        ->label('Alícuota')
                        ->options(['IVA_G' => 'General', 'IVA_E' => 'Exento'])
                        ->default('IVA_G'),
                ]),

            Section::make('Existencia')
                ->description('El stock real lo recalcula el kardex a partir de las ventas. Acá va el punto de partida y el umbral de alerta.')
                ->columns(2)
                ->schema([
                    TextInput::make('stock_inicial')->label('Stock inicial')->numeric()->default(0),
                    TextInput::make('stock_minimo')->label('Stock mínimo')->numeric()->default(0)
                        ->helperText('Por debajo de este valor el sistema avisa.'),
                    Toggle::make('activo')->label('Activo')->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nombre')
            ->columns([
                TextColumn::make('codigo')->label('Código')->searchable()->badge()->color('gray'),
                TextColumn::make('nombre')->label('Insumo')->searchable()->sortable()->weight('bold'),
                TextColumn::make('unidadMedida.nombre')->label('Unidad')->badge()->color('gray'),
                TextColumn::make('precio_compra')->label('Compra')->money('USD')->sortable(),
                TextColumn::make('stock_inicial')
                    ->label('Stock')
                    ->sortable()
                    ->description(fn (Insumo $record) => 'Mínimo: '.$record->stock_minimo)
                    ->color(fn (Insumo $record) => $record->estaBajoMinimo() ? 'danger' : null)
                    ->icon(fn (Insumo $record) => $record->estaBajoMinimo() ? 'heroicon-m-exclamation-triangle' : null),
                TextColumn::make('proveedor')->label('Proveedor')->toggleable()->placeholder('—'),
                TextColumn::make('merma_porcentaje')->label('Merma')->suffix(' %')->toggleable(),
                IconColumn::make('activo')->label('Activo')->boolean(),
            ])
            ->filters([
                SelectFilter::make('unidad_medida_id')
                    ->label('Unidad')
                    ->relationship('unidadMedida', 'nombre')
                    ->preload(),
                TernaryFilter::make('activo')->label('Solo activos')->default(true),
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
            'index' => Pages\ListInsumos::route('/'),
            'create' => Pages\CreateInsumo::route('/create'),
            'edit' => Pages\EditInsumo::route('/{record}/edit'),
        ];
    }
}
