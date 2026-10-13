<?php

namespace App\Filament\Resources\AuditoriaResource\Pages;

use App\Domain\Auditoria\Models\Auditoria;
use App\Filament\Resources\AuditoriaResource;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

/**
 * Una entrada de la bitácora, con el antes y el después completos.
 *
 * Es la pantalla que se abre cuando alguien pregunta "¿quién cambió el precio de
 * esto y a qué hora?". Por eso el IP y el navegador no se esconden detrás de un
 * desplegable: son la mitad de la respuesta.
 */
class ViewAuditoria extends ViewRecord
{
    protected static string $resource = AuditoriaResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->record->loadMissing('usuario');
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Quién y cuándo')
                ->columns(3)
                ->schema([
                    TextEntry::make('created_at')
                        ->label('Fecha y hora')
                        ->date('d/m/Y')
                        ->time('H:i:s'),

                    TextEntry::make('actor_nombre')
                        ->label('Actor')
                        ->weight('bold')
                        ->placeholder('Sistema'),

                    TextEntry::make('usuario.name')
                        ->label('Usuario')
                        ->placeholder('—'),

                    TextEntry::make('ip')
                        ->label('IP')
                        ->fontFamily('mono'),

                    TextEntry::make('user_agent')
                        ->label('Navegador')
                        ->columnSpanFull(),
                ]),

            Section::make('Qué cambió')
                ->columns(2)
                ->schema([
                    TextEntry::make('accion')
                        ->label('Acción')
                        ->badge()
                        ->weight('bold'),

                    TextEntry::make('entidad')
                        ->label('Entidad'),

                    TextEntry::make('entidad_id')
                        ->label('Identificador')
                        ->fontFamily('mono'),

                    TextEntry::make('resumen')
                        ->label('Resumen')
                        ->columnSpanFull(),

                    TextEntry::make('antes')
                        ->label('Antes')
                        ->state(fn (Auditoria $registro) => self::json($registro->antes))
                        ->fontFamily('mono')
                        ->placeholder('Registro nuevo'),

                    TextEntry::make('despues')
                        ->label('Después')
                        ->state(fn (Auditoria $registro) => self::json($registro->despues))
                        ->fontFamily('mono')
                        ->placeholder('Registro nuevo'),
                ]),

            Section::make('Contexto')
                ->columns(1)
                ->schema([
                    TextEntry::make('contexto')
                        ->label('Datos del contexto')
                        ->state(fn (Auditoria $registro) => self::json($registro->contexto))
                        ->fontFamily('mono')
                        ->placeholder('Sin contexto adicional'),
                ]),
        ]);
    }

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
