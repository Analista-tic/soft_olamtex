<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // ── Configuración base ──────────────────────────────
            CompanySettingSeeder::class,
            SettingsFieldParametersSeeder::class,
            CategorySeeder::class,
            TaxesSeeder::class,
            WarehousesSeeder::class,
            RolesAndPermissionsSeeder::class,

            // ── Operaciones de producción ───────────────────────
            OperationsSeeder::class,
            AccountingAccountSeeder::class,
            FabricItemsDemoSeeder::class,
            SupplyItemsDemoSeeder::class,
            ProductionListingOperationsSeeder::class,

            // ── Datos legacy reales (CSV) ───────────────────────
            // LegacyItemsSeeder primero: sus ítems deben existir para que
            // FabricRollsLegacySeeder los encuentre por referencia (col 19 del CSV).
            LegacyItemsSeeder::class,
            FabricRollsLegacySeeder::class,
        ]);
    }
}