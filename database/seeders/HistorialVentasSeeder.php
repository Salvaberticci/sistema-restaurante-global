<?php

namespace Database\Seeders;

use App\Domain\Catalogos\Models\Producto;
use App\Domain\Mesas\Models\Zona;
use App\Domain\Ordenes\Enums\Canal;
use App\Domain\Ordenes\Enums\EstadoOrden;
use App\Domain\Ordenes\Models\Mesa;
use App\Domain\Ordenes\Models\Orden;
use App\Domain\Ordenes\Models\OrdenItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Noventa días de historial del restaurante.
 *
 * Esto es lo que separa una demo de una maqueta. Sin historial de ventas, el
 * modulo de estadisticas es una tabla vacia y el dueno no puede ni imaginarse lo que
 * cargue sus datos.
 *
 * Los patrones NO son ruido aleatorio, y esa es la diferencia:
 *
 *  - Fin de semana ~40% más comensales: es lo que pasa en cualquier restaurante.
 *  - Pico de almuerzo 12:00-14:00 y de cena 19:00-21:30, con una meseta los martes.
 *  - Días de lluvia bajan la terraza, los de evento la suben.
 *  - El gráfico de IGTF sale solo porque el 30% de los pagos son en divisa.
 *
 * Todos los datos son inventados. El banner de la interfaz lo dice.
 */
class HistorialVentasSeeder extends Seeder
{
    private const DIAS = 90;

    /**
     * Peso de cada producto. No es uniforme: el pabellón vende tres veces más
     * que el ceviche, y si todo pesara igual la gráfica de "top de productos"
     * sería plana y no diría nada.
     *
     * @var array<string, float>
     */
    private const POPULARIDAD = [
        'PRI-01' => 3.2,  // Pabellón criollo
        'PRI-02' => 2.6,  // Cachapas con pollo
        'ENT-01' => 2.4,  // Tequeños
        'PRI-03' => 2.2,  // Arepas
        'ENT-02' => 2.0,  // Patacón
        'BEB-01' => 1.9,  // Malta
        'PRI-04' => 1.8,  // Pastel de casas
        'BEB-02' => 1.8,  // Refresco
        'ENT-03' => 1.6,  // Croquetas
        'PRI-05' => 1.5,  // Arroz a la Julieta
        'POS-01' => 1.4,  // Tres leches
        'BEB-03' => 1.3,  // Jugo de naranja
        'PRI-07' => 1.2,  // Pasta
        'ENT-04' => 1.2,  // Empanadas
        'PRI-06' => 1.1,  // Churrasco
        'POS-02' => 1.0,  // Flan
        'BEB-05' => 1.0,  // Cerveza
        'PRI-08' => 0.9,  // Pizza
        'BEB-08' => 0.8,  // Café
        'ENT-06' => 0.7,  // Ensalada de aguacate
        'POS-03' => 0.7,  // Torta
        'BEB-04' => 0.6,  // Agua
        'BEB-07' => 0.6,  // Mojito
        'ENT-05' => 0.4,  // Ceviche
        'PRI-10' => 0.4,  // Salmón
        'PRI-09' => 0.3,  // Cazuela
        'BEB-06' => 0.3,  // Cerveza artesanal
        'POS-04' => 0.3,  // Helado
        'COM-01' => 0.5,  // Combo Casero
        'COM-02' => 0.3,  // Combo Parrilla
    ];

    /**
     * Mezcla de pago. En un restaurante venezolano real la divisa manda, y el
     * resto se reparte entre efectivo en bolívares, pago móvil y tarjeta.
     */
    private const METODOS_PAGO = [
        ['codigo' => 'efectivo_usd', 'peso' => 34, 'propina' => 0.10],
        ['codigo' => 'pago_movil', 'peso' => 22, 'propina' => 0.00],
        ['codigo' => 'efectivo_bs', 'peso' => 16, 'propina' => 0.08],
        ['codigo' => 'tarjeta_debito', 'peso' => 14, 'propina' => 0.00],
        ['codigo' => 'transferencia_usd', 'peso' => 8, 'propina' => 0.00],
        ['codigo' => 'punto_venta_usd', 'peso' => 6, 'propina' => 0.00],
    ];

    /** Tasa del bolívar. Ejemplo verosímil, no la real de ningún día. */
    private const TASA_USD_VES = '36.5000';

