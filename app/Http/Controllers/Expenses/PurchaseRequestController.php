<?php

namespace App\Http\Controllers\Expenses;

use App\Http\Controllers\Controller;
use App\Models\Expenses\PurchaseRequest;
use App\Services\Expenses\PurchaseRequestService;
use Illuminate\Http\Request;
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
        $filters = $request->only(['search', 'status', 'provider_name', 'date_from', 'date_to', 'exclude_with_orders']);
        // Convertir exclude_with_orders a boolean si viene como string
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
        $payload = $this->validateRequestPayload($request);
        
        // Manejar subida de archivo
        if ($request->hasFile('document')) {
            $payload['document_path'] = $this->service->handleDocumentUpload($request->file('document'));
        }
        
        $purchaseRequest = $this->service->create($payload);

        return response()->json(['data' => $purchaseRequest], 201);
    }

    public function show(PurchaseRequest $purchaseRequest)
    {
        return response()->json(['data' => $this->service->loadWithRelations($purchaseRequest)]);
    }

    public function update(Request $request, PurchaseRequest $purchaseRequest)
    {
        $payload = $this->validateRequestPayload($request, true);
        
        // Manejar subida de archivo
        if ($request->hasFile('document')) {
            $payload['document_path'] = $this->service->handleDocumentUpload(
                $request->file('document'), 
                $purchaseRequest->document_path
            );
        } elseif ($request->has('remove_document') && $request->boolean('remove_document')) {
            // Eliminar documento si se solicita
            if ($purchaseRequest->document_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($purchaseRequest->document_path);
            }
            $payload['document_path'] = null;
        }
        
        $updated = $this->service->update($purchaseRequest, $payload);

        return response()->json(['data' => $updated]);
    }

    public function destroy(PurchaseRequest $purchaseRequest)
    {
        // Validar que solo se pueda eliminar si está en draft o cancelled
        if (!$purchaseRequest->canEdit() && !$purchaseRequest->isCancelled()) {
            return response()->json([
                'message' => 'No se puede eliminar una solicitud que no está en borrador o cancelada.'
            ], 422);
        }

        $this->service->delete($purchaseRequest);
        return response()->json(['message' => 'Solicitud eliminada correctamente.']);
    }

    /**
     * Enviar solicitud a aprobación (draft → pending)
     */
    public function sendToApproval(PurchaseRequest $purchaseRequest)
    {
        try {
            $updated = $this->service->sendToApproval($purchaseRequest);
            
            // Asegurar que el modelo se serialice correctamente convirtiendo a array
            // Esto fuerza la conversión del enum a string
            $data = $updated->toArray();
            
            // Asegurar que las relaciones también se serialicen correctamente
            if (isset($data['requester']) && is_object($data['requester'])) {
                $data['requester'] = $data['requester']->toArray();
            }
            
            return response()->json(['data' => $data, 'message' => 'Solicitud enviada a aprobación correctamente.']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            \Log::error('Error enviando solicitud a aprobación: ' . $e->getMessage(), [
                'exception' => $e,
                'request_id' => $purchaseRequest->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Error al enviar la solicitud a aprobación: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Aprobar solicitud (pending → approved)
     */
    public function approve(Request $request, PurchaseRequest $purchaseRequest)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $updated = $this->service->approve($purchaseRequest, $validated['reason'] ?? null);
            
            // Asegurar que el modelo se serialice correctamente
            $data = $updated->toArray();
            
            return response()->json(['data' => $data, 'message' => 'Solicitud aprobada correctamente.']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            \Log::error('Error aprobando solicitud: ' . $e->getMessage(), [
                'exception' => $e,
                'request_id' => $purchaseRequest->id,
            ]);
            return response()->json(['message' => 'Error al aprobar la solicitud: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Rechazar solicitud (pending → rejected)
     */
    public function reject(Request $request, PurchaseRequest $purchaseRequest)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $updated = $this->service->reject($purchaseRequest, $validated['reason']);
            
            // Asegurar que el modelo se serialice correctamente
            $data = $updated->toArray();
            
            return response()->json(['data' => $data, 'message' => 'Solicitud rechazada correctamente.']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            \Log::error('Error rechazando solicitud: ' . $e->getMessage(), [
                'exception' => $e,
                'request_id' => $purchaseRequest->id,
            ]);
            return response()->json(['message' => 'Error al rechazar la solicitud: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Cancelar solicitud (draft/pending → cancelled)
     */
    public function cancel(PurchaseRequest $purchaseRequest)
    {
        try {
            $updated = $this->service->cancel($purchaseRequest);
            
            // Asegurar que el modelo se serialice correctamente
            $data = $updated->toArray();
            
            return response()->json(['data' => $data, 'message' => 'Solicitud cancelada correctamente.']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            \Log::error('Error cancelando solicitud: ' . $e->getMessage(), [
                'exception' => $e,
                'request_id' => $purchaseRequest->id,
            ]);
            return response()->json(['message' => 'Error al cancelar la solicitud: ' . $e->getMessage()], 500);
        }
    }

    protected function validateRequestPayload(Request $request, bool $isUpdate = false): array
    {
        // Parsear items si viene como JSON string
        if ($request->has('items') && is_string($request->input('items'))) {
            $itemsJson = $request->input('items');
            $itemsArray = json_decode($itemsJson, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($itemsArray)) {
                $request->merge(['items' => $itemsArray]);
            } else {
                $request->merge(['items' => []]);
            }
        }

        $rules = [
            // request_number se genera automáticamente en el backend
            'provider_name' => 'nullable|string|max:255',
            'requester_user_id' => 'nullable|exists:users,id',
            'date' => 'required|date',
            'destination' => 'nullable|string|max:255',
            'description' => $isUpdate ? 'sometimes|nullable|string' : 'required|string',
                'status' => 'sometimes|in:draft,pending,approved,processed,rejected,cancelled',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'nullable',
            'items.*.name' => 'nullable|string',
            'items.*.price' => 'nullable|numeric|min:0',
            'items.*.quantity' => 'nullable|numeric|min:0',
            'items.*.description' => 'nullable|string',
            'items.*.total' => 'nullable|numeric|min:0',
            'document' => 'nullable|file|mimes:jpeg,jpg,png,webp,pdf|max:10240', // 10MB max
            'remove_document' => 'nullable|boolean',
        ];

        $validated = $request->validate($rules);
        
        // Asegurar que items sea un array válido
        if (!isset($validated['items']) || !is_array($validated['items'])) {
            $validated['items'] = [];
        }
        
        return $validated;
    }
}

