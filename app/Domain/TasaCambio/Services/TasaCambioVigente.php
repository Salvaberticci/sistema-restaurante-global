<?php

namespace App\Domain\TasaCambio\Services;

use App\Domain\Shared\Support\FechaFiscal;
use App\Domain\TasaCambio\Models\TasaCambio;

/**
 * La tasa del día, según la fecha del negocio.
 *
 * El servicio no adivina: si no hay tasa vigente para esa fecha, lanza una
 * excepción. La alternativa —devolver 0 o la última conocida— convierte un
 * problema visible en un documento con el número equivocado, y de eso el
 * cliente se entera al mes siguiente, cuando ya no se puede arreglar.
 */
final class TasaCambioVigente
{
    /**
     * Tasa vigente en una fecha dada.
     *
     * @throws \RuntimeException si no hay ninguna
     */
    public function en(FechaFiscal $fecha, string $moneda = 'USD'): TasaCambio
    {
        $columna = $moneda === 'EUR' ? 'eur' : 'usd';

        $tasa = TasaCambio::query()
            ->whereNotNull($columna)
            ->whereDate('vigente_desde', '<=', $fecha->iso())
            ->where(function ($query) use ($fecha) {
                $query->whereNull('vigente_hasta')
                    ->orWhereDate('vigente_hasta', '>=', $fecha->iso());
            })
            ->orderByDesc('vigente_desde')
            ->first();

        if ($tasa === null) {
            throw new \RuntimeException(
                "No hay tasa de cambio {$moneda} vigente al {$fecha->iso()}. "
                .'Registre una tasa antes de emitir documentos en esa fecha.'
            );
        }

        return $tasa;
    }

    public function valor(FechaFiscal $fecha, string $moneda = 'USD'): float
    {
        return (float) $this->en($fecha, $moneda)->getAttribute($moneda === 'EUR' ? 'eur' : 'usd');
    }

    /**
     * Serie para la gráfica: la tasa de cada día, en orden.
     *
     * @return array<string, float>
     */
    public function serie(int $dias = 30, ?FechaFiscal $hasta = null): array
    {
        $hasta = $hasta ?? FechaFiscal::hoy();
        $desde = $hasta->sumarDias(-$dias + 1);

        $filas = TasaCambio::query()
            ->selectRaw('DATE(vigente_desde) as dia, MAX(usd) as usd')
            ->whereBetween('vigente_desde', [$desde->fecha()->startOfDay(), $hasta->fecha()->endOfDay()])
            ->groupBy('dia')
            ->pluck('usd', 'dia');

        $salida = [];

        for ($i = $dias - 1; $i >= 0; $i--) {
            $fecha = $hasta->sumarDias(-$i)->iso();
            $salida[$fecha] = round((float) ($filas[$fecha] ?? 0), 2);
        }

        return $salida;
    }
}
