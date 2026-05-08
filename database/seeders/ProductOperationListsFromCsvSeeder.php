<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ProductOperationListsFromCsvSeeder extends Seeder
{
    private const CSV_PATH = __DIR__ . '/data/PLANTILLA LISTA OPERACIONAL - BASE DE DATOS OPERACIONES.csv';

    /** @var array<string, int> */
    private array $machineMap = [];

    /** @var array<int, float> */
    private array $machineSupplementMap = [];

    /** @var array<int, int> */
    private array $sectionMap = [];

    /** @var array<string, int> */
    private array $productSheetMap = [];

    /** @var array<int, string> */
    private array $skippedRows = [];

    /** @var array<string, bool> */
    private array $productSheetOperationColumns = [];

    public function run(): void
    {
        $this->guardCsvExists();

        $this->command->info('Cargando catálogos de referencia...');
        $this->loadMaps();
        $this->loadProductSheetOperationColumns();

        $rows = $this->parseCsv();
        if ($rows === []) {
            $this->command->warn('No se encontraron filas para procesar.');
            return;
        }

        [$operationsRows, $relationRows] = $this->buildRows($rows);

        $insertedOperations = $this->upsertOperations($operationsRows);
        $insertedRelations = $this->upsertProductSheetOperations($relationRows);

        $this->report($insertedOperations, $insertedRelations);
    }

    /**
     * @param array<int, array<string, string>> $rows
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    private function buildRows(array $rows): array
    {
        $operationsByCode = [];
        $pendingRelations = [];
        $sequenceBySheet = [];

        foreach ($rows as $row) {
            $csvId = $this->parseInt($row['ID'] ?? null, 0);

            $codigo = mb_strtoupper(trim((string) ($row['CODIGO'] ?? '')));
            if ($codigo === '') {
                $this->skip($csvId, 'CODIGO vacío');
                continue;
            }

            $sectionId = $this->resolveSectionId((string) ($row['SECTION_ID'] ?? ''));
            if ($sectionId === null) {
                $this->skip($csvId, 'SECTION_ID inválido o no existe');
                continue;
            }

            $machineCode = mb_strtoupper(trim((string) ($row['MACHINE_ID_REFERENCE'] ?? '')));
            $machineId = $this->machineMap[$machineCode] ?? null;
            if ($machineCode !== '' && $machineId === null) {
                Log::warning("ProductOperationListsFromCsvSeeder: máquina no encontrada por code '{$machineCode}' (fila {$csvId}).");
            }

            $productReference = trim((string) ($row['PRODUCT_SHEET_ID_REFERENCE'] ?? ''));
            $productSheetId = $this->productSheetMap[$productReference] ?? null;
            if ($productReference !== '' && $productSheetId === null) {
                Log::warning("ProductOperationListsFromCsvSeeder: product_reference no encontrado '{$productReference}' (fila {$csvId}).");
            }

            $samBase = max(0, $this->parseFloat($row['SAM_BASE'] ?? null, 0.0));
            $factorOperarioPercent = max(0.01, $this->parseFloat($row['FACTOR_OPERARIO'] ?? null, 100.0));
            $factorOperarioMultiplier = round($factorOperarioPercent / 100, 4);

            $action = mb_strtoupper(trim((string) ($row['ACTION'] ?? '')));
            $garmentPart = mb_strtoupper(trim((string) ($row['GARMENT_PART'] ?? '')));
            $anexo = $this->nullableUpper($row['ANEXO'] ?? $row['ANEXO '] ?? null);
            $description = mb_strtoupper(trim((string) ($row['DESCRIPTION'] ?? '')));

            $quantity = max(1, $this->parseInt($row['QUANTITY'] ?? null, 1));
            $measure = $this->parseNullableFloat($row['MEASURE'] ?? null);
            $isActive = $this->parseInt($row['IS_ACTIVE'] ?? null, 1) === 1;

            $operationsByCode[$codigo] = [
                'codigo' => $codigo,
                'action' => $action,
                'garment_part' => $garmentPart,
                'anexo' => $anexo,
                'section_id' => $sectionId,
                'measure' => $measure,
                'quantity' => $quantity,
                'description' => $description,
                'sam_base' => $samBase,
                'factor_operario' => $factorOperarioPercent,
                'product_sheet_id_reference' => $productSheetId,
                'machine_id_reference' => $machineId,
                'is_active' => $isActive,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($productSheetId === null) {
                $this->skip($csvId, "No se pudo resolver PRODUCT_SHEET_ID_REFERENCE '{$productReference}' para relación product_sheet_operations");
                continue;
            }

            if ($this->hasProductSheetOperationColumn('machine_id') && $machineId === null) {
                $this->skip($csvId, "MACHINE_ID_REFERENCE '{$machineCode}' no resuelto para relación product_sheet_operations");
                continue;
            }

            $sheetKey = (string) $productSheetId;
            $nextSequence = ($sequenceBySheet[$sheetKey] ?? 0) + 10;
            $sequenceBySheet[$sheetKey] = $nextSequence;

            $machineSupplement = $machineId !== null
                ? (float) ($this->machineSupplementMap[$machineId] ?? 1.0)
                : 1.0;

            $machineSupplement = $machineSupplement > 0 ? $machineSupplement : 1.0;
            $samTotalUnitario = round($samBase * $factorOperarioMultiplier * $machineSupplement, 4);
            $samTotalOperacion = round($samTotalUnitario * $quantity, 4);

            $pendingRelations[] = [
                'csv_id' => $csvId,
                'codigo' => $codigo,
                'product_sheet_id' => $productSheetId,
                'operation_section_id' => $sectionId,
                'machine_id' => $machineId,
                'fabric_base_id' => null,
                'quantity' => $quantity,
                'has_override' => false,
                'sequence' => $nextSequence,
                'sam_base' => $samBase,
                'sam' => $samBase,
                'fabric_base_factor' => 1.0,
                'operator_efficiency_factor' => $factorOperarioMultiplier,
                'machine_supplement_factor' => $machineSupplement,
                'sam_total_unitario' => $samTotalUnitario,
                'sam_total_operacion' => $samTotalOperacion,
                'optimal_time' => $samTotalOperacion,
                'is_active' => $isActive,
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        return [array_values($operationsByCode), $pendingRelations];
    }

    /**
     * @param array<int, array<string, mixed>> $operationsRows
     */
    private function upsertOperations(array $operationsRows): int
    {
        if ($operationsRows === []) {
            return 0;
        }

        return DB::table('operations')->insertOrIgnore($operationsRows);
    }

    /**
     * @param array<int, array<string, mixed>> $relationRows
     */
    private function upsertProductSheetOperations(array $relationRows): int
    {
        if ($relationRows === []) {
            return 0;
        }

        $codes = array_values(array_unique(array_map(
            static fn (array $row): string => (string) $row['codigo'],
            $relationRows
        )));

        $operationIdByCode = DB::table('operations')
            ->whereIn('codigo', $codes)
            ->pluck('id', 'codigo')
            ->toArray();

        $sheetIds = array_values(array_unique(array_map(
            static fn (array $row): int => (int) $row['product_sheet_id'],
            $relationRows
        )));

        $existingPairs = DB::table('product_sheet_operations')
            ->whereIn('product_sheet_id', $sheetIds)
            ->get(['product_sheet_id', 'operation_id'])
            ->mapWithKeys(static fn ($row) => [((int) $row->product_sheet_id) . '|' . ((int) $row->operation_id) => true])
            ->toArray();

        $maxSequenceBySheet = DB::table('product_sheet_operations')
            ->whereIn('product_sheet_id', $sheetIds)
            ->selectRaw('product_sheet_id, MAX(sequence) as max_sequence')
            ->groupBy('product_sheet_id')
            ->pluck('max_sequence', 'product_sheet_id')
            ->map(static fn ($value): int => (int) ($value ?? 0))
            ->toArray();

        $rowsForInsert = [];

        foreach ($relationRows as $row) {
            $codigo = (string) $row['codigo'];
            $operationId = $operationIdByCode[$codigo] ?? null;

            if ($operationId === null) {
                $this->skip((int) $row['csv_id'], "No se encontró operation_id para código '{$codigo}'");
                continue;
            }

            $productSheetId = (int) $row['product_sheet_id'];
            $pairKey = $productSheetId . '|' . (int) $operationId;

            if (isset($existingPairs[$pairKey])) {
                continue;
            }

            $nextSequence = ((int) ($maxSequenceBySheet[$productSheetId] ?? 0)) + 10;
            $maxSequenceBySheet[$productSheetId] = $nextSequence;

            $row['sequence'] = $nextSequence;

            $basePayload = [
                'product_sheet_id' => $productSheetId,
                'operation_id' => (int) $operationId,
                'operation_section_id' => (int) $row['operation_section_id'],
                'machine_id' => $row['machine_id'] !== null ? (int) $row['machine_id'] : null,
                'fabric_base_id' => $row['fabric_base_id'],
                'quantity' => (int) $row['quantity'],
                'has_override' => (bool) $row['has_override'],
                'sequence' => (int) $row['sequence'],
                'sam_base' => (float) $row['sam_base'],
                'sam' => (float) $row['sam'],
                'fabric_base_factor' => (float) $row['fabric_base_factor'],
                'operator_efficiency_factor' => (float) $row['operator_efficiency_factor'],
                'machine_supplement_factor' => (float) $row['machine_supplement_factor'],
                'sam_total_unitario' => (float) $row['sam_total_unitario'],
                'sam_total_operacion' => (float) $row['sam_total_operacion'],
                'optimal_time' => (float) $row['optimal_time'],
                'is_active' => (bool) $row['is_active'],
                'notes' => $row['notes'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];

            $rowsForInsert[] = array_filter(
                $basePayload,
                fn ($_value, string $key): bool => $this->hasProductSheetOperationColumn($key),
                ARRAY_FILTER_USE_BOTH
            );
        }

        if ($rowsForInsert === []) {
            return 0;
        }

        return DB::table('product_sheet_operations')->insertOrIgnore($rowsForInsert);
    }

    private function loadMaps(): void
    {
        $this->machineMap = DB::table('machines')
            ->whereNull('deleted_at')
            ->pluck('id', 'code')
            ->toArray();

        $this->machineSupplementMap = DB::table('machines')
            ->whereNull('deleted_at')
            ->pluck('supplement_factor', 'id')
            ->map(fn ($value): float => $value !== null ? (float) $value : 1.0)
            ->toArray();

        $this->sectionMap = DB::table('operation_sections')
            ->pluck('id', 'id')
            ->toArray();

        $this->productSheetMap = DB::table('product_sheets')
            ->whereNull('deleted_at')
            ->whereNotNull('product_reference')
            ->pluck('id', 'product_reference')
            ->toArray();

        $this->command->line('  → Máquinas: ' . count($this->machineMap));
        $this->command->line('  → Secciones: ' . count($this->sectionMap));
        $this->command->line('  → Product references: ' . count($this->productSheetMap));
    }

    private function loadProductSheetOperationColumns(): void
    {
        $columns = Schema::getColumnListing('product_sheet_operations');
        $this->productSheetOperationColumns = array_fill_keys($columns, true);
    }

    private function hasProductSheetOperationColumn(string $column): bool
    {
        return isset($this->productSheetOperationColumns[$column]);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function parseCsv(): array
    {
        $handle = fopen(self::CSV_PATH, 'r');
        if ($handle === false) {
            throw new \RuntimeException('No se pudo abrir el CSV: ' . self::CSV_PATH);
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            return [];
        }

        $header = array_map(
            static fn (string $value): string => mb_strtoupper(trim($value)),
            $header
        );

        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            if (count($line) !== count($header)) {
                continue;
            }

            $row = array_combine($header, $line);
            if (!is_array($row)) {
                continue;
            }

            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    private function resolveSectionId(string $rawSection): ?int
    {
        if (preg_match('/^(\d+)/', trim($rawSection), $matches) !== 1) {
            return null;
        }

        $sectionId = (int) $matches[1];

        return isset($this->sectionMap[$sectionId]) ? $sectionId : null;
    }

    private function parseFloat(?string $value, float $default = 0.0): float
    {
        $normalized = str_replace(',', '.', trim((string) $value));

        return is_numeric($normalized) ? (float) $normalized : $default;
    }

    private function parseNullableFloat(?string $value): ?float
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        $normalized = str_replace(',', '.', $normalized);

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private function parseInt(?string $value, int $default = 0): int
    {
        $normalized = trim((string) $value);

        return is_numeric($normalized) ? (int) $normalized : $default;
    }

    private function nullableUpper(?string $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : mb_strtoupper($normalized);
    }

    private function guardCsvExists(): void
    {
        if (!file_exists(self::CSV_PATH)) {
            throw new \RuntimeException('CSV no encontrado en: ' . self::CSV_PATH);
        }
    }

    private function skip(int $csvId, string $reason): void
    {
        $this->skippedRows[$csvId] = $reason;
    }

    private function report(int $operationCount, int $relationCount): void
    {
        $this->command->info("✓ Operaciones insertadas (sin sobrescribir): {$operationCount}");
        $this->command->info("✓ Relaciones product_sheet_operations insertadas (sin sobrescribir): {$relationCount}");

        if ($this->skippedRows === []) {
            $this->command->info('✓ Sin filas omitidas.');
            return;
        }

        $this->command->warn('⚠ Filas omitidas: ' . count($this->skippedRows));
        foreach ($this->skippedRows as $csvId => $reason) {
            $this->command->line("  - CSV ID {$csvId}: {$reason}");
        }
    }
}
