<?php

namespace App\Filament\Widgets;

use App\Domain\Ordenes\Enums\EstadoOrden;
use App\Domain\Ventas\Services\IndicadoresVentas;
use Filament\Widgets\Widget;

/**
 * Últimas cuentas cobradas.
 *
 * No es una tabla más: es una tira con el número, la mesa, el mesero, el importe
 * y cuánto tardó. El tiempo en la mesa es el dato que un dueño quiere ver en
 * crudo, porque es el que dice si el servicio se está tardando.
 */
class ActividadReciente extends Widget
{
    protected static string $view = 'filament.widgets.actividad-reciente';

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return true;
    }

    public function heading(): string
    {
        return 'Actividad reciente';
    }

    protected function getHeading(): ?string
    {
        return 'Actividad reciente';
    }

    public function getViewData(): array
    {
        $ordenes = app(IndicadoresVentas::class)->ultimasOrdenes(6);

        return [
            'ordenes' => $ordenes,
            'colorEstado' => fn (EstadoOrden $estado) => $estado->color(),
        ];
    }
}
