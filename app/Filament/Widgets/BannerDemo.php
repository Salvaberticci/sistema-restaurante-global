<?php

namespace App\Filament\Widgets;

use App\Domain\Mesas\Models\Sucursal;
use Filament\Widgets\Widget;

/**
 * Aviso de modo demo.
 *
 * Mientras `DEMO_MODO` esté activo, esta cinta aparece en todas las pantallas
 * del panel. El motivo es concreto: los datos son de un restaurante ficticio y
 * hay valores fiscales de ejemplo sin confirmar. Que el cliente lo vea escrito
 * es mejor que después preguntarle por qué el restaurante se llama Casa Mango.
 */
class BannerDemo extends Widget
{
    protected static string $view = 'filament.widgets.banner-demo';

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) config('demo.modo');
    }

    public function heading(): ?string
    {
        return 'Modo demostración';
    }

    public function getViewData(): array
    {
        return [
            'sucursales' => Sucursal::activas()->pluck('nombre'),
        ];
    }
}
