<?php

namespace App\Http\Controllers\Expenses;

use App\Http\Controllers\Controller;
use App\Models\Expenses\PurchaseDocument;
use App\Services\Expenses\PurchasePaymentService;
use Illuminate\Http\Request;

class PurchasePaymentController extends Controller
{
    public function __construct(private PurchasePaymentService $service)
    {
    }

    public function byInvoice(PurchaseDocument $purchaseDocument)
    {
        $payments = $this->service->getInvoicePayments($purchaseDocument);
        $summary = $this->service->getInvoicePaymentSummary($purchaseDocument);

        return response()->json([
            'data' => $payments,
            'summary' => $summary,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'purchase_invoice_id' => ['required', 'integer', 'exists:purchase_documents,id'],
            'payment_date' => ['nullable', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $payment = $this->service->registerInvoicePayment($validated);

        return response()->json([
            'data' => [
                'id' => $payment->id,
                'purchase_invoice_id' => $payment->purchase_invoice_id,
                'payment_date' => $payment->payment_date?->format('Y-m-d'),
                'amount' => round((float) ($payment->amount ?? 0), 2),
                'reference' => $payment->reference,
                'notes' => $payment->notes,
                'created_by_user_id' => $payment->created_by_user_id,
                'created_by_name' => $payment->creator?->name,
                'created_at' => $payment->created_at?->toDateTimeString(),
            ],
        ], 201);
    }
}
