<?php

namespace App\Services\MasterData;

use App\Models\Finance\Tax;
use App\Models\Expenses\PurchaseDocument;
use App\Models\Expenses\PurchaseDocumentItem;
use App\Models\Inventory\InventoryAdjustmentLine;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseItem;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Color;
use App\Models\MasterData\Item;
use App\Models\Settings\Setting;
use App\Support\TextNormalizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ItemService
{
    protected array $orderedAdditionalFieldNamesCache = [];
    protected array $warehouseNameMapCache = [];
    protected array $additionalFieldNameMap = [];
    protected array $unitMeasureNameMap = [];

    public function __construct(protected AdditionalFieldService $fieldService) {}

    public function paginate(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $perPage = max(5, min(2000, (int) $perPage));

        $query = Item::query()->select([
            'id',
            'name',
            'reference',
            'description',
            'status',
            'origin_type',
            'was_edited_after_import',
            'imported_at',
            'import_batch_id',
            'tax_id',
            'category_sub_group_id',
            'color_id',
        ])->with([
            'categorySubGroup:id,name,category_group_id',
            'categorySubGroup.categoryGroup:id,name',
            'tax:id,name,percentage',
            'color:id,name,rgb',
        ]);

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

        if (!empty($filters['origin_type']) && in_array($filters['origin_type'], [Item::ORIGIN_SYSTEM, Item::ORIGIN_MIGRATION], true)) {
            $query->where('origin_type', $filters['origin_type']);
        }

        if (!empty($filters['category_sub_group_id'])) {
            $query->where(
                'category_sub_group_id',
                (int) $filters['category_sub_group_id']
            );
        }

        if (!empty($filters['category_group_id'])) {
            $query->whereHas(
                'categorySubGroup',
                fn($q) => $q->where('category_group_id', (int) $filters['category_group_id'])
            );
        }

        $allowedSorts = ['id'];
        $sortBy = in_array($filters['sort_by'] ?? null, $allowedSorts, true)
            ? (string) $filters['sort_by']
            : 'id';
        $sortDir = ($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage);
    }

    public function findWithRelations(int $id, array $relations = ['categorySubGroup', 'tax']): Item
    {
        return Item::with(array_values(array_unique(array_merge($relations, ['color']))))->findOrFail($id);
    }

    public function find(int $id): Item
    {
        return Item::findOrFail($id);
    }

    public function create(array $data, array $warehouses = []): Item
    {
        $payload = $this->buildCreatePayload($data);
        $item = Item::create($payload);
        $this->syncWarehouses($item, $warehouses);

        return $item;
    }

    public function update(Item $item, array $data, ?array $warehouses = null): Item
    {
        $payload = $this->buildUpdatePayload($item, $data);
        $item->update($payload);
        if ($warehouses !== null) {
            $this->syncWarehouses($item, $warehouses, true);
        }

        return $item;
    }

    public function delete(Item $item): void
    {
        $reasons = $this->gatherDeletionReasons($item);
        if (!empty($reasons)) {
            $message = $this->formatReasonList($reasons);
            throw new \RuntimeException("No se puede eliminar el item porque tiene {$message}.");
        }

        WarehouseItem::where('item_id', $item->id)->delete();

        try {
            $item->delete();
        } catch (QueryException $e) {
            if ((int) $e->getCode() === 23000) {
                throw new \RuntimeException('No se puede eliminar el item porque tiene registros relacionados (ajustes de inventario, compras u otros movimientos). Elimine o reasigne esos registros primero.');
            }
            throw $e;
        }
    }

    public function toggleStatus(Item $item): bool
    {
        $item->status = !$item->status;
        $item->save();
        return $item->status;
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
            $trimmedValue = $normalize(is_scalar($value) ? (string) ($value ?? '') : '');
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
            $normalized = [];

            foreach ($value as $key => $fieldValue) {
                if (!is_string($key)) {
                    continue;
                }

                $fieldName = preg_replace('/\s+/u', ' ', trim($key));
                if ($fieldName === null || $fieldName === '') {
                    continue;
                }

                $normalized[$fieldName] = is_string($fieldValue)
                    ? TextNormalizer::titleCaseWords($fieldValue)
                    : $fieldValue;
            }

            return $normalized;
        }

        return $fallback;
    }

    /**
     * Procesa filas extraidas de una hoja de calculo para carga masiva.
     * Espera que la fila 1 contenga encabezados (nombres en mayusculas en espanol).
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
        // debug logs removed
        unset($rows[1]);

        $inserted = 0;
        $errors = [];
        $importedAt = now();
        $importBatchId = 'bulk_' . $importedAt->format('Ymd_His');

        $extraFieldsKeys = [
            'COLOR',
            'BASE TELA',
            'BASE HILAZA',
            'CAMPO ADICIONAL',
            'MARCA',
            'MUNDO',
            'Líneas',
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

                    // debug logs removed

                $normalized = $this->normalizeRowKeys($rowData);
                $normalizedRow = $normalized['values'];
                $headerMap = $normalized['map'];
                    // debug logs removed
                $subGroupName = $normalizedRow['SUBGRUPO'] ?? '';
                $subGroup = CategorySubGroup::where('name', $subGroupName)->first();
                if (!$subGroup) {
                    $errors[] = "Fila $rowNum: Subgrupo '$subGroupName' no encontrado";
                    continue;
                }

                $extraFields = $this->parseAdditionalFieldsColumns($normalizedRow);
                foreach ($extraFieldsKeys as $key) {
                    $normalizedKey = $this->normalizeHeader($key);
                    $value = $normalizedRow[$normalizedKey] ?? null;
                    if ($value === null || $value === '') {
                        continue;
                    }
                    $actualHeader = $headerMap[$normalizedKey] ?? $key;
                    $additionalFieldName = $this->getAdditionalFieldNameByNormalizedHeader($normalizedKey);
                    $fieldKey = $additionalFieldName ?? $actualHeader;
                    if (array_key_exists($fieldKey, $extraFields) && $extraFields[$fieldKey] !== '') {
                        continue;
                    }
                    $extraFields[$fieldKey] = $value;
                }

                $extrasString = $this->buildExtraFieldsNameSegment($extraFields, $orderedFieldNames);
                $colorValue = $this->extractColorNameFromExtraFields($extraFields);
                $nameParts = array_filter([
                    optional($subGroup)->name,
                    $extrasString,
                    $colorValue,
                ]);
                $name = trim(implode(' ', $nameParts));

                $taxValue = $normalizedRow['IMPUESTO (%)'] ?? null;
                $descriptionValue = $this->getRowValueFromAlternatives(
                    $normalizedRow,
                    ['DESCRIPCION', 'DESCRIPCION DEL ARTICULO', 'DESCRIPTION'],
                    null,
                );
                if ($descriptionValue === null || trim((string) $descriptionValue) === '') {
                    $descriptionValue = $this->findValueByKeySubstring(
                        $normalizedRow,
                        ['DESCRIPCION', 'DESCRIPTION', 'DESCRIPCION DEL ARTICULO'],
                    );
                }
                // Fallback: search using original header names (handles odd characters/encodings)
                if ($descriptionValue === null || trim((string) $descriptionValue) === '') {
                    $fallback = $this->findValueByOriginalHeader(
                        $rowData,
                        ['DESCRIPCION', 'DESCRIPCION DEL ARTICULO', 'DESCRIPCIÓN', 'DESCRIPCIÓN DEL ARTÍCULO', 'DESCRIPTION']
                    );
                    if ($fallback !== null && trim((string) $fallback) !== '') {
                        $descriptionValue = $fallback;
                    }
                }
                $descriptionValue = $descriptionValue === null ? '' : trim((string) $descriptionValue);

                $barCodeValue = $this->getRowValueFromAlternatives(
                    $normalizedRow,
                    ['CODIGO DE BARRAS', 'BARCODE', 'CODE BAR'],
                    null,
                );
                if ($barCodeValue === null || trim((string) $barCodeValue) === '') {
                    $barCodeValue = $this->findValueByKeySubstring(
                        $normalizedRow,
                        ['CODIGO', 'BARCODE', 'CODIGO DE BARRAS'],
                    );
                }
                // Fallback: try original headers if still missing
                if ($barCodeValue === null || trim((string) $barCodeValue) === '') {
                    $fallbackBar = $this->findValueByOriginalHeader(
                        $rowData,
                        ['CODIGO DE BARRAS', 'CODIGO', 'CÓDIGO', 'BARCODE', 'CODE BAR']
                    );
                    if ($fallbackBar !== null && trim((string) $fallbackBar) !== '') {
                        $barCodeValue = $fallbackBar;
                    }
                }
                $barCodeValue = $barCodeValue === null ? null : trim((string) $barCodeValue);

                $itemData = [
                    'category_sub_group_id' => $subGroup->id,
                    'tax_id' => $this->resolveTaxIdFromImportValue($taxValue),
                    'tax' => $taxValue,
                    'bar_code' => $barCodeValue,
                    'negative_movement' => $this->resolveNegativeMovementFromRow($normalizedRow),
                    'description' => $descriptionValue,
                    'extra_fields' => $extraFields,
                    'name' => $name,
                    'reference' => $normalizedRow['REFERENCIA'] ?? '',
                    'status' => $this->resolveRowStatus($normalizedRow),
                    'item_type' => $this->resolveItemTypeFromRow($normalizedRow),
                    'unit_measure' => $this->resolveUnitMeasureValue($normalizedRow['UNIDAD DE MEDIDA'] ?? null) ?? ($normalizedRow['UNIDAD DE MEDIDA'] ?? null),
                    'origin_type' => Item::ORIGIN_MIGRATION,
                    'was_edited_after_import' => false,
                    'imported_at' => $importedAt,
                    'import_batch_id' => $importBatchId,
                ];
                // debug logs removed

                $warehouses = $this->buildWarehousePayloadFromRow($normalizedRow, $headerMap, $errors, $rowNum);

                if (!empty($warehouses)) {
                    $this->createImportedItemWithOpeningBalances($itemData, $warehouses, $importBatchId, (int) $rowNum);
                } else {
                    $this->create($itemData, $warehouses);
                }
                $inserted++;
            } catch (\Throwable $e) {
                $errors[] = "Fila $rowNum: " . $e->getMessage();
            }
        }

        return ['inserted' => $inserted, 'errors' => $errors];
    }

    protected function normalizeRowKeys(array $row): array
    {
        $values = [];
        $map = [];
        foreach ($row as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            $normalized = $this->normalizeHeader($key);
            $values[$normalized] = $value;
            if (!isset($map[$normalized])) {
                $map[$normalized] = $key;
            }
        }
        return ['values' => $values, 'map' => $map];
    }

    protected function normalizeHeader(string $value): string
    {
        // Normalize whitespace and remove common invisible chars
        $val = (string) $value;
        // Remove control characters (portable POSIX class)
        $val = preg_replace('/[[:cntrl:]]+/u', ' ', $val);
        // Replace Unicode separator characters (spaces, no-break spaces, etc.) with regular space
        $val = preg_replace('/\p{Z}+/u', ' ', $val);
        // Trim and collapse spaces
        $val = preg_replace('/\s+/u', ' ', trim($val));
        $clean = mb_strtoupper($val, 'UTF-8');

        // Try transliteration to ASCII; fallback to cleaned UTF-8 value
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $clean);
        $result = $transliterated !== false && $transliterated !== null ? $transliterated : $clean;

        // Remove punctuation commonly present in headers (commas, dots, parentheses)
        $result = preg_replace('/[()\[\].,;:\/\\-]+/u', ' ', $result);
        // Collapse spaces again and trim
        $result = preg_replace('/\s+/u', ' ', trim($result));

        return $result;
    }

    protected function getAdditionalFieldNameByNormalizedHeader(string $normalized): ?string
    {
        $this->ensureAdditionalFieldNameMap();
        return $this->additionalFieldNameMap[$normalized] ?? null;
    }

    protected function ensureAdditionalFieldNameMap(): void
    {
        if ($this->additionalFieldNameMap !== []) {
            return;
        }
        Setting::fields()->where('status', true)->get(['label'])->each(function (Setting $field) {
            $key = $this->normalizeHeader($field->label);
            if ($key !== '') {
                $this->additionalFieldNameMap[$key] = $field->label;
            }
        });
    }

    protected function ensureUnitMeasureNameMap(): void
    {
        if ($this->unitMeasureNameMap !== []) {
            return;
        }
        $path = resource_path('js/data/unitMeasures.json');
        if (!file_exists($path)) {
            return;
        }
        $payload = json_decode(file_get_contents($path), true);
        $groups = $payload['unitMeasureGroups'] ?? [];
        foreach ($groups as $group) {
            $options = $group['options'] ?? [];
            foreach ($options as $option) {
                $id = (string)($option['id'] ?? '');
                $name = (string)($option['name'] ?? '');
                if ($id !== '') {
                    $this->unitMeasureNameMap[$this->normalizeHeader($id)] = $id;
                }
                if ($name !== '') {
                    $this->unitMeasureNameMap[$this->normalizeHeader($name)] = $id;
                }
            }
        }
    }

    protected function resolveUnitMeasureValue(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $this->ensureUnitMeasureNameMap();
        return $this->unitMeasureNameMap[$this->normalizeHeader($value)] ?? null;
    }

    protected function resolveItemTypeFromRow(array $normalizedRow): string
    {
        $rawType = $this->getRowValueFromAlternatives(
            $normalizedRow,
            ['TIPO DE ITEM', 'TIPO DE ÍTEM', 'TIPO'],
            '',
        );

        return $this->mapItemTypeLabel((string) $rawType);
    }

    protected function mapItemTypeLabel(string $value): string
    {
        $clean = trim(mb_strtolower($value, 'UTF-8'));
        if ($clean === '') {
            return 'product';
        }
        if (str_contains($clean, 'serv')) {
            return 'service';
        }
        if (str_contains($clean, 'prod')) {
            return 'product';
        }
        return 'product';
    }

    protected function resolveRowStatus(array $normalizedRow): bool
    {
        $rawStatus = $this->getRowValueFromAlternatives(
            $normalizedRow,
            ['ESTADO', 'ESTADO ITEM', 'STATUS'],
            '',
        );

        return $this->mapStatusLabelToBool((string) $rawStatus);
    }

    protected function resolveNegativeMovementFromRow(array $normalizedRow): int
    {
        $rawValue = $this->getRowValueFromAlternatives(
            $normalizedRow,
            ['MOVIMIENTO NEGATIVO', 'NEGATIVE MOVEMENT'],
            null,
        );
        if ($rawValue === null || $rawValue === '') {
            return 0;
        }
        if (is_bool($rawValue)) {
            return $rawValue ? 1 : 0;
        }
        if (is_numeric($rawValue)) {
            return (float) $rawValue !== 0.0 ? 1 : 0;
        }

        $normalized = $this->normalizeHeader((string) $rawValue);
        if ($normalized === '') {
            return 0;
        }

        $truthy = ['SI', 'S', 'YES', 'Y', 'TRUE', 'VERDADERO', '1'];
        if (in_array($normalized, $truthy, true)) {
            return 1;
        }

        $falsy = ['NO', 'N', 'FALSE', 'FALSO', '0'];
        if (in_array($normalized, $falsy, true)) {
            return 0;
        }

        return filter_var($rawValue, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    }

    protected function mapStatusLabelToBool(string $value): bool
    {
        $clean = trim(mb_strtolower($value, 'UTF-8'));
        if ($clean === '') {
            return true;
        }
        if (preg_match('/^(0|false|deshabil|inactiv|inhabil|no|off)/', $clean)) {
            return false;
        }
        return true;
    }

    protected function resolveTaxIdFromImportValue(mixed $value): ?int
    {
        $percentage = $this->normalizeTaxPercentageForImport($value);
        if ($percentage === null) {
            return null;
        }

        $rounded = round($percentage, 2);
        $tax = Tax::where('percentage', $rounded)->first();
        if ($tax) {
            return $tax->id;
        }

        $name = $rounded === 0.0 ? 'Ninguno (0%)' : 'Impuesto ' . $this->formatTaxPercentageLabel($rounded) . '%';
        $newTax = Tax::create([
            'name' => $name,
            'percentage' => $rounded,
            'status' => true,
        ]);

        return $newTax->id;
    }

    protected function normalizeTaxPercentageForImport(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        $sanitized = preg_replace('/[^0-9,.\-]/', '', $raw);
        if ($sanitized === null || $sanitized === '') {
            return null;
        }
        $normalized = str_replace(',', '.', $sanitized);
        if (!is_numeric($normalized)) {
            return null;
        }
        return (float) $normalized;
    }

    protected function formatTaxPercentageLabel(float $value): string
    {
        $formatted = number_format($value, 2, '.', '');
        return rtrim(rtrim($formatted, '0'), '.');
    }

    protected function parseAdditionalFieldsColumns(array $normalizedRow): array
    {
        $fields = $this->splitAdditionalFieldsString($normalizedRow['CAMPOS ADICIONALES'] ?? null);
        $values = $this->splitAdditionalFieldsString($normalizedRow['VALORES ADICIONALES'] ?? null);

        $parsed = [];
        $count = min(count($fields), count($values));
        for ($i = 0; $i < $count; $i++) {
            $fieldName = $fields[$i];
            $value = $values[$i];
            if ($fieldName === '' || $value === '') {
                continue;
            }
            $parsed[$fieldName] = $value;
        }

        return $parsed;
    }

    protected function splitAdditionalFieldsString(?string $value): array
    {
        if ($value === null) {
            return [];
        }

        $parts = array_map(fn($segment) => trim((string) $segment), explode(';', $value));
        return array_values(array_filter($parts, fn($segment) => $segment !== ''));
    }

    protected function buildWarehousePayloadFromRow(array $normalizedRow, array $headerMap, array &$errors, int $rowNum): array
    {
        $warehouseName = $this->getRowValueFromAlternatives($normalizedRow, ['BODEGA', 'BODEGAS'], '');
        if ($warehouseName === '') {
            return [];
        }

        $warehouseId = $this->getWarehouseIdByName($warehouseName);
        if (!$warehouseId) {
            $errors[] = "Fila $rowNum: Bodega '$warehouseName' no encontrada";
            return [];
        }

        $quantity = $this->parseDecimalValue($this->getRowValueFromAlternatives($normalizedRow, ['CANTIDAD', 'TOTAL CANTIDAD'], 0));
        $stockMin = $this->parseDecimalValue($this->getRowValueFromAlternatives($normalizedRow, ['CANTIDAD MIN', 'CANTIDAD MINIMA'], 0));
        $stockMax = $this->parseDecimalValue($this->getRowValueFromAlternatives($normalizedRow, ['CANTIDAD MAX', 'CANTIDAD MAXIMA'], 0));
        $unitPrice = $this->parseDecimalOrNull($this->getRowValueFromAlternatives($normalizedRow, ['PRECIO'], null));

        return [[
            'warehouse_id' => $warehouseId,
            'quantity' => $quantity,
            'stock_min' => $stockMin,
            'stock_max' => $stockMax,
            'unit_price' => $unitPrice,
        ]];
    }

    protected function getRowValueFromAlternatives(array $normalizedRow, array $keys, $default = null)
    {
        foreach ($keys as $key) {
            $value = $normalizedRow[$this->normalizeHeader($key)] ?? null;
            if ($value !== null && $value !== '') {
                return $value;
            }
        }
        return $default;
    }

    protected function findValueByKeySubstring(array $normalizedRow, array $substrings): ?string
    {
        $needles = array_filter(array_map(fn($substring) => $this->normalizeHeader($substring), $substrings));
        if (empty($needles)) {
            return null;
        }
        foreach ($normalizedRow as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            foreach ($needles as $needle) {
                if ($needle !== '' && strpos($key, $needle) !== false) {
                    if ($value !== null && $value !== '') {
                        return (string) $value;
                    }
                }
            }
        }
        return null;
    }

    protected function findValueByOriginalHeader(array $rowData, array $candidates): ?string
    {
        if (empty($rowData)) {
            return null;
        }

        $needles = array_filter(array_map(fn($s) => $this->normalizeHeader((string) $s), $candidates));
        if (empty($needles)) {
            return null;
        }

        foreach ($rowData as $origHeader => $value) {
            if (!is_string($origHeader)) {
                continue;
            }
            $normHeader = $this->normalizeHeader($origHeader);
            foreach ($needles as $needle) {
                if ($needle !== '' && strpos($normHeader, $needle) !== false) {
                    if ($value !== null && $value !== '') {
                        return (string) $value;
                    }
                }
            }
        }

        return null;
    }

    protected function parseDecimalValue($value): float
    {
        $parsed = $this->parseDecimalOrNull($value);
        return $parsed ?? 0.0;
    }

    protected function parseDecimalOrNull($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $clean = trim((string) $value);
        if ($clean === '' || $clean === '-') {
            return null;
        }

        $clean = preg_replace('/[^0-9,.\- ]/', '', $clean);
        $clean = trim((string) $clean);

        if ($clean === '' || $clean === '-') {
            return null;
        }

        $lastComma = strrpos($clean, ',');
        $lastDot = strrpos($clean, '.');

        if ($lastComma !== false && $lastDot !== false) {
            if ($lastComma > $lastDot) {
                $clean = str_replace('.', '', $clean);
                $clean = str_replace(',', '.', $clean);
            } else {
                $clean = str_replace(',', '', $clean);
            }
        } elseif ($lastComma !== false) {
            $clean = str_replace(',', '.', $clean);
        }

        $clean = str_replace(' ', '', $clean);

        return is_numeric($clean) ? (float) $clean : null;
    }

    protected function normalizeWarehouseName(string $name): string
    {
        return $this->normalizeHeader($name);
    }

    protected function ensureWarehouseNameMap(): void
    {
        if ($this->warehouseNameMapCache !== []) {
            return;
        }
        Warehouse::select('id', 'name')->get()->each(function ($warehouse) {
            $key = $this->normalizeWarehouseName($warehouse->name);
            if ($key !== '') {
                $this->warehouseNameMapCache[$key] = $warehouse->id;
            }
        });
    }

    protected function getWarehouseIdByName(string $name): ?int
    {
        $normalized = $this->normalizeWarehouseName($name);
        if ($normalized === '') {
            return null;
        }
        $this->ensureWarehouseNameMap();
        return $this->warehouseNameMapCache[$normalized] ?? null;
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
                'quantity' => is_numeric($warehouse['quantity'] ?? null) ? (float) $warehouse['quantity'] : 0.0,
                'stock_min' => is_numeric($warehouse['stock_min'] ?? null) ? (float) $warehouse['stock_min'] : 0.0,
                'stock_max' => is_numeric($warehouse['stock_max'] ?? null) ? (float) $warehouse['stock_max'] : 0.0,
            ];
        }

        return $warehouses;
    }

    protected function buildCreatePayload(array $data): array
    {
        $extraFields = $this->normalizeExtraFields($data['extra_fields'] ?? []);
        $itemType = $this->normalizeItemType($data['item_type'] ?? 'product');
        $tracksInventory = $this->normalizeTracksInventory($data['tracks_inventory'] ?? null, $itemType);
        $originType = $this->normalizeOriginType($data['origin_type'] ?? Item::ORIGIN_SYSTEM);
        $payload = [
            'category_sub_group_id' => $data['category_sub_group_id'],
            'img' => $data['img'] ?? null,
            'tax_id' => $this->ensureTaxId($data['tax_id'] ?? null),
            'tax' => $data['tax'],
            'bar_code' => $data['bar_code'] ?? null,
            'negative_movement' => $tracksInventory && filter_var($data['negative_movement'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
            'description' => isset($data['description']) ? (string) $data['description'] : '',
            'extra_fields' => $extraFields,
            'reference' => $data['reference'],
            'status' => array_key_exists('status', $data) ? (bool) $data['status'] : true,
            'item_type' => $itemType,
            'tracks_inventory' => $tracksInventory,
            'origin_type' => $originType,
            'was_edited_after_import' => false,
            'imported_at' => $originType === Item::ORIGIN_MIGRATION ? ($data['imported_at'] ?? now()) : null,
            'import_batch_id' => $originType === Item::ORIGIN_MIGRATION ? ($data['import_batch_id'] ?? null) : null,
            'unit_measure' => $data['unit_measure'] ?? null,
            'income_account_id' => $this->normalizeAccountId($data['income_account_id'] ?? null),
            'inventory_account_id' => $tracksInventory
                ? $this->normalizeAccountId($data['inventory_account_id'] ?? null)
                : null,
            'cost_of_sale_account_id' => $this->normalizeAccountId($data['cost_of_sale_account_id'] ?? null),
            'color_id' => $this->normalizeColorId($data['color_id'] ?? null),
        ];

        $payload['name'] = $this->buildName(
            $payload['category_sub_group_id'],
            $extraFields,
            $payload['color_id'] ?? null
        );

        // debug logs removed

        return $payload;
    }

    protected function buildUpdatePayload(Item $item, array $data): array
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
            'income_account_id',
            'inventory_account_id',
            'cost_of_sale_account_id',
            'color_id',
        ];

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        foreach (['income_account_id', 'inventory_account_id', 'cost_of_sale_account_id'] as $accountField) {
            if (array_key_exists($accountField, $payload)) {
                $payload[$accountField] = $this->normalizeAccountId($payload[$accountField]);
            }
        }

        if (array_key_exists('color_id', $payload)) {
            $payload['color_id'] = $this->normalizeColorId($payload['color_id']);
        }

        if (array_key_exists('status', $data)) {
            $payload['status'] = (bool) $data['status'];
        }

        $itemType = $this->normalizeItemType($data['item_type'] ?? $item->item_type ?? 'product');
        $tracksInventory = $this->normalizeTracksInventory(
            $data['tracks_inventory'] ?? $item->tracks_inventory ?? null,
            $itemType
        );

        if (array_key_exists('item_type', $data) || $itemType !== $item->item_type) {
            $payload['item_type'] = $itemType;
        }

        if (array_key_exists('tracks_inventory', $data) || $tracksInventory !== (bool) $item->tracks_inventory) {
            $payload['tracks_inventory'] = $tracksInventory;
        }

        if (array_key_exists('negative_movement', $data) || !$tracksInventory) {
            $payload['negative_movement'] = $tracksInventory
                && filter_var($data['negative_movement'] ?? $item->negative_movement, FILTER_VALIDATE_BOOLEAN)
                    ? 1
                    : 0;
        }

        $extraFields = array_key_exists('extra_fields', $data)
            ? $this->normalizeExtraFields($data['extra_fields'])
            : ($item->extra_fields ?? []);
        $payload['extra_fields'] = $extraFields;

        $subGroupId = $data['category_sub_group_id'] ?? $item->category_sub_group_id;
        $resolvedColorId = $payload['color_id'] ?? $item->color_id ?? null;
        $payload['name'] = $this->buildName($subGroupId, $extraFields, $resolvedColorId);

        if (array_key_exists('tax_id', $data)) {
            $payload['tax_id'] = $this->ensureTaxId($data['tax_id']);
        }

        if (!$tracksInventory) {
            $payload['inventory_account_id'] = null;
        }

        if ($this->shouldMarkEditedAfterImport($item)) {
            $payload['was_edited_after_import'] = true;
        }

        return $payload;
    }

    protected function shouldMarkEditedAfterImport(Item $item): bool
    {
        return $item->origin_type === Item::ORIGIN_MIGRATION && !(bool) $item->was_edited_after_import;
    }

    protected function normalizeOriginType(mixed $value): string
    {
        return $value === Item::ORIGIN_MIGRATION
            ? Item::ORIGIN_MIGRATION
            : Item::ORIGIN_SYSTEM;
    }

    protected function normalizeAccountId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    protected function normalizeColorId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    protected function normalizeItemType(mixed $value): string
    {
        return (string) $value === 'service' ? 'service' : 'product';
    }

    protected function normalizeTracksInventory(mixed $value, string $itemType): bool
    {
        if ($itemType === 'service') {
            return false;
        }

        if ($value === null || $value === '') {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    protected function ensureTaxId(?int $taxId): ?int
    {
        if ($taxId !== null) {
            return $taxId;
        }

        $tax = Tax::firstOrCreate(
            ['name' => 'Ninguno (0%)', 'percentage' => 0],
            ['status' => true]
        );

        return $tax->id;
    }

    protected function buildName(?int $subGroupId, array $extraFields, ?int $colorId = null): string
    {
        $subGroupName = optional(CategorySubGroup::find($subGroupId))->name ?? '';
        $extrasString = $this->buildExtraFieldsNameSegment($extraFields);
        $name = trim("{$subGroupName} {$extrasString}");
        if (!empty($colorId)) {
            $color = Color::find($colorId);
            $colorName = $color ? trim((string) $color->name) : '';
            if ($colorName !== '' && !str_contains(mb_strtolower($name), mb_strtolower($colorName))) {
                $name = trim($name . ' ' . $colorName);
            }
        }
        return $name;
    }

    public function computeName(?int $subGroupId, array $extraFields, ?int $colorId = null): string
    {
        return $this->buildName($subGroupId, $extraFields, $colorId);
    }

    public function existsByReference(string $reference, ?int $excludeId = null): bool
    {
        $normalized = $this->normalizeForComparison($reference);
        if ($normalized === '') {
            return false;
        }

        $query = Item::query()->whereRaw('LOWER(reference) = ?', [$normalized]);
        if ($excludeId) {
            $query->where('id', '<>', $excludeId);
        }

        return $query->exists();
    }

    public function existsByName(string $name, ?int $excludeId = null): bool
    {
        $normalized = $this->normalizeForComparison($name);
        if ($normalized === '') {
            return false;
        }

        $query = Item::query()->whereRaw('LOWER(name) = ?', [$normalized]);
        if ($excludeId) {
            $query->where('id', '<>', $excludeId);
        }

        return $query->exists();
    }

    protected function normalizeForComparison(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    public function duplicateStatus(string $reference, string $name, ?int $excludeId = null): array
    {
        if ($this->normalizeForComparison($name) === '') {
            return [
                'duplicate' => false,
                'duplicate_by_reference' => false,
                'duplicate_by_name' => false,
                'message' => '',
            ];
        }

        $duplicateByName = $this->existsByName($name, $excludeId);

        return [
            'duplicate' => $duplicateByName,
            'duplicate_by_reference' => false,
            'duplicate_by_name' => $duplicateByName,
            'message' => $duplicateByName
                ? 'El ítem ya existe.'
                : '',
        ];
    }

    public function assertUniqueReferenceAndName(string $reference, string $name, ?int $excludeId = null): void
    {
        $status = $this->duplicateStatus($reference, $name, $excludeId);
        if (!$status['duplicate']) {
            return;
        }

        $errors = [];
        if ($status['duplicate_by_name']) {
            $errors['reference'][] = 'Ya existe un ítem con los mismos datos de subgrupo y campos.';
        }

        throw ValidationException::withMessages($errors);
    }

    protected function syncWarehouses(
        Item $item,
        array $warehouses,
        bool $replace = false
    ): void {
        if ($replace) {
            WarehouseItem::where('item_id', $item->id)->delete();
        }

        foreach ($warehouses as $warehouse) {
            WarehouseItem::updateOrCreate(
                [
                    'item_id'      => $item->id,
                    'warehouse_id' => $warehouse['warehouse_id'],
                ],
                [
                    'quantity'  => $warehouse['quantity']  ?? 0,
                    'stock_min' => $warehouse['stock_min'] ?? 0,
                    'stock_max' => $warehouse['stock_max'] ?? 0,
                ]
            );
        }
    }

    protected function createImportedItemWithOpeningBalances(
        array $itemData,
        array $warehouses,
        string $importBatchId,
        int $rowNum
    ): Item {
        return DB::transaction(function () use ($itemData, $warehouses, $importBatchId, $rowNum) {
            $item = Item::create($this->buildCreatePayload($itemData));

            foreach (array_values($warehouses) as $index => $warehouse) {
                $quantity = (float) ($warehouse['quantity'] ?? 0);
                $unitPrice = array_key_exists('unit_price', $warehouse) && $warehouse['unit_price'] !== null
                    ? (float) $warehouse['unit_price']
                    : null;

                WarehouseItem::updateOrCreate(
                    [
                        'item_id' => $item->id,
                        'warehouse_id' => $warehouse['warehouse_id'],
                    ],
                    [
                        'quantity' => $quantity,
                        'stock_min' => $warehouse['stock_min'] ?? 0,
                        'stock_max' => $warehouse['stock_max'] ?? 0,
                        'weighted_average_price' => $quantity > 0 && $unitPrice !== null ? $unitPrice : null,
                    ]
                );

                if ($quantity <= 0) {
                    continue;
                }

                InventoryMovement::create([
                    'document_number' => mb_substr("BI-{$importBatchId}-{$rowNum}-{$index}", 0, 50),
                    'item_id' => $item->id,
                    'warehouse_id' => $warehouse['warehouse_id'],
                    'source_type' => 'item_excel_import',
                    'source_id' => null,
                    'type' => 'entrada',
                    'date' => now()->toDateString(),
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_cost' => $unitPrice !== null ? round($quantity * $unitPrice, 4) : null,
                    'previous_stock' => 0,
                    'previous_weighted_price' => 0,
                    'new_stock' => $quantity,
                    'new_weighted_price' => $unitPrice ?? 0,
                    'notes' => 'Saldo inicial importado desde plantilla de items',
                    'created_by_user_id' => null,
                ]);
            }

            return $item;
        });
    }

    protected function gatherDeletionReasons(Item $item): array
    {
        $reasons = [];

        $warehouseCount = WarehouseItem::where('item_id', $item->id)->count();
        if ($warehouseCount > 0) {
            $reasons[] = $warehouseCount === 1 ? '1 bodega asignada' : "{$warehouseCount} bodegas asignadas";
        }

        $documentCounts = $this->countPurchaseDocumentItemsByType($item->id);
        $requestCount = $documentCounts[PurchaseDocument::TYPE_REQUEST] ?? 0;
        if ($requestCount > 0) {
            $reasons[] = $requestCount === 1 ? '1 solicitud de compra' : "{$requestCount} solicitudes de compra";
        }
        $orderCount = $documentCounts[PurchaseDocument::TYPE_ORDER] ?? 0;
        if ($orderCount > 0) {
            $reasons[] = $orderCount === 1 ? '1 orden de compra' : "{$orderCount} ordenes de compra";
        }
        $invoiceCount = $documentCounts[PurchaseDocument::TYPE_INVOICE] ?? 0;
        if ($invoiceCount > 0) {
            $reasons[] = $invoiceCount === 1 ? '1 factura de compra' : "{$invoiceCount} facturas de compra";
        }

        $adjustmentCount = InventoryAdjustmentLine::where('item_id', $item->id)->count();
        if ($adjustmentCount > 0) {
            $reasons[] = $adjustmentCount === 1 ? '1 movimiento de inventario' : "{$adjustmentCount} movimientos de inventario";
        }

        return $reasons;
    }

    protected function countPurchaseDocumentItemsByType(int $itemId): array
    {
        $counts = [];
        foreach ([PurchaseDocument::TYPE_REQUEST, PurchaseDocument::TYPE_ORDER, PurchaseDocument::TYPE_INVOICE] as $type) {
            $counts[$type] = PurchaseDocumentItem::query()
                ->where('item_id', $itemId)
                ->whereHas('document', fn($query) => $query->where('document_type', $type))
                ->count();
        }
        return $counts;
    }

    protected function formatReasonList(array $reasons): string
    {
        if (count($reasons) === 1) {
            return $reasons[0];
        }

        return implode(', ', array_slice($reasons, 0, -1)) . ' y ' . end($reasons);
    }
}
