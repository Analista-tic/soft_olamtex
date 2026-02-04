<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CompanySettingSeeder::class,
            SettingsCatalogsSeeder::class,
            SettingsFieldDefinitionsSeeder::class,
            CategorySeeder::class,
            RolesAndPermissionsSeeder::class,
            ExampleDataSeeder::class,
        ]);
    }
}
