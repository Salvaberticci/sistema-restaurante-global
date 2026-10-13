<?php

namespace App\Filament\Widgets;

use App\Domain\Facturacion\Models\DocumentoFiscal;
use App\Domain\Shared\Support\FechaFiscal;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

/**
 * Reporte X: cómo se comportó el día.
 *
 * Va por documento fiscal y no por orden a propósito. El reporte X lo que
 * cuenta son los documentos emitidos, porque es lo que se declaró; si se contara
 * desde las órdenes, un documento rechazado después quedaría igual.
 *
 * La curva por hora aquí es otra cosa a la de "Jornada de hoy": aquella muestra
 * la hora de apertura de la cuenta, esta la hora de emisión del documento. No
 * son el mismo dato y por eso no comparten gráfico.
 */
class ReporteX extends ChartWidget
{
    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'lg:col-span-2';

    protected function getData(): array
    {
        $fecha = FechaFiscal::hoy();

        $porHora = DocumentoFiscal::query()
            ->where('estado', 'issued')
            ->whereDate('fecha_fiscal', $fecha->iso())
            ->selectRaw('HOUR(emitido_en) as hora, SUM(total) as total')
            ->groupBy('hora')
            ->pluck('total', 'hora');

        $datos = [];

        for ($hora = 8; $hora <= 22; $hora++) {
            $datos[$hora] = round((float) ($porHora[$hora] ?? 0), 2);
        }

        return [
            'datasets' => [[
                'label' => 'Documentos emitidos',
                'data' => array_values($datos),
                'backgroundColor' => array_map(
                    fn (float $v) => $v > 0 ? 'rgba(52, 211, 153, 0.75)' : 'rgba(52, 211, 153, 0.15)',
                    $datos,
                ),
                'borderRadius' => 6,
                'borderSkipped' => false,
            ]],
            'labels' => array_map(fn (int $h) => sprintf('%02d:00', $h), array_keys($datos)),
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
        return 'Reporte X del día';
    }

    public function getSubheading(): ?string
    {
        $fecha = FechaFiscal::hoy();

        $documentos = DocumentoFiscal::query()
            ->where('estado', 'issued')
            ->whereDate('fecha_fiscal', $fecha->iso())
            ->count();

        if ($documentos === 0) {
            return 'Aún no se han emitido documentos hoy';
        }

        $total = (float) DocumentoFiscal::query()
            ->where('estado', 'issued')
            ->whereDate('fecha_fiscal', $fecha->iso())
            ->sum('total');

        $encabezado = $documentos.' documentos por $'.number_format($total, 2);

        return $encabezado.' · '.CarbonImmutable::parse($fecha->iso())->format('d/m/Y');
    }
}
