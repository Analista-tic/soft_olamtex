<?php

namespace App\Console\Commands;

use App\Models\Production\FabricRollProgrammingLine;
use App\Models\Production\ManualRollProgramming;
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

        // Fase 15: fuente de verdad es fabric_roll_programming_lines, ya no
        // TechnicalSheetRoll::lines (JSON, congelado como respaldo de solo lectura).
        $query = FabricRollProgrammingLine::query()
            ->whereNotNull('fabric_roll_id')
            ->where('programmed_meters', '>', 0)
            ->select(['fabric_roll_id', 'programmed_meters']);

        if ($targetRollId !== null) {
            $query->where('fabric_roll_id', (int) $targetRollId);
        }

        foreach ($query->cursor() as $line) {
            $rollId = (string) $line->fabric_roll_id;
            $totalesPorRoll[$rollId] = ($totalesPorRoll[$rollId] ?? 0.0) + (float) $line->programmed_meters;
        }

        // Reservas manuales pendientes (manual_roll_programmings) — programado menos ya
        // consumido, para no perder la reserva de la fecha de un descargue parcial.
        $manualQuery = ManualRollProgramming::query()->whereNotNull('fabric_roll_id');
        if ($targetRollId !== null) {
            $manualQuery->where('fabric_roll_id', (int) $targetRollId);
        }

        foreach ($manualQuery->cursor() as $manualLine) {
            $pending = (float) $manualLine->programmed_meters - (float) $manualLine->consumed_meters;
            if ($pending <= 0) {
                continue;
            }
            $rollId = (string) $manualLine->fabric_roll_id;
            $totalesPorRoll[$rollId] = ($totalesPorRoll[$rollId] ?? 0.0) + $pending;
        }

        return $totalesPorRoll;
    }
}
