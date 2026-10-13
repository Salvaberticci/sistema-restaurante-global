<?php

namespace App\Filament\Widgets;

use App\Domain\Ventas\Services\IndicadoresVentas;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

/**
 * Ventas por día.
 *
 * El período es conmutable porque un dueño mira distinto según la pregunta:
 * "¿cómo fue hoy?" (7 días) y "¿estoy creciendo?" (90 días) no son la misma
 * gráfica.
 */
class VentasPorDia extends ChartWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'lg:col-span-2';

    public ?string $filter = '30';

    protected function getFilters(): ?array
    {
        return [
            '7' => 'Últimos 7 días',
            '30' => 'Últimos 30 días',
            '90' => 'Últimos 90 días',
        ];
    }

    protected function getData(): array
    {
        $servicio = app(IndicadoresVentas::class);
        $datos = $servicio->ventasPorDia((int) $this->filter);

        return [
            'datasets' => [[
                'label' => 'Ventas (USD)',
                'data' => array_values($datos),
                'borderColor' => '#F59E0B',
                'backgroundColor' => 'rgba(245, 158, 11, 0.14)',
                'fill' => true,
                'tension' => 0.35,
                'pointRadius' => 0,
                'pointHoverRadius' => 5,
                'borderWidth' => 2.5,
            ]],
            'labels' => array_map(
                fn (string $fecha) => CarbonImmutable::parse($fecha)->format('d/m'),
                array_keys($datos),
            ),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'plugins' => [
                'legend' => ['display' => false],
                'tooltip' => [
                    'callbacks' => [
                        'label' => fn ($ctx) => ' $'.number_format($ctx->raw, 2),
                    ],
                ],
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
        return 'Ventas por día';
    }

    public function getSubheading(): ?string
    {
        $total = array_sum(app(IndicadoresVentas::class)->ventasPorDia((int) $this->filter));

        return 'Total $'.number_format($total, 2).' · promedio $'.number_format($total / max(1, (int) $this->filter), 2).'/día';
    }
}
