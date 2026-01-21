<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\InventoryRequest;
use App\Services\Inventory\InventoryRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $isLogistics = $this->isLogisticsUser($user);
        $filters = $request->only(['search', 'status', 'date_from', 'date_to']);
        $perPage = $request->input('per_page', 10);

        $requests = $this->service->paginate($filters, $perPage, $user, $isLogistics);
        $rows = array_map(function (InventoryRequest $req) {
            $hasMissing = $req->relationLoaded('items')
                ? $req->items->contains(fn ($item) => $item->item_id === null)
                : $req->items()->whereNull('item_id')->exists();

            return [
                'id' => $req->id,
                'document_number' => $req->document_number,
                'date' => $req->date,
                'delivery_date' => $req->delivery_date,
                'delivered_at' => $req->delivered_at,
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
            'is_logistics' => $isLogistics,
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
        $created = $this->service->create($payload, $this->requireUser());

        return response()->json(['data' => $created], 201);
    }

    public function update(Request $request, InventoryRequest $inventoryRequest)
    {
        $this->ensureCanEdit($inventoryRequest);
        $payload = $this->validateRequestPayload($request, $inventoryRequest);
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
        if ($this->isLogisticsUser($user)) {
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
        if (!$this->isLogisticsUser($this->requireUser())) {
            abort(403, 'Solo logistica puede aprobar o rechazar solicitudes.');
        }
    }

    private function isLogisticsUser($user): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->can('gestionar solicitudes internas') || $user->can('aprobar solicitudes internas')) {
            return true;
        }

        $roles = ['logistica', 'logistics', 'inventario'];
        return method_exists($user, 'hasAnyRole') ? $user->hasAnyRole($roles) : false;
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
