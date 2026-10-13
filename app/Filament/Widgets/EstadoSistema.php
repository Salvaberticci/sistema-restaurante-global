<?php

namespace App\Filament\Widgets;

use App\Domain\Configuracion\Models\Parametro;
use App\Domain\Iam\Roles;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class EstadoSistema extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $hoy = now('America/Caracas')->toDateString();

        $usuarios = DB::table('users')->whereNull('deleted_at')->count();
        $pendientes = $this->parametrosPendientes();

        return [
            Stat::make('Usuarios activos', number_format($usuarios))
                ->description('Personal con acceso al sistema')
                ->color('success'),

            Stat::make('Roles', (string) count(Roles::nombres()))
                ->description('Administrador · Gerente · Cajero · Mesero · Cocina'),

            Stat::make('Fecha fiscal', $hoy)
                ->description('America/Caracas')
                ->color('info'),

            Stat::make('Parámetros pendientes', (string) $pendientes)
                ->description($pendientes > 0
                    ? 'Valores fiscales sin confirmar con un contador'
                    : 'Todo confirmado')
                ->color($pendientes > 0 ? 'warning' : 'success'),
        ];
    }

    /**
     * Cuenta los parámetros marcados como no confirmados.
     *
     * Esto existe para una razón concreta: las alícuotas del IGTF cambiaron por
     * decreto varias veces y el demo las trae de ejemplo. Si nadie las
     * confirma, el cliente puede estar viendo un número que no es el vigente. Es
     * preferible que se vea en pantalla a que se descubra cuando cobre.
     */
    private function parametrosPendientes(): int
    {
        $pendientes = 0;

        foreach (Parametro::where('grupo', 'fiscal')->get() as $parametro) {
            if ($this->tienePendientes($parametro->valor)) {
                $pendientes++;
            }
        }

        return $pendientes;
    }

    private function tienePendientes(mixed $valor): bool
    {
        if ($valor === null) {
            return false;
        }

        if ($valor === false) {
            return false;
        }

        if (! is_array($valor)) {
            return false;
        }

        if (array_key_exists('confirmado', $valor)) {
            return $valor['confirmado'] !== true;
        }

        foreach ($valor as $item) {
            if (is_array($item) && array_key_exists('confirmado', $item)) {
                return $item['confirmado'] !== true;
            }
        }

        return false;
    }
}