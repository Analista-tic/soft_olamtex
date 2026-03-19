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
        ]);
    }

    public function show(int $id): Response
    {
        $account = AccountingAccount::with('parent:id,code,name')->findOrFail($id);

        return Inertia::render('Finance/AccountChart/AccountChartForm', [
            'mode' => 'show',
            'account' => $account,
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

    public function item(int $id): JsonResponse
    {
        $account = AccountingAccount::with('parent:id,code,name')->findOrFail($id);

        return response()->json([
            'data' => $account,
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

        if (isset($validated['parent_id']) && $validated['parent_id'] !== null) {
            if ($validated['parent_id'] === $account->id ||
                $this->wouldCreateCycle($account->id, $validated['parent_id'])) {
                return response()->json([
                    'message' => 'La cuenta padre seleccionada crearía una referencia circular en el catálogo.',
                    'errors' => ['parent_id' => ['La cuenta padre seleccionada crearía una referencia circular en el catálogo.']],
                ], 422);
            }
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
            $allowsEntries = filter_var(
                $request->input('allows_entries'),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );
            if ($allowsEntries !== null) {
                $query->where('allows_entries', $allowsEntries);
            }
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
            'table_date' => ['nullable', 'date'],
            'table_account_id' => ['nullable', 'integer', 'exists:accounting_accounts,id'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'document_type' => ['nullable', 'string', 'max:80'],
            'search' => ['nullable', 'string', 'max:120'],
            'entry_reference' => ['nullable', 'string', 'max:120'],
        ]);

        $account = AccountingAccount::findOrFail($id);

        return response()->json($movementService->getMovements($account, $validated));
    }

    private function resolveLevel(?int $parentId): int
    {
        if ($parentId === null) {
            return 1;
        }

        $parent = AccountingAccount::find($parentId);

        return $parent ? $parent->level + 1 : 1;
    }

    private function wouldCreateCycle(int $accountId, int $newParentId): bool
    {
        $visited = [];
        $currentId = $newParentId;

        while ($currentId !== null) {
            if ($currentId === $accountId) {
                return true;
            }

            if (isset($visited[$currentId])) {
                break;
            }

            $visited[$currentId] = true;
            $parent = AccountingAccount::select('parent_id')
                ->find($currentId);
            $currentId = $parent?->parent_id;
        }

        return false;
    }
}
