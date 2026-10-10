<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Limpieza de permisos de Solicitudes de Insumos y Solicitudes de Compra.
 *
 * - Elimina 'atender solicitudes insumos': era redundante. Solo habilitaba gestionar
 *   solicitudes, lo mismo que ya dan 'entregar' y 'rechazar' (todos los roles que lo
 *   tenían también tienen ambos), así que nadie pierde acceso.
 * - Asegura que existan los permisos de destino de Solicitudes de Compra
 *   ('ver solicitudes compra a gerencia' / 'a logistica'), sin tocar sus asignaciones.
 *
 * Idempotente: no modifica ningún otro permiso ni rol.
 */
class RequestPermissionsCleanupSeeder extends Seeder
{
    private const REMOVED = ['atender solicitudes insumos'];

    private const ENSURED = [
        'ver solicitudes compra a gerencia',
        'ver solicitudes compra a logistica',
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // delete() de Spatie desasigna el permiso de roles y usuarios antes de borrarlo.
        Permission::whereIn('name', self::REMOVED)->where('guard_name', 'web')->get()->each->delete();

        foreach (self::ENSURED as $name) {
            Permission::findOrCreate($name, 'web');
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command?->info('RequestPermissionsCleanupSeeder ejecutado: eliminado "atender solicitudes insumos"; permisos de destino de compras verificados.');
    }
}
