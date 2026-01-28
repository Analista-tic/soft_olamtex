<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Item;
use App\Models\Inventory\WarehouseTransfer;
use App\Models\Inventory\WarehouseTransferItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WarehouseTransferService
{
    public function __construct(protected InventoryMovementService $movementService) {}

    public function create(array $data, User $user): WarehouseTransfer
    {
        return DB::transaction(function () use ($data, $user) {
            $documentNumber = $this->generateDocumentNumber();

            $transfer = WarehouseTransfer::create([
                'document_number' => $documentNumber,
                'date' => $data['date'],
                'origin_warehouse_id' => $data['origin_warehouse_id'],
                'destination_warehouse_id' => $data['destination_warehouse_id'],
                'observation' => $data['observation'] ?? null,
                'created_by_user_id' => $user->id,
            ]);

            $items = $this->fetchItems($data['items']);
            $this->assertStockAvailability($items, $data['items'], (int) $data['origin_warehouse_id']);

            foreach ($data['items'] as $line) {
                $itemId = (int) $line['item_id'];
                $quantity = (float) $line['quantity'];
                $item = $items->get($itemId);

                if (!$item) {
                    throw ValidationException::withMessages([
                        'items' => ["El item {$itemId} no existe."],
                    ]);
                }

                WarehouseTransferItem::create([
                    'warehouse_transfer_id' => $transfer->id,
                    'item_id' => $itemId,
                    'quantity' => $quantity,
                ]);

                $notes = sprintf('Transferencia %s', $transfer->document_number ?? $transfer->id);

                $this->movementService->createMovement([
                    'item_id' => $itemId,
                    'warehouse_id' => (int) $data['origin_warehouse_id'],
                    'source_type' => WarehouseTransfer::class,
                    'source_id' => $transfer->id,
                    'type' => 'salida',
                    'date' => $data['date'],
                    'quantity' => $quantity,
                    'notes' => $notes,
                    'created_by_user_id' => $user->id,
                ]);

                $this->movementService->createMovement([
                    'item_id' => $itemId,
                    'warehouse_id' => (int) $data['destination_warehouse_id'],
                    'source_type' => WarehouseTransfer::class,
                    'source_id' => $transfer->id,
                    'type' => 'entrada',
                    'date' => $data['date'],
                    'quantity' => $quantity,
                    'notes' => $notes,
                    'created_by_user_id' => $user->id,
                ]);
            }

            return $transfer->load(['originWarehouse', 'destinationWarehouse', 'items.item']);
        });
    }

    private function fetchItems(array $lines): Collection
    {
        $ids = collect($lines)
            ->pluck('item_id')
            ->filter()
            ->unique()
            ->values();

        return Item::whereIn('id', $ids)->get()->keyBy('id');
    }

    private function assertStockAvailability(Collection $items, array $lines, int $originWarehouseId): void
    {
        $totals = collect($lines)
            ->groupBy('item_id')
            ->map(fn (Collection $rows) => $rows->sum(fn ($row) => (float) $row['quantity']));

        foreach ($totals as $itemId => $quantity) {
            $item = $items->get((int) $itemId);
            if (!$item) {
                throw ValidationException::withMessages([
                    'items' => ["El item {$itemId} no existe."],
                ]);
            }

            $row = DB::table('warehouse_items')
                ->where('item_id', $itemId)
                ->where('warehouse_id', $originWarehouseId)
                ->lockForUpdate()
                ->first();

            $available = $row ? (float) $row->quantity : 0.0;

            if ($available < $quantity) {
                throw ValidationException::withMessages([
                    'items' => [
                        sprintf(
                            'Stock insuficiente para el item %s. Disponible: %s.',
                            $item->name,
                            $available
                        ),
                    ],
                ]);
            }
        }
    }

    private function generateDocumentNumber(): string
    {
        $prefix = 'TR';
        $sequenceLength = 3;

        $lastNumber = WarehouseTransfer::whereNotNull('document_number')
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
    }
}
