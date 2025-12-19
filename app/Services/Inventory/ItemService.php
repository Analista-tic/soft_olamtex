<?php

namespace App\Services\Inventory;

use App\Services\Inventory\AdditionalFieldService;
use App\Models\Common\CategorySubGroup;
use App\Models\Expenses\PurchaseDocumentItem;
use App\Models\Expenses\PurchaseOrderItem;
use App\Models\Inventory\InventoryAdjustmentLine;
use App\Models\Inventory\Item;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;

class ItemService
{
    protected array $orderedAdditionalFieldNamesCache = [];

    public function __construct(protected AdditionalFieldService $fieldService) {}

    public function paginate(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $query = Item::with(['categorySubGroup', 'tax', 'warehouses']);

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('bar_code', 'like', "%{$search}%")
                    ->orWhereHas('categorySubGroup', fn($sg) => $sg->where('name', 'like', "%{$search}%"));
            });
        }

        if (isset($filters['warehouse_id']) && $filters['warehouse_id'] !== '') {
            $warehouseId = (int) $filters['warehouse_id'];
            $query->whereHas('warehouses', fn($q) => $q->where('warehouse_id', $warehouseId));
        }

        if (array_key_exists('status', $filters) && $filters['status'] !== '') {
            $query->where('status', filter_var($filters['status'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['item_type'])) {
            $query->where('item_type', $filters['item_type']);
        }

        return $query->paginate($perPage);
    }

    public function findWithRelations(int $id, array $relations = ['categorySubGroup', 'tax']): Item
    {
        return Item::with($relations)->findOrFail($id);
    }

    public function find(int $id): Item
    {
        return Item::findOrFail($id);
    }

    public function create(array $data, array $warehouses = []): Item
    {
        $payload = $this->buildCreatePayload($data);
        $article = Item::create($payload);
        $this->syncWarehouses($article, $warehouses);

        return $article;
    }

    public function update(Item $article, array $data, ?array $warehouses = null): Item
    {
        $payload = $this->buildUpdatePayload($article, $data);
        $article->update($payload);
        if ($warehouses !== null) {
            $this->syncWarehouses($article, $warehouses, true);
        }

        return $article;
    }

    public function delete(Item $article): void
    {
        $reasons = $this->gatherDeletionReasons($article);
        if (!empty($reasons)) {
            $message = $this->formatReasonList($reasons);
            throw new \RuntimeException("No se puede eliminar el item porque tiene {$message}.");
        }

        $article->warehouses()->detach();

        try {
            $article->delete();
        } catch (QueryException $e) {
            if ((int) $e->getCode() === 23000) {
                throw new \RuntimeException('No se puede eliminar el item porque tiene registros relacionados (ajustes de inventario, compras u otros movimientos). Elimine o reasigne esos registros primero.');
            }
            throw $e;
        }
    }

    public function toggleStatus(Item $article): bool
    {
        $article->status = !$article->status;
        $article->save();
        return $article->status;
    }

    public function orderedAdditionalFieldNames(): array
    {
        if ($this->orderedAdditionalFieldNamesCache === []) {
            $this->orderedAdditionalFieldNamesCache = $this->fieldService->orderedNames();
        }
        return $this->orderedAdditionalFieldNamesCache;
    }

    public function buildExtraFieldsNameSegment(array $extraFields, ?array $orderedNames = null): string
    {
        $segments = [];
        $handled = [];
        $normalize = function ($value) {
            if (!is_string($value)) {
                return '';
            }
            return trim($value);
        };

        $extraFieldsList = [];
        $extraFieldsMap = [];

        foreach ($extraFields as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            $normalizedKey = $normalize($key);
            if ($normalizedKey === '') {
                continue;
            }
            $upperKey = mb_strtoupper($normalizedKey);
            $trimmedValue = $normalize((string) ($value ?? ''));
            $extraFieldsList[] = [
                'upper' => $upperKey,
                'value' => $trimmedValue,
            ];
            if (!isset($extraFieldsMap[$upperKey])) {
                $extraFieldsMap[$upperKey] = $trimmedValue;
            }
        }

        $names = $orderedNames ?? $this->orderedAdditionalFieldNames();
        foreach ($names as $name) {
            $normalizedName = $normalize($name);
            if ($normalizedName === '') {
                continue;
            }
            $upperName = mb_strtoupper($normalizedName);
            $handled[] = $upperName;
            $value = $extraFieldsMap[$upperName] ?? null;
            if ($value !== null && $value !== '') {
                $segments[] = $value;
            }
        }

        foreach ($extraFieldsList as $entry) {
            if (in_array($entry['upper'], $handled, true)) {
                continue;
            }
            if ($entry['value'] !== '') {
                $segments[] = $entry['value'];
            }
        }

        return implode(' ', $segments);
    }

    public function extractColorNameFromExtraFields(array $extraFields): string
    {
        foreach ($extraFields as $key => $value) {
            if (is_string($key) && strcasecmp(trim($key), 'color') === 0) {
                return trim((string) ($value ?? ''));
            }
        }

        return '';
    }

    public function normalizeExtraFields(mixed $value, array $fallback = []): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            }
        }

        if (is_array($value)) {
            return $value;
        }

        return $fallback;
    }

    /**
     * Procesa filas extraídas de una hoja de cálculo para carga masiva.
     * Espera que la fila 1 contenga encabezados (nombres en mayúsculas en español).
     * Retorna arreglo con 'inserted' y 'errors'.
     *
     * @param array $rows
     * @return array
     */
    public function processBulkUploadArray(array $rows): array
    {
        if (empty($rows) || !isset($rows[1])) {
            return ['inserted' => 0, 'errors' => ['No hay datos en el archivo']];
        }

        $header = array_map('trim', $rows[1]);
        unset($rows[1]);

        $inserted = 0;
        $errors = [];

        $extraFieldsKeys = [
            'COLOR',
            'BASE TELA',
            'BASE HILAZA',
            'CAMPO ADICIONAL',
            'MARCA',
            'MUNDO',
            'LÍNEAS',
            'TIPO PUNTERA',
            'TALLA',
            'MEDIDA'
        ];

        $orderedFieldNames = $this->orderedAdditionalFieldNames();

        foreach ($rows as $rowNum => $row) {
            if (empty(array_filter($row))) {
                continue;
            }

            try {
                $rowData = [];
                foreach ($header as $colIdx => $colName) {
                    $rowData[$colName] = $row[$colIdx] ?? null;
                }

                $subGroupName = $rowData['SUBGRUPO'] ?? '';
                $subGroup = CategorySubGroup::where('name', $subGroupName)->first();
                if (!$subGroup) {
                    $errors[] = "Fila $rowNum: Subgrupo '$subGroupName' no encontrado";
                    continue;
                }

                $extraFields = [];
                foreach ($extraFieldsKeys as $key) {
                    if (isset($rowData[$key]) && $rowData[$key] !== '') {
                        $extraFields[$key] = $rowData[$key];
                    }
                }

                $extrasString = $this->buildExtraFieldsNameSegment($extraFields, $orderedFieldNames);
                $colorValue = $this->extractColorNameFromExtraFields($extraFields);
                $nameParts = array_filter([
                    optional($subGroup)->name,
                    $extrasString,
                    $colorValue,
                ]);
                $name = trim(implode(' ', $nameParts));

                $articleData = [
                    'category_sub_group_id' => $subGroup->id,
                    'tax' => isset($rowData['IMPUesta (%)']) ? $rowData['IMPUesta (%)'] : null,
                    'bar_code' => $rowData['CÓDIGO DE BARRAS'] ?? null,
                    'negative_movement' => filter_var($rowData['MOVIMIENTO NEGATIVO'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
                    'description' => $rowData['DESCRIPCIÓN'] ?? '',
                    'extra_fields' => $extraFields,
                    'name' => $name,
                    'reference' => $rowData['REFERENCIA'] ?? '',
                    'status' => true,
                    'item_type' => 'product',
                    'unit_measure' => $rowData['UNIDAD DE MEDIDA'] ?? null,
                ];

                $this->create($articleData, []);
                $inserted++;
            } catch (\Throwable $e) {
                $errors[] = "Fila $rowNum: " . $e->getMessage();
            }
        }

        return ['inserted' => $inserted, 'errors' => $errors];
    }

    public function resolveWarehousePayload(mixed $payload): array
    {
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                return [];
            }
            $payload = $decoded;
        }

        if (!is_array($payload)) {
            return [];
        }

        $warehouses = [];
        foreach ($payload as $warehouse) {
            if (!is_array($warehouse)) {
                continue;
            }

            $warehouseId = $warehouse['warehouse_id'] ?? $warehouse['warehouseId'] ?? null;
            if (empty($warehouseId)) {
                continue;
            }

            $warehouses[] = [
                'warehouse_id' => (int) $warehouseId,
                'quantity' => is_numeric($warehouse['quantity'] ?? null) ? (int) $warehouse['quantity'] : 0,
                'stock_min' => is_numeric($warehouse['stock_min'] ?? null) ? (int) $warehouse['stock_min'] : 0,
                'stock_max' => is_numeric($warehouse['stock_max'] ?? null) ? (int) $warehouse['stock_max'] : 0,
            ];
        }

        return $warehouses;
    }

    public function warehouseStocks(Item $article)
    {
        return $article->getWarehouseStocks();
    }

    protected function buildCreatePayload(array $data): array
    {
        $extraFields = $this->normalizeExtraFields($data['extra_fields'] ?? []);
        $payload = [
            'category_sub_group_id' => $data['category_sub_group_id'],
            'img' => $data['img'] ?? null,
            'tax_id' => $data['tax_id'] ?? null,
            'tax' => $data['tax'],
            'bar_code' => $data['bar_code'] ?? null,
            'negative_movement' => filter_var($data['negative_movement'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
            'description' => $data['description'],
            'extra_fields' => $extraFields,
            'reference' => $data['reference'],
            'status' => array_key_exists('status', $data) ? (bool) $data['status'] : true,
            'item_type' => $data['item_type'] ?? 'product',
            'unit_measure' => $data['unit_measure'] ?? null,
        ];

        $payload['name'] = $this->buildName($payload['category_sub_group_id'], $extraFields);

        return $payload;
    }

    protected function buildUpdatePayload(Item $article, array $data): array
    {
        $payload = [];

        $fields = [
            'category_sub_group_id',
            'img',
            'tax_id',
            'tax',
            'bar_code',
            'description',
            'reference',
            'unit_measure',
        ];

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        if (array_key_exists('negative_movement', $data)) {
            $payload['negative_movement'] = filter_var($data['negative_movement'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }

        if (array_key_exists('status', $data)) {
            $payload['status'] = (bool) $data['status'];
        }

        if (array_key_exists('item_type', $data)) {
            $payload['item_type'] = $data['item_type'];
        }

        $extraFields = array_key_exists('extra_fields', $data)
            ? $this->normalizeExtraFields($data['extra_fields'])
            : ($article->extra_fields ?? []);
        $payload['extra_fields'] = $extraFields;

        $subGroupId = $data['category_sub_group_id'] ?? $article->category_sub_group_id;
        $payload['name'] = $this->buildName($subGroupId, $extraFields);

        return $payload;
    }

    protected function buildName(?int $subGroupId, array $extraFields): string
    {
        $subGroupName = optional(CategorySubGroup::find($subGroupId))->name ?? '';
        $extrasString = $this->buildExtraFieldsNameSegment($extraFields);
        return trim("{$subGroupName} {$extrasString}");
    }

    protected function syncWarehouses(Item $article, array $warehouses, bool $replace = false): void
    {
        $payload = [];
        foreach ($warehouses as $warehouse) {
            $payload[$warehouse['warehouse_id']] = [
                'quantity' => $warehouse['quantity'] ?? 0,
                'stock_min' => $warehouse['stock_min'] ?? 0,
                'stock_max' => $warehouse['stock_max'] ?? 0,
            ];
        }

        if ($replace) {
            $article->warehouses()->sync($payload);
            return;
        }

        foreach ($payload as $warehouseId => $values) {
            $article->warehouses()->attach($warehouseId, $values);
        }
    }

    protected function gatherDeletionReasons(Item $article): array
    {
        $reasons = [];

        $warehouseCount = $article->warehouses()->count();
        if ($warehouseCount > 0) {
            $reasons[] = $warehouseCount === 1 ? '1 bodega asignada' : "{$warehouseCount} bodegas asignadas";
        }

        $poCount = PurchaseOrderItem::where('item_id', $article->id)->count();
        if ($poCount > 0) {
            $reasons[] = $poCount === 1 ? '1 orden de compra' : "{$poCount} ordenes de compra";
        }

        $invoiceCount = PurchaseDocumentItem::where('item_id', $article->id)->count();
        if ($invoiceCount > 0) {
            $reasons[] = $invoiceCount === 1 ? '1 factura de compra' : "{$invoiceCount} facturas de compra";
        }

        $adjustmentCount = InventoryAdjustmentLine::where('item_id', $article->id)->count();
        if ($adjustmentCount > 0) {
            $reasons[] = $adjustmentCount === 1 ? '1 movimiento de inventario' : "{$adjustmentCount} movimientos de inventario";
        }

        return $reasons;
    }

    protected function formatReasonList(array $reasons): string
    {
        if (count($reasons) === 1) {
            return $reasons[0];
        }

        return implode(', ', array_slice($reasons, 0, -1)) . ' y ' . end($reasons);
    }
}
