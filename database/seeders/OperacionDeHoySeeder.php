<?php

namespace Database\Seeders;

use App\Domain\Catalogos\Models\Producto;
use App\Domain\Ordenes\Enums\EstadoOrden;
use App\Domain\Ordenes\Models\Mesa;
use App\Domain\Ordenes\Models\Orden;
use App\Domain\Ordenes\Models\OrdenItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * La operación de hoy: lo que ya se cobró y lo que está en cocina.
 *
 * Son dos mitades y van juntas a propósito.
 *
 * La parte cobrada existe porque el panel de inicio mide **el día**, y una demo
 * que se corre a las nueve de la mañana no tiene todavía ninguna cuenta cerrada:
 * los seis indicadores salen en cero y el cliente lee "esto no funciona" en vez
 * de "hoy todavía no ha abierto". Con unas cuentas de la madrugada, los números
 * existen y además son ciertos —son las órdenes que se cobró de verdad—.
 *
 * La parte en cocina existe porque un KDS vacío no demuestra nada, y a las siete
 * de la tarde un restaurante siempre tiene comandas en pantalla.
 *
 * Los minutos de espera se reparten a mano y no al azar porque el semáforo se ve.
 * Con el límite en quince minutos, casi todo al azar cae en rojo y la pantalla
 * parece un incendio; lo que se quiere demostrar es que el semáforo distingue:
 * verde para lo que va bien, ámbar para lo que se atrasó un poco y rojo solo para
 * lo que hay que correr.
 */
class OperacionDeHoySeeder extends Seeder
{
    /**
     * Menú corto, tomado del catálogo y no inventado.
     *
     * Se leen los productos por código y se usa **su** precio, no uno escrito
     * acá. Además se guardan con su `producto_id` real: la utilidad se calcula
     * cruzando los ítems con el costo del producto, y una comanda con
     * `producto_id = 0` no cruza con nada. El resultado es un margen del 100% en
     * todas las cuentas del día, que no es una utilidad: es un número inventado,
     * y de esos no se.demo.nada frente a un cliente.
     *
     * @var list<string>
     */
    private const CODIGOS = ['PRI-01', 'PRI-02', 'PRI-03', 'BEB-01', 'BEB-02'];

    /** Métodos de pago: código, propina sugerida y si es en divisa. */
    private const PAGOS = [
        ['efectivo_usd', 10, true],
        ['pago_movil', 0, false],
        ['efectivo_bs', 10, false],
        ['transferencia_usd', 0, true],
    ];

    public function run(): void
    {
        $meseros = User::whereHas('roles', fn ($q) => $q->where('name', 'mesero'))->get();
        $cajeros = User::whereHas('roles', fn ($q) => $q->where('name', 'cajero'))->get();
        $mesas = Mesa::orderBy('id')->get();
        $menu = $this->menu();

        if ($meseros->isEmpty() || $cajeros->isEmpty() || $mesas->isEmpty() || $menu->count() < count(self::CODIGOS)) {
            $this->command->warn('Faltan meseros, cajeros, mesas o productos: se corre el seeder que los carga primero.');

            return;
        }

        $ahora = Carbon::now();
        $numero = ((int) (Orden::max('numero') ?? 0)) + 1;

        $numero = $this->cuentasCobradas($ahora, $meseros, $cajeros, $mesas, $menu, $numero);
        $this->comandasEnCocina($ahora, $meseros, $mesas, $menu, $numero);
    }

    /**
     * Los productos del menú, indexados por código.
     *
     * @return Collection<string, Producto>
     */
    private function menu(): Collection
    {
        return Producto::query()
            ->whereIn('codigo', self::CODIGOS)
            ->get()
            ->keyBy('codigo');
    }

    /**
     * Las cuentas que ya se cerraron hoy.
     *
     * Se reparten entre la medianoche y ahora, que es todo el turno que existe a
     * la hora en que se corre el seeder. Un restaurante que abre a las diez no
     * puede tener cuentas de las tres de la mañana.
     */
    private function cuentasCobradas(Carbon $ahora, $meseros, $cajeros, $mesas, Collection $menu, int $numero): int
    {
        $minutosTranscurridos = max(60, (int) $ahora->diffInMinutes($ahora->copy()->startOfDay()));

        // Una cuenta cada siete minutos en promedio: una cocina pequeña en hora
        // pico. Ni una cada dos minutos ni una cada veinte.
        $cuantas = max(3, (int) round($minutosTranscurridos / 7));

        for ($i = 0; $i < $cuantas; $i++) {
            $mesa = $mesas->random();
            $apertura = $ahora->copy()->subMinutes(
                $minutosTranscurridos - (int) round($i * $minutosTranscurridos / $cuantas),
            );

            $items = $this->armarComanda($mesa->sillas, $menu);
            $subtotal = array_sum(array_column($items, 'importe'));

            [$codigoPago, $propinaPct, $enDivisa] = self::PAGOS[array_rand(self::PAGOS)];
            $propina = $propinaPct > 0 ? round($subtotal * $propinaPct / 100, 2) : 0.0;
            $total = $subtotal + $propina;
            $cierre = $apertura->copy()->addMinutes(random_int(25, 70));

            $orden = Orden::create([
                'numero' => str_pad((string) $numero, 6, '0', STR_PAD_LEFT),
                'canal' => 'mesa',
                'mesa_id' => $mesa->id,
                'mesero_id' => $meseros->random()->id,
                'cajero_id' => $cajeros->random()->id,
                'estado' => EstadoOrden::Cobrada->value,
                'comensales' => random_int(1, max(1, (int) ceil($mesa->sillas * 0.8))),
                'metodo_pago' => $codigoPago,
                'subtotal' => number_format($subtotal, 2, '.', ''),
                'descuentos' => '0.0000',
                'propina' => number_format($propina, 2, '.', ''),
                'total' => number_format($total, 2, '.', ''),
                'moneda' => $enDivisa ? 'USD' : 'VES',
                'abierta_at' => $apertura,
                'cerrada_at' => $cierre,
                'cobrada_at' => $cierre->copy()->addMinutes(random_int(1, 5)),
            ]);

            foreach ($items as $item) {
                OrdenItem::create([
                    'orden_id' => $orden->id,
                    'producto_id' => $item['producto_id'],
                    'producto_nombre' => $item['nombre'],
                    'producto_codigo' => $item['codigo'],
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $item['precio'],
                    'importe' => number_format($item['importe'], 2, '.', ''),
                    'alicuota_codigo' => 'IVA_G',
                    'estado' => 'servido',
                    'created_at' => $apertura,
                    'updated_at' => $cierre,
                ]);
            }

            $numero++;
        }

        $this->command->info("Cuentas cobradas hoy: {$cuantas}.");

        return $numero;
    }

