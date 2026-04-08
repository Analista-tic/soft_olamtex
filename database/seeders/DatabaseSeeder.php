<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\ProductOperationList\BermudaShortOperationsSeeder;
use Database\Seeders\ProductOperationList\BlouseCamisaBuzosOperationsSeeder;
use Database\Seeders\ProductOperationList\JoggerKidsOperationsSeeder;
use Database\Seeders\ProductOperationList\JoggerPantalonOperationsSeeder;
use Database\Seeders\ProductOperationList\KnitwearOperationsSeeder;

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

            OperationsSeeder::class,
            ProductionListingOperationsSeeder::class,
            BermudaShortOperationsSeeder::class,
            BlouseCamisaBuzosOperationsSeeder::class,
            JoggerKidsOperationsSeeder::class,
            JoggerPantalonOperationsSeeder::class,
            KnitwearOperationsSeeder::class,

            AccountingAccountSeeder::class,

            LegacyItemsSeeder::class,
            FabricRollsLegacySeeder::class,
        ]);
    }
}
