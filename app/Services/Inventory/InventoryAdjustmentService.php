<?php

namespace App\Services\Inventory;

use App\Models\Inventory\InventoryAdjustment;
use App\Models\Inventory\InventoryAdjustmentLine;
use App\Models\Inventory\Item;
use App\Services\Inventory\InventoryStockService;
use App\Models\Inventory\InventoryAdjustment as Adjustment;
use App\Models\Inventory\InventoryAdjustmentLine as AdjustmentLine;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class InventoryAdjustmentService
{
    public function __construct(protected InventoryStockService $stockService) {}
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = InventoryAdjustment::query();

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('document_number', 'like', "%{$search}%")
                  ->orWhere('observation', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['type']) && in_array($filters['type'], ['entrada', 'salida'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }

        return $query->orderBy('date', 'desc')->orderBy('id', 'desc')->paginate($perPage);
    }

    public function findOrFail($id): InventoryAdjustment
    {
        return InventoryAdjustment::with(['lines.item', 'lines.warehouse'])->findOrFail($id);
    }

    public function nextNumber(): string
    {
        return DB::transaction(function () {
            $prefix = 'OL';
            $sequenceLength = 3;

            $lastNumber = InventoryAdjustment::whereNotNull('document_number')
                ->where('document_number', 'like', "{$prefix}-%")
                ->orderByDesc('id')
                ->lockForUpdate()
                ->value('document_number');

            $next = 1;
            if ($lastNumber && preg_match("/^{$prefix}-(\\d{{$sequenceLength}})$/", $lastNumber, $matches)) {
                $next = (int) $matches[1] + 1;
            }

            $sequence = str_pad((string) $next, $sequenceLength, '0', STR_PAD_LEFT);

            return "{$prefix}-{$sequence}";
        });
    }

    public function movementReasons(): array
    {
        return [
            'entrada' => 'entrada',
            'salida' => 'salida',
        ];
    }

    public function preparePayload(array $data): array
    {
        $movementReasons = $this->movementReasons();
        $data['type'] = $movementReasons[$data['movement_reason']] ?? $data['type'];
        $data['document_number'] = $data['document_number'] ?: null;

        [$subtotal, $iva] = $this->calculateTotals($data['lines']);
        $data['subtotal'] = $subtotal;
        $data['iva'] = $iva;
        $data['total'] = $subtotal + $iva;

        return $data;
    }

    private function calculateTotals(array $lines): array
    {
        $subtotal = 0;
        $ivaTotal = 0;

        foreach ($lines as $line) {
            $price = (float) ($line['price'] ?? 0);
            $qty = (int) ($line['quantity'] ?? 0);
            $tax = (float) ($line['tax'] ?? 0);

            $base = $price * $qty;
            $taxAmount = $base * $tax / 100;

            $subtotal += $base;
            $ivaTotal += $taxAmount;
        }

        return [$subtotal, $ivaTotal];
    }

    private function fetchItemsByLines(array $lines): Collection
    {
        return Item::with('warehouses')
            ->whereIn('id', collect($lines)->pluck('item_id'))
            ->get()
            ->keyBy('id');
    }

    public function createAdjustment(array $data): InventoryAdjustment
    {
        $data = $this->preparePayload($data);
        $items = $this->fetchItemsByLines($data['lines']);

        return $this->persistAdjustment($data, $items, null);
    }

    public function updateAdjustment($id, array $data): InventoryAdjustment
    {
        $adjustment = InventoryAdjustment::findOrFail($id);
        $data = $this->preparePayload($data);
        $items = $this->fetchItemsByLines($data['lines']);

        return $this->persistAdjustment($data, $items, $adjustment);
    }

    public function deleteAdjustment($id): void
    {
        $adjustment = InventoryAdjustment::findOrFail($id);

        DB::transaction(function () use ($adjustment) {
            foreach ($adjustment->lines as $line) {
                $item = Item::find($line->item_id);
                if ($item) {
                    $warehouseId = $this->stockService->resolveWarehouseId($item, $line->warehouse_id);
                    $reverseType = $adjustment->type === 'entrada' ? 'salida' : 'entrada';
                    $this->stockService->applyStockMovement($item, $warehouseId, (int) $line->quantity, $reverseType);
                }
            }

            $adjustment->lines()->delete();
            $adjustment->delete();
        });
    }

    private function persistAdjustment(array $data, Collection $items, ?InventoryAdjustment $adjustment = null): InventoryAdjustment
    {
        $movementReasons = $this->movementReasons();

        return DB::transaction(function () use ($data, $items, $adjustment, $movementReasons) {
            $documentNumber = $data['document_number'] ?: ($adjustment->document_number ?? $this->nextNumber());

            if (!$adjustment) {
                $adjustment = InventoryAdjustment::create([
                    'document_number'  => $documentNumber,
                    'date'             => $data['date'],
                    'type'             => $data['type'],
                    'movement_reason'  => $data['movement_reason'],
                    'observation'      => $data['observation'] ?? null,
                    'production_order' => $data['production_order'] ?? null,
                    'subtotal'         => $data['subtotal'],
                    'iva'              => $data['iva'],
                    'total'            => $data['total'],
                ]);
            } else {
                $this->revertStockForAdjustment($adjustment, $items);

                $adjustment->update([
                    'document_number'  => $documentNumber,
                    'date'             => $data['date'],
                    'type'             => $data['type'],
                    'movement_reason'  => $data['movement_reason'],
                    'observation'      => $data['observation'] ?? null,
                    'production_order' => $data['production_order'] ?? null,
                    'subtotal'         => $data['subtotal'],
                    'iva'              => $data['iva'],
                    'total'            => $data['total'],
                ]);

                $adjustment->lines()->delete();
            }

            $this->syncLinesAndStock($adjustment, $data['lines'], $items, $movementReasons);

            return $adjustment->load('lines.item');
        });
    }

    private function syncLinesAndStock(InventoryAdjustment $adjustment, array $lines, Collection $items, array $movementReasons): void
    {
        foreach ($lines as $line) {
            $item = $items[$line['item_id']] ?? null;
            if (!$item) {
                throw ValidationException::withMessages([
                    'lines' => ['No se encontro el item indicado.'],
                ]);
            }

            $warehouseId = $this->stockService->resolveWarehouseId($item, $line['warehouse_id'] ?? null);
            $lineMovementReason = $line['movement_reason'] ?? $adjustment->movement_reason ?? $adjustment->type;
            $lineType = $movementReasons[$lineMovementReason] ?? $lineMovementReason ?? 'entrada';

            $price = (float) ($line['price'] ?? 0);
            $qty   = (int) ($line['quantity'] ?? 0);
            $tax   = (float) ($line['tax'] ?? 0);
            $base = $price * $qty;
            $taxAmount = $base * $tax / 100;

            InventoryAdjustmentLine::create([
                'inventory_adjustment_id' => $adjustment->id,
                'item_id'                 => $line['item_id'],
                'warehouse_id'            => $warehouseId,
                'price'                   => $price,
                'tax'                     => $tax,
                'quantity'                => $qty,
                'description'             => $line['description'] ?? null,
                'line_total'              => $base + $taxAmount,
            ]);

            $this->stockService->applyStockMovement($item, $warehouseId, $qty, $lineType);
        }
    }

    private function revertStockForAdjustment(InventoryAdjustment $adjustment, Collection $items): void
    {
        foreach ($adjustment->lines as $line) {
            $item = $items[$line->item_id] ?? Item::find($line->item_id);
            if (!$item) {
                continue;
            }

            $warehouseId = $this->stockService->resolveWarehouseId($item, $line->warehouse_id);
            $reverseType = $adjustment->type === 'entrada' ? 'salida' : 'entrada';
            $this->stockService->applyStockMovement($item, $warehouseId, (int) $line->quantity, $reverseType);
        }
    }

    
}
