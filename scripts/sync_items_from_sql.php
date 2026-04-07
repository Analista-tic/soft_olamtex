<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function out(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

function fail(string $message, int $code = 1): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit($code);
}

$basePath = dirname(__DIR__);
$sqlPath = $basePath . '/db/items.sql';
$dryRun = in_array('--dry-run', $argv, true);

if (!is_file($sqlPath)) {
    fail("No existe el archivo SQL en: {$sqlPath}");
}

$content = file_get_contents($sqlPath);
if ($content === false) {
    fail('No se pudo leer db/items.sql');
}

if (!preg_match('/CREATE TABLE IF NOT EXISTS `items`\s*\([\s\S]*?\)\s*ENGINE=.*?;/i', $content, $createMatch)) {
    fail('No se encontró el bloque CREATE TABLE de items en db/items.sql');
}

$insertStatements = [];
$lines = preg_split('/\R/', $content) ?: [];
$buffer = '';
$capturing = false;

foreach ($lines as $line) {
    if (!$capturing) {
        if (str_starts_with($line, 'INSERT INTO `items`')) {
            $capturing = true;
            $buffer = $line . PHP_EOL;
        }
        continue;
    }

    $buffer .= $line . PHP_EOL;
    if (preg_match('/;\s*$/', trim($line)) === 1) {
        $insertStatements[] = $buffer;
        $buffer = '';
        $capturing = false;
    }
}

if (count($insertStatements) === 0) {
    fail('No se encontraron INSERT INTO `items` en db/items.sql');
}

$currentColumns = Schema::getColumnListing('items');
if (count($currentColumns) === 0) {
    fail('No se pudieron leer columnas actuales de la tabla items');
}

$tempTable = 'items_import_tmp';
$timestamp = date('Ymd_His');
$backupTable = "items_backup_{$timestamp}";

$createTempSql = str_replace('CREATE TABLE IF NOT EXISTS `items`', "CREATE TABLE IF NOT EXISTS `{$tempTable}`", $createMatch[0]);
$createTempSql = preg_replace('/AUTO_INCREMENT=\d+/i', '', $createTempSql) ?? $createTempSql;

out('Iniciando sincronización segura de items desde db/items.sql...');
if ($dryRun) {
    out('Modo: DRY RUN (sin cambios en tabla items).');
    out('Paso 1/5: Omitir respaldo por modo dry-run.');
} else {
    out('Modo: EJECUCIÓN REAL.');
    out('Paso 1/5: Crear respaldo de items (IDs 1..5749).');

    DB::statement("DROP TABLE IF EXISTS `{$backupTable}`");
    DB::statement("CREATE TABLE `{$backupTable}` LIKE `items`");
    DB::statement("INSERT INTO `{$backupTable}` SELECT * FROM `items` WHERE `id` BETWEEN 1 AND 5749");
    out("Respaldo creado: {$backupTable}");
}

out('Paso 2/5: Crear tabla temporal de importación.');

DB::statement("DROP TABLE IF EXISTS `{$tempTable}`");
DB::unprepared($createTempSql);

out('Paso 3/5: Cargar INSERTs del dump a tabla temporal.');

foreach ($insertStatements as $index => $statement) {
    $sql = str_replace('INSERT INTO `items`', "INSERT INTO `{$tempTable}`", $statement);
    DB::unprepared($sql);

    if (($index + 1) % 20 === 0) {
        out('  - INSERTs procesados: ' . ($index + 1) . '/' . count($insertStatements));
    }
}

$outCols = array_map(static fn(string $col): string => "`{$col}`", $currentColumns);
$insertColsSql = implode(', ', $outCols);

$nonUpdatable = ['id', 'created_at'];
$updateCols = array_values(array_filter(
    $currentColumns,
    static fn(string $col): bool => !in_array($col, $nonUpdatable, true)
));

$updateSetSql = implode(",\n    ", array_map(static fn(string $col): string => "`{$col}` = VALUES(`{$col}`)", $updateCols));

$upsertSql = "INSERT INTO `items` ({$insertColsSql})\n"
    . "SELECT {$insertColsSql}\n"
    . "FROM `{$tempTable}`\n"
    . "ON DUPLICATE KEY UPDATE\n    {$updateSetSql}";

out('Paso 4/5: Ejecutar UPSERT en items (sin borrar IDs existentes).');

DB::statement("UPDATE `{$tempTable}` SET `bar_code` = NULL WHERE `bar_code` IS NOT NULL AND TRIM(`bar_code`) IN ('', '.', '-', 'N/A', 'n/a')");
DB::statement("UPDATE `{$tempTable}` t JOIN (SELECT `bar_code` FROM `{$tempTable}` WHERE `bar_code` IS NOT NULL GROUP BY `bar_code` HAVING COUNT(*) > 1) d ON d.`bar_code` = t.`bar_code` SET t.`bar_code` = NULL");
DB::statement("UPDATE `{$tempTable}` t JOIN `items` i ON i.`bar_code` = t.`bar_code` AND i.`id` <> t.`id` SET t.`bar_code` = NULL WHERE t.`bar_code` IS NOT NULL");
DB::statement("DELETE t FROM `{$tempTable}` t LEFT JOIN `category_sub_groups` csg ON csg.`id` = t.`category_sub_group_id` WHERE csg.`id` IS NULL");
DB::statement("UPDATE `{$tempTable}` t LEFT JOIN `taxes` tx ON tx.`id` = t.`tax_id` SET t.`tax_id` = NULL WHERE t.`tax_id` IS NOT NULL AND tx.`id` IS NULL");
DB::statement("UPDATE `{$tempTable}` t LEFT JOIN `accounting_accounts` a ON a.`id` = t.`income_account_id` SET t.`income_account_id` = NULL WHERE t.`income_account_id` IS NOT NULL AND a.`id` IS NULL");
DB::statement("UPDATE `{$tempTable}` t LEFT JOIN `accounting_accounts` a ON a.`id` = t.`inventory_account_id` SET t.`inventory_account_id` = NULL WHERE t.`inventory_account_id` IS NOT NULL AND a.`id` IS NULL");
DB::statement("UPDATE `{$tempTable}` t LEFT JOIN `accounting_accounts` a ON a.`id` = t.`cost_of_sale_account_id` SET t.`cost_of_sale_account_id` = NULL WHERE t.`cost_of_sale_account_id` IS NOT NULL AND a.`id` IS NULL");

$tmpTotal = (int) DB::table($tempTable)->count();
$tmpInsert = (int) DB::table("{$tempTable} as t")
    ->leftJoin('items as i', 'i.id', '=', 't.id')
    ->whereNull('i.id')
    ->count();
$tmpUpdate = (int) DB::table("{$tempTable} as t")
    ->join('items as i', 'i.id', '=', 't.id')
    ->count();

if ($dryRun) {
    out('DRY RUN: UPSERT no ejecutado.');
    out("DRY RUN resumen -> tmp_total={$tmpTotal}, inserciones_estimadas={$tmpInsert}, actualizaciones_estimadas={$tmpUpdate}");
} else {
    $affected = DB::affectingStatement($upsertSql);
    out("UPSERT ejecutado. Filas afectadas (MySQL): {$affected}");
}

out('Paso 5/5: Limpiar tabla temporal.');
DB::statement("DROP TABLE IF EXISTS `{$tempTable}`");

$totalItems = (int) DB::table('items')->count();
$maxId = (int) DB::table('items')->max('id');

out('Sincronización finalizada correctamente.');
out("items.total={$totalItems} | items.max_id={$maxId}");
if (!$dryRun) {
    out("Respaldo disponible en tabla: {$backupTable}");
}
