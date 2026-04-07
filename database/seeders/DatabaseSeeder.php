<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // ── Configuración base ──────────────────────────────────────
            CompanySettingSeeder::class,
            SettingsFieldParametersSeeder::class,
            CategorySeeder::class,
            TaxesSeeder::class,
            WarehousesSeeder::class,
            RolesAndPermissionsSeeder::class,

            // ── Producción ────────────────────────────────────────────
            OperationsSeeder::class,
            ProductionListingOperationsSeeder::class,
            
            // ── Contabilidad ────────────────────────────────────────────
            AccountingAccountSeeder::class,

            // ── Datos legacy reales (XLS → CSV) ─────────────────────────
            // LegacyItemsSeeder primero: sus ítems deben existir para que
            // FabricRollsLegacySeeder los encuentre por referencia.
            LegacyItemsSeeder::class,
            FabricRollsLegacySeeder::class,
        ]);
    }
}
