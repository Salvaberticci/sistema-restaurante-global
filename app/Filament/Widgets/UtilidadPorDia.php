<?php

namespace App\Filament\Widgets;

use App\Domain\Shared\Support\FechaFiscal;
use App\Domain\Ventas\Services\IndicadoresVentas;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

/**
 * Utilidad por día.
 *
 * Gráfica distinta de la de ventas a propósito: superponerlas haría entender
 * de un vistazo si el margen se está comprimiendo, que es la pregunta que un
 * dueño se hace cuando ve crecer la venta y no crece el dinero.
 */
class UtilidadPorDia extends ChartWidget
{
    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'lg:col-span-2';

    protected function getData(): array
    {
        $datos = app(IndicadoresVentas::class)->utilidadPorDia(30) ?? [];

        return [
            'datasets' => [
                [
                    'label' => 'Ventas',
                    'data' => array_values(app(IndicadoresVentas::class)->ventasPorDia(30)),
                    'borderColor' => 'rgba(245, 158, 11, 0.45)',
                    'borderWidth' => 1.5,
                    'pointRadius' => 0,
                    'tension' => 0.35,
                    'fill' => false,
                ],
                [
                    'label' => 'Utilidad',
                    'data' => array_values($datos),
                    'borderColor' => '#34D399',
                    'backgroundColor' => 'rgba(52, 211, 153, 0.16)',
                    'borderWidth' => 2.5,
                    'pointRadius' => 0,
                    'tension' => 0.35,
                    'fill' => true,
                ],
            ],
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
                'legend' => ['labels' => ['color' => 'rgba(255,255,255,0.7)', 'boxWidth' => 10, 'font' => ['size' => 11]]],
                'tooltip' => ['callbacks' => ['label' => fn ($c) => ' '.$c['dataset']['label'].': $'.number_format($c->raw, 2)]],
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
        return 'Ventas contra utilidad';
    }

    public function getSubheading(): ?string
    {
        $utilidad = app(IndicadoresVentas::class)->utilidadDelDia(FechaFiscal::hoy());

        if ($utilidad === null) {
            return 'Carga los costos en los productos para ver el margen';
        }

        $total = array_sum(app(IndicadoresVentas::class)->ventasPorDia(30));
        $utilidadPeriodo = array_sum(app(IndicadoresVentas::class)->utilidadPorDia(30) ?? []);
        $margen = $total > 0 ? round($utilidadPeriodo / $total * 100) : 0;

        return 'Margen del período: '.$margen.'%';
    }
}
