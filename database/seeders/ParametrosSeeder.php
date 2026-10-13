<?php

namespace Database\Seeders;

use App\Domain\Configuracion\Models\Parametro;
use Illuminate\Database\Seeder;

/**
 * Parámetros fiscales y políticas comerciales.
 *
 * ACÁ ESTÁN LAS TASAS, no en el código. Es la invariante I1: si alguien escribe
 * `0.16` en un servicio, el test arquitectónico falla.
 *
 * ⚠️ Los valores del IGTF son de DEMOSTRACIÓN y hay que confirmarlos con un
 * contador antes de usar esto con datos reales. La alícuota de IGTF cambió por
 * decreto varias veces y depende de la modalidad del pago en divisa. Están
 * marcados como pendientes en la descripción de cada parámetro para que en una
 * reunión con el cliente se vea qué falta confirmar.
 */
class ParametrosSeeder extends Seeder
{
    public function run(): void
    {
        $parametros = [
            // ── Impuestos ────────────────────────────────────────────────────
            [
                'clave' => 'impuestos.iva',
                'grupo' => 'fiscal',
                'descripcion' => 'Alícuotas de IVA con vigencia. La general aplica a comida preparada, bebidas y servicio de restaurante.',
                'valor' => [
                    ['codigo' => 'IVA_G', 'descripcion' => 'General', 'alicuota' => '16', 'vigencia_desde' => '2016-01-01', 'vigencia_hasta' => null],
                    ['codigo' => 'IVA_E', 'descripcion' => 'Exento', 'alicuota' => '0', 'vigencia_desde' => '2016-01-01', 'vigencia_hasta' => null],
                ],
            ],
            [
                'clave' => 'impuestos.igtf',
                'grupo' => 'fiscal',
                'descripcion' => 'PENDIENTE DE CONFIRMAR CON UN CONTADOR. Alícuota del IGTF por modalidad de pago en divisa. Cambió por decreto varias veces.',
                'valor' => [
                    ['codigo' => 'IGTF_EFECTIVO', 'descripcion' => 'Efectivo en divisa', 'alicuota' => '17', 'vigencia_desde' => '2026-01-01', 'vigencia_hasta' => null, 'confirmado' => false],
                    ['codigo' => 'IGTF_TRANSFERENCIA', 'descripcion' => 'Transferencia en divisa', 'alicuota' => '17', 'vigencia_desde' => '2026-01-01', 'vigencia_hasta' => null, 'confirmado' => false],
                    ['codigo' => 'IGTF_PUNTO_VENTA', 'descripcion' => 'Punto de venta en divisa', 'alicuota' => '17', 'vigencia_desde' => '2026-01-01', 'vigencia_hasta' => null, 'confirmado' => false],
                ],
            ],
            [
                'clave' => 'igtf.cobrar_al_cliente',
                'grupo' => 'fiscal',
                'descripcion' => 'Si el IGTF se traslada al cliente y se imprime desglosado en el documento, o si lo asume el restaurante.',
                'valor' => ['valor' => true, 'confirmado' => false],
            ],
            [
                'clave' => 'impuestos.idc',
                'grupo' => 'fiscal',
                'descripcion' => 'Impuesto a Débitos y Créditos. Lo paga el restaurante al recibir pagos con tarjeta. NO va en el documento fiscal del cliente.',
                'valor' => [
                    ['codigo' => 'IDC_DEBITO', 'descripcion' => 'Débito', 'alicuota' => '0.5', 'vigencia_desde' => '2024-01-01', 'vigencia_hasta' => null],
                    ['codigo' => 'IDC_CREDITO', 'descripcion' => 'Crédito', 'alicuota' => '1.5', 'vigencia_desde' => '2024-01-01', 'vigencia_hasta' => null],
                ],
            ],

            // ── Redondeo ─────────────────────────────────────────────────────
            [
                'clave' => 'redondeo.politica_diferencia',
                'grupo' => 'fiscal',
                'descripcion' => 'Qué se hace con el residuo de convertir dólares a bolívares: absorberlo o agregarlo como línea de ajuste.',
                'valor' => ['modo' => 'absorber'],
            ],

            // ── Comercial ────────────────────────────────────────────────────
            [
                'clave' => 'comercial.propina_sugerida',
                'grupo' => 'comercial',
                'descripcion' => 'Propina que sugiere el POS según el canal.',
                'valor' => [
                    ['canal' => 'mesa', 'porcentaje' => '10'],
                    ['canal' => 'parádelivery', 'porcentaje' => '0'],
                    ['canal' => 'para_llevar', 'porcentaje' => '0'],
                ],
            ],
            [
                'clave' => 'comercial.consumidor_final',
                'grupo' => 'comercial',
                'descripcion' => 'Cuándo se permite emitir a consumidor final sin exigir documento. Exigir RIF a cada cliente frena el punto de venta.',
                'valor' => [
                    'exigir_documento' => false,
                    'exigir_direccion_fiscal' => true,
                ],
            ],
            [
                'clave' => 'reservas.pago_por_adelantado',
                'grupo' => 'comercial',
                'descripcion' => 'Invariante I8: eventos y cursos se pagan 100% por adelantado. Una reserva no se confirma sin el pago completo.',
                'valor' => ['porcentaje' => '100', 'bloquea_sin_pago' => true],
            ],

            // ── Operación ────────────────────────────────────────────────────
            [
                'clave' => 'cocina.semaforo_minutos',
                'grupo' => 'operacion',
                'descripcion' => 'Colores del temporizador de la pantalla de cocina: verde, ámbar, rojo.',
                'valor' => [
                    ['nivel' => 'verde', 'minutos' => '15'],
                    ['nivel' => 'ambar', 'minutos' => '20'],
                    ['nivel' => 'rojo', 'minutos' => '20'],
                ],
            ],
        ];

        foreach ($parametros as $parametro) {
            Parametro::updateOrCreate(
                ['clave' => $parametro['clave']],
                [
                    'valor' => $parametro['valor'],
                    'descripcion' => $parametro['descripcion'],
                    'grupo' => $parametro['grupo'],
                ],
            );
        }

        $this->command?->newLine();
        $this->command?->info('Parámetros fiscales cargados. Revisar los marcados como PENDIENTES con un contador.');
    }
}