<?php

namespace App\Domain\Facturacion\Values;

use App\Domain\Facturacion\Dtos\LineasDocumento;
use App\Domain\Facturacion\Dtos\PagosDocumento;
use App\Domain\Facturacion\Dtos\ResultadoFiscal;
use App\Domain\Shared\Support\FechaFiscal;
use App\Domain\Shared\ValueObjects\Moneda;
use App\Domain\Shared\ValueObjects\Money;

/**
 * Memoria fiscal del emisor simulado.
 *
 * Hace las veces de la memoria no volátil de una impresora fiscal: guarda el
 * correlativo por serie y fecha, el contador Z, el turno acumulado y los
 * documentos ya emitidos por clave de idempotencia.
 *
 * Existe aparte del driver para que los tests puedan inspeccionarla. Que un
 * Reporte X no la modifique y que un Reporte Z sí la cierre es una afirmación
 * que se tiene que poder verificar, y eso solo se verifica si alguien puede
 * mirarla.
 */
final class MemoriaFiscal
{
    /** Documento abierto en la memoria fiscal, si hay uno. */
    private ?SesionFiscal $sesionAbierta = null;

    private LineasDocumento $lineas;

    private PagosDocumento $pagos;

    /** @var array<string, int> correlativo por "serie|fecha" */
    private array $correlativos = [];

    /** @var array<string, int> numero de facturas por fecha */
    private array $documentosPorTurno = [];

    /** @var array<string, Money> total del turno por fecha */
    private array $totalPorTurno = [];

    /** @var array<string, ResultadoFiscal> emitido por idempotencyKey */
    private array $emitidos = [];

    /** @var array<string, string> número fiscal -> número fiscal de su nota */
    private array $notas = [];

    private int $contador = 0;

    private int $contadorZ = 0;

    private int $consumiblesRestantes;

    /** @var array<string, string> número fiscal -> idempotencyKey */
    private array $keyPorNumero = [];

    public function __construct(
        private readonly string $serial,
        int $consumibles = 1_000,
    ) {
        $this->lineas = LineasDocumento::de();
        $this->pagos = PagosDocumento::vacio();
        $this->consumiblesRestantes = $consumibles;
    }

    public function serial(): string
    {
        return $this->serial;
    }

    public function abrirSesion(
        string $tipo,
        string $serie,
        FechaFiscal $fechaFiscal,
        Moneda $moneda,
        ?int $ordenId,
    ): SesionFiscal {
        $this->lineas = LineasDocumento::de();
        $this->pagos = PagosDocumento::vacio();

        $this->sesionAbierta = new SesionFiscal(
            token: bin2hex(random_bytes(16)),
            tipo: $tipo,
            serie: $serie,
            fechaFiscal: $fechaFiscal,
            moneda: $moneda,
            ordenId: $ordenId,
        );

        return $this->sesionAbierta;
    }

    public function sesionAbierta(): ?SesionFiscal
    {
        return $this->sesionAbierta;
    }

    public function tieneSesionAbierta(): bool
    {
        return $this->sesionAbierta !== null;
    }

    public function cerrarSesion(): void
    {
        $this->sesionAbierta = null;
        $this->lineas = LineasDocumento::de();
        $this->pagos = PagosDocumento::vacio();
    }

    public function agregarLineas(LineasDocumento $lineas): void
    {
        foreach ($lineas as $linea) {
            $this->lineas = $this->lineas->agregar($linea);
        }
    }

    public function agregarPagos(PagosDocumento $pagos): void
    {
        foreach ($pagos as $pago) {
            $this->pagos = $this->pagos->sumar($pago);
        }
    }

    public function lineas(): LineasDocumento
    {
        return $this->lineas;
    }

    public function pagos(): PagosDocumento
    {
        return $this->pagos;
    }

    public function proximoCorrelativo(string $serie, FechaFiscal $fecha): int
    {
        $clave = $this->claveCorrelativo($serie, $fecha);
        $siguiente = ($this->correlativos[$clave] ?? 0) + 1;
        $this->correlativos[$clave] = $siguiente;

        return $siguiente;
    }

