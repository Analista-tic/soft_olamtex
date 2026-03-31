<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Genera db/roll_item_mapping.xlsx con TRES hojas:
 *
 *  1. "Mapeo"      — combinaciones únicas TELA×COLOR para editar REF_MANUAL
 *                    (Verde = auto-match OK, Naranja = requiere llenado manual)
 *
 *  2. "Telas"      — TODOS los ítems de subgrupo "Tela" de los CSVs de items
 *                    (referencia de búsqueda: REF, NOMBRE, COLOR, GRUPO, SUBGRUPO)
 *
 *  3. "Rollos"     — TODOS los registros individuales de rollos.csv
 *                    (referencia: #ROLLO, TELA, COLOR, KG, ANCHO, PROVEEDOR, PRECIO)
 *
 * Flujo:
 *   1. php artisan import:export-roll-mapping
 *   2. Abre el Excel → hoja "Mapeo" → rellena columna REF_MANUAL en filas naranjas
 *      (usa "Telas" y "Rollos" como referencia)
 *   3. php artisan import:match-rolls-items --mapping=db/roll_item_mapping.xlsx --in-place
 */
class ExportRollMappingCommand extends Command
{
    protected $signature   = 'import:export-roll-mapping';
    protected $description = 'Genera Excel editable (Mapeo + Telas + Rollos) para mapear rollos → ítems';

    private const GREEN_BG   = 'FFD6EFC7';
    private const ORANGE_BG  = 'FFFFD9B3';
    private const HEADER_BG  = 'FF2D5F8A';
    private const HEADER2_BG = 'FF3A7CA5';
    private const HEADER3_BG = 'FF4A6741';
    private const MANUAL_BG  = 'FFFFFFCC';
    private const MANUAL_BG2 = 'FFFFF0AA';
    private const ALT_ROW    = 'FFF5F5F5';

    public function handle(): int
    {
        ini_set('memory_limit', '512M');

        $rollsPath  = base_path('db/rollos.csv');
        $itemFiles  = [
            base_path('db/Base de datos normalizada 1 - Items.csv'),
            base_path('db/Base de datos normalizada 2 - Items.csv'),
        ];
        $outputPath = base_path('db/roll_mapping.xlsx');

        if (! file_exists($rollsPath)) {
            $this->error("No encontrado: {$rollsPath}");
            return 1;
        }

        // ── 1. Ítems ───────────────────────────────────────────────────────
        $this->info('Leyendo CSVs de ítems…');
        [$colorIndex, $allItems] = $this->loadItems($itemFiles);
        $this->info('Ítems tipo Tela: ' . count($allItems));

        // ── 2. Rollos ──────────────────────────────────────────────────────
        $this->info('Leyendo rollos.csv…');
        $allRolls = $this->loadRolls($rollsPath);
        $this->info('Rollos cargados: ' . count($allRolls));

        // ── 3. Combos únicos TELA×COLOR ───────────────────────────────────
        $combos = $this->buildCombos($allRolls, $colorIndex);
        $this->info('Combinaciones únicas TELA×COLOR: ' . count($combos));

        // ── 4. Excel ───────────────────────────────────────────────────────
        $this->info('Generando Excel (puede tardar unos segundos)…');
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setTitle('Mapeo Rollos → Ítems')
            ->setDescription('Hoja Mapeo: editar REF_MANUAL | Hojas Telas y Rollos: solo referencia');

        $this->buildMappingSheet($spreadsheet, $combos);
        $this->buildTelasSheet($spreadsheet, $allItems);
        $this->buildRollosSheet($spreadsheet, $allRolls);
        $spreadsheet->setActiveSheetIndex(0);

        $this->info('Escribiendo archivo…');
        (new Xlsx($spreadsheet))->save($outputPath);

        $matched   = count(array_filter($combos, fn ($c) => $c['ref_auto'] !== ''));
        $unmatched = count($combos) - $matched;

        $this->info("Excel generado: db/roll_mapping.xlsx");
        $this->table(
            ['Hoja', 'Registros', 'Descripción'],
            [
                ['Mapeo',  count($combos) . ' combos',      "Verde={$matched} auto-match | Naranja={$unmatched} manual"],
                ['Telas',  count($allItems) . ' ítems',     'Catálogo completo subgrupo Tela'],
                ['Rollos', count($allRolls) . ' rollos',    'Todos los registros de rollos.csv'],
            ]
        );
        $this->newLine();
        $this->comment('Pasos:');
        $this->comment('  1. Abre db/roll_item_mapping.xlsx');
        $this->comment('  2. Hoja "Mapeo" → rellena REF_MANUAL en filas NARANJAS');
        $this->comment('     (busca la referencia en hoja "Telas" columna A)');
        $this->comment('  3. Guarda y ejecuta:');
        $this->comment('     php -d memory_limit=512M artisan import:match-rolls-items --mapping=db/roll_mapping.xlsx --in-place');

        return 0;
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  Carga de datos
    // ═══════════════════════════════════════════════════════════════════════

    private function loadItems(array $files): array
    {
        $colorIndex = [];
        $allItems   = [];

        foreach ($files as $file) {
            if (! file_exists($file)) {
                $this->warn("No encontrado, se omite: {$file}");
                continue;
            }

            $handle = fopen($file, 'r');
            $this->skipBom($handle);
            $hm = $this->parseHeaderMap(fgetcsv($handle));

            $colTipo   = $hm['TIPO DE ITEM']       ?? 0;
            $colSub    = $hm['SUBGRUPO']            ?? 3;
            $colGrupo  = $hm['GRUPO']               ?? 2;
            $colNombre = $hm['NOMBRE']               ?? 1;
            $colRef    = $hm['REFERENCIA']           ?? 11;
            $colDesc   = $hm['DESCRIPCION']          ?? 12;
            $colUm     = $hm['UNIDAD DE MEDIDA']     ?? 9;
            $colCampos = $hm['CAMPOS ADICIONALES']   ?? 14;
            $colVals   = $hm['VALORES ADICIONALES']  ?? 15;
            $colEstado = $hm['ESTADO']               ?? 16;

            while (($d = fgetcsv($handle)) !== false) {
                if (count($d) < 15) {
                    continue;
                }
                if (strtolower(trim($d[$colSub] ?? '')) !== 'tela') {
                    continue;
                }

                $nombre    = trim($d[$colNombre] ?? '');
                $referencia = trim($d[$colRef]   ?? '');
                $campos    = trim($d[$colCampos] ?? '');
                $valores   = trim($d[$colVals]   ?? '');
                $colors    = $this->extractColors($campos, $valores);
                $color     = $colors[0] ?? '';

                foreach ($colors as $c) {
                    $colorIndex[strtolower($c)][] = ['name' => $nombre, 'reference' => $referencia];
                }

                $allItems[] = [
                    'referencia' => $referencia,
                    'nombre'     => $nombre,
                    'color'      => $color,
                    'grupo'      => trim($d[$colGrupo]  ?? ''),
                    'subgrupo'   => trim($d[$colSub]    ?? ''),
                    'descripcion'=> trim($d[$colDesc]   ?? ''),
                    'um'         => trim($d[$colUm]     ?? ''),
                    'estado'     => trim($d[$colEstado] ?? ''),
                ];
            }

            fclose($handle);
        }

        usort($allItems, fn ($a, $b) => strcmp($a['color'] . $a['nombre'], $b['color'] . $b['nombre']));

        return [$colorIndex, $allItems];
    }

    private function loadRolls(string $path): array
    {
        $rolls = [];
        $handle = fopen($path, 'r');
        $this->skipBom($handle);

        while (($d = fgetcsv($handle)) !== false) {
            if (count($d) < 13) {
                continue;
            }
            $rolls[] = [
                'rollo'      => trim($d[12] ?? ''),
                'tela'       => trim($d[4]  ?? ''),
                'base_tela'  => trim($d[3]  ?? ''),
                'color'      => trim($d[5]  ?? ''),
                'cod_color'  => trim($d[6]  ?? ''),
                'kg'         => trim($d[7]  ?? ''),
                'ancho'      => trim($d[8]  ?? ''),
                'proveedor'  => trim($d[1]  ?? ''),
                'precio'     => trim($d[13] ?? ''),
                'ubicacion'  => trim($d[10] ?? ''),
                'fecha'      => trim($d[16] ?? ''),
                'salida'     => trim($d[18] ?? 'NULL'),
            ];
        }

        fclose($handle);

        return $rolls;
    }

    private function buildCombos(array $rolls, array $colorIndex): array
    {
        $combos = [];

        foreach ($rolls as $roll) {
            $tela  = $roll['tela'];
            $color = $roll['color'];
            $key   = strtolower($tela) . '||' . strtolower($color);

            if (! isset($combos[$key])) {
                $match = $this->findBestMatch(strtolower($tela), strtolower($color), $colorIndex);
                $combos[$key] = [
                    'tela'      => $tela,
                    'color'     => $color,
                    'count'     => 0,
                    'ref_auto'  => $match['reference'],
                    'name_auto' => $match['name'],
                ];
            }
            $combos[$key]['count']++;
        }

        usort($combos, function ($a, $b) {
            if (($a['ref_auto'] === '') !== ($b['ref_auto'] === '')) {
                return $a['ref_auto'] === '' ? -1 : 1;
            }
            return strcmp($a['tela'] . $a['color'], $b['tela'] . $b['color']);
        });

        return array_values($combos);
    }

    private function findBestMatch(string $tela, string $color, array $colorIndex): array
    {
        $candidates = $colorIndex[$color] ?? [];
        if (empty($candidates)) {
            return ['reference' => '', 'name' => ''];
        }

        $matches = array_values(array_filter(
            $candidates,
            fn ($c) => str_contains(strtolower($c['name']), $tela)
        ));

        if (empty($matches) && str_contains($tela, ' ')) {
            $first   = explode(' ', $tela)[0];
            $matches = array_values(array_filter(
                $candidates,
                fn ($c) => str_contains(strtolower($c['name']), $first)
            ));
        }

        if (empty($matches)) {
            return ['reference' => '', 'name' => ''];
        }

        usort($matches, fn ($a, $b) => strlen($a['name']) <=> strlen($b['name']));

        return ['reference' => $matches[0]['reference'], 'name' => $matches[0]['name']];
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  Hoja 1 — "Mapeo"  (editable)
    // ═══════════════════════════════════════════════════════════════════════

    private function buildMappingSheet(Spreadsheet $spreadsheet, array $combos): void
    {
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Mapeo');

        $headers = ['A' => 'TELA (rollos)', 'B' => 'COLOR (rollos)', 'C' => '# ROLLOS',
                    'D' => 'REF_AUTO',       'E' => 'NOMBRE_AUTO',   'F' => 'REF_MANUAL', 'G' => 'NOTAS'];

        foreach ($headers as $col => $label) {
            $sheet->getCell("{$col}1")->setValue($label);
        }

        $sheet->getStyle('A1:G1')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE], 'size' => 11],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::HEADER_BG]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);

        foreach ($combos as $i => $combo) {
            $row      = $i + 2;
            $hasMatch = $combo['ref_auto'] !== '';
            $rowBg    = $hasMatch ? self::GREEN_BG : self::ORANGE_BG;
            $manualBg = $hasMatch ? self::MANUAL_BG : self::MANUAL_BG2;

            $sheet->getCell("A{$row}")->setValue($combo['tela']);
            $sheet->getCell("B{$row}")->setValue($combo['color']);
            $sheet->getCell("C{$row}")->setValue($combo['count']);
            $sheet->getCell("D{$row}")->setValue($combo['ref_auto']);
            $sheet->getCell("E{$row}")->setValue($combo['name_auto']);
            $sheet->getCell("F{$row}")->setValue('');
            $sheet->getCell("G{$row}")->setValue('');

            $sheet->getStyle("A{$row}:E{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($rowBg);

            $sheet->getStyle("F{$row}")->applyFromArray([
                'fill'   => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $manualBg]],
                'font'   => ['bold' => ! $hasMatch],
                'border' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCCCCCC']]],
            ]);
        }

        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(10);
        $sheet->getColumnDimension('D')->setWidth(14);
        $sheet->getColumnDimension('E')->setWidth(44);
        $sheet->getColumnDimension('F')->setWidth(16);
        $sheet->getColumnDimension('G')->setWidth(30);

        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:G1');

        // Leyenda
        $lr = count($combos) + 3;
        $sheet->getCell("A{$lr}")->setValue('LEYENDA:');
        $sheet->getStyle("A{$lr}")->getFont()->setBold(true);
        $sheet->getCell("B{$lr}")->setValue('Verde = auto-match encontrado');
        $sheet->getStyle("B{$lr}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::GREEN_BG);
        $sheet->getCell("C{$lr}")->setValue('Naranja = llenar REF_MANUAL (col F)');
        $sheet->getStyle("C{$lr}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::ORANGE_BG);
        $sheet->getStyle("B{$lr}:C{$lr}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("B{$lr}:C{$lr}")->getFont()->setBold(true);
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  Hoja 2 — "Telas"  (todos los ítems, solo referencia)
    // ═══════════════════════════════════════════════════════════════════════

    private function buildTelasSheet(Spreadsheet $spreadsheet, array $items): void
    {
        $sheet = $spreadsheet->createSheet()->setTitle('Telas');

        $headers = ['A' => 'REFERENCIA', 'B' => 'NOMBRE', 'C' => 'COLOR',
                    'D' => 'GRUPO',       'E' => 'SUBGRUPO', 'F' => 'DESCRIPCION',
                    'G' => 'UM',          'H' => 'ESTADO'];

        foreach ($headers as $col => $label) {
            $sheet->getCell("{$col}1")->setValue($label);
        }

        $sheet->getStyle('A1:H1')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE], 'size' => 11],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::HEADER2_BG]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(20);

        foreach ($items as $i => $item) {
            $row = $i + 2;
            $sheet->getCell("A{$row}")->setValue($item['referencia']);
            $sheet->getCell("B{$row}")->setValue($item['nombre']);
            $sheet->getCell("C{$row}")->setValue($item['color']);
            $sheet->getCell("D{$row}")->setValue($item['grupo']);
            $sheet->getCell("E{$row}")->setValue($item['subgrupo']);
            $sheet->getCell("F{$row}")->setValue($item['descripcion']);
            $sheet->getCell("G{$row}")->setValue($item['um']);
            $sheet->getCell("H{$row}")->setValue($item['estado']);

            // Filas alternas para facilitar lectura
            if ($row % 2 === 0) {
                $sheet->getStyle("A{$row}:H{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::ALT_ROW);
            }
        }

        $sheet->getColumnDimension('A')->setWidth(14);
        $sheet->getColumnDimension('B')->setWidth(46);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(14);
        $sheet->getColumnDimension('E')->setWidth(12);
        $sheet->getColumnDimension('F')->setWidth(22);
        $sheet->getColumnDimension('G')->setWidth(8);
        $sheet->getColumnDimension('H')->setWidth(12);

        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:H1');
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  Hoja 3 — "Rollos"  (todos los registros, solo referencia)
    // ═══════════════════════════════════════════════════════════════════════

    private function buildRollosSheet(Spreadsheet $spreadsheet, array $rolls): void
    {
        $sheet = $spreadsheet->createSheet()->setTitle('Rollos');

        $headers = ['A' => '# ROLLO', 'B' => 'TELA', 'C' => 'BASE TELA', 'D' => 'COLOR',
                    'E' => 'COD COLOR', 'F' => 'KG', 'G' => 'ANCHO', 'H' => 'PROVEEDOR',
                    'I' => 'PRECIO',   'J' => 'UBICACION', 'K' => 'FECHA', 'L' => 'SALIDA'];

        foreach ($headers as $col => $label) {
            $sheet->getCell("{$col}1")->setValue($label);
        }

        $sheet->getStyle('A1:L1')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE], 'size' => 11],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::HEADER3_BG]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(20);

        foreach ($rolls as $i => $roll) {
            $row = $i + 2;
            $sheet->getCell("A{$row}")->setValue($roll['rollo']);
            $sheet->getCell("B{$row}")->setValue($roll['tela']);
            $sheet->getCell("C{$row}")->setValue($roll['base_tela']);
            $sheet->getCell("D{$row}")->setValue($roll['color']);
            $sheet->getCell("E{$row}")->setValue($roll['cod_color']);
            $sheet->getCell("F{$row}")->setValue($roll['kg']);
            $sheet->getCell("G{$row}")->setValue($roll['ancho']);
            $sheet->getCell("H{$row}")->setValue($roll['proveedor']);
            $sheet->getCell("I{$row}")->setValue($roll['precio']);
            $sheet->getCell("J{$row}")->setValue($roll['ubicacion']);
            $sheet->getCell("K{$row}")->setValue($roll['fecha']);
            $sheet->getCell("L{$row}")->setValue($roll['salida'] === 'NULL' ? '' : $roll['salida']);

            if ($row % 2 === 0) {
                $sheet->getStyle("A{$row}:L{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::ALT_ROW);
            }
        }

        $sheet->getColumnDimension('A')->setWidth(10);
        $sheet->getColumnDimension('B')->setWidth(24);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(16);
        $sheet->getColumnDimension('E')->setWidth(12);
        $sheet->getColumnDimension('F')->setWidth(8);
        $sheet->getColumnDimension('G')->setWidth(8);
        $sheet->getColumnDimension('H')->setWidth(26);
        $sheet->getColumnDimension('I')->setWidth(10);
        $sheet->getColumnDimension('J')->setWidth(12);
        $sheet->getColumnDimension('K')->setWidth(14);
        $sheet->getColumnDimension('L')->setWidth(10);

        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:L1');
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  Utilidades
    // ═══════════════════════════════════════════════════════════════════════

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

    private function parseHeaderMap(array $headers): array
    {
        $map = [];
        foreach ($headers as $i => $h) {
            $map[strtoupper(trim($h))] = $i;
        }
        return $map;
    }

    private function skipBom($handle): void
    {
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }
    }
}
