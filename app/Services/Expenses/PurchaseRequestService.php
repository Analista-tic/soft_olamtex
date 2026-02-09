<?php

namespace App\Services\Expenses;

use App\Models\Expenses\PurchaseRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class PurchaseRequestService
{
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = PurchaseRequest::with(['requester']);

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

        if (!empty($filters['date_from'])) {
            $query->where('date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('date', '<=', $filters['date_to']);
        }
        
        // Excluir solicitudes que ya tienen órdenes generadas o están procesadas
        if (!empty($filters['exclude_with_orders']) && $filters['exclude_with_orders']) {
            $query->whereDoesntHave('orders', function ($orderQuery) {
                $orderQuery->where('document_type', \App\Models\Expenses\PurchaseDocument::TYPE_ORDER)
                    ->whereNull('deleted_at');
            })
            ->where('status', '!=', \App\Enums\PurchaseRequestStatus::PROCESSED->value);
        }

        $paginator = $query->orderByDesc('date')->orderByDesc('id')->paginate($perPage);
        
        // Asegurar que items sea un array en cada resultado
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
        $payload = $this->prepareData($data, true);
        $purchaseRequest->update($payload);
        return $purchaseRequest->fresh('requester');
    }

    public function delete(PurchaseRequest $purchaseRequest): void
    {
        $purchaseRequest->delete();
    }

    public function loadWithRelations(PurchaseRequest $purchaseRequest): PurchaseRequest
    {
        return $purchaseRequest->load(['requester', 'approvedBy', 'rejectedBy', 'processor']);
    }

    /**
     * Enviar solicitud a aprobación (draft → pending)
     */
    public function sendToApproval(PurchaseRequest $purchaseRequest): PurchaseRequest
    {
        if (!$purchaseRequest->canSendToApproval()) {
            throw new \RuntimeException('No se puede enviar a aprobación desde el estado actual.');
        }

        $purchaseRequest->sendToApproval();
        return $purchaseRequest->fresh(['requester']);
    }

    /**
     * Aprobar solicitud (pending → approved)
     */
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

    /**
     * Rechazar solicitud (pending → rejected)
     */
    public function reject(PurchaseRequest $purchaseRequest, string $reason): PurchaseRequest
    {
        if (!$purchaseRequest->canReject()) {
            throw new \RuntimeException('No se puede rechazar desde el estado actual.');
        }

        $purchaseRequest->reject(Auth::id(), $reason);
        return $purchaseRequest->fresh(['requester', 'rejectedBy']);
    }

    /**
     * Cancelar solicitud (draft/pending → cancelled)
     */
    public function cancel(PurchaseRequest $purchaseRequest): PurchaseRequest
    {
        if (!$purchaseRequest->canCancel()) {
            throw new \RuntimeException('No se puede cancelar desde el estado actual.');
        }

        $purchaseRequest->cancel();
        return $purchaseRequest->fresh(['requester']);
    }

    /**
     * Marcar como procesada (compatibilidad - ahora es approved)
     * @deprecated Usar approve() en su lugar
     */
    public function markAsProcessed(PurchaseRequest $request, int $userId): void
    {
        $request->approve($userId);
    }

    protected function prepareData(array $data, bool $isUpdate = false): array
    {
        if (!$isUpdate && empty($data['request_number'])) {
            $data['request_number'] = $this->generateRequestNumber();
        }

        // Por defecto, las nuevas solicitudes se crean en estado 'draft'
        $data['status'] = $data['status'] ?? 'draft';
        $data['requester_user_id'] = $data['requester_user_id'] ?? Auth::id();

        // Manejar items: si viene como string JSON, decodificar y volver a codificar para validar
        // Si viene como array, codificar a JSON
        $items = null;
        if (isset($data['items'])) {
            if (is_string($data['items'])) {
                // Ya viene como JSON string, validar que sea válido
                $decoded = json_decode($data['items'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $items = $decoded;
                    $data['items'] = json_encode($decoded);
                } else {
                    $data['items'] = null;
                }
            } elseif (is_array($data['items'])) {
                $items = $data['items'];
                $data['items'] = json_encode($data['items']);
            } else {
                $data['items'] = null;
            }
        }

        // Calcular monto estimado desde los items
        if ($items && is_array($items) && count($items) > 0) {
            $data['estimated_amount'] = array_reduce($items, function ($sum, $item) {
                $total = is_numeric($item['total'] ?? 0) ? (float) $item['total'] : 0;
                return $sum + $total;
            }, 0.0);
        } else {
            $data['estimated_amount'] = null;
        }

        return $data;
    }

    public function handleDocumentUpload(?UploadedFile $file, ?string $existingPath = null): ?string
    {
        // Eliminar archivo anterior si existe
        if ($existingPath && Storage::disk('public')->exists($existingPath)) {
            Storage::disk('public')->delete($existingPath);
        }

        if (!$file) {
            return $existingPath;
        }

        // Validar tipo de archivo
        $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'application/pdf'];
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            throw new \InvalidArgumentException('Tipo de archivo no permitido. Solo se permiten imágenes (JPEG, PNG, WebP) y PDF.');
        }

        // Generar ruta única
        $extension = $file->getClientOriginalExtension();
        $path = sprintf('purchase-requests/documents/%s/%s.%s', 
            now()->format('Y/m/d'), 
            \Illuminate\Support\Str::uuid(), 
            $extension
        );

        // Guardar archivo
        Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));

        return $path;
    }

    protected function generateRequestNumber(): string
    {
        $nextId = (int) PurchaseRequest::withTrashed()->max('id') + 1;
        return sprintf('SOL-%03d', $nextId);
    }
}
