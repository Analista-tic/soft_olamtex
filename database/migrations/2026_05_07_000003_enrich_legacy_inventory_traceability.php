<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->string('source_reference', 120)->nullable()->after('source_id');
            $table->index('source_reference', 'inventory_movements_source_reference_idx');
        });

        Schema::table('warehouse_items', function (Blueprint $table) {
            $table->decimal('last_unit_cost', 15, 4)->nullable()->after('weighted_average_price');
            $table->decimal('total_cost', 18, 4)->nullable()->after('last_unit_cost');
            $table->string('source_type', 60)->nullable()->after('total_cost');
            $table->string('source_reference', 120)->nullable()->after('source_type');
            $table->index(['source_type', 'source_reference'], 'warehouse_items_source_type_reference_idx');
        });

        $this->backfillLegacyMovementReferences();
        $this->backfillWarehouseItems();
    }

    public function down(): void
    {
        Schema::table('warehouse_items', function (Blueprint $table) {
            $table->dropIndex('warehouse_items_source_type_reference_idx');
            $table->dropColumn([
                'last_unit_cost',
                'total_cost',
                'source_type',
                'source_reference',
            ]);
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropIndex('inventory_movements_source_reference_idx');
            $table->dropColumn('source_reference');
        });
    }

    private function backfillLegacyMovementReferences(): void
    {
        $prefix = $this->legacyDocumentStoragePrefix();
        $movements = DB::table('inventory_movements')
            ->where('source_type', 'legacy_import')
            ->orderBy('id')
            ->get(['id', 'document_number']);

        if ($movements->isEmpty()) {
            return;
        }

        $batches = [];

        foreach ($movements as $movement) {
            $batchReference = $this->extractLegacyBatchReference((string) $movement->document_number)
                ?? "legacy_import_{$movement->id}";

            if (!array_key_exists($batchReference, $batches)) {
                $batches[$batchReference] = [];
            }

            $batches[$batchReference][] = (int) $movement->id;
        }

        $batchNumber = 0;

        foreach ($batches as $batchReference => $movementIds) {
            $batchNumber++;
            $sequence = 0;

            foreach ($movementIds as $movementId) {
                $sequence++;

                DB::table('inventory_movements')
                    ->where('id', $movementId)
                    ->update([
                        'document_number' => sprintf('%s %d-%04d', $prefix, $batchNumber, $sequence),
                        'source_reference' => $batchReference,
                    ]);
            }
        }
    }

    private function backfillWarehouseItems(): void
    {
        $rows = DB::table('warehouse_items')
            ->orderBy('id')
            ->get([
                'id',
                'item_id',
                'warehouse_id',
                'quantity',
                'weighted_average_price',
            ]);

        foreach ($rows as $row) {
            $latestMovement = DB::table('inventory_movements')
                ->where('item_id', $row->item_id)
                ->where('warehouse_id', $row->warehouse_id)
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->first([
                    'source_type',
                    'source_reference',
                ]);

            $latestEntry = DB::table('inventory_movements')
                ->where('item_id', $row->item_id)
                ->where('warehouse_id', $row->warehouse_id)
                ->where('type', 'entrada')
                ->whereNotNull('unit_price')
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->first([
                    'unit_price',
                ]);

            $averageCost = $row->weighted_average_price !== null
                ? (float) $row->weighted_average_price
                : 0.0;
            $quantity = (float) $row->quantity;
            $lastUnitCost = $latestEntry?->unit_price !== null
                ? (float) $latestEntry->unit_price
                : ($averageCost > 0 ? $averageCost : null);

            DB::table('warehouse_items')
                ->where('id', $row->id)
                ->update([
                    'last_unit_cost' => $lastUnitCost,
                    'total_cost' => round($quantity * $averageCost, 4),
                    'source_type' => $latestMovement?->source_type,
                    'source_reference' => $latestMovement?->source_reference,
                ]);
        }
    }

    private function extractLegacyBatchReference(string $documentNumber): ?string
    {
        if (preg_match('/^LM-(.+)-\d+-\d+$/u', $documentNumber, $matches) === 1) {
            return trim((string) ($matches[1] ?? '')) ?: null;
        }

        return null;
    }

    private function legacyDocumentStoragePrefix(): string
    {
        return "Migraci\u{00F3}n";
    }
};
