<?php

namespace App\Filament\Resources;

use App\Domain\Auditoria\Models\Auditoria;
use App\Domain\Iam\Permisos;
use App\Filament\Navegacion;
use App\Filament\Resources\AuditoriaResource\Pages;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Bitácora: quién cambió qué, cuándo y desde dónde. Invariante I6.
 *
 * Recurso de **solo lectura** y no por capricho: el modelo lanza una excepción
 * si algo intenta actualizarla o borrarla. Una bitácora que se puede corregir es
 * una bitácora en la que nadie cree.
 *
 * La pantalla responde a la pregunta que se le hace de verdad: "¿quién tocó el
 * precio de esto?". Por eso el antes y el después se muestran lado a lado en el
 * detalle, y no solo el nombre del campo.
 */
class AuditoriaResource extends Resource
{
    protected static ?string $model = Auditoria::class;

    protected static ?string $modelLabel = 'entrada de auditoría';

    protected static ?string $pluralModelLabel = 'auditoría';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = Navegacion::ADMINISTRACION;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'auditoria';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permisos::AUDITORIA_VER) ?? false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('usuario:id,name'))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Cuándo')
                    ->date('d/m/Y')
                    ->time('H:i:s')
                    ->sortable(),

                TextColumn::make('actor_nombre')
                    ->label('Quién')
                    ->weight('bold')
                    ->searchable()
                    // `actor_nombre` es el nombre congelado en el momento del
                    // cambio; la relación es solo para poder filtrar por usuario.
                    // Si el nombre se borró después, la entrada sigue diciendo quién
                    // fue, y eso es lo que hace útil una bitácora.
                    ->description(fn (Auditoria $registro) => $registro->usuario?->name)
                    ->placeholder('Sistema'),

                TextColumn::make('accion')
                    ->label('Qué hizo')
                    ->searchable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('entidad')
                    ->label('Dónde')
                    ->searchable(),

                TextColumn::make('entidad_id')
                    ->label('Registro')
                    ->fontFamily('mono')
                    ->color('gray'),

                TextColumn::make('resumen')
                    ->label('Cambio')
                    ->state(fn (Auditoria $registro) => $registro->resumen())
                    ->wrap()
                    ->placeholder('—')
                    ->color('gray'),

                TextColumn::make('ip')
                    ->label('IP')
                    ->fontFamily('mono')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('user_agent')
                    ->label('Navegador')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('usuario_id')
                    ->label('Usuario')
                    ->relationship('usuario', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('entidad')
                    ->label('Módulo')
                    ->options(fn () => Auditoria::query()
                        ->select('entidad')
                        ->distinct()
                        ->orderBy('entidad')
                        ->pluck('entidad', 'entidad')
                        ->all()),

                SelectFilter::make('accion')
                    ->label('Acción')
                    ->options(fn () => Auditoria::query()
                        ->select('accion')
                        ->distinct()
                        ->orderBy('accion')
                        ->pluck('accion', 'accion')
                        ->all()),

                Filter::make('created_at')
                    ->label('Fecha')
                    ->form([
                        DatePicker::make('desde')->label('Desde'),
                        DatePicker::make('hasta')->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['desde'] ?? null, fn (Builder $q, $f) => $q->whereDate('created_at', '>=', $f))
                            ->when($data['hasta'] ?? null, fn (Builder $q, $f) => $q->whereDate('created_at', '<=', $f));
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditorias::route('/'),
            'view' => Pages\ViewAuditoria::route('/{record}'),
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
}
