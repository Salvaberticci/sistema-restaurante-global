<?php

namespace App\Filament\Resources\OrdenResource\Pages;

use App\Domain\Ordenes\Enums\Canal;
use App\Domain\Ordenes\Enums\EstadoOrden;
use App\Domain\Ordenes\Models\Orden;
use App\Domain\Ordenes\Models\OrdenItem;
use App\Domain\Ventas\Services\IndicadoresVentas;
use App\Filament\Resources\OrdenResource;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

/**
 * El detalle de una cuenta: qué se pidió, quién lo pidió y cuánto se cobró.
 *
 * Los importes salen de la orden, no del catálogo. Si el precio de un plato
 * cambió después, esta pantalla sigue mostrando lo que ese día valía, porque eso
 * es lo que aparece en el recibo y lo que el cliente puede venir a discutir.
 *
 * Los ítems cortados se muestran con su motivo, no se esconden. En una cuenta de
 * mesa es normal que algo se corte y el mesero tiene que poder responder por qué.
 * Esconderlos hace pensar que el sistema se comió el plato.
 *
 * Dos detalles de Filament que salen caros si no se saben: los closures solo
 * reciben por nombre `$state`, `$record` y `$livewire`, y no se puede tipar el
 * enum en el parámetro porque el contenedor no puede instanciarlo. Por eso los
 * enums se resuelven desde el string guardado.
 */
class ViewOrden extends ViewRecord
{
    protected static string $resource = OrdenResource::class;

