<?php

namespace Database\Seeders;

use App\Models\MasterData\Color;
use App\Models\MasterData\Item;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MigrateItemColorsSeeder extends Seeder
{
    public function run(): void
    {
        $createdColors = 0;
        $updatedItems = 0;

        DB::transaction(function () use (&$createdColors, &$updatedItems) {
            $items = Item::query()->whereNotNull('extra_fields')->get();

            foreach ($items as $item) {
                $extraFields = is_array($item->extra_fields)
                    ? $item->extra_fields
                    : [];

                $colorName = '';
                foreach ($extraFields as $key => $value) {
                    if (strcasecmp(trim((string) $key), 'color') === 0) {
                        $colorName = trim((string) $value);
                        break;
                    }
                }

                if ($colorName === '') {
                    continue;
                }

                $color = Color::firstOrCreate(
                    ['name' => $colorName],
                    ['rgb' => null],
                );

                if ($color->wasRecentlyCreated) {
                    $createdColors++;
                }

                if ((int) ($item->color_id ?? 0) !== (int) $color->id) {
                    $item->update(['color_id' => $color->id]);
                    $updatedItems++;
                }
            }
        });

        $message = "Colores creados: {$createdColors}. Ítems actualizados: {$updatedItems}.";

        if ($this->command) {
            $this->command->info($message);
        } else {
            logger()->info($message);
        }
    }
}
