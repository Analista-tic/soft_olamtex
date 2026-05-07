<?php

namespace App\Services\Production;

use App\Models\Production\ProductSheet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductSheetPersistenceService
{
    /**
     * Normaliza tallas a JSON objeto {talla: boolean} para almacenar correctamente.
     * Retorna array asociativo para que Eloquent (cast array) serialice una sola vez.
     */
    public function normalizeSizes(mixed $sizesValue): array
    {
        return $this->toSizesObject($sizesValue);
    }

    /**
     * Convierte el valor a objeto {talla: boolean} para almacenamiento.
     */
    private function toSizesObject(mixed $sizesValue): array
    {
        if (is_array($sizesValue)) {
            return $this->arrayToSizesObject($sizesValue);
        }

        if (!is_string($sizesValue) || trim($sizesValue) === '') {
            return [];
        }

        $decoded = json_decode($sizesValue, true);
        if (!is_array($decoded)) {
            return [];
        }

        return $this->arrayToSizesObject($decoded);
    }

    /**
     * Convierte array asociativo o secuencial a objeto {talla: boolean}.
     */
    private function arrayToSizesObject(array $sizes): array
    {
        $isAssociative = array_keys($sizes) !== range(0, count($sizes) - 1);

        if ($isAssociative) {
            $result = [];
            foreach ($sizes as $key => $value) {
                $k = is_string($key) ? trim($key) : (string) $key;
                if ($k !== '') {
                    $result[$k] = ($value ?? false) === true;
                }
            }
            return $result;
        }

        return array_fill_keys(
            array_map(static fn($v) => is_string($v) ? trim($v) : (string) $v, array_values($sizes)),
            true,
        );
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

}
