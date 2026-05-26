<?php

namespace Database\Seeders;

use App\Models\MasterData\Color;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Carga el catálogo de colores desde colores.csv.
 *
 * Archivos buscados (en orden):
 *   storage/app/legacy/colores.csv
 *   db/colores.csv
 *
 * Formato esperado del CSV (encabezados en primera fila):
 *   id, name, rgb, hexadecimal, description
 *
 * El campo `id` del CSV se ignora; `name` se usa como clave única.
 * Si el color ya existe (por nombre) se omite sin error.
 */
class ColorsSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = $this->catalog();

        $inserted = 0;

        DB::transaction(function () use ($catalog, &$inserted) {
            Color::query()->delete();

            foreach ($catalog as $row) {
                Color::query()->create([
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'rgb' => null,
                    'hexadecimal' => $row['hexadecimal'],
                    'description' => null,
                ]);

                $inserted++;
            }
        });

        $this->command?->info("ColorsSeeder completo — Insertados: {$inserted}");
        Log::info('colors_seeder.done', ['inserted' => $inserted]);
    }

    /**
     * Catálogo oficial de colores (código de 3 dígitos).
     * Se omiten códigos sin color válido (--- / —).
     */
    private function catalog(): array
    {
        return [
            ['code' => '000', 'name' => 'UNICO', 'hexadecimal' => null],
            ['code' => '001', 'name' => 'BLANCO', 'hexadecimal' => '#FFFFFF'],
            ['code' => '002', 'name' => 'NEGRO', 'hexadecimal' => '#000000'],
            ['code' => '003', 'name' => 'AMARILLO', 'hexadecimal' => '#FFFF00'],
            ['code' => '004', 'name' => 'AZUL', 'hexadecimal' => '#03A9F4'],
            ['code' => '005', 'name' => 'ROJO', 'hexadecimal' => '#FF0000'],
            ['code' => '006', 'name' => 'GRIS CLARO', 'hexadecimal' => '#BDBDBD'],
            ['code' => '007', 'name' => 'GRIS OSCURO', 'hexadecimal' => '#332F2C'],
            ['code' => '008', 'name' => 'AZUL CLARO', 'hexadecimal' => '#C9E9FC'],
            ['code' => '009', 'name' => 'AZUL OSCURO', 'hexadecimal' => '#000020'],
            ['code' => '010', 'name' => 'MOSTAZA', 'hexadecimal' => '#D6A02B'],
            ['code' => '011', 'name' => 'VERDE MILITAR', 'hexadecimal' => '#4B5320'],
            ['code' => '012', 'name' => 'IVORY', 'hexadecimal' => '#FFFAE6'],
            ['code' => '013', 'name' => 'TERRACOTA', 'hexadecimal' => '#972503'],
            ['code' => '014', 'name' => 'CAMEL', 'hexadecimal' => '#BF8A3D'],
            ['code' => '015', 'name' => 'BEIGE', 'hexadecimal' => '#CFB997'],
            ['code' => '016', 'name' => 'VINOTINTO', 'hexadecimal' => '#3C0000'],
            ['code' => '017', 'name' => 'GRIS PLOMO', 'hexadecimal' => '#696E73'],
            ['code' => '018', 'name' => 'INDIGO CLARO', 'hexadecimal' => '#647E9A'],
            ['code' => '020', 'name' => 'PALO DE ROSA', 'hexadecimal' => '#EA899A'],
            ['code' => '021', 'name' => 'CHOCOLATE', 'hexadecimal' => '#5C3317'],
            ['code' => '022', 'name' => 'MILITAR', 'hexadecimal' => '#5A6937'],
            ['code' => '023', 'name' => 'GRANATE', 'hexadecimal' => '#B03648'],
            ['code' => '024', 'name' => 'GRIS', 'hexadecimal' => '#CDCDCD'],
            ['code' => '025', 'name' => 'CRUDO', 'hexadecimal' => '#F5F5DC'],
            ['code' => '026', 'name' => 'MENTA', 'hexadecimal' => '#84FFFF'],
            ['code' => '027', 'name' => 'DORADO', 'hexadecimal' => '#D2AF32'],
            ['code' => '030', 'name' => 'PLATEADO', 'hexadecimal' => '#BEBEC3'],
            ['code' => '032', 'name' => 'CAFÉ', 'hexadecimal' => '#784623'],
            ['code' => '033', 'name' => 'VERDE', 'hexadecimal' => '#027B32'],
            ['code' => '034', 'name' => 'MORADO', 'hexadecimal' => '#8E24AA'],
            ['code' => '035', 'name' => 'AGUA MARINA', 'hexadecimal' => '#66CDAA'],
            ['code' => '036', 'name' => 'AZUL REY', 'hexadecimal' => '#0303B5'],
            ['code' => '037', 'name' => 'CREMA', 'hexadecimal' => '#F9FBE7'],
            ['code' => '038', 'name' => 'FUCSIA CLARO', 'hexadecimal' => '#FAB9C3'],
            ['code' => '039', 'name' => 'FUCSIA', 'hexadecimal' => '#FF0080'],
            ['code' => '040', 'name' => 'LILA', 'hexadecimal' => '#C7A1D9'],
            ['code' => '041', 'name' => 'NARANJA', 'hexadecimal' => '#FF8000'],
            ['code' => '042', 'name' => 'VERDE OLIVO', 'hexadecimal' => '#AFB42B'],
            ['code' => '043', 'name' => 'SALMÓN', 'hexadecimal' => '#FA8072'],
            ['code' => '045', 'name' => 'GRIS JASPE', 'hexadecimal' => '#A5A09B'],
            ['code' => '047', 'name' => 'ROSADO', 'hexadecimal' => '#FFCBDB'],
            ['code' => '048', 'name' => 'VERDE MENTA', 'hexadecimal' => '#B9F9E5'],
            ['code' => '049', 'name' => 'MOCA', 'hexadecimal' => '#A38068'],
            ['code' => '050', 'name' => 'TURQUESA', 'hexadecimal' => '#00CED1'],
            ['code' => '052', 'name' => 'BEIGE JASPE', 'hexadecimal' => '#EDD7BD'],
            ['code' => '053', 'name' => 'TAUPE', 'hexadecimal' => '#775D4C'],
            ['code' => '054', 'name' => 'BERENJENA', 'hexadecimal' => '#8C289B'],
            ['code' => '055', 'name' => 'CORAL', 'hexadecimal' => '#FA785F'],
            ['code' => '056', 'name' => 'VERDE JADE', 'hexadecimal' => '#87B29D'],
            ['code' => '057', 'name' => 'VERDE PETRÓLEO', 'hexadecimal' => '#009688'],
            ['code' => '058', 'name' => 'AZUL PETRÓLEO', 'hexadecimal' => '#2896AF'],
            ['code' => '059', 'name' => 'INDIGO OSCURO', 'hexadecimal' => '#041A2F'],
            ['code' => '061', 'name' => 'KAKI', 'hexadecimal' => '#A18F5E'],
            ['code' => '062', 'name' => 'MANDARINA', 'hexadecimal' => '#FFCC80'],
            ['code' => '063', 'name' => 'AZUL NAVY', 'hexadecimal' => '#19284C'],
            ['code' => '067', 'name' => 'VERDE ESMERALDA', 'hexadecimal' => '#199B55'],
            ['code' => '068', 'name' => 'AZUL CELESTE', 'hexadecimal' => '#86B0E5'],
            ['code' => '070', 'name' => 'VIOLETA', 'hexadecimal' => '#8906BD'],
            ['code' => '071', 'name' => 'CURUBA', 'hexadecimal' => '#FAAA94'],
            ['code' => '072', 'name' => 'DURAZNO', 'hexadecimal' => '#FFCCBC'],
            ['code' => '076', 'name' => 'ROSADO CLARO', 'hexadecimal' => '#F6CACA'],
            ['code' => '080', 'name' => 'CAOBA', 'hexadecimal' => '#AE3E50'],
            ['code' => '081', 'name' => 'LIMA', 'hexadecimal' => '#80FF00'],
            ['code' => '082', 'name' => 'MAGENTA', 'hexadecimal' => '#C5299B'],
            ['code' => '083', 'name' => 'VERDE OSCURO', 'hexadecimal' => '#105134'],
            ['code' => '084', 'name' => 'VERDE PISTACHO', 'hexadecimal' => '#C5E1A5'],
            ['code' => '086', 'name' => 'LAVANDA', 'hexadecimal' => '#7E57C2'],
            ['code' => '087', 'name' => 'AMARILLO CLARO', 'hexadecimal' => '#F4FF81'],
            ['code' => '088', 'name' => 'MALVA', 'hexadecimal' => '#C7A1D9'],
            ['code' => '089', 'name' => 'CAFÉ OSCURO', 'hexadecimal' => '#170C00'],
            ['code' => '091', 'name' => 'GUAYABA', 'hexadecimal' => '#FF8674'],
            ['code' => '092', 'name' => 'CANELA', 'hexadecimal' => '#70594B'],
            ['code' => '093', 'name' => 'VERDE SALVIA', 'hexadecimal' => '#7E9E84'],
            ['code' => '094', 'name' => 'MAUVE', 'hexadecimal' => '#774360'],
            ['code' => '095', 'name' => 'ROSA VIEJO', 'hexadecimal' => '#E1BDB2'],
            ['code' => '097', 'name' => 'NUDE', 'hexadecimal' => '#FAE1CE'],
            ['code' => '099', 'name' => 'AZUL ELECTRICO', 'hexadecimal' => '#0000FF'],
        ];
    }
}
