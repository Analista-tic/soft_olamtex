<?php

namespace App\Console\Commands;

use App\Services\Production\OperationImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Importa masivamente el catálogo de operaciones de producción desde la
 * plantilla "LISTA OPERACIONAL - BASE DE DATOS OPERACIONES" (CSV/XLSX).
 *
 * Modos:
 * - Por defecto: dry-run, solo reporta (no escribe en BD).
 * - --apply: ejecuta de verdad dentro de una transacción (commit al final).
 * - --sql-dump=<ruta>: resuelve toda la lógica de negocio en una transacción
 *   que SIEMPRE se revierte al final, y escribe los INSERT literales
 *   resultantes a un archivo .sql. No deja cambios aplicados en la BD.
 */
class ImportOperationalListCommand extends Command
{
    protected $signature = 'operations:import
                            {path : Ruta al archivo CSV/XLSX de la plantilla}
                            {--apply : Aplica los cambios a la base de datos (por defecto solo dry-run)}
                            {--sql-dump= : Escribe los INSERT resultantes a esta ruta .sql sin tocar la BD}
                            {--limit=20 : Máximo de filas de detalle a mostrar por sección}';

    protected $description = 'Importa masivamente el catálogo de operaciones desde la plantilla operacional';

    public function __construct(private readonly OperationImportService $importService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $path = (string) $this->argument('path');
        $apply = (bool) $this->option('apply');
        $sqlDumpPath = $this->option('sql-dump');
        $limit = max(1, (int) $this->option('limit'));

        if (! is_file($path)) {
            $this->error("El archivo no existe: {$path}");

            return self::FAILURE;
        }

        if ($apply && $sqlDumpPath) {
            $this->error('No puedes combinar --apply y --sql-dump. Elige un solo modo.');

            return self::FAILURE;
        }

        $this->info('Leyendo archivo: '.$path);
        $rows = $this->importService->readRows($path);
        $this->info('Filas de datos detectadas: '.count($rows));
        $this->line('');

        if ($sqlDumpPath) {
            $summary = $this->runSqlDumpMode($rows, (string) $sqlDumpPath);
        } elseif ($apply) {
            $this->warn('Modo APPLY: se escribirá en la base de datos.');
            $summary = DB::transaction(fn () => $this->importService->importRows($rows, true));
        } else {
            $this->comment('Modo DRY-RUN: no se escribirá nada en la base de datos.');
            $summary = $this->importService->importRows($rows, false);
        }

        $this->printSummary($summary, $limit);

        if (! $apply && ! $sqlDumpPath) {
            $this->line('');
            $this->warn('Dry-run: no se aplicaron cambios. Usa --apply o --sql-dump=<ruta> según el flujo que necesites.');
        }

        return self::SUCCESS;
    }

