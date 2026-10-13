<?php

namespace App\Domain\Facturacion\Drivers;

use App\Domain\Facturacion\Contracts\DriverCapabilities;
use App\Domain\Facturacion\Contracts\EstadoDriver;
use App\Domain\Facturacion\Contracts\FiscalDocumentDriver;
use App\Domain\Facturacion\Dtos\DocumentoFiscalContexto;
use App\Domain\Facturacion\Dtos\LineasDocumento;
use App\Domain\Facturacion\Dtos\NotaFiscalContexto;
use App\Domain\Facturacion\Dtos\PagosDocumento;
use App\Domain\Facturacion\Dtos\ReporteCierreContexto;
use App\Domain\Facturacion\Dtos\ResultadoFiscal;
use App\Domain\Facturacion\Dtos\ResultadoReporte;
use App\Domain\Facturacion\Exceptions\DriverDesconectadoException;
use App\Domain\Facturacion\Exceptions\IdempotenciaVioladaException;
use App\Domain\Facturacion\Values\MemoriaFiscal;
use App\Domain\Facturacion\Values\SesionFiscal;

/**
 * Emisor fiscal del demo.
 *
 * No devuelve basura: mantiene memoria fiscal de verdad — correlativo por
 * serie, contador Z, turno acumulado, documentos ya emitidos por clave de
 * idempotencia — y se comporta como una máquina real. Eso es lo que permite
 * mostrarle al cliente reportes X y Z con números creíbles, y es lo que valida
 * el contrato de la interfaz antes de que exista el hardware.
 *
 * Lo que NO hace es simular los límites de una máquina fiscal de verdad
 * (memoria no volátil, corte de energía a media emisión). Eso se prueba cuando
 * exista el driver real.
 */
final class SimulatedFiscalDriver implements FiscalDocumentDriver
{
    private MemoriaFiscal $memoria;

    public function __construct(
        ?string $serial = null,
        private float $tasaFallo = 0.0,
    ) {
        $this->memoria = new MemoriaFiscal(
            serial: $serial ?? config('facturacion.serial_simulado', 'DEMO-SRG-0001'),
        );
    }

    public function nombre(): string
    {
        return 'simulated';
    }

    public function capacidades(): DriverCapabilities
    {
        return DriverCapabilities::deSimulador();
    }

    public function abrir(DocumentoFiscalContexto $contexto): SesionFiscal
    {
        $this->asegurarOperativo();

        if (count($contexto->lineas) === 0) {
            throw new \InvalidArgumentException('No se puede abrir un documento fiscal sin líneas.');
        }

        if ($this->memoria->tieneSesionAbierta()) {
            $this->memoria->cerrarSesion();
        }

        return $this->memoria->abrirSesion(
            tipo: $contexto->tipo,
            serie: $this->serie($contexto),
            fechaFiscal: $contexto->fechaFiscal,
            moneda: $contexto->moneda,
            sucursalId: $contexto->sucursalId,
            ordenId: $contexto->ordenId,
        );
    }

    public function registrarLineas(SesionFiscal $sesion, LineasDocumento $lineas): void
    {
        $this->asegurarOperativo();

        if (! $sesion->abierta()) {
            throw new \LogicException('La sesión fiscal está cerrada: hay que abrir el documento antes de enviar líneas.');
        }

        if (count($lineas) > $this->capacidades()->maximoLineas) {
            throw new \LogicException(sprintf(
                'El documento tiene %d líneas y el emisor admite %d.',
                count($lineas),
                $this->capacidades()->maximoLineas,
            ));
        }

        $this->memoria->agregarLineas($lineas);
    }

    public function registrarPagos(SesionFiscal $sesion, PagosDocumento $pagos): void
    {
        $this->asegurarOperativo();

        if (! $sesion->abierta()) {
            throw new \LogicException('La sesión fiscal está cerrada: hay que abrir el documento antes de enviar pagos.');
        }

        if (count($pagos) === 0) {
            throw new \InvalidArgumentException('Un documento fiscal no puede cerrarse sin pagos.');
        }

        if (! $this->capacidades()->soportaPagoMixto && $pagos->esPagoMixto()) {
            throw new \LogicException('Este emisor no admite pago mixto en un mismo documento.');
        }

        $this->memoria->agregarPagos($pagos);
    }

