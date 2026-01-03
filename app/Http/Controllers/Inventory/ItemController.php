<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Item;
// ProductSheet imports removed: extra fields are managed dynamically elsewhere
use App\Services\Inventory\ItemService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Models\Common\CategorySubGroup;
use Illuminate\Support\Facades\Log;

class ItemController extends Controller
{
    public function __construct(protected ItemService $service) {}

    public function index()
    {
        return Inertia::render('Inventory/Item/Index', [
            'items' => Item::with(['warehouses', 'categorySubGroup', 'tax'])->get(),
        ]);
    }

    public function show($id)
    {
        return $this->service->findWithRelations($id);
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate($this->itemValidationRules());
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Throwable $e) {
            return $this->unexpectedErrorResponse();
        }

        $extraFields = $this->service->normalizeExtraFields($request->input('extra_fields') ?? []);
        $data['extra_fields'] = $extraFields;
        $reference = trim($data['reference']);
        $data['reference'] = $reference;
        $subGroupId = (int) $data['category_sub_group_id'];
        $itemName = $this->service->computeName($subGroupId, $extraFields);
        $this->service->assertUniqueReferenceAndName($reference, $itemName);

        $this->handleImageUploadIfPresent($request, $data, 'articles');
        $warehouses = $this->resolveWarehouses($request) ?? [];

