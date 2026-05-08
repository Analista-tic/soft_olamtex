<?php

namespace App\Services\Textil;

use App\Models\Expenses\PurchaseDocument;
use App\Models\Expenses\PurchaseDocumentItem;
use App\Models\MasterData\Item;
use App\Models\Production\ProductionRollConsumption;
use App\Models\Textil\FabricRoll;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Inventory\WarehouseResolver;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class FabricRollService
{
    public function __construct(
        protected InventoryMovementService $movementService,
        protected WarehouseResolver $warehouseResolver
    ) {}
    /** Tolerancia de cantidad al aprobar recepción: ±30% respecto al pedido por ítem. */
    public const TOLERANCE_QUANTITY_PERCENT = 30;

    /**
     * Genera el siguiente número de rollo consecutivo en formato RLXXXXX.
     * Usa un lock pesimista para evitar duplicados en concurrencia.
     */
    public function nextRollNumber(): string
    {
        return DB::transaction(function () {
            // Bloquea la tabla mientras calculamos el máximo para evitar race conditions
            $max = DB::table('fabric_rolls')
                ->where('roll_number', 'like', 'RL%')
                ->lockForUpdate()
                ->selectRaw("MAX(CAST(REGEXP_REPLACE(roll_number, '[^0-9]', '') AS UNSIGNED)) as max_num")
                ->value('max_num');

            $next = ($max ?? 0) + 1;

            return 'RL' . $next;
        });
    }

    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = FabricRoll::with([
            'item',
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
                'item',
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
            'item',
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
                'item',
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
        if (!$doc) {
            return;
        }

        if ($doc->status === 'approved') {
            throw ValidationException::withMessages([
                'general' => ['No se puede modificar o eliminar el rollo: la factura ya está recepcionada.'],
            ]);
        }

        if ($this->isDifferenceRejectedInvoice($doc)) {
            throw ValidationException::withMessages([
                'general' => ['No se puede modificar o eliminar el rollo: la factura tiene diferencia rechazada y no puede gestionarse en recepción.'],
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
            'item',
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
        $item = $fabricRoll->item ?? $fabricRoll->purchaseDocumentItem?->item;
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

        $pdi = PurchaseDocumentItem::findOrFail($data['purchase_document_item_id']);
        $data['item_id'] = $pdi->item_id;

        // Resolver color desde el item si no viene en el payload
        if (empty($data['internal_color_name']) && $pdi->item_id) {
            $item = Item::with('color')->find($pdi->item_id);
            if ($item && $item->color) {
                $data['internal_color_name'] = $item->color->name;
            }
        }

        // Resolver price_per_meter desde la factura
        // si no viene en el payload
        if (empty($data['price_per_meter']) && $pdi->price) {
            $data['price_per_meter'] = (float) $pdi->price;
        }

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

        if ($this->isDifferenceRejectedInvoice($item->document)) {
            throw ValidationException::withMessages([
                'general' => ['No se pueden añadir rollos: la factura tiene diferencia rechazada y no puede gestionarse en recepción.'],
            ]);
        }
    }

    /** Suma de metros recibidos para un ítem de documento (línea de factura). */
    protected function sumRollQuantityForPurchaseDocumentItem(int $purchaseDocumentItemId): float
    {
        return (float) FabricRoll::where('purchase_document_item_id', $purchaseDocumentItemId)->sum('quantity');
    }

    public function getByInvoiceItem($itemId): Collection
    {
        return FabricRoll::with([
            'item',
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
            'item.color',
            'purchaseDocumentItem.item.color',
            'purchaseDocumentItem.document.supplier',
            'purchaseDocumentItem.document.creator',
        ]);

        if (!empty($fabricItemIds)) {
            $query->whereHas('purchaseDocumentItem', function ($builder) use ($fabricItemIds) {
                $builder->whereIn('item_id', $fabricItemIds);
            });
        }

        return $query
            ->where('approval_status', FabricRoll::APPROVAL_APPROVED)
            ->orderBy('entry_date', 'desc')
            ->get();
    }

    /**
     * Retorna rollos con datos de tela/proveedor aplanados vía JOINs.
     * Evita eager-loading masivo: carga solo columnas necesarias en una sola query.
     *
     * @return LengthAwarePaginator
     */
    public function inventoryWithFilters(array $filters = [], int $perPage = 100, string $sortBy = 'entry_date', string $sortDirection = 'desc'): LengthAwarePaginator
    {
        $allowedSorts = [
            'roll_number'  => DB::raw("CAST(REGEXP_REPLACE(fr.roll_number, '[^0-9]', '') AS UNSIGNED)"),
            'location'     => 'fr.location',
            'fabric'       => DB::raw("COALESCE(i.name, pdi.item_name, 'Tela')"),
            'color'        => DB::raw("COALESCE(fr.internal_color_name, pdi.supplier_color)"),
            'width'        => 'fr.roll_width',
            'quantity'     => 'fr.quantity',
            'partial_output' => 'fr.partial_output',
            'actual_output'  => 'fr.actual_output',
            'entry_date'   => 'fr.entry_date',
            'provider'     => DB::raw("COALESCE(c.full_name, pd.provider_name, 'Proveedor')"),
        ];

        $sortColumn    = $allowedSorts[$sortBy] ?? 'fr.entry_date';
        $sortDirection = strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';

        $query = DB::table('fabric_rolls as fr')
            ->select([
                'fr.id',
                'fr.roll_number',
                'fr.location',
                'fr.quantity',
                'fr.roll_width',
                DB::raw('(fr.quantity - fr.partial_output) as partial_availability_value'),
                DB::raw('CONCAT(FORMAT(fr.quantity - fr.partial_output, 2), " m") as partial_availability'),
                'fr.partial_output',
                'fr.actual_output',
                'fr.internal_color_name',
                DB::raw('COALESCE(col_fi.hexadecimal, col_i.hexadecimal) as item_color_hex'),
                DB::raw('COALESCE(col_fi.name, col_i.name) as item_color_name'),
                DB::raw("COALESCE(fi.name, i.name, pdi.item_name, fr.description, 'Tela') as fabric"),
                'pdi.id as purchase_document_item_id',
                DB::raw('COALESCE(fr.item_id, pdi.item_id) as fabric_item_id'),
                'pdi.supplier_color',
                'pdi.supplier_code',
                DB::raw('COALESCE(fr.price_per_meter, pdi.price) as price_per_meter'),
                DB::raw("COALESCE(c.full_name, pd.provider_name, 'Proveedor') as provider"),
                DB::raw("COALESCE(pd.supplier_order_number, pd.document_number, 'N/A') as order_number"),
                'pd.created_at as doc_created_at',
                'u.name as creator_name',
            ])
            ->leftJoin('purchase_document_items as pdi', 'pdi.id', '=', 'fr.purchase_document_item_id')
            ->leftJoin('items as fi', 'fi.id', '=', 'fr.item_id')
            ->leftJoin('colors as col_fi', 'col_fi.id', '=', 'fi.color_id')
            ->leftJoin('items as i', 'i.id', '=', 'pdi.item_id')
            ->leftJoin('colors as col_i', 'col_i.id', '=', 'i.color_id')
            ->leftJoin('purchase_documents as pd', 'pd.id', '=', 'pdi.purchase_document_id')
            ->leftJoin('contacts as c', 'c.id', '=', 'pd.contact_id')
            ->leftJoin('users as u', 'u.id', '=', 'pd.created_by_user_id')
            ->whereNull('fr.deleted_at')
            ->where('fr.approval_status', FabricRoll::APPROVAL_APPROVED);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('fr.roll_number', 'like', "%{$search}%")
                    ->orWhere('fr.location', 'like', "%{$search}%")
                    ->orWhere('fi.name', 'like', "%{$search}%")
                    ->orWhere('i.name', 'like', "%{$search}%")
                    ->orWhere('pdi.item_name', 'like', "%{$search}%")
                    ->orWhere('fr.internal_color_name', 'like', "%{$search}%")
                    ->orWhere('pdi.supplier_color', 'like', "%{$search}%")
                    ->orWhere('c.full_name', 'like', "%{$search}%")
                    ->orWhere('pd.provider_name', 'like', "%{$search}%")
                    ->orWhere('pd.supplier_order_number', 'like', "%{$search}%")
                    ->orWhere('pd.document_number', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['color'])) {
            $query->where('fr.internal_color_name', $filters['color']);
        }

        if (!empty($filters['location'])) {
            $query->where('fr.location', $filters['location']);
        }

        if (!empty($filters['with_output'])) {
            $query->where(function ($q) {
                $q->where('fr.partial_output', '>', 0)
                    ->orWhere('fr.actual_output', '>', 0);
            });
        }

        if (!empty($filters['fabric_item_ids'])) {
            $query->whereIn(DB::raw('COALESCE(fr.item_id, pdi.item_id)'), (array) $filters['fabric_item_ids']);
        }

        return $query->orderBy($sortColumn, $sortDirection)->paginate($perPage);
    }

    public function available(): Collection
    {
        return FabricRoll::with([
            'item',
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
        $invoice = PurchaseDocument::with(['items', 'items.item.categorySubGroup'])
            ->where('document_type', PurchaseDocument::TYPE_INVOICE)
            ->findOrFail($invoiceId);

        $this->ensureInvoiceReceptionAllowed($invoice);
        $this->validateReceptionTolerances($invoice);

        return DB::transaction(function () use ($invoice) {
            $lockedInvoice = PurchaseDocument::query()
                ->where('document_type', PurchaseDocument::TYPE_INVOICE)
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            $pendingRolls = FabricRoll::with([
                'item',
                'purchaseDocumentItem.item',
            ])
                ->whereHas('purchaseDocumentItem', function ($q) use ($invoice) {
                    $q->where('purchase_document_id', $invoice->id);
                })
                ->where('approval_status', FabricRoll::APPROVAL_PENDING)
                ->lockForUpdate()
                ->get();

            $lockedInvoice->update(['status' => 'approved']);

            if ($pendingRolls->isNotEmpty()) {
                FabricRoll::whereIn('id', $pendingRolls->pluck('id'))
                    ->update(['approval_status' => FabricRoll::APPROVAL_APPROVED]);

                $existingMovementReferences = DB::table('inventory_movements')
                    ->where('source_type', PurchaseDocument::class)
                    ->where('source_id', $lockedInvoice->id)
                    ->whereIn('source_reference', $pendingRolls->pluck('roll_number'))
                    ->pluck('source_reference')
                    ->all();

                $existingMovementReferences = array_fill_keys($existingMovementReferences, true);
                $documentNumber = trim((string) ($lockedInvoice->document_number ?? '')) !== ''
                    ? trim((string) $lockedInvoice->document_number)
                    : 'Factura #' . $lockedInvoice->id;
                $responsibleUserId = Auth::id() ?? $lockedInvoice->approved_by_user_id ?? $lockedInvoice->created_by_user_id;

                if (empty($responsibleUserId)) {
                    throw new \RuntimeException('No se pudo resolver el usuario responsable de la recepción de rollos.');
                }

                foreach ($pendingRolls as $roll) {
                    if (isset($existingMovementReferences[$roll->roll_number])) {
                        continue;
                    }

                    $item = $roll->item ?? $roll->purchaseDocumentItem?->item;
                    if (! $item) {
                        continue;
                    }

                    $warehouseId = $this->warehouseResolver->resolveWarehouseId($item, null);
                    $unitPrice = $roll->price_per_meter !== null
                        ? (float) $roll->price_per_meter
                        : (float) ($roll->purchaseDocumentItem?->price ?? 0);
                    $movementDocumentNumber = mb_substr($documentNumber . ' [' . $roll->roll_number . ']', 0, 50);

                    $this->movementService->createMovement([
                        'document_number' => $movementDocumentNumber,
                        'item_id' => $item->id,
                        'warehouse_id' => $warehouseId,
                        'type' => 'entrada',
                        'quantity' => (float) $roll->quantity,
                        'unit_price' => $unitPrice,
                        'date' => $roll->entry_date?->toDateString() ?? now()->toDateString(),
                        'source_type' => PurchaseDocument::class,
                        'source_id' => $lockedInvoice->id,
                        'source_reference' => $roll->roll_number,
                        'notes' => 'Recepción de rollo ' . $roll->roll_number . ' de factura ' . $documentNumber,
                        'created_by_user_id' => (int) $responsibleUserId,
                    ]);
                }
            }

            return $lockedInvoice->fresh();
        });
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

        $this->ensureInvoiceReceptionAllowed($invoice);

        return DB::transaction(function () use ($invoice) {
            $invoice->update(['status' => 'rejected']);

            FabricRoll::whereHas('purchaseDocumentItem', function ($q) use ($invoice) {
                $q->where('purchase_document_id', $invoice->id);
            })
                ->where('approval_status', FabricRoll::APPROVAL_PENDING)
                ->update(['approval_status' => FabricRoll::APPROVAL_NOT_APPROVED]);

            return $invoice;
        });
    }

    protected function ensureInvoiceReceptionAllowed(PurchaseDocument $invoice): void
    {
        if ($this->isDifferenceRejectedInvoice($invoice)) {
            throw ValidationException::withMessages([
                'general' => ['La factura tiene diferencia rechazada y no puede volver al flujo de recepción.'],
            ]);
        }
    }

    protected function isDifferenceRejectedInvoice(PurchaseDocument $invoice): bool
    {
        $approvalStatus = mb_strtolower(trim((string) ($invoice->approval_status ?? '')));
        $status = mb_strtolower(trim((string) ($invoice->status ?? '')));

        return in_array($approvalStatus, [
            PurchaseDocument::APPROVAL_DIFFERENCE_REJECTED,
            'diferencia rechazada',
        ], true) || in_array($status, [
            PurchaseDocument::APPROVAL_DIFFERENCE_REJECTED,
            'diferencia rechazada',
        ], true);
    }
}