    /**
     * Las comandas que la cocina tiene en pantalla ahora mismo.
     */
    private function comandasEnCocina(Carbon $ahora, $meseros, $mesas, Collection $menu, int $numero): void
    {
        $codigos = self::CODIGOS;

        $comandas = [
            ['min' => 3, 'items' => [[0, 2], [4, 2]]],
            ['min' => 7, 'items' => [[1, 1], [3, 2]]],
            ['min' => 12, 'items' => [[0, 1], [2, 1], [4, 3]]],
            ['min' => 17, 'items' => [[1, 2], [3, 1]]],
            ['min' => 26, 'items' => [[2, 2], [4, 2]]],
            // La más antigua: la única en rojo. Sirve además para probar el
            // botón de "Servir todo".
            ['min' => 44, 'items' => [[0, 1], [1, 1], [3, 1]]],
        ];

        foreach ($comandas as $indice => $comanda) {
            $mesa = $mesas[$indice % $mesas->count()];
            $apertura = $ahora->copy()->subMinutes($comanda['min']);

            $subtotal = 0.0;

            foreach ($comanda['items'] as [$producto, $cantidad]) {
                $subtotal += (float) $menu[$codigos[$producto]]->precio_venta * $cantidad;
            }

            $orden = Orden::create([
                'numero' => str_pad((string) $numero, 6, '0', STR_PAD_LEFT),
                'canal' => 'mesa',
                'mesa_id' => $mesa->id,
                'mesero_id' => $meseros->random()->id,
                'estado' => EstadoOrden::EnviadaCocina->value,
                'comensales' => random_int(2, max(2, (int) $mesa->sillas)),
                'metodo_pago' => 'efectivo_usd',
                'subtotal' => number_format($subtotal, 2, '.', ''),
                'descuentos' => '0.0000',
                'propina' => '0.0000',
                'total' => number_format($subtotal, 2, '.', ''),
                'moneda' => 'USD',
                'abierta_at' => $apertura,
            ]);

            foreach ($comanda['items'] as $posicion => [$producto, $cantidad]) {
                $plato = $menu[$codigos[$producto]];
                $precio = (float) $plato->precio_venta;

                OrdenItem::create([
                    'orden_id' => $orden->id,
                    'producto_id' => $plato->id,
                    'producto_nombre' => $plato->nombre,
                    'producto_codigo' => $plato->codigo,
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precio,
                    'importe' => number_format($precio * $cantidad, 2, '.', ''),
                    'alicuota_codigo' => $plato->alicuota_codigo,
                    // El último plato de la primera comanda ya está listo: así la
                    // pantalla "Para pasar" también tiene contenido.
                    'estado' => ($indice === 0 && $posicion === count($comanda['items']) - 1)
                        ? 'listo'
                        : 'en_preparacion',
                    'created_at' => $apertura,
                    'updated_at' => $apertura,
                ]);
            }

            $numero++;
        }

        $this->command->info(sprintf(
            'Comandas en cocina: %d. La pantalla de cocina ya tiene trabajo que mostrar.',
            count($comandas),
        ));
    }

    /**
     * Una comanda para una mesa de cierta capacidad, con productos reales.
     *
     * El precio sale del catálogo, no de una constante acá: si el dueño sube el
     * pabellón, la cuenta de hoy sube con él.
     *
     * @return list<array{producto_id: int, codigo: string, nombre: string, cantidad: int, precio: float, importe: float}>
     */
    private function armarComanda(int $sillas, Collection $menu): array
    {
        $platos = max(1, (int) round($sillas / 2));

        $items = [];

        for ($i = 0; $i < $platos; $i++) {
            $plato = $menu->random();
            $cantidad = random_int(1, 2);
            $precio = (float) $plato->precio_venta;

            $items[] = [
                'producto_id' => $plato->id,
                'codigo' => $plato->codigo,
                'nombre' => $plato->nombre,
                'cantidad' => $cantidad,
                'precio' => $precio,
                'importe' => $precio * $cantidad,
            ];
        }

        // Una bebida acompaña a la comida. Sin ella el ticket promedio queda raro
        // para un restaurante y la gráfica de categorías sale descuadrada.
        if (array_rand([true, false, true]) && $menu->has('BEB-01')) {
            $bebida = $menu['BEB-01'];
            $precio = (float) $bebida->precio_venta;

            $items[] = [
                'producto_id' => $bebida->id,
                'codigo' => $bebida->codigo,
                'nombre' => $bebida->nombre,
                'cantidad' => 1,
                'precio' => $precio,
                'importe' => $precio,
            ];
        }

        return $items;
    }
}
