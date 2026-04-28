<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeder para DESARROLLO: incluye datos de ejemplo + parametrización completa
 *
 * Uso:
 *   Desarrollo: php artisan migrate:fresh --seed
 *   Desarrollo con seeders específicos: php artisan db:seed --class=DevelopmentSeeder
 *
 * Para producción usar DatabaseSeeder (solo parametrización, no datos de ejemplo)
 */
class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // === PARAMETRIZACIÓN DEL SISTEMA (SIEMPRE EJECUTAR) ===
            CompanySettingSeeder::class,
            SettingsFieldParametersSeeder::class,
            RolesAndPermissionsSeeder::class,
            FabricRollImportPermissionSeeder::class,

            // === MAESTROS DE DATOS ===
            CategorySeeder::class,
            TaxesSeeder::class,
            WarehousesSeeder::class,
            AccountingAccountSeeder::class,

            // === PARAMETRIZACIÓN DE OPERACIONES ===
            OperationSeeder::class,

            // === DATOS HISTÓRICOS ===
            LegacyItemsSeeder::class,
            FabricRollsLegacySeeder::class,

            // === DATOS DE EJEMPLO (DESARROLLO SOLO) ===
            ExampleDataSeeder::class,
            ContactsSeeder::class,
            FabricItemsDemoSeeder::class,
            FabricRollsV2Seeder::class,
            InventoryStockByWarehouseSeeder::class,
            PurchasesDemoSeeder::class,
            SupplyItemsDemoSeeder::class,
            TechnicalSheetCostsSeeder::class,

            // === LOTES Y OPERACIONES (DESARROLLO SOLO) ===
            BatchControlModuleSeeder::class,
        ]);
    }
}
