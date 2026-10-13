<?php

namespace Database\Seeders;

use App\Domain\Iam\Permisos;
use App\Domain\Iam\Roles;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles y permisos.
 *
 * Idempotente: se puede volver a correr sin duplicar ni desordenar la matriz.
 * Es lo que hace falta para que un `demo:reset` a medio demo no rompa los roles
 * que el cliente ya configuró.
 */
class RolesSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permisos::grupos() as $datos) {
            foreach ($datos['permisos'] as $permiso) {
                // La tabla de permisos de Spatie solo tiene nombre y guard. El
                // grupo con el que se muestra en el panel sale de
                // Permisos::grupos(), no de una columna.
                if (! Permission::query()->where('name', $permiso)->where('guard_name', 'web')->exists()) {
                    Permission::create(['name' => $permiso, 'guard_name' => 'web']);
                }
            }
        }

        foreach (Roles::todos() as $nombre => $datos) {
            $rol = Role::findOrCreate($nombre, 'web');

            // syncPermissions y no givePermissionTo: repetir el seed tiene que
            // dejar la matriz igual, no acumulando permisos.
            $rol->syncPermissions($datos['permisos']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command->newLine();
        $this->command->info(sprintf(
            'Roles: %d · Permisos: %d',
            count(Roles::nombres()),
            count(Permisos::todos()),
        ));
    }
}
