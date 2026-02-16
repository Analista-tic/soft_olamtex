<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\ProductSheet\Programmer;
use App\Services\Production\TechnicalDataSheetThreadsStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class TechnicalDataSheetThreadsController extends Controller
{
    public function __construct(
        private TechnicalDataSheetThreadsStore $store,
    ) {
    }

    public function index(int $opId): JsonResponse
    {
        Programmer::query()->findOrFail($opId);

        $payload = $this->store->read($opId);
        $lines = is_array($payload['lines'] ?? null) ? $payload['lines'] : [];

        return response()->json([
            'lines' => array_values(array_map(fn(array $line) => $this->normalizeLine($line), $lines)),
        ]);
    }

    public function saveAll(int $opId, Request $request): JsonResponse
    {
        Programmer::query()->findOrFail($opId);

        $validated = $request->validate([
            'threads' => ['required', 'array'],
            'threads.*.id' => ['nullable', 'string', 'max:100'],
            'threads.*.threadName' => ['required', 'string', 'max:200'],
            'threads.*.gauge' => ['nullable', 'numeric', 'min:0'],
            'threads.*.brand' => ['nullable', 'string', 'max:200'],
            'threads.*.color' => ['nullable', 'string', 'max:120'],
            'threads.*.colorCode' => ['nullable', 'string', 'max:120'],
            'threads.*.stitchesPerInch' => ['nullable', 'numeric', 'min:0'],
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
        foreach ($validated['threads'] as $row) {
            $rawId = trim((string) ($row['id'] ?? ''));
            $lineId = $rawId !== '' ? $rawId : (string) Str::uuid();
            if (isset($deletedLookup[$lineId])) {
                continue;
            }

            $previous = $existingById[$lineId] ?? null;
            $nextLines[] = [
                'id' => $lineId,
                'threadName' => trim((string) $row['threadName']),
                'gauge' => $this->nullableNumber($row['gauge'] ?? null),
                'brand' => $this->nullableText($row['brand'] ?? null),
                'color' => $this->nullableText($row['color'] ?? null),
                'colorCode' => $this->nullableText($row['colorCode'] ?? null),
                'stitchesPerInch' => $this->nullableNumber($row['stitchesPerInch'] ?? null),
                'createdAt' => $previous['createdAt'] ?? $now,
                'updatedAt' => $now,
            ];
        }

        $payload['lines'] = array_values($nextLines);
        $this->store->write($opId, $payload);

        return response()->json([
            'lines' => array_values(array_map(fn(array $line) => $this->normalizeLine($line), $payload['lines'])),
        ]);
    }

    public function saveParams(int $opId, Request $request): JsonResponse
    {
        Programmer::query()->findOrFail($opId);

        $validated = $request->validate([
            'defaultYear' => ['required', 'integer', 'min:2000', 'max:2100'],
            'method' => ['nullable', 'in:PER_GARMENT'],
        ]);

        $payload = $this->store->read($opId);
        $payload['params'] = [
            'defaultYear' => (int) $validated['defaultYear'],
            'method' => 'PER_GARMENT',
        ];

        $this->store->write($opId, $payload);
        return response()->json($payload);
    }

    public function upsertLine(int $opId, Request $request): JsonResponse
    {
        Programmer::query()->findOrFail($opId);

        $validated = $request->validate([
            'id' => ['nullable', 'string', 'max:100'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'threadName' => ['required', 'string', 'max:200'],
            'color' => ['nullable', 'string', 'max:120'],
            'supplier' => ['nullable', 'string', 'max:200'],
            'costPerGarment' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'threadItemId' => ['nullable'],
        ]);

        $payload = $this->store->read($opId);
        $lines = is_array($payload['lines'] ?? null) ? $payload['lines'] : [];
        $targetId = isset($validated['id']) && trim((string) $validated['id']) !== ''
            ? trim((string) $validated['id'])
            : (string) Str::uuid();
        $now = Carbon::now()->toISOString();

        $lineData = [
            'id' => $targetId,
            'year' => (int) $validated['year'],
            'threadName' => trim((string) $validated['threadName']),
            'color' => $this->nullableText($validated['color'] ?? null),
            'supplier' => $this->nullableText($validated['supplier'] ?? null),
            'costPerGarment' => (float) $validated['costPerGarment'],
            'notes' => $this->nullableText($validated['notes'] ?? null),
            'threadItemId' => $validated['threadItemId'] ?? null,
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
        Programmer::query()->findOrFail($opId);

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
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function normalizeLine(array $line): array
    {
        return [
            'id' => (string) ($line['id'] ?? Str::uuid()),
            'threadName' => trim((string) ($line['threadName'] ?? '')),
            'gauge' => $this->nullableNumber($line['gauge'] ?? null),
            // Compatibilidad con payload historico: supplier -> brand.
            'brand' => $this->nullableText($line['brand'] ?? $line['supplier'] ?? null),
            'color' => $this->nullableText($line['color'] ?? null),
            'colorCode' => $this->nullableText($line['colorCode'] ?? null),
            // Compatibilidad con payload historico: costPerGarment -> stitchesPerInch solo para no perder dato.
            'stitchesPerInch' => $this->nullableNumber($line['stitchesPerInch'] ?? $line['costPerGarment'] ?? null),
            'createdAt' => $line['createdAt'] ?? null,
            'updatedAt' => $line['updatedAt'] ?? null,
        ];
    }
}
