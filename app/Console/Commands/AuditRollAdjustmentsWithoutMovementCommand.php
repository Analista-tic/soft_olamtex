<?php

namespace App\Console\Commands;

use App\Models\Textil\FabricRollAdjustment;
use Illuminate\Console\Command;

/**
 * Auditoría de solo lectura (Fase 6): reporta los FabricRollAdjustment sin
 * inventory_movement_id, para que el usuario decida el alcance real antes
 * de centralizar la creación de ajuste+movimiento o backfillear históricos.
 * No escribe nada — no inventa movimientos retroactivos.
 */
class AuditRollAdjustmentsWithoutMovementCommand extends Command
{
    protected $signature = 'textil:audit-roll-adjustments-without-movement
                            {--detail : Lista cada ajuste sin movimiento (roll, tipo, motivo, fecha)}';

    protected $description = 'Reporta los ajustes de rollo (fabric_roll_adjustments) sin inventory_movement_id asociado';

    public function handle(): int
    {
        $total = FabricRollAdjustment::count();
        $withoutMovement = FabricRollAdjustment::whereNull('inventory_movement_id')->get([
            'id', 'fabric_roll_id', 'type', 'reason', 'reference', 'created_at',
        ]);

        $this->info("Total de ajustes de rollo: {$total}");
        $this->info('Ajustes sin inventory_movement_id: '.$withoutMovement->count());

        if ($withoutMovement->isEmpty()) {
            $this->info('No hay ajustes sin movimiento. Nada que auditar.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Desglose por tipo:');
        $byType = $withoutMovement->groupBy('type')->map->count()->sortDesc();
        $this->table(['Tipo', 'Cantidad'], $byType->map(fn ($count, $type) => [$type, $count])->values()->toArray());

        $oldest = $withoutMovement->min('created_at');
        $newest = $withoutMovement->max('created_at');
        $this->newLine();
        $this->info("Rango de fechas: {$oldest} a {$newest}");

        if ($this->option('detail')) {
            $this->newLine();
            $rows = $withoutMovement
                ->sortBy('created_at')
                ->map(fn (FabricRollAdjustment $adjustment) => [
                    $adjustment->id,
                    $adjustment->fabric_roll_id,
                    $adjustment->type,
                    $adjustment->reference ?? '-',
                    $adjustment->reason ?? '-',
                    $adjustment->created_at?->toDateTimeString(),
                ])
                ->values()
                ->toArray();

            $this->table(['ID', 'Rollo ID', 'Tipo', 'Referencia', 'Motivo', 'Fecha'], $rows);
        }

        return self::SUCCESS;
    }
}
