<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\AccountingAccount;
use App\Services\Finance\AccountingAccountMovementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AccountingAccountController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Finance/AccountChart/Index');
    }

    public function create(): Response
    {
        return Inertia::render('Finance/AccountChart/AccountChartForm', [
            'mode' => 'create',
            'accountTypeOptions' => AccountingAccount::getAccountTypeOptions(),
            'parentOptions' => $this->getParentOptions(),
            'specialTypeOptions' => $this->getSpecialTypeOptions(),
        ]);
    }

    public function show(int $id): Response
    {
        $account = AccountingAccount::with('parent:id,code,name')->findOrFail($id);

        return Inertia::render('Finance/AccountChart/AccountChartForm', [
            'mode' => 'show',
            'account' => $account,
            'accountTypeOptions' => AccountingAccount::getAccountTypeOptions(),
            'parentOptions' => $this->getParentOptions(),
            'specialTypeOptions' => $this->getSpecialTypeOptions(),
        ]);
    }

    public function movements(int $id): Response
    {
        $account = AccountingAccount::with('parent:id,code,name')->findOrFail($id);

        return Inertia::render('Finance/AccountChart/Movements', [
            'account' => $account,
        ]);
    }

    public function edit(int $id): Response
    {
        $account = AccountingAccount::with('parent:id,code,name')->findOrFail($id);

        return Inertia::render('Finance/AccountChart/AccountChartForm', [
            'mode' => 'edit',
            'account' => $account,
            'accountTypeOptions' => AccountingAccount::getAccountTypeOptions(),
            'parentOptions' => $this->getParentOptions($id),
            'specialTypeOptions' => $this->getSpecialTypeOptions(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'search' => ['nullable', 'string', 'max:120'],
            'account_type' => ['nullable', 'string', 'max:30'],
            'is_active' => ['nullable', 'string', 'in:all,1,0'],
            'allows_entries' => ['nullable', 'string', 'in:all,1,0'],
        ]);

        $query = AccountingAccount::query()
            ->with('parent:id,code,name')
            ->orderBy('code');

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if (! empty($validated['account_type']) && $validated['account_type'] !== 'all') {
            $query->where('account_type', $validated['account_type']);
        }

        if (isset($validated['is_active']) && $validated['is_active'] !== 'all') {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        if (isset($validated['allows_entries']) && $validated['allows_entries'] !== 'all') {
            $query->where('allows_entries', (bool) $validated['allows_entries']);
        }

        $perPage = (int) ($validated['per_page'] ?? 50);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->items(),
            'total' => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:accounting_accounts,code'],
            'name' => ['required', 'string', 'max:150'],
            'account_type' => ['required', 'string', Rule::in([
                AccountingAccount::TYPE_ASSET,
                AccountingAccount::TYPE_LIABILITY,
                AccountingAccount::TYPE_EQUITY,
                AccountingAccount::TYPE_INCOME,
                AccountingAccount::TYPE_COST,
                AccountingAccount::TYPE_EXPENSE,
            ])],
            'parent_id' => ['nullable', 'integer', 'exists:accounting_accounts,id'],
            'allows_entries' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'special_type' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $validated['level'] = $this->resolveLevel($validated['parent_id'] ?? null);

        $account = AccountingAccount::create($validated);

        return response()->json(['data' => $account->load('parent:id,code,name')], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $account = AccountingAccount::findOrFail($id);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('accounting_accounts', 'code')->ignore($account->id)],
            'name' => ['required', 'string', 'max:150'],
            'account_type' => ['required', 'string', Rule::in([
                AccountingAccount::TYPE_ASSET,
                AccountingAccount::TYPE_LIABILITY,
                AccountingAccount::TYPE_EQUITY,
                AccountingAccount::TYPE_INCOME,
                AccountingAccount::TYPE_COST,
                AccountingAccount::TYPE_EXPENSE,
            ])],
            'parent_id' => ['nullable', 'integer', 'exists:accounting_accounts,id'],
            'allows_entries' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'special_type' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        // Evitar que una cuenta sea su propio padre
        if (isset($validated['parent_id']) && $validated['parent_id'] === $account->id) {
            return response()->json([
                'message' => 'Una cuenta no puede ser su propia cuenta padre.',
                'errors' => ['parent_id' => ['Una cuenta no puede ser su propia cuenta padre.']],
            ], 422);
        }

        $validated['level'] = $this->resolveLevel($validated['parent_id'] ?? null);

        $account->update($validated);

        return response()->json(['data' => $account->fresh()->load('parent:id,code,name')]);
    }

    public function toggleStatus(int $id): JsonResponse
    {
        $account = AccountingAccount::findOrFail($id);
        $account->update(['is_active' => ! $account->is_active]);

        return response()->json(['data' => $account->fresh()]);
    }

    public function options(Request $request): JsonResponse
    {
        $query = AccountingAccount::query()
            ->select('id', 'code', 'name', 'account_type', 'parent_id', 'level', 'allows_entries')
            ->active()
            ->orderBy('code');

        if ($request->filled('allows_entries')) {
            $query->where('allows_entries', true);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function movementsData(
        Request $request,
        int $id,
        AccountingAccountMovementService $movementService,
    ): JsonResponse {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'document_type' => ['nullable', 'string', 'max:80'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $account = AccountingAccount::findOrFail($id);

        return response()->json($movementService->getMovements($account, $validated));
    }

    private function getParentOptions(?int $excludeId = null): array
    {
        $query = AccountingAccount::query()
            ->select('id', 'code', 'name', 'account_type', 'level')
            ->active()
            ->orderBy('code');

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->get()->map(fn (AccountingAccount $a) => [
            'id' => $a->id,
            'name' => "{$a->code} - {$a->name}",
        ])->toArray();
    }

    private function getSpecialTypeOptions(): array
    {
        return [
            ['id' => '', 'name' => 'Ninguno'],
            ['id' => AccountingAccount::SPECIAL_CASH, 'name' => 'Caja'],
            ['id' => AccountingAccount::SPECIAL_BANK, 'name' => 'Banco'],
            ['id' => AccountingAccount::SPECIAL_ACCOUNTS_PAYABLE, 'name' => 'Cuentas por pagar'],
            ['id' => AccountingAccount::SPECIAL_ACCOUNTS_RECEIVABLE, 'name' => 'Cuentas por cobrar'],
        ];
    }

    private function resolveLevel(?int $parentId): int
    {
        if ($parentId === null) {
            return 1;
        }

        $parent = AccountingAccount::find($parentId);

        return $parent ? $parent->level + 1 : 1;
    }
}
