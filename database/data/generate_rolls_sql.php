<?php
/**
 * Script generador de SQL para importar rollos históricos.
 * Uso: php database/data/generate_rolls_sql.php > database/data/fabric_rolls_historico.sql
 */

$csvFile = __DIR__ . '/rollos.csv';

if (!file_exists($csvFile)) {
    fwrite(STDERR, "ERROR: No se encontró el archivo $csvFile\n");
    exit(1);
}

$contents = file_get_contents($csvFile);
// Quitar BOM UTF-8
$contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents);
$lines = preg_split('/\r\n|\r|\n/', trim($contents));

function parseCsvDate(string $value): string {
    $value = trim($value);
    if ($value === '') return date('Y-m-d');
    foreach (['d/m/Y H:i', 'd/m/Y'] as $fmt) {
        $d = DateTime::createFromFormat($fmt, $value);
        if ($d !== false) return $d->format('Y-m-d');
    }
    $ts = strtotime($value);
    return $ts !== false ? date('Y-m-d', $ts) : date('Y-m-d');
}

function esc(string $v): string {
    return str_replace(["\\", "'"], ["\\\\", "\\'"], $v);
}

function floatVal2(string $v): float {
    return (float) str_replace(',', '.', trim($v));
}

$inserts = [];
$skipped = 0;

// Saltar cabecera
foreach (array_slice($lines, 1) as $i => $line) {
    $line = trim($line);
    if ($line === '') continue;

    $row = str_getcsv($line, ',');
    // Asegurar al menos 15 columnas
    while (count($row) < 15) $row[] = '';

    $rollNumber = trim($row[0]);
    if ($rollNumber === '') { $skipped++; continue; }

    $location  = trim($row[1]) !== '' ? trim($row[1]) : null;
    $baseTela  = strtoupper(trim($row[4]));
    $tela      = strtoupper(trim($row[5]));
    $desc      = ($baseTela !== '' && $tela !== '') ? "$baseTela - $tela"
               : ($baseTela ?: ($tela ?: null));

    $colorName = strtoupper(trim($row[6])) !== '' ? strtoupper(trim($row[6])) : null;
    $colorCode = trim($row[7]) !== '' ? trim($row[7]) : null;
    $width     = floatVal2($row[8]) ?: null;
    $qty       = floatVal2($row[9]);
    $partOut   = floatVal2($row[10]);
    $actOut    = floatVal2($row[12]);
    $entryDate = parseCsvDate($row[14]);

    if ($qty <= 0) { $skipped++; continue; }

    $locSql      = $location  ? "'" . esc($location)  . "'" : 'NULL';
    $descSql     = $desc      ? "'" . esc($desc)      . "'" : 'NULL';
    $colorNSql   = $colorName ? "'" . esc($colorName) . "'" : 'NULL';
    $colorCSql   = $colorCode ? "'" . esc($colorCode) . "'" : 'NULL';
    $widthSql    = $width !== null ? $width : 'NULL';

    $inserts[] = sprintf(
        "('%s', %s, %s, %s, %s, %s, %.4f, %.4f, %.4f, '%s', 'approved', NULL, NULL, NOW(), NOW())",
        esc($rollNumber),
        $locSql,
        $descSql,
        $colorNSql,
        $colorCSql,
        $widthSql,
        $qty,
        $partOut,
        $actOut,
        $entryDate
    );
}

$now = date('Y-m-d H:i:s');
echo "-- Generado: $now\n";
echo "-- Rollos a insertar: " . count($inserts) . " | Omitidos: $skipped\n\n";

echo "-- ============================================================\n";
echo "-- PASO 1: Limpiar kardex y stock de ítems de tela\n";
echo "-- ============================================================\n";
echo "DELETE FROM inventory_movements\n";
echo "  WHERE item_id IN (SELECT item_id FROM fabric_rolls WHERE item_id IS NOT NULL);\n\n";
echo "DELETE FROM warehouse_items\n";
echo "  WHERE item_id IN (SELECT item_id FROM fabric_rolls WHERE item_id IS NOT NULL);\n\n";

echo "-- ============================================================\n";
echo "-- PASO 2: Borrar todos los rollos existentes\n";
echo "-- ============================================================\n";
echo "DELETE FROM fabric_roll_adjustments;\n";
echo "DELETE FROM fabric_rolls;\n\n";

echo "-- ============================================================\n";
echo "-- PASO 3: Insertar rollos históricos (" . count($inserts) . " registros)\n";
echo "-- ============================================================\n";
echo "INSERT INTO fabric_rolls\n";
echo "  (roll_number, location, description, internal_color_name, internal_color_code, roll_width,\n";
echo "   quantity, partial_output, actual_output, entry_date, approval_status,\n";
echo "   item_id, purchase_document_item_id, created_at, updated_at)\nVALUES\n";
echo implode(",\n", $inserts) . ";\n\n";
echo "-- FIN\n";
