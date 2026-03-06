<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BatchControlModuleSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('production_orders') || !Schema::hasTable('batch_control_deliveries')) {
            return;
        }

        if (!Schema::hasColumns('production_orders', ['batch_priority', 'current_process', 'process_estimated_dates'])) {
            return;
        }

        $processes = config('product-sheet.batch_control_processes', []);
        if (empty($processes)) {
            return;
        }

        $seedUserId = DB::table('users')->value('id');
        $today = Carbon::today();

        $orders = DB::table('production_orders')
            ->orderBy('id')
            ->get();

        if ($orders->isEmpty()) {
            return;
        }

        foreach ($orders as $index => $order) {
            $currentProcess = $processes[$index % count($processes)];
            $currentIndex = array_search($currentProcess, $processes, true);
            if ($currentIndex === false) {
                $currentIndex = 0;
                $currentProcess = $processes[0];
            }

            $processDates = $this->buildProcessEstimatedDates($processes, $currentIndex, $today, $index);

            DB::table('production_orders')
                ->where('id', $order->id)
                ->update([
                    'batch_priority' => $index,
                    'current_process' => $currentProcess,
                    'process_estimated_dates' => json_encode($processDates),
                    'estimated_delivery_date' => $order->estimated_delivery_date
                        ? (string) $order->estimated_delivery_date
                        : $today->copy()->addDays(15 + $index)->toDateString(),
                    'updated_at' => now(),
                ]);

            DB::table('batch_control_deliveries')
                ->where('production_order_id', $order->id)
                ->delete();

            for ($step = 0; $step < $currentIndex; $step++) {
                $fromProcess = $processes[$step];
                $toProcess = $processes[$step + 1];

                $deliveredAt = $today->copy()->subDays(($currentIndex - $step) + ($index % 3));

                DB::table('batch_control_deliveries')->insert([
                    'production_order_id' => $order->id,
                    'from_process' => $fromProcess,
                    'to_process' => $toProcess,
                    'observations' => "Entrega de prueba: {$fromProcess} completado y entregado a {$toProcess}.",
                    'delivered_by' => $seedUserId,
                    'created_at' => $deliveredAt,
                    'updated_at' => $deliveredAt,
                ]);
            }
        }

        $this->seedTechnicalSheets($today);
        $this->seedTechnicalSheetOperations($today);
        $this->seedProductionRollConsumptions($today);
        $this->seedBatchControlNovelties($processes, $today, $seedUserId);
    }

    /**
     * Fechas de prueba por proceso para una orden.
     *
     * - Estado anterior: fecha pasada
     * - Estado actual: vencida / por vencer / a tiempo / sin fecha (varía)
     * - Estado siguiente: fecha futura
     */
    private function buildProcessEstimatedDates(array $processes, int $currentIndex, Carbon $today, int $rowIndex): array
    {
        $dates = [];

        if ($currentIndex > 0) {
            $previousProcess = $processes[$currentIndex - 1];
            $dates[$previousProcess] = $today->copy()->subDays(2 + ($rowIndex % 2))->toDateString();
        }

        $currentProcess = $processes[$currentIndex] ?? $processes[0];
        $variant = $rowIndex % 4;

        if ($variant === 0) {
            $dates[$currentProcess] = $today->copy()->subDays(3)->toDateString();
        } elseif ($variant === 1) {
            $dates[$currentProcess] = $today->copy()->addDay()->toDateString();
        } elseif ($variant === 2) {
            $dates[$currentProcess] = $today->copy()->addDays(6)->toDateString();
        }

        if ($currentIndex < count($processes) - 1) {
            $nextProcess = $processes[$currentIndex + 1];
            $dates[$nextProcess] = $today->copy()->addDays(10 + ($rowIndex % 3))->toDateString();
        }

        return $dates;
    }

    /**
     * Asegura TechnicalSheet para cada ProductionOrder del módulo.
     */
    private function seedTechnicalSheets(Carbon $today): void
    {
        if (!Schema::hasTable('technical_sheets') || !Schema::hasTable('production_orders')) {
            return;
        }

        $orderIds = DB::table('production_orders')->pluck('id');

        foreach ($orderIds as $orderId) {
            $exists = DB::table('technical_sheets')
                ->where('production_order_id', $orderId)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('technical_sheets')->insert([
                'production_order_id' => $orderId,
                'status' => 'borrador',
                'created_at' => $today,
                'updated_at' => $today,
            ]);
        }
    }

    /**
     * Semilla para technical_sheet_operations.
     * Cubre la migración de operaciones de ficha técnica en el módulo de Producción.
     */
    private function seedTechnicalSheetOperations(Carbon $today): void
    {
        if (!Schema::hasTable('technical_sheet_operations') || !Schema::hasTable('technical_sheets')) {
            return;
        }

        $operationId = DB::table('operations')->value('id');
        if (!$operationId) {
            return;
        }

        $machineId = Schema::hasTable('machines') ? DB::table('machines')->value('id') : null;

        $sheetRows = DB::table('technical_sheets as ts')
            ->leftJoin('production_orders as po', 'po.id', '=', 'ts.production_order_id')
            ->select('ts.id as technical_sheet_id', 'po.units')
            ->orderBy('ts.id')
            ->get();

        foreach ($sheetRows as $rowIndex => $sheetRow) {
            $exists = DB::table('technical_sheet_operations')
                ->where('technical_sheet_id', $sheetRow->technical_sheet_id)
                ->exists();

            if ($exists) {
                continue;
            }

            $units = (int) ($sheetRow->units ?? 100);
            if ($units <= 0) {
                $units = 100;
            }

            for ($step = 1; $step <= 2; $step++) {
                $quantity = 1 + (($rowIndex + $step) % 3);
                $samBase = 0.55 + (0.15 * $step);
                $machineFactor = 1.10;
                $fabricFactor = 1.05;
                $efficiencyFactor = 1.00;
                $samUnit = $samBase * $machineFactor * $fabricFactor * $efficiencyFactor;
                $samOperation = $samUnit * $quantity;
                $samLot = $samOperation * $units;

                DB::table('technical_sheet_operations')->insert([
                    'technical_sheet_id' => $sheetRow->technical_sheet_id,
                    'product_sheet_operation_id' => null,
                    'sequence' => $step * 10,
                    'operation_id' => $operationId,
                    'machine_id' => $machineId,
                    'quantity' => $quantity,
                    'sam_base' => round($samBase, 4),
                    'machine_supplement_factor' => round($machineFactor, 4),
                    'fabric_base_factor' => round($fabricFactor, 4),
                    'operator_efficiency_factor' => round($efficiencyFactor, 4),
                    'sam_total_unitario' => round($samUnit, 4),
                    'sam_total_operacion' => round($samOperation, 4),
                    'sam_total_lote' => round($samLot, 4),
                    'quantity_progressed' => 0,
                    'motivo_progreso' => null,
                    'status' => 'PENDIENTE',
                    'progressed_at' => null,
                    'created_at' => $today,
                    'updated_at' => $today,
                    'deleted_at' => null,
                ]);
            }
        }
    }

    /**
     * Semilla para production_roll_consumptions.
     * Cubre la migración de consumos de rollo del módulo de Producción.
     */
    private function seedProductionRollConsumptions(Carbon $today): void
    {
        if (!Schema::hasTable('production_roll_consumptions') || !Schema::hasTable('fabric_rolls')) {
            return;
        }

        $alreadySeeded = DB::table('production_roll_consumptions')->exists();
        if ($alreadySeeded) {
            return;
        }

        $orders = DB::table('production_orders')
            ->select('id')
            ->orderBy('id')
            ->limit(20)
            ->get();

        $rolls = DB::table('fabric_rolls')
            ->select('id', 'quantity')
            ->orderBy('id')
            ->limit(20)
            ->get();

        if ($orders->isEmpty() || $rolls->isEmpty()) {
            return;
        }

        $maxRows = min($orders->count(), $rolls->count());

        for ($i = 0; $i < $maxRows; $i++) {
            $orderId = $orders[$i]->id;
            $roll = $rolls[$i];

            $rollQty = (float) ($roll->quantity ?? 0);
            $meters = $rollQty > 0 ? min($rollQty, 12 + ($i * 2)) : 10;

            DB::table('production_roll_consumptions')->insert([
                'production_order_id' => $orderId,
                'fabric_roll_id' => $roll->id,
                'meters_consumed' => round($meters, 6),
                'created_at' => $today,
                'updated_at' => $today,
            ]);
        }
    }

    /**
     * Semilla para batch_control_novelties.
     * Crea novedades de prueba (pendientes y resueltas) para órdenes
     * que ya estén en un proceso posterior al primero.
     */
    private function seedBatchControlNovelties(array $processes, Carbon $today, ?int $userId): void
    {
        if (!Schema::hasTable('batch_control_novelties')) {
            return;
        }

        $alreadySeeded = DB::table('batch_control_novelties')->exists();
        if ($alreadySeeded) {
            return;
        }

        if (!$userId) {
            return;
        }

        // Buscar órdenes que estén en un proceso posterior al primero
        $orders = DB::table('production_orders')
            ->select('id', 'op_code', 'current_process')
            ->orderBy('id')
            ->get();

        $descriptions = [
            'Falta revisión de medidas en la muestra anterior.',
            'El material entregado no coincide con la especificación de la ficha técnica.',
            'Se encontraron defectos de calidad en el corte enviado.',
            'Faltan hilos del color solicitado en la orden original.',
            'La cantidad entregada no coincide con lo programado.',
        ];

        $resolutionNotes = [
            'Corregido. Se ajustaron las medidas según especificación.',
            'Material reemplazado por el correcto.',
            'Defectos corregidos y calidad verificada.',
        ];

        $insertedCount = 0;

        foreach ($orders as $index => $order) {
            $currentIdx = array_search($order->current_process, $processes, true);

            // Solo crear novedades si está en un estado que tiene al menos un anterior
            if ($currentIdx === false || $currentIdx < 1) {
                continue;
            }

            // La primera orden con novedad: 1 pendiente
            if ($insertedCount === 0) {
                $targetProcess = $processes[$currentIdx - 1];
                DB::table('batch_control_novelties')->insert([
                    'production_order_id' => $order->id,
                    'from_process' => $order->current_process,
                    'to_process' => $targetProcess,
                    'description' => $descriptions[$index % count($descriptions)],
                    'status' => 'pending',
                    'reported_by' => $userId,
                    'resolved_by' => null,
                    'resolved_at' => null,
                    'resolution_notes' => null,
                    'created_at' => $today->copy()->subHours(6),
                    'updated_at' => $today->copy()->subHours(6),
                ]);
                $insertedCount++;
                continue;
            }

            // La segunda orden: 1 resuelta + 1 pendiente
            if ($insertedCount === 1) {
                $targetProcess = $processes[$currentIdx - 1];

                // Resuelta
                DB::table('batch_control_novelties')->insert([
                    'production_order_id' => $order->id,
                    'from_process' => $order->current_process,
                    'to_process' => $targetProcess,
                    'description' => $descriptions[($index + 1) % count($descriptions)],
                    'status' => 'resolved',
                    'reported_by' => $userId,
                    'resolved_by' => $userId,
                    'resolved_at' => $today->copy()->subHours(2),
                    'resolution_notes' => $resolutionNotes[$index % count($resolutionNotes)],
                    'created_at' => $today->copy()->subDays(1),
                    'updated_at' => $today->copy()->subHours(2),
                ]);

                // Pendiente
                DB::table('batch_control_novelties')->insert([
                    'production_order_id' => $order->id,
                    'from_process' => $order->current_process,
                    'to_process' => $targetProcess,
                    'description' => $descriptions[($index + 2) % count($descriptions)],
                    'status' => 'pending',
                    'reported_by' => $userId,
                    'resolved_by' => null,
                    'resolved_at' => null,
                    'resolution_notes' => null,
                    'created_at' => $today->copy()->subHours(3),
                    'updated_at' => $today->copy()->subHours(3),
                ]);
                $insertedCount++;
                break; // Suficientes datos de prueba
            }
        }
    }
}
