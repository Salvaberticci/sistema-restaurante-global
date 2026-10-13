<?php

namespace App\Filament\Widgets;

use App\Domain\Ventas\Services\IndicadoresVentas;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Los seis números que resumen el día.
 *
 * Cada tarjeta lleva su número en `data-srg-contador`, que el JavaScript del
 * panel anima. El motivo no es decoración: un número que aparece de golpe obliga
 * a leerlo dos veces, una para ver cuánto es y otra para compararlo con el de al
 * lado.
 */
class ResumenDelDia extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $r = app(IndicadoresVentas::class)->resumenDelDia();

        $utilidad = $r['utilidad'];
        $margen = $utilidad !== null && $r['total'] > 0
            ? round($utilidad / $r['total'] * 100, 1)
            : null;

        return [
            Stat::make('Ventas de hoy', '$'.number_format($r['total'], 2))
                ->description($r['ordenes'] === 0 ? 'Sin ventas registradas' : $r['ordenes'].' cuentas cobradas')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning')
                ->extraAttributes([
                    'data-srg-contador' => $r['total'],
                    'data-srg-prefijo' => '$',
                    'data-srg-decimales' => '2',
                ]),

            Stat::make('Ticket promedio', '$'.number_format($r['ticket_promedio'], 2))
                ->description('Por cuenta cobrada')
                ->descriptionIcon('heroicon-m-receipt-percent')
                ->color('info')
                ->extraAttributes([
                    'data-srg-contador' => $r['ticket_promedio'],
                    'data-srg-prefijo' => '$',
                    'data-srg-decimales' => '2',
                ]),

            Stat::make('Comensales', number_format($r['comensales']))
                ->description('Atendidos hoy')
                ->descriptionIcon('heroicon-m-users')
                ->color('success')
                ->extraAttributes([
                    'data-srg-contador' => $r['comensales'],
                ]),

            Stat::make('Propinas', '$'.number_format($r['propinas'], 2))
                ->description($r['propinas'] > 0
                    ? round($r['propinas'] / max(1, $r['total']) * 100, 1).'% sobre la venta'
                    : 'Sin propinas registradas')
                ->descriptionIcon('heroicon-m-heart')
                ->color('success')
                ->extraAttributes([
                    'data-srg-contador' => $r['propinas'],
                    'data-srg-prefijo' => '$',
                    'data-srg-decimales' => '2',
                ]),

            Stat::make('IGTF del día', '$'.number_format($r['igtf_del_dia'], 2))
                ->description('Generado por pagos en divisa')
                ->descriptionIcon('heroicon-m-building-library')
                ->color('danger')
                ->extraAttributes([
                    'data-srg-contador' => $r['igtf_del_dia'],
                    'data-srg-prefijo' => '$',
                    'data-srg-decimales' => '2',
                ]),

            Stat::make('Utilidad de hoy', $utilidad === null ? '—' : '$'.number_format($utilidad, 2))
                ->description($margen === null ? 'Carga los costos de los productos' : 'Margen del '.$margen.'%')
                ->descriptionIcon('heroicon-m-chart-bar-square')
                ->color($margen === null ? 'gray' : ($margen >= 60 ? 'success' : ($margen >= 35 ? 'warning' : 'danger')))
                ->extraAttributes([
                    'data-srg-contador' => $utilidad ?? 0,
                    'data-srg-prefijo' => '$',
                    'data-srg-decimales' => '2',
                ]),
        ];
    }
}
