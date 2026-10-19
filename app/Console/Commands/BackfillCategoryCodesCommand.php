<?php

namespace App\Console\Commands;

use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Support\TextNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfill de grupos y subgrupos de categoría existentes.
 *
 * 1. Asigna `code` (2 dígitos, consecutivo, por orden de id) a los grupos y
 *    subgrupos que lo tienen en null.
 * 2. Convierte a MAYÚSCULAS el nombre de los subgrupos existentes. Si el nombre
 *    resultante colisiona con otro subgrupo del mismo grupo, se omite con warning.
 *
 * Es idempotente: solo toca filas con code null o nombre aún no en mayúsculas.
 *
 * Uso:
 *   php artisan categories:backfill-codes --dry-run
 *   php artisan categories:backfill-codes
 */
class BackfillCategoryCodesCommand extends Command
{
    protected $signature = 'categories:backfill-codes
                            {--dry-run : Solo muestra el reporte sin persistir}';

    protected $description = 'Asigna code a grupos/subgrupos sin código y pasa a MAYÚSCULAS los nombres de subgrupos';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info('Backfill de categorías'.($dryRun ? ' [dry-run]' : '').'...');

        DB::transaction(function () use ($dryRun) {
            $this->backfillCodes(CategoryGroup::class, 'grupos', $dryRun);
            $this->backfillCodes(CategorySubGroup::class, 'subgrupos', $dryRun);
            $this->uppercaseSubgroupNames($dryRun);
        });

        return self::SUCCESS;
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    private function backfillCodes(string $model, string $label, bool $dryRun): void
    {
        $next = (int) $model::query()->max('code');
        $assigned = 0;

        foreach ($model::query()->whereNull('code')->orderBy('id')->get() as $row) {
            $next++;

            if ($next > 99) {
                $this->warn("Se alcanzó el máximo de códigos de {$label} (99); quedan filas sin código.");
                break;
            }

            if (! $dryRun) {
                $row->code = str_pad((string) $next, 2, '0', STR_PAD_LEFT);
                $row->saveQuietly();
            }

            $assigned++;
        }

        $this->info("Códigos asignados a {$label}: {$assigned}");
    }

    private function uppercaseSubgroupNames(bool $dryRun): void
    {
        $updated = 0;

        foreach (CategorySubGroup::query()->orderBy('id')->get() as $subgroup) {
            $upper = TextNormalizer::upperCaseWords($subgroup->name);

            if ($upper === '' || $upper === $subgroup->name) {
                continue;
            }

            $upperKey = TextNormalizer::semanticKey($upper);
            $collides = CategorySubGroup::query()
                ->where('category_group_id', $subgroup->category_group_id)
                ->where('id', '<>', $subgroup->id)
                ->get(['name'])
                ->contains(fn ($other) => TextNormalizer::semanticKey($other->name) === $upperKey);

            if ($collides) {
                $this->warn("Subgrupo #{$subgroup->id} '{$subgroup->name}' omitido: colisiona con otro del mismo grupo.");

                continue;
            }

            if (! $dryRun) {
                $subgroup->name = $upper;
                $subgroup->saveQuietly();
            }

            $updated++;
        }

        $this->info("Nombres de subgrupos pasados a mayúsculas: {$updated}");
    }
}
