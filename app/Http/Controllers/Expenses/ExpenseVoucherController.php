<?php

namespace App\Http\Controllers\Expenses;

use App\Http\Controllers\Controller;
use App\Services\Expenses\ExpenseVoucherService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExpenseVoucherController extends Controller
{
    public function __construct(private ExpenseVoucherService $expenseVoucherService) {}

    public function index()
    {
        return Inertia::render('Expenses/ExpenseVouchers/Index');
    }

    public function create()
    {
        return Inertia::render('Expenses/ExpenseVouchers/ExpenseVoucherForm', [
            'mode' => 'create',
        ]);
    }

    public function show(int $voucher)
    {
        return Inertia::render('Expenses/ExpenseVouchers/ExpenseVoucherForm', [
            'mode' => 'show',
            'voucher' => $this->expenseVoucherService->findOrFail($voucher),
        ]);
    }

    public function edit(int $voucher)
    {
        return Inertia::render('Expenses/ExpenseVouchers/ExpenseVoucherForm', [
            'mode' => 'edit',
            'voucher' => $this->expenseVoucherService->findOrFail($voucher),
        ]);
    }

    public function data(Request $request)
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:120'],
            'provider_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'status' => ['nullable', 'string', 'in:all,recorded'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'sort_by' => ['nullable', 'string', 'in:voucher_number,payment_date,supplier_name,total_applied'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $paginator = $this->expenseVoucherService->paginate(
            $validated,
            (int) ($validated['per_page'] ?? 10),
            $request->user(),
        );

        return response()->json([
            'data' => $paginator->items(),
            'total' => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'contact_id' => ['required', 'integer', 'exists:contacts,id'],
            'date' => ['required', 'date'],
            'account_id' => ['nullable', 'integer'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
            'applications' => ['required', 'array', 'min:1'],
            'applications.*.document_id' => ['required', 'integer', 'exists:purchase_documents,id'],
            'applications.*.amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $record = $this->expenseVoucherService->create($validated);

        return response()->json([
            'data' => $record,
        ], 201);
    }

    public function update(Request $request, int $voucher)
    {
        $validated = $request->validate([
            'contact_id' => ['required', 'integer', 'exists:contacts,id'],
            'date' => ['required', 'date'],
            'account_id' => ['nullable', 'integer'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
            'applications' => ['required', 'array', 'min:1'],
            'applications.*.document_id' => ['required', 'integer', 'exists:purchase_documents,id'],
            'applications.*.amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $record = $this->expenseVoucherService->update($voucher, $validated);

        return response()->json([
            'data' => $record,
        ]);
    }

    public function void(int $voucher, Request $request)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        $this->expenseVoucherService->void($voucher, $validated['reason']);

        return back()->with('success', 'Comprobante anulado correctamente.');
    }
}
