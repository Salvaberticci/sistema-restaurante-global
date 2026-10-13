<?php

use App\Domain\Auditoria\Contracts\RegistraAuditoria;
use App\Domain\Auditoria\Models\Auditoria;

/**
 * CA-04 / invariante I6: la auditoría va en la misma transacción que el cambio.
 * Si el cambio falla, no queda entrada: una auditoría de algo que no pasó miente.
 */
it('la auditoria se revierte si la transaccion falla', function () {
    DB::beginTransaction();

    Auditoria::create([
        'accion' => 'producto.precio_actualizado',
        'entidad' => 'Prueba',
        'entidad_id' => '1',
        'antes' => ['valor' => 10],
        'despues' => ['valor' => 20],
    ]);

    DB::rollBack();

    expect(Auditoria::count())->toBe(0);
});

it('guarda solo los campos que cambiaron', function () {
    $servicio = app(RegistraAuditoria::class);

    $diferencias = $servicio->diferencias(
        ['precio' => 10, 'nombre' => 'Pabellon', 'activo' => true],
        ['precio' => 15, 'nombre' => 'Pabellon', 'activo' => true],
    );

    expect($diferencias['antes'])->toBe(['precio' => 10])
        ->and($diferencias['despues'])->toBe(['precio' => 15]);
});

it('no deja modificar una entrada de auditoria', function () {
    $entrada = Auditoria::create([
        'accion' => 'prueba',
        'entidad' => 'Prueba',
        'entidad_id' => '1',
    ]);

    $entrada->accion = 'manipulada';
    $entrada->save();
})->throws(LogicException::class, 'no se modifican');

it('no deja borrar una entrada de auditoria', function () {
    Auditoria::create([
        'accion' => 'prueba',
        'entidad' => 'Prueba',
        'entidad_id' => '1',
    ])->delete();
})->throws(LogicException::class, 'no se borran');

it('la fecha fiscal usa America/Caracas', function () {
    // Invariante I4.
    expect(config('app.timezone'))->toBe('America/Caracas')
        ->and(fecha('2026-10-12')->iso())->toBe('2026-10-12');
});
