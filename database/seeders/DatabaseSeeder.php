<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Orden de siembra.
 *
 * Los roles van antes que los usuarios porque asignar un rol a alguien que no
 * existe deja una fila huérfana en la tabla de permisos.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CatalogosSeeder::class,
            RolesSeeder::class,
            ParametrosSeeder::class,
            UsuariosDemoSeeder::class,
            HistorialVentasSeeder::class,
        ]);

        $this->command->newLine();
        $this->command->info('Datos base listos. Entra en /admin/login con cualquier cuenta de la demo.');
    }
}
