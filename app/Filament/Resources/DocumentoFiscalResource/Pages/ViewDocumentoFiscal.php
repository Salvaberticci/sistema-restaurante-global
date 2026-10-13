<?php

namespace App\Filament\Resources\DocumentoFiscalResource\Pages;

use App\Domain\Facturacion\Models\DocumentoFiscal;
use App\Filament\Resources\DocumentoFiscalResource;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

/**
 * El documento tal como se emitió: lo que se cobró, con qué desglose y qué
 * respondió el emisor.
 *
 * Los bloques del emisor (serial, hash, payload) salen tal cual, sin interpretar.
 * Un cliente que pide ver el documento quiere ver lo que recibió la máquina
 * fiscal, no un resumen prettier del mismo. Si algo hay que traducir, es un
 * comentario al lado, no un dato retocado.
 */
class ViewDocumentoFiscal extends ViewRecord
{
    protected static string $resource = DocumentoFiscalResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->record->loadMissing(['orden', 'usuario', 'padre']);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Documento')
                ->columns(3)
                ->schema([
                    TextEntry::make('numero_fiscal')
                        ->label('Número fiscal')
                        ->fontFamily('mono')
                        ->weight('bold')
                        ->size('lg')
                        ->placeholder('Sin número: el documento no llegó a emitirse'),

                    TextEntry::make('tipo')
                        ->label('Tipo')
                        ->badge(),

                    TextEntry::make('estado')
                        ->label('Estado')
                        ->badge(),

                    TextEntry::make('serie')
                        ->label('Serie'),

                    TextEntry::make('numero')
                        ->label('Correlativo')
                        ->placeholder('—'),

                    TextEntry::make('contador')
                        ->label('Contador del emisor')
                        ->placeholder('—'),

                    TextEntry::make('fecha_fiscal')
                        ->label('Fecha fiscal')
                        ->date('d/m/Y'),

                    TextEntry::make('emitido_en')
                        ->label('Emitido')
                        ->date('d/m/Y')
                        ->time('H:i')
                        ->placeholder('Nunca emitido'),

                    TextEntry::make('orden.numero')
                        ->label('Cuenta de origen')
                        ->fontFamily('mono')
                        ->placeholder('—'),

                    TextEntry::make('usuario.name')
                        ->label('Emitido por')
                        ->placeholder('—'),

                    TextEntry::make('padre.numero_fiscal')
                        ->label('Corrige al documento')
                        ->fontFamily('mono')
                        ->placeholder('—')
                        ->columnSpanFull(),

                    TextEntry::make('ultimo_error')
                        ->label('Último error')
                        ->placeholder('Ninguno')
                        ->color('danger')
                        ->columnSpanFull(),
                ]),

            Section::make('Importes')
                ->description('La tasa queda congelada en el documento: es la del día de emisión, no la de hoy.')
                ->columns(3)
                ->schema([
                    TextEntry::make('base_imponible')
                        ->label('Base imponible')
                        ->money('USD'),

                    TextEntry::make('total_impuestos')
                        ->label('IVA')
                        ->money('USD'),

                    TextEntry::make('total_igtf')
                        ->label('IGTF')
                        ->money('USD')
                        ->color(fn (DocumentoFiscal $record) => (float) $record->total_igtf > 0 ? 'danger' : null),

                    TextEntry::make('total_descuentos')
                        ->label('Descuentos')
                        ->money('USD'),

                    TextEntry::make('total_propina')
                        ->label('Propina')
                        ->money('USD')
                        ->helperText('La propina no es base imponible.'),

                    TextEntry::make('total')
                        ->label('Total')
                        ->money('USD')
                        ->size('lg')
                        ->weight('bold')
                        ->color('warning'),

                    TextEntry::make('moneda')
                        ->label('Moneda'),

                    TextEntry::make('tasa_cambio')
                        ->label('Tasa de cambio')
                        ->formatStateUsing(fn ($state) => $state === null ? '—' : number_format((float) $state, 2).' Bs/USD'),
                ]),

            Section::make('Desglose')
                ->columns(1)
                ->schema([
                    TextEntry::make('iva_por_alicuota')
                        ->label('IVA por alícuota')
                        ->state(fn (DocumentoFiscal $record) => self::json($record->iva_por_alicuota))
                        ->placeholder('Sin desglose'),

                    TextEntry::make('igtf_por_perfil')
                        ->label('IGTF por perfil del contribuyente')
                        ->state(fn (DocumentoFiscal $record) => self::json($record->igtf_por_perfil))
                        ->placeholder('No aplica'),

                    TextEntry::make('pagos')
                        ->label('Pagos')
                        ->state(fn (DocumentoFiscal $record) => self::json($record->pagos))
                        ->placeholder('Sin detalle de pagos'),

                    TextEntry::make('cliente_snapshot')
                        ->label('Cliente')
                        ->state(fn (DocumentoFiscal $record) => self::json($record->cliente_snapshot))
                        ->placeholder('Consumidor final'),
                ]),

            Section::make('Emisor')
                ->description('Lo que devolvió el emisor fiscal. Se muestra literal: es la evidencia.')
                ->columns(1)
                ->schema([
                    TextEntry::make('serial')
                        ->label('Serial')
                        ->fontFamily('mono')
                        ->placeholder('—'),

                    TextEntry::make('hash')
                        ->label('Hash')
                        ->fontFamily('mono')
                        ->placeholder('—'),

                    TextEntry::make('idempotency_key')
                        ->label('Clave de idempotencia')
                        ->fontFamily('mono')
                        ->helperText('Invariante I5: un reintento con esta clave nunca duplica el documento.'),

                    TextEntry::make('intentos')
                        ->label('Intentos de emisión'),

                    TextEntry::make('resultado_emisor')
                        ->label('Respuesta del emisor')
                        ->state(fn (DocumentoFiscal $record) => self::json($record->resultado_emisor))
                        ->placeholder('—'),

                    TextEntry::make('documento_payload')
                        ->label('Documento enviado')
                        ->state(fn (DocumentoFiscal $record) => self::json($record->documento_payload))
                        ->placeholder('—'),
                ]),
        ]);
    }

    /**
     * Los bloques del emisor se muestran como JSON legible.
     *
     * Sin esto un array sale como `Array` o como un volcado críptico en una sola
     * línea, y en una reunión hay que abrir las herramientas para leerlo.
     */
    private static function json(?array $datos): ?string
    {
        if ($datos === null || $datos === []) {
            return null;
        }

        return json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
