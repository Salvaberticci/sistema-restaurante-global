<?php

namespace App\Filament\Resources;

use App\Domain\Iam\Permisos;
use App\Domain\Reservas\Enums\EstadoPago;
use App\Domain\Reservas\Enums\EstadoReserva;
use App\Domain\Reservas\Models\Reservacion;
use App\Domain\Reservas\Services\ConfirmaReserva;
use App\Filament\Navegacion;
use App\Filament\Resources\ReservacionResource\Pages;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Eventos y cursos.
 *
 * Lo que hace distinto a esta pantalla de las demás es la regla del pago: la
 * acción de confirmar está **habilitada solo si la reserva está pagada**, y
 * cuando alguien la pulsa sin haber pagado, el error explica por qué.
 *
 * Es deliberado que el botón se vea y se pueda pulsar. Ocultarlo deja al usuario
 * buscando la pantalla equivocada; mostrarlo deshabilitado con el motivo al lado
 * le responde la pregunta en el sitio donde se la hace.
 *
 * El CHECK de la tabla es la tercera capa: aunque alguien se salte el formulario,
 * la base no guarda una reserva confirmada sin el pago completo (invariante I8).
 */
class ReservacionResource extends Resource
{
    protected static ?string $model = Reservacion::class;

    protected static ?string $modelLabel = 'reserva';

