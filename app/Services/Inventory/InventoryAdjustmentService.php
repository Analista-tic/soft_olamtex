<?php

namespace App\Services\Inventory;

use App\Models\Inventory\InventoryAdjustment;
use App\Models\Inventory\InventoryAdjustmentLine;
use App\Models\MasterData\Item;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Inventory\InventoryStockService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class InventoryAdjustmentService
{
    public function __construct(
        protected InventoryStockService $stockService,
        protected InventoryMovementService $movementService,
    ) {}
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
            $this->createReversalMovements($adjustment);
            $adjustment->delete(); // soft delete
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
        $observation = trim((string) ($adjustment->observation ?? ''));
        $userId = Auth::id();
        if (!$userId) {
            throw ValidationException::withMessages([
                'lines' => ['Debe haber un usuario autenticado para registrar movimientos de inventario.'],
            ]);
        }

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
            $description = $line['description'] ?? null;

            InventoryAdjustmentLine::create([
                'inventory_adjustment_id' => $adjustment->id,
                'item_id'                 => $line['item_id'],
                'warehouse_id'            => $warehouseId,
                'price'                   => $price,
                'tax'                     => $tax,
                'quantity'                => $qty,
                'description'             => $description,
                'line_total'              => $base + $taxAmount,
            ]);

            $notes = 'Ajuste ' . ($adjustment->document_number ?? '') . ($observation !== '' ? ': ' . $observation : '');
            if ($description !== null && $description !== '') {
                $notes .= ' | ' . $description;
            }

            $payload = [
                'item_id' => $line['item_id'],
                'warehouse_id' => $warehouseId,
                'type' => $lineType,
                'quantity' => $qty,
                'date' => $adjustment->date->toDateString(),
                'source_type' => InventoryAdjustment::class,
                'source_id' => $adjustment->id,
                'notes' => $notes,
                'created_by_user_id' => $userId,
            ];

            if ($lineType === 'entrada') {
                if ($price <= 0) {
                    throw ValidationException::withMessages([
                        'lines' => ['El ajuste de entrada requiere precio unitario mayor a 0 en cada línea.'],
                    ]);
                }
                $payload['unit_price'] = $price;
            }

            $this->movementService->createMovement($payload);
        }
    }

    private function revertStockForAdjustment(InventoryAdjustment $adjustment, Collection $items): void
    {
        $this->createReversalMovements($adjustment);
    }

    /**
     * Crea movimientos inversos para anular el efecto en inventario de un ajuste.
     * Usa InventoryMovementService::createMovement (flujo centralizado).
     */
    private function createReversalMovements(InventoryAdjustment $adjustment): void
    {
        $userId = Auth::id();
        if (!$userId) {
            throw new \RuntimeException('Debe haber un usuario autenticado para anular ajustes.');
        }

        $docNum = $adjustment->document_number ?? 'N/A';
        $notes = 'Anulación de ajuste ' . $docNum;

        foreach ($adjustment->lines as $line) {
            $item = Item::find($line->item_id);
            if (!$item) {
                continue;
            }

            $warehouseId = $this->stockService->resolveWarehouseId($item, $line->warehouse_id);
            $reverseType = $adjustment->type === 'entrada' ? 'salida' : 'entrada';
            $qty = (int) $line->quantity;
            if ($qty <= 0) {
                continue;
            }

            $payload = [
                'item_id' => $line->item_id,
                'warehouse_id' => $warehouseId,
                'type' => $reverseType,
                'quantity' => $qty,
                'date' => $adjustment->date->toDateString(),
                'source_type' => InventoryAdjustment::class,
                'source_id' => $adjustment->id,
                'notes' => $notes,
                'created_by_user_id' => $userId,
            ];

            if ($reverseType === 'entrada') {
                $payload['unit_price'] = $this->movementService->getCurrentWeightedPrice($line->item_id, $warehouseId);
                if ($payload['unit_price'] <= 0) {
                    $payload['unit_price'] = (float) $line->price;
                }
            }

            $this->movementService->createMovement($payload);
        }
    }
}