    /**
     * Las relaciones se cargan aquí y no con `->load()` en la vista porque el
     * proyecto prohíbe el lazy loading fuera de producción: al abrir la cuenta se
     * piden mesa, mesero, cajero, documento e ítems en una sola consulta.
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->record->loadMissing(['items', 'mesa', 'mesero', 'cajero', 'documentoFiscal']);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Cuenta')
                ->columns(3)
                ->schema([
                    TextEntry::make('numero')
                        ->label('Número')
                        ->fontFamily('mono')
                        ->weight('bold'),

                    TextEntry::make('estado')
                        ->label('Estado')
                        ->badge()
                        ->formatStateUsing(fn ($state) => self::etiquetaEstado($state))
                        ->color(fn ($state) => self::colorEstado($state)),

                    TextEntry::make('abierta_at')
                        ->label('Abierta')
                        ->date('d/m/Y')
                        ->time('H:i'),

                    TextEntry::make('canal')
                        ->label('Canal')
                        ->badge()
                        ->formatStateUsing(fn ($state) => self::etiquetaCanal($state))
                        ->color(fn ($state) => self::colorCanal($state)),

                    TextEntry::make('mesa.nombre')
                        ->label('Mesa')
                        ->placeholder('—'),

                    TextEntry::make('comensales')
                        ->label('Comensales')
                        ->placeholder('—'),

                    TextEntry::make('mesero.name')
                        ->label('Mesero')
                        ->placeholder('—'),

                    TextEntry::make('cajero.name')
                        ->label('Cajero')
                        ->placeholder('—'),

                    TextEntry::make('duracion')
                        ->label('Duración')
                        ->state(fn (Orden $record) => $record->duracionMinutos())
                        ->formatStateUsing(fn ($state) => $state === null ? '—' : $state.' min'),

                    TextEntry::make('cerrada_at')
                        ->label('Cerrada')
                        ->date('d/m/Y')
                        ->time('H:i')
                        ->placeholder('Sigue abierta'),

                    TextEntry::make('cobrada_at')
                        ->label('Cobrada')
                        ->date('d/m/Y')
                        ->time('H:i')
                        ->placeholder('—'),

                    TextEntry::make('observaciones')
                        ->label('Observaciones')
                        ->placeholder('Sin observaciones')
                        ->columnSpanFull(),
                ]),

            Section::make('Comanda')
                ->description('Los ítems cortados salen en rojo con su motivo: se pidieron y no se cobraron.')
                ->columns(1)
                ->schema([
                    RepeatableEntry::make('items')
                        ->schema([
                            TextEntry::make('cantidad')
                                ->label('Cant.')
                                ->alignEnd(),

                            TextEntry::make('producto_nombre')
                                ->label('Producto')
                                ->weight('bold')
                                ->color(fn (OrdenItem $item) => $item->fueCortado() ? 'danger' : null),

                            TextEntry::make('notas')
                                ->label('Notas')
                                ->placeholder('—'),

                            TextEntry::make('alicuota_codigo')
                                ->label('Alícuota')
                                ->formatStateUsing(fn ($state) => $state === 'IVA_E' ? 'Exento' : 'General')
                                ->badge()
                                ->color('gray'),

                            TextEntry::make('estado')
                                ->label('Estado')
                                ->badge()
                                ->formatStateUsing(fn ($state) => self::etiquetaItem($state))
                                ->color(fn ($state) => self::colorItem($state)),

                            TextEntry::make('cortado_motivo')
                                ->label('Motivo del corte')
                                ->placeholder('—')
                                ->color('danger'),

                            TextEntry::make('precio_unitario')
                                ->label('P. unit.')
                                ->money('USD')
                                ->alignEnd(),

                            TextEntry::make('importe')
                                ->label('Importe')
                                ->money('USD')
                                ->alignEnd()
                                ->weight('bold')
                                ->color(fn (OrdenItem $item) => $item->fueCortado() ? 'danger' : null),
                        ])
                        ->columns(4),
                ]),

            Section::make('Cobro')
                ->description('La tasa es la del momento del cobro y queda congelada en la cuenta.')
                ->columns(3)
                ->schema([
                    TextEntry::make('subtotal')
                        ->label('Subtotal')
                        ->money('USD'),

                    TextEntry::make('descuentos')
                        ->label('Descuentos')
                        ->money('USD')
                        ->color(fn (Orden $record) => (float) $record->descuentos > 0 ? 'danger' : null),

                    TextEntry::make('propina')
                        ->label('Propina')
                        ->money('USD'),

                    TextEntry::make('total')
                        ->label('Total')
                        ->money('USD')
                        ->weight('bold')
                        ->size('lg')
                        ->color('warning'),

                    TextEntry::make('metodo_pago')
                        ->label('Método de pago')
                        ->formatStateUsing(fn (?string $state) => $state === null ? '—' : (IndicadoresVentas::METODOS[$state] ?? $state))
                        ->badge(),

                    TextEntry::make('tasa_cambio')
                        ->label('Tasa de cambio')
                        ->state(fn (Orden $record) => $record->tasa_cambio)
                        ->formatStateUsing(fn ($state) => $state === null ? '—' : number_format((float) $state, 2).' Bs/USD'),

                    TextEntry::make('total_bolivares')
                        ->label('Total en bolívares')
                        ->state(fn (Orden $record) => $record->total_bolivares)
                        ->formatStateUsing(fn ($state) => $state === null ? '—' : 'Bs '.number_format((float) $state, 2)),

                    TextEntry::make('documentoFiscal.numero')
                        ->label('Documento fiscal')
                        ->placeholder('Sin documento emitido')
                        ->fontFamily('mono'),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        // Sin acciones de escritura. Una cuenta cobrada no se edita: se corrige
        // con una nota de crédito que la referencia (invariante I3).
        return [];
    }

    private static function etiquetaEstado(mixed $state): string
    {
        return $state instanceof EstadoOrden ? $state->etiqueta() : '.';
    }

    private static function colorEstado(mixed $state): string
    {
        return $state instanceof EstadoOrden ? $state->color() : 'gray';
    }

    private static function etiquetaCanal(mixed $state): string
    {
        return $state instanceof Canal ? $state->etiqueta() : '.';
    }

    private static function colorCanal(mixed $state): string
    {
        return $state instanceof Canal ? $state->color() : 'gray';
    }

    private static function etiquetaItem(mixed $state): string
    {
        return match ($state) {
            'pendiente' => 'Pendiente',
            'en_preparacion' => 'En preparación',
            'listo' => 'Listo',
            'servido' => 'Servido',
            'cortado' => 'Cortado',
            null => '—',
            default => ucfirst($state),
        };
    }

    private static function colorItem(mixed $state): string
    {
        return match ($state) {
            'cortado' => 'danger',
            'listo' => 'success',
            'servido' => 'info',
            'en_preparacion' => 'warning',
            default => 'gray',
        };
    }
}
