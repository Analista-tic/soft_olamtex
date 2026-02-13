<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\ProductSheet\Programmer;
use App\Services\Production\TechnicalDataSheetSuppliesStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class TechnicalDataSheetSuppliesController extends Controller
{
    public function __construct(
        private TechnicalDataSheetSuppliesStore $store,
    ) {
    }

    public function index(int $opId): JsonResponse
    {
        Programmer::query()->findOrFail($opId);
        return response()->json($this->store->read($opId));
    }

    public function upsert(int $opId, Request $request): JsonResponse
    {
        Programmer::query()->findOrFail($opId);

        $validated = $request->validate([
            'id' => ['nullable', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:200'],
            'stage' => ['required', 'in:GENERAL,CORTE,CONFECCION,TERMINACION'],
            'supplier' => ['nullable', 'string', 'max:200'],
            'uom' => ['nullable', 'string', 'max:60'],
            'qtyPerUnit' => ['required', 'numeric', 'min:0'],
            'unitCost' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $now = Carbon::now()->toISOString();
        $items = $this->store->read($opId);
        $targetId = isset($validated['id']) && trim((string) $validated['id']) !== ''
            ? trim((string) $validated['id'])
            : (string) Str::uuid();

        $updated = false;
        foreach ($items as &$item) {
            if ((string) ($item['id'] ?? '') !== $targetId) {
                continue;
            }

            $item = [
                ...$item,
                'id' => $targetId,
                'name' => trim((string) $validated['name']),
                'stage' => (string) $validated['stage'],
                'supplier' => $this->nullableText($validated['supplier'] ?? null),
                'uom' => $this->nullableText($validated['uom'] ?? null),
                'qtyPerUnit' => (float) $validated['qtyPerUnit'],
                'unitCost' => (float) $validated['unitCost'],
                'notes' => $this->nullableText($validated['notes'] ?? null),
                'updatedAt' => $now,
            ];
            $updated = true;
            break;
        }
        unset($item);

        if (!$updated) {
            $items[] = [
                'id' => $targetId,
                'name' => trim((string) $validated['name']),
                'stage' => (string) $validated['stage'],
                'supplier' => $this->nullableText($validated['supplier'] ?? null),
                'uom' => $this->nullableText($validated['uom'] ?? null),
                'qtyPerUnit' => (float) $validated['qtyPerUnit'],
                'unitCost' => (float) $validated['unitCost'],
                'notes' => $this->nullableText($validated['notes'] ?? null),
                'createdAt' => $now,
                'updatedAt' => $now,
            ];
        }

        $this->store->write($opId, $items);
        return response()->json($items);
    }

    public function destroy(int $opId, string $supplyId): JsonResponse
    {
        Programmer::query()->findOrFail($opId);
        $targetId = trim($supplyId);
        $items = $this->store->read($opId);

        $filtered = array_values(array_filter($items, function (array $item) use ($targetId) {
            return (string) ($item['id'] ?? '') !== $targetId;
        }));

        $this->store->write($opId, $filtered);
        return response()->json($filtered);
    }

    private function nullableText(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $text = trim($value);
        return $text !== '' ? $text : null;
    }
}

