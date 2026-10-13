<?php

namespace App\Filament\Resources;

use App\Domain\Catalogos\Models\MetodoPago;
use App\Filament\Navegacion;
use App\Filament\Resources\MetodoPagoResource\Pages;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
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

class MetodoPagoResource extends Resource
{
    protected static ?string $model = MetodoPago::class;

    protected static ?string $modelLabel = 'método de pago';

    protected static ?string $pluralModelLabel = 'métodos de pago';

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = Navegacion::CATALOGOS;

    protected static ?int $navigationSort = 50;

    protected static ?string $slug = 'metodos-pago';

    /**
     * Perfiles de IGTF disponibles. Los valores salen de la tabla de vigencias
     * (parámetro `impuestos.igtf`), no de este archivo.
     *
     * @return array<string, string>
     */
    private static function perfilesIgtf(): array
    {
        return [
            'IGTF_EFECTIVO' => 'IGTF efectivo en divisa',
            'IGTF_TRANSFERENCIA' => 'IGTF transferencia en divisa',
            'IGTF_PUNTO_VENTA' => 'IGTF punto de venta en divisa',
            '' => 'No genera IGTF',
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Identificación')
                ->columns(2)
                ->schema([
                    TextInput::make('nombre')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(80)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, ?string $state) => $set('codigo', Str::snake($state ?? '', '_'))),

                    TextInput::make('codigo')
                        ->label('Código')
                        ->required()
                        ->maxLength(30)
                        ->unique(ignoreRecord: true),

                    TextInput::make('orden')->label('Orden en el POS')->numeric()->default(0),
                    TextInput::make('icono')->label('Ícono')->maxLength(60),
                ]),

            Section::make('Comportamiento')
                ->description('Estas cuatro casillas definen cómo se comporta el método en el punto de venta. Mal configuradas, el IGTF se calcula sobre la base equivocada.')
                ->columns(2)
                ->schema([
                    Toggle::make('es_efectivo')
                        ->label('Es efectivo')
                        ->helperText('Entra en el arqueo de caja.'),

                    Toggle::make('es_divisa')
                        ->label('Es en divisas')
                        ->helperText('Es lo que separa "pago en bolívares" de "pago en dólares".'),

                    Toggle::make('requiere_referencia')
                        ->label('Exige referencia')
                        ->helperText('Número de operación, trace ID. Sin esto no se concilia con el banco.'),

                    Toggle::make('genera_igtf')
                        ->label('Genera IGTF')
                        ->live()
                        ->helperText('Cada modalidad de pago en divisa tiene su propia alícuota.')
                        ->afterStateUpdated(fn (Set $set, bool $state) => $set(
                            'igtf_perfil_codigo',
                            $state ? 'IGTF_EFECTIVO' : null,
                        )),

                    Select::make('igtf_perfil_codigo')
                        ->label('Perfil de IGTF')
                        ->options(self::perfilesIgtf())
                        ->visible(fn (Get $get) => (bool) $get('genera_igtf'))
                        ->helperText('Vinculado al parámetro impuestos.igtf.'),

                    Toggle::make('activo')->label('Activo')->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('orden')
            ->columns([
                TextColumn::make('orden')->label('#')->sortable()->width(45),
                TextColumn::make('nombre')->label('Método')->searchable()->sortable()->weight('bold'),
                TextColumn::make('codigo')->label('Código')->badge()->color('gray')->toggleable(),
                IconColumn::make('es_efectivo')->label('Efectivo')->boolean(),
                IconColumn::make('es_divisa')->label('Divisa')->boolean(),
                IconColumn::make('requiere_referencia')->label('Pide ref.')->boolean(),
                TextColumn::make('igtf_perfil_codigo')
                    ->label('IGTF')
                    ->formatStateUsing(fn (?string $state) => $state ? str_replace('IGTF_', '', $state) : 'No aplica')
                    ->badge()
                    ->color(fn (?string $state) => $state ? 'warning' : 'gray'),
                IconColumn::make('activo')->label('Activo')->boolean(),
            ])
            ->filters([
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
            'index' => Pages\ListMetodosPago::route('/'),
            'create' => Pages\CreateMetodoPago::route('/create'),
            'edit' => Pages\EditMetodoPago::route('/{record}/edit'),
        ];
    }
}
