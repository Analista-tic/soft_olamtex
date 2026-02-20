<?php

namespace App\Services\ProductSheet;

use App\Models\Production\ProductSheet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductSheetPersistenceService
{
    public function normalizeSizes(mixed $sizesValue): string
    {
        if (is_array($sizesValue)) {
            return json_encode($this->normalizeSizesArray($sizesValue), JSON_UNESCAPED_UNICODE);
        }

        if (!is_string($sizesValue)) {
            return json_encode([], JSON_UNESCAPED_UNICODE);
        }

        $decoded = json_decode($sizesValue, true);
        if (!is_array($decoded)) {
            return $sizesValue;
        }

        return json_encode($this->normalizeSizesArray($decoded), JSON_UNESCAPED_UNICODE);
    }

    public function prepareFlatSketchUpload(Request $request, array $data, ?ProductSheet $productSheet = null): array
    {
        unset($data['flat_sketch']);

        if (!$request->hasFile('flat_sketch')) {
            unset($data['flat_sketch_path']);
            return $data;
        }

        $existingPath = $productSheet?->flat_sketch_path;
        if ($existingPath) {
            Storage::disk('public')->delete($existingPath);
        }

        $path = Storage::disk('public')->putFile('product_sheets', $request->file('flat_sketch'));
        $data['flat_sketch_path'] = $path;

        return $data;
    }

    public function rollbackFlatSketch(?string $path): void
    {
        if (!is_string($path) || trim($path) === '') {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    public function serializeProductSheet(ProductSheet $productSheet): array
    {
        $data = $productSheet->toArray();
        $data['flat_sketch_path'] = $productSheet->flat_sketch_path ?? null;

        return $data;
    }

    private function normalizeSizesArray(array $sizes): array
    {
        $isAssociative = array_keys($sizes) !== range(0, count($sizes) - 1);
        if (!$isAssociative) {
            return array_values($sizes);
        }

        return array_values(array_filter(
            array_keys($sizes),
            static fn ($key) => ($sizes[$key] ?? false) === true
        ));
    }
}
