<?php

namespace App\Domain\Ventas\Services;

use App\Domain\Configuracion\Contracts\Parametros;
use App\Domain\Ordenes\Enums\EstadoOrden;
use App\Domain\Ordenes\Models\Orden;
use App\Domain\Shared\Support\FechaFiscal;
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Support\Facades\DB;

/**
 * Indicadores de venta.
 *
 * Todo lo que se ve en los grÃ¡ficos sale de acÃ¡, no de consultas sueltas en los
 * widgets. La razÃ³n es concreta: si el grÃ¡fico de ventas y el nÃºmero del
 * indicador de arriba salen de consultas distintas, se terminan contradiciendo
 * cuando alguien ajusta un filtro, y en una reuniÃ³n frente al cliente eso es
 * peor que no tener el nÃºmero.
 *
 * Una venta cuenta solo si la orden estÃ¡ `cobrada`. Una orden abierta no es
 * venta: es una promesa.
 */
final class IndicadoresVentas
{
    /** Ventas por dÃ­a, en la moneda del documento. */
    public function ventasPorDia(int $dias = 30, ?FechaFiscal $hasta = null): array
    {
        $hasta = $hasta ?? FechaFiscal::hoy();
        $desde = $hasta->sumarDias(-$dias + 1);

        $filas = DB::table('ordenes')
            ->selectRaw('DATE(abierta_at) as dia, COUNT(*) as ordenes, SUM(total) as total, SUM(propina) as propina')
            ->where('estado', EstadoOrden::Cobrada->value)
            ->whereBetween('abierta_at', [$desde->fecha()->startOfDay(), $hasta->fecha()->endOfDay()])
            ->groupBy('dia')
            ->pluck('total', 'dia');

        $salida = [];

        for ($i = $dias - 1; $i >= 0; $i--) {
            $fecha = $hasta->sumarDias(-$i)->iso();
            $salida[$fecha] = round((float) ($filas[$fecha] ?? 0), 2);
        }

        return $salida;
    }

    /**
     * Ventas por hora del dÃ­a. Es el grÃ¡fico que mejor explica por quÃ© hay mÃ¡s
     * gente a la una que a las cinco: la curva es la jornada real.
     *
     * @return array<int, float>
     */
    public function ventasPorHora(?FechaFiscal $fecha = null): array
    {
        $fecha = $fecha ?? FechaFiscal::hoy();

        $filas = DB::table('ordenes')
            ->selectRaw('HOUR(abierta_at) as hora, SUM(total) as total')
            ->where('estado', EstadoOrden::Cobrada->value)
            ->whereDate('abierta_at', $fecha->iso())
            ->groupBy('hora')
            ->pluck('total', 'hora');

        $salida = [];

        for ($hora = 11; $hora <= 23; $hora++) {
            $salida[$hora] = round((float) ($filas[$hora] ?? 0), 2);
        }

        return $salida;
    }

    /**
     * DistribuciÃ³n por mÃ©todo de pago. Es el grÃ¡fico que explica de dÃ³nde sale el
     * IGTF: la porciÃ³n en divisa es la que lo genera.
     *
     * @return array<string, float>
     */
    public function porMetodoPago(int $dias = 30, ?FechaFiscal $hasta = null): array
    {
        $hasta = $hasta ?? FechaFiscal::hoy();
        $desde = $hasta->sumarDias(-$dias + 1);

        $filas = DB::table('ordenes')
            ->selectRaw('metodo_pago as metodo, SUM(total) as total')
            ->where('estado', EstadoOrden::Cobrada->value)
            ->whereBetween('abierta_at', [$desde->fecha()->startOfDay(), $hasta->fecha()->endOfDay()])
            ->groupBy('metodo')
            ->pluck('total', 'metodo');

        $salida = [];

        foreach (self::METODOS as $codigo => $etiqueta) {
            $salida[$etiqueta] = round((float) ($filas[$codigo] ?? 0), 2);
        }

        return $salida;
    }