        return $this->service->create($data, $warehouses);
    }

    public function update(Request $request, $id)
    {
        $item = $this->service->find($id);

        try {
            $data = $request->validate($this->itemValidationRules(true, $item));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Throwable $e) {
            return $this->unexpectedErrorResponse();
        }

        $extraFields = $this->service->normalizeExtraFields(
            $request->input('extra_fields') ?? $item->extra_fields
        );
        $subGroupId = $request->has('category_sub_group_id')
            ? (int) $request->input('category_sub_group_id')
            : $item->category_sub_group_id;
        $data['extra_fields'] = $extraFields;
        $reference = trim($data['reference']);
        $data['reference'] = $reference;
        $itemName = $this->service->computeName($subGroupId, $extraFields);
        $this->service->assertUniqueReferenceAndName($reference, $itemName, $item->id);

        $this->handleImageUploadIfPresent($request, $data, 'items');
        $warehouses = $this->resolveWarehouses($request);

        $updated = $this->service->update($item, $data, $warehouses);

        return $updated;
    }

    public function destroy($id)
    {
        $item = $this->service->find($id);

        try {
            $this->service->delete($item);
            return response()->noContent();
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        }
    }

    public function getData(Request $request)
    {
        $filters = $request->only(['search', 'status', 'item_type', 'warehouse_id']);
        $perPage = $request->input('per_page', 10);
        $items = $this->service->paginate($filters, $perPage);

        return response()->json($items);
    }

    public function togglestatus($id)
    {
        $item = $this->service->find($id);
        $status = $this->service->toggleStatus($item);

        return response()->json([
            'status' => $status,
            'message' => $status ? 'item habilitado' : 'item deshabilitado',
        ]);
    }

    public function checkDuplicate(Request $request)
    {
        $data = $request->validate([
            'reference' => 'required|string',
            'category_sub_group_id' => 'required|integer',
            'extra_fields' => 'nullable',
            'item_id' => 'nullable|integer',
        ]);

        $extraFields = $this->service->normalizeExtraFields($request->input('extra_fields') ?? []);
        $subGroupId = (int) $data['category_sub_group_id'];
        $name = $this->service->computeName($subGroupId, $extraFields);
        $excludeId = $data['item_id'] ?? null;

        $status = $this->service->duplicateStatus($data['reference'], $name, $excludeId);

        return response()->json($status);
    }

    public function downloadTemplate()
    {
        $fileName = 'plantilla_items.xlsx';
        $filePath = storage_path('app/public/' . $fileName);

        $spreadsheet = new Spreadsheet();

        // Encabezados de la hoja principal
        $headers = $this->templateHeaders();

        // Hoja principal: items (solo encabezados + fila de ejemplo)
        $mainSheet = $spreadsheet->getActiveSheet();
        $mainSheet->setTitle('items');
        // Fila de ejemplo alineada con el orden oficial y los nuevos campos (bodega/stock)
        $exampleRow = [
            'Producto', // TIPO DE ITEM
            'Ejemplo articulo', // NOMBRE
            'Suministros', // GRUPO
            'Papel', // SUBGRUPO
            '19', // IMPUESTO (%)
            'Principal', // BODEGA
            '100', // CANTIDAD
            '10', // CANTIDAD MIN
            '200', // CANTIDAD MAX
            'Kilogramo', // UNIDAD DE MEDIDA
            'ART-001', // CODIGO DE BARRAS
            'ART-001', // REFERENCIA
            'Descripcion de articulo de prueba', // DESCRIPCION
            '0', // MOVIMIENTO NEGATIVO
            'COLOR', // CAMPOS ADICIONALES
            'Rojo', // VALORES ADICIONALES
            'Activo', // ESTADO
        ];

        $mainSheet->fromArray([
            $headers,
            $exampleRow,
        ], '', 'A1');

        // Presentación básica de cabecera
        $lastCol = $mainSheet->getHighestColumn();
        $mainSheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
        foreach (range('A', $lastCol) as $col) {
            $mainSheet->getColumnDimension($col)->setAutoSize(true);
        }

        $subGroups = CategorySubGroup::with('categoryGroup')->get()->map(function ($sg) {
            return [
                'id' => $sg->id,
                'name' => $sg->name,
                'group' => optional($sg->categoryGroup)->name ?? '',
            ];
        })->toArray();

        $subGroupSheet = $spreadsheet->createSheet();
        $subGroupSheet->setTitle('Subgrupos');
        $subGroupSheet->fromArray(['ID', 'Nombre', 'Grupo'], null, 'A1');
        if (!empty($subGroups)) {
            $rows = array_map(fn($s) => [$s['id'], $s['name'], $s['group']], $subGroups);
            $subGroupSheet->fromArray($rows, null, 'A2');
        }
        $rowS = count($subGroups) + 1;

        $unitMeasures = $this->loadUnitMeasureOptionsForTemplate();
        $unitSheet = $spreadsheet->createSheet();
        $unitSheet->setTitle('Unidades');
        $unitSheet->fromArray(['ID', 'Nombre'], null, 'A1');
        if (!empty($unitMeasures)) {
            $unitSheet->fromArray(
                array_map(fn($unit) => [$unit['id'], $unit['name']], $unitMeasures),
                null,
                'A2'
            );
        }
        $unitLastRow = max(1, count($unitMeasures)) + 1;

        $mainSheet = $spreadsheet->setActiveSheetIndex(0);
        $subGroupLastRow = $rowS;

        $maxValidationRow = 1000;
        if ($subGroupLastRow >= 2) {
            $validationSubGroup = new \PhpOffice\PhpSpreadsheet\Cell\DataValidation();
            $validationSubGroup->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST)
                ->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP)
                ->setAllowBlank(false)
                ->setShowInputMessage(true)
                ->setShowErrorMessage(true)
                ->setShowDropDown(true)
                ->setFormula1("'Subgrupos'!B2:B{$subGroupLastRow}");

            // Apply validation to the column where 'SUBGRUPO' header is located
            $colLetters = range('A', 'Z');
            $subGroupColIdx = array_search('SUBGRUPO', $headers);
            $groupColIdx = array_search('GRUPO', $headers);
            if ($subGroupColIdx !== false && isset($colLetters[$subGroupColIdx])) {
                $subGroupCol = $colLetters[$subGroupColIdx];
                for ($row = 2; $row <= $maxValidationRow; $row++) {
                    $mainSheet->getCell($subGroupCol . $row)->setDataValidation(clone $validationSubGroup);
                }

                // If we have a GRUPO column, auto-fill it via VLOOKUP from Subgrupos sheet
                if ($groupColIdx !== false && isset($colLetters[$groupColIdx])) {
                    $groupCol = $colLetters[$groupColIdx];
                    // Use VLOOKUP on Subgrupos: lookup by SUBGRUPO name (column B) and return Grupo (column C)
                    for ($row = 2; $row <= $maxValidationRow; $row++) {
                        $formula = sprintf(
                            '=IF(TRIM(%1$s%2$d)="","",IFERROR(VLOOKUP(%1$s%2$d,\'Subgrupos\'!B2:C%s,2,FALSE),""))',
                            $subGroupCol,
                            $row,
                            $rowS
                        );
                        $mainSheet->setCellValue($groupCol . $row, $formula);
                    }
                }
            }
        }

        $this->applyUnitValidationToSheet($mainSheet, $headers, $unitLastRow, $maxValidationRow);

        $spreadsheet->setActiveSheetIndex(0);

        try {
            $this->saveSpreadsheetToPath($spreadsheet, $filePath);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'No se pudo guardar la plantilla Excel. Intente cerrar el archivo si est\u00e1 abierto y vuelva a intentarlo.',
                'error' => $e->getMessage()
            ], 500);
        }

        return response()->download($filePath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'public',
            'Expires' => '0',
        ]);
    }

    public function bulkUpload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls'
        ]);

        $file = $request->file('file');
        $spreadsheet = IOFactory::load($file->getPathname());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        $result = $this->service->processBulkUploadArray($rows);

        if ($result['inserted'] === 0 && !empty($result['errors'])) {
            return response()->json([
                'message' => 'No se pudo insertar ningun item. Errores: ' . implode('; ', $result['errors'])
            ], 422);
        }

        return response()->json([
            'message' => "Carga masiva completada. items insertados: {$result['inserted']}",
            'errors' => $result['errors'] ?? [],
            'inserted' => $result['inserted'] ?? 0
        ]);
    }

    public function getWarehouses($id)
    {
        $item = Item::findOrFail($id);
        return response()->json($item->getWarehouseStocks());
    }

    // Nueva función para exportar todos los items a Excel
    public function exportExcel()
    {
        $items = Item::with(['categorySubGroup.categoryGroup', 'tax', 'warehouses'])->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('items');

        // Headers aligned with the import template
        $headers = $this->templateHeaders();
        $sheet->fromArray($headers, null, 'A1');

        $rowNum = 2;
        foreach ($items as $item) {
            $rowData = $this->exportRowForItem($item);

            $sheet->fromArray($rowData, null, "A{$rowNum}");
            $rowNum++;
        }

        // Ajustar ancho de columnas
        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Descargar archivo
        $fileName = 'items_exportados.xlsx';
        $tempFile = tempnam(sys_get_temp_dir(), 'items_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);
    }

    public function listView()
    {
        return Inertia::render('Inventory/Item/Index', [
            'items' => Item::with(['categorySubGroup', 'tax'])->get(),
        ]);
    }

    public function advancedForm()
    {
        return Inertia::render('Inventory/Item/AdvancedForm');
    }

    public function adjustmentView()
    {
        return Inertia::render('Inventory/Item/InventoryAdjustment');
    }

    private function resolveWarehouses(Request $request): array
    {
        // Delegar la resolución de payloads de bodegas al servicio
        return $this->service->resolveWarehousePayload($request->input('warehouses'));
    }
    
    private function itemValidationRules(bool $isUpdate = false, $item = null): array
    {
        $rules = [
            'category_sub_group_id' => ($isUpdate ? 'sometimes' : 'required') . '|integer',
            'img' => 'nullable|string',
            'image' => 'nullable|file|image',
            'tax_id' => 'nullable|integer',
            'tax' => ($isUpdate ? 'sometimes' : 'required') . '|numeric',
            'bar_code' => 'nullable|string',
            'negative_movement' => $isUpdate ? 'sometimes' : 'required',
            'description' => ($isUpdate ? 'sometimes' : 'required') . '|string',
            'extra_fields' => 'nullable',
            'reference' => ['required', 'string'],
            'status' => 'sometimes|boolean',
            'item_type' => 'required|string|in:product,service',
            'unit_measure' => 'nullable|string',
        ];

        // Reference unique rule: ignore current item on update
        $uniqueRule = Rule::unique('items', 'reference');
        if ($isUpdate && $item) {
            $uniqueRule = $uniqueRule->ignore($item->id);
        }
        $rules['reference'] = [$uniqueRule];

        return $rules;
    }

    private function handleImageUploadIfPresent(Request $request, array &$data, string $folder = 'articles')
    {
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store($folder, 'public');
            $data['img'] = $path;
        }
    }

    private function templateHeaders(): array
    {
        // Order optimized for clarity and matches the download/upload layout
        return [
            'TIPO DE ITEM',
            'NOMBRE',
            'GRUPO',
            'SUBGRUPO',
            'IMPUESTO (%)',
            'BODEGA',
            'CANTIDAD',
            'CANTIDAD MIN',
            'CANTIDAD MAX',
            'UNIDAD DE MEDIDA',
            'CODIGO DE BARRAS',
            'REFERENCIA',
            'DESCRIPCION',
            'MOVIMIENTO NEGATIVO',
            'CAMPOS ADICIONALES',
            'VALORES ADICIONALES',
            'ESTADO',
        ];
    }

    private function applyUnitValidationToSheet($sheet, array $headers, int $unitLastRow, int $maxValidationRow = 1000)
    {
        $colLetters = range('A', 'Z');
        $unitColIdx = array_search('UNIDAD DE MEDIDA', $headers);
        if ($unitColIdx !== false && isset($colLetters[$unitColIdx])) {
            $unitCol = $colLetters[$unitColIdx];
            $validationUnit = new \PhpOffice\PhpSpreadsheet\Cell\DataValidation();
            $validationUnit->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST)
                ->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP)
                ->setAllowBlank(true)
                ->setShowInputMessage(true)
                ->setShowErrorMessage(true)
                ->setShowDropDown(true)
                ->setFormula1("'Unidades'!A2:A{$unitLastRow}");
            for ($row = 2; $row <= $maxValidationRow; $row++) {
                $sheet->getCell($unitCol . $row)->setDataValidation(clone $validationUnit);
            }
            $sheet->getColumnDimension($unitCol)->setAutoSize(true);
        }
    }

    private function loadUnitMeasureOptionsForTemplate(): array
    {
        $path = resource_path('js/data/unitMeasures.json');
        if (!file_exists($path)) {
            return [];
        }

        $content = file_get_contents($path);
        $data = json_decode($content, true);
        if (!is_array($data)) {
            return [];
        }

        $groups = $data['unitMeasureGroups'] ?? [];
        $options = [];
        foreach ($groups as $group) {
            if (!empty($group['options']) && is_array($group['options'])) {
                foreach ($group['options'] as $opt) {
                    if (isset($opt['id']) && isset($opt['name'])) {
                        $options[] = [
                            'id' => (string) $opt['id'],
                            'name' => (string) $opt['name'],
                        ];
                    }
                }
            }
        }

        return $options;
    }
    private function saveSpreadsheetToPath(Spreadsheet $spreadsheet, string $filePath)
    {
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');

        $maxAttempts = 5;
        $attempt = 0;
        $waitMs = 200;
        while (file_exists($filePath) && !is_writable($filePath) && $attempt < $maxAttempts) {
            usleep($waitMs * 1000);
            $attempt++;
        }

        try {
            $writer->save($filePath);
            Log::info('Hoja Excel guardada', ['path' => $filePath, 'size' => filesize($filePath)]);
        } catch (\Exception $e) {
            Log::error('Error guardando Excel', ['error' => $e->getMessage(), 'path' => $filePath]);
            throw $e;
        }
    }

    private function exportRowForItem($item): array
    {
        $extra = is_array($item->extra_fields) ? $item->extra_fields : (json_decode($item->extra_fields, true) ?: []);

        $tax = $item->tax;
        $statusLabel = $item->status ? 'Activo' : 'Inactivo';
        $typeLabel = $item->item_type === 'service' ? 'Servicio' : 'Producto';

        $extraKeys = array_map(fn($k) => mb_strtoupper((string) $k, 'UTF-8'), array_keys($extra));
        $extraVals = array_map(fn($v) => (string) $v, array_values($extra));
        $fieldsCol = implode('; ', $extraKeys);
        $valuesCol = implode('; ', $extraVals);

        $groupName = optional(optional($item->categorySubGroup)->categoryGroup)->name ?? '';
        $subGroupName = optional($item->categorySubGroup)->name;
        $warehouse = $item->warehouses->first();
        $warehouseName = optional($warehouse)->name ?? '';
        $warehousePivot = $warehouse ? $warehouse->pivot : null;
        $quantityValue = $warehousePivot && isset($warehousePivot->quantity) ? $warehousePivot->quantity : '';
        $stockMinValue = $warehousePivot && isset($warehousePivot->stock_min) ? $warehousePivot->stock_min : '';
        $stockMaxValue = $warehousePivot && isset($warehousePivot->stock_max) ? $warehousePivot->stock_max : '';

        return [
            // Orden: TIPO, NOMBRE, GRUPO, SUBGRUPO, IMPUESTO, BODEGA, CANTIDAD, CANTIDAD MIN, CANTIDAD MAX, UNIDAD, CODIGO, REFERENCIA, DESCRIPCION, MOVIMIENTO NEGATIVO, CAMPOS, VALORES, ESTADO
            $typeLabel,
            $item->name,
            $groupName,
            $subGroupName,
            $tax ? number_format((float) $tax->percentage, 2, '.', '') : '',
            $warehouseName,
            $quantityValue,
            $stockMinValue,
            $stockMaxValue,
            $item->unit_measure,
            $item->bar_code,
            $item->reference,
            $item->description,
            $item->negative_movement ? 1 : 0,
            $fieldsCol,
            $valuesCol,
            $statusLabel,
        ];
    }
    

    private function validationErrorResponse(ValidationException $exception)
    {
        return response()->json([
            'message' => 'Errores de validacion',
            'errors' => $exception->errors(),
        ], 422);
    }

    private function unexpectedErrorResponse()
    {
        return response()->json([
            'message' => 'Ocurrio un error inesperado al procesar la solicitud.',
            'errors' => [],
        ], 500);
    }

}


