<?php

namespace App\Http\Controllers\Expenses;

use App\Http\Controllers\Controller;
use App\Models\Expenses\PurchaseRequest;
use App\Services\Expenses\PurchaseRequestService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PurchaseRequestController extends Controller
{
    public function __construct(protected PurchaseRequestService $service) {}

    public function index()
    {
        return Inertia::render('Expenses/PurchaseServiceRequests/Index');
    }

    public function getData(Request $request)
    {
        $filters = $request->only(['search', 'status', 'provider_id', 'date_from', 'date_to']);
        $requests = $this->service->paginate($filters, $request->input('per_page', 20));

        return response()->json([
            'data' => $requests->items(),
            'total' => $requests->total(),
            'current_page' => $requests->currentPage(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $purchaseRequest = $this->service->create($data);

        return response()->json(['data' => $purchaseRequest], 201);
    }

    public function show(PurchaseRequest $purchaseRequest)
    {
        return response()->json(['data' => $this->service->loadWithRelations($purchaseRequest)]);
    }

    public function update(Request $request, PurchaseRequest $purchaseRequest)
    {
        $data = $this->validateData($request, true);
        $updated = $this->service->update($purchaseRequest, $data);

        return response()->json(['data' => $updated]);
    }

    public function destroy(PurchaseRequest $purchaseRequest)
    {
        $this->service->delete($purchaseRequest);
        return response()->json(['message' => 'Solicitud eliminada correctamente.']);
    }

    private function validateData(Request $request, bool $isUpdate = false): array
    {
        return $request->validate([
            'request_number' => 'nullable|string|max:64',
            'contact_id' => 'required|exists:contacts,id',
            'requester_user_id' => 'nullable|exists:users,id',
            'date' => 'required|date',
            'destination' => 'nullable|string|max:255',
            'estimated_amount' => 'nullable|numeric',
            'description' => 'nullable|string',
            'status' => 'nullable|string|max:50',
        ]);
    }
}