    /**
     * Productos mÃ¡s vendidos por importe, no por unidades. Al dueÃ±o le importa
     * Productos mÃ¡s vendidos por importe, no por unidades. Al dueÃ±o le importa
     *
     * @return array<string, float>
     */
    public function topProductos(int $limite = 8, int $dias = 90, ?FechaFiscal $hasta = null): array
    {
        $hasta = $hasta ?? FechaFiscal::hoy();
        $desde = $hasta->sumarDias(-$dias + 1);

        return DB::table('orden_items')
            ->join('ordenes', 'ordenes.id', '=', 'orden_items.orden_id')
            ->selectRaw('orden_items.producto_nombre as nombre, SUM(orden_items.importe) as total')
            ->where('ordenes.estado', EstadoOrden::Cobrada->value)
            ->where('orden_items.estado', '!=', 'cortado')
            ->whereBetween('ordenes.abierta_at', [$desde->fecha()->startOfDay(), $hasta->fecha()->endOfDay()])
            ->groupBy('orden_items.producto_nombre')
            ->orderByDesc('total')
            ->limit($limite)
            ->pluck('total', 'nombre')
            ->map(fn ($v) => round((float) $v, 2))
            ->all();
    }

    /** @return array<string, float> */
    public function porCategoria(int $dias = 30, ?FechaFiscal $hasta = null): array
    {
        $hasta = $hasta ?? FechaFiscal::hoy();
        $desde = $hasta->sumarDias(-$dias + 1);

        return DB::table('orden_items')
            ->join('ordenes', 'ordenes.id', '=', 'orden_items.orden_id')
            ->join('productos', 'productos.id', '=', 'orden_items.producto_id')
            ->leftJoin('categorias', 'categorias.id', '=', 'productos.categoria_id')
            ->selectRaw("COALESCE(categorias.nombre, 'Sin categorÃ­a') as categoria, SUM(orden_items.importe) as total")
            ->where('ordenes.estado', EstadoOrden::Cobrada->value)
            ->where('orden_items.estado', '!=', 'cortado')
            ->whereBetween('ordenes.abierta_at', [$desde->fecha()->startOfDay(), $hasta->fecha()->endOfDay()])
            ->groupBy('categoria')
            ->orderByDesc('total')
            ->pluck('total', 'categoria')
            ->map(fn ($v) => round((float) $v, 2))
            ->all();
    }

    /**
     * Utilidad por dÃ­a. Solo sale si los productos tienen costo cargado: sin
     * costo, la utilidad es un nÃºmero inventado, y un dueÃ±o prefiere un hueco
     * a un nÃºmero falso.
     *
     * @return array<string, float>|null
     */
    public function utilidadPorDia(int $dias = 30, ?FechaFiscal $hasta = null): ?array
    {
        $tieneCosto = DB::table('productos')->whereNotNull('precio_costo')->where('precio_costo', '>', 0)->exists();

        if (! $tieneCosto) {
            return null;
        }

        $hasta = $hasta ?? FechaFiscal::hoy();

        $ventas = $this->ventasPorDia($dias, $hasta);

        $costos = DB::table('orden_items')
            ->join('ordenes', 'ordenes.id', '=', 'orden_items.orden_id')
            ->join('productos', 'productos.id', '=', 'orden_items.producto_id')
            ->selectRaw('DATE(ordenes.abierta_at) as dia, SUM(orden_items.cantidad * productos.precio_costo) as costo')
            ->where('ordenes.estado', EstadoOrden::Cobrada->value)
            ->where('orden_items.estado', '!=', 'cortado')
            ->whereNotNull('productos.precio_costo')
            ->whereBetween('ordenes.abierta_at', [
                $hasta->sumarDias(-$dias + 1)->fecha()->startOfDay(),
                $hasta->fecha()->endOfDay(),
            ])
            ->groupBy('dia')
            ->pluck('costo', 'dia');

        $salida = [];

        foreach ($ventas as $fecha => $venta) {
            $salida[$fecha] = round($venta - (float) ($costos[$fecha] ?? 0), 2);
        }

        return $salida;
    }

    /**
     * Los nÃºmeros de arriba del todo, los que resume todo lo demÃ¡s.
     *
     * @return array<string, mixed>
     */
    public function resumenDelDia(?FechaFiscal $fecha = null): array
    {
        $fecha = $fecha ?? FechaFiscal::hoy();

        $delDia = DB::table('ordenes')
            ->selectRaw('COUNT(*) as ordenes, SUM(total) as total, SUM(propina) as propina, SUM(descuentos) as descuentos, SUM(comensales) as comensales')
            ->where('estado', EstadoOrden::Cobrada->value)
            ->whereDate('abierta_at', $fecha->iso())
            ->first();

        $total = (float) ($delDia->total ?? 0);
        $ordenes = (int) ($delDia->ordenes ?? 0);

        $utilidad = $this->utilidadDelDia($fecha);

        return [
            'fecha' => $fecha->iso(),
            'total' => $total,
            'ordenes' => $ordenes,
            'ticket_promedio' => $ordenes > 0 ? $total / $ordenes : 0.0,
            'propinas' => (float) ($delDia->propina ?? 0),
            'descuentos' => (float) ($delDia->descuentos ?? 0),
            'comensales' => (int) ($delDia->comensales ?? 0),
            'utilidad' => $utilidad,
            'igtf_del_dia' => $this->igtfDelDia($fecha),
        ];
    }

