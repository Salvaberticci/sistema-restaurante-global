<?php

namespace App\Filament\Widgets;

use App\Domain\Ventas\Services\IndicadoresVentas;
use Filament\Widgets\ChartWidget;

/**
 * Ventas por categoría.
 *
 * Es el gráfico que responde "¿en qué se está yendo la plata?". Un dueño ve que
 * la venta subió y necesita saber si fue porque la gente come más o porque está
 * vendiendo otra cosa: esto separa las dos cosas.
 *
 * Circular y no barras porque las categorías son partes de un todo. La pregunta
 * "¿qué porcentaje del mes es bebidas?" necesita que las porciones se vean como
 * porciones.
 */
class VentasPorCategoria extends ChartWidget
{
    protected static ?int $sort = 8;

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
        $datos = app(IndicadoresVentas::class)->porCategoria((int) $this->filter);

        $paleta = [
            '#F59E0B', '#FBBF24', '#38BDF8', '#A78BFA',
            '#34D399', '#FB7185', '#60A5FA', '#64748B',
        ];

        // El color se asigna por posición en la porción, no por nombre: las categorías
        // las carga el dueño y no hay forma de saber de antemano cuántas son ni cómo se
        // llaman. Si sobran porciones, la paleta se repite.
        $etiquetas = array_keys($datos);
        $colores = [];

        foreach ($etiquetas as $posicion => $etiqueta) {
            $colores[$posicion] = $paleta[$posicion % count($paleta)];
        }

        return [
            'datasets' => [[
                'data' => array_values($datos),
                'backgroundColor' => $colores,
                'borderColor' => 'rgba(15, 17, 21, 0.9)',
                'borderWidth' => 2,
                'hoverOffset' => 8,
            ]],
            'labels' => $etiquetas,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        $datos = app(IndicadoresVentas::class)->porCategoria((int) $this->filter);
        $total = array_sum($datos);

        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'cutout' => '58%',
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                    'labels' => ['color' => 'rgba(255,255,255,0.7)', 'boxWidth' => 10, 'padding' => 12, 'font' => ['size' => 11]],
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => fn ($ctx) => ' $'.number_format($ctx->raw, 2)
                            .' ('.round($ctx->raw / max(1, $total) * 100).'%)',
                    ],
                ],
            ],
        ];
    }

    public function getHeading(): string
    {
        return 'Ventas por categoría';
    }

    public function getSubheading(): ?string
    {
        $datos = app(IndicadoresVentas::class)->porCategoria((int) $this->filter);

        if ($datos === []) {
            return 'Sin ventas en el período';
        }

        $total = array_sum($datos);
        $primera = array_key_first($datos);

        return $primera.' represents el '.round($datos[$primera] / max(1, $total) * 100).'% de $'.number_format($total, 2);
    }
}