    /**
     * Ejecuta la importación real dentro de una transacción que siempre se
     * revierte, y escribe los INSERT resultantes a un archivo .sql.
     */
    private function runSqlDumpMode(array $rows, string $sqlDumpPath): array
    {
        $this->comment('Modo SQL-DUMP: se resuelve toda la lógica de negocio dentro de una transacción que se revierte al final. No se aplican cambios en la BD.');

        DB::beginTransaction();
        try {
            $summary = $this->importService->importRows($rows, true);

            $sql = $this->buildSqlDump($summary);
        } finally {
            DB::rollBack();
        }

        $directory = dirname($sqlDumpPath);
        if ($directory !== '' && $directory !== '.' && ! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($sqlDumpPath, $sql);
        $this->info("SQL escrito en: {$sqlDumpPath}");
        $this->line('');

        return $summary;
    }

    /**
     * IMPORTANTE: este .sql está pensado para correr en un entorno (ej. producción)
     * cuyo autoincremental de `machines`/`operations` NO coincide con el de la BD
     * usada para generarlo. Por eso:
     * - `machines`/`operations` NUNCA llevan `id` literal en el INSERT (lo asigna
     *   AUTO_INCREMENT del entorno destino).
     * - Una operación que referencia una máquina NUEVA (creada en este mismo dump)
     *   usa una subquery `(SELECT id FROM machines WHERE code = '...')` en vez del
     *   id numérico resuelto en la BD de origen, que no tendría sentido en destino.
     * - `operations.codigo` (único) no se puede precomputar sin conocer el id real
     *   que asignará el destino, así que se deja NULL en el INSERT y se completa
     *   con un UPDATE final basado en el id ya asignado, acotado por el
     *   `created_at` de este lote para no tocar filas preexistentes con codigo NULL.
     * - `product_sheet_id_reference` sí queda como id literal: no son filas nuevas
     *   de este dump, son sábanas que ya existen en la BD de origen elegida como
     *   fuente de verdad de referencias reales (decisión explícita del usuario).
     */
    private function buildSqlDump(array $summary): string
    {
        $batchTimestamp = now()->toDateTimeString();

        $newMachineCodeByOriginId = collect($summary['created_machines_detail'])
            ->mapWithKeys(fn ($m) => [$m['id'] => $m['code']])
            ->all();

        $lines = [
            '-- Generado por operations:import --sql-dump',
            '-- Resultado de aplicar la lógica de resolución/normalización del importador.',
            '-- operations.machine_id_reference hacia una máquina nueva de este mismo',
            '-- lote se resuelve con subquery por `code`, no con el id de la BD de origen.',
            '-- operations.codigo se completa al final con un UPDATE basado en el id',
            '-- real que asigne el entorno destino (ver comentario antes del UPDATE).',
            '',
        ];

        $lines[] = '-- Máquinas nuevas ('.count($summary['created_machines_detail']).')';
        $lines[] = $this->buildInsertSql(
            'machines',
            $summary['created_machines_detail'],
            excludeColumns: ['id', 'deleted_at'],
            appendedColumns: [],
            overrides: fn () => [],
            createdAt: $batchTimestamp,
        );

        $lines[] = '-- Operaciones ('.count($summary['created_operations_detail']).')';
        $lines[] = $this->buildInsertSql(
            'operations',
            $summary['created_operations_detail'],
            excludeColumns: ['id', 'codigo', 'deleted_at'],
            appendedColumns: ['name', 'normalized_name', 'generated_description'],
            overrides: function (array $row) use ($newMachineCodeByOriginId) {
                $machineId = $row['machine_id_reference'] ?? null;
                if ($machineId !== null && isset($newMachineCodeByOriginId[$machineId])) {
                    $code = $newMachineCodeByOriginId[$machineId];

                    return ['machine_id_reference' => "(SELECT id FROM `machines` WHERE `code` = ".DB::connection()->getPdo()->quote($code).' LIMIT 1)'];
                }

                return [];
            },
            createdAt: $batchTimestamp,
        );

        if (! empty($summary['created_operations_detail'])) {
            $lines[] = '-- Completa el código único (OP-0000) usando el id real ya asignado por el destino.';
            $lines[] = "UPDATE `operations` SET `codigo` = CONCAT('OP-', LPAD(id, 4, '0')) WHERE `codigo` IS NULL AND `created_at` = ".DB::connection()->getPdo()->quote($batchTimestamp).';';
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  array<int,string>  $excludeColumns  Columnas reales de BD a omitir del INSERT.
     * @param  array<int,string>  $appendedColumns  Atributos "appended" del modelo (no son columnas reales) a omitir.
     * @param  callable(array):array<string,string>  $overrides  Por fila, columnas cuyo valor debe ir literal en el SQL (sin comillas/escapado) en vez del valor normal — ej. una subquery.
     */
    private function buildInsertSql(string $table, array $rows, array $excludeColumns, array $appendedColumns, callable $overrides, string $createdAt): string
    {
        if (empty($rows)) {
            return "-- (sin registros)\n";
        }

        $excludedColumns = array_merge($appendedColumns, $excludeColumns);

        $pdo = DB::connection()->getPdo();
        $columns = array_values(array_diff(array_keys($rows[0]), $excludedColumns));

        $lines = [];
        foreach ($rows as $row) {
            $row['created_at'] = $createdAt;
            $row['updated_at'] = $createdAt;
            $rowOverrides = $overrides($row);

            $values = array_map(function (string $column) use ($row, $pdo, $rowOverrides) {
                if (array_key_exists($column, $rowOverrides)) {
                    return $rowOverrides[$column];
                }

                $value = $row[$column] ?? null;

                if ($value === null) {
                    return 'NULL';
                }
                if (is_bool($value)) {
                    return $value ? '1' : '0';
                }
                if (is_array($value)) {
                    return $pdo->quote(json_encode($value, JSON_UNESCAPED_UNICODE));
                }

                return $pdo->quote((string) $value);
            }, $columns);

            $lines[] = 'INSERT INTO `'.$table.'` (`'.implode('`, `', $columns).'`) VALUES ('.implode(', ', $values).');';
        }

        return implode("\n", $lines)."\n";
    }

    private function printSummary(array $summary, int $limit): void
    {
        $this->info('Resumen');
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Total de filas de datos', $summary['total_rows']],
                ['Importadas (o importables en dry-run)', $summary['imported']],
                ['Duplicadas ya existentes en BD', $summary['duplicated_in_db']],
                ['Duplicadas dentro del mismo archivo', $summary['duplicated_in_file']],
                ['Filas con error (no importadas)', count($summary['errors'])],
                ['Máquinas nuevas detectadas/creadas', count($summary['machines_created'])],
                ['Acciones nuevas registradas en catálogo', count($summary['actions_registered'])],
                ['Partes de prenda nuevas registradas en catálogo', count($summary['garment_parts_registered'])],
                ['Operaciones sin ficha de producto resuelta', count($summary['unresolved_product_sheets'])],
            ],
        );

        if (! empty($summary['machines_created'])) {
            $this->line('');
            $this->info('Máquinas nuevas (código = nombre, supplement_factor=1.0, requieren completarse luego)');
            $rows = collect($summary['machines_created'])->take($limit)
                ->map(fn ($m) => [$m['code'], $m['rows_in_file']])
                ->all();
            $this->table(['Código', 'Filas en archivo'], $rows);
            if (count($summary['machines_created']) > $limit) {
                $this->comment('... y '.(count($summary['machines_created']) - $limit).' más.');
            }
        }

        if (! empty($summary['unresolved_product_sheets'])) {
            $this->line('');
            $this->info('Operaciones sin ficha de producto resuelta (quedan con raw_product_sheet_reference)');
            $rows = collect($summary['unresolved_product_sheets'])->take($limit)
                ->map(fn ($u) => [$u['row'], $u['raw_reference']])
                ->all();
            $this->table(['Fila CSV', 'Referencia cruda'], $rows);
            if (count($summary['unresolved_product_sheets']) > $limit) {
                $this->comment('... y '.(count($summary['unresolved_product_sheets']) - $limit).' más.');
            }
        }

        if (! empty($summary['errors'])) {
            $this->line('');
            $this->warn('Filas con error (no importadas)');
            $rows = collect($summary['errors'])->take($limit)
                ->map(fn ($e) => [$e['row'], $e['reason']])
                ->all();
            $this->table(['Fila CSV', 'Motivo'], $rows);
            if (count($summary['errors']) > $limit) {
                $this->comment('... y '.(count($summary['errors']) - $limit).' más.');
            }
        }
    }
}
