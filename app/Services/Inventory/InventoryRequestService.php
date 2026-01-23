<?php

namespace App\Services\Inventory;

use App\Models\Inventory\InventoryRequest;
use App\Models\Inventory\InventoryRequestItem;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryRequestService
{
    public function __construct(protected InventoryMovementService $movementService) {}

    public function paginate(array $filters, int $perPage, ?User $user, bool $isLogistics): LengthAwarePaginator
    {
        $query = InventoryRequest::with(['items.item', 'requester'])
            ->when(!$isLogistics && $user, fn ($q) => $q->where('created_by_user_id', $user->id));

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('document_number', 'like', "%{$search}%")
                    ->orWhere('observation', 'like', "%{$search}%")
                    ->orWhereHas('requester', fn ($sub) =>
                        $sub->where('name', 'like', "%{$search}%")
                    );
            });
        }

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }

        return $query->orderByDesc('date')->orderByDesc('id')->paginate($perPage);
    }

    public function findOrFail(int $id): InventoryRequest
    {
        return InventoryRequest::with(['items.item', 'items.warehouse', 'requester', 'approvedBy', 'rejectedBy'])
            ->findOrFail($id);
    }

    public function create(array $data, User $user): InventoryRequest
    {
        return DB::transaction(function () use ($data, $user) {
            $documentNumber = $this->generateDocumentNumber();

            $imagePath = null;
            if (isset($data['image']) && $data['image'] instanceof \Illuminate\Http\UploadedFile) {
                $imagePath = $data['image']->store('inventory_requests', 'public');
            }

            $request = InventoryRequest::create([
                'document_number' => $documentNumber,
                'date' => $data['date'],
                'delivery_date' => $data['delivery_date'] ?? null,
                'observation' => $data['observation'] ?? null,
                'status' => InventoryRequest::STATUS_REQUESTED,
                'created_by_user_id' => $user->id,
                'image' => $imagePath,
            ]);

            $this->syncItems($request, $data['items'] ?? []);

            return $this->findOrFail($request->id);
        });
    }

    public function update(InventoryRequest $request, array $data, ?array $items = null): InventoryRequest
    {
        $this->assertPending($request);

        return DB::transaction(function () use ($request, $data, $items) {
            $updateData = [
                'date' => $data['date'] ?? $request->date,
                'delivery_date' => $data['delivery_date'] ?? $request->delivery_date,
                'observation' => $data['observation'] ?? $request->observation,
            ];
            if (isset($data['image']) && $data['image'] instanceof \Illuminate\Http\UploadedFile) {
                $updateData['image'] = $data['image']->store('inventory_requests', 'public');
            }
            $request->update($updateData);

            if ($items !== null) {
                $this->syncItems($request, $items);
            }

            return $this->findOrFail($request->id);
        });
    }

    public function deliver(InventoryRequest $request, array $payload, User $user): InventoryRequest
    {
        $this->assertPending($request);

        return DB::transaction(function () use ($request, $payload, $user) {
            $deliveryDate = $payload['delivery_date'] ?? now()->toDateString();
            $itemsPayload = $payload['items'] ?? [];
            $items = $request->items()->get()->keyBy('id');

            if (count($itemsPayload) !== $items->count()) {
                throw ValidationException::withMessages([
                    'items' => ['Debe entregar todas las lineas de la solicitud.'],
                ]);
            }

            foreach ($itemsPayload as $line) {
                $lineId = (int) ($line['id'] ?? 0);
                $itemLine = $items->get($lineId);
                if (!$itemLine) {
                    throw ValidationException::withMessages([
                        'items' => ['Linea no valida para la solicitud.'],
                    ]);
                }

                if (!$itemLine->item_id) {
                    throw ValidationException::withMessages([
                        'items' => ['Debe asociar un item existente antes de entregar.'],
                    ]);
                }

                $deliveredQty = (float) ($line['delivered_quantity'] ?? 0);
                if ($deliveredQty <= 0) {
                    continue;
                }

                if ($deliveredQty > (float) $itemLine->requested_quantity) {
                    throw ValidationException::withMessages([
                        'items' => ["La cantidad entregada supera la solicitada para el item {$itemLine->item_id}."],
                    ]);
                }

                $warehouseId = (int) ($line['warehouse_id'] ?? 0);
                if ($warehouseId <= 0) {
                    throw ValidationException::withMessages([
                        'items' => ['Debe seleccionar una bodega para cada item.'],
                    ]);
                }

                $stockRow = DB::table('warehouse_items')
                    ->where('item_id', $itemLine->item_id)
                    ->where('warehouse_id', $warehouseId)
                    ->lockForUpdate()
                    ->first();

                if (!$stockRow) {
                    throw ValidationException::withMessages([
                        'items' => ["El item {$itemLine->item_id} no tiene bodega asociada."],
                    ]);
                }

                $availableStock = (float) $stockRow->quantity;

                if ($availableStock < $deliveredQty) {
                    throw ValidationException::withMessages([
                        'items' => ["Stock insuficiente para el item {$itemLine->item_id}. Disponible: {$availableStock}."],
                    ]);
                }

                $itemLine->update([
                    'delivered_quantity' => $deliveredQty,
                    'warehouse_id' => $warehouseId,
                ]);

                $this->movementService->createMovement([
                    'item_id' => $itemLine->item_id,
                    'warehouse_id' => $warehouseId,
                    'source_type' => InventoryRequest::class,
                    'source_id' => $request->id,
                    'type' => 'salida',
                    'date' => $deliveryDate,
                    'quantity' => $deliveredQty,
                    'notes' => sprintf('Solicitud interna %s', $request->document_number ?? $request->id),
                    'created_by_user_id' => $user->id,
                ]);
            }

            $request->update([
                'delivery_date' => $request->delivery_date ?: $deliveryDate,
                'status' => InventoryRequest::STATUS_DELIVERED,
                'approved_by_user_id' => $user->id,
            ]);

            return $this->findOrFail($request->id);
        });
    }

    public function reject(InventoryRequest $request, string $reason, User $user): InventoryRequest
    {
        $this->assertPending($request);

        $request->update([
            'status' => InventoryRequest::STATUS_REJECTED,
            'rejected_by_user_id' => $user->id,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return $this->findOrFail($request->id);
    }

    protected function syncItems(InventoryRequest $request, array $items): void
    {
        $request->items()->delete();

        foreach ($items as $item) {
            $itemId = $item['item_id'] ?? null;
            $itemName = isset($item['item_name']) ? trim((string) $item['item_name']) : '';
            $quantity = $item['quantity'] ?? $item['requested_quantity'] ?? null;
            if ((!$itemId && $itemName === '') || !$quantity) {
                continue;
            }

            InventoryRequestItem::create([
                'inventory_request_id' => $request->id,
                'item_id' => $itemId ? (int) $itemId : null,
                'item_name' => $itemName !== '' ? $itemName : null,
                'requested_quantity' => (float) $quantity,
            ]);
        }
    }

    public function nextNumber(): string
    {
        return $this->generateDocumentNumber();
    }

    protected function generateDocumentNumber(): string
    {
        $prefix = 'SINT';
        $sequenceLength = 3;

        $lastNumber = InventoryRequest::whereNotNull('document_number')
            ->orderByDesc('id')
            ->value('document_number');

        $next = 1;
        if ($lastNumber && preg_match("/^{$prefix}-(\\d{{$sequenceLength}})$/", $lastNumber, $matches)) {
            $next = (int) $matches[1] + 1;
        }

        $sequence = str_pad((string) $next, $sequenceLength, '0', STR_PAD_LEFT);

        return "{$prefix}-{$sequence}";
    }

    protected function assertPending(InventoryRequest $request): void
    {
        if ($request->status !== InventoryRequest::STATUS_REQUESTED) {
            throw ValidationException::withMessages([
                'status' => ['Solo se puede modificar una solicitud en estado solicitado.'],
            ]);
        }
    }
}
