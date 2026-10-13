<?php

namespace App\Filament\Resources;

use App\Domain\Iam\Permisos;
use App\Domain\Ordenes\Enums\Canal;
use App\Domain\Ordenes\Enums\EstadoOrden;
use App\Domain\Ordenes\Models\Orden;
use App\Domain\Ventas\Services\IndicadoresVentas;
use App\Filament\Navegacion;
use App\Filament\Resources\OrdenResource\Pages;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Ventas: la cuenta cerrada y cobrada.
 *
 * Es un recurso **de solo lectura**. No hay `create`, no hay `edit` y no hay
 * `delete`, y no es una omisiÃ³n por pereza: una cuenta cobrada es la evidencia de
 * lo que se le cobrÃ³ a alguien. Si algo estÃ¡ mal, la correcciÃ³n no es reescribir
 * el nÃºmero, es emitir una nota de crÃ©dito que lo referencia (invariante I3).
 *
 * Por eso los importes de la tabla son los que se guardaron al cobrar, no los
 * que dice el catÃ¡logo hoy. Si maÃ±ana suben el pabellÃ³n, la venta de ayer sigue
 * mostrando lo que se pagÃ³ ayer, y eso es lo que un cliente dispute.
 *
 * La pantalla de esta lista lleva encima los grÃ¡ficos del perÃ­odo. Que la tabla
 * y las grÃ¡ficas tengan filtros independientes es deliberado: el filtro de fecha
 * de la tabla es de auditorÃ­a ("muÃ©strame las cuentas del martes"), el de las
 * grÃ¡ficas es de anÃ¡lisis ("muÃ©strame el mes"). Los dos leen del mismo
 * `IndicadoresVentas`, asÃ­ que cuando se cruzan no se contradicen.
 */
class OrdenResource extends Resource
{
    protected static ?string $model = Orden::class;

    protected static ?string $modelLabel = 'venta';

    protected static ?string $pluralModelLabel = 'ventas';

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationGroup = Navegacion::GESTION;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'ventas';

