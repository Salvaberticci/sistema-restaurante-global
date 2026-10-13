<?php

namespace App\Filament\Resources;

use App\Domain\Configuracion\Models\Parametro;
use App\Filament\Navegacion;
use App\Filament\Resources\ParametroResource\Pages;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;

/**
 * Los parámetros del sistema, con vigencia.
 *
 * Es la pantalla más importante del backoffice y la menos fancy: acá viven las
 * alícuotas de IVA, los porcentajes de IGTF por modalidad de pago y las políticas
 * comerciales. Ninguna de esas tasas está escrita en el código.
 *
 * Editar uno de acá cambia lo que el sistema calcula. Por eso cada fila muestra
 * su descripción y por qué se toca.
 */
class ParametroResource extends Resource
{
    protected static ?string $model = Parametro::class;

    protected static ?string $modelLabel = 'parámetro';

    protected static ?string $pluralModelLabel = 'parámetros';

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = Navegacion::ADMINISTRACION;

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'parametros';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Parámetro')
                ->columns(2)
                ->schema([
                    TextInput::make('clave')
                        ->label('Clave')
                        ->disabled()
                        ->helperText('Identificador interno. No se puede cambiar: el código la usa para consultarla.'),

                    TextInput::make('grupo')
                        ->label('Grupo')
                        ->disabled(),

                    Textarea::make('descripcion')
                        ->label('Qué es y cuándo se toca')
                        ->disabled()
                        ->columnSpanFull()
                        ->rows(3),

                    Textarea::make('valor')
                        ->label('Valor (JSON)')
                        ->required()
                        ->rows(14)
                        ->columnSpanFull()
                        ->helperText('Formato JSON. Ej: {"codigo":"IVA_G","alicuota":"16"}. Cambiar esto cambia lo que el sistema calcula.')
                        ->rules([
                            function () {
                                return function (string $attribute, mixed $value, \Closure $fail) {
                                    json_decode((string) $value);

                                    if (json_last_error() !== JSON_ERROR_NONE) {
                                        $fail('El valor no es un JSON válido: '.json_last_error_msg());
                                    }
                                };
                            },
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('grupo')
            ->columns([
                TextColumn::make('clave')
                    ->label('Clave')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Parametro $record) => $record->descripcion),

                TextColumn::make('grupo')
                    ->label('Grupo')
                    ->badge()
                    ->color(fn (Parametro $record) => match ($record->grupo) {
                        'fiscal' => 'warning',
                        'comercial' => 'success',
                        'operacion' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('valor')
                    ->label('Valor')
                    ->state(fn (Parametro $record) => self::resumir($record))
                    ->badge()
                    ->color(fn (Parametro $record) => self::tienePendientes($record) ? 'danger' : 'gray')
                    ->tooltip(fn (Parametro $record) => self::resumir($record, completo: true)),

                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->since()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Filter::make('busqueda')
                    ->label('Buscar')
                    ->form([
                        TextInput::make('q')
                            ->label('Clave o descripción')
                            ->placeholder('Ej: igtf'),
                    ])
                    ->query(function ($query, array $data) {
                        $query->when($data['q'] ?? null, function ($query, $q) {
                            $query->where(function ($query) use ($q) {
                                $query->where('clave', 'like', "%{$q}%")
                                    ->orWhere('descripcion', 'like', "%{$q}%");
                            });
                        });
                    }),

                Filter::make('grupo')
                    ->label('Grupo')
                    ->form([
                        Select::make('g')
                            ->label('Grupo')
                            ->options([
                                'fiscal' => 'Fiscal',
                                'comercial' => 'Comercial',
                                'operacion' => 'Operación',
                                'general' => 'General',
                            ])
                            ->placeholder('Todos'),
                    ])
                    ->query(function ($query, array $data) {
                        $query->when($data['g'] ?? null, fn ($query, $g) => $query->where('grupo', $g));
                    }),
            ])
            ->actions([
                EditAction::make()->label('Editar'),
            ]);
    }

    /**
     * Resumen legible de un parámetro estructurado.
     *
     * Una lista de alícuotas en JSON crudo es ilegible en una columna de tabla.
     * Se aplana a "IVA_G 16 % | IVA_E 0 %" para que de un vistazo se vea lo que
     * el sistema esta aplicando.
     */
    private static function resumir(Parametro $parametro, bool $completo = false): string
    {
        $valor = $parametro->valor ?? [];

        if ($valor === []) {
            return '—';
        }

        $partes = [];

        foreach ($valor as $clave => $item) {
            if (! is_array($item)) {
                $partes[] = "$clave: ".(is_bool($item) ? ($item ? 'sí' : 'no') : (string) $item);

                continue;
            }

            $etiqueta = $item['codigo'] ?? $item['nivel'] ?? $item['canal'] ?? (string) $clave;
            $cantidad = $item['alicuota'] ?? $item['porcentaje'] ?? $item['minutos'] ?? null;
            $partes[] = $cantidad === null ? $etiqueta : "$etiqueta $cantidad";
        }

        $texto = implode(' · ', $partes);

        return $completo ? $texto : mb_strimwidth($texto, 0, 70, '…');
    }

    /**
     * Marca en rojo los parámetros que el demo trae como ejemplo y que hay que
     * confirmar con un contador. Ver ParametrosSeeder.
     */
    private static function tienePendientes(Parametro $parametro): bool
    {
        $valor = $parametro->valor ?? [];

        if (array_key_exists('confirmado', $valor)) {
            return $valor['confirmado'] !== true;
        }

        foreach ($valor as $item) {
            if (is_array($item) && array_key_exists('confirmado', $item)) {
                return $item['confirmado'] !== true;
            }
        }

        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListParametros::route('/'),
            'edit' => Pages\EditParametro::route('/{record}/edit'),
        ];
    }
}
