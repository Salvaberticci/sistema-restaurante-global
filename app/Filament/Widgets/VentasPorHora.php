<?php

namespace App\Filament\Widgets;

use App\Domain\Ventas\Services\IndicadoresVentas;
use Filament\Widgets\ChartWidget;

/**
 * Ventas por hora.
 *
 * La gráfica de la jornada real: muestra el pico de almuerzo, la meseta de la
 * tarde y el segundo pico de la cena. Es la que explica de un vistazo por qué
 * hay que tener dos cocineros a la una y no a las cinco.
 */
class VentasPorHora extends ChartWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'lg:col-span-1';

    protected function getData(): array
    {
        $datos = app(IndicadoresVentas::class)->ventasPorHora();

        return [
            'datasets' => [[
                'label' => 'Ventas',
                'data' => array_values($datos),
                'backgroundColor' => array_map(
                    fn ($v) => $v > 0 ? 'rgba(245, 158, 11, 0.75)' : 'rgba(245, 158, 11, 0.15)',
                    array_values($datos),
                ),
                'borderRadius' => 6,
                'borderSkipped' => false,
            ]],
            'labels' => array_map(
                fn (int $hora) => sprintf('%02d:00', $hora),
                array_keys($datos),
            ),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['display' => false],
                'tooltip' => ['callbacks' => ['label' => fn ($c) => ' $'.number_format($c->raw, 2)]],
            ],
            'scales' => [
                'x' => ['grid' => ['display' => false]],
                'y' => [
                    'beginAtZero' => true,
                    'grid' => ['color' => 'rgba(255,255,255,0.05)'],
                    'ticks' => [
                        'callback' => fn ($v) => '$'.number_format((float) $v),
                        'color' => 'rgba(255,255,255,0.45)',
                    ],
                ],
            ],
        ];
    }

    public function getHeading(): string
    {
        return 'Jornada de hoy';
    }
}
