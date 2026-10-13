<?php

namespace App\Filament\Widgets;

use App\Domain\Shared\Support\FechaFiscal;
use App\Domain\TasaCambio\Services\TasaCambioVigente;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * La tasa vigente y cómo llegó ahí.
 *
 * Tres números y no uno porque el que pergunta "¿cuánto está el dólar?" siempre
 * termina preguntando también "¿y ayer?" y "¿y hace un mes?". Si solo está el de
 * hoy, hay que salir a buscar los otros dos en otra pantalla.
 */
class TasaCambioResumen extends StatsOverviewWidget
{
    protected static ?int $sort = 19;

    protected function getStats(): array
    {
        $servicio = app(TasaCambioVigente::class);
        $hoy = FechaFiscal::hoy();

        try {
            $tasa = $servicio->en($hoy);
        } catch (\RuntimeException $e) {
            return [
                Stat::make('Tasa de hoy', 'Sin tasa')
                    ->description($e->getMessage())
                    ->descriptionIcon('heroicon-m-exclamation-triangle')
                    ->color('danger'),
            ];
        }

        $actual = (float) $tasa->usd;
        $anterior = $this->tasaDe($servicio, $hoy->sumarDias(-1), $actual);
        $variacionDia = $anterior > 0 ? round(($actual - $anterior) / $anterior * 100, 2) : 0.0;

        $mes = $this->tasaDe($servicio, $hoy->sumarDias(-30), $actual);
        $variacionMes = $mes > 0 ? round(($actual - $mes) / $mes * 100, 2) : 0.0;

        $unDolar = 100;
        $equivalencia = $servicio->serie(1) ? $actual : 0.0;

        return [
            Stat::make('Tasa de hoy', 'Bs '.number_format($actual, 2))
                ->description($tasa->confirmada ? 'Confirmada · '.$tasa->fuente : 'Sin confirmar')
                ->descriptionIcon('heroicon-m-arrows-right-left')
                ->color('info')
                ->extraAttributes([
                    'data-srg-contador' => $actual,
                    'data-srg-prefijo' => 'Bs ',
                    'data-srg-decimales' => '2',
                ]),

            Stat::make('Variación del día', ($variacionDia >= 0 ? '+' : '').$variacionDia.' %')
                ->description('Contra la tasa de ayer')
                ->descriptionIcon('heroicon-m-arrow-trending-'.($variacionDia >= 0 ? 'up' : 'down'))
                ->color($variacionDia > 0 ? 'danger' : 'success'),

            Stat::make('Variación del mes', ($variacionMes >= 0 ? '+' : '').$variacionMes.' %')
                ->description('Contra hace 30 días')
                ->descriptionIcon('heroicon-m-arrow-trending-'.($variacionMes >= 0 ? 'up' : 'down'))
                ->color($variacionMes > 0 ? 'danger' : 'success'),

            Stat::make('Equivalencia', '$'.number_format($unDolar, 0).' = Bs '.number_format($equivalencia * $unDolar, 2))
                ->description('Cálculo con la tasa de hoy')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('gray'),
        ];
    }

    private function tasaDe(TasaCambioVigente $servicio, $fecha, float $porDefecto): float
    {
        try {
            return $servicio->valor($fecha);
        } catch (\RuntimeException $e) {
            return $porDefecto;
        }
    }
}
