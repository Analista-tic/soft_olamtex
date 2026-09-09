<?php

namespace App\Console\Commands;

use App\Models\MasterData\FabricName;
use App\Models\MasterData\Item;
use App\Models\MasterData\YarnName;
use App\Support\MasterData\MaterialClassifier;
use App\Support\TextNormalizer;
use Illuminate\Console\Command;

/**
 * Migra el texto libre en extra_fields (ej. "Base tela") a fabric_name_id/
 * yarn_name_id, contra el catálogo de nombres ya poblado en la Fase 3.5.
 *
 * Requiere que el catálogo de nombres YA exista y esté poblado — este
 * comando nunca crea nombres nuevos ni adivina; una fila sin match exacto
 * (tras normalizar: sin tildes, minúsculas, espacios colapsados) se reporta
 * para revisión manual y nunca se asigna sola.
 *
 * Uso:
 *   php artisan master-data:migrate-item-material-bases --dry-run
 *   php artisan master-data:migrate-item-material-bases
 */
class MigrateItemMaterialBasesCommand extends Command
{
    protected $signature = 'master-data:migrate-item-material-bases
                            {--dry-run : Muestra qué se asignaría sin escribir nada}';

    protected $description = 'Asigna fabric_name_id/yarn_name_id a ítems según el texto libre en extra_fields';

    private const FABRIC_EXTRA_FIELD_KEY = 'Base tela';

    private const YARN_EXTRA_FIELD_KEY = 'Base hilaza';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->migrateFor('fabric', self::FABRIC_EXTRA_FIELD_KEY, 'fabric_name_id', FabricName::class, 'tela', $dryRun);
        $this->newLine();
        $this->migrateFor('yarn', self::YARN_EXTRA_FIELD_KEY, 'yarn_name_id', YarnName::class, 'hilaza', $dryRun);

        if ($dryRun) {
            $this->comment('Dry-run: no se escribió nada. Ejecuta sin --dry-run para aplicar.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  class-string<FabricName|YarnName>  $modelClass
     */
    private function migrateFor(string $kind, string $extraFieldKey, string $fkColumn, string $modelClass, string $label, bool $dryRun): void
    {
        $catalog = $modelClass::query()
            ->get(['id', 'nombre'])
            ->keyBy(fn ($model) => TextNormalizer::semanticKey($model->nombre));

        if ($catalog->isEmpty()) {
            $this->warn("[{$label}] El catálogo de nombres de {$label} está vacío — nada que mapear. Da de alta el catálogo primero (Fase 3.5).");

            return;
        }

        $items = Item::query()
            ->whereNotNull('extra_fields')
            ->whereNull($fkColumn)
            ->get(['id', 'name', 'category_sub_group_id', 'extra_fields', $fkColumn]);

        $matched = 0;
        $unmatchedRows = [];

        foreach ($items as $item) {
            $isMatch = $kind === 'fabric' ? MaterialClassifier::isFabricItem($item) : MaterialClassifier::isYarnItem($item);
            if (! $isMatch) {
                continue;
            }

            $extraFields = is_array($item->extra_fields) ? $item->extra_fields : [];
            $raw = trim((string) ($extraFields[$extraFieldKey] ?? ''));
            if ($raw === '') {
                continue;
            }

            $match = $catalog->get(TextNormalizer::semanticKey($raw));

            if (! $match) {
                $unmatchedRows[] = [$item->id, $item->name, $raw];

                continue;
            }

            $matched++;

            if (! $dryRun) {
                // El valor queda en extra_fields['_legacy'][clave] en vez de borrarse
                // (se conserva el histórico) ni quedar duplicado junto a la FK nueva.
                // El nombre del ítem (buildName()) y el formulario ignoran la clave
                // reservada '_legacy' por una única regla, sin listas de nombres.
                $updatedExtraFields = $extraFields;
                $updatedExtraFields['_legacy'] = is_array($updatedExtraFields['_legacy'] ?? null)
                    ? $updatedExtraFields['_legacy']
                    : [];
                $updatedExtraFields['_legacy'][$extraFieldKey] = $raw;
                unset($updatedExtraFields[$extraFieldKey]);

                $item->forceFill([
                    $fkColumn => $match->id,
                    'extra_fields' => $updatedExtraFields,
                ])->save();
            }
        }

        $suffix = $dryRun ? ' (dry-run, no se escribió nada)' : ' (aplicado)';
        $this->info("[{$label}] Coincidencias: {$matched}{$suffix}");

        if (! empty($unmatchedRows)) {
            $this->warn('['.$label.'] Sin match ('.count($unmatchedRows).'), requieren revisión manual — probablemente falta dar de alta ese nombre en el catálogo (Fase 3.5):');
            $this->table(['item_id', 'nombre', 'texto_original'], $unmatchedRows);
        }
    }
}