    protected static ?string $pluralModelLabel = 'reservaciones';

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = Navegacion::OPERACION;

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'reservaciones';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permisos::RESERVAS_VER) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can(Permisos::RESERVAS_EDITAR) ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can(Permisos::RESERVAS_EDITAR) ?? false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Qué se reserva')
                ->columns(2)
                ->schema([
                    Select::make('tipo')
                        ->label('Tipo')
                        ->options(['evento' => 'Evento', 'curso' => 'Curso'])
                        ->default('evento')
                        ->live()
                        ->required()
                        ->helperText('Un curso tiene cupo y se paga completo; un evento se cobra por persona.'),

                    TextInput::make('nombre')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(120),

                    DatePicker::make('fecha_inicio')
                        ->label('Fecha de inicio')
                        ->required()
                        ->native(false),

                    DatePicker::make('fecha_fin')
                        ->label('Fecha de fin')
                        ->native(false)
                        ->afterOrEqual('fecha_inicio')
                        ->helperText('Vacío si es un solo día.'),

                    TextInput::make('hora_inicio')
                        ->label('Hora')
                        ->type('time')
                        ->default('18:00')
                        ->required(),

                    Textarea::make('descripcion')
                        ->label('Descripción')
                        ->rows(2)
                        ->maxLength(500)
                        ->columnSpanFull(),
                ]),

            Section::make('Cupo y precio')
                ->columns(2)
                ->schema([
                    TextInput::make('personas')
                        ->label('Personas')
                        ->numeric()
                        ->minValue(1)
                        ->default(1)
                        ->required(),

                    TextInput::make('cupo_maximo')
                        ->label('Cupo máximo')
                        ->numeric()
                        ->minValue(1)
                        ->helperText('Vacío = sin límite. Solo para cursos.'),

                    TextInput::make('precio_unitario')
                        ->label('Precio unitario (USD)')
                        ->numeric()
                        ->step(0.01)
                        ->prefix('$')
                        ->required(),

                    TextInput::make('total')
                        ->label('Total (USD)')
                        ->disabled()
                        ->dehydrated(false)
                        ->helperText('Se calcula como precio por personas. No se escribe a mano.')
                        ->formatStateUsing(fn (?string $state) => $state === null ? '' : number_format((float) $state, 2)),
                ]),

            Section::make('Cliente y pago')
                ->description('Eventos y cursos se pagan 100% por adelantado (invariante I8).')
                ->columns(2)
                ->schema([
                    TextInput::make('cliente_nombre')
                        ->label('Cliente')
                        ->required()
                        ->maxLength(120),

                    TextInput::make('cliente_documento')
                        ->label('Cédula')
                        ->maxLength(30),

                    TextInput::make('cliente_telefono')
                        ->label('Teléfono')
                        ->tel()
                        ->maxLength(30),

                    TextInput::make('pagado')
                        ->label('Abonado (USD)')
                        ->numeric()
                        ->step(0.01)
                        ->prefix('$')
                        ->default(0)
                        ->required()
                        ->helperText('Mientras sea menor que el total, la reserva no se puede confirmar.'),

                    Toggle::make('confirmada')
                        ->label('Confirmar')
                        ->helperText('Solo se habilita si el pago está completo.')
                        ->dehydrateStateUsing(fn (?bool $state, array $data) => $state ? 'confirmada' : 'solicitada'),

                    Textarea::make('observaciones')
                        ->label('Observaciones')
                        ->rows(2)
                        ->maxLength(500)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('fecha_inicio', 'asc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('usuario:id,name'))
            ->columns([
                TextColumn::make('fecha_inicio')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable()
                    ->description(fn (Reservacion $registro) => $registro->hora_inicio),

                TextColumn::make('nombre')
                    ->label('Reserva')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (Reservacion $registro) => $registro->cliente_nombre),

                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'curso' ? 'Curso' : 'Evento')
                    ->color(fn (string $state) => $state === 'curso' ? 'info' : 'purple'),

                TextColumn::make('personas')
                    ->label('Personas')
                    ->alignEnd()
                    ->description(fn (Reservacion $registro) => $registro->cupo_maximo === null
                        ? null
                        : $registro->quedanCupos().' cupos libres'),

                TextColumn::make('total')
                    ->label('Total')
                    ->money('USD')
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('avancePago')
                    ->label('Pago')
                    ->state(fn (Reservacion $registro) => $registro->avancePago())
                    ->formatStateUsing(fn (int $state) => $state.'%')
                    ->alignEnd()
                    ->badge()
                    ->color(fn (Reservacion $registro) => $registro->estado_pago->color())
                    ->description(fn (Reservacion $registro) => $registro->estado_pago->etiqueta()),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof EstadoReserva ? $state->etiqueta() : (string) $state)
                    ->color(fn ($state) => $state instanceof EstadoReserva ? $state->color() : 'gray'),
            ])
            ->filters([
                SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options(['evento' => 'Evento', 'curso' => 'Curso']),

                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options([
                        'solicitada' => 'Solicitada',
                        'confirmada' => 'Confirmada',
                        'realizada' => 'Realizada',
                        'cancelada' => 'Cancelada',
                    ]),

                SelectFilter::make('estado_pago')
                    ->label('Pago')
                    ->options(collect(EstadoPago::cases())
                        ->mapWithKeys(fn (EstadoPago $estado) => [$estado->value => $estado->etiqueta()])
                        ->all()),
            ])
            ->actions([
                Action::make('confirmar')
                    ->label('Confirmar')
                    ->icon('heroicon-m-check-badge')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar la reserva')
                    ->modalDescription('Eventos y cursos solo se confirman con el pago completo.')
                    // El botón se ve siempre y se explica cuando no se puede.
                    ->disabled(fn (Reservacion $registro) => $registro->estado === EstadoReserva::Confirmada)
                    ->visible(fn (Reservacion $registro) => $registro->estado === EstadoReserva::Solicitada)
                    ->tooltip(fn (Reservacion $registro) => $registro->estaPagada()
                        ? null
                        : 'Falta el pago completo: va '.$registro->avancePago().'%')
                    ->action(function (Reservacion $registro) {
                        app(ConfirmaReserva::class)->ejecutar($registro);
                    })
                    ->successRedirectUrl(static fn (Reservacion $registro) => ReservacionResource::getUrl('index')),

                EditAction::make()
                    ->visible(fn (Reservacion $registro) => in_array(
                        $registro->estado,
                        [EstadoReserva::Solicitada, EstadoReserva::Confirmada],
                        true,
                    )),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReservaciones::route('/'),
            'create' => Pages\CreateReservacion::route('/create'),
            'edit' => Pages\EditReservacion::route('/{record}/edit'),
        ];
    }
}
