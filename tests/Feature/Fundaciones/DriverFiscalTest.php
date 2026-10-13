<?php

use App\Domain\Facturacion\Contracts\FiscalDocumentDriver;
use App\Domain\Facturacion\Drivers\SimulatedFiscalDriver;
use App\Domain\Facturacion\Dtos\DocumentoFiscalContexto;
use App\Domain\Facturacion\Dtos\LineaDocumento;
use App\Domain\Facturacion\Dtos\LineasDocumento;
use App\Domain\Facturacion\Dtos\NotaFiscalContexto;
use App\Domain\Facturacion\Dtos\PagoDocumento;
use App\Domain\Facturacion\Dtos\PagosDocumento;
use App\Domain\Facturacion\Dtos\ReporteCierreContexto;
use App\Domain\Facturacion\Dtos\ResultadoFiscal;
use App\Domain\Facturacion\Exceptions\DriverDesconectadoException;
use App\Domain\Facturacion\Exceptions\IdempotenciaVioladaException;
use App\Domain\Shared\ValueObjects\Decimal;
use App\Domain\Shared\ValueObjects\Moneda;
use App\Domain\Shared\ValueObjects\Money;

/**
 * Línea de precio final, con impuesto incluido, como la imprime una máquina
 * fiscal. La alícuota llega como string porque en el dominio sale de la tabla
 * de vigencias, nunca de una constante (invariante I1).
 */
function linea(
    string $codigo,
    string $descripcion,
    string $importe,
    string $alicuota = '16',
    Moneda $moneda = Moneda::Dolar,
): LineaDocumento {
    $bruto = Money::de($importe, $moneda);
    $porcentaje = Decimal::deString($alicuota);
    $base = $bruto->multiplicarPor(Decimal::deEntero(100)->dividir($porcentaje->sumar(Decimal::deEntero(100))));

    return new LineaDocumento(
        codigo: $codigo,
        descripcion: $descripcion,
        cantidad: Decimal::deEntero(1),
        precioUnitario: Decimal::deString($importe),
        importe: $bruto,
        alicuota: $alicuota === '0' ? 'IVA_E' : 'IVA_G',
        baseImponible: $base,
        impuesto: $bruto->restar($base),
        exento: $alicuota === '0',
    );
}

function documento(
    LineasDocumento $lineas,
    PagosDocumento $pagos,
    string $idempotencyKey = 'test-key-1',
): DocumentoFiscalContexto {
    return new DocumentoFiscalContexto(
        tipo: 'factura',
        idempotencyKey: $idempotencyKey,
        fechaFiscal: fecha('2026-10-12'),
        moneda: Moneda::Dolar,
        lineas: $lineas,
        pagos: $pagos,
        sucursalId: 1,
    );
}

function driver(): SimulatedFiscalDriver
{
    return new SimulatedFiscalDriver(serial: 'TEST-0001');
}

/**
 * El flujo completo contra el emisor: abrir, mandar líneas, mandar pagos,
 * cerrar. Es lo que hace el POS, así que los tests van por esta ruta en vez de
 * llamar a `cerrar()` suelto.
 */
function emitir(
    SimulatedFiscalDriver $driver,
    LineasDocumento $lineas,
    PagosDocumento $pagos,
    string $idempotencyKey = 'test-key-1',
): ResultadoFiscal {
    $contexto = documento($lineas, $pagos, $idempotencyKey);

    $sesion = $driver->abrir($contexto);
    $driver->registrarLineas($sesion, $lineas);
    $driver->registrarPagos($sesion, $pagos);

    return $driver->cerrar($contexto);
}

/** El 50 % del pago es en divisa, el resto en bolívares: el caso normal aquí. */
function pagosDe(Money ...$documento): PagosDocumento
{
    $pagos = PagosDocumento::vacio();

    foreach ($documento as $monto) {
        $pagos = $pagos->sumar(PagoDocumento::simple(
            metodo: 'pago_movil',
            monto: $monto,
        ));
    }

    return $pagos;
}

function unaFacturaDe(string $importe = '7.50'): LineasDocumento
{
    return LineasDocumento::de(linea('P001', 'Pabellon criollo', $importe));
}

