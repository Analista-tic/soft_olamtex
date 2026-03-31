<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Expenses\PurchaseDocument;
use App\Services\Finance\CreditDebitNoteService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CreditDebitNoteController extends Controller
{
    public function __construct(protected CreditDebitNoteService $service)
    {
    }

    public function index(Request $request)
    {
        $documents = $this->service->paginate([
            'per_page' => (int) $request->input('per_page', 20),
        ]);

        return Inertia::render('Finance/CreditDebitNotes/Index', [
            'initialData' => [
                'data' => $documents->items(),
                'total' => $documents->total(),
                'current_page' => $documents->currentPage(),
            ],
        ]);
    }

    public function getData(Request $request)
    {
        $filters = $request->only(['contact_id', 'parent_document_id', 'date_from', 'date_to', 'per_page', 'context', 'document_type']);

        if (!$request->filled('contact_id') && $request->filled('provider_id')) {
            $filters['contact_id'] = $request->input('provider_id');
        }

        $documents = $this->service->paginate($filters);

        return response()->json([
            'data' => $documents->items(),
            'total' => $documents->total(),
            'current_page' => $documents->currentPage(),
        ]);
    }

    public function nextNumber(Request $request)
    {
        $validated = $request->validate([
            'document_type' => ['required', Rule::in($this->noteTypes())],
        ]);

        $documentNumber = $this->service->getNextDocumentNumberPreview($validated['document_type']);

        return response()->json([
            'data' => [
                'document_number' => $documentNumber,
            ],
        ]);
    }

    public function eligiblePurchaseInvoices(Request $request)
    {
        $validated = $request->validate([
            'contact_id' => ['nullable', 'integer', 'min:1'],
            'provider_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $contactId = (int) ($validated['contact_id'] ?? ($validated['provider_id'] ?? 0));
        $rows = $this->service->listEligiblePurchaseInvoices($contactId > 0 ? $contactId : null);

        return response()->json([
            'data' => $rows,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);
        $document = $this->service->create($validated)->load([
            'items.item',
            'supplier',
            'parent:id,document_number,document_type',
            'creator:id,name',
        ]);

        return response()->json(['data' => $document], 201);
    }

    public function show($id)
    {
        $purchaseDocument = $this->findNoteOrFail($id)->load([
            'items.item',
            'supplier',
            'parent:id,document_number,document_type',
            'creator:id,name',
            'executor:id,name',
        ]);

        return response()->json(['data' => $purchaseDocument]);
    }

    public function update(Request $request, $id)
    {
        $purchaseDocument = $this->findNoteOrFail($id);
        $validated = $this->validatePayload($request, true);

        $updated = $this->service->update($purchaseDocument, $validated)->load([
            'items.item',
            'supplier',
            'parent:id,document_number,document_type',
            'creator:id,name',
            'executor:id,name',
        ]);

        return response()->json(['data' => $updated]);
    }

    public function destroy($id)
    {
        $purchaseDocument = $this->findNoteOrFail($id);
        $this->service->delete($purchaseDocument);

        return response()->json(['message' => 'Nota eliminada correctamente.']);
    }

    protected function validatePayload(Request $request, bool $isUpdate = false): array
    {
        return $request->validate([
            'scope' => ['sometimes', 'string'],
            'document_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'document_type' => [$isUpdate ? 'sometimes' : 'required', Rule::in($this->noteTypes())],
            'parent_document_id' => [$isUpdate ? 'sometimes' : 'required', 'integer', 'exists:purchase_documents,id'],
            'contact_id' => ['nullable', 'integer'],
            'provider_name' => ['nullable', 'string', 'max:255'],
            'provider_phone' => ['nullable', 'string', 'max:50'],
            'date' => ['nullable', 'date'],
            'delivery_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            'reason_code' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:100'],
            'reason' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'max:50'],
            'approval_status' => ['nullable', 'string', 'max:50'],
            'executed_by_user_id' => ['nullable', 'integer'],
            'items' => [$isUpdate ? 'sometimes' : 'required', 'array', 'min:1'],
            'items.*.id' => ['sometimes', 'integer'],
            'items.*.item_id' => ['nullable', 'integer', 'exists:items,id'],
            'items.*.item_name' => ['required_without:items.*.item_id', 'string', 'max:255'],
            'items.*.price' => ['required', 'numeric'],
            'items.*.tax' => ['nullable', 'numeric'],
            'items.*.quantity' => ['required', 'numeric'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.supplier_code' => ['nullable', 'string', 'max:100'],
            'items.*.supplier_color' => ['nullable', 'string', 'max:100'],
            'items.*.order_width' => ['nullable', 'numeric'],
            'items.*.total' => ['nullable', 'numeric'],
            'items.*.total_tax' => ['nullable', 'numeric'],
        ]);
    }

    protected function findNoteOrFail($id): PurchaseDocument
    {
        return PurchaseDocument::query()
            ->whereIn('document_type', $this->noteTypes())
            ->where('id', $id)
            ->firstOrFail();
    }

    protected function noteTypes(): array
    {
        return [
            PurchaseDocument::TYPE_CREDIT_NOTE,
            PurchaseDocument::TYPE_DEBIT_NOTE,
        ];
    }
}
