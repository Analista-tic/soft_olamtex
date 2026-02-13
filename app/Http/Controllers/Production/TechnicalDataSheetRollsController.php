<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\ProductSheet\Programmer;
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
        Programmer::query()->findOrFail($opId);
        return response()->json($this->store->read($opId));
    }

    public function saveParams(int $opId, Request $request): JsonResponse
    {
        Programmer::query()->findOrFail($opId);

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
        Programmer::query()->findOrFail($opId);

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
        if ($value === null || $value === '') {
            return null;
        }
        return is_numeric($value) ? (float) $value : null;
    }
}