    public function cerrar(DocumentoFiscalContexto $contexto): ResultadoFiscal
    {
        $this->asegurarOperativo();

        // Invariante I5: si esta clave ya se emitió, se devuelve el mismo
        // resultado. Nunca un segundo documento.
        $previo = $this->memoria->buscarPorIdempotencia($contexto->idempotencyKey);

        if ($previo !== null) {
            return $previo->comoReemision();
        }

        $sesion = $this->memoria->sesionAbierta();

        if ($sesion === null) {
            throw new \LogicException('No hay ningún documento abierto en la memoria fiscal.');
        }

        $lineas = $this->memoria->lineas();
        $pagos = $this->memoria->pagos();

        $total = $pagos->total();
        $correlativo = $this->memoria->proximoCorrelativo($sesion->serie, $contexto->fechaFiscal);
        $numeroFiscal = $sesion->serie.'-'.str_pad((string) $correlativo, 8, '0', STR_PAD_LEFT);

        // Un emisor real rechaza el pago si no cuadra con el total del
        // documento. El simulador también, para que el bug aparezca en el test
        // y no frente al cliente.
        $esperado = $lineas->total();

        if (! $total->esIgualA($esperado)) {
            throw new \LogicException(sprintf(
                'El pago (%s) no cuadra con el documento (%s).',
                $total->formateado(),
                $esperado->formateado(),
            ));
        }

        $resultado = new ResultadoFiscal(
            documentoId: $contexto->ordenId,
            numeroFiscal: $numeroFiscal,
            serial: $this->memoria->serial(),
            contador: $this->memoria->proximoContador(),
            hash: $this->hash($contexto, $numeroFiscal),
            emitidoEn: $contexto->fechaFiscal,
            moneda: $contexto->moneda,
            total: $total,
            driver: $this->nombre(),
            crudo: $this->memoria->payloadEmitido($sesion, $lineas, $pagos, $numeroFiscal),
        );

        $this->memoria->registrarEmision($contexto->idempotencyKey, $resultado, $contexto->fechaFiscal);
        $this->memoria->cerrarSesion();

        return $resultado;
    }

    public function emitirNota(NotaFiscalContexto $contexto): ResultadoFiscal
    {
        $this->asegurarOperativo();

        $previo = $this->memoria->buscarPorIdempotencia($contexto->idempotencyKey);

        if ($previo !== null) {
            return $previo->comoReemision();
        }

        $original = $this->memoria->buscarPorNumeroFiscal($contexto->documentoPadreNumeroFiscal);

        if ($original === null) {
            throw new IdempotenciaVioladaException(
                "No se puede emitir {$contexto->tipo}: el documento {$contexto->documentoPadreNumeroFiscal} "
                .'no existe en la memoria fiscal del emisor.'
            );
        }

        if ($this->memoria->tieneNotaContra($original->numeroFiscal)) {
            throw new IdempotenciaVioladaException(
                "El documento {$original->numeroFiscal} ya tiene una nota de crédito o débito."
            );
        }

        $serie = $this->serieParaTipo($contexto->tipo);
        $correlativo = $this->memoria->proximoCorrelativo($serie, $contexto->fechaFiscal);
        $numeroFiscal = $serie.'-'.str_pad((string) $correlativo, 8, '0', STR_PAD_LEFT);

        $resultado = new ResultadoFiscal(
            documentoId: $contexto->ordenId,
            numeroFiscal: $numeroFiscal,
            serial: $this->memoria->serial(),
            contador: $this->memoria->proximoContador(),
            hash: $this->hash($contexto, $numeroFiscal),
            emitidoEn: $contexto->fechaFiscal,
            moneda: $contexto->pagos->total()->moneda(),
            total: $contexto->pagos->total(),
            driver: $this->nombre(),
            crudo: [
                'documento_padre' => $original->numeroFiscal,
                'motivo' => $contexto->motivo,
                'lineas' => $contexto->lineas->aArray(),
            ],
        );

        $this->memoria->registrarNota($original->numeroFiscal, $numeroFiscal);
        $this->memoria->registrarEmision($contexto->idempotencyKey, $resultado, $contexto->fechaFiscal);

        return $resultado;
    }