    public function proximoContador(): int
    {
        return ++$this->contador;
    }

    public function ultimoCorrelativo(): ?int
    {
        return $this->correlativos === [] ? null : max($this->correlativos);
    }

    public function ultimoNumeroFiscal(): ?string
    {
        if ($this->keyPorNumero === []) {
            return null;
        }

        return array_key_last($this->keyPorNumero);
    }

    public function ultimoContadorZ(): ?int
    {
        return $this->contadorZ === 0 ? null : $this->contadorZ;
    }

    public function consumiblesRestantes(): int
    {
        return $this->consumiblesRestantes;
    }

    public function turno(FechaFiscal $fecha): TurnoFiscal
    {
        $clave = $this->claveTurno($fecha);

        $total = $this->totalPorTurno[$clave]
            ?? Money::deCero($this->sesionAbierta->moneda ?? Moneda::Dolar);

        return new TurnoFiscal(
            total: $total,
            cantidad: $this->documentosPorTurno[$clave] ?? 0,
        );
    }

    public function registrarEmision(
        string $idempotencyKey,
        ResultadoFiscal $resultado,
        FechaFiscal $fecha,

    ): void {
        $this->emitidos[$idempotencyKey] = $resultado;
        $this->keyPorNumero[$resultado->numeroFiscal] = $idempotencyKey;

        $clave = $this->claveTurno($fecha);

        $this->documentosPorTurno[$clave] = ($this->documentosPorTurno[$clave] ?? 0) + 1;
        $this->totalPorTurno[$clave] = isset($this->totalPorTurno[$clave])
            ? $this->totalPorTurno[$clave]->sumar($resultado->total)
            : $resultado->total;

        $this->consumiblesRestantes = max(0, $this->consumiblesRestantes - 1);
    }

    /**
     * El Reporte Z graba el contador y resetea el turno. Devuelve el contador Z
     * que quedó en la memoria fiscal.
     */
    public function registrarCierreZ(FechaFiscal $fecha): int
    {
        $this->contadorZ++;
        unset(
            $this->documentosPorTurno[$this->claveTurno($fecha)],
            $this->totalPorTurno[$this->claveTurno($fecha)],
        );

        return $this->contadorZ;
    }

    public function buscarPorIdempotencia(string $idempotencyKey): ?ResultadoFiscal
    {
        return $this->emitidos[$idempotencyKey] ?? null;
    }

    public function buscarPorNumeroFiscal(string $numeroFiscal): ?ResultadoFiscal
    {
        $key = $this->keyPorNumero[$numeroFiscal] ?? null;

        return $key === null ? null : ($this->emitidos[$key] ?? null);
    }

    public function tieneNotaContra(string $numeroFiscalPadre): bool
    {
        return isset($this->notas[$numeroFiscalPadre]);
    }

    public function registrarNota(string $numeroPadre, string $numeroNota): void
    {
        $this->notas[$numeroPadre] = $numeroNota;
    }

    public function numeroNota(string $numeroPadre): ?string
    {
        return $this->notas[$numeroPadre] ?? null;
    }

    /** @return array<string, mixed> */
    public function payloadEmitido(
        SesionFiscal $sesion,
        LineasDocumento $lineas,
        PagosDocumento $pagos,
        string $numeroFiscal,
    ): array {
        return [
            'serial' => $this->serial,
            'numero_fiscal' => $numeroFiscal,
            'fecha' => $sesion->fechaFiscal->iso(),
            'moneda' => $sesion->moneda->value,
            'lineas' => $lineas->aArray(),
            'pagos' => $pagos->aArray(),
            'impuestos_por_alicuota' => array_map(
                fn (Money $m) => $m->formateado(),
                $lineas->impuestosPorAlicuota(),
            ),
            'igtf' => $pagos->igtfTotal()->formateado(),
            'pago_mixto' => $pagos->esPagoMixto(),
        ];
    }

    private function claveCorrelativo(string $serie, FechaFiscal $fecha): string
    {
        return $serie.'|'.$fecha->iso();
    }

    private function claveTurno(FechaFiscal $fecha): string
    {
        return $fecha->iso();
    }
}