    /**
     * Los roles de operaciÃ³n pueden ver las cuentas; los de catÃ¡logo no.
     * El permiso sale de `Permisos`, que es donde vive la matriz de roles.
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permisos::VENTAS_VER) ?? false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('abierta_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['mesa:id,nombre', 'mesero:id,name', 'cajero:id,name']))
            ->columns([
                TextColumn::make('numero')
                    ->label('Cuenta')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->fontFamily('mono'),

                TextColumn::make('abierta_at')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->time('H:i')
                    ->sortable(),

                TextColumn::make('canal')
                    ->label('Canal')
                    ->badge()
                    ->formatStateUsing(fn ($state) => self::etiqueta($state, Canal::class))
                    ->color(fn ($state) => self::color($state, Canal::class)),

                TextColumn::make('mesa.nombre')
                    ->label('Mesa')
                    ->placeholder('â€”')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('mesero.name')
                    ->label('Mesero')
                    ->placeholder('â€”')
                    ->toggleable(),

                TextColumn::make('comensales')
                    ->label('Comensales')
                    ->alignEnd()
                    ->placeholder('â€”')
                    ->toggleable(),

                TextColumn::make('items_count')
                    ->label('Ãtems')
                    ->alignEnd()
                    ->counts('items')
                    ->sortable(query: fn (Builder $q) => $q->withCount('items'))
                    ->color('gray'),

                TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->money('USD')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('descuentos')
                    ->label('Descuento')
                    ->money('USD')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color(fn (Orden $registro) => (float) $registro->descuentos > 0 ? 'danger' : null),

                TextColumn::make('propina')
                    ->label('Propina')
                    ->money('USD')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('total')
                    ->label('Total')
                    ->money('USD')
                    ->sortable()
                    ->weight('bold')
                    ->color('warning'),

                TextColumn::make('metodo_pago')
                    ->label('MÃ©todo')
                    ->formatStateUsing(fn ($state) => IndicadoresVentas::METODOS[(string) $state] ?? (string) $state)
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn ($state) => self::etiqueta($state, EstadoOrden::class))
                    ->color(fn ($state) => self::color($state, EstadoOrden::class)),
            ])
            ->filters([
                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options(collect(EstadoOrden::cases())
                        ->mapWithKeys(fn (EstadoOrden $estado) => [$estado->value => $estado->etiqueta()])
                        ->all())
                    ->default('cobrada'),

                SelectFilter::make('canal')
                    ->label('Canal')
                    ->options(collect(Canal::cases())
                        ->mapWithKeys(fn (Canal $canal) => [$canal->value => $canal->etiqueta()])
                        ->all()),

                SelectFilter::make('metodo_pago')
                    ->label('MÃ©todo de pago')
                    ->options(IndicadoresVentas::METODOS),

                SelectFilter::make('mesero_id')
                    ->label('Mesero')
                    ->relationship('mesero', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('abierta_at')
                    ->label('Fecha de la cuenta')
                    ->form([
                        DatePicker::make('desde')->label('Desde'),
                        DatePicker::make('hasta')->label('Hasta'),
                    ])
                    // Filament inyecta los valores del formulario por el nombre
                    // del parÃ¡metro: si se llama distinto, no lo encuentra y la
                    // pantalla revienta al pintar el filtro.
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['desde'] ?? null,
                                fn (Builder $q, $fecha) => $q->whereDate('abierta_at', '>=', $fecha)
                            )
                            ->when(
                                $data['hasta'] ?? null,
                                fn (Builder $q, $fecha) => $q->whereDate('abierta_at', '<=', $fecha)
                            );
                    })
                    ->indicateUsing(fn (array $data): array => array_values(array_filter([
                        isset($data['desde']) ? 'Desde '.Carbon::parse($data['desde'])->format('d/m/Y') : null,
                        isset($data['hasta']) ? 'Hasta '.Carbon::parse($data['hasta'])->format('d/m/Y') : null,
                    ]))),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrdenes::route('/'),
            'view' => Pages\ViewOrden::route('/{record}'),
        ];
    }

    /**
     * Los registros estÃ¡n bloqueados: no hay acciones de escritura en la tabla,
     * pero un usuario con permiso puede abrir la URL de ediciÃ³n a mano.
     */
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

    /** La autorizaciÃ³n se hace sobre el recurso, no sobre la polÃ­tica de Filament. */
    public static function canView($record): bool
    {
        return self::canViewAny();
    }

    /**
     * * Etiqueta y color de una columna cuyo estado viene de un enum.
     *
     * Dos trampas de Filament en una lÃ­nea, y las dos muerden:
     *
     * 1. No se puede tipar el enum en el closure (`fn (Canal $c) => ...`).
     *    Filament resuelve los parÃ¡metros por tipo contra el contenedor, y un
     *    enum no se puede instanciar: la pantalla revienta con
     *    "Target [Canal] is not instantiable".
     * 2. El nombre del parÃ¡metro tiene que ser `$state`. Un `$codigo` cualquiera
     *    no se resuelve y el error es "unresolvable".
     *
     * Y un detalle del modelo: `canal` y `estado` tienen cast a enum, asÃ­ que lo
     * que llega ya es el enum, no el string. Los helpers aceptan las dos formas
     * para no depender de que el cast siga puesto maÃ±ana.
     */
    private static function etiqueta(mixed $state, string $enum): string
    {
        $caso = $state instanceof $enum ? $state : ($enum::tryFrom((string) $state));

        return $caso?->etiqueta() ?? 'â€”';
    }

    /** @param class-string<Canal|EstadoOrden> $enum */
    private static function color(mixed $state, string $enum): string
    {
        $caso = $state instanceof $enum ? $state : ($enum::tryFrom((string) $state));

        return $caso?->color() ?? 'gray';
    }
}
