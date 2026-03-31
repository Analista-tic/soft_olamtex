<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Lee los CSVs de ítems (db/) y enriquece rollos.csv añadiendo la columna
 * item_reference (col 19) con la REFERENCIA del ítem coincidente.
 *
 * Flujo recomendado:
 *   1. php -d memory_limit=512M artisan import:export-roll-mapping   → genera db/roll_mapping.xlsx
 *   2. Editar el Excel: rellenar columna REF_MANUAL en filas naranjas
 *   3. php -d memory_limit=512M artisan import:match-rolls-items \
 *        --mapping=db/roll_mapping.xlsx --in-place
 *
 * Sin Excel (sólo auto-match):
 *   php artisan import:match-rolls-items --in-place
 *   php artisan import:match-rolls-items --dry-run
 */
class MatchRollsToItemsCommand extends Command
{
    protected $signature = 'import:match-rolls-items
                            {--dry-run          : Muestra estadísticas sin escribir ningún archivo}
                            {--in-place         : Sobreescribe db/rollos.csv (crea backup .bak)}
                            {--mapping=         : Ruta al Excel editado (db/roll_item_mapping.xlsx)}';

    protected $description = 'Añade columna item_reference a rollos.csv enlazando cada rollo al ítem de tela correcto';

    public function handle(): int
    {
        $rollsPath  = base_path('db/rollos.csv');
        $outputPath = base_path('db/rollos_enriched.csv');
        $itemFiles  = [
            base_path('db/Base de datos normalizada 1 - Items.csv'),
            base_path('db/Base de datos normalizada 2 - Items.csv'),
        ];

        if (! file_exists($rollsPath)) {
            $this->error("No encontrado: {$rollsPath}");
            return 1;
        }

        // ── 1. Indexar ítems por COLOR ─────────────────────────────────────
        $this->info('Leyendo CSVs de ítems…');
        $colorIndex = $this->buildColorIndex($itemFiles);
        $totalItems = array_sum(array_map('count', $colorIndex));
        $this->info("Ítems tipo Tela indexados: {$totalItems} en " . count($colorIndex) . ' colores únicos.');

        // ── 1b. Cargar overrides manuales del Excel (si se pasó --mapping) ─
        $manualMap = $this->loadManualMapping($this->option('mapping'));
        if (! empty($manualMap)) {
            $this->info('Overrides manuales cargados desde Excel: ' . count($manualMap));
        }

        // ── 2. Enriquecer rollos ───────────────────────────────────────────
        $this->info('Procesando rollos.csv…');
        [$rows, $matched, $unmatched, $skipped] = $this->enrichRolls($rollsPath, $colorIndex, $manualMap);

        $total = count($rows) - $skipped;
        $pct   = $total > 0 ? round($matched / $total * 100, 1) : 0;

        $this->info("Total filas:     " . count($rows));
        $this->info("Con match:       {$matched} ({$pct}%)");
        $this->info("Sin match:       {$unmatched}");
        $this->info("Cortas/ignoradas:{$skipped}");

        if ($this->option('dry-run')) {
            $this->comment('Modo dry-run: no se escribió ningún archivo.');
            return 0;
        }

        // ── 3. Escribir resultado ──────────────────────────────────────────
        if ($this->option('in-place')) {
            $backup = $rollsPath . '.bak';
            copy($rollsPath, $backup);
            $this->writeCsv($rollsPath, $rows);
            $this->info("rollos.csv actualizado. Backup en: {$backup}");
        } else {
            $this->writeCsv($outputPath, $rows);
            $this->info("Archivo generado: {$outputPath}");
            $this->comment('Para aplicarlo: renombra rollos_enriched.csv → rollos.csv');
            $this->comment('O ejecuta con --in-place para sobreescribir directamente.');
        }

        return 0;
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  Construcción del índice COLOR → [{name, reference}]
    // ═══════════════════════════════════════════════════════════════════════

    private function buildColorIndex(array $files): array
    {
        $index = []; // strtolower(COLOR) => [{name, reference}]

        foreach ($files as $file) {
            if (! file_exists($file)) {
                $this->warn("No encontrado, se omite: {$file}");
                continue;
            }

            $handle = fopen($file, 'r');
            $this->skipBom($handle);

            $rawHeaders = fgetcsv($handle);
            $headerMap  = [];
            foreach ($rawHeaders as $i => $h) {
                $headerMap[strtoupper(trim($h))] = $i;
            }

            $colSubgrupo = $headerMap['SUBGRUPO']            ?? 3;
            $colNombre   = $headerMap['NOMBRE']               ?? 1;
            $colRef      = $headerMap['REFERENCIA']           ?? 11;
            $colCampos   = $headerMap['CAMPOS ADICIONALES']   ?? 14;
            $colValores  = $headerMap['VALORES ADICIONALES']  ?? 15;

            while (($data = fgetcsv($handle)) !== false) {
                if (count($data) < 15) {
                    continue;
                }

                $subgrupo = strtolower(trim($data[$colSubgrupo] ?? ''));
                if ($subgrupo !== 'tela') {
                    continue;
                }

                $nombre    = trim($data[$colNombre]  ?? '');
                $referencia = trim($data[$colRef]    ?? '');
                $campos    = trim($data[$colCampos]  ?? '');
                $valores   = trim($data[$colValores] ?? '');

                foreach ($this->extractColors($campos, $valores) as $color) {
                    $key           = strtolower($color);
                    $index[$key][] = ['name' => $nombre, 'reference' => $referencia];
                }
            }

            fclose($handle);
        }

        return $index;
    }

    /** Extrae los valores de campos con clave "COLOR" (separados por ;). */
    private function extractColors(string $campos, string $valores): array
    {
        $keys   = array_map('trim', explode(';', $campos));
        $vals   = array_map('trim', explode(';', $valores));
        $colors = [];

        for ($i = 0; $i < min(count($keys), count($vals)); $i++) {
            if (strtoupper($keys[$i]) === 'COLOR' && $vals[$i] !== '') {
                $colors[] = $vals[$i];
            }
        }

        return $colors;
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  Enriquecimiento de rollos.csv
    // ═══════════════════════════════════════════════════════════════════════

    /** @return array{array[], int, int, int} [rows, matched, unmatched, skipped] */
    private function enrichRolls(string $rollsPath, array $colorIndex, array $manualMap = []): array
    {
        $rows      = [];
        $matched   = 0;
        $unmatched = 0;
        $skipped   = 0;

        $handle = fopen($rollsPath, 'r');
        $this->skipBom($handle);

        while (($data = fgetcsv($handle)) !== false) {
            // Filas cortas (posible encabezado o línea vacía) → pasar sin tocar
            if (count($data) < 18) {
                $rows[]  = $data;
                $skipped++;
                continue;
            }

            $tela  = strtolower(trim($data[4]));  // TELA  (fabric_name)
            $color = strtolower(trim($data[5]));  // COLOR
            $key   = $tela . '||' . $color;

            // Prioridad 1: override manual del Excel
            if (isset($manualMap[$key]) && $manualMap[$key] !== '') {
                $data[19] = $manualMap[$key];
                $matched++;
                $rows[] = $data;
                continue;
            }

            // Prioridad 2: ya tenía item_reference en col 19, conservarlo
            if (! empty(trim($data[19] ?? ''))) {
                $rows[] = $data;
                $matched++;
                continue;
            }

            // Prioridad 3: auto-match por COLOR + nombre
            $itemRef = $this->findBestMatch($tela, $color, $colorIndex);

            if ($itemRef !== '') {
                $matched++;
            } else {
                $unmatched++;
            }

            $data[19] = $itemRef;
            $rows[]   = $data;
        }

        fclose($handle);

        return [$rows, $matched, $unmatched, $skipped];
    }

    /**
     * Lee la columna REF_MANUAL del Excel generado por import:export-roll-mapping.
     *
     * @return array<string, string>  key: 'tela||color' → referencia manual
     */
    private function loadManualMapping(?string $mappingPath): array
    {
        if (empty($mappingPath)) {
            return [];
        }

        $fullPath = str_starts_with($mappingPath, '/') || str_contains($mappingPath, ':')
            ? $mappingPath
            : base_path($mappingPath);

        if (! file_exists($fullPath)) {
            $this->warn("Archivo de mapping no encontrado: {$fullPath}");
            return [];
        }

        try {
            $spreadsheet = IOFactory::load($fullPath);
            $sheet       = $spreadsheet->getSheetByName('Mapeo');
            if (! $sheet) {
                $this->warn('El Excel no tiene hoja "Mapeo". Se ignora --mapping.');
                return [];
            }

            $map     = [];
            $maxRow  = $sheet->getHighestRow();

            // Fila 1 = encabezado; col 1=TELA, 2=COLOR, 6=REF_MANUAL
            for ($row = 2; $row <= $maxRow; $row++) {
                $tela    = strtolower(trim((string) $sheet->getCellByColumnAndRow(1, $row)->getValue()));
                $color   = strtolower(trim((string) $sheet->getCellByColumnAndRow(2, $row)->getValue()));
                $manual  = trim((string) $sheet->getCellByColumnAndRow(6, $row)->getValue());

                if ($tela === '' && $color === '') {
                    continue;  // fila vacía o leyenda
                }

                if ($manual !== '') {
                    $map[$tela . '||' . $color] = $manual;
                }
            }

            return $map;
        } catch (\Throwable $e) {
            $this->warn('Error leyendo Excel de mapping: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca la mejor referencia de ítem para una combinación TELA + COLOR.
     *
     * Estrategia:
     *  1. Candidatos = ítems cuyo COLOR indexado coincide exactamente.
     *  2. Filtra candidatos cuyo nombre contiene la frase TELA completa.
     *  3. Si no hay resultado, reintenta con sólo la primera palabra de TELA.
     *  4. Entre los candidatos válidos, elige el de nombre más corto.
     */
    private function findBestMatch(string $tela, string $color, array $colorIndex): string
    {
        $candidates = $colorIndex[$color] ?? [];

        if (empty($candidates)) {
            return '';
        }

        // Intento 1: frase TELA completa en el nombre del ítem
        $matches = array_values(array_filter(
            $candidates,
            fn ($c) => str_contains(strtolower($c['name']), $tela)
        ));

        // Intento 2: sólo primera palabra de TELA (útil para telas con código numérico)
        if (empty($matches) && str_contains($tela, ' ')) {
            $firstWord = explode(' ', $tela)[0];
            $matches   = array_values(array_filter(
                $candidates,
                fn ($c) => str_contains(strtolower($c['name']), $firstWord)
            ));
        }

        if (empty($matches)) {
            return '';
        }

        // El más específico = nombre más corto entre los candidatos válidos
        usort($matches, fn ($a, $b) => strlen($a['name']) <=> strlen($b['name']));

        return $matches[0]['reference'];
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  Utilidades
    // ═══════════════════════════════════════════════════════════════════════

    private function writeCsv(string $path, array $rows): void
    {
        $temp   = $path . '.tmp';
        $handle = fopen($temp, 'w');

        // Escribir BOM UTF-8 para compatibilidad con Excel
        fwrite($handle, "\xEF\xBB\xBF");

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);
        rename($temp, $path);
    }

    /** Descarta el BOM UTF-8 si existe al inicio del archivo. */
    private function skipBom($handle): void
    {
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }
    }
}
