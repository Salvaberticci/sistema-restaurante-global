<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Orden de siembra.
 *
 * Los roles van antes que los usuarios porque asignar un rol a alguien que no
 * existe deja una fila huérfana en la tabla de permisos.
 *
 * La tasa de cambio va antes que el historial de ventas porque el historial
 * guarda la tasa congelada de cada cuenta; sembrarlo sin tasas deja 4.000
 * documentos con una tasa de ejemplo y la pantalla de facturación mintiendo.
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
            TasaCambioSeeder::class,
            ReservacionesSeeder::class,
            HistorialVentasSeeder::class,
            ComandasAbiertasSeeder::class,
        ]);

        $this->command->newLine();
        $this->command->info('Datos base listos. Entra en /admin/login con cualquier cuenta de la demo.');
    }
}
