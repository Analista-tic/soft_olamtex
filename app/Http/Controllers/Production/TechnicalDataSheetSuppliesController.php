<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\MasterData\Item;
use App\Models\Production\Programmer;
use App\Services\Production\TechnicalDataSheetItemOptionsService;
use App\Services\Production\TechnicalDataSheetSuppliesStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class TechnicalDataSheetSuppliesController extends Controller
{
    public function __construct(
        private TechnicalDataSheetSuppliesStore $store,
        private TechnicalDataSheetItemOptionsService $itemOptionsService,
    ) {
    }

    public function index(int $opId): JsonResponse
    {
        Programmer::query()->findOrFail($opId);
        return response()->json($this->store->read($opId));
    }

    public function itemOptions(Request $request): JsonResponse
    {
        $group = trim((string) $request->query('group', ''));
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', 'habilitado'));
        $items = $this->itemOptionsService->getOptions($group, $search, $status);

        return response()->json($items);
    }

    public function lastPurchaseForItem(int $itemId): JsonResponse
    {
        $item = Item::query()->findOrFail($itemId);

        return response()->json([
            'data' => $this->store->getLastPurchaseForItem((int) $item->id),
        ]);
    }

    public function itemMeta(int $itemId): JsonResponse
    {
        $item = Item::query()->findOrFail($itemId);

        return response()->json([
            'data' => $this->store->getItemMeta((int) $item->id),
        ]);
    }

    public function upsert(int $opId, Request $request): JsonResponse
    {
        Programmer::query()->findOrFail($opId);

        $validated = $request->validate($this->singleItemRules());

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

            $item = $this->normalizeItemPayload($validated, $targetId, $item['createdAt'] ?? $now, $now);
            $updated = true;
            break;
        }
        unset($item);

        if (!$updated) {
            $items[] = $this->normalizeItemPayload($validated, $targetId, $now, $now);
        }

        $this->store->write($opId, $items);
        return response()->json($items);
    }

    public function saveAll(int $opId, Request $request): JsonResponse
    {
        Programmer::query()->findOrFail($opId);

        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['nullable', 'string', 'max:100'],
            'items.*.name' => ['required', 'string', 'max:200'],
            'items.*.stage' => ['required', 'in:GENERAL,CORTE,CONFECCION,TERMINACION,OTRO'],
            'items.*.sequence' => ['nullable', 'integer', 'min:1'],
            'items.*.displayLabel' => ['nullable', 'string', 'max:100'],
            'items.*.itemId' => ['nullable', 'integer'],
            'items.*.requestedName' => ['nullable', 'string', 'max:200'],
            'items.*.supplier' => ['nullable', 'string', 'max:200'],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.modaLinea' => ['nullable', 'in:MODA,LINEA,NA'],
            'items.*.uom' => ['nullable', 'string', 'max:60'],
            'items.*.qtyPerUnit' => ['required', 'numeric', 'min:0'],
            'items.*.unitCost' => ['required', 'numeric', 'min:0'],
            'items.*.internalRequestId' => ['nullable', 'integer'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $now = Carbon::now()->toISOString();
        $existingItems = $this->store->read($opId);
        $existingById = collect($existingItems)->keyBy(fn (array $item) => (string) ($item['id'] ?? ''));

        $normalized = collect($validated['items'])
            ->values()
            ->map(function (array $item) use ($existingById, $now) {
                $id = isset($item['id']) && trim((string) $item['id']) !== ''
                    ? trim((string) $item['id'])
                    : (string) Str::uuid();

                $createdAt = (string) ($existingById->get($id)['createdAt'] ?? $now);
                return $this->normalizeItemPayload($item, $id, $createdAt, $now);
            })
            ->all();

        $this->store->write($opId, $normalized);
        return response()->json($normalized);
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

    private function singleItemRules(): array
    {
        return [
            'id' => ['nullable', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:200'],
            'stage' => ['required', 'in:GENERAL,CORTE,CONFECCION,TERMINACION,OTRO'],
            'sequence' => ['nullable', 'integer', 'min:1'],
            'displayLabel' => ['nullable', 'string', 'max:100'],
            'itemId' => ['nullable', 'integer'],
            'requestedName' => ['nullable', 'string', 'max:200'],
            'supplier' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'modaLinea' => ['nullable', 'in:MODA,LINEA,NA'],
            'uom' => ['nullable', 'string', 'max:60'],
            'qtyPerUnit' => ['required', 'numeric', 'min:0'],
            'unitCost' => ['required', 'numeric', 'min:0'],
            'internalRequestId' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function normalizeItemPayload(array $item, string $id, string $createdAt, string $updatedAt): array
    {
        return [
            'id' => $id,
            'name' => trim((string) $item['name']),
            'stage' => (string) $item['stage'],
            'sequence' => isset($item['sequence']) ? (int) $item['sequence'] : null,
            'displayLabel' => $this->nullableText($item['displayLabel'] ?? null),
            'itemId' => isset($item['itemId']) ? (int) $item['itemId'] : null,
            'requestedName' => $this->nullableText($item['requestedName'] ?? null),
            'supplier' => $this->nullableText($item['supplier'] ?? null),
            'description' => $this->nullableText($item['description'] ?? null),
            'modaLinea' => isset($item['modaLinea']) ? (string) $item['modaLinea'] : 'NA',
            'uom' => $this->nullableText($item['uom'] ?? null),
            'qtyPerUnit' => (float) $item['qtyPerUnit'],
            'unitCost' => (float) $item['unitCost'],
            'internalRequestId' => isset($item['internalRequestId']) ? (int) $item['internalRequestId'] : null,
            'notes' => $this->nullableText($item['notes'] ?? null),
            'createdAt' => $createdAt,
            'updatedAt' => $updatedAt,
        ];
    }
}