    /**
     * Reporte X: lectura del turno. No toca la memoria fiscal ni el correlativo,
     * que es exactamente lo que distingue a un X de un Z.
     */
    public function reporteX(ReporteCierreContexto $contexto): ResultadoReporte
    {
        $this->asegurarOperativo();

        $turno = $this->memoria->turno($contexto->fechaFiscal, $contexto->sucursalId);

        return new ResultadoReporte(
            tipo: 'X',
            fecha: $contexto->fechaFiscal,
            sucursalId: $contexto->sucursalId,
            total: $turno->total,
            cantidadDocumentos: $turno->cantidad,
            contadorZ: $this->memoria->ultimoContadorZ(),
            afectoMemoriaFiscal: false,
            serial: $this->memoria->serial(),
            crudo: [
                'modo' => 'lectura',
                'consumibles_restantes' => $this->memoria->consumiblesRestantes(),
            ],
        );
    }

    /**
     * Reporte Z: cierre fiscal. Graba en memoria no volátil y resetea el turno.
     * En una máquina real este paso no se puede deshacer.
     */
    public function reporteZ(ReporteCierreContexto $contexto): ResultadoReporte
    {
        $this->asegurarOperativo();

        $turno = $this->memoria->turno($contexto->fechaFiscal, $contexto->sucursalId);
        $contadorZ = $this->memoria->registrarCierreZ($contexto->fechaFiscal, $contexto->sucursalId);

        return new ResultadoReporte(
            tipo: 'Z',
            fecha: $contexto->fechaFiscal,
            sucursalId: $contexto->sucursalId,
            total: $turno->total,
            cantidadDocumentos: $turno->cantidad,
            contadorZ: $contadorZ,
            afectoMemoriaFiscal: true,
            serial: $this->memoria->serial(),
            crudo: [
                'modo' => 'cierre',
                'consumibles_restantes' => $this->memoria->consumiblesRestantes(),
                'total' => $turno->total->formateado(),
            ],
        );
    }

    public function estado(): EstadoDriver
    {
        if ($this->estaCaido()) {
            return new EstadoDriver(
                enLinea: false,
                nombre: $this->nombre(),
                detalle: 'El emisor fiscal simulado está marcado como desconectado (modo de falla).',
            );
        }

        return new EstadoDriver(
            enLinea: true,
            nombre: $this->nombre(),
            ultimoCorrelativo: $this->memoria->ultimoCorrelativo(),
            ultimoNumeroFiscal: $this->memoria->ultimoNumeroFiscal(),
            ultimoContadorZ: $this->memoria->ultimoContadorZ(),
            serial: $this->memoria->serial(),
        );
    }

    /** Memoria fiscal completa. El test de reportes X y Z la inspecciona. */
    public function memoria(): MemoriaFiscal
    {
        return $this->memoria;
    }

    private function asegurarOperativo(): void
    {
        if ($this->estaCaido()) {
            throw DriverDesconectadoException::simuladorCaido();
        }
    }

    private function estaCaido(): bool
    {
        if ($this->tasaFallo <= 0.0) {
            return false;
        }

        return (mt_rand() / mt_getrandmax()) < $this->tasaFallo;
    }

    private function serie(DocumentoFiscalContexto $contexto): string
    {
        return $this->serieParaTipo($contexto->tipo);
    }

    private function serieParaTipo(string $tipo): string
    {
        return match ($tipo) {
            'factura' => config('facturacion.serie_factura', '0001'),
            'nota_credito' => config('facturacion.serie_nota_credito', '0002'),
            'nota_debito' => config('facturacion.serie_nota_debito', '0003'),
            default => throw new \InvalidArgumentException("Tipo de documento fiscal desconocido: {$tipo}"),
        };
    }

    private function hash(object $contexto, string $numeroFiscal): string
    {
        return hash('sha256', $numeroFiscal.'|'.$contexto->idempotencyKey);
    }
}
