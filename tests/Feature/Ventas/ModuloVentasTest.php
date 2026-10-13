<?php

use App\Domain\Iam\Permisos;
use App\Domain\Ordenes\Enums\Canal;
use App\Domain\Ordenes\Enums\EstadoOrden;
use App\Domain\Ordenes\Models\Orden;
use App\Domain\Ordenes\Models\OrdenItem;
use App\Filament\Resources\OrdenResource;
use App\Filament\Resources\OrdenResource\Pages\ListOrdenes;
use App\Filament\Resources\OrdenResource\Pages\ViewOrden;
use App\Filament\Widgets\PorMetodoPago;
use App\Filament\Widgets\ResumenDelDia;
use App\Filament\Widgets\VentasPorDia;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Módulo de Ventas: la lista de cuentas cobradas y su detalle.
 *
 * Las pruebas de pantalla se hacen con `livewire()` porque devuelven el estado ya
 * renderizado, incluido el HTML: un `assertOk()` sobre la URL solo dice que la
 * ruta existe, no que la tabla se pueda pintar.
 */

/** Usuario con permiso, autenticado, con el panel listo para renderizarse. */
function usuarioConPermiso(string ...$permisos): User
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

/** Una cuenta cobrada con un ítem servido y otro cortado. */
function cuentaCobrada(array $atributos = []): Orden
{
    $orden = Orden::create(array_merge([
        'numero' => 'V-'.fake()->unique()->numerify('#####'),
        'canal' => Canal::Mesa,
        'estado' => EstadoOrden::Cobrada,
        'comensales' => 4,
        'metodo_pago' => 'efectivo_usd',
        'subtotal' => 100,
        'descuentos' => 0,
        'propina' => 10,
        'total' => 110,
        'moneda' => 'USD',
        'abierta_at' => now()->subHours(2),
        'cerrada_at' => now()->subHour(),
        'cobrada_at' => now()->subHour(),
    ], $atributos));

    OrdenItem::create([
        'orden_id' => $orden->id,
        'producto_id' => 1,
        'producto_nombre' => 'Pabellón',
        'cantidad' => 2,
        'precio_unitario' => 30,
        'importe' => 60,
        'alicuota_codigo' => 'IVA_G',
        'estado' => 'servido',
    ]);

    OrdenItem::create([
        'orden_id' => $orden->id,
        'producto_id' => 2,
        'producto_nombre' => 'Jugo de mango',
        'cantidad' => 1,
        'precio_unitario' => 20,
        'importe' => 20,
        'alicuota_codigo' => 'IVA_E',
        'estado' => 'cortado',
        'cortado_motivo' => 'El cliente lo cambió por limonada',
    ]);

    return $orden;
}

it('muestra las cuentas cobradas y deja fuera las abiertas', function () {
    usuarioConPermiso(Permisos::VENTAS_VER);

    $cobrada = cuentaCobrada();

    $abierta = cuentaCobrada([
        'numero' => 'V-ABIERTA',
        'estado' => EstadoOrden::Abierta,
        'abierta_at' => now(),
        'cerrada_at' => null,
        'cobrada_at' => null,
    ]);

    // Una cuenta abierta es una promesa, no una venta. Si apareciera en la lista
    // de ventas, el total de abajo no cuadraría con el número del día de arriba.
    Livewire::test(ListOrdenes::class)
        ->assertCanSeeTableRecords([$cobrada])
        ->assertCanNotSeeTableRecords([$abierta]);
});

it('el filtro por fecha acota las cuentas', function () {
    usuarioConPermiso(Permisos::VENTAS_VER);

    $hoy = cuentaCobrada(['numero' => 'V-HOY', 'abierta_at' => now()]);

    $viejo = cuentaCobrada([
        'numero' => 'V-VIEJO',
        'abierta_at' => now()->subDays(40),
        'cerrada_at' => now()->subDays(40),
        'cobrada_at' => now()->subDays(40),
    ]);

    // `filterTable` es el atajo de Filament y lo que hay que usar. Con `set()` a
    // mano hay que acordarse de `isActive => true`, y sin eso el filtro queda escrito
    // pero la consulta no lo aplica: la tabla no acota nada y el test pasa mintiendo.
    Livewire::test(ListOrdenes::class)
        ->filterTable('abierta_at', [
            'desde' => now()->subDays(7)->toDateString(),
            'isActive' => true,
        ])
        ->assertCanSeeTableRecords([$hoy])
        ->assertCanNotSeeTableRecords([$viejo]);
});

it('la cabecera de la lista lleva los graficos', function () {
    usuarioConPermiso(Permisos::VENTAS_VER);

    // La gráfica y la tabla tienen que salir del mismo servicio: si cada pantalla
    // calculara lo suyo, con el tiempo una acabaría diciendo $18.400 y la otra
    // $18.900, y eso no tiene defensa frente al cliente.
    $widgets = Livewire::test(ListOrdenes::class)
        ->instance()
        ->getVisibleWidgets();

    expect($widgets)->toContain(VentasPorDia::class, PorMetodoPago::class, ResumenDelDia::class);
});

it('el detalle muestra la comanda con los cortes y su motivo', function () {
    usuarioConPermiso(Permisos::VENTAS_VER);

    $orden = cuentaCobrada();

    Livewire::test(ViewOrden::class, ['record' => $orden->getRouteKey()])
        ->assertOk()
        ->assertSee('Pabellón')
        ->assertSee('Cortado')
        ->assertSee('El cliente lo cambió por limonada')
        // El total guardado, tal como se cobró, no el del catálogo.
        ->assertSee('$110.00');
});

it('una cuenta cobrada no se puede crear, editar ni borrar', function () {
    $orden = cuentaCobrada();

    // Invariante I3: la corrección de una cuenta cobrada es una nota de crédito
    // que la referencia, no reescribir el número que ya se cobró.
    expect(OrdenResource::canCreate())->toBeFalse()
        ->and(OrdenResource::canEdit($orden))->toBeFalse()
        ->and(OrdenResource::canDelete($orden))->toBeFalse();
});

it('sin permiso de ventas la pantalla no se abre', function () {
    usuarioConPermiso(Permisos::CATALOGOS_VER);

    expect(OrdenResource::canViewAny())->toBeFalse();

    $this->get(OrdenResource::getUrl('index'))->assertForbidden();
});

it('el importe guardado es el que se muestra aunque cambie el catalogo', function () {
    usuarioConPermiso(Permisos::VENTAS_VER);

    // El precio es un snapshot. Si el catálogo cambia mañana, la venta de ayer
    // tiene que seguir diciendo lo que se cobró ayer.
    $orden = cuentaCobrada();

    DB::table('productos')->where('id', 1)->update(['precio_venta' => 999]);

    expect((float) $orden->fresh()->itemsCobrados()->sum('importe'))->toBe(60.0)
        ->and((float) $orden->fresh()->total)->toBe(110.0);
});
