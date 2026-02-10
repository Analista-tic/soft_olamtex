<?php

namespace App\Services\ProductSheet;

use App\Models\ProductSheet\ProductSheet;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class ProductSheetCreationService
{
    private const TYPE_SAMPLE = 'muestra';

    public function __construct(private ProgrammerService $programmerService)
    {
    }

    public function create(array $attributes): ProductSheet
    {
        $attributes = $this->sanitizeAttributes($attributes);
        $this->logPersistencePayload('create', $attributes);

        $productSheet = ProductSheet::create($attributes);

        if ($productSheet->type === self::TYPE_SAMPLE) {
            $this->programmerService->createFromSample($productSheet);
        }

        return $productSheet;
    }

    public function sanitizeAttributes(array $attributes): array
    {
        $removedKeys = [];
        if (array_key_exists('flat_sketch', $attributes)) {
            unset($attributes['flat_sketch']);
            $removedKeys[] = 'flat_sketch';
        }

        $allowed = (new ProductSheet())->getFillable();
        $sanitized = Arr::only($attributes, $allowed);

        $discardedUnknownKeys = array_values(array_diff(array_keys($attributes), array_keys($sanitized)));
        if (!empty($discardedUnknownKeys)) {
            $removedKeys = array_values(array_unique(array_merge($removedKeys, $discardedUnknownKeys)));
        }

        if (!empty($removedKeys)) {
            Log::warning('product_sheet_persistence_sanitized_keys', [
                'removed_keys' => $removedKeys,
            ]);
        }

        return $sanitized;
    }

    private function logPersistencePayload(string $action, array $attributes): void
    {
        Log::debug('product_sheet_persistence_payload', [
            'action' => $action,
            'keys' => array_keys($attributes),
            'contains_flat_sketch' => array_key_exists('flat_sketch', $attributes),
            'contains_flat_sketch_path' => array_key_exists('flat_sketch_path', $attributes),
        ]);
    }
}
