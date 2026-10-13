<?php

namespace Database\Seeders;

use App\Domain\Shared\Support\FechaFiscal;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Historial de tasas de cambio de los últimos 120 días.
 *
 * El número base no se escribe en el código como dato de negocio: es el punto de
 * partida de una serie sintética para la demostración. Lo que el sistema usa de
 * verdad está en `tasas_cambio`, con vigencia, y eso es lo que lee el POS y la
 * facturación.
 *
 * Las tasas suben y bajan con una onda suave y no en línea recta. Una serie que
 * solo sube se lee comoProjection y no como un mercado; y un dueño que conoce el
 * bolívar reconoce la forma de la curva.
 */
class TasaCambioSeeder extends Seeder
{
    private const DIAS = 120;

    public function run(): void
    {
        $fin = FechaFiscal::hoy();
        $base = 36.50;

        $filas = [];

        for ($i = self::DIAS - 1; $i >= 0; $i--) {
            $fecha = $fin->sumarDias(-$i)->fecha();

            // Onda lenta + ruido diario. Es una serie de demostración, no un
            // pronóstico: el objetivo es que la gráfica se vea creíble.
            $onda = sin($i / 9) * 0.9 + sin($i / 3.1) * 0.35;
            $valor = $base + $onda + $i * 0.055;

            $filas[] = [
                'vigente_desde' => $fecha->toDateString(),
                // Una tasa diaria vale solo ese día. Si quedara abierta, el
                // `whereDate('vigente_desde','<=')` del servicio devolvería
                // siempre la más antigua y la tasa nunca subiría.
                'vigente_hasta' => $fecha->toDateString(),
                'usd' => round($valor, 4),
                'eur' => round($valor * 0.92, 4),
                'fuente' => 'bcv',
                'confirmada' => true,
                'observacion' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('tasas_cambio')->insert($filas);
    }
}
