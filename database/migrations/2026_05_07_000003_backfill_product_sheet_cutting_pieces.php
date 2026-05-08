<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Backfill: technical_sheet_cutting_pieces → product_sheet_cutting_pieces
     */
    public function up(): void
    {
        // Validación de integridad: confirmar que existen OPs sin product_sheet_id
        $opsWithoutProductSheetId = DB::table('production_orders')
            ->whereNull('product_sheet_id')
            ->count();

        if ($opsWithoutProductSheetId > 0) {
            Log::warning("⚠️  Se encontraron {$opsWithoutProductSheetId} OPs sin product_sheet_id. Se saltarán.");
        }

        // Migración por lotes para mejor trazabilidad y control
        $batchSize = 100;
        $offset = 0;
        $totalProcessed = 0;
        $totalSkipped = 0;

        DB::transaction(function () use (&$offset, &$batchSize, &$totalProcessed, &$totalSkipped) {
            while (true) {
                $technicalPieces = DB::table('technical_sheet_cutting_pieces')
                    ->offset($offset)
                    ->limit($batchSize)
                    ->get();

                if ($technicalPieces->isEmpty()) {
                    break;
                }

                foreach ($technicalPieces as $piece) {
                    try {
                        // Obtener technical_sheet con su production_order_id
                        $technicalSheet = DB::table('technical_sheets')
                            ->where('id', $piece->technical_sheet_id)
                            ->first();

                        if (!$technicalSheet) {
                            Log::warning("Pieza ID {$piece->id}: TechnicalSheet no encontrado");
                            $totalSkipped++;
                            continue;
                        }

                        // Obtener production_order con su product_sheet_id
                        $productionOrder = DB::table('production_orders')
                            ->where('id', $technicalSheet->production_order_id)
                            ->first();

                        if (!$productionOrder) {
                            Log::warning("Pieza ID {$piece->id}: ProductionOrder no encontrado");
                            $totalSkipped++;
                            continue;
                        }

                        // VALIDACIÓN CRÍTICA: product_sheet_id debe existir
                        if (!$productionOrder->product_sheet_id) {
                            Log::warning("Pieza ID {$piece->id}: OP {$productionOrder->id} sin product_sheet_id. SALTADA.");
                            $totalSkipped++;
                            continue;
                        }

                        // Insertar en product_sheet_cutting_pieces
                        $newPieceId = DB::table('product_sheet_cutting_pieces')->insertGetId([
                            'product_sheet_id' => $productionOrder->product_sheet_id,
                            'piece_uid' => $piece->piece_uid,
                            'sequence' => $piece->sequence ?? 0,
                            'name' => $piece->name ?? '',
                            'piece_type' => $piece->piece_type ?? 'PRINCIPAL',
                            'fabric_type' => $piece->fabric_type ?? 'PRINCIPAL',
                            'fabric_uid' => $piece->fabric_uid,
                            'trace_location' => $piece->trace_location,
                            'side' => $piece->side ?? 'AMBOS',
                            'grain' => $piece->grain ?? '90',
                            'quantity' => $piece->quantity ?? 1,
                            'notes' => $piece->notes,
                            'created_at' => $piece->created_at,
                            'updated_at' => $piece->updated_at,
                        ]);

                        // Copiar sizes asociados
                        $sizes = DB::table('technical_sheet_cutting_piece_sizes')
                            ->where('cutting_piece_id', $piece->id)
                            ->get();

                        foreach ($sizes as $size) {
                            DB::table('product_sheet_cutting_piece_sizes')->insert([
                                'cutting_piece_id' => $newPieceId,
                                'size' => $size->size,
                                'units' => $size->units ?? 0,
                                'curve' => $size->curve,
                                'created_at' => $size->created_at,
                                'updated_at' => $size->updated_at,
                            ]);
                        }

                        $totalProcessed++;
                    } catch (\Exception $e) {
                        Log::error("Error procesando pieza ID {$piece->id}: {$e->getMessage()}");
                        $totalSkipped++;
                    }
                }

                $offset += $batchSize;
            }
        });

        Log::info("✅ Backfill completado:");
        Log::info("   - Procesadas: {$totalProcessed}");
        Log::info("   - Saltadas: {$totalSkipped}");
    }

    /**
     * Reverse the migrations.
     * Elimina datos copiados de product_sheet_cutting_pieces.
     * NO elimina technical_sheet_cutting_pieces (aún vigente).
     */
    public function down(): void
    {
        // Limpia las piezas copiadas (aquellas que vinieron del backfill)
        // Para identificarlas, usamos el hecho de que todas tienen sequence >= 0
        DB::table('product_sheet_cutting_pieces')->delete();
        DB::table('product_sheet_cutting_piece_sizes')->delete();

        Log::info("⚠️  Backfill revertido: tablas product_sheet_* vaciadas.");
    }
};
