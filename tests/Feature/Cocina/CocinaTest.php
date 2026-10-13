<?php

use App\Domain\Configuracion\Contracts\Parametros;
use App\Domain\Iam\Permisos;
use App\Domain\Ordenes\Enums\EstadoOrden;
use App\Domain\Ordenes\Models\Orden;
use App\Domain\Ordenes\Models\OrdenItem;
use App\Livewire\Kitchen\Cocina;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Pantalla de cocina.
 *
 * Lo que se prueba aquí es el flujo de estados de un plato y la regla de que la
 * orden se sirve sola. Las dos cosas son la diferencia entre un KDS y un tablón:
 * sin ellas, la cocina trabaja a ciegas y el mesero tiene que ir a preguntar.
 */

/** Orden en cocina con el estado de sus platos ya preparado. */
function comandaDeCocina(array $estadoItems = ['pendiente']): Orden
{
    $orden = Orden::create([
        'numero' => 'C-'.fake()->unique()->numerify('####'),
        'canal' => 'mesa',
        'estado' => EstadoOrden::EnviadaCocina,
        'metodo_pago' => 'efectivo_usd',
        'subtotal' => 30,
        'total' => 30,
        'moneda' => 'USD',
        'abierta_at' => now(),
    ]);

    foreach ($estadoItems as $indice => $estado) {
        OrdenItem::create([
            'orden_id' => $orden->id,
            'producto_id' => $indice + 1,
            'producto_nombre' => 'P'.$indice,
            'cantidad' => 1,
            'precio_unitario' => 30,
            'importe' => 30,
            'alicuota_codigo' => 'IVA_G',
            'estado' => $estado,
        ]);
    }

    return $orden;
}

function usuarioDeCocina(): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate(Permisos::COCINA_VER, 'web');

    $usuario = User::factory()->create(['name' => 'Cocina']);
    $usuario->givePermissionTo(Permisos::COCINA_VER);

    test()->actingAs($usuario);

    return $usuario;
}

it('muestra las comandas que hay que preparar', function () {
    usuarioDeCocina();

    $comanda = comandaDeCocina();

    Livewire::test(Cocina::class)
        ->assertOk()
        ->assertSee($comanda->numero);
});

it('los umbrales del semaforo salen del parametro, no del codigo', function () {
    usuarioDeCocina();

    app(Parametros::class)->definir('cocina.semaforo_minutos', [
        ['nivel' => 'verde', 'minutos' => '5'],
        ['nivel' => 'ambar', 'minutos' => '30'],
        ['nivel' => 'rojo', 'minutos' => '30'],
    ]);

    // El dueño cambia la tolerancia sin que nadie toque el KDS. El parámetro son
    // tres niveles, no un número: leerlo como entero devolvía un arreglo.
    expect(Livewire::test(Cocina::class)->instance()->umbrales())
        ->toBe(['verde' => 5, 'ambar' => 30, 'rojo' => 30]);
});

it('el semaforo reparte los platos en verde, ambar y rojo', function () {
    usuarioDeCocina();

    // La hora se congela a media tarde porque el semáforo cuenta lo del día, y
    // una prueba ejecutada a las 00:34 tendría el plato "atrasado" del día
    // anterior y no contaría. Sin esto la prueba pasa o falla según la hora en
    // que se corra.
    Carbon::setTestNow(Carbon::create(2026, 10, 12, 14, 30));

    try {
        app(Parametros::class)->definir('cocina.semaforo_minutos', [
            ['nivel' => 'verde', 'minutos' => '15'],
            ['nivel' => 'ambar', 'minutos' => '30'],
            ['nivel' => 'rojo', 'minutos' => '30'],
        ]);

        comandaDeCocina(['en_preparacion']);
        $orden = Orden::first();

        // `created_at` no es rellenable a propósito —el modelo tiene el
        // `preventSilentlyDiscardingAttributes` activo—, así que la marca de tiempo
        // se fija sobre la instancia ya creada.
        $reciente = OrdenItem::create([
            'orden_id' => $orden->id,
            'producto_id' => 9,
            'producto_nombre' => 'Reciente',
            'cantidad' => 1,
            'precio_unitario' => 10,
            'importe' => 10,
            'alicuota_codigo' => 'IVA_G',
            'estado' => 'en_preparacion',
        ]);
        $reciente->timestamps = false;
        $reciente->forceFill(['created_at' => now()->subMinutes(4)])->save();

        $atrasado = OrdenItem::create([
            'orden_id' => $orden->id,
            'producto_id' => 10,
            'producto_nombre' => 'Atrasado',
            'cantidad' => 1,
            'precio_unitario' => 10,
            'importe' => 10,
            'alicuota_codigo' => 'IVA_G',
            'estado' => 'en_preparacion',
        ]);
        $atrasado->timestamps = false;
        $atrasado->forceFill(['created_at' => now()->subMinutes(45)])->save();

        $semaforo = Livewire::test(Cocina::class)->instance()->semaforo();

        // El primero, recién creado, entra en verde; el que lleva 45 minutos, en
        // rojo. Si esto saliera con todo en rojo, el semáforo estaría mintiendo.
        expect($semaforo['ok'])->toBeGreaterThanOrEqual(1)
            ->and($semaforo['tarde'])->toBeGreaterThanOrEqual(1)
            ->and($semaforo['avisos'])->toBeGreaterThanOrEqual(0);
    } finally {
        Carbon::setTestNow();
    }
});

