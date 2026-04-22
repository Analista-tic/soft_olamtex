<?php

namespace Database\Seeders;

use App\Models\Settings\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class UpdateProduccionRolePermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $produccion = Role::where('name', 'produccion')->first();

        if (! $produccion) {
            return;
        }

        $permisosNuevos = [
            'ver control lotes',
            'entregar lote produccion',
            'reportar novedad lote',
            'resolver novedad lote',
            'registrar consumo tela corte',
            'ver telas rollos',
            'crear telas rollos',
            'editar telas rollos',
            'aprobar recepcion tela',
            'rechazar recepcion tela',
            'ver descargue telas orden',
            'crear descargue telas orden',
        ];

        foreach ($permisosNuevos as $permiso) {
            Permission::findOrCreate($permiso);

            if ($produccion->hasPermissionTo($permiso)) {
                continue;
            }

            $produccion->givePermissionTo($permiso);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
