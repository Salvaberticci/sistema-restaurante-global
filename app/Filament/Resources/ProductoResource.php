<?php

namespace App\Filament\Resources;

use App\Domain\Catalogos\Models\Categoria;
use App\Domain\Catalogos\Models\EstacionCocina;
use App\Domain\Catalogos\Models\Producto;
use App\Filament\Navegacion;
use App\Filament\Resources\ProductoResource\Pages;
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

class ProductoResource extends Resource
{
    protected static ?string $model = Producto::class;

    protected static ?string $modelLabel = 'producto';

    protected static ?string $pluralModelLabel = 'productos';

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationGroup = Navegacion::CATALOGOS;

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'productos';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Identificación')
                ->columns(2)
                ->schema([
                    TextInput::make('codigo')
                        ->label('Código')
                        ->required()
                        ->maxLength(30)
                        ->unique(ignoreRecord: true)
                        ->helperText('Ej: PRI-01, BEB-03. Sale impreso en el ticket.'),

                    TextInput::make('nombre')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(120),

                    Select::make('categoria_id')
                        ->label('Categoría')
                        ->relationship('categoria', 'nombre')
                        ->searchable()
                        ->preload()
                        ->required(),

                    Textarea::make('descripcion')
                        ->label('Descripción')
                        ->rows(2)
                        ->maxLength(500)
                        ->columnSpanFull()
                        ->helperText('Aparece en el menú digital y sirve de ayuda al mesero.'),
                ]),

            Section::make('Precio')
                ->description('El precio se fija en dólares. Es el precio de venta al público, con el impuesto incluido como sale en la carta.')
                ->columns(3)
                ->schema([
                    TextInput::make('precio_venta')
                        ->label('Precio de venta (USD)')
                        ->required()
                        ->numeric()
                        ->step(0.01)
                        ->prefix('$')
                        ->helperText('Decimal, nunca float.'),

                    TextInput::make('precio_costo')
                        ->label('Costo (USD)')
                        ->numeric()
                        ->step(0.01)
                        ->prefix('$')
                        ->helperText('Lo calcula el BOM. Vacío hasta que existan recetas.'),

                    Select::make('alicuota_codigo')
                        ->label('Alícuota')
                        ->options([
                            'IVA_G' => 'General',
                            'IVA_E' => 'Exento',
                        ])
                        ->default('IVA_G')
                        ->helperText('Se guarda el código, no el número. El valor sale de la tabla de vigencias.'),
                ]),

            Section::make('Operación')
                ->columns(2)
                ->schema([
                    Select::make('estacion_cocina_id')
                        ->label('Estación de cocina')
                        ->relationship('estacionCocina', 'nombre')
                        ->searchable()
                        ->preload()
                        ->helperText('Vacío = no pasa por cocina. Es lo que reparte las comandas.'),

                    Select::make('unidad_medida_id')
                        ->label('Unidad')
                        ->relationship('unidadMedida', 'nombre')
                        ->searchable()
                        ->preload(),

                    Toggle::make('requiere_preparacion')->label('Requiere preparación')->default(true),
                    Toggle::make('es_bebida')->label('Es bebida')->default(false),
                ]),

            Section::make('Visibilidad')
                ->columns(2)
                ->schema([
                    Toggle::make('disponible')
                        ->label('Disponible')
                        ->helperText('Desmarcarlo lo saca del POS sin borrarlo del historial.')
                        ->default(true),

                    Toggle::make('mostrar_menu_digital')
                        ->label('Aparece en el menú digital')
                        ->default(true),

                    TextInput::make('orden')->label('Orden')->numeric()->default(0),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('categoria_id')
            // `categoria` y `estacionCocina` se leen en varias columnas. Sin la
            // carga previa cada fila dispara una consulta aparte, y además el
            // modelo tiene el lazy loading prohibido.
            ->modifyQueryUsing(fn ($query) => $query->with(['categoria', 'estacionCocina']))
            ->columns([
                TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable()
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('nombre')
                    ->label('Producto')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Producto $record) => $record->categoria?->getAttribute('nombre')),

                TextColumn::make('precio_venta')
                    ->label('Precio')
                    ->money('USD')
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('margen')
                    ->label('Margen')
                    ->state(fn (Producto $record) => $record->margen())
                    ->formatStateUsing(fn (?string $state) => $state === null ? '—' : $state.' %')
                    ->color(fn (?string $state) => $state === null ? 'gray' : match (true) {
                        (float) $state >= 60 => 'success',
                        (float) $state >= 35 => 'warning',
                        default => 'danger',
                    })
                    ->tooltip('Margen sobre venta. Vacío mientras no haya costo cargado.')
                    ->sortable(query: fn ($query) => $query),

                TextColumn::make('estacionCocina.nombre')
                    ->label('Estación')
                    ->badge()
                    ->color('info')
                    ->placeholder('Sin cocina'),

                TextColumn::make('alicuota_codigo')
                    ->label('Alícuota')
                    ->formatStateUsing(fn (string $state) => $state === 'IVA_E' ? 'Exento' : 'General')
                    ->toggleable(),

                IconColumn::make('disponible')
                    ->label('Disponible')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('categoria_id')
                    ->label('Categoría')
                    ->relationship('categoria', 'nombre')
                    ->preload(),

                SelectFilter::make('estacion_cocina_id')
                    ->label('Estación')
                    ->relationship('estacionCocina', 'nombre')
                    ->preload(),

                TernaryFilter::make('disponible')->label('Solo disponibles')->default(true),
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
            'index' => Pages\ListProductos::route('/'),
            'create' => Pages\CreateProducto::route('/create'),
            'edit' => Pages\EditProducto::route('/{record}/edit'),
        ];
    }
}
