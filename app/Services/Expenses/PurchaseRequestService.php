<?php

namespace App\Services\Expenses;

use App\Helpers\ScopeHelper;
use App\Models\Expenses\PurchaseRequest;
use App\Models\Settings\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PurchaseRequestService
{
    public function paginate(array $filters, int $perPage = 20, ?Request $request = null, ?User $user = null): LengthAwarePaginator
    {
        $query = PurchaseRequest::with(['requester']);
        ScopeHelper::apply($query, $user, 'ver propios solicitudes compra', 'ver area solicitudes compra', 'ver todos solicitudes compra', 'requester_user_id');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($sub) use ($search) {
                $sub->where('request_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('provider_name', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['provider_name']) && $filters['provider_name'] !== 'all') {
            $query->where('provider_name', 'like', "%{$filters['provider_name']}%");
        }


        if (!empty($filters['date_from']) && !empty($filters['date_to']) && $filters['date_from'] === $filters['date_to']) {
            $query->whereDate('date', $filters['date_from']);
        } elseif (!empty($filters['date_from']) || !empty($filters['date_to'])) {
            if (!empty($filters['date_from'])) {
                $query->whereDate('date', '>=', $filters['date_from']);
            }
            if (!empty($filters['date_to'])) {
                $query->whereDate('date', '<=', $filters['date_to']);
            }
        }

        if (!empty($filters['exclude_with_orders']) && $filters['exclude_with_orders']) {
            $query->whereDoesntHave('orders', function ($orderQuery) {
                $orderQuery->where('document_type', \App\Models\Expenses\PurchaseDocument::TYPE_ORDER)
                    ->whereNull('deleted_at');
            })->where('status', '!=', \App\Enums\PurchaseRequestStatus::PROCESSED->value);
        }

        $allowedSorts = ['date', 'status', 'total'];
        $sortBy = in_array($request?->get('sort_by'), $allowedSorts, true)
            ? (string) $request->get('sort_by')
            : 'date';
        $sortDir = $request?->get('sort_dir') === 'asc' ? 'asc' : 'desc';

        if ($sortBy === 'total') {
            $query->orderByRaw('COALESCE(estimated_amount, 0) ' . $sortDir)->orderByDesc('id');
        } else {
            $query->orderBy($sortBy, $sortDir)->orderByDesc('id');
        }

        $paginator = $query->paginate($perPage);

        $paginator->getCollection()->transform(function ($request) {
            if (is_string($request->items)) {
                $decoded = json_decode($request->items, true);
                $request->items = is_array($decoded) ? $decoded : [];
            } elseif (!is_array($request->items)) {
                $request->items = [];
            }

            return $request;
        });

        return $paginator;
    }

    public function create(array $data): PurchaseRequest
    {
        $payload = $this->prepareData($data);
        return PurchaseRequest::create($payload)->load('requester');
    }

    public function update(PurchaseRequest $purchaseRequest, array $data): PurchaseRequest
    {
        $existingItems = $this->extractItems($purchaseRequest->items);
        $payload = $this->prepareData($data, true);
        $this->deleteRemovedItemImages($existingItems, $data['items'] ?? []);
        $purchaseRequest->update($payload);

        return $purchaseRequest->fresh('requester');
    }

    public function delete(PurchaseRequest $purchaseRequest): void
    {
        $this->deleteAllItemImages($this->extractItems($purchaseRequest->items));
        $purchaseRequest->delete();
    }

    public function loadWithRelations(PurchaseRequest $purchaseRequest): PurchaseRequest
    {
        return $purchaseRequest->load(['requester', 'approvedBy', 'rejectedBy', 'processor']);
    }

    public function sendToApproval(PurchaseRequest $purchaseRequest): PurchaseRequest
    {
        if (!$purchaseRequest->canSendToApproval()) {
            throw new \RuntimeException('No se puede enviar a aprobacion desde el estado actual.');
        }

        $purchaseRequest->sendToApproval();

        return $purchaseRequest->fresh(['requester']);
    }

    public function approve(PurchaseRequest $purchaseRequest, ?string $reason = null): PurchaseRequest
    {
        if (!$purchaseRequest->canApprove()) {
            throw new \RuntimeException('No se puede aprobar desde el estado actual.');
        }

        $userId = Auth::id();
        if (!$userId) {
            throw new \RuntimeException('Usuario no autenticado.');
        }

        $purchaseRequest->approve($userId, $reason);

        return $purchaseRequest->fresh(['requester', 'approvedBy']);
    }

    public function reject(PurchaseRequest $purchaseRequest, string $reason): PurchaseRequest
    {
        if (!$purchaseRequest->canReject()) {
            throw new \RuntimeException('No se puede rechazar desde el estado actual.');
        }

        $purchaseRequest->reject(Auth::id(), $reason);

        return $purchaseRequest->fresh(['requester', 'rejectedBy']);
    }

    public function cancel(PurchaseRequest $purchaseRequest): PurchaseRequest
    {
        if (!$purchaseRequest->canCancel()) {
            throw new \RuntimeException('No se puede cancelar desde el estado actual.');
        }

        $itemsWithoutImages = $this->clearItemImages($this->extractItems($purchaseRequest->items));
        $purchaseRequest->update([
            'items' => json_encode($itemsWithoutImages),
        ]);

        $purchaseRequest->cancel();

        return $purchaseRequest->fresh(['requester']);
    }

    public function markAsProcessed(PurchaseRequest $request, int $userId): void
    {
        $request->approve($userId);
    }

    protected function prepareData(array $data, bool $isUpdate = false): array
    {
        if (!$isUpdate && empty($data['request_number'])) {
            $data['request_number'] = $this->generateRequestNumber();
        }

        $data['status'] = $data['status'] ?? 'draft';
        $data['requester_user_id'] = $data['requester_user_id'] ?? Auth::id();

        $items = null;
        if (isset($data['items'])) {
            if (is_string($data['items'])) {
                $decoded = json_decode($data['items'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $items = $decoded;
                    $data['items'] = $this->safeJsonEncode($decoded);
                } else {
                    $data['items'] = null;
                }
            } elseif (is_array($data['items'])) {
                $items = $data['items'];
                $data['items'] = $this->safeJsonEncode($data['items']);
            } else {
                $data['items'] = null;
            }
        }

        if ($items && is_array($items) && count($items) > 0) {
            $data['estimated_amount'] = array_reduce($items, function ($sum, $item) {
                $total = is_numeric($item['total'] ?? 0) ? (float) $item['total'] : 0;
                $totalTax = is_numeric($item['total_tax'] ?? 0) ? (float) ($item['total_tax'] ?? 0) : 0;
                return $sum + $total + $totalTax;
            }, 0.0);
        } else {
            $data['estimated_amount'] = null;
        }

        return $data;
    }

    protected function safeJsonEncode(array $value): ?string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        if ($encoded === false) {
            throw new \RuntimeException('No fue posible serializar los items de la solicitud.');
        }

        return $encoded;
    }

    public function handleItemImageUpload(?UploadedFile $file, ?string $existingPath = null): ?string
    {
        if (!$file) {
            return $existingPath;
        }

        $this->deleteStoredPublicFile($existingPath);

        return $file->store('purchase-requests/items', 'public');
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

    protected function generateRequestNumber(): string
    {
        $nextId = (int) PurchaseRequest::withTrashed()->max('id') + 1;
        return sprintf('SOL-%03d', $nextId);
    }

    protected function extractItems(mixed $items): array
    {
        if (is_string($items)) {
            $decoded = json_decode($items, true);
            return json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : [];
        }

        return is_array($items) ? $items : [];
    }

    protected function deleteRemovedItemImages(array $existingItems, array $newItems): void
    {
        $newKeys = [];

        foreach (array_values($newItems) as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $newKeys[] = $this->resolveItemKey($item, $index, null);
        }

        foreach (array_values($existingItems) as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $existingKey = $this->resolveItemKey($item, $index, null);
            if (in_array($existingKey, $newKeys, true)) {
                continue;
            }

            $this->deleteStoredPublicFile($item['image_path'] ?? null);
        }
    }

    protected function deleteAllItemImages(array $items): void
    {
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $this->deleteStoredPublicFile($item['image_path'] ?? null);
        }
    }

    protected function clearItemImages(array $items): array
    {
        return array_values(array_map(function ($item) {
            if (!is_array($item)) {
                return [];
            }

            $this->deleteStoredPublicFile($item['image_path'] ?? null);
            $item['image_path'] = null;

            return $item;
        }, $items));
    }

    protected function resolveItemKey(array $item, int $index, ?int $purchaseRequestId): string
    {
        $uid = isset($item['uid']) && is_string($item['uid']) ? trim($item['uid']) : '';

        if ($uid !== '') {
            return $uid;
        }

        return sprintf('legacy-%s-%s', $purchaseRequestId ?? 'request', $index);
    }
}
