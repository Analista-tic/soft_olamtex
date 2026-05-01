<?php

namespace App\Observers\Textil;

use App\Models\Inventory\WarehouseItem;
use App\Models\Textil\FabricRoll;

class FabricRollObserver
{
    public function saved(FabricRoll $roll): void
    {
        $this->recalcularStock($roll);
    }

    public function deleted(FabricRoll $roll): void
    {
        $this->recalcularStock($roll);
    }

    private function recalcularStock(FabricRoll $roll): void
    {
        $roll->loadMissing([
            'item.categorySubGroup',
            'purchaseDocumentItem.item.categorySubGroup',
        ]);

        $item = $roll->item ?? $roll->purchaseDocumentItem?->item;

        if (! $item) {
            return;
        }

        $subgrupo = mb_strtoupper(trim((string) ($item->categorySubGroup?->name ?? '')));
        if ($subgrupo !== 'TELA') {
            return;
        }

        $totalDisponible = (float) (
            FabricRoll::where('item_id', $item->id)
                ->where('approval_status', FabricRoll::APPROVAL_APPROVED)
                ->whereNull('deleted_at')
                ->selectRaw('SUM(quantity - actual_output) as total')
                ->value('total') ?? 0
        );

        WarehouseItem::where('item_id', $item->id)
            ->update(['quantity' => $totalDisponible]);
    }
}
