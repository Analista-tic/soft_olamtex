<?php

namespace App\Http\Controllers\Expenses;

use App\Http\Controllers\Controller;
use App\Models\Expenses\PurchaseRequest;
use App\Services\Expenses\PurchaseRequestService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PurchaseRequestController extends Controller
{
    public function __construct(private PurchaseRequestService $service) {}

    public function index()
    {
        return Inertia::render('Expenses/PurchaseRequests/Index');
    }

    public function getData(Request $request)
    {

        $filters = $request->only(['search', 'status', 'provider_name', 'date', 'exclude_with_orders']);


        if (isset($filters['exclude_with_orders'])) {
            $filters['exclude_with_orders'] = filter_var($filters['exclude_with_orders'], FILTER_VALIDATE_BOOLEAN);
        }


        $perPage = $request->input('per_page', 20);
        $documents = $this->service->paginate($filters, $perPage);

        return response()->json([
            'data' => $documents->items(),
            'total' => $documents->total(),
            'current_page' => $documents->currentPage(),
        ]);
    }

    public function store(Request $request)
    {
        try {
            $payload = $this->validateRequestPayload($request);
            $payload['items'] = $this->prepareItemsPayload($request, $payload['items'] ?? []);

            $purchaseRequest = $this->service->create($payload);

            return response()->json(['data' => $purchaseRequest], 201);
        } catch (\Throwable $e) {
            Log::error('Error creando solicitud de compra', [
                'message' => $e->getMessage(),
                'exception' => $e,
                'payload_keys' => array_keys($request->all()),
            ]);

            return response()->json([
                'message' => 'Error al crear la solicitud de compra: '.$e->getMessage(),
            ], 500);
        }
    }

    public function show(PurchaseRequest $purchaseRequest)
    {
        return response()->json(['data' => $this->service->loadWithRelations($purchaseRequest)]);
    }

    public function update(Request $request, PurchaseRequest $purchaseRequest)
    {
        try {
            $payload = $this->validateRequestPayload($request, true);
            $payload['items'] = $this->prepareItemsPayload($request, $payload['items'] ?? [], $purchaseRequest);

            $updated = $this->service->update($purchaseRequest, $payload);

            return response()->json(['data' => $updated]);
        } catch (\Throwable $e) {
            Log::error('Error actualizando solicitud de compra', [
                'purchase_request_id' => $purchaseRequest->id,
                'message' => $e->getMessage(),
                'exception' => $e,
                'payload_keys' => array_keys($request->all()),
            ]);

            return response()->json([
                'message' => 'Error al actualizar la solicitud de compra: '.$e->getMessage(),
            ], 500);
        }
    }

    public function destroy(PurchaseRequest $purchaseRequest)
    {
        if (!$purchaseRequest->canEdit() && !$purchaseRequest->isCancelled()) {
            return response()->json([
                'message' => 'No se puede eliminar una solicitud que no esta en borrador o cancelada.',
            ], 422);
        }

        $this->service->delete($purchaseRequest);

        return response()->json(['message' => 'Solicitud eliminada correctamente.']);
    }

    public function sendToApproval(PurchaseRequest $purchaseRequest)
    {
        try {
            $updated = $this->service->sendToApproval($purchaseRequest);
            $data = $updated->toArray();

            if (isset($data['requester']) && is_object($data['requester'])) {
                $data['requester'] = $data['requester']->toArray();
            }

            return response()->json([
                'data' => $data,
                'message' => 'Solicitud enviada a aprobacion correctamente.',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            \Log::error('Error enviando solicitud a aprobacion: ' . $e->getMessage(), [
                'exception' => $e,
                'request_id' => $purchaseRequest->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error al enviar la solicitud a aprobacion: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function approve(Request $request, PurchaseRequest $purchaseRequest)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $updated = $this->service->approve($purchaseRequest, $validated['reason'] ?? null);

            return response()->json([
                'data' => $updated->toArray(),
                'message' => 'Solicitud aprobada correctamente.',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            \Log::error('Error aprobando solicitud: ' . $e->getMessage(), [
                'exception' => $e,
                'request_id' => $purchaseRequest->id,
            ]);

            return response()->json([
                'message' => 'Error al aprobar la solicitud: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function reject(Request $request, PurchaseRequest $purchaseRequest)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $updated = $this->service->reject($purchaseRequest, $validated['reason']);

            return response()->json([
                'data' => $updated->toArray(),
                'message' => 'Solicitud rechazada correctamente.',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            \Log::error('Error rechazando solicitud: ' . $e->getMessage(), [
                'exception' => $e,
                'request_id' => $purchaseRequest->id,
            ]);

            return response()->json([
                'message' => 'Error al rechazar la solicitud: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function cancel(PurchaseRequest $purchaseRequest)
    {
        try {
            $updated = $this->service->cancel($purchaseRequest);

            return response()->json([
                'data' => $updated->toArray(),
                'message' => 'Solicitud cancelada correctamente.',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            \Log::error('Error cancelando solicitud: ' . $e->getMessage(), [
                'exception' => $e,
                'request_id' => $purchaseRequest->id,
            ]);

            return response()->json([
                'message' => 'Error al cancelar la solicitud: ' . $e->getMessage(),
            ], 500);
        }
    }

    protected function validateRequestPayload(Request $request, bool $isUpdate = false): array
    {
        if ($request->has('items') && is_string($request->input('items'))) {
            $decoded = json_decode($request->input('items'), true);
            $request->merge([
                'items' => json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : [],
            ]);
        }

        $rules = [
            'provider_name' => 'nullable|string|max:255',
            'requester_user_id' => 'nullable|exists:users,id',
            'date' => 'required|date',
            'destination' => 'nullable|string|max:255',
            'description' => $isUpdate ? 'sometimes|nullable|string' : 'required|string',
            'status' => 'sometimes|in:draft,pending,approved,processed,rejected,cancelled',
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => 'nullable',
            'items.*.name' => 'nullable|string',
            'items.*.price' => 'nullable|numeric|min:0',
            'items.*.quantity' => 'nullable|numeric|min:0',
            'items.*.description' => 'nullable|string',
            'items.*.total' => 'nullable|numeric|min:0',
            'items.*.total_tax' => 'nullable|numeric|min:0',
            'items.*.uid' => 'nullable|string|max:100',
            'items.*._delete_image' => 'nullable|boolean',
            'items.*.image' => 'nullable|image|max:2048',
        ];

        $validated = $request->validate($rules, [
            'items.required' => 'Debe agregar al menos un ítem a la solicitud.',
            'items.array' => 'Debe agregar al menos un ítem a la solicitud.',
            'items.min' => 'Debe agregar al menos un ítem a la solicitud.',
        ]);

        if (!isset($validated['items']) || !is_array($validated['items'])) {
            $validated['items'] = [];
        }

        return $validated;
    }

    protected function prepareItemsPayload(Request $request, array $items, ?PurchaseRequest $purchaseRequest = null): array
    {
        $existingItems = $purchaseRequest ? $this->resolveExistingItemsByKey($purchaseRequest) : [];
        $preparedItems = [];

        foreach (array_values($items) as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $itemKey = $this->resolveIncomingItemKey($item, $index, $purchaseRequest);
            $existingItem = $existingItems[$itemKey] ?? [];
            $imagePath = $existingItem['image_path'] ?? null;
            $uploadedImage = $request->file("items.$index.image");

            if ($uploadedImage instanceof UploadedFile) {
                $imagePath = $this->service->handleItemImageUpload($uploadedImage, $imagePath);
            } elseif ($request->boolean("items.$index._delete_image")) {
                $this->service->deleteStoredPublicFile($imagePath);
                $imagePath = null;
            }

            $preparedItem = [
                'uid' => $itemKey,
                'item_id' => $this->normalizeNullableValue($item['item_id'] ?? null),
                'name' => $this->normalizeNullableString($item['name'] ?? $item['item_name'] ?? null),
                'price' => $this->normalizeNumericValue($item['price'] ?? 0),
                'quantity' => $this->normalizeNumericValue($item['quantity'] ?? 0),
                'description' => $this->normalizeNullableString($item['description'] ?? null) ?? '',
                'total' => $this->normalizeNumericValue($item['total'] ?? 0),
                'image_path' => $imagePath,
            ];

            $totalTax = $this->normalizeNumericValue($item['total_tax'] ?? null, true);
            if ($totalTax !== null) {
                $preparedItem['total_tax'] = $totalTax;
            }

            $preparedItems[] = $preparedItem;
        }

        return $preparedItems;
    }

    protected function resolveExistingItemsByKey(PurchaseRequest $purchaseRequest): array
    {
        $items = is_array($purchaseRequest->items) ? $purchaseRequest->items : [];
        $resolved = [];

        foreach (array_values($items) as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $resolved[$this->resolveStoredItemKey($item, $index, $purchaseRequest)] = $item;
        }

        return $resolved;
    }

    protected function resolveIncomingItemKey(array $item, int $index, ?PurchaseRequest $purchaseRequest = null): string
    {
        $uid = isset($item['uid']) && is_string($item['uid']) ? trim($item['uid']) : '';

        if ($uid !== '') {
            return $uid;
        }

        if ($purchaseRequest) {
            return sprintf('legacy-%s-%s', $purchaseRequest->getKey(), $index);
        }

        return (string) Str::uuid();
    }

    protected function resolveStoredItemKey(array $item, int $index, PurchaseRequest $purchaseRequest): string
    {
        $uid = isset($item['uid']) && is_string($item['uid']) ? trim($item['uid']) : '';

        if ($uid !== '') {
            return $uid;
        }

        return sprintf('legacy-%s-%s', $purchaseRequest->getKey(), $index);
    }

    protected function normalizeNullableValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value) && trim($value) === '') {
            return null;
        }

        return $value;
    }

    protected function normalizeNullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    protected function normalizeNumericValue(mixed $value, bool $allowNull = false): ?float
    {
        if ($value === null || $value === '') {
            return $allowNull ? null : 0.0;
        }

        return is_numeric($value) ? (float) $value : ($allowNull ? null : 0.0);
    }
}
