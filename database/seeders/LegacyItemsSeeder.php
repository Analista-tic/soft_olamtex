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
 *   - Base de datos normalizada 1 - Items.csv
 *   - Base de datos normalizada 2 - Items.csv
 *
 * Ejecución:
 *   php artisan migrate:fresh --seed          (completo)
 *   php artisan db:seed --class=LegacyItemsSeeder  (solo legacy)
 */
class LegacyItemsSeeder extends Seeder
{
    public function run(): void
    {
        // Busca primero en storage/app/legacy/, luego en db/ como fallback
        $csvFiles = [
            storage_path('app\legacy\Base de datos normalizada 1 - Items.csv'),
            storage_path('app\legacy\Base de datos normalizada 2 - Items.csv'),
        ];

        // Si no existen en storage/, intentar desde db/
        $fallback = [
            base_path('db/Base de datos normalizada 1 - Items.csv'),
            base_path('db/Base de datos normalizada 2 - Items.csv'),
        ];
        foreach ($fallback as $i => $path) {
            if (! file_exists($csvFiles[$i]) && file_exists($path)) {
                $csvFiles[$i] = $path;
            }
        }

        // Filtrar solo archivos que existan (permite ejecutar seed sin los CSV)
        $existing = array_filter($csvFiles, fn(string $path) => file_exists($path));

        if (empty($existing)) {
            $this->command?->warn('LegacyItemsSeeder: No se encontraron archivos CSV en storage/app/legacy/ ni en db/. Se omite importación legacy.');
            Log::warning('legacy_import.no_files', ['expected' => $csvFiles]);
            return;
        }

        $this->command?->info('Importando ítems legacy desde ' . count($existing) . ' archivo(s)...');

        $batchId = 'legacy_' . now()->format('Ymd_His');
        $service = app(LegacyItemsMigrationService::class);
        $summary = $service->importAll($existing, $batchId);

        $this->command?->info("Legacy import completo — Batch: {$batchId}");
        $this->command?->info("  Leídos: {$summary['total_read']}  |  Insertados: {$summary['inserted']}  |  Omitidos: {$summary['skipped']}");

        if (!empty($summary['errors'])) {
            $errorCount = count($summary['errors']);
            $this->command?->warn("  Errores: {$errorCount}");
            // Mostrar los primeros 10 errores en consola
            foreach (array_slice($summary['errors'], 0, 10) as $err) {
                $this->command?->line("    -> {$err}");
            }
            if ($errorCount > 10) {
                $this->command?->line("    ... y " . ($errorCount - 10) . " errores más (ver logs).");
            }
        }
    }
}
