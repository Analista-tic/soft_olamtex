<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\Production\ProductionOrder;
use App\Services\Production\TechnicalDataSheetRollsStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class TechnicalDataSheetRollsController extends Controller
{
    public function __construct(
        private TechnicalDataSheetRollsStore $store,
    ) {
    }

    public function index(int $opId): JsonResponse
    {
        ProductionOrder::query()->findOrFail($opId);
        $payload = $this->store->read($opId);
        $lines = is_array($payload['lines'] ?? null) ? $payload['lines'] : [];

        return response()->json([
            'params' => is_array($payload['params'] ?? null) ? $payload['params'] : [
                'trazo' => 0.8,
                'defaultExtra' => 0.03,
            ],
            'lines' => array_values(array_map(fn(array $line) => $this->normalizeLine($line), $lines)),
        ]);
    }

    public function saveAll(int $opId, Request $request): JsonResponse
    {
        ProductionOrder::query()->findOrFail($opId);

        $validated = $request->validate([
            'rolls' => ['required', 'array'],
            'rolls.*.id' => ['nullable', 'string', 'max:100'],
            'rolls.*.consecutive' => ['nullable', 'string', 'max:120'],
            'rolls.*.type' => ['required', 'in:PRINCIPAL,SECUNDARIA'],
            'rolls.*.fabricName' => ['required', 'string', 'max:200'],
            'rolls.*.averageTrace' => ['nullable', 'numeric', 'min:0'],
            'rolls.*.factor' => ['nullable', 'numeric', 'min:0'],
            'rolls.*.consumptionBase' => ['required', 'numeric', 'min:0'],
            'rolls.*.pricePerMeter' => ['required', 'numeric', 'min:0'],
            'rolls.*.programmedMeters' => ['nullable', 'numeric', 'min:0'],
            'rolls.*.colors' => ['nullable', 'string', 'max:200'],
            'rolls.*.availableMeters' => ['nullable', 'numeric', 'min:0'],
            'rolls.*.balanceMeters' => ['nullable', 'numeric'],
            'rolls.*.color' => ['nullable', 'string', 'max:120'],
            'rolls.*.colorCode' => ['nullable', 'string', 'max:120'],
            'rolls.*.widthCm' => ['nullable', 'numeric', 'min:0'],
            'rolls.*.supplier' => ['nullable', 'string', 'max:200'],
            'rolls.*.notes' => ['nullable', 'string', 'max:1000'],
            'rolls.*.rawMaterialUid' => ['nullable', 'string', 'max:100'],
            'rolls.*.fabricItemId' => ['nullable'],
            'rolls.*.rollId' => ['nullable'],
            'rolls.*.location' => ['nullable', 'string', 'max:100'],
            'deletedIds' => ['nullable', 'array'],
            'deletedIds.*' => ['string', 'max:100'],
        ]);

        $payload = $this->store->read($opId);
        $existing = is_array($payload['lines'] ?? null) ? $payload['lines'] : [];
        $existingById = [];
        foreach ($existing as $line) {
            $lineId = trim((string) ($line['id'] ?? ''));
            if ($lineId === '') {
                continue;
            }
            $existingById[$lineId] = $line;
        }

        $deletedIds = collect($validated['deletedIds'] ?? [])
            ->map(fn($id) => trim((string) $id))
            ->filter(fn($id) => $id !== '')
            ->values()
            ->all();
        $deletedLookup = array_fill_keys($deletedIds, true);

        $now = Carbon::now()->toISOString();
        $nextLines = [];
        foreach ($validated['rolls'] as $row) {
            $rawId = trim((string) ($row['id'] ?? ''));
            $lineId = $rawId !== '' ? $rawId : (string) Str::uuid();
            if (isset($deletedLookup[$lineId])) {
                continue;
            }

            $previous = $existingById[$lineId] ?? null;
            $nextLines[] = [
                'id' => $lineId,
                'consecutive' => $this->nullableText($row['consecutive'] ?? null),
                'type' => (string) $row['type'],
                'fabricName' => trim((string) $row['fabricName']),
                'averageTrace' => $this->nullableNumber($row['averageTrace'] ?? null),
                'factor' => $this->nullableNumber($row['factor'] ?? null),
                'consumptionBase' => (float) $row['consumptionBase'],
                'pricePerMeter' => (float) $row['pricePerMeter'],
                'programmedMeters' => $this->nullableNumber($row['programmedMeters'] ?? null),
                'colors' => $this->nullableText($row['colors'] ?? null),
                'availableMeters' => $this->nullableNumber($row['availableMeters'] ?? null),
                'balanceMeters' => $this->nullableNumber($row['balanceMeters'] ?? null),
                'color' => $this->nullableText($row['color'] ?? null),
                'colorCode' => $this->nullableText($row['colorCode'] ?? null),
                'widthCm' => $this->nullableNumber($row['widthCm'] ?? null),
                'supplier' => $this->nullableText($row['supplier'] ?? null),
                // Mantener compatibilidad con calculo legacy en costos:
                // consumoAjustado = consumptionBase / trazo + extraMeters.
                'extraMeters' => is_numeric($row['extraMeters'] ?? null) ? (float) $row['extraMeters'] : 0.0,
                'notes' => $this->nullableText($row['notes'] ?? null),
                'rawMaterialUid' => $this->nullableText($row['rawMaterialUid'] ?? null),
                'fabricItemId' => $row['fabricItemId'] ?? null,
                'rollId' => $row['rollId'] ?? null,
                'location' => $this->nullableText($row['location'] ?? null),
                'updatedAt' => $now,
                'createdAt' => $previous['createdAt'] ?? $now,
            ];
        }

        $payload['lines'] = array_values($nextLines);
        $this->store->write($opId, $payload);

        return response()->json([
            'params' => is_array($payload['params'] ?? null) ? $payload['params'] : [
                'trazo' => 0.8,
                'defaultExtra' => 0.03,
            ],
            'lines' => array_values(array_map(fn(array $line) => $this->normalizeLine($line), $payload['lines'])),
        ]);
    }

    public function saveParams(int $opId, Request $request): JsonResponse
    {
        ProductionOrder::query()->findOrFail($opId);

        $validated = $request->validate([
            'trazo' => ['required', 'numeric', 'gt:0.01', 'lte:1'],
            'defaultExtra' => ['required', 'numeric', 'min:0'],
        ]);

        $payload = $this->store->read($opId);
        $payload['params'] = [
            'trazo' => (float) $validated['trazo'],
            'defaultExtra' => (float) $validated['defaultExtra'],
        ];

        $this->store->write($opId, $payload);
        return response()->json($payload);
    }

    public function upsertLine(int $opId, Request $request): JsonResponse
    {
        ProductionOrder::query()->findOrFail($opId);

        $validated = $request->validate([
            'id' => ['nullable', 'string', 'max:100'],
            'type' => ['required', 'in:PRINCIPAL,SECUNDARIA'],
            'fabricName' => ['required', 'string', 'max:200'],
            'color' => ['nullable', 'string', 'max:120'],
            'widthCm' => ['nullable', 'numeric', 'min:0'],
            'supplier' => ['nullable', 'string', 'max:200'],
            'pricePerMeter' => ['required', 'numeric', 'min:0'],
            'consumptionBase' => ['required', 'numeric', 'min:0'],
            'extraMeters' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'rawMaterialUid' => ['nullable', 'string', 'max:100'],
            'fabricItemId' => ['nullable'],
            'rollId' => ['nullable'],
        ]);

        $payload = $this->store->read($opId);
        $lines = is_array($payload['lines'] ?? null) ? $payload['lines'] : [];

        $targetId = isset($validated['id']) && trim((string) $validated['id']) !== ''
            ? trim((string) $validated['id'])
            : (string) Str::uuid();
        $now = Carbon::now()->toISOString();

        $lineData = [
            'id' => $targetId,
            'type' => (string) $validated['type'],
            'fabricName' => trim((string) $validated['fabricName']),
            'color' => $this->nullableText($validated['color'] ?? null),
            'widthCm' => $this->nullableNumber($validated['widthCm'] ?? null),
            'supplier' => $this->nullableText($validated['supplier'] ?? null),
            'pricePerMeter' => (float) $validated['pricePerMeter'],
            'consumptionBase' => (float) $validated['consumptionBase'],
            'extraMeters' => (float) $validated['extraMeters'],
            'notes' => $this->nullableText($validated['notes'] ?? null),
            'rawMaterialUid' => $this->nullableText($validated['rawMaterialUid'] ?? null),
            'fabricItemId' => $validated['fabricItemId'] ?? null,
            'rollId' => $validated['rollId'] ?? null,
            'updatedAt' => $now,
        ];

        $updated = false;
        foreach ($lines as &$line) {
            if ((string) ($line['id'] ?? '') !== $targetId) {
                continue;
            }
            $line = [
                ...$line,
                ...$lineData,
                'createdAt' => $line['createdAt'] ?? $now,
            ];
            $updated = true;
            break;
        }
        unset($line);

        if (!$updated) {
            $lines[] = [
                ...$lineData,
                'createdAt' => $now,
            ];
        }

        $payload['lines'] = array_values($lines);
        $this->store->write($opId, $payload);

        return response()->json($payload);
    }

    public function destroyLine(int $opId, string $lineId): JsonResponse
    {
        ProductionOrder::query()->findOrFail($opId);

        $payload = $this->store->read($opId);
        $lines = is_array($payload['lines'] ?? null) ? $payload['lines'] : [];
        $targetId = trim($lineId);

        $payload['lines'] = array_values(array_filter($lines, function (array $line) use ($targetId) {
            return (string) ($line['id'] ?? '') !== $targetId;
        }));

        $this->store->write($opId, $payload);
        return response()->json($payload);
    }

    private function nullableText(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $text = trim($value);
        return $text !== '' ? $text : null;
    }

    private function nullableNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return is_numeric($value) ? (float) $value : null;
    }

    private function normalizeLine(array $line): array
    {
        $averageTrace = $this->nullableNumber($line['averageTrace'] ?? null);
        $factor = $this->nullableNumber($line['factor'] ?? null) ?? 0.03;
        $consumption = is_numeric($line['consumptionBase'] ?? null)
            ? (float) $line['consumptionBase']
            : ($averageTrace !== null ? ($averageTrace * $factor) : 0.0);
        $price = is_numeric($line['pricePerMeter'] ?? null) ? (float) $line['pricePerMeter'] : 0.0;
        $programmed = $this->nullableNumber($line['programmedMeters'] ?? null);
        $available = $this->nullableNumber($line['availableMeters'] ?? null);
        $balance = $this->nullableNumber($line['balanceMeters'] ?? null);

        if ($balance === null && $available !== null && $programmed !== null) {
            $balance = $available - $programmed;
        }

        return [
            'id' => (string) ($line['id'] ?? Str::uuid()),
            'consecutive' => $this->nullableText($line['consecutive'] ?? null),
            'type' => ($line['type'] ?? 'PRINCIPAL') === 'SECUNDARIA' ? 'SECUNDARIA' : 'PRINCIPAL',
            'fabricName' => trim((string) ($line['fabricName'] ?? 'Tela')),
            'averageTrace' => $averageTrace,
            'factor' => $factor,
            'consumptionBase' => $consumption,
            'pricePerMeter' => $price,
            'programmedMeters' => $programmed,
            'colors' => $this->nullableText($line['colors'] ?? $line['color'] ?? null),
            'availableMeters' => $available,
            'balanceMeters' => $balance,
            'color' => $this->nullableText($line['color'] ?? null),
            'colorCode' => $this->nullableText($line['colorCode'] ?? null),
            'widthCm' => $this->nullableNumber($line['widthCm'] ?? null),
            'supplier' => $this->nullableText($line['supplier'] ?? null),
            'extraMeters' => is_numeric($line['extraMeters'] ?? null) ? (float) $line['extraMeters'] : 0.0,
            'notes' => $this->nullableText($line['notes'] ?? null),
            'rawMaterialUid' => $this->nullableText($line['rawMaterialUid'] ?? null),
            'fabricItemId' => $line['fabricItemId'] ?? null,
            'rollId' => $line['rollId'] ?? null,
            'location' => $this->nullableText($line['location'] ?? null),
            'createdAt' => $line['createdAt'] ?? null,
            'updatedAt' => $line['updatedAt'] ?? null,
        ];
    }
}
