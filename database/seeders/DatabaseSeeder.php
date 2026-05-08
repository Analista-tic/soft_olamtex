<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed para PRODUCCIÓN: solo parametrización
     *
     * Incluye: configuración, categorías, impuestos, bodegas, roles/permisos, operaciones
     * NO incluye: datos de ejemplo, órdenes de producción, inventario específico
     */
    public function run(): void
    {
        $this->call([
            // === CONFIGURACIÓN DEL SISTEMA ===
            CompanySettingSeeder::class,
            SettingsFieldParametersSeeder::class,
            RolesAndPermissionsSeeder::class,
            PermissionSeeder::class,
            FabricRollImportPermissionSeeder::class,
            RolePermissionMatrixAdjustmentSeeder::class,

            // === MAESTROS DE DATOS ===
            CategorySeeder::class,
            TaxesSeeder::class,
            WarehousesSeeder::class,
            AccountingAccountSeeder::class,

            // === PARAMETRIZACIÓN DE OPERACIONES ===
            OperationSeeder::class,

            // === DATOS HISTÓRICOS (opcional) ===
            ColorsSeeder::class,
            LegacyItemsSeeder::class,
            LegacyContactsSeeder::class,
            LegacyFabricRollsSeeder::class,
        ]);

        if (app()->environment('local')) {
            $this->call([
                ProductionDemoSeeder::class,
            ]);
        }
    }
}