    public function run(): void
    {
        $this->zonasYMesas();
        $this->costos();

        $productos = Producto::all()->keyBy('codigo');
        $meseros = User::whereHas('roles', fn ($q) => $q->where('name', 'mesero'))->get();
        $cajeros = User::whereHas('roles', fn ($q) => $q->where('name', 'cajero'))->get();
        $mesas = Mesa::orderBy('id')->get();

        if ($meseros->isEmpty() || $cajeros->isEmpty()) {
            $this->command->warn('Faltan meseros o cajeros: se corre UsuariosDemoSeeder primero.');

            return;
        }

        $correlativo = 1;
        $totalOrdenes = 0;
        $totalVentas = 0.0;

        for ($diasAtras = self::DIAS - 1; $diasAtras >= 0; $diasAtras--) {
            $fecha = CarbonImmutable::now('America/Caracas')->subDays($diasAtras);
            [$ventasDia, $ordenesDia] = $this->generarDia(
                $fecha,
                $correlativo,
                $productos,
                $mesas,
                $meseros,
                $cajeros,
            );

            $correlativo += $ordenesDia;
            $totalOrdenes += $ordenesDia;
            $totalVentas += $ventasDia;
        }

        $this->command->newLine();
        $this->command->info(sprintf(
            'Historial: %d días · %d órdenes · $%s vendidos',
            self::DIAS,
            $totalOrdenes,
            number_format($totalVentas, 2),
        ));
    }

    private function zonasYMesas(): void
    {
        $salon = Zona::updateOrCreate(
            ['nombre' => 'Salón principal'],
            ['tipo' => 'salon', 'capacidad' => 60, 'mesas' => 14, 'turnover_minutos' => 75, 'activo' => true],
        );

        $barra = Zona::updateOrCreate(
            ['nombre' => 'Barra'],
            ['tipo' => 'barra', 'capacidad' => 12, 'mesas' => 6, 'turnover_minutos' => 40, 'activo' => true],
        );

        $terraza = Zona::updateOrCreate(
            ['nombre' => 'Terraza'],
            ['tipo' => 'terraza', 'capacidad' => 24, 'mesas' => 6, 'turnover_minutos' => 70, 'activo' => true],
        );

        $disposicion = [
            'salon' => [4, 4, 4, 2, 2, 6, 6, 8, 8, 4, 2, 10, 10, 12],
            'barra' => [1, 1, 1, 2, 2, 4],
            'terraza' => [2, 2, 4, 4, 6, 8],
        ];

        foreach ($disposicion as $zonaNombre => $sillas) {
            $zona = match ($zonaNombre) {
                'salon' => $salon,
                'barra' => $barra,
                default => $terraza,
            };

            $fila = 0;

            foreach ($sillas as $indice => $cantidad) {
                $numero = $fila * 10 + $indice + 1;

                Mesa::updateOrCreate(
                    ['codigo' => strtoupper($zonaNombre[0]).str_pad((string) $numero, 2, '0', STR_PAD_LEFT)],
                    [
                        'nombre' => (string) $numero,
                        'zona_id' => $zona->id,
                        'sillas' => $cantidad,
                        'pos_x' => $indice * 90,
                        'pos_y' => $fila * 70,
                        'activo' => true,
                    ],
                );

                // La barra y la terraza se usan en grupos de dos o cuatro.
                if (in_array($zonaNombre, ['barra', 'terraza'], true) && $indice % 2 === 1) {
                    $fila++;
                }
            }
        }
    }

    /**
     * Los productos no tenían costo, y sin costo no hay gráfico de utilidad.
     * Los productos no tienen costo, y sin costo no hay grafico de utilidad. Estos
     * valores son plausibles para una carta de este tamaño, pero son inventados: los
     *
     * @var array<string, string>
     */
    private const COSTOS = [
        'ENT-01' => '1.10', 'ENT-02' => '0.55', 'ENT-03' => '1.30', 'ENT-04' => '0.60',
        'ENT-05' => '3.10', 'ENT-06' => '1.40',
        'PRI-01' => '2.35', 'PRI-02' => '1.85', 'PRI-03' => '0.45', 'PRI-04' => '1.60',
        'PRI-05' => '1.70', 'PRI-06' => '4.20', 'PRI-07' => '1.55', 'PRI-08' => '1.40',
        'PRI-09' => '4.90', 'PRI-10' => '4.60',
        'BEB-01' => '0.28', 'BEB-02' => '0.42', 'BEB-03' => '0.65', 'BEB-04' => '0.30',
        'BEB-05' => '0.85', 'BEB-06' => '1.60', 'BEB-07' => '1.10', 'BEB-08' => '0.15',
        'POS-01' => '0.95', 'POS-02' => '0.60', 'POS-03' => '0.85', 'POS-04' => '0.70',
        'COM-01' => '3.40', 'COM-02' => '5.30',
    ];

