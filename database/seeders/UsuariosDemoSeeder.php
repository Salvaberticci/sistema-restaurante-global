<?php

namespace Database\Seeders;

use App\Domain\Iam\Roles;
use App\Domain\Mesas\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Las cuentas que ve el cliente el día de la demo.
 *
 * Un usuario por rol, con contraseña conocida, para poder saltar de una pantalla
 * a otra sin buscar credenciales. En un restaurante de verdad esto no existe:
 * acá sí, y por eso el login muestra los accesos directos.
 *
 * Todas usan `demo1234`. Es una demo, el password es público a propósito, y el
 * seeder escribe el modo demo en la interfaz para que no se confunda con datos
 * reales.
 */
class UsuariosDemoSeeder extends Seeder
{
    /** Contraseña única para todas las cuentas de la demo. */
    public const PASSWORD = 'demo1234';

    /** @var array<string, array{rol: string, nombre: string, cedula: string}> */
    private const CUENTAS = [
        'admin' => ['rol' => Roles::ADMINISTRADOR, 'nombre' => 'María Fernández', 'cedula' => 'V-12345678'],
        'gerente' => ['rol' => Roles::GERENTE, 'nombre' => 'Carlos Rodríguez', 'cedula' => 'V-15222333'],
        'cajero' => ['rol' => Roles::CAJERO, 'nombre' => 'Luis Bracho', 'cedula' => 'V-18844555'],
        'mesero' => ['rol' => Roles::MESERO, 'nombre' => 'Andrea Pérez', 'cedula' => 'V-20111222'],
        'cocina' => ['rol' => Roles::COCINA, 'nombre' => 'José Guerra', 'cedula' => 'V-18433221'],
    ];

    public function run(): void
    {
        $sucursal = Sucursal::orderBy('id')->first();

        if ($sucursal === null) {
            $this->command->warn('No hay sucursales: se corre SucursalesSeeder primero.');
            $sucursal = Sucursal::create([
                'nombre' => 'Casa Mango — Sabana',
                'codigo' => 'SAB',
                'direccion' => 'Av.Principal de Chacao, Caracas',
                'ciudad' => 'Caracas',
                'activo' => true,
            ]);
        }

        foreach (self::CUENTAS as $clave => $datos) {
            $usuario = User::updateOrCreate(
                ['email' => $clave.'@casa-mango.test'],
                [
                    'name' => $datos['nombre'],
                    // updateOrCreate no reescribe la contraseña si el usuario ya
                    // existe y se la cambió. Para la demo se fuerza siempre, así
                    // que `demo:reset` deja las cuentas como estaban.
                    'password' => Hash::make(self::PASSWORD),
                    'cedula' => $datos['cedula'],
                    'telefono' => '0412-'.random_int(1000000, 9999999),
                    'sucursal_id' => $sucursal->id,
                    'activo' => true,
                    'email_verified_at' => now(),
                ],
            );

            $usuario->syncRoles([$datos['rol']]);
        }

        $this->command->newLine();
        $this->command->info('Cuentas de demo listas. Contraseña de todas: '.self::PASSWORD);
    }

    /** @return array<string, array{email: string, rol: string, nombre: string}> Para el login con accesos directos. */
    public static function accesosDirectos(): array
    {
        $salida = [];

        foreach (self::CUENTAS as $clave => $datos) {
            $salida[$clave] = [
                'email' => $clave.'@casa-mango.test',
                'rol' => $datos['rol'],
                'nombre' => $datos['nombre'],
            ];
        }

        return $salida;
    }
}
