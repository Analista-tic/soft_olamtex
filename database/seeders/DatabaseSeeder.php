<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CompanySettingSeeder::class,
            SettingsFieldParametersSeeder::class,
            CategorySeeder::class,
            TaxesSeeder::class,
            WarehousesSeeder::class,
            RolesAndPermissionsSeeder::class,

            OperationSeeder::class,

            AccountingAccountSeeder::class,

            LegacyItemsSeeder::class,
            FabricRollsLegacySeeder::class,
        ]);
    }
}
