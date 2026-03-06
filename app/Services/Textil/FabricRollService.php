<?php

namespace App\Services\Textil;

use App\Models\Expenses\PurchaseDocument;
use App\Models\Expenses\PurchaseDocumentItem;
use App\Models\Production\ProductionRollConsumption;
use App\Models\Textil\FabricRoll;
use App\Services\Inventory\InventoryMovementService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class FabricRollService
{
    public function __construct(
        protected InventoryMovementService $movementService
    ) {}
    /** Tolerancia de cantidad al aprobar recepción: ±30% respecto al pedido por ítem. */
    public const TOLERANCE_QUANTITY_PERCENT = 30;

    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = FabricRoll::with([
            'purchaseDocumentItem.item',
            'purchaseDocumentItem.document.supplier',
            'purchaseDocumentItem.document.creator',
        ]);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('roll_number', 'like', "%{$search}%")
                    ->orWhereHas('purchaseDocumentItem.item', function ($article) use ($search) {
                        $article->where('description', 'like', "%{$search}%")
                                ->orWhere('reference', 'like', "%{$search}%");
                    });
            });
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            if ($filters['status'] === 'available') {
                $query->whereRaw('quantity > actual_output');
            } elseif ($filters['status'] === 'depleted') {
                $query->whereRaw('quantity <= actual_output');
            }
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('entry_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('entry_date', '<=', $filters['date_to']);
        }

        return $query->orderBy('entry_date', 'desc')->paginate($perPage);
    }

    public function create(array $data): FabricRoll
    {
        return DB::transaction(function () use ($data) {
            $data['partial_output'] = $data['partial_output'] ?? 0;
            $data['actual_output'] = $data['actual_output'] ?? 0;
            $data['roll_width'] = $data['roll_width'] ?? 0;
            $data['approval_status'] = $data['approval_status'] ?? FabricRoll::APPROVAL_PENDING;

            $fabricRoll = FabricRoll::create($data);
            $fabricRoll->load([
                'purchaseDocumentItem.item',
                'purchaseDocumentItem.document.supplier',
                'purchaseDocumentItem.document.creator',
            ]);

            return $fabricRoll;
        });
    }

    public function findOrFail($id): FabricRoll
    {
        return FabricRoll::with([
            'purchaseDocumentItem.item',
            'purchaseDocumentItem.document.supplier',
            'purchaseDocumentItem.document.creator',
        ])->findOrFail($id);
    }

    public function update(FabricRoll $fabricRoll, array $data): FabricRoll
    {
        $this->ensureInvoiceNotYetReceptionApprovedForRoll($fabricRoll);
        return DB::transaction(function () use ($fabricRoll, $data) {
            $data['partial_output'] = $data['partial_output'] ?? 0;
            $data['actual_output'] = $data['actual_output'] ?? 0;

            $fabricRoll->update($data);
            $fabricRoll->load([
                'purchaseDocumentItem.item',
                'purchaseDocumentItem.document.supplier',
                'purchaseDocumentItem.document.creator',
            ]);

            return $fabricRoll;
        });
    }

    public function delete(FabricRoll $fabricRoll): void
    {
        $this->ensureInvoiceNotYetReceptionApprovedForRoll($fabricRoll);
        $fabricRoll->delete();
    }

    /**
     * No permite eliminar ni editar un rollo si la factura ya está recepcionada.
     * @throws ValidationException
     */
    protected function ensureInvoiceNotYetReceptionApprovedForRoll(FabricRoll $fabricRoll): void
    {
        $fabricRoll->loadMissing('purchaseDocumentItem.document');
        $doc = $fabricRoll->purchaseDocumentItem?->document;
        if ($doc && $doc->status === 'approved') {
            throw ValidationException::withMessages([
                'general' => ['No se puede modificar o eliminar el rollo: la factura ya está recepcionada.'],
            ]);
        }
    }

    public function updateOutput(FabricRoll $fabricRoll, array $data, ?int $productionOrderId = null): FabricRoll
    {
        $previousOutput = (float) $fabricRoll->actual_output;
        $newOutput = array_key_exists('actual_output', $data)
            ? (float) $data['actual_output']
            : $previousOutput;
        $consumedDelta = max(0, $newOutput - $previousOutput);

        $fabricRoll->update($data);
        $fabricRoll->load([
            'purchaseDocumentItem.item',
            'purchaseDocumentItem.document.supplier',
            'purchaseDocumentItem.document.creator',
        ]);

        if ($consumedDelta > 0) {
            $this->recordConsumptionAndCreateMovement($fabricRoll, $consumedDelta, $productionOrderId);
        }

        return $fabricRoll;
    }

    /**
     * Registra consumo de rollo y crea movimiento de inventario (salida).
     * No llama a syncItemWarehouseLength: el movimiento actualiza warehouse_items.
     */
    protected function recordConsumptionAndCreateMovement(FabricRoll $fabricRoll, float $metersConsumed, ?int $productionOrderId): void
    {
        $item = $fabricRoll->purchaseDocumentItem?->item;
        if (!$item) {
            return;
        }

        $warehouse = $item->warehouses()->first();
        if (!$warehouse) {
            return;
        }

        $unitPrice = $this->movementService->getCurrentWeightedPrice($item->id, $warehouse->id);
        if ($unitPrice <= 0 && $fabricRoll->purchaseDocumentItem) {
            $unitPrice = (float) $fabricRoll->purchaseDocumentItem->price;
        }

        ProductionRollConsumption::create([
            'production_order_id' => $productionOrderId,
            'fabric_roll_id' => $fabricRoll->id,
            'meters_consumed' => $metersConsumed,
        ]);

        $this->movementService->createMovement([
            'item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'type' => 'salida',
            'quantity' => $metersConsumed,
            'unit_price' => $unitPrice,
            'source_type' => $productionOrderId ? \App\Models\Production\ProductionOrder::class : null,
            'source_id' => $productionOrderId,
            'notes' => 'Consumo de rollo #' . $fabricRoll->roll_number,
            'created_by_user_id' => Auth::id(),
        ]);
    }

    public function storeFromReception(array $data): FabricRoll
    {
        $this->ensureInvoiceNotYetReceptionApproved((int) $data['purchase_document_item_id']);
        return $this->create($data);
    }

    /**
     * No permite registrar rollos si la factura ya fue marcada como recepcionada (aprobada).
     * @throws ValidationException
     */
    protected function ensureInvoiceNotYetReceptionApproved(int $purchaseDocumentItemId): void
    {
        $item = PurchaseDocumentItem::with('document')->find($purchaseDocumentItemId);
        if (!$item || !$item->document) {
            return;
        }
        if ($item->document->status === 'approved') {
            throw ValidationException::withMessages([
                'general' => ['No se pueden añadir más rollos: la factura ya está recepcionada (aprobada).'],
            ]);
        }
    }

    protected function sumRollQuantityForItem(int $itemId): float
    {
        return (float) FabricRoll::whereHas('purchaseDocumentItem', function ($query) use ($itemId) {
            $query->where('item_id', $itemId);
        })->sum('quantity');
    }

    /** Suma de metros recibidos para un ítem de documento (línea de factura). */
    protected function sumRollQuantityForPurchaseDocumentItem(int $purchaseDocumentItemId): float
    {
        return (float) FabricRoll::where('purchase_document_item_id', $purchaseDocumentItemId)->sum('quantity');
    }

    public function getByInvoiceItem($itemId): Collection
    {
        return FabricRoll::with([
            'purchaseDocumentItem.item',
            'purchaseDocumentItem.document.supplier',
            'purchaseDocumentItem.document.creator',
        ])
            ->where('purchase_document_item_id', $itemId)
            ->orderBy('entry_date')
            ->get();
    }

    public function export(array $fabricItemIds = []): Collection
    {
        $query = FabricRoll::with([
            'purchaseDocumentItem.item',
            'purchaseDocumentItem.document.supplier',
            'purchaseDocumentItem.document.creator',
        ]);

        if (!empty($fabricItemIds)) {
            $query->whereHas('purchaseDocumentItem', function ($builder) use ($fabricItemIds) {
                $builder->whereIn('item_id', $fabricItemIds);
            });
        }

        return $query
            ->orderBy('entry_date', 'desc')
            ->get();
    }

    public function available(): Collection
    {
        return FabricRoll::with([
            'purchaseDocumentItem.item',
            'purchaseDocumentItem.document.supplier',
            'purchaseDocumentItem.document.creator',
        ])
            ->whereColumn('quantity', '>', 'actual_output')
            ->orderBy('entry_date', 'desc')
            ->get();
    }

    public function approveReception($invoiceId)
    {
        $invoice = PurchaseDocument::with(['items', 'items.item.categorySubGroup'])->where('document_type', PurchaseDocument::TYPE_INVOICE)
            ->findOrFail($invoiceId);

        $this->validateReceptionTolerances($invoice);

        $invoice->update(['status' => 'approved']);
        return $invoice;
    }

    /**
     * No permite aprobar la recepción si algún ítem no cumple la tolerancia de cantidad (±30%).
     * @throws ValidationException
     */
    protected function validateReceptionTolerances(PurchaseDocument $invoice): void
    {
        $failing = [];
        foreach ($invoice->items as $item) {
            if (!$item instanceof PurchaseDocumentItem || !$this->isFabricDocumentItem($item)) {
                continue;
            }

            $ordered = (float) $item->quantity;
            if ($ordered <= 0) {
                continue;
            }
            $received = $this->sumRollQuantityForPurchaseDocumentItem($item->id);
            $diff = abs($ordered - $received);
            $percent = ($diff / $ordered) * 100;
            if ($percent > self::TOLERANCE_QUANTITY_PERCENT) {
                $name = $item->item_name ?? $item->item?->name ?? "Ítem #{$item->id}";
                $failing[] = sprintf(
                    '%s: pedido %.2f mts, recibido %.2f mts (%.1f%% fuera de ±%d%%)',
                    $name,
                    $ordered,
                    $received,
                    $percent,
                    self::TOLERANCE_QUANTITY_PERCENT
                );
            }
        }
        if (count($failing) > 0) {
            throw ValidationException::withMessages([
                'general' => [
                    'No se puede aprobar la recepción: la cantidad recibida de uno o más ítems no cumple la tolerancia de ±' . self::TOLERANCE_QUANTITY_PERCENT . '%. ' . implode('; ', $failing),
                ],
            ]);
        }
    }

    protected function isFabricDocumentItem(PurchaseDocumentItem $item): bool
    {
        $subgroupName = mb_strtolower(trim((string) ($item->item?->categorySubGroup?->name ?? '')));
        if ($subgroupName !== '') {
            return str_contains($subgroupName, 'tela');
        }

        $fallbackName = mb_strtolower(trim((string) ($item->item_name ?? $item->item?->name ?? '')));
        return $fallbackName !== '' && str_contains($fallbackName, 'tela');
    }

    public function rejectReception($invoiceId)
    {
        $invoice = PurchaseDocument::where('document_type', PurchaseDocument::TYPE_INVOICE)
            ->findOrFail($invoiceId);
        $invoice->update(['status' => 'rejected']);
        return $invoice;
    }
}
