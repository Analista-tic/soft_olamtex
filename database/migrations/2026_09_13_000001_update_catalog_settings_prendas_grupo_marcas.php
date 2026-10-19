<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Actualiza los catálogos de parámetros ya sembrados en `settings`.
 *
 * SettingsFieldParametersSeeder no reescribe estos valores en un reseed
 * (mergeParameterOptions conserva el valor existente cuando la fila ya
 * existe), así que instalaciones que ya corrieron el seeder necesitan este
 * parche para reflejar el catálogo actualizado:
 *  - sections ("Prendas"): agrega FALDASHORT, SUDADERA, CAMISA, BLUSA.
 *  - groups ("Grupo"): agrega FULLMODA.
 *  - brands ("Marcas"): ATUENDOS -> FULLWEAR.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->appendToSections();
        $this->appendToGroups();
        $this->renameBrand('ATUENDOS', 'FULLWEAR');
    }

    public function down(): void
    {
        $this->removeFromSections(['FALDASHORT', 'SUDADERA', 'CAMISA', 'BLUSA']);
        $this->removeFromGroups(['FULLMODA']);
        $this->renameBrand('FULLWEAR', 'ATUENDOS');
    }

    private function appendToSections(): void
    {
        $newItems = [
            ['name' => 'FALDASHORT', 'allowed_garment_type' => 'ambos'],
            ['name' => 'SUDADERA', 'allowed_garment_type' => 'ambos'],
            ['name' => 'CAMISA', 'allowed_garment_type' => 'ambos'],
            ['name' => 'BLUSA', 'allowed_garment_type' => 'ambos'],
        ];

        $this->appendRows('sections', $newItems);
    }

    private function removeFromSections(array $names): void
    {
        $this->removeRowsByName('sections', $names);
    }

    private function appendToGroups(): void
    {
        $this->appendRows('groups', [['name' => 'FULLMODA']]);
    }

    private function removeFromGroups(array $names): void
    {
        $this->removeRowsByName('groups', $names);
    }

    private function renameBrand(string $from, string $to): void
    {
        $setting = DB::table('settings')
            ->where('kind', 'parameter')
            ->where('key', 'brands')
            ->first();

        if (! $setting) {
            return;
        }

        $rows = json_decode($setting->value, true);
        if (! is_array($rows)) {
            return;
        }

        $changed = false;
        foreach ($rows as &$row) {
            if (is_array($row) && ($row['name'] ?? null) === $from) {
                $row['name'] = $to;
                $changed = true;
            }
        }
        unset($row);

        if ($changed) {
            DB::table('settings')
                ->where('id', $setting->id)
                ->update(['value' => json_encode($rows), 'updated_at' => now()]);
        }
    }

    private function appendRows(string $key, array $newItems): void
    {
        $setting = DB::table('settings')
            ->where('kind', 'parameter')
            ->where('key', $key)
            ->first();

        if (! $setting) {
            return;
        }

        $rows = json_decode($setting->value, true);
        if (! is_array($rows)) {
            return;
        }

        $existingNames = array_map(
            static fn ($row) => is_array($row) ? ($row['name'] ?? null) : $row,
            $rows
        );

        $nextId = 1;
        foreach ($rows as $row) {
            if (is_array($row) && isset($row['id'])) {
                $nextId = max($nextId, (int) $row['id'] + 1);
            }
        }

        foreach ($newItems as $item) {
            if (in_array($item['name'], $existingNames, true)) {
                continue;
            }

            $rows[] = array_merge(['id' => $nextId++], $item);
        }

        DB::table('settings')
            ->where('id', $setting->id)
            ->update(['value' => json_encode($rows), 'updated_at' => now()]);
    }

    private function removeRowsByName(string $key, array $names): void
    {
        $setting = DB::table('settings')
            ->where('kind', 'parameter')
            ->where('key', $key)
            ->first();

        if (! $setting) {
            return;
        }

        $rows = json_decode($setting->value, true);
        if (! is_array($rows)) {
            return;
        }

        $rows = array_values(array_filter($rows, static function ($row) use ($names) {
            $name = is_array($row) ? ($row['name'] ?? null) : $row;

            return ! in_array($name, $names, true);
        }));

        DB::table('settings')
            ->where('id', $setting->id)
            ->update(['value' => json_encode($rows), 'updated_at' => now()]);
    }
};
