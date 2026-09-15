<?php

namespace Database\Seeders;

use App\Models\MasterData\Color;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Carga el catálogo oficial de colores (código de 3 dígitos + nombre + hex).
 * Hace upsert por `name` (no borra la tabla): items.color_id tiene FK
 * `onDelete('set null')`, así que un delete()+create() en producción dejaría
 * sin color, silenciosamente, a todo ítem que ya tuviera uno asignado. Un color
 * existente que ya no aparece en catalog() se conserva pero queda con `code`
 * null ("retirado") en vez de eliminarse, para no romper esa relación.
 */
class ColorsSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = $this->catalog();

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($catalog, &$created, &$updated) {
            // Libera todos los códigos antes de reasignar: el catálogo reordena códigos
            // entre colores ya existentes (ej. BLANCO pasa de 001 a 002) y `code` es
            // único, así que reasignar sin liberar antes choca con esa restricción.
            Color::query()->update(['code' => null]);

            foreach ($catalog as $row) {
                $color = Color::query()->where('name', $row['name'])->first();

                $rgb = $this->hexToRgb($row['hexadecimal']);

                if ($color) {
                    $color->update([
                        'code' => $row['code'],
                        'rgb' => $rgb,
                        'hexadecimal' => $row['hexadecimal'],
                    ]);
                    $updated++;
                } else {
                    Color::query()->create([
                        'code' => $row['code'],
                        'name' => $row['name'],
                        'rgb' => $rgb,
                        'hexadecimal' => $row['hexadecimal'],
                        'description' => null,
                    ]);
                    $created++;
                }
            }

            $retired = Color::query()->whereNull('code')->pluck('name');
            if ($retired->isNotEmpty()) {
                Log::warning('colors_seeder.retired_colors', ['names' => $retired->all()]);
            }
        });

        $this->command?->info("ColorsSeeder completo — Creados: {$created}, Actualizados: {$updated}");
        Log::info('colors_seeder.done', ['created' => $created, 'updated' => $updated]);
    }

    /**
     * Catálogo oficial de colores (código de 3 dígitos).
     */
    private function catalog(): array
    {
        return [
            ['code' => '001', 'name' => 'UNICO', 'hexadecimal' => null],
            ['code' => '002', 'name' => 'BLANCO', 'hexadecimal' => '#FFFFFF'],
            ['code' => '003', 'name' => 'NEGRO', 'hexadecimal' => '#000000'],
            ['code' => '004', 'name' => 'AMARILLO', 'hexadecimal' => '#FFFF00'],
            ['code' => '005', 'name' => 'ROJO', 'hexadecimal' => '#FF0000'],
            ['code' => '006', 'name' => 'GRIS JASPE', 'hexadecimal' => '#D3D3D3'],
            ['code' => '007', 'name' => 'GRIS CLARO', 'hexadecimal' => '#CCCCCC'],
            ['code' => '008', 'name' => 'GRIS OSCURO', 'hexadecimal' => '#404040'],
            ['code' => '009', 'name' => 'AZUL CLARO', 'hexadecimal' => '#C9E9FC'],
            ['code' => '010', 'name' => 'AZUL OSCURO', 'hexadecimal' => '#1B365D'],
            ['code' => '011', 'name' => 'MOSTAZA', 'hexadecimal' => '#D6A02B'],
            ['code' => '012', 'name' => 'VERDE MILITAR', 'hexadecimal' => '#4B5320'],
            ['code' => '013', 'name' => 'TERRACOTA', 'hexadecimal' => '#972503'],
            ['code' => '014', 'name' => 'CAMEL', 'hexadecimal' => '#BF8A3D'],
            ['code' => '015', 'name' => 'BEIGE', 'hexadecimal' => '#CFB997'],
            ['code' => '016', 'name' => 'VINOTINTO', 'hexadecimal' => '#3C0000'],
            ['code' => '017', 'name' => 'INDIGO CLARO', 'hexadecimal' => '#647E9A'],
            ['code' => '018', 'name' => 'PALO DE ROSA', 'hexadecimal' => '#EA899A'],
            ['code' => '019', 'name' => 'CRUDO', 'hexadecimal' => '#F5F5DC'],
            ['code' => '020', 'name' => 'CAFÉ', 'hexadecimal' => '#784623'],
            ['code' => '021', 'name' => 'VERDE', 'hexadecimal' => '#027B32'],
            ['code' => '022', 'name' => 'MORADO', 'hexadecimal' => '#8E24AA'],
            ['code' => '023', 'name' => 'CREMA', 'hexadecimal' => '#F9FBE7'],
            ['code' => '024', 'name' => 'FUCSIA', 'hexadecimal' => '#FF0080'],
            ['code' => '025', 'name' => 'LILA', 'hexadecimal' => '#C7A1D9'],
            ['code' => '026', 'name' => 'NARANJA', 'hexadecimal' => '#FF8000'],
            ['code' => '027', 'name' => 'SALMÓN', 'hexadecimal' => '#FA8072'],
            ['code' => '028', 'name' => 'ROSADO', 'hexadecimal' => '#FFCBDB'],
            ['code' => '029', 'name' => 'VERDE MENTA', 'hexadecimal' => '#B9F9E5'],
            ['code' => '030', 'name' => 'MOCCA', 'hexadecimal' => '#A38068'],
            ['code' => '031', 'name' => 'TAUPE', 'hexadecimal' => '#775D4C'],
            ['code' => '032', 'name' => 'VERDE JADE', 'hexadecimal' => '#87B29D'],
            ['code' => '033', 'name' => 'VERDE PETRÓLEO', 'hexadecimal' => '#009688'],
            ['code' => '034', 'name' => 'INDIGO OSCURO', 'hexadecimal' => '#041A2F'],
            ['code' => '035', 'name' => 'KAKI', 'hexadecimal' => '#A18F5E'],
            ['code' => '036', 'name' => 'AZUL NAVY', 'hexadecimal' => '#19284C'],
            ['code' => '037', 'name' => 'AZUL CELESTE', 'hexadecimal' => '#86B0E5'],
            ['code' => '038', 'name' => 'DURAZNO', 'hexadecimal' => '#FFCCBC'],
            ['code' => '039', 'name' => 'ROSADO CLARO', 'hexadecimal' => '#F6CACA'],
            ['code' => '040', 'name' => 'CAOBA', 'hexadecimal' => '#AE3E50'],
            ['code' => '041', 'name' => 'LIMA', 'hexadecimal' => '#80FF00'],
            ['code' => '042', 'name' => 'MAGENTA', 'hexadecimal' => '#C5299B'],
            ['code' => '043', 'name' => 'VERDE OSCURO', 'hexadecimal' => '#105134'],
            ['code' => '044', 'name' => 'VERDE PISTACHO', 'hexadecimal' => '#C5E1A5'],
            ['code' => '045', 'name' => 'AMARILLO CLARO', 'hexadecimal' => '#F4FF81'],
            ['code' => '046', 'name' => 'CAFÉ OSCURO', 'hexadecimal' => '#3E2723'],
            ['code' => '047', 'name' => 'CANELA', 'hexadecimal' => '#70594B'],
            ['code' => '048', 'name' => 'VERDE SALVIA', 'hexadecimal' => '#7E9E84'],
            ['code' => '049', 'name' => 'NUDE', 'hexadecimal' => '#FAE1CE'],
            ['code' => '050', 'name' => 'AZUL REY', 'hexadecimal' => '#0047AB'],
        ];
    }

    /**
     * Mismo formato que resources/js/lib/colorUtils.ts (hexToRgb): "R,G,B" sin
     * espacios, el que valida ColorController (`regex:/^\d{1,3},\d{1,3},\d{1,3}$/`).
     */
    private function hexToRgb(?string $hex): ?string
    {
        if ($hex === null) {
            return null;
        }

        $normalized = ltrim($hex, '#');
        if (! preg_match('/^[0-9a-fA-F]{6}$/', $normalized)) {
            return null;
        }

        return implode(',', array_map(
            static fn (string $part): int => hexdec($part),
            str_split($normalized, 2)
        ));
    }
}
