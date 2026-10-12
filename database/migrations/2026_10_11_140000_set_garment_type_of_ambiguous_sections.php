<?php

use App\Models\Settings\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** Secciones que estaban en "ambos" por defecto y se fijan al tipo de prenda que realmente son. */
    private const TYPES = [
        'CAMISA' => 'superior',
        'BLUSA' => 'superior',
        'SUDADERA' => 'inferior',
        'FALD' => 'inferior',
        'FALDASHORT' => 'inferior',
    ];

    public function up(): void
    {
        $setting = Setting::query()->where('kind', Setting::KIND_PARAMETER)->where('key', 'sections')->first();
        if (! $setting || ! is_array($setting->value)) {
            return;
        }

        $changed = false;
        $sections = array_map(function ($section) use (&$changed) {
            $name = is_array($section) ? mb_strtoupper(trim((string) ($section['name'] ?? ''))) : '';
            if ($name !== '' && isset(self::TYPES[$name]) && ($section['allowed_garment_type'] ?? null) !== self::TYPES[$name]) {
                $section['allowed_garment_type'] = self::TYPES[$name];
                $changed = true;
            }

            return $section;
        }, $setting->value);

        if ($changed) {
            $setting->value = $sections;
            $setting->save();
        }
    }

    public function down(): void
    {
        // Sin reversa: antes eran "ambos" por defecto, pero no es un estado que valga la pena restaurar.
    }
};
