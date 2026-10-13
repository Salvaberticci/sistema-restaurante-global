<?php

namespace App\Filament\Resources\TasaCambioResource\Pages;

use App\Filament\Resources\TasaCambioResource;
use App\Filament\Widgets\TasaCambioGrafica;
use App\Filament\Widgets\TasaCambioResumen;
use Filament\Resources\Pages\ListRecords;

/**
 * La tasa de hoy arriba, el historial abajo.
 *
 * El orden es el de la pregunta: primero cuánto está, después de dónde salió.
 */
class ListTasasCambio extends ListRecords
{
    protected static string $resource = TasaCambioResource::class;

    protected static ?string $title = 'Tasa de cambio';

    protected static string $view = 'filament.resources.pages.tasas-lista';

    /**
     * @return array<int, class-string>
     */
    public function getWidgets(): array
    {
        return [
            TasaCambioResumen::class,
            TasaCambioGrafica::class,
        ];
    }

    /**
     * @return array<int, class-string>
     */
    public function getVisibleWidgets(): array
    {
        return $this->filterVisibleWidgets($this->getWidgets());
    }

    /**
     * @return array<string, int|string|null>
     */
    public function getColumns(): array
    {
        return ['default' => 1, 'lg' => 2];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
