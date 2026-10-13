<?php

namespace App\Filament\Widgets;

use App\Domain\Ventas\Services\IndicadoresVentas;
use Filament\Widgets\ChartWidget;

/**
 * Ventas por canal.
 *
 * Comedor, para llevar y delivery. El canal decide el costo de servicio: una
 * venta para llevar consume menos mesa y menos cubiertos, así que el dueño
 * quiere ver cuánto de cada uno sale antes de decidir si abre otra.delivery.
 *
 * Barras y no circular porque las categorías no son partes de un todo medido
 * sobre el mismo número de clientes: son volúmenes de la misma venta.
 */
class VentasPorCanal extends ChartWidget
{
    protected static ?int $sort = 9;

    protected int|string|array $columnSpan = 'lg:col-span-1';

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
        $datos = app(IndicadoresVentas::class)->porCanal((int) $this->filter);

        $colores = [
            'Comedor' => '#F59E0B',
            'Para llevar' => '#38BDF8',
            'Delivery' => '#A78BFA',
        ];

        return [
            'datasets' => [[
                'label' => 'Ventas',
                'data' => array_values($datos),
                'backgroundColor' => array_map(
                    fn (string $etiqueta) => $colores[$etiqueta] ?? '#64748B',
                    array_keys($datos),
                ),
                'borderRadius' => 6,
                'borderSkipped' => false,
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
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['display' => false],
                'tooltip' => ['callbacks' => ['label' => fn ($ctx) => ' $'.number_format($ctx->raw, 2)]],
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
        return 'Comedor, para llevar y delivery';
    }

    public function getSubheading(): ?string
    {
        $datos = app(IndicadoresVentas::class)->porCanal((int) $this->filter);
        $total = array_sum($datos);

        if ($total <= 0) {
            return null;
        }

        return 'Comedor: '.round(($datos['Comedor'] ?? 0) / $total * 100).'% de la venta';
    }
}
