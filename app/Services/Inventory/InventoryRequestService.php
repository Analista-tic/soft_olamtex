<?php

namespace App\Services\Inventory;

use App\Helpers\ScopeHelper;
use App\Models\Inventory\InventoryRequest;
use App\Models\Inventory\InventoryRequestItem;
use App\Support\DocumentSequence;
use App\Models\Settings\User;
use App\Notifications\InventoryRequestCreatedNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class InventoryRequestService
{
    public function __construct(protected InventoryMovementService $movementService) {}

    public function paginate(array $filters, int $perPage, ?User $user): LengthAwarePaginator
    {
        $query = InventoryRequest::with(['items.item', 'requester']);
        ScopeHelper::apply($query, $user, 'ver propios solicitudes insumos', 'ver area solicitudes insumos', 'ver todos solicitudes insumos');

        $sortBy = $filters['sort_by'] ?? 'date';
        $sortDir = strtolower((string) ($filters['sort_dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['date', 'delivery_date'];
        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'date';
        }

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

        return $query
            ->orderBy($sortBy, $sortDir)
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findOrFail(int $id): InventoryRequest
    {
        return InventoryRequest::with(['items.item', 'items.warehouse', 'requester', 'approvedBy', 'rejectedBy'])
            ->findOrFail($id);
    }

    public function create(array $data, User $user): InventoryRequest
    {
        $inventoryRequest = DB::transaction(function () use ($data, $user) {
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

        // Enviar notificación a usuarios con permiso atender solicitudes insumos
        $this->notifyInventoryRequestCreated($inventoryRequest, $user);

        return $inventoryRequest;
    }

    /**
     * Notifica a todos los usuarios (excepto creador) sobre una nueva solicitud.
     */
    protected function notifyInventoryRequestCreated(InventoryRequest $request, User $creator): void
    {
        $requesterName = $creator->name ?? null;
        $requesterArea = $this->resolveUserArea($creator);

        app(\App\Services\Settings\NotificationDispatcher::class)->dispatch(
            'inventory_request_created',
            new InventoryRequestCreatedNotification($request, $requesterName, $requesterArea),
            ['actor' => $creator]
        );
    }

    /**
     * Resuelve los destinatarios para notificaciones de solicitudes de inventario.
     * Incluye todos los usuarios excepto quien crea la solicitud.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    protected function resolveRecipientsForInventoryRequest(User $excludeUser): \Illuminate\Database\Eloquent\Collection
    {
        return User::role('logistica')
            ->where('id', '!=', $excludeUser->id)
            ->get();
    }

    /**
     * Resuelve el área del usuario a partir de su área asignada.
     */
    protected function resolveUserArea(User $user): ?string
    {
        if (!$user->area_id) {
            return null;
        }

        return \App\Models\Settings\Area::query()->whereKey($user->area_id)->value('name');
    }

    /**
     * Marca como leídas todas las notificaciones asociadas a una solicitud de inventario.
     * Se usa cuando el status cambia de 'solicitado' a otro.
     */
    protected function markNotificationsAsReadForRequest(int $requestId): void
    {
        DatabaseNotification::query()
            ->where('notifiable_type', User::class)
            ->whereNull('read_at')
            ->whereRaw("JSON_EXTRACT(data, '$.request_id') = ?", [$requestId])
            ->update(['read_at' => now()]);
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
        $hasMissingItems = $request->items()->whereNull('item_id')->exists();
        if ($hasMissingItems) {
            throw ValidationException::withMessages([
                'items' => ['No se puede entregar la solicitud porque tiene lineas sin item asociado.'],
            ]);
        }

        $deliveredRequest = DB::transaction(function () use ($request, $payload, $user) {
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

        // Marcar notificaciones como leídas al cambiar de status
        $this->markNotificationsAsReadForRequest($request->id);

        return $deliveredRequest;
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

        // Marcar notificaciones como leídas al cambiar de status
        $this->markNotificationsAsReadForRequest($request->id);

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
                'image_path' => $item['image_path'] ?? null,
            ]);
        }
    }

    public function handleItemImageUpload(?UploadedFile $file, ?string $existingPath = null): ?string
    {
        if (!$file) {
            return $existingPath;
        }

        $this->deleteStoredPublicFile($existingPath);

        return $file->store('inventory-requests/items', 'public');
    }

    public function deleteStoredPublicFile(?string $path): void
    {
        if (!$path) {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /** Consecutivo atómico (SINT-###): se asigna al guardar; el formulario ya no "sugiere" uno. */
    protected function generateDocumentNumber(): string
    {
        $next = DocumentSequence::next(
            'inventory_requests:SINT',
            fn () => DocumentSequence::maxInTable('inventory_requests', 'document_number', 'SINT')
        );

        return sprintf('SINT-%03d', $next);
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
