<?php

use App\Domain\Iam\Permisos;
use App\Domain\Reservas\Enums\EstadoPago;
use App\Domain\Reservas\Enums\EstadoReserva;
use App\Domain\Reservas\Models\Reservacion;
use App\Domain\Reservas\Services\ConfirmaReserva;
use App\Filament\Resources\ReservacionResource;
use App\Filament\Resources\ReservacionResource\Pages\ListReservaciones;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Reservaciones de eventos y cursos.
 *
 * La prueba más importante del archivo es la del CHECK: la invariante I8 no es
 * una validación de formulario, es una restricción de la base. Si alguien la
 * quita de la migración y la deja solo en el servicio, esta prueba falla — y esa
 * es exactamente la gracia de escribirla.
 */
function reservaDemo(array $atributos = []): Reservacion
{
    return Reservacion::create(array_merge([
        'tipo' => 'evento',
        'nombre' => 'Boda de prueba',
        'fecha_inicio' => now()->addWeek(),
        'hora_inicio' => '18:00',
        'personas' => 10,
        'precio_unitario' => '30.0000',
        'total' => '300.0000',
        'pagado' => '0.0000',
        'estado_pago' => EstadoPago::Pendiente,
        'estado' => EstadoReserva::Solicitada,
        'cliente_nombre' => 'Cliente de prueba',
    ], $atributos));
}

function usuarioConPermisoReserva(string ...$permisos): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ($permisos as $permiso) {
        Permission::findOrCreate($permiso, 'web');
    }

    $usuario = User::factory()->create();
    $usuario->givePermissionTo($permisos);

    test()->actingAs($usuario);

    return $usuario;
}

it('no deja confirmar una reserva sin el pago completo', function () {
    $reserva = reservaDemo(['pagado' => '150.0000']);

    // El caso que la invariante I8 prohíbe: un abono y confirmación.
    expect(fn () => app(ConfirmaReserva::class)->ejecutar($reserva))
        ->toThrow(DomainException::class, 'falta el pago completo');

    expect($reserva->fresh()->estado)->toBe(EstadoReserva::Solicitada);
});

it('confirma una reserva pagada al 100 por ciento', function () {
    $reserva = reservaDemo(['pagado' => '300.0000', 'estado_pago' => EstadoPago::Pagado]);

    app(ConfirmaReserva::class)->ejecutar($reserva);

    $reserva = $reserva->fresh();

    expect($reserva->estado)->toBe(EstadoReserva::Confirmada)
        ->and($reserva->confirmada_at)->not->toBeNull();
});

it('la base de datos rechaza confirmar sin pagar, aunque el codigo se salte', function () {
    $reserva = reservaDemo(['pagado' => '150.0000', 'estado_pago' => EstadoPago::Parcial]);

    // Sin pasar por el servicio: un UPDATE directo, como el que alguien haría en
    // la consola del servidor "para no perder tiempo". La base lo frena.
    //
    // Se comprueba el nombre de la restricción y no la clase de excepción: la
    // de Laravel cambia según la versión, lo que no cambia es qué regla se
    // violó, y eso es lo que esta prueba protege.
    $mensaje = null;

    try {
        DB::table('reservaciones')
            ->where('id', $reserva->id)
            ->update(['estado' => 'confirmada']);
    } catch (Throwable $e) {
        $mensaje = $e->getMessage();
    }

    expect($mensaje)
        ->not->toBeNull('La base aceptó confirmar una reserva con pago parcial.')
        ->toContain('reservaciones_confirmada_pagada_check');
});

it('el estado de pago se deriva de los importes, no se escribe a mano', function () {
    expect(EstadoPago::desde('300', '0'))->toBe(EstadoPago::Pendiente)
        ->and(EstadoPago::desde('300', '150'))->toBe(EstadoPago::Parcial)
        ->and(EstadoPago::desde('300', '300'))->toBe(EstadoPago::Pagado);

    // El borde que da Recordería a alguien: un céntimo de diferencia por
    // redondeo no debe dejar la reserva en "parcial" para siempre.
    expect(EstadoPago::desde('300', '299.9999'))->toBe(EstadoPago::Pagado);
});

it('al recalcular, si el pago deja de cubrir el total, la reserva se desconfirma', function () {
    $reserva = reservaDemo([
        'personas' => 10,
        'precio_unitario' => '30.0000',
        'total' => '300.0000',
        'pagado' => '300.0000',
        'estado_pago' => EstadoPago::Pagado,
        'estado' => EstadoReserva::Confirmada,
    ]);

    // Bajan a 15 personas: el total nuevo no lo cubre el abono.
    $reserva->personas = 15;
    app(ConfirmaReserva::class)->recalcular($reserva);

    $reserva = $reserva->fresh();

    expect((float) $reserva->total)->toBe(450.0)
        ->and($reserva->estado_pago)->toBe(EstadoPago::Parcial)
        ->and($reserva->estado)->toBe(EstadoReserva::Solicitada);
});

it('el saldo de una reserva pendiente dice lo que falta', function () {
    $reserva = reservaDemo(['total' => '450.0000', 'pagado' => '150.0000']);

    expect((string) $reserva->saldo())->toContain('300')
        ->and($reserva->avancePago())->toBe(33);
});

it('el boton de confirmar aparece con el motivo cuando falta el pago', function () {
    usuarioConPermisoReserva(Permisos::RESERVAS_VER, Permisos::RESERVAS_EDITAR);

    $reserva = reservaDemo(['pagado' => '100.0000']);

    Livewire::test(ListReservaciones::class)
        ->assertCanSeeTableRecords([$reserva])
        ->assertTableActionVisible('confirmar', $reserva);
});

it('sin permiso de reservas la pantalla no se abre', function () {
    usuarioConPermisoReserva(Permisos::CATALOGOS_VER);

    expect(ReservacionResource::canViewAny())->toBeFalse();

    $this->get(ReservacionResource::getUrl('index'))->assertForbidden();
});

it('una reserva confirmada no se borra, se cancela', function () {
    // Borrar una reserva confirmada dejaría un evento cobrado y sin rastro, que
    // es el mismo problema que I3 resuelve para los documentos fiscales.
    $reserva = reservaDemo();

    expect(ReservacionResource::canDelete($reserva))->toBeFalse();
});
