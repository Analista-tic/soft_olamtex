<?php

namespace Database\Seeders;

use App\Models\MasterData\Color;
use Illuminate\Database\Seeder;
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
        $csvPath = $this->resolveCsvPath();

        if ($csvPath === null) {
            $this->command?->warn(
                'ColorsSeeder: No se encontró colores.csv en storage/app/legacy/ ni en db/. Se omite carga de colores.'
            );
            Log::warning('colors_seeder.no_file');
            return;
        }

        $this->command?->info("Cargando colores desde: {$csvPath}");

        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            $this->command?->error("ColorsSeeder: No se pudo abrir {$csvPath}.");
            return;
        }

        // Detectar BOM UTF-8
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $rawHeaders = fgetcsv($handle, 0, ',', '"', '\\');
        if ($rawHeaders === false || empty($rawHeaders)) {
            fclose($handle);
            $this->command?->error('ColorsSeeder: CSV sin encabezados.');
            return;
        }

        // Normalizar encabezados: minúsculas, sin espacios extra
        $headers = array_map(fn(string $h) => mb_strtolower(trim($h), 'UTF-8'), $rawHeaders);

        $inserted = 0;
        $skipped  = 0;

        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $data = [];
            foreach ($headers as $i => $header) {
                $data[$header] = isset($row[$i]) ? trim((string) $row[$i]) : '';
            }

            $name = $data['name'] ?? '';
            if ($name === '') {
                $skipped++;
                continue;
            }

            $color = Color::firstOrCreate(
                ['name' => $name],
                [
                    'rgb'         => $data['rgb']         !== '' ? $data['rgb']         : null,
                    'hexadecimal' => $data['hexadecimal'] !== '' ? $data['hexadecimal'] : null,
                    'description' => $data['description'] !== '' ? $data['description'] : null,
                ]
            );

            if ($color->wasRecentlyCreated) {
                $inserted++;
            } else {
                $skipped++;
            }
        }

        fclose($handle);

        $this->command?->info("ColorsSeeder completo — Insertados: {$inserted} | Omitidos/existentes: {$skipped}");
        Log::info('colors_seeder.done', ['inserted' => $inserted, 'skipped' => $skipped]);
    }

    private function resolveCsvPath(): ?string
    {
        $candidates = [
            storage_path('app/legacy/colores.csv'),
            base_path('db/colores.csv'),
        ];

        foreach ($candidates as $path) {
            if (file_exists($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }
}
