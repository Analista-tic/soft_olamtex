<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\CashReceipt;
use App\Services\Finance\CashReceiptService;
use App\Services\Finance\TreasuryVoucherService;
use Illuminate\Http\Request;

class CashReceiptController extends Controller
{
    public function __construct(
        private readonly CashReceiptService $cashReceiptService,
        private readonly TreasuryVoucherService $treasuryVoucherService,
    ) {
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:120'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $paginator = $this->cashReceiptService->paginate(
            $validated,
            (int) ($validated['per_page'] ?? 10),
        );

        return response()->json([
            'data' => collect($paginator->items())->map(fn (CashReceipt $receipt) => $this->serializeReceipt($receipt))->values(),
            'total' => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'contact_id' => ['required', 'integer', 'exists:contacts,id'],
            'payment_date' => ['required', 'date'],
            'account_id' => ['nullable', 'integer'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
            'applications' => ['required', 'array', 'min:1'],
            'applications.*.sale_invoice_id' => ['required', 'integer', 'exists:sale_documents,id'],
            'applications.*.amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $receipt = $this->cashReceiptService->create(
            $validated,
            $validated['applications'] ?? [],
        );

        return response()->json([
            'message' => 'Recibo de caja registrado correctamente.',
            'data' => $this->serializeReceipt($receipt, true),
        ], 201);
    }

    public function show(CashReceipt $receipt)
    {
        return response()->json([
            'data' => $this->serializeReceipt(
                $this->cashReceiptService->findOrFail((int) $receipt->id),
                true
            ),
        ]);
    }

    public function void(Request $request, CashReceipt $receipt)
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string'],
        ]);

        $voided = $this->cashReceiptService->void($receipt, (string) ($validated['reason'] ?? ''));

        return response()->json([
            'message' => 'Recibo de caja anulado correctamente.',
            'data' => $this->serializeReceipt($voided, true),
        ]);
    }

    public function pendingInvoices(Request $request)
    {
        $validated = $request->validate([
            'contact_id' => ['required', 'integer', 'exists:contacts,id'],
        ]);

        return response()->json([
            'data' => $this->cashReceiptService->getPendingReceivableDocuments((int) $validated['contact_id']),
        ]);
    }

    public function customers(Request $request)
    {
        return response()->json([
            'data' => $this->treasuryVoucherService->searchCustomers(
                (string) $request->input('search', ''),
                (int) $request->input('limit', 30),
            ),
        ]);
    }

    public function accounts()
    {
        return response()->json([
            'data' => $this->treasuryVoucherService->getAccountOptions(),
        ]);
    }

    public function paymentMethods()
    {
        return response()->json([
            'data' => $this->treasuryVoucherService->getPaymentMethodOptions(),
        ]);
    }

    private function serializeReceipt(CashReceipt $receipt, bool $includeJournalEntry = false): array
    {
        $receipt->setRelation('client', $receipt->contact);
        $receipt->setRelation('payments', $receipt->salePayments);

        $payload = $receipt->toArray();
        $payload['total'] = round((float) ($payload['total'] ?? $receipt->salePayments->sum('amount')), 2);
        $payload['status'] = 'recorded';

        if ($includeJournalEntry) {
            $payload['journal_entry'] = $this->cashReceiptService->getJournalEntry($receipt)?->load('lines.account', 'lines.contact');
        }

        return $payload;
    }
}
