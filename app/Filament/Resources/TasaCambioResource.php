<?php

namespace App\Filament\Resources;

use App\Domain\Iam\Permisos;
use App\Domain\TasaCambio\Models\TasaCambio;
use App\Filament\Navegacion;
use App\Filament\Resources\TasaCambioResource\Pages;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Historial de tasas.
 *
 * Recurso de **solo lectura**. Una tasa pasada no se corrige: se deja como
 * estaba y se agrega la nueva. Cambiar la tasa de un día ya emitido cambiaría el
 * significado de las cuentas de ese día, y eso es exactamente lo que una tasa
 * congelada evita.
 *
 * Cargar la tasa del día sí es una operación del sistema (viene del BCV o la
 * escribe alguien), y por eso vive aquí como historial y no como un parámetro
 * editable: lo que se carga es un hecho fechado.
 */
class TasaCambioResource extends Resource
{
    protected static ?string $model = TasaCambio::class;

    protected static ?string $modelLabel = 'tasa';

    protected static ?string $pluralModelLabel = 'tasas de cambio';

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = Navegacion::FINANZAS;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'tasas-cambio';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permisos::VENTAS_VER) ?? false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('vigente_desde', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('usuario:id,name'))
            ->columns([
                TextColumn::make('vigente_desde')
                    ->label('Desde')
                    ->date('d/m/Y')
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('vigente_hasta')
                    ->label('Hasta')
                    ->date('d/m/Y')
                    ->placeholder('Vigente')
                    ->color('gray'),

                TextColumn::make('usd')
                    ->label('Bs por USD')
                    ->formatStateUsing(fn (string $state) => number_format((float) $state, 2))
                    ->sortable()
                    ->weight('bold')
                    ->color('info'),

                TextColumn::make('eur')
                    ->label('Bs por EUR')
                    ->formatStateUsing(fn (?string $state) => $state === null ? '—' : number_format((float) $state, 2))
                    ->sortable()
                    ->color('gray'),

                TextColumn::make('fuente')
                    ->label('Fuente')
                    ->badge()
                    ->color('gray'),

                IconColumn::make('confirmada')
                    ->label('Confirmada')
                    ->boolean()
                    ->tooltip('Una tasa sin confirmar se muestra pero no debería usarse para emitir.'),

                TextColumn::make('usuario.name')
                    ->label('Cargada por')
                    ->placeholder('BCV automática')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Registrada')
                    ->date('d/m/Y H:i')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('vigente_desde')
                    ->label('Fecha')
                    ->form([
                        DatePicker::make('desde')->label('Desde'),
                        DatePicker::make('hasta')->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['desde'] ?? null, fn (Builder $q, $f) => $q->whereDate('vigente_desde', '>=', $f))
                            ->when($data['hasta'] ?? null, fn (Builder $q, $f) => $q->whereDate('vigente_desde', '<=', $f));
                    }),

                Filter::make('confirmada')
                    ->label('Solo confirmadas')
                    ->query(fn (Builder $query) => $query->where('confirmada', true)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTasasCambio::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
