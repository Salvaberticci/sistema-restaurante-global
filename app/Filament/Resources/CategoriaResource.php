<?php

namespace App\Filament\Resources;

use App\Domain\Catalogos\Models\Categoria;
use App\Filament\Resources\CategoriaResource\Pages;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CategoriaResource extends Resource
{
    protected static ?string $model = Categoria::class;

    protected static ?string $modelLabel = 'categoría';

    protected static ?string $pluralModelLabel = 'categorías';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $slug = 'categorias';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Categoría')
                ->columns(2)
                ->schema([
                    TextInput::make('nombre')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(80)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state ?? ''))),

                    TextInput::make('slug')
                        ->label('Identificador')
                        ->required()
                        ->maxLength(80)
                        ->unique(ignoreRecord: true)
                        ->helperText('Se arma solo con el nombre. Se usa en el enlace del menú digital.'),

                    TextInput::make('orden')
                        ->label('Orden en la carta')
                        ->numeric()
                        ->default(0)
                        ->helperText('De menor a mayor. Define en qué orden salen en el menú.'),

                    TextInput::make('icono')
                        ->label('Ícono')
                        ->maxLength(60)
                        ->helperText('Nombre de Heroicon. Ej: heroicon-o-cake'),
                ]),

            Section::make('Presentación')
                ->columns(2)
                ->schema([
                    Textarea::make('descripcion')->label('Descripción')->rows(2)->maxLength(255)->columnSpanFull(),

                    Toggle::make('mostrar_menu_digital')
                        ->label('Aparece en el menú digital')
                        ->helperText('Se apaga para categorías internas como "Combos".')
                        ->default(true),

                    Toggle::make('activo')
                        ->label('Activa')
                        ->helperText('Una categoría ya vendida no se borra: se desactiva.')
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('orden')->label('#')->sortable()->width(50),
                TextColumn::make('nombre')->label('Categoría')->searchable()->sortable()->weight('bold'),
                TextColumn::make('productos_count')
                    ->label('Productos')
                    ->counts('productos')
                    ->badge()
                    ->color('gray'),
                IconColumn::make('mostrar_menu_digital')->label('Menú digital')->boolean(),
                IconColumn::make('activo')->label('Activa')->boolean(),
            ])
            ->defaultSort('orden')
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
            'index' => Pages\ListCategorias::route('/'),
            'create' => Pages\CreateCategoria::route('/create'),
            'edit' => Pages\EditCategoria::route('/{record}/edit'),
        ];
    }
}