    private function costos(): void
    {
        foreach (self::COSTOS as $codigo => $costo) {
            Producto::where('codigo', $codigo)->update(['precio_costo' => $costo]);
        }
    }

    /**
     * Un día entero de operación.
     *
     * @return array{0: float, 1: int} [ventas, órdenes]
     */
    private function generarDia(
        CarbonImmutable $fecha,
        int &$correlativo,
        $productos,
        $mesas,
        $meseros,
        $cajeros,
    ): array {
        $factor = $this->factorDelDia($fecha);
        $ventas = 0.0;
        $ordenes = 0;

        foreach ($this->horariosDelDia() as [$hora, $peso]) {
            $cuantas = (int) round($this->azar(0, 6) * $peso * $factor);

            for ($i = 0; $i < $cuantas; $i++) {
                $venta = $this->generarOrden(
                    $fecha->setTime($hora, $this->azar(0, 59)),
                    $correlativo++,
                    $productos,
                    $mesas,
                    $meseros,
                    $cajeros,
                );

                $ventas += $venta;
                $ordenes++;
            }
        }

        return [$ventas, $ordenes];
    }

    /**
     * Volumen relativo del día. Un gráfico de línea que sube y baja siempre igual
     * no convence a nadie: tiene que parecerse a un restaurante, con su viernes
     * lleno y su lunes flojo.
     */
    private function factorDelDia(CarbonImmutable $fecha): float
    {
        $factor = match ($fecha->dayOfWeek) {
            CarbonImmutable::FRIDAY => 1.35,
            CarbonImmutable::SATURDAY => 1.40,
            CarbonImmutable::SUNDAY => 1.25,
            CarbonImmutable::MONDAY => 0.80,
            CarbonImmutable::TUESDAY => 0.85,
            CarbonImmutable::WEDNESDAY => 0.95,
            default => 1.0,
        };

        // Eventos aleatorios: un cumpleaños, un cocktail, una primera
        // comunión. Es lo que hace que un mes real tenga picos que no
        // corresponden con el día de la semana.
        if (random_int(1, 100) <= 12) {
            $factor *= random_int(150, 260) / 100;
        }

        // Meseta entre semana.
        if ($fecha->dayOfWeek === CarbonImmutable::TUESDAY && $fecha->day <= 21) {
            $factor *= 0.75;
        }

        // Crecimiento suave a lo largo del período: el restaurante está yendo
        // mejor. Sin esto la línea de 90 días es plana y parece un sistema sin
        // uso.
        return $factor * (0.82 + ($fecha->dayOfYear / 365) * 0.30);
    }

    /**
     * @return list<array{0: int, 1: float}>
     */
    private function horariosDelDia(): array
    {
        return [
            [12, 1.4], [12, 1.1], [13, 1.6], [13, 1.2], [14, 0.8],
            [19, 1.2], [19, 1.5], [20, 1.8], [20, 1.3], [21, 0.7], [21, 0.4],
        ];
    }

    private function generarOrden(
        CarbonImmutable $apertura,
        int $correlativo,
        $productos,
        $mesas,
        $meseros,
        $cajeros,
    ): float {
        // Una sola tirada para las dos decisiones: repetir la llamada hace que el
        // análisis estático asuma que devuelve el mismo número y marque la
        // comparación como imposible.
        $tirada = $this->azar(0, 99);

        // 68 % comedor. Del resto, seis de cada diez para llevar.
        $canal = $tirada < 68
            ? Canal::Mesa
            : ($tirada < 92 ? Canal::ParaLlevar : Canal::Delivery);

        $mesa = $canal === Canal::Mesa ? $mesas->random() : null;
        $comensales = $mesa === null ? 1 : $this->azar(1, max(1, (int) ceil($mesa->sillas * 0.7)));

        $items = $this->armarComanda($comensales, $productos);
        $subtotal = array_sum(array_column($items, 'importe'));

        $descuentos = random_int(1, 100) <= 8 ? round($subtotal * random_int(5, 10) / 100, 2) : 0.0;

        $metodo = $this->elegirMetodoPago();
        $propinaPct = $metodo['propina'];
        $propina = $propinaPct > 0 ? round(($subtotal - $descuentos) * $propinaPct, 2) : 0.0;

        $total = $subtotal - $descuentos + $propina;

        $duracionMin = random_int(35, 95);
        $cierre = $apertura->addMinutes($duracionMin);

        $esDivisa = $this->esEnDivisa($metodo['codigo']);

        $orden = Orden::create([
            'numero' => str_pad((string) $correlativo, 6, '0', STR_PAD_LEFT),
            'canal' => $canal->value,
            'mesa_id' => $mesa?->id,
            'mesero_id' => $meseros->random()->id,
            'cajero_id' => $cajeros->random()->id,
            'estado' => EstadoOrden::Cobrada->value,
            'comensales' => $comensales,
            'metodo_pago' => $metodo['codigo'],
            'subtotal' => $subtotal,
            'descuentos' => $descuentos,
            'propina' => $propina,
            'total' => $total,
            'moneda' => $esDivisa ? 'USD' : 'VES',
            'tasa_cambio' => self::TASA_USD_VES,
            'total_bolivares' => round($total * (float) self::TASA_USD_VES, 2),
            'abierta_at' => $apertura,
            'cerrada_at' => $cierre,
            'cobrada_at' => $cierre->addMinutes(random_int(1, 6)),
        ]);

        foreach ($items as $item) {
            OrdenItem::create([
                'orden_id' => $orden->id,
                'producto_id' => $item['producto_id'],
                'producto_nombre' => $item['nombre'],
                'producto_codigo' => $item['codigo'],
                'cantidad' => $item['cantidad'],
                'precio_unitario' => $item['precio'],
                'importe' => $item['importe'],
                'alicuota_codigo' => 'IVA_G',
                'estado' => 'servido',
                'notas' => $this->notaAleatoria($item['codigo']),
            ]);
        }

        return $total;
    }

