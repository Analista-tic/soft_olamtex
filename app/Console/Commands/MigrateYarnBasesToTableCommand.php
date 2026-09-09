<?php

namespace App\Console\Commands;

use App\Models\Production\YarnBase;
use App\Models\Settings\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Migra el catálogo "yarn_bases" desde settings (kind=parameter, JSON) a la
 * tabla dedicada yarn_bases, preservando los mismos ids que ya usa
 * product_sheets.yarn_base_id (mismo patrón ya usado para fabric_bases en
 * la migración 2026_06_03_000002_seed_fabric_bases_from_settings).
 *
 * Uso:
 *   php artisan master-data:migrate-yarn-bases-to-table --dry-run
 *   php artisan master-data:migrate-yarn-bases-to-table
 */
class MigrateYarnBasesToTableCommand extends Command
{
    protected $signature = 'master-data:migrate-yarn-bases-to-table
                            {--dry-run : Muestra qué se migraría sin escribir nada}';

    protected $description = 'Migra el catálogo de bases de hilaza desde settings a la tabla yarn_bases';

    private const SETTING_KEY_CANDIDATES = ['yarn_bases', 'bases_hilaza', 'bases_hilo'];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $setting = Setting::where('kind', 'parameter')
            ->whereIn('key', self::SETTING_KEY_CANDIDATES)
            ->first();

        if (!$setting || !is_array($setting->value) || empty($setting->value)) {
            $this->warn('No se encontró el parámetro yarn_bases en settings (o está vacío). Nada que migrar.');

            return self::SUCCESS;
        }

        $rows = collect($setting->value)->filter(
            fn ($item) => is_array($item) && isset($item['id'], $item['name'])
        );

        $this->info("Entradas encontradas en settings ('{$setting->key}'): {$rows->count()}");

        $existingIds = YarnBase::withTrashed()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $toCreate = $rows->reject(fn ($row) => in_array((int) $row['id'], $existingIds, true));
        $alreadyExist = $rows->count() - $toCreate->count();

        $this->line("Ya existen en yarn_bases: {$alreadyExist}");
        $this->line("Se crearán: {$toCreate->count()}");

        foreach ($toCreate as $row) {
            $id = (int) $row['id'];
            $name = strtoupper(trim((string) $row['name']));
            $codigo = 'BH-' . str_pad((string) $id, 3, '0', STR_PAD_LEFT);

            $this->line("  [{$id}] {$codigo} — {$name}");

            if (!$dryRun) {
                DB::table('yarn_bases')->insertOrIgnore([
                    'id' => $id,
                    'codigo' => $codigo,
                    'nombre' => $name,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->reportOrphanReferences($rows);

        if ($dryRun) {
            $this->comment('Dry-run: no se escribió nada. Ejecuta sin --dry-run para aplicar.');
        }

        return self::SUCCESS;
    }

    private function reportOrphanReferences(\Illuminate\Support\Collection $settingRows): void
    {
        $settingIds = $settingRows->pluck('id')->map(fn ($id) => (int) $id)->all();

        $orphanIds = DB::table('product_sheets')
            ->whereNotNull('yarn_base_id')
            ->distinct()
            ->pluck('yarn_base_id')
            ->reject(fn ($id) => in_array((int) $id, $settingIds, true));

        if ($orphanIds->isEmpty()) {
            $this->info('Todos los yarn_base_id usados en product_sheets están cubiertos por el catálogo migrado.');

            return;
        }

        $this->warn('yarn_base_id usados en product_sheets que NO aparecen en el catálogo de settings (revisar manualmente, quedarán en NULL al agregar la FK):');

        foreach ($orphanIds as $id) {
            $count = DB::table('product_sheets')->where('yarn_base_id', $id)->count();
            $this->line("  id={$id} ({$count} sábana(s))");
        }
    }
}
