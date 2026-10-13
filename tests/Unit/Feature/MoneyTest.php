<?php

use App\Domain\Shared\Exceptions\MonedaIncompatibleException;
use App\Domain\Shared\ValueObjects\Decimal;
use App\Domain\Shared\ValueObjects\Moneda;
use App\Domain\Shared\ValueObjects\Money;

/**
 * CA-01 a CA-02: Money es exacto y no mezcla monedas.
 * Invariante I2 de AGENTS.md: el dinero nunca es float.
 */
it('calcula sin errores de punto flotante', function () {
    $diez = Money::deDolares('10.00');
    $diesCentavos = Money::deDolares('0.10');

    expect((string) $diez->restar($diesCentavos))->toBe('USD 9,90')
        ->and($diez->restar($diesCentavos)->esIgualA(Money::deDolares('9.90')))->toBeTrue();
});

it('reparte sin perder ni inventar centavos', function () {
    $total = Money::deBolivares('10.00');
    $partes = $total->repartirEn(3);

    expect(count($partes))->toBe(3);

    $suma = array_reduce(
        $partes,
        fn (Money $acumulado, Money $parte) => $acumulado->sumar($parte),
        Money::deCero(Moneda::Bolivar),
    );

    expect($suma->esIgualA($total))->toBeTrue()
        ->and($partes[0]->centavos())->toBe(334);
});

it('rechaza mezclar monedas', function () {
    Money::deDolares('10.00')->sumar(Money::deBolivares('5.00'));
})->throws(MonedaIncompatibleException::class);

it('calcula porcentajes exactos con decimal', function () {
    // 16% de 7.50 son 1.20 exactos, no 1.1999999999999997
    $iva = Money::deDolares('7.50')->porcentaje(16);

    expect($iva->formateado())->toBe('1,20');
});

it('no pierde precisión al encadenar sumas', function () {
    $subtotal = Money::deCero(Moneda::Dolar);

    for ($i = 0; $i < 10; $i++) {
        $subtotal = $subtotal->sumar(Money::deDolares('0.10'));
    }

    expect($subtotal->centavos())->toBe(100);
});

it('serializa a string y no a float en JSON', function () {
    // Escala 4: la misma representación que viaja a la columna decimal(18,4).
    // Un float en el JSON es un float perdido en el navegador.
    expect(json_encode(Money::deDolares('7.50')))->toBe('"7.5000"');
});

it('formatea al estilo venezolano', function () {
    expect(Money::deBolivares('1234.5')->formateado())->toBe('1.234,50')
        ->and(Money::deBolivares('1234.5')->formateadoConSimbolo())->toBe('Bs.1.234,50');
});

it('decimal exacto conserva mas de cuatro decimales', function () {
    $resultado = Decimal::deString('0.1')->sumar(Decimal::deString('0.2'));

    expect($resultado->__toString())->toBe('0.3');
});