it('emite una factura con numero fiscal y serial', function () {
    $resultado = emitir(driver(), unaFacturaDe(), pagosDe(Money::deDolares('7.50')));

    expect($resultado->numeroFiscal)->toBe('0001-00000001')
        ->and($resultado->serial)->toBe('TEST-0001')
        ->and($resultado->contador)->toBe(1)
        ->and($resultado->hash)->not->toBeEmpty()
        ->and($resultado->yaEmitido)->toBeFalse();
});

it('no duplica documento al reintentar la emision con la misma clave', function () {
    // CA-05 / invariante I5: un reintento de cola nunca puede duplicar.
    $driver = driver();
    $lineas = unaFacturaDe();
    $pagos = pagosDe(Money::deDolares('7.50'));

    $primero = emitir($driver, $lineas, $pagos, 'misma-clave');
    $segundo = emitir($driver, $lineas, $pagos, 'misma-clave');

    expect($segundo->numeroFiscal)->toBe($primero->numeroFiscal)
        ->and($segundo->contador)->toBe($primero->contador)
        ->and($segundo->yaEmitido)->toBeTrue();

    // El correlativo no avanzó: solo se emitió una vez.
    expect($driver->memoria()->ultimoNumeroFiscal())->toBe('0001-00000001');
});

it('emite documentos distintos con claves distintas', function () {
    $driver = driver();

    $primero = emitir($driver, unaFacturaDe(), pagosDe(Money::deDolares('7.50')), 'clave-a');
    $segundo = emitir($driver, unaFacturaDe(), pagosDe(Money::deDolares('7.50')), 'clave-b');

    expect($primero->numeroFiscal)->toBe('0001-00000001')
        ->and($segundo->numeroFiscal)->toBe('0001-00000002');
});

it('el reporte x no altera la memoria fiscal y el z si', function () {
    // CA-06 y CA-07. Esta es la diferencia entre una lectura y un cierre, y en
    // una máquina fiscal real el Z no se puede deshacer.
    $driver = driver();

    emitir($driver, unaFacturaDe(), pagosDe(Money::deDolares('7.50')), 'factura-1');
    emitir($driver, unaFacturaDe(), pagosDe(Money::deDolares('7.50')), 'factura-2');

    $contexto = new ReporteCierreContexto(
        tipo: 'X',
        fechaFiscal: fecha('2026-10-12'),
        sucursalId: 1,
    );

    $x = $driver->reporteX($contexto);

    expect($x->cantidadDocumentos)->toBe(2)
        ->and($x->afectoMemoriaFiscal)->toBeFalse()
        ->and($x->contadorZ)->toBeNull();

    // El X no reseteo nada: el Z sigue viendo las dos facturas.
    $z = $driver->reporteZ($contexto);

    expect($z->cantidadDocumentos)->toBe(2)
        ->and($z->afectoMemoriaFiscal)->toBeTrue()
        ->and($z->contadorZ)->toBe(1);

    // Turno reseteado.
    expect($driver->reporteX($contexto)->cantidadDocumentos)->toBe(0);
});

it('la nota de credito no borra el documento original', function () {
    // CA-08 / invariante I3.
    $driver = driver();
    $lineas = unaFacturaDe();
    $pagos = pagosDe(Money::deDolares('7.50'));

    $factura = emitir($driver, $lineas, $pagos, 'factura-a');

    $nota = $driver->emitirNota(new NotaFiscalContexto(
        tipo: 'nota_credito',
        idempotencyKey: 'nota-a',
        fechaFiscal: fecha('2026-10-12'),
        documentoPadreNumeroFiscal: $factura->numeroFiscal,
        documentoPadreId: 1,
        lineas: $lineas,
        pagos: $pagos,
        motivo: 'El cliente devolvio el plato',
        sucursalId: 1,
    ));

    expect($nota->numeroFiscal)->not->toBe($factura->numeroFiscal)
        ->and($nota->crudo['documento_padre'])->toBe($factura->numeroFiscal)
        // El original sigue existiendo, con su numero intacto.
        ->and($driver->memoria()->buscarPorNumeroFiscal($factura->numeroFiscal))->not->toBeNull()
        ->and($driver->memoria()->numeroNota($factura->numeroFiscal))->toBe($nota->numeroFiscal);
});

