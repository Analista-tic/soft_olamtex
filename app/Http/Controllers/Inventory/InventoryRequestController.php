<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\InventoryRequest;
use App\Services\Inventory\InventoryRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class InventoryRequestController extends Controller
{
    public function __construct(protected InventoryRequestService $service) {}

    public function index()
    {
        return Inertia::render('Inventory/Requests/Index');
    }

    public function getData(Request $request)
    {
        $user = Auth::user();
        $canManageRequests = $this->canManageRequests($user);
        $filters = $request->only(['search', 'status', 'date_from', 'date_to']);
        $perPage = $request->input('per_page', 10);

        $requests = $this->service->paginate($filters, $perPage, $user, $canManageRequests);
        $rows = array_map(function (InventoryRequest $req) {
            $hasMissing = $req->relationLoaded('items')
                ? $req->items->contains(fn ($item) => $item->item_id === null)
                : $req->items()->whereNull('item_id')->exists();

            return [
                'id' => $req->id,
                'document_number' => $req->document_number,
                'date' => $req->date,
                'delivery_date' => $req->delivery_date,
                'status' => $req->status,
                'observation' => $req->observation,
                'created_by_user_id' => $req->created_by_user_id,
                'requester' => $req->requester ? ['id' => $req->requester->id, 'name' => $req->requester->name] : null,
                'items_count' => $req->items ? $req->items->count() : $req->items()->count(),
                'has_missing_items' => $hasMissing,
            ];
        }, $requests->items());

        return response()->json([
            'data' => $rows,
            'total' => $requests->total(),
            'current_page' => $requests->currentPage(),
            'can_manage_requests' => $canManageRequests,
            'is_logistics' => $canManageRequests,
            'current_user_id' => $user?->id,
        ]);
    }

    public function nextNumber()
    {
        $next = $this->service->nextNumber();

        return response()->json(['document_number' => $next]);
    }

    public function show(InventoryRequest $inventoryRequest)
    {
        $this->ensureCanView($inventoryRequest);
        return response()->json(['data' => $this->service->findOrFail($inventoryRequest->id)]);
    }

    public function store(Request $request)
    {
        $payload = $this->validateRequestPayload($request);
        // Adjuntar imagen si existe
        if ($request->hasFile('image')) {
            $payload['image'] = $request->file('image');
        }
        $created = $this->service->create($payload, $this->requireUser());
        return response()->json(['data' => $created], 201);
    }

    public function update(Request $request, InventoryRequest $inventoryRequest)
    {
        $this->ensureCanEdit($inventoryRequest);
        $payload = $this->validateRequestPayload($request, $inventoryRequest);
        // Adjuntar imagen si existe
        if ($request->hasFile('image')) {
            $payload['image'] = $request->file('image');
        }
        $items = $request->has('items') ? $payload['items'] ?? [] : null;
        $updated = $this->service->update($inventoryRequest, $payload, $items);
        return response()->json(['data' => $updated]);
    }

    public function deliver(Request $request, InventoryRequest $inventoryRequest)
    {
        $this->ensureCanManage($inventoryRequest);
        $payload = $this->validateDeliveryPayload($request);
        $updated = $this->service->deliver($inventoryRequest, $payload, $this->requireUser());

        return response()->json(['data' => $updated]);
    }

    public function reject(Request $request, InventoryRequest $inventoryRequest)
    {
        $this->ensureCanManage($inventoryRequest);
        $payload = $this->validateRejectPayload($request);
        $updated = $this->service->reject($inventoryRequest, $payload['reason'], $this->requireUser());

        return response()->json(['data' => $updated]);
    }

    private function validateRequestPayload(Request $request, ?InventoryRequest $inventoryRequest = null): array
    {
        // Cuando viene multipart/form-data, "items" puede llegar como JSON string.
        if (is_string($request->input('items'))) {
            $decoded = json_decode($request->input('items'), true);
            $request->merge([
                'items' => is_array($decoded) ? $decoded : [],
            ]);
        }

        $uniqueRule = Rule::unique('inventory_requests', 'document_number');
        if ($inventoryRequest) {
            $uniqueRule->ignore($inventoryRequest->id);
        }

        $isUpdate = (bool) $inventoryRequest;

        $rules = [
            'document_number' => ['nullable', 'string', 'max:50', $uniqueRule],
            'date' => ($isUpdate ? 'sometimes' : 'required') . '|date',
            'delivery_date' => 'nullable|date',
            'observation' => 'nullable|string',
            'items' => ($isUpdate ? 'sometimes' : 'required') . '|array|min:1',
            'items.*.item_id' => 'nullable|integer|exists:items,id',
            'items.*.item_name' => 'required_without:items.*.item_id|string',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'image' => 'nullable|image|max:5120', // hasta 5MB
        ];

        return $request->validate($rules);
    }

    private function validateDeliveryPayload(Request $request): array
    {
        $rules = [
            'delivery_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|exists:inventory_request_items,id',
            'items.*.delivered_quantity' => 'required|numeric|min:0.0001',
            'items.*.warehouse_id' => 'required|integer|exists:warehouses,id',
        ];

        return $request->validate($rules);
    }

    private function validateRejectPayload(Request $request): array
    {
        return $request->validate([
            'reason' => 'required|string',
        ]);
    }

    private function ensureCanView(InventoryRequest $inventoryRequest): void
    {
        $user = $this->requireUser();
        if ($this->canManageRequests($user)) {
            return;
        }

        if ((int) $inventoryRequest->created_by_user_id !== (int) $user->id) {
            abort(403, 'No tiene permiso para ver esta solicitud.');
        }
    }

    private function ensureCanEdit(InventoryRequest $inventoryRequest): void
    {
        $user = $this->requireUser();
        if ((int) $inventoryRequest->created_by_user_id !== (int) $user->id) {
            abort(403, 'No tiene permiso para editar esta solicitud.');
        }
    }

    private function ensureCanManage(InventoryRequest $inventoryRequest): void
    {
        if (!$this->canManageRequests($this->requireUser())) {
            abort(403, 'No tiene permiso para gestionar solicitudes internas.');
        }
    }

    private function canManageRequests($user): bool
    {
        if (!$user) {
            return false;
        }

        if (
            $user->can('gestionar solicitudes internas')
            || $user->can('aprobar solicitudes internas')
            || $user->can('inventory.requests.manage')
        ) {
            return true;
        }

        if (!$user->relationLoaded('roles')) {
            $user->loadMissing('roles');
        }

        $normalizedRoleNames = collect($user->roles ?? [])
            ->pluck('name')
            ->filter()
            ->map(fn ($name) => (string) Str::of(trim((string) $name))->ascii()->lower())
            ->values()
            ->all();

        if (in_array('admin', $normalizedRoleNames, true)) {
            return true;
        }

        $logisticsRoles = ['logistica', 'logistics'];
        foreach ($logisticsRoles as $role) {
            if (in_array($role, $normalizedRoleNames, true)) {
                return true;
            }
        }

        return false;
    }

    private function requireUser()
    {
        $user = Auth::user();
        if (!$user) {
            abort(401, 'Usuario no autenticado.');
        }

        return $user;
    }
}
