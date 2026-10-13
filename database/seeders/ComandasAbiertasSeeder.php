<?php

namespace Database\Seeders;

use App\Domain\Ordenes\Enums\EstadoOrden;
use App\Domain\Ordenes\Models\Mesa;
use App\Domain\Ordenes\Models\Orden;
use App\Domain\Ordenes\Models\OrdenItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Las comandas que están abiertas en este momento.
 *
 * Sin esto, la pantalla de cocina arranca vacía y frente al cliente parece un
 * módulo sin datos. Es lo contrario de un demo: un restaurante a las siete de la
 * tarde siempre tiene comandas en pantalla.
 *
 * Se siembran al final, después del historial, y usan los mismos productos y las
 * mismas mesas para que la cocina muestre platos que existen de verdad en el
 * catálogo.
 *
 * Los estados se reparten a mano —no al azar— porque los cuatro casos tienen que
 * estar visibles en una demostración: platos que acaban de entrar, platos a
 * media preparación, platos listos esperando que alguien los lleve, y una
 * comanda completa para probar el botón de servir todo.
 */
class ComandasAbiertasSeeder extends Seeder
{
    public function run(): void
    {
        $meseros = User::whereHas('roles', fn ($q) => $q->where('name', 'mesero'))->get();
        $mesas = Mesa::orderBy('id')->get();

        $catalogo = [
            ['PRI-01', 'Pabellón', 6.50],
            ['PRI-02', 'Ceviche', 9.80],
            ['PRI-03', 'Pastel de choclo', 4.20],
            ['BEB-01', 'Jugo de mango', 2.80],
            ['BEB-02', 'Cerveza', 3.50],
        ];

        /*
 * Los minutos de espera están repartidos a mano y no al azar porque el semáforo
 * se ve. Con el límite en 20 minutos, casi todo al azar cae en rojo y la
 * pantalla parece un incendio; lo que se quiere demostrar es que el semáforo
 * distingue: verde para lo que va bien, ámbar para lo que se atrasó un poco y
 * rojo solo para lo que hay que correr.
 */
        $comandas = [
            ['mesas' => 0, 'min' => 3, 'items' => [[0, 2], [4, 2]]],
            ['mesas' => 3, 'min' => 7, 'items' => [[1, 1], [3, 2]]],
            ['mesas' => 5, 'min' => 12, 'items' => [[0, 1], [2, 1], [4, 3]]],
            ['mesas' => 8, 'min' => 17, 'items' => [[1, 2], [3, 1]]],
            ['mesas' => 10, 'min' => 26, 'items' => [[2, 2], [4, 2]]],
            // La más antigua: la única en rojo. Sirve además para probar el
            // botón de "Servir todo".
            ['mesas' => 12, 'min' => 44, 'items' => [[0, 1], [1, 1], [3, 1]]],
        ];

        $ahora = Carbon::now();

        // El correlativo sale del `numero` más alto, no del `id` más alto. Son
        // cosas distintas: el historial numera de 000001 hacia arriba mientras
        // los identificadores llevan la numeración de los inserts. Tomar el `id`
        // produce un `numero` repetido y choca con el índice único.
        $correlativo = ((int) (Orden::max('numero') ?? 0)) + 1;

        foreach ($comandas as $indice => $comanda) {
            $mesa = $mesas[$indice % $mesas->count()];
            $apertura = $ahora->copy()->subMinutes($comanda['min']);

            $subtotal = 0.0;

            foreach ($comanda['items'] as [$producto, $cantidad]) {
                [$codigo, $nombre, $precio] = $catalogo[$producto];
                $subtotal += $precio * $cantidad;
            }

            $orden = Orden::create([
                'numero' => str_pad((string) $correlativo, 6, '0', STR_PAD_LEFT),
                'canal' => 'mesa',
                'mesa_id' => $mesa->id,
                'mesero_id' => $meseros->random()->id,
                'estado' => EstadoOrden::EnviadaCocina->value,
                'comensales' => $this->azar(2, max(2, (int) $mesa->sillas)),
                'metodo_pago' => 'efectivo_usd',
                'subtotal' => number_format($subtotal, 2, '.', ''),
                'descuentos' => '0.0000',
                'propina' => '0.0000',
                'total' => number_format($subtotal, 2, '.', ''),
                'moneda' => 'USD',
                'abierta_at' => $apertura,
            ]);

            foreach ($comanda['items'] as $indiceItem => [$producto, $cantidad]) {
                [$codigo, $nombre, $precio] = $catalogo[$producto];

                OrdenItem::create([
                    'orden_id' => $orden->id,
                    'producto_id' => 0,
                    'producto_nombre' => $nombre,
                    'producto_codigo' => $codigo,
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precio,
                    'importe' => number_format($precio * $cantidad, 2, '.', ''),
                    'alicuota_codigo' => 'IVA_G',
                    // Los últimos platos de la comanda más antigua ya están
                    // listos: así se ve la pantalla "Para pasar" con contenido.
                    'estado' => ($indice === 0 && $indiceItem === count($comanda['items']) - 1)
                        ? 'listo'
                        : 'en_preparacion',
                    'created_at' => $apertura,
                    'updated_at' => $apertura,
                ]);
            }

            $correlativo++;
        }

        $this->command->info(sprintf(
            'Comandas abiertas: %d. La pantalla de cocina ya tiene trabajo que mostrar.',
            count($comandas),
        ));
    }

    private function azar(int $min, int $max): int
    {
        return random_int($min, max($min, $max));
    }
}