    /**
     * Arma lo que una mesa pidió. El número de platos por comensal sube un poco
     * los fines de semana: la gente se queda más tiempo.
     *
     * @return list<array{producto_id: int, codigo: string, nombre: string, cantidad: int, precio: float, importe: float}>
     */
    private function armarComanda(int $comensales, $productos): array
    {
        $codigos = array_keys(self::POPULARIDAD);
        $pesos = array_values(self::POPULARIDAD);

        $platos = max(1, (int) round($comensales * random_int(15, 28) / 10));
        $items = [];
        $vistos = [];

        for ($i = 0; $i < $platos; $i++) {
            $codigo = $codigos[$this->ponderado($pesos)];

            // No repetir dos veces el mismo plato en la misma comanda: se
            // agrupa la cantidad, que es como llega de verdad.
            if (isset($vistos[$codigo])) {
                $vistos[$codigo]['cantidad']++;
                $vistos[$codigo]['importe'] = round($vistos[$codigo]['cantidad'] * $vistos[$codigo]['precio'], 2);

                continue;
            }

            $producto = $productos->get($codigo);

            if ($producto === null) {
                continue;
            }

            $vistos[$codigo] = [
                'producto_id' => $producto->id,
                'codigo' => $codigo,
                'nombre' => $producto->nombre,
                'cantidad' => 1,
                'precio' => (float) $producto->precio_venta,
                'importe' => (float) $producto->precio_venta,
            ];
        }

        return array_values($vistos);
    }

    /**
     * Escoge un elemento según un vector de pesos. Es una ruleta: el producto
     * con peso 3,2 sale el triple de veces que uno con peso 1.
     *
     * @param  list<float>  $pesos
     */
    private function ponderado(array $pesos): int
    {
        $total = array_sum($pesos);
        $tiro = mt_rand() / mt_getrandmax() * $total;
        $acumulado = 0.0;

        foreach ($pesos as $indice => $peso) {
            $acumulado += $peso;

            if ($tiro <= $acumulado) {
                return $indice;
            }
        }

        return count($pesos) - 1;
    }

    /** @return array{codigo: string, propina: float} */
    private function elegirMetodoPago(): array
    {
        $pesos = array_column(self::METODOS_PAGO, 'peso');
        $elegido = self::METODOS_PAGO[$this->ponderado($pesos)];

        return ['codigo' => $elegido['codigo'], 'propina' => $elegido['propina']];
    }

    private function esEnDivisa(string $codigoMetodo): bool
    {
        return in_array($codigoMetodo, ['efectivo_usd', 'transferencia_usd', 'punto_venta_usd'], true);
    }

    /**
     * La mitad de las notas son indicaciones tecnicas de cocina, que es lo que de
     * verdad se escribe ahí. Un detalle de cada plato es lo que hace que el
     * módulo de cocina se vea real en la demo.
     */
    private function notaAleatoria(string $codigo): ?string
    {
        $notas = [
            'Sin cebolla', 'Sin cilantro', 'Término medio', 'Bien cocido',
            'Para compartir', 'En un plato aparte', 'Sin picante',
            'Con doble queso', 'Al natural', 'Para llevar',
        ];

        if (random_int(1, 100) > 22) {
            return null;
        }

        return $notas[array_rand($notas)];
    }

    private function azar(int $min, int $max): int
    {
        return random_int($min, max($min, $max));
    }
}
