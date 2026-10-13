<?php

namespace App\Domain\Facturacion\Contracts;

use App\Domain\Facturacion\Dtos\DocumentoFiscalContexto;
use App\Domain\Facturacion\Dtos\LineasDocumento;
use App\Domain\Facturacion\Dtos\NotaFiscalContexto;
use App\Domain\Facturacion\Dtos\PagosDocumento;
use App\Domain\Facturacion\Dtos\ReporteCierreContexto;
use App\Domain\Facturacion\Dtos\ResultadoFiscal;
use App\Domain\Facturacion\Dtos\ResultadoReporte;
use App\Domain\Facturacion\Values\SesionFiscal;

/**
 * El emisor fiscal del restaurante. Hay tres posibles y ninguno está decidido:
 * impresora fiscal autorizada, facturación electrónica ante un proveedor
 * certificado, o el simulador del demo.
 *
 * Por eso esto es una interfaz y no un módulo atado a un hardware. Cambiar de
 * emisor es cambiar `FISCAL_DRIVER` en el `.env`, no reescribir el POS.
 *
 * Contrato de dinero: todo importe que cruza esta frontera viaja como `Money`.
 * Ningún método recibe ni devuelve float. Invariante I2.
 *
 * Contrato de idempotencia: `cerrar()` y `emitirNota()` reciben un contexto con
 * `idempotencyKey`. Llamarlos dos veces con la misma clave devuelve el mismo
 * `numeroFiscal` y no emite un segundo documento. Invariante I5.
 */
interface FiscalDocumentDriver
{
    /** Identificador legible: "simulated", "spooler_hka", "electronic". */
    public function nombre(): string;

    /** Qué puede y qué no puede hacer este emisor. El POS lo consulta. */
    public function capacidades(): DriverCapabilities;

    /** Abre el documento en el emisor y devuelve la sesión abierta. */
    public function abrir(DocumentoFiscalContexto $contexto): SesionFiscal;

    public function registrarLineas(SesionFiscal $sesion, LineasDocumento $lineas): void;

    /**
     * Pago mixto: la sesión conserva el desglose de cada método. El IGTF se
     * calcula aparte en la spec 070 y llega ya resuelto dentro del contexto.
     */
    public function registrarPagos(SesionFiscal $sesion, PagosDocumento $pagos): void;

    /** Cierra y emite. Idempotente por `idempotencyKey`. */
    public function cerrar(DocumentoFiscalContexto $contexto): ResultadoFiscal;

    /** Emite nota de crédito o débito referenciando el documento original. */
    public function emitirNota(NotaFiscalContexto $contexto): ResultadoFiscal;

    /** Reporte X: lectura del turno, NO altera la memoria fiscal. */
    public function reporteX(ReporteCierreContexto $contexto): ResultadoReporte;

    /** Reporte Z: cierre fiscal, graba en memoria y resetea el turno. */
    public function reporteZ(ReporteCierreContexto $contexto): ResultadoReporte;

    /** En línea, último correlativo, contadores de memoria fiscal. */
    public function estado(): EstadoDriver;
}
