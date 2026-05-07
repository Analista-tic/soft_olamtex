<?php

namespace Database\Seeders;

use App\Services\MasterData\LegacyItemsMigrationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * Seeder para importar ítems históricos desde archivos CSV del sistema legado.
 *
 * NO modifica el esquema de BD — toda la trazabilidad se almacena en extra_fields (JSON).
 *
 * Archivos esperados en storage/app/legacy/:
 *   - bodega_8_items.csv
 *
 * Ejecución:
 *   php artisan migrate:fresh --seed               (completo)
 *   php artisan db:seed --class=LegacyItemsSeeder  (solo legacy)
 */
class LegacyItemsSeeder extends Seeder
{
    public function run(): void
    {
        $csvFiles = [
            storage_path('app/legacy/bodega_8_items.csv'),
        ];

        // Fallback en db/
        $fallback = [
            base_path('db/bodega_8_items.csv'),
        ];

        foreach ($fallback as $i => $path) {
            if (! file_exists($csvFiles[$i]) && file_exists($path)) {
                $csvFiles[$i] = $path;
            }
        }

        // Filtrar solo archivos que existan
        $existing = array_filter($csvFiles, fn(string $path) => file_exists($path));

        if (empty($existing)) {
            $this->command?->warn(
                'LegacyItemsSeeder: No se encontró bodega_8_items.csv ' .
                'en storage/app/legacy/ ni en db/. Se omite importación legacy.'
            );
            Log::warning('legacy_import.no_files', ['expected' => $csvFiles]);
            return;
        }

        $this->command?->info('Importando ítems legacy desde ' . count($existing) . ' archivo(s)...');

        $batchId = 'legacy_' . now()->format('Ymd_His');
        $service = app(LegacyItemsMigrationService::class);
        $summary = $service->importAll($existing, $batchId);

        $this->command?->info("Legacy import completo — Batch: {$batchId}");
        $this->command?->info(
            "  Leídos: {$summary['total_read']}  |  " .
            "Items insertados: {$summary['items_inserted']}  |  " .
            "Items actualizados: {$summary['items_updated']}  |  " .
            "WarehouseItems creados: {$summary['warehouse_items_created']}  |  " .
            "WarehouseItems actualizados: {$summary['warehouse_items_updated']}  |  " .
            "Sin bodega: {$summary['missing_warehouse']}  |  " .
            "Cantidades invÃ¡lidas: {$summary['invalid_quantities']}  |  " .
            "Omitidos: {$summary['skipped']}"
        );

        if (! empty($summary['errors'])) {
            $errorCount = count($summary['errors']);
            $this->command?->warn("  Errores: {$errorCount}");
            foreach (array_slice($summary['errors'], 0, 10) as $err) {
                $this->command?->line("    -> {$err}");
            }
            if ($errorCount > 10) {
                $this->command?->line("    ... y " . ($errorCount - 10) . " errores más (ver logs).");
            }
        }
    }
}
