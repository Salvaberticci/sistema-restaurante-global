<?php

namespace App\Filament\Resources\OrdenResource\Pages;

use App\Filament\Resources\OrdenResource;
use App\Filament\Widgets\PorMetodoPago;
use App\Filament\Widgets\ResumenDelDia;
use App\Filament\Widgets\TopProductos;
use App\Filament\Widgets\UtilidadPorDia;
use App\Filament\Widgets\VentasPorDia;
use App\Filament\Widgets\VentasPorHora;
use Filament\Resources\Pages\ListRecords;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;

/**
 * Ventas: las gráficas arriba, la tabla de cuentas abajo.
 *
 * Filament no tiene `getHeaderWidgets()` en una página de recurso —ese método es
 * del panel de inicio—, así que la vista de la página está propia: usa el mismo
 * componente `<x-filament-widgets::widgets>` que el dashboard y luego la tabla.
 *
 * Los widgets son los mismos del panel de inicio, y esa es la parte importante:
 * la gráfica de esta pantalla y la del menú leen del mismo `IndicadoresVentas`.
 * Si cada pantalla calculara lo suyo, con el tiempo una acabaría diciendo $18.400
 * y la otra $18.900, y en una reunión frente al cliente eso no tiene defensa.
 */
class ListOrdenes extends ListRecords
{
    protected static string $resource = OrdenResource::class;

    protected static ?string $title = 'Ventas';

    protected static string $view = 'filament.resources.pages.ventas-lista';

    /**
     * @return array<int, class-string<Widget>|WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        return [
            ResumenDelDia::class,
            VentasPorDia::class,
            VentasPorHora::class,
            PorMetodoPago::class,
            UtilidadPorDia::class,
            TopProductos::class,
        ];
    }

    /**
     * @return array<int, class-string<Widget>|WidgetConfiguration>
     */
    public function getVisibleWidgets(): array
    {
        return $this->filterVisibleWidgets($this->getWidgets());
    }

    /**
     * Dos columnas en pantalla grande, una en el teléfono. La misma retícula que
     * el panel de inicio, para que moving the eye entre las dos no salte.
     *
     * @return array<string, int|string|null>
     */
    public function getColumns(): array
    {
        return ['default' => 1, 'lg' => 2];
    }
}