it('no deja emitir dos notas contra el mismo documento', function () {
    $driver = driver();
    $lineas = unaFacturaDe();
    $pagos = pagosDe(Money::deDolares('7.50'));

    $factura = emitir($driver, $lineas, $pagos, 'factura-b');

    $nota = fn (string $clave) => new NotaFiscalContexto(
        tipo: 'nota_credito',
        idempotencyKey: $clave,
        fechaFiscal: fecha('2026-10-12'),
        documentoPadreNumeroFiscal: $factura->numeroFiscal,
        documentoPadreId: 1,
        lineas: $lineas,
        pagos: $pagos,
        motivo: 'Devolucion',
        sucursalId: 1,
    );

    $driver->emitirNota($nota('nota-1'));
    $driver->emitirNota($nota('nota-2'));
})->throws(IdempotenciaVioladaException::class);

it('rechaza una nota contra un documento que no existe', function () {
    driver()->emitirNota(new NotaFiscalContexto(
        tipo: 'nota_credito',
        idempotencyKey: 'nota-fantasma',
        fechaFiscal: fecha('2026-10-12'),
        documentoPadreNumeroFiscal: '0001-00099999',
        documentoPadreId: 99,
        lineas: unaFacturaDe(),
        pagos: pagosDe(Money::deDolares('7.50')),
        motivo: 'Prueba',
        sucursalId: 1,
    ));
})->throws(IdempotenciaVioladaException::class);

it('rechaza el pago que no cuadra con el documento', function () {
    emitir(
        driver(),
        unaFacturaDe('7.50'),
        pagosDe(Money::deDolares('5.00')),
    );
})->throws(LogicException::class, 'no cuadra');

it('acepta pago mixto y lo refleja en el payload emitido', function () {
    $driver = driver();

    // El documento está en bolívares: el cliente entregó $2,50 en efectivo y
    // cubrió el resto en bolívares. La conversión la hizo quien armó el
    // documento, no el driver.
    $lineas = LineasDocumento::de(
        linea('P001', 'Pabellon criollo', '700.00', '16', Moneda::Bolivar),
    );

    $pagos = PagosDocumento::de(
        PagoDocumento::enDivisa(
            metodo: 'efectivo_usd',
            montoEnDivisa: Money::deDolares('2.50'),
            montoEnDocumento: Money::deBolivares('250.00'),
        ),
        PagoDocumento::simple('pago_movil', Money::deBolivares('450.00')),
    );

    $resultado = emitir($driver, $lineas, $pagos);

    expect($pagos->esPagoMixto())->toBeTrue()
        ->and($resultado->crudo['pago_mixto'])->toBeTrue()
        ->and($resultado->total->formateado())->toBe('700,00')
        ->and($resultado->total->moneda())->toBe(Moneda::Bolivar)
        // El nativo en dólares se conserva para el IGTF y para la caja.
        ->and($pagos->nativoPor('USD')->formateado())->toBe('2,50');
});

it('desglosa los impuestos por alicuota', function () {
    $desglose = LineasDocumento::de(
        linea('P001', 'Pabellon', '10.00', '16'),
        linea('P002', 'Insumo exento', '5.00', '0'),
    )->impuestosPorAlicuota();

    expect($desglose)->toHaveKeys(['IVA_G', 'IVA_E'])
        ->and($desglose['IVA_G']->formateado())->toBe('1,38')
        ->and($desglose['IVA_E']->formateado())->toBe('0,00');
});

it('expone el serial y el estado del emisor', function () {
    $estado = driver()->estado();

    expect($estado->enLinea)->toBeTrue()
        ->and($estado->serial)->toBe('TEST-0001')
        ->and($estado->hayFallo())->toBeFalse();
});

it('el driver caido lanza excepcion recuperable, no pantalla en blanco', function () {
    $driver = new SimulatedFiscalDriver(serial: 'TEST-0002', tasaFallo: 1.0);

    expect($driver->estado()->enLinea)->toBeFalse();

    emitir($driver, unaFacturaDe(), pagosDe(Money::deDolares('7.50')));
})->throws(DriverDesconectadoException::class);

it('es el driver por defecto segun FISCAL_DRIVER', function () {
    expect(app(FiscalDocumentDriver::class))->toBeInstanceOf(SimulatedFiscalDriver::class);
});
