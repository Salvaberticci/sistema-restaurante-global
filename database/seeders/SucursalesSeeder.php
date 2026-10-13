<?php

namespace Database\Seeders;

use App\Domain\Mesas\Models\Sucursal;
use Illuminate\Database\Seeder;

/**
 * Las dos sucursales del restaurante de la demo.
 *
 * "Casa Mango" es un restaurante ficticio, inventado para el demo. Tener dos
 * sucursales desde el día uno sirve para que el cliente vea que el sistema
 * soporta más de una, que es casi siempre lo primero que pregunta.
 */
class SucursalesSeeder extends Seeder
{
    public function run(): void
    {
        $sucursales = [
            [
                'nombre' => 'Casa Mango — Sabana',
                'codigo' => 'SAB',
                'direccion' => 'Av. Principal de Chacao, esquina Av. La Guairita',
                'telefono' => '0212-2634410',
                'ciudad' => 'Caracas',
                'estado' => 'Distrito Capital',
            ],
            [
                'nombre' => 'Casa Mango — Páez',
                'codigo' => 'PAE',
                'direccion' => 'Av. Principal de Los Teques, Km 5',
                'telefono' => '0212-3832210',
                'ciudad' => 'Los Teques',
                'estado' => 'Miranda',
            ],
        ];

        foreach ($sucursales as $datos) {
            Sucursal::updateOrCreate(
                ['codigo' => $datos['codigo']],
                [...$datos, 'activo' => true],
            );
        }
    }
}