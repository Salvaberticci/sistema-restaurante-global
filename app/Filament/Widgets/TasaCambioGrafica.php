<?php

namespace App\Filament\Widgets;

use App\Domain\Shared\Support\FechaFiscal;
use App\Domain\TasaCambio\Services\TasaCambioVigente;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

/**
 * La tasa del dólar, día por día.
 *
 * Una línea descendente suave es lo que un dueño Venezuelan está acostumbrado a
 * ver en la reality del bolívar. Si la curva no baja nunca, la pantalla no pasa
 * la primera pregunta de "¿esto está bien?" aunque los números cuadren.
 *
 * Se muestra en bolívares por dólar, que es como se lee: "el dólar está en 40".
 */
class TasaCambioGrafica extends ChartWidget
{
    protected static ?int $sort = 20;

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
        $serie = app(TasaCambioVigente::class)->serie((int) $this->filter);

        return [
            'datasets' => [[
                'label' => 'Bs por USD',
                'data' => array_values($serie),
                'borderColor' => '#38BDF8',
                'backgroundColor' => 'rgba(56, 189, 248, 0.14)',
                'fill' => true,
                'tension' => 0.35,
                'pointRadius' => 0,
                'pointHoverRadius' => 5,
                'borderWidth' => 2.5,
            ]],
            'labels' => array_map(
                fn (string $fecha) => CarbonImmutable::parse($fecha)->format('d/m'),
                array_keys($serie),
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
                'tooltip' => ['callbacks' => ['label' => fn ($ctx) => ' Bs '.number_format($ctx->raw, 2)]],
            ],
            'scales' => [
                'x' => ['grid' => ['display' => false]],
                'y' => [
                    'beginAtZero' => false,
                    'grid' => ['color' => 'rgba(255,255,255,0.05)'],
                    'ticks' => [
                        'callback' => fn ($v) => number_format((float) $v, 2),
                        'color' => 'rgba(255,255,255,0.45)',
                    ],
                ],
            ],
        ];
    }

    public function getHeading(): string
    {
        return 'Tasa de cambio';
    }

    public function getSubheading(): ?string
    {
        try {
            $tasa = app(TasaCambioVigente::class)->en(FechaFiscal::hoy());
        } catch (\RuntimeException $e) {
            return 'Sin tasa vigente para hoy';
        }

        return 'Hoy: Bs '.number_format((float) $tasa->usd, 2).' por dólar'
            .($tasa->confirmada ? '' : ' · sin confirmar');
    }
}
