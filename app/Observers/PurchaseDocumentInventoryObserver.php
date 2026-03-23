<?php

namespace App\Observers;

use App\Models\Expenses\PurchaseDocument;
use App\Models\Inventory\InventoryMovement;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Inventory\WarehouseResolver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Crea movimientos de entrada en inventario al aprobar una factura de compra.
 * Todo debe pasar por InventoryMovementService::createMovement (flujo centralizado).
 */
class PurchaseDocumentInventoryObserver
{
    public function __construct(
        protected InventoryMovementService $movementService,
        protected WarehouseResolver $warehouseResolver,
    ) {}

    public function updated(PurchaseDocument $document): void
    {
        $this->maybeCreateInventoryEntriesForApprovedInvoice($document);
    }

    protected function maybeCreateInventoryEntriesForApprovedInvoice(PurchaseDocument $document): void
    {
        if (!$document->isInvoice()) {
            return;
        }

        if (($document->approval_status ?? '') !== 'approved') {
            return;
        }

        $alreadyHasMovements = InventoryMovement::query()
            ->where('source_type', PurchaseDocument::class)
            ->where('source_id', $document->id)
            ->exists();

        if ($alreadyHasMovements) {
            return;
        }

        $this->createInventoryEntriesForInvoice($document);
    }

    protected function createInventoryEntriesForInvoice(PurchaseDocument $document): void
    {
        $document->load(['items.item']);

        $userId = $document->approved_by_user_id ?? Auth::id();
        if (empty($userId)) {
            return;
        }

        $notesBase = 'Aprobación factura de compra ' . ($document->document_number ?? '');
        $date = $document->date?->toDateString() ?? now()->toDateString();

        DB::transaction(function () use ($document, $userId, $notesBase, $date) {
            foreach ($document->items as $line) {
                if (empty($line->item_id)) {
                    continue;
                }

                $item = $line->item;
                if (!$item) {
                    continue;
                }

                try {
                    $warehouseId = $this->warehouseResolver->resolveWarehouseId($item, null);
                } catch (\Throwable) {
                    continue;
                }

                $qty = (float) $line->quantity;
                $price = (float) $line->price;
                if ($qty <= 0 || $price <= 0) {
                    continue;
                }

                $this->movementService->createMovement([
                    'item_id' => $line->item_id,
                    'warehouse_id' => $warehouseId,
                    'type' => 'entrada',
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'date' => $date,
                    'source_type' => PurchaseDocument::class,
                    'source_id' => $document->id,
                    'notes' => $notesBase,
                    'created_by_user_id' => $userId,
                ]);
            }
        });
    }
}
