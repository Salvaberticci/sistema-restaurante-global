<?php

namespace Database\Seeders;

use App\Domain\Reservas\Enums\EstadoPago;
use App\Domain\Reservas\Enums\EstadoReserva;
use App\Domain\Reservas\Models\Reservacion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Eventos y cursos de demostración.
 *
 * Se siembran los cuatro estados a propósito, no solo los pagados: una pantalla
 * donde todo está confirmado y pagado no demuestra que el sistema sepa manejar un
 * abono pendiente. El que está a medias es el que prueba la regla.
 */
class ReservacionesSeeder extends Seeder
{
    public function run(): void
    {
        $hoy = Carbon::today();

        $reservas = [
            [
                'tipo' => 'evento',
                'nombre' => 'Boda de Samantha y Miguel',
                'descripcion' => 'Salón completo, menú de 4 tiempos y barra libre.',
                'dias' => 12,
                'personas' => 60,
                'precio' => 35.00,
                'pagado' => 2100.00,
                'estado' => EstadoReserva::Confirmada,
                'cliente' => 'María Fernández',
                'documento' => 'V-12345678',
                'telefono' => '0412-1234567',
            ],
            [
                'tipo' => 'curso',
                'nombre' => 'Curso de cocina Venezuelan: arepas y cachapas',
                'descripcion' => 'Cuatro sesiones. Cupo de 12 personas. Incluye materiales.',
                'dias' => 5,
                'personas' => 12,
                'precio' => 80.00,
                'pagado' => 960.00,
                'cupo' => 12,
                'estado' => EstadoReserva::Confirmada,
                'cliente' => 'Corporación Chab Foods R.A.',
                'documento' => 'J-31234567-8',
                'telefono' => '0414-5556677',
            ],
            [
                'tipo' => 'evento',
                'nombre' => 'Cumpleaños de 15',
                'descripcion' => 'Torta, décoración y menú para 25 personas.',
                'dias' => 3,
                'personas' => 25,
                'precio' => 28.00,
                'pagado' => 700.00,
                'estado' => EstadoReserva::Confirmada,
                'cliente' => 'José Ramírez',
                'documento' => 'V-87654321',
                'telefono' => '0424-9876543',
            ],
            [
                // La del abono: el CHECK de la tabla no permite esto confirmado.
                'tipo' => 'evento',
                'nombre' => 'Reunión de socios',
                'descripcion' => 'Almuerzo para 15 personas, salón pequeño.',
                'dias' => 7,
                'personas' => 15,
                'precio' => 22.00,
                'pagado' => 165.00,
                'estado' => EstadoReserva::Solicitada,
                'cliente' => 'Transportes El Páramo',
                'documento' => 'J-19876543-2',
                'telefono' => '0212-2468135',
            ],
            [
                'tipo' => 'curso',
                'nombre' => 'Taller de repostería: postres y tortas',
                'dias' => 20,
                'personas' => 0,
                'precio' => 95.00,
                'pagado' => 0,
                'cupo' => 10,
                'estado' => EstadoReserva::Solicitada,
                'cliente' => 'Sin cliente asignado',
                'documento' => null,
                'telefono' => null,
            ],
            [
                'tipo' => 'evento',
                'nombre' => 'Aniversario de la empresa',
                'dias' => -10,
                'personas' => 45,
                'precio' => 32.00,
                'pagado' => 1440.00,
                'estado' => EstadoReserva::Realizada,
                'cliente' => 'Distribuidora Lara',
                'documento' => 'J-40112233-4',
                'telefono' => '0416-3344556',
            ],
        ];

        foreach ($reservas as $datos) {
            $personas = $datos['personas'] > 0 ? $datos['personas'] : 1;
            $total = round($datos['precio'] * $personas, 2);

            Reservacion::create([
                'tipo' => $datos['tipo'],
                'nombre' => $datos['nombre'],
                'descripcion' => $datos['descripcion'] ?? null,
                'fecha_inicio' => $hoy->copy()->addDays($datos['dias']),
                'fecha_fin' => null,
                'hora_inicio' => '18:00',
                'personas' => $personas,
                'cupo_maximo' => $datos['cupo'] ?? null,
                'precio_unitario' => number_format($datos['precio'], 2, '.', ''),
                'total' => number_format($total, 2, '.', ''),
                'pagado' => number_format($datos['pagado'], 2, '.', ''),
                'estado_pago' => EstadoPago::desde((string) $total, (string) $datos['pagado']),
                'estado' => $datos['estado'],
                'cliente_nombre' => $datos['cliente'],
                'cliente_documento' => $datos['documento'],
                'cliente_telefono' => $datos['telefono'],
                'confirmada_at' => $datos['estado'] === EstadoReserva::Confirmada ? now()->subDays(5) : null,
            ]);
        }
    }
}
