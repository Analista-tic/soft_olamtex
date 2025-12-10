<?php

namespace App\Http\Controllers\Expenses;

use App\Http\Controllers\Controller;
use App\Models\Expenses\PurchaseRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PurchaseRequestController extends Controller
{
    public function index()
    {
        return Inertia::render('Expenses/PurchaseServiceRequests/Index');
    }

    public function getData(Request $request)
    {
        $query = PurchaseRequest::with(['provider', 'requester']);

        if ($search = $request->input('search')) {
            $query->where(function ($sub) use ($search) {
                $sub->where('request_number', 'like', "%{$search}%")
                    ->orWhereHas('provider', function ($providerQuery) use ($search) {
                        $providerQuery->where('full_name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('provider_id') && $request->provider_id !== 'all') {
            $providerId = is_numeric($request->provider_id) ? (int) $request->provider_id : null;
            if ($providerId !== null) {
                $query->where('contact_id', $providerId);
            }
        }

        if ($request->filled('date_from')) {
            $query->where('date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('date', '<=', $request->date_to);
        }

        $perPage = $request->input('per_page', 20);
        $requests = $query->orderByDesc('date')->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'data' => $requests->items(),
            'total' => $requests->total(),
            'current_page' => $requests->currentPage(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        if (empty($data['request_number'])) {
            $data['request_number'] = $this->generateRequestNumber();
        }
        $data['status'] = $data['status'] ?? 'draft';
        $data['requester_user_id'] = $data['requester_user_id'] ?? $request->user()?->id;

        $purchaseRequest = PurchaseRequest::create($data);

        return response()->json(['data' => $purchaseRequest->load('provider', 'requester')], 201);
    }

    public function show(PurchaseRequest $purchaseRequest)
    {
        return response()->json(['data' => $purchaseRequest->load('provider', 'requester')]);
    }

    public function update(Request $request, PurchaseRequest $purchaseRequest)
    {
        $data = $this->validateData($request, true);
        $purchaseRequest->update($data);

        return response()->json(['data' => $purchaseRequest->fresh('provider', 'requester')]);
    }

    public function destroy(PurchaseRequest $purchaseRequest)
    {
        $purchaseRequest->delete();
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

    private function generateRequestNumber(): string
    {
        $nextId = (int) PurchaseRequest::withTrashed()->max('id') + 1;
        return sprintf('SOL-%s-%04d', now()->format('Ymd'), $nextId);
    }
}
