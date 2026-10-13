<?php

namespace App\Filament\Widgets;

use App\Domain\Ventas\Services\IndicadoresVentas;
use Filament\Widgets\ChartWidget;

/**
 * Los productos que más dinero trajeron.
 *
 * Barras horizontales y ordenadas de mayor a menor: con barras verticales los
 * nombres largos de plato se cortan y hay que inclinar la etiqueta.
 */
class TopProductos extends ChartWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'lg:col-span-2';

    protected function getData(): array
    {
        $datos = app(IndicadoresVentas::class)->topProductos(8, 90);

        // Chart.js no entiende bien el orden inverso en barras horizontales:
        // sin invertir, el producto más vendido queda abajo.
        $datos = array_reverse($datos, true);

        return [
            'datasets' => [[
                'label' => 'Vendido',
                'data' => array_values($datos),
                'backgroundColor' => array_map(
                    fn ($v, $i) => $i >= count($datos) - 3 ? '#F59E0B' : 'rgba(245, 158, 11, 0.45)',
                    array_values($datos),
                    array_keys($datos),
                ),
                'borderRadius' => 6,
                'borderSkipped' => false,
                'barThickness' => 22,
            ]],
            'labels' => array_keys($datos),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['display' => false],
                'tooltip' => ['callbacks' => ['label' => fn ($c) => ' $'.number_format($c->raw, 2)]],
            ],
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'grid' => ['color' => 'rgba(255,255,255,0.05)'],
                    'ticks' => [
                        'callback' => fn ($v) => '$'.number_format((float) $v),
                        'color' => 'rgba(255,255,255,0.45)',
                    ],
                ],
                'y' => [
                    'grid' => ['display' => false],
                    'ticks' => ['color' => 'rgba(255,255,255,0.7)'],
                ],
            ],
        ];
    }

    public function getHeading(): string
    {
        return 'Lo que más se vendió';
    }

    public function getSubheading(): ?string
    {
        $datos = app(IndicadoresVentas::class)->topProductos(8, 90);

        if ($datos === []) {
            return null;
        }

        $top = array_key_first($datos);

        return $top.' lidera con $'.number_format($datos[$top], 2).' en 90 días';
    }
}
