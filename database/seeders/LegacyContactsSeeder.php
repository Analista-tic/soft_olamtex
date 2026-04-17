<?php

namespace Database\Seeders;

use App\Services\MasterData\LegacyContactsMigrationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * Seeder para importar terceros/contactos históricos desde CSV del sistema legado.
 *
 * NO modifica el esquema de BD — trazabilidad almacenada en extra_fields (JSON).
 *
 * Archivo esperado en storage/app/legacy/:
 *   - Base de datos normalizada - Contactos.csv
 *
 * Ejecución:
 *   php artisan migrate:fresh --seed                              (completo)
 *   php artisan db:seed --class=LegacyContactsSeeder             (solo contactos)
 */
class LegacyContactsSeeder extends Seeder
{
    public function run(): void
    {
        $csvFiles = [
            storage_path('app/legacy/Base de datos normalizada - Contactos.csv'),
        ];

        // Fallback en db/ si no existe en storage/
        $fallback = [
            base_path('db/Base de datos normalizada - Contactos.csv'),
        ];

        foreach ($fallback as $i => $path) {
            if (! file_exists($csvFiles[$i]) && file_exists($path)) {
                $csvFiles[$i] = $path;
            }
        }

        $existing = array_filter($csvFiles, fn (string $path) => file_exists($path));

        if (empty($existing)) {
            $this->command?->warn(
                'LegacyContactsSeeder: No se encontró el CSV en storage/app/legacy/ ni en db/. Se omite importación.'
            );
            Log::warning('legacy_contacts_import.no_files', ['expected' => $csvFiles]);
            return;
        }

        $this->command?->info('Importando contactos legacy desde ' . count($existing) . ' archivo(s)...');

        $batchId = 'legacy_contacts_' . now()->format('Ymd_His');
        $service = app(LegacyContactsMigrationService::class);
        $summary = $service->importAll(array_values($existing), $batchId);

        $this->command?->info("Legacy contacts import completo — Batch: {$batchId}");
        $this->command?->info(
            "  Leídos: {$summary['total_read']}  |  Insertados: {$summary['inserted']}  |  " .
            "Actualizados: {$summary['updated']}  |  Omitidos: {$summary['skipped']}"
        );

        if (! empty($summary['errors'])) {
            $errorCount = count($summary['errors']);
            $this->command?->warn("  Errores: {$errorCount}");
            foreach (array_slice($summary['errors'], 0, 10) as $err) {
                $this->command?->line("    -> {$err}");
            }
            if ($errorCount > 10) {
                $this->command?->line('    ... y ' . ($errorCount - 10) . ' errores más (ver logs).');
            }
        }
    }
}
