<?php

namespace App\Services\Expenses;

use App\Helpers\ScopeHelper;
use App\Models\Expenses\PurchaseDocument;
use App\Models\Expenses\PurchaseRequest;
use App\Models\Settings\User;
use App\Enums\PurchaseRequestStatus;
use App\Notifications\PurchaseRequestSubmittedNotification;
use App\Notifications\PurchaseRequestRespondedNotification;
use Illuminate\Support\Collection;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class PurchaseRequestService
{
    public function __construct(private PurchaseDocumentOrderService $orderService) {}

    public function getApprovedOptionsForOrderSelect(?User $user, int $limit = 200, string $search = '', bool $myRequestsOnly = false): array
    {
        $safeLimit = max(1, min($limit, 500));
        $normalizedSearch = trim($search);

        $query = PurchaseRequest::query()
            ->select(['purchase_requests.id', 'purchase_requests.request_number', 'purchase_requests.provider_name', 'purchase_requests.created_at', 'purchase_requests.requester_user_id'])
            ->leftJoin('users', 'users.id', '=', 'purchase_requests.requester_user_id')
            ->addSelect('users.name as requester_name')
            ->where('purchase_requests.status', PurchaseRequestStatus::APPROVED->value)
            ->whereNotExists(function ($subQuery) {
                $subQuery->selectRaw('1')
                    ->from('purchase_documents as pd')
                    ->whereColumn('pd.purchase_request_id', 'purchase_requests.id')
                    ->where('pd.document_type', PurchaseDocument::TYPE_ORDER)
                    ->where('pd.status', '!=', 'pending')
                    ->whereNull('pd.deleted_at');
            });

        ScopeHelper::apply($query, $user, 'ver propios solicitudes compra', 'ver area solicitudes compra', 'ver todos solicitudes compra', 'purchase_requests.requester_user_id');
        $this->applySolicitedToFilter($query, $user);

        if ($myRequestsOnly && $user !== null) {
            $query->where('purchase_requests.buyer_user_id', $user->id);
        }

        if ($normalizedSearch !== '') {
            $query->where(function (Builder $subQuery) use ($normalizedSearch) {
                $subQuery->where('purchase_requests.request_number', 'like', "%{$normalizedSearch}%")
                    ->orWhere('purchase_requests.provider_name', 'like', "%{$normalizedSearch}%")
                    ->orWhere('users.name', 'like', "%{$normalizedSearch}%");
            });
        }

        /** @var Collection<int, PurchaseRequest> $rows */
        $rows = $query
            ->orderByDesc('id')
            ->limit($safeLimit)
            ->get();

        return $rows->map(static function (PurchaseRequest $request): array {
            return [
                'id' => $request->id,
                'request_number' => $request->request_number,
                'provider_name' => $request->provider_name,
                'requester_name' => $request->requester_name ?? null,
                'created_at' => $request->created_at?->toISOString(),
                'status' => PurchaseRequestStatus::APPROVED->value,
            ];
        })->all();
    }

    public function paginate(array $filters, int $perPage = 20, ?Request $request = null, ?User $user = null): LengthAwarePaginator
    {
        $minimal = !empty($filters['minimal']);

        $query = PurchaseRequest::with($minimal ? [] : ['requester', 'buyer']);

        if ($minimal) {
            $query->select(['id', 'request_number', 'provider_name', 'date', 'status', 'description', 'estimated_amount', 'requester_user_id']);
        }

        $this->applyVisibility($query, $user);

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
                    ->where('status', '!=', 'pending')
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

        if (!$minimal) {
            $paginator->getCollection()->transform(function ($request) {
                if (is_string($request->items)) {
                    $decoded = json_decode($request->items, true);
                    $request->items = is_array($decoded) ? $decoded : [];
                } elseif (!is_array($request->items)) {
                    $request->items = [];
                }

                return $request;
            });
        }

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
        return $purchaseRequest->load(['requester', 'buyer', 'approvedBy', 'rejectedBy', 'processor']);
    }

    public function findAccessibleOrFail(PurchaseRequest $purchaseRequest, ?User $user): PurchaseRequest
    {
        $query = PurchaseRequest::query()->whereKey($purchaseRequest->getKey());
        $this->applyVisibility($query, $user);

        if (!$query->exists()) {
            throw new AuthorizationException('No tienes permisos para acceder a esta solicitud de compra.');
        }

        return $this->loadWithRelations($purchaseRequest);
    }

    public function sendToApproval(PurchaseRequest $purchaseRequest): PurchaseRequest
    {
        if (!$purchaseRequest->canSendToApproval()) {
            throw new \RuntimeException('No se puede enviar a aprobacion desde el estado actual.');
        }

        $purchaseRequest->sendToApproval();

        $this->notifySubmitted($purchaseRequest);

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

        $this->notifyResponded($purchaseRequest, 'approved', Auth::user(), $reason);

        return $purchaseRequest->fresh(['requester', 'approvedBy']);
    }

    public function reject(PurchaseRequest $purchaseRequest, string $reason): PurchaseRequest
    {
        if (!$purchaseRequest->canReject()) {
            throw new \RuntimeException('No se puede rechazar desde el estado actual.');
        }

        $purchaseRequest->reject(Auth::id(), $reason);

        $this->notifyResponded($purchaseRequest, 'rejected', Auth::user(), $reason);

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

        $this->notifyResponded($purchaseRequest, 'cancelled', Auth::user(), null);

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
        // El solicitante es siempre quien crea la solicitud; se ignora cualquier valor enviado por el cliente.
        unset($data['requester_user_id']);
        if (!$isUpdate) {
            $data['requester_user_id'] = Auth::id();
        }

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

    public function convertToOrder(PurchaseRequest $purchaseRequest): \App\Models\Expenses\PurchaseDocument
    {
        if (!$purchaseRequest->isApproved()) {
            throw new \RuntimeException('Solo se pueden convertir a orden las solicitudes con estado "Aprobada".');
        }

        $rawItems = $this->extractItems($purchaseRequest->items);

        $orderItems = array_map(fn($item) => [
            'item_id'     => $item['item_id'] ?? null,
            'item_name'   => $item['name'] ?? $item['item_name'] ?? null,
            'quantity'    => (float) ($item['quantity'] ?? 1),
            'price'       => (float) ($item['price'] ?? 0),
            'total'       => (float) ($item['total'] ?? 0),
            'total_tax'   => (float) ($item['total_tax'] ?? 0),
            'description' => $item['description'] ?? null,
        ], $rawItems);

        $data = [
            'provider_name'       => $purchaseRequest->provider_name,
            'date'                => $purchaseRequest->date ? Carbon::parse((string) $purchaseRequest->date)->toDateString() : now()->toDateString(),
            'description'         => $purchaseRequest->description,
            'purchase_request_id' => $purchaseRequest->id,
            'buyer_user_id'       => $purchaseRequest->buyer_user_id,
            'status'              => 'pending',
        ];

        return $this->orderService->createOrder($data, $orderItems);
    }

    /**
     * Reglas de visibilidad (ver docs/compras/solicitudes-visibilidad.md):
     *  1. Alcance normal por permisos (propios / area / todos) + filtro "solicitado a".
     *  2. Los borradores solo los ve su solicitante (quien la creó).
     */
    private function applyVisibility(Builder $query, ?User $user): void
    {
        ScopeHelper::apply($query, $user, 'ver propios solicitudes compra', 'ver area solicitudes compra', 'ver todos solicitudes compra', 'requester_user_id');
        $this->applySolicitedToFilter($query, $user);

        $userId = $user?->id ?? -1;
        $query->where(function (Builder $q) use ($userId) {
            $q->where('status', '!=', 'draft')->orWhere('requester_user_id', $userId);
        });
    }

    private function applySolicitedToFilter(Builder $query, ?User $user): void
    {
        if ($user === null) {
            return;
        }

        try {
            if (!$user->hasPermissionTo('ver todos solicitudes compra')) {
                return;
            }
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist) {
            return;
        }

        $userId = $user->id;

        if ($user->hasRole('logistica')) {
            $query->where(function (Builder $q) use ($userId) {
                // Siempre ve las propias (cualquier estado)
                $q->where('requester_user_id', $userId)
                // O las dirigidas a logistica de otros, solo si no son borrador
                  ->orWhere(function (Builder $sub) use ($userId) {
                      $sub->where('requester_user_id', '!=', $userId)
                          ->where(function (Builder $s) {
                              $s->where('solicited_to', 'logistica')->orWhereNull('solicited_to');
                          })
                          ->where('status', '!=', 'draft');
                  });
            });
        } elseif ($user->hasRole('admin')) {
            $query->where(function (Builder $q) use ($userId) {
                // Siempre ve las propias (cualquier estado)
                $q->where('requester_user_id', $userId)
                // O las dirigidas a gerencia de otros, solo si no son borrador
                  ->orWhere(function (Builder $sub) use ($userId) {
                      $sub->where('requester_user_id', '!=', $userId)
                          ->where(function (Builder $s) {
                              $s->where('solicited_to', 'gerencia')->orWhereNull('solicited_to');
                          })
                          ->where('status', '!=', 'draft');
                  });
            });
        }
    }

    protected function notifySubmitted(PurchaseRequest $purchaseRequest): void
    {
        $recipients = $this->resolveSubmittedRecipients($purchaseRequest);

        if ($recipients->isEmpty()) {
            \Log::info('notifySubmitted: sin destinatarios', ['purchase_request_id' => $purchaseRequest->id]);
            return;
        }

        $requesterName = $purchaseRequest->requester?->name ?? Auth::user()?->name;
        Notification::send($recipients, new PurchaseRequestSubmittedNotification($purchaseRequest, $requesterName));
    }

    /**
     * Destinatarios al enviar a aprobación: quienes pueden aprobar en el área destino
     * ("solicited_to": gerencia -> rol admin, logistica -> rol logistica) Y tienen el permiso
     * 'aprobar solicitudes compra'. Un auxiliar sin ese permiso no recibe el aviso.
     * Nunca se notifica al propio solicitante. El encargado de compra se entera al responderse.
     *
     * @return Collection<int, User>
     */
    public function resolveSubmittedRecipients(PurchaseRequest $purchaseRequest): Collection
    {
        $solicitedTo = $purchaseRequest->solicited_to;
        if (!$solicitedTo) {
            return collect();
        }

        // 'gerencia' no existe como rol Spatie; los gerentes están en 'admin'
        $roleName = $solicitedTo === 'gerencia' ? 'admin' : $solicitedTo;

        try {
            $recipients = User::role($roleName)->permission('aprobar solicitudes compra')->get();
        } catch (\Throwable $e) {
            \Log::warning('resolveSubmittedRecipients: rol o permiso no encontrado', ['role' => $roleName, 'error' => $e->getMessage()]);
            return collect();
        }

        return $recipients->reject(fn (User $u) => $u->id === $purchaseRequest->requester_user_id)->values();
    }

    protected function notifyResponded(PurchaseRequest $purchaseRequest, string $action, ?User $responder, ?string $reason): void
    {
        // Solicitante y encargado de compra; nunca quien ejecuta la acción.
        $recipients = User::whereIn('id', array_filter([$purchaseRequest->requester_user_id, $purchaseRequest->buyer_user_id]))
            ->get()
            ->reject(fn (User $u) => $responder && $u->id === $responder->id);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new PurchaseRequestRespondedNotification($purchaseRequest, $action, $responder?->name, $reason));
    }
}
