<?php

namespace App\Filament\Widgets;

use App\Domain\Ventas\Services\IndicadoresVentas;
use Filament\Widgets\ChartWidget;

/**
 * Reparto por método de pago.
 *
 * Este gráfico es el que explica el IGTF sin que haya que explicarlo: la porción
 * ámbar es la que se paga en divisa, y la que va enfvían divisa es la que genera
 * el impuesto. Un dueño lo entiende viéndolo, no leyendo una definición.
 */
class PorMetodoPago extends ChartWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'lg:col-span-1';

    protected function getData(): array
    {
        $datos = app(IndicadoresVentas::class)->porMetodoPago(30);

        $colores = [
            'Efectivo USD' => '#F59E0B',
            'Efectivo Bs' => '#FBBF24',
            'Pago móvil' => '#38BDF8',
            'Tarjeta débito' => '#A78BFA',
            'Transferencia USD' => '#34D399',
            'POS divisa' => '#FB7185',
            'Zelle' => '#60A5FA',
        ];

        return [
            'datasets' => [[
                'data' => array_values($datos),
                'backgroundColor' => array_map(
                    fn (string $etiqueta) => $colores[$etiqueta] ?? '#64748B',
                    array_keys($datos),
                ),
                'borderColor' => 'rgba(15, 17, 21, 0.9)',
                'borderWidth' => 2,
                'hoverOffset' => 8,
            ]],
            'labels' => array_keys($datos),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        $total = array_sum(app(IndicadoresVentas::class)->porMetodoPago(30));
        $enDivisa = $total
            - (app(IndicadoresVentas::class)->porMetodoPago(30)['Efectivo Bs'] ?? 0)
            - (app(IndicadoresVentas::class)->porMetodoPago(30)['Pago móvil'] ?? 0)
            - (app(IndicadoresVentas::class)->porMetodoPago(30)['Tarjeta débito'] ?? 0);

        $porcentaje = $total > 0 ? round($enDivisa / $total * 100) : 0;

        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'cutout' => '62%',
            'plugins' => [
                'legend' => ['position' => 'right', 'labels' => ['color' => 'rgba(255,255,255,0.7)', 'boxWidth' => 10, 'font' => ['size' => 11]]],
                'tooltip' => [
                    'callbacks' => [
                        'label' => fn ($c) => ' $'.number_format($c->raw, 2).' ('.round($c->raw / max(1, $total) * 100).'%)',
                    ],
                ],
            ],
        ];
    }

    public function getHeading(): string
    {
        return 'Métodos de pago';
    }

    public function getSubheading(): ?string
    {
        $total = array_sum(app(IndicadoresVentas::class)->porMetodoPago(30));

        if ($total <= 0) {
            return null;
        }

        $datos = app(IndicadoresVentas::class)->porMetodoPago(30);
        $enDivisa = $total - ($datos['Efectivo Bs'] ?? 0) - ($datos['Pago móvil'] ?? 0) - ($datos['Tarjeta débito'] ?? 0);
        $porcentaje = round($enDivisa / $total * 100);

        return $porcentaje.'% de la venta del mes se cobró en divisa';
    }
}
