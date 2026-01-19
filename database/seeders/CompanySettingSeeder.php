<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Settings\CompanySetting;

class CompanySettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Insert default company settings or update existing
        $defaults = [
            'name' => 'Olamtex S.A.S.',
            'nit' => '900123456-7',
            'phone' => '+57 1 234 5678',
            'email' => 'info@olamtex.com',
            'accounting_email' => 'contabilidad@olamtex.com',
            'website' => 'https://olamtex.example',
            'address' => 'Calle Falsa 123',
            'municipality' => 'Pereira',
            'department' => 'Risaralda',
            'description' => 'Empresa fabricante y comercializadora de telas y textiles.',
            'logo_path' => null,
        ];

        // Use updateOrCreate to keep seed idempotent and allow easy re-seeding
        CompanySetting::updateOrCreate(
            ['id' => 1], // ensure a single record (you can change the key if you prefer)
            $defaults
        );
    }
}