    /**
     * Utilidad del dÃ­a.
     */
    public function utilidadDelDia(FechaFiscal $fecha): ?float
    {
        $tieneCosto = DB::table('productos')->whereNotNull('precio_costo')->where('precio_costo', '>', 0)->exists();

        if (! $tieneCosto) {
            return null;
        }

        $total = (float) DB::table('ordenes')
            ->where('estado', EstadoOrden::Cobrada->value)
            ->whereDate('abierta_at', $fecha->iso())
            ->sum('total');

        $costo = (float) DB::table('orden_items')
            ->join('ordenes', 'ordenes.id', '=', 'orden_items.orden_id')
            ->join('productos', 'productos.id', '=', 'orden_items.producto_id')
            ->where('ordenes.estado', EstadoOrden::Cobrada->value)
            ->where('orden_items.estado', '!=', 'cortado')
            ->whereDate('ordenes.abierta_at', $fecha->iso())
            ->whereNotNull('productos.precio_costo')
            ->sum(DB::raw('orden_items.cantidad * productos.precio_costo'));

        return round($total - $costo, 2);
    }

    /**
     * IGTF del dÃ­a: la suma de los pagos en divisa por la alÃ­cuota vigente.
     */
    public function igtfDelDia(FechaFiscal $fecha): float
    {
        $params = app(Parametros::class);
        $alicuota = (float) $params->obtener('igtf.alicuota_actual', 0);

        if ($alicuota <= 0) {
            return 0.0;
        }

        $enDivisa = (float) DB::table('ordenes')
            ->where('estado', EstadoOrden::Cobrada->value)
            ->whereDate('abierta_at', $fecha->iso())
            ->where('moneda', 'USD')
            ->sum('total');

        return round($enDivisa * $alicuota / 100, 2);
    }

    /** @return array<int, Orden> */
    public function ultimasOrdenes(int $limite = 8): array
    {
        return Orden::query()
            ->with('items:id,orden_id,producto_nombre,cantidad,importe', 'mesero:id,name')
            ->latest('abierta_at')
            ->limit($limite)
            ->get()
            ->all();
    }

    /** @return array<string, float> Etiqueta legible => importe. */
    public function porCanal(int $dias = 30, ?FechaFiscal $hasta = null): array
    {
        $hasta = $hasta ?? FechaFiscal::hoy();
        $desde = $hasta->sumarDias(-$dias + 1);

        $filas = DB::table('ordenes')
            ->selectRaw('canal, SUM(total) as total')
            ->where('estado', EstadoOrden::Cobrada->value)
            ->whereBetween('abierta_at', [$desde->fecha()->startOfDay(), $hasta->fecha()->endOfDay()])
            ->groupBy('canal')
            ->pluck('total', 'canal');

        $etiquetas = ['mesa' => 'Comedor', 'para_llevar' => 'Para llevar', 'delivery' => 'Delivery'];
        $salida = [];

        foreach ($etiquetas as $codigo => $etiqueta) {
            $salida[$etiqueta] = round((float) ($filas[$codigo] ?? 0), 2);
        }

        return $salida;
    }

    public function totalDelPeriodo(int $dias = 30, ?FechaFiscal $hasta = null): Money
    {
        $hasta = $hasta ?? FechaFiscal::hoy();
        $desde = $hasta->sumarDias(-$dias + 1);

        $total = (float) DB::table('ordenes')
            ->where('estado', EstadoOrden::Cobrada->value)
            ->whereBetween('abierta_at', [$desde->fecha()->startOfDay(), $hasta->fecha()->endOfDay()])
            ->sum('total');

        return Money::deDolares((string) round($total, 2));
    }

    /** Etiquetas legibles de los mÃ©todos de pago. */
    public const METODOS = [
        'efectivo_usd' => 'Efectivo USD',
        'efectivo_bs' => 'Efectivo Bs',
        'pago_movil' => 'Pago mÃ³vil',
        'tarjeta_debito' => 'Tarjeta dÃ©bito',
        'transferencia_usd' => 'Transferencia USD',
        'punto_venta_usd' => 'POS divisa',
        'zelle' => 'Zelle',
    ];
}
