<?php

namespace App\Filament\Pages;

use App\Domain\Iam\Permisos;
use App\Filament\Widgets\PorMetodoPago;
use App\Filament\Widgets\ReporteX;
use App\Filament\Widgets\ResumenDelDia;
use App\Filament\Widgets\TopProductos;
use App\Filament\Widgets\UtilidadPorDia;
use App\Filament\Widgets\VentasPorCanal;
use App\Filament\Widgets\VentasPorCategoria;
use App\Filament\Widgets\VentasPorDia;
use App\Filament\Widgets\VentasPorHora;
use Filament\Pages\Page;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;

/**
 * Estadísticas: todas las gráficas del sistema en una pantalla.
 *
 * Existe porque el panel de inicio y la pantalla de Ventas muestran lo esencial
 * del día; esto es la pantalla donde se mira el mes. No es lo mismo: el dueño
 * que entra acá no está mirando qué va pasando hoy, está buscando por qué pasó.
 *
 * Todos los widgets leen del mismo `IndicadoresVentas`. Es la razón de que esta
 * pantalla no tenga cálculos propios: dos lugares que calculan lo mismo un día
 * terminan dando números distintos, y el primero que lo nota es el cliente.
 */
class Estadisticas extends Page
{
    protected static string $view = 'filament.pages.estadisticas';

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Gestión';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Estadísticas';

    protected static ?string $navigationLabel = 'Estadísticas';

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permisos::VENTAS_REPORTES) ?? false;
    }

    /**
     * @return array<int, class-string<Widget>|WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        return [
            ResumenDelDia::class,
            VentasPorDia::class,
            UtilidadPorDia::class,
            VentasPorCategoria::class,
            VentasPorCanal::class,
            PorMetodoPago::class,
            VentasPorHora::class,
            ReporteX::class,
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
     * Tres columnas en pantallas anchas. Dos no alcanzan para que un gráfico de
     * línea y otro circular no queden comprimidos, y uno deja la pantalla tan
     * larga que hay que hacer scroll para ver el último.
     *
     * @return array<string, int|string|null>
     */
    public function getColumns(): array
    {
        return ['default' => 1, 'lg' => 2, 'xl' => 3];
    }
}
