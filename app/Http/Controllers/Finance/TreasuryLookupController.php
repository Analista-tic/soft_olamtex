<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\Finance\TreasuryVoucherService;
use Illuminate\Http\Request;

class TreasuryLookupController extends Controller
{
    public function __construct(private TreasuryVoucherService $treasuryVoucherService)
    {
    }

    public function suppliers(Request $request)
    {
        $data = $this->treasuryVoucherService->searchSuppliers(
            (string) $request->input('search', ''),
            (int) $request->input('limit', 30),
        );

        return response()->json(['data' => $data]);
    }

    public function payableDocuments(Request $request)
    {
        $validated = $request->validate([
            'contact_id' => ['required', 'integer', 'exists:contacts,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $data = $this->treasuryVoucherService->getPendingPayableDocuments(
            (int) $validated['contact_id'],
            (int) ($validated['per_page'] ?? 200),
        );

        return response()->json(['data' => $data]);
    }

    public function customers(Request $request)
    {
        $data = $this->treasuryVoucherService->searchCustomers(
            (string) $request->input('search', ''),
            (int) $request->input('limit', 30),
        );

        return response()->json(['data' => $data]);
    }

    public function receivableDocuments(Request $request)
    {
        $validated = $request->validate([
            'contact_id' => ['required', 'integer', 'exists:contacts,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $data = $this->treasuryVoucherService->getPendingReceivableDocuments(
            (int) $validated['contact_id'],
            (int) ($validated['per_page'] ?? 200),
        );

        return response()->json(['data' => $data]);
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
}
