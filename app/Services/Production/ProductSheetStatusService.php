<?php

namespace App\Services\Production;

use App\Models\Production\ProductSheet;
use Illuminate\Support\Facades\DB;

class ProductSheetStatusService
{
    private const STATUS_COMPLETE = 'complete';
    private const STATUS_INCOMPLETE = 'incomplete';

    /**
     * Promueve a completas solo las fichas incompletas que ya tienen operaciones.
     */
    public function promoteIncompleteWithOperations(): int
    {
        return ProductSheet::query()
            ->where('status', self::STATUS_INCOMPLETE)
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('product_sheet_operations')
                    ->whereColumn('product_sheet_operations.product_sheet_id', 'product_sheets.id')
                    ->whereNull('product_sheet_operations.deleted_at');
            })
            ->update(['status' => self::STATUS_COMPLETE]);
    }

    /**
     * Sincroniza estado de una ficha según existencia de operaciones activas.
     */
    public function syncForSheet(int $productSheetId): bool
    {
        $hasOperations = DB::table('product_sheet_operations')
            ->where('product_sheet_id', $productSheetId)
            ->whereNull('deleted_at')
            ->exists();

        $targetStatus = $hasOperations ? self::STATUS_COMPLETE : self::STATUS_INCOMPLETE;

        $updated = ProductSheet::query()
            ->whereKey($productSheetId)
            ->where('status', '!=', $targetStatus)
            ->update(['status' => $targetStatus]);

        return $updated > 0;
    }

    /**
     * Sincroniza múltiples fichas evitando ids duplicados.
     *
     * @param array<int, int> $productSheetIds
     */
    public function syncForSheets(array $productSheetIds): int
    {
        $updated = 0;

        foreach (array_unique(array_map('intval', $productSheetIds)) as $sheetId) {
            if ($sheetId > 0 && $this->syncForSheet($sheetId)) {
                $updated++;
            }
        }

        return $updated;
    }
}
