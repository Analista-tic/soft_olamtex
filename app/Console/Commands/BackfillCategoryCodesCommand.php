<?php

namespace App\Console\Commands;

use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Backfill de `code` para grupos y subgrupos de categoría creados antes de que
 * la asignación automática existiera (migración
 * 2026_09_02_000001_add_code_to_category_groups_and_sub_groups_table quedó
 * nullable, sin backfill). Sin code, ItemReferenceSequenceService no puede
 * generar la referencia automática de ítems que usen esos grupos/subgrupos.
 *
 * Mismo patrón que app/Console/Commands/BackfillColorCodesCommand.php: lock +
 * transacción por tabla, continuando desde el máximo existente.
 *
 * Uso:
 *   php artisan categories:backfill-codes
 */
class BackfillCategoryCodesCommand extends Command
{
    protected $signature = 'categories:backfill-codes';

    protected $description = 'Asigna código consecutivo a los grupos y subgrupos de categoría existentes que no tienen (code IS NULL)';

    public function handle(): int
    {
        $this->backfill(CategoryGroup::class, 'grupos');
        $this->backfill(CategorySubGroup::class, 'subgrupos');

        return self::SUCCESS;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function backfill(string $modelClass, string $label): void
    {
        $updated = [];

        DB::transaction(function () use ($modelClass, &$updated) {
            $max = $modelClass::query()->lockForUpdate()->max('code');
            $next = ((int) $max) + 1;

            $pending = $modelClass::whereNull('code')
                ->orderBy('created_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($pending as $record) {
                if ($next > 99) {
                    $this->error("Se alcanzó el máximo de códigos (99). Deteniendo en id={$record->id} ('{$record->name}').");
                    break;
                }

                $code = str_pad((string) $next, 2, '0', STR_PAD_LEFT);
                $record->update(['code' => $code]);
                $updated[] = ['id' => $record->id, 'name' => $record->name, 'code' => $code];
                $next++;
            }
        });

        if ($updated === []) {
            $this->info("No hay {$label} sin código. Nada que hacer.");

            return;
        }

        $first = $updated[0];
        $last = $updated[count($updated) - 1];

        $this->info(
            count($updated)." {$label} actualizados: id={$first['id']} (code={$first['code']}) "
            ."hasta id={$last['id']} (code={$last['code']})."
        );
    }
}