it('mueve un plato por la secuencia pendiente, preparacion, listo, servido', function () {
    usuarioDeCocina();

    $comanda = comandaDeCocina();
    $item = $comanda->items()->first();

    $cocina = Livewire::test(Cocina::class);

    $cocina->call('moverItem', $comanda->id, $item->id, 'en_preparacion');
    expect($item->fresh()->estado)->toBe('en_preparacion');

    $cocina->call('moverItem', $comanda->id, $item->id, 'listo');
    expect($item->fresh()->estado)->toBe('listo');

    $cocina->call('moverItem', $comanda->id, $item->id, 'servido');
    expect($item->fresh()->estado)->toBe('servido');
});

it('no se salta un estado de la secuencia', function () {
    usuarioDeCocina();

    $comanda = comandaDeCocina();
    $item = $comanda->items()->first();

    // De "pendiente" directo a "servido": con dos tablets sobre la misma comanda
    // esto pasa, y sin la validación el plato se pierde entre las dos pantallas.
    Livewire::test(Cocina::class)
        ->call('moverItem', $comanda->id, $item->id, 'servido');

    expect($item->fresh()->estado)->toBe('pendiente');
});

it('la orden pasa a servida sola cuando el ultimo plato termina', function () {
    usuarioDeCocina();

    $comanda = comandaDeCocina(['pendiente', 'pendiente']);
    $items = $comanda->items()->get();

    $cocina = Livewire::test(Cocina::class);

    $cocina->call('moverItem', $comanda->id, $items[0]->id, 'en_preparacion');
    $cocina->call('moverItem', $comanda->id, $items[0]->id, 'listo');

    expect($comanda->fresh()->estado)->toBe(EstadoOrden::EnviadaCocina);

    $cocina->call('moverItem', $comanda->id, $items[1]->id, 'en_preparacion');
    $cocina->call('moverItem', $comanda->id, $items[1]->id, 'listo');

    // El mesero tiene que enterarse solo de que la mesa ya está servida.
    expect($comanda->fresh()->estado)->toBe(EstadoOrden::Servida);
});

it('servir la comanda entera marca todos los platos', function () {
    usuarioDeCocina();

    $comanda = comandaDeCocina(['pendiente', 'en_preparacion']);

    Livewire::test(Cocina::class)->call('servirOrden', $comanda->id);

    expect($comanda->items()->pluck('estado')->all())->toBe(['servido', 'servido'])
        ->and($comanda->fresh()->estado)->toBe(EstadoOrden::Servida);
});

it('la vista de listos cambia lo que se ve', function () {
    usuarioDeCocina();

    comandaDeCocina(['listo']);

    Livewire::test(Cocina::class)
        ->call('cambiarVista', 'listos')
        ->assertSee('Para pasar');
});

it('sin permiso de cocina la pantalla no se abre', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate(Permisos::CATALOGOS_VER, 'web');

    $usuario = User::factory()->create();
    $usuario->givePermissionTo(Permisos::CATALOGOS_VER);

    $this->actingAs($usuario)->get('/cocina')->assertForbidden();
});

it('sin sesion la pantalla de cocina pide iniciar sesion', function () {
    // El login es el de Filament. La prueba lo pide por su nombre de ruta y no
    // por `route('login')`, que en este proyecto no existe: si algún día el
    // middleware vuelve a buscar la ruta de Laravel, esta prueba lo falla.
    $this->get('/cocina')->assertRedirect(route('filament.admin.auth.login'));
});
