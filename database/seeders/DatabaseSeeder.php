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
            RolesAndPermissionsSeeder::class,
            ExampleDataSeeder::class,
            OperationsSeeder::class,
        ]);
    }
}
