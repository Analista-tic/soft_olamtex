<?php

namespace App\Console\Commands;

use App\Models\Production\TechnicalSheetRoll;
use App\Models\Textil\FabricRoll;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalibrarPartialOutput extends Command
{
    protected $signature = 'rolls:recalibrar-partial {--roll_id= : ID especifico de un rollo}';

    protected $description = 'Recalcula partial_output de rollos sumando todos los snapshots de fichas tecnicas';

    public function handle(): int
    {
        $rollId = $this->option('roll_id');
        if ($rollId !== null && $rollId !== '' && !is_numeric($rollId)) {
            $this->error('La opcion --roll_id debe ser numerica.');
            return self::INVALID;
        }

        $targetRollId = $rollId !== null && $rollId !== '' ? (string) (int) $rollId : null;
        $totalesPorRoll = $this->buildTotalsByRollId($targetRollId);
        $updated = 0;

        DB::transaction(function () use ($targetRollId, $totalesPorRoll, &$updated): void {
            $query = FabricRoll::query()
                ->select(['id', 'roll_number', 'partial_output']);

            if ($targetRollId !== null) {
                $query->whereKey((int) $targetRollId);
            }

            foreach ($query->lockForUpdate()->cursor() as $roll) {
                $correcto = round($totalesPorRoll[(string) $roll->id] ?? 0.0, 2);
                $actual = round((float) $roll->partial_output, 2);

                if (abs($correcto - $actual) <= 0.001) {
                    continue;
                }

                $this->line("Corrigiendo {$roll->roll_number} (#{$roll->id}): {$actual} -> {$correcto}");
                $roll->update(['partial_output' => $correcto]);
                $updated++;
            }
        });

        if ($targetRollId !== null && !FabricRoll::query()->whereKey((int) $targetRollId)->exists()) {
            $this->warn("No existe el rollo con ID {$targetRollId}.");
            return self::FAILURE;
        }

        $scope = $targetRollId !== null ? "rollo {$targetRollId}" : 'todos los rollos';
        $this->info("Recalibracion completada para {$scope}. Ajustes aplicados: {$updated}.");

        return self::SUCCESS;
    }

    /**
     * @return array<string, float>
     */
    private function buildTotalsByRollId(?string $targetRollId = null): array
    {
        $totalesPorRoll = [];

        foreach (TechnicalSheetRoll::query()->select('lines')->cursor() as $snapshot) {
            $lines = is_array($snapshot->lines) ? $snapshot->lines : [];

            foreach ($lines as $line) {
                $rollId = trim((string) ($line['rollId'] ?? ''));
                if ($rollId === '' || !is_numeric($rollId)) {
                    continue;
                }

                if ($targetRollId !== null && $rollId !== $targetRollId) {
                    continue;
                }

                $meters = is_numeric($line['programmedMeters'] ?? null)
                    ? (float) $line['programmedMeters']
                    : 0.0;

                if ($meters <= 0.0) {
                    continue;
                }

                $totalesPorRoll[$rollId] = ($totalesPorRoll[$rollId] ?? 0.0) + $meters;
            }
        }

        return $totalesPorRoll;
    }
}
