<?php

namespace App\Console\Commands;

use App\Models\MasterData\Item;
use App\Support\MasterData\MaterialClassifier;
use App\Support\TextNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reporta, de solo lectura, los valores distintos de texto libre usados hoy
 * en extra_fields de ítems clasificados como tela/hilaza (ej. "Base tela").
 * No escribe nada — es el insumo para dar de alta manualmente el catálogo
 * de nombres (Fase 3.5) antes de correr el backfill de la Fase 4.
 *
 * Para cada nombre distinto, cruza contra fabric_rolls.description (que en
 * datos reales sigue el patrón "{BASE} - {NOMBRE}") y sugiere la base más
 * frecuente asociada — solo como ayuda visual, nunca se asigna sola.
 */
class ReportDistinctMaterialNamesCommand extends Command
{
    protected $signature = 'master-data:report-distinct-material-names
                            {--dry-run : El comando siempre es de solo lectura; esta opción no tiene efecto}';

    protected $description = 'Reporta los nombres distintos de tela/hilaza usados en extra_fields de ítems, con sugerencia de base';

    private const FABRIC_EXTRA_FIELD_KEY = 'Base tela';

    private const YARN_EXTRA_FIELD_KEY = 'Base hilaza';

    public function handle(): int
    {
        $suggestions = $this->suggestBasesFromRolls();

        $this->reportFor('fabric', self::FABRIC_EXTRA_FIELD_KEY, 'Tela', $suggestions);
        $this->newLine();
        $this->reportFor('yarn', self::YARN_EXTRA_FIELD_KEY, 'Hilaza', $suggestions);

        return self::SUCCESS;
    }

    private function reportFor(string $kind, string $extraFieldKey, string $label, array $suggestions): void
    {
        $items = Item::whereNotNull('extra_fields')->get(['id', 'category_sub_group_id', 'extra_fields']);

        $counts = [];
        foreach ($items as $item) {
            $isMatch = $kind === 'fabric' ? MaterialClassifier::isFabricItem($item) : MaterialClassifier::isYarnItem($item);
            if (! $isMatch) {
                continue;
            }

            $extraFields = is_array($item->extra_fields) ? $item->extra_fields : [];
            $rawValue = $extraFields[$extraFieldKey] ?? null;
            if (! is_string($rawValue) || trim($rawValue) === '') {
                continue;
            }

            $normalized = TextNormalizer::semanticKey($rawValue);
            if ($normalized === '') {
                continue;
            }

            $counts[$normalized]['count'] = ($counts[$normalized]['count'] ?? 0) + 1;
            $counts[$normalized]['original'] = trim($rawValue);
        }

        if (empty($counts)) {
            $this->info("No hay valores de \"{$extraFieldKey}\" en ítems clasificados como {$label}.");

            return;
        }

        $this->info('Nombres distintos de '.$label.' encontrados ('.count($counts).'):');

        $rows = [];
        foreach ($counts as $normalized => $data) {
            $rows[] = [$data['original'], $data['count'], $suggestions[$normalized] ?? '(sin sugerencia)'];
        }
        usort($rows, fn ($a, $b) => $b[1] <=> $a[1]);

        $this->table(['Nombre', 'Usos', 'Base sugerida (según fabric_rolls.description)'], $rows);
    }

    /**
     * @return array<string, string> normalizedName => nombre de base más frecuente
     */
    private function suggestBasesFromRolls(): array
    {
        $descriptions = DB::table('fabric_rolls')->whereNotNull('description')->pluck('description');

        $tally = [];
        foreach ($descriptions as $description) {
            if (! is_string($description) || ! str_contains($description, ' - ')) {
                continue;
            }

            [$base, $name] = array_map('trim', explode(' - ', $description, 2));
            if ($base === '' || $name === '') {
                continue;
            }

            $normalizedName = TextNormalizer::semanticKey($name);
            $tally[$normalizedName][$base] = ($tally[$normalizedName][$base] ?? 0) + 1;
        }

        $suggestions = [];
        foreach ($tally as $normalizedName => $bases) {
            arsort($bases);
            $suggestions[$normalizedName] = (string) array_key_first($bases);
        }

        return $suggestions;
    }
}
