<?php

namespace Database\Seeders;

use App\Domain\Mesas\Models\Sucursal;
use Illuminate\Database\Seeder;

/**
 * Orden de siembra. Las cuentas de demo necesitan sucursales para existir y los
 * roles para tener permisos, así que el orden importa.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SucursalesSeeder::class,
            RolesSeeder::class,
            ParametrosSeeder::class,
            UsuariosDemoSeeder::class,
        ]);

        $this->command?->newLine();
        $this->command?->info('Datos base listos. Entra en /login con cualquier cuenta de la demo.');
    }
}