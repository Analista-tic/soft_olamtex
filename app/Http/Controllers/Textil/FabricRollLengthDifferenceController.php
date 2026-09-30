<?php

namespace App\Http\Controllers\Textil;

use App\Http\Controllers\Controller;
use App\Models\Textil\FabricRollLengthDifference;
use App\Services\Textil\FabricRollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Diferencias de metraje (sobrante/faltante) detectadas al descargar rollos: listado para
 * el supervisor, con el rollo, su factura/proveedor, la orden y quién lo registró.
 */
class FabricRollLengthDifferenceController extends Controller
{
    public function __construct(protected FabricRollService $service) {}

    public function index()
    {
        return Inertia::render('Textil/FabricRollLengthDifferences/Index');
    }

    public function data(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:pending,reviewed,all'],
            'type' => ['nullable', 'in:sobrante,faltante'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $query = FabricRollLengthDifference::query()
            ->with([
                'roll:id,roll_number,quantity,received_quantity,actual_output,description,purchase_document_item_id',
                'roll.purchaseDocumentItem:id,purchase_document_id,item_name',
                'roll.purchaseDocumentItem.document:id,document_number,contact_id,provider_name',
                'roll.purchaseDocumentItem.document.supplier:id,full_name',
                'manualRollProgramming:id,manual_order_code',
                'productionOrder:id,op_code',
                'createdBy:id,name',
                'reviewedBy:id,name',
            ])
            ->orderByDesc('id');

        $status = $validated['status'] ?? 'pending';
        if ($status === 'pending') {
            $query->whereNull('reviewed_at');
        } elseif ($status === 'reviewed') {
            $query->whereNotNull('reviewed_at');
        }

        if (! empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->whereHas('roll', fn ($q) => $q->where('roll_number', 'like', "%{$search}%"));
        }

        $paginator = $query->paginate((int) ($validated['per_page'] ?? 50));

        $rows = $paginator->getCollection()->map(function (FabricRollLengthDifference $difference) {
            $roll = $difference->roll;
            $document = $roll?->purchaseDocumentItem?->document;

            return [
                'id' => $difference->id,
                'type' => $difference->type,
                'meters' => (float) $difference->meters,
                'quantity_before' => (float) $difference->quantity_before,
                'quantity_after' => (float) $difference->quantity_after,
                'reason' => $difference->reason,
                'roll_id' => $roll?->id,
                'roll_number' => $roll?->roll_number,
                'roll_received_quantity' => $roll ? (float) ($roll->received_quantity ?? $roll->quantity) : null,
                'roll_quantity' => $roll ? (float) $roll->quantity : null,
                'fabric' => $roll?->purchaseDocumentItem?->item_name ?? $roll?->description,
                'invoice_number' => $document?->document_number,
                'provider' => $document?->supplier?->full_name ?? $document?->provider_name,
                'order_code' => $difference->manualRollProgramming?->manual_order_code ?? $difference->productionOrder?->op_code,
                'released_reservations' => $difference->released_reservations ?? [],
                'is_reversal' => $difference->reverses_id !== null,
                'created_by' => $difference->createdBy?->name,
                'created_at' => $difference->created_at?->toIso8601String(),
                'reviewed_by' => $difference->reviewedBy?->name,
                'reviewed_at' => $difference->reviewed_at?->toIso8601String(),
                'review_notes' => $difference->review_notes,
            ];
        });

        return response()->json([
            'data' => $rows,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function review(Request $request, FabricRollLengthDifference $fabricRollLengthDifference): JsonResponse
    {
        $validated = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $difference = $this->service->reviewLengthDifference($fabricRollLengthDifference, $validated['review_notes'] ?? null);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'message' => 'Diferencia de metraje marcada como revisada.',
            'data' => [
                'id' => $difference->id,
                'reviewed_at' => $difference->reviewed_at?->toIso8601String(),
                'reviewed_by' => $difference->reviewedBy?->name,
                'review_notes' => $difference->review_notes,
            ],
        ]);
    }
}
