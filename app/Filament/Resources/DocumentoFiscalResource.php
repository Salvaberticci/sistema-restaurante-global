<?php

namespace App\Filament\Resources;

use App\Domain\Facturacion\Models\DocumentoFiscal;
use App\Domain\Iam\Permisos;
use App\Filament\Navegacion;
use App\Filament\Resources\DocumentoFiscalResource\Pages;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Documentos fiscales: facturas y notas de crédito.
 *
 * Recurso **de solo lectura**, por invariante I3. Ni una celda se edita: un
 * documento fiscal es un hecho ya ocurrido y lo que lo corrige es otro
 * documento que lo referencia, no la reescritura del anterior. Por eso aquí no
 * hay `create`, ni `edit`, ni `delete`, ni siquiera de forma oculta: el modelo
 * bloquea el `update` por su cuenta.
 *
 * La anulación tampoco es un botón que "borra" la factura. El documento aparece
 * con su número, con el motivo y con el documento hijo que lo corrigió. Un dueño
 * que necesita explicar una factura anulada necesita ver las dos.
 */
class DocumentoFiscalResource extends Resource
{
    protected static ?string $model = DocumentoFiscal::class;

    protected static ?string $modelLabel = 'documento fiscal';

    protected static ?string $pluralModelLabel = 'documentos fiscales';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = Navegacion::FINANZAS;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'documentos-fiscales';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permisos::FACTURACION_VER) ?? false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('fecha_fiscal', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['usuario:id,name', 'orden:id,numero', 'padre:id,numero_fiscal']))
            ->columns([
                TextColumn::make('numero_fiscal')
                    ->label('Número fiscal')
                    ->searchable()
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->placeholder('Sin número')
                    ->description(fn (DocumentoFiscal $registro) => 'Serie '.$registro->serie.' · correlativo '.($registro->numero ?? '—')),

                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => self::etiquetaTipo($state))
                    ->color(fn (?string $state) => $state === 'nota_credito' ? 'warning' : 'info'),

                TextColumn::make('fecha_fiscal')
                    ->label('Fecha fiscal')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => self::etiquetaEstado($state))
                    ->color(fn (?string $state) => self::colorEstado($state)),

                TextColumn::make('orden.numero')
                    ->label('Cuenta')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('usuario.name')
                    ->label('Emitido por')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('base_imponible')
                    ->label('Base imponible')
                    ->money('USD')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('total_impuestos')
                    ->label('IVA')
                    ->money('USD')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('total_igtf')
                    ->label('IGTF')
                    ->money('USD')
                    ->sortable()
                    ->color(fn (DocumentoFiscal $registro) => (float) $registro->total_igtf > 0 ? 'danger' : 'gray')
                    ->tooltip('Solo se genera en la porción cobrada en divisa.'),

                TextColumn::make('total')
                    ->label('Total')
                    ->money('USD')
                    ->sortable()
                    ->weight('bold')
                    ->color('warning'),

                TextColumn::make('padre.numero_fiscal')
                    ->label('Corrige a')
                    ->fontFamily('mono')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->tooltip('Documento que este anula o reemplaza.'),

                TextColumn::make('serial')
                    ->label('Serial')
                    ->fontFamily('mono')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options([
                        'factura' => 'Factura',
                        'nota_credito' => 'Nota de crédito',
                        'nota_debito' => 'Nota de débito',
                    ]),

                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options([
                        'queued' => 'En cola',
                        'issued' => 'Emitido',
                        'anulado' => 'Anulado',
                        'error' => 'Con error',
                    ])
                    ->default('issued'),

                SelectFilter::make('usuario_id')
                    ->label('Emitido por')
                    ->relationship('usuario', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('fecha_fiscal')
                    ->label('Fecha fiscal')
                    ->form([
                        DatePicker::make('desde')->label('Desde'),
                        DatePicker::make('hasta')->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['desde'] ?? null, fn (Builder $q, $f) => $q->whereDate('fecha_fiscal', '>=', $f))
                            ->when($data['hasta'] ?? null, fn (Builder $q, $f) => $q->whereDate('fecha_fiscal', '<=', $f));
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocumentosFiscales::route('/'),
            'view' => Pages\ViewDocumentoFiscal::route('/{record}'),
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

    public static function canView($record): bool
    {
        return self::canViewAny();
    }

    private static function etiquetaTipo(?string $state): string
    {
        return match ($state) {
            'factura' => 'Factura',
            'nota_credito' => 'Nota de crédito',
            'nota_debito' => 'Nota de débito',
            null => '—',
            default => ucfirst(str_replace('_', ' ', $state)),
        };
    }

    private static function etiquetaEstado(?string $state): string
    {
        return match ($state) {
            'queued' => 'En cola',
            'issued' => 'Emitido',
            'anulado' => 'Anulado',
            'nota_emitida' => 'Anulado por nota',
            'error' => 'Con error',
            null => '—',
            default => ucfirst($state),
        };
    }

    private static function colorEstado(?string $state): string
    {
        return match ($state) {
            'issued' => 'success',
            'queued' => 'warning',
            'anulado', 'nota_emitida' => 'danger',
            'error' => 'danger',
            default => 'gray',
        };
    }
}
