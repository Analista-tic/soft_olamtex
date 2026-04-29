<?php

namespace Database\Seeders;

use App\Models\MasterData\Item;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Seeder para migrar rollos de tela históricos desde CSV del sistema legado.
 *
 * Vincula cada rollo a su ítem de tela en warehouse_items via item_referencia.
 * Tras insertar los rollos recalcula quantity en warehouse_items como
 * la suma de actual_output de todos los rollos asociados al ítem.
 *
 * Archivo esperado en storage/app/legacy/:
 *   - fabric_rolls_exactos.csv
 *
 * Fallback en app/legacy/ si no existe en storage/
 *
 * Ejecución:
 *   php artisan db:seed --class=LegacyFabricRollsSeeder
 */
class LegacyFabricRollsSeeder extends Seeder
{
    private const WAREHOUSE_008_NAME = '%008%';

    public function run(): void
    {
        // ── Resolver ruta del CSV (mismo patrón que LegacyContactsSeeder) ────
        $csvPath = storage_path('app/legacy/fabric_rolls_exactos.csv');
        $fallback = base_path('app/legacy/fabric_rolls_exactos.csv');

        if (! file_exists($csvPath) && file_exists($fallback)) {
            $csvPath = $fallback;
        }

        if (! file_exists($csvPath)) {
            $this->command->warn(
                'LegacyFabricRollsSeeder: No se encontró el CSV en storage/app/legacy/ ni en app/legacy/. Se omite.'
            );
            Log::warning('legacy_fabric_rolls.no_file', ['expected' => $csvPath]);
            return;
        }

        $this->command->info("Leyendo CSV: {$csvPath}");

        $rows = $this->readCsv($csvPath);

        if (empty($rows)) {
            $this->command->error("El CSV está vacío o mal formado.");
            return;
        }

        $this->command->info("Filas leídas: " . count($rows));

        // ── Resolver id de Bodega 008 ─────────────────────────────────────────
        $warehouseId = DB::table('warehouses')
            ->where('name', 'like', self::WAREHOUSE_008_NAME)
            ->value('id');

        if (! $warehouseId) {
            $this->command->error("No se encontró la bodega 008 en la tabla warehouses.");
            return;
        }

        $this->command->info("Bodega 008 id: {$warehouseId}");

        // ── Pre-cargar mapa referencia → item.id ──────────────────────────────
        $refs = collect($rows)
            ->pluck('item_referencia')
            ->filter()
            ->unique()
            ->values();

        $itemMap = Item::whereIn('reference', $refs->toArray())
            ->pluck('id', 'reference')
            ->toArray();

        $this->command->info("Ítems encontrados por referencia: " . count($itemMap));

        $refsNoEncontradas = $refs->diff(array_keys($itemMap));
        if ($refsNoEncontradas->isNotEmpty()) {
            $this->command->warn(
                "Referencias no encontradas en BD (" . $refsNoEncontradas->count() . "): "
                . $refsNoEncontradas->implode(', ')
            );
            Log::warning('legacy_fabric_rolls.refs_not_found', [
                'count' => $refsNoEncontradas->count(),
                'refs'  => $refsNoEncontradas->toArray(),
            ]);
        }

        // ── Filtrar rollos ya existentes (idempotencia) ───────────────────────
        $existentes = DB::table('fabric_rolls')
        ->pluck('roll_number')
        ->flip()
        ->toArray();

        $toInsert = [];
        $skipped  = 0;

        foreach ($rows as $row) {
            $rollNumber = trim($row['roll_number'] ?? '');

            if ($rollNumber === '') {
                continue;
            }

            if (isset($existentes[$rollNumber])) {
                $skipped++;
                continue;
            }

            $ref    = trim($row['item_referencia'] ?? '');
            $itemId = $itemMap[$ref] ?? null;

            $toInsert[] = [
                'roll_number'               => $rollNumber,
                'purchase_document_item_id' => null,
                'item_id'                   => $itemId,
                'location'                  => trim($row['location'] ?? ''),
                'description'               => trim($row['tela'] ?? ''),
                'internal_color_name'       => trim($row['internal_color_name'] ?? ''),
                'internal_color_code'       => trim($row['internal_color_code'] ?? ''),
                'roll_width'                => $this->toFloat($row['roll_width']),
                'quantity'                  => $this->toFloat($row['quantity']),
                'partial_output'            => $this->toFloat($row['partial_output']),
                'actual_output'             => $this->toFloat($row['actual_output']),
                'movement_type'             => 'entrada',
                'approval_status'           => 'approved',
                'entry_date'                => $this->toDate($row['entry_date']),
                'created_at'                => now(),
                'updated_at'                => now(),
            ];
        }

        $this->command->info("Rollos a insertar: " . count($toInsert));
        $this->command->warn("Rollos omitidos (ya existían): {$skipped}");

        if (empty($toInsert)) {
            $this->command->warn("No hay rollos nuevos que insertar.");
        } else {
            $insertedCount = DB::transaction(function () use ($toInsert) {
                $inserted = 0;

                foreach (array_chunk($toInsert, 100) as $chunk) {
                    $inserted += DB::table('fabric_rolls')->insertOrIgnore($chunk);
                }

                return $inserted;
            });

            $this->command->info("✓ Rollos insertados.");
        }

        // ── Recalcular quantity en warehouse_items ────────────────────────────
        $this->recalcularMetrosItems($itemMap, $warehouseId);

        $this->command->info("✓ Seeder finalizado.");
    }

    /**
     * Recalcula warehouse_items.quantity = SUM(fabric_rolls.actual_output)
     * para cada ítem de tela en la bodega 008.
     *
     * Si el registro en warehouse_items no existe lo crea (upsert).
     */
    private function recalcularMetrosItems(array $itemMap, int $warehouseId): void
    {
        $this->command->info("Recalculando metros en warehouse_items (bodega {$warehouseId})...");

        $totales = DB::table('fabric_rolls')
            ->whereNull('deleted_at')
            ->whereIn('item_id', array_values($itemMap))
            ->groupBy('item_id')
            ->select(
                'item_id',
                DB::raw('SUM(actual_output) as total_metros')
            )
            ->get()
            ->keyBy('item_id');

        $actualizados = 0;

        foreach ($itemMap as $ref => $itemId) {
            $totalMetros = round((float) ($totales->get($itemId)?->total_metros ?? 0), 2);

            DB::table('warehouse_items')->upsert(
                [
                    'item_id'      => $itemId,
                    'warehouse_id' => $warehouseId,
                    'quantity'     => $totalMetros,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ],
                ['item_id', 'warehouse_id'],  // columnas únicas para detectar duplicado
                ['quantity', 'updated_at']    // columnas a actualizar si ya existe
            );

            $actualizados++;
        }

        $this->command->info("warehouse_items actualizados: {$actualizados}");
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        $headers = fgetcsv($handle);
        $headers = array_map('trim', $headers);

        $rows = [];

        while (($line = fgetcsv($handle)) !== false) {
            if (count($line) !== count($headers)) {
                continue;
            }
            $rows[] = array_combine($headers, $line);
        }

        fclose($handle);

        return $rows;
    }

    private function toFloat(mixed $value): float
    {
        if ($value === null || trim((string) $value) === '') return 0.0;
        return round((float) str_replace(',', '.', trim((string) $value)), 2);
    }

    private function toDate(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') return null;

        $value = trim((string) $value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        $dt = date_create($value);
        return $dt ? date_format($dt, 'Y-m-d') : null;
    }
}
