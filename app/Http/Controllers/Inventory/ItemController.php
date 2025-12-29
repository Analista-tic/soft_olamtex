<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Item;
use App\Models\ProductSheet\Brand;
use App\Models\ProductSheet\FabricBase;
use App\Models\ProductSheet\World;
use App\Models\ProductSheet\YarnBase;
use App\Models\Expenses\PurchaseOrderItem;
use App\Models\Expenses\PurchaseInvoiceItem;
use App\Models\Inventory\InventoryAdjustmentLine;
use App\Services\Inventory\ItemService;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
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
            'articles' => Item::with(['warehouses', 'categorySubGroup', 'tax'])->get(),
        ]);
    }

    public function show($id)
    {
        return $this->service->findWithRelations($id);
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'category_sub_group_id' => 'required|integer',
                'img' => 'nullable|string',
                'tax_id' => 'nullable|integer',
                'tax' => 'required|numeric',
                'bar_code' => 'nullable|string',
                'negative_movement' => 'required',
                'description' => 'required|string',
                'extra_fields' => 'nullable',
                'reference' => ['required', 'string', Rule::unique('items', 'reference')],
                'status' => 'sometimes|boolean',
                'item_type' => 'required|string|in:product,service',
                'unit_measure' => 'nullable|string',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Errores de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Ocurrió un error inesperado al procesar la solicitud.',
                'errors' => [],
            ], 500);
        }

        $extraFields = $this->service->normalizeExtraFields($request->input('extra_fields') ?? []);
        $data['extra_fields'] = $extraFields;
        $reference = trim($data['reference']);
        $data['reference'] = $reference;
        $subGroupId = (int) $data['category_sub_group_id'];
        $itemName = $this->service->computeName($subGroupId, $extraFields);
        $this->service->assertUniqueReferenceAndName($reference, $itemName);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('articles', 'public');
            $data['img'] = $path;
        }

        $warehouses = $this->service->resolveWarehousePayload($request->input('warehouses')) ?? [];

        return $this->service->create($data, $warehouses);
    }

    public function update(Request $request, $id)
    {
        $item = $this->service->find($id);

        try {
            $data = $request->validate([
                'category_sub_group_id' => 'sometimes|integer',
                'img' => 'nullable|string',
                'tax_id' => 'nullable|integer',
                'tax' => 'sometimes|numeric',
                'bar_code' => 'nullable|string',
                'negative_movement' => 'sometimes',
                'description' => 'sometimes|string',
                'extra_fields' => 'nullable',
                'reference' => ['required', 'string', Rule::unique('items', 'reference')->ignore($item->id)],
                'status' => 'sometimes|boolean',
                'item_type' => 'required|string|in:product,service',
                'unit_measure' => 'nullable|string',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Errores de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Ocurrió un error inesperado al procesar la solicitud.',
                'errors' => [],
            ], 500);
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

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('items', 'public');
            $data['img'] = $path;
        }

        $warehouses = $this->service->resolveWarehousePayload($request->input('warehouses'));

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

        // Extra fields (Español, para la plantilla)
        $extraFieldNames = [
            'Base tela',
            'Base hilaza',
            'Campo adicional',
            'Marca',
            'Mundo',
            'Líneas',
            'Tipo puntera',
            'Talla',
            'Medida',
        ];

        // Encabezados en español y mayúsculas
        $headers = [
            'SUBGRUPO',
            'COLOR',
            'IMPUESTO (%)',
            'CÓDIGO DE BARRAS',
            'DESCRIPCIÓN',
            'BASE TELA',
            'BASE HILAZA',
            'CAMPO ADICIONAL',
            'MARCA',
            'MUNDO',
            'LÍNEAS',
            'TIPO PUNTERA',
            'TALLA',
            'MEDIDA',
            'UNIDAD DE MEDIDA',
            'NOMBRE',
            'REFERENCIA',
        ];

        // Ejemplo de fila 2
        $exampleRow = [
            'CAMISETA', // SUBGRUPO
            'ROJO',     // COLOR
            '19',       // IMPUesta
            '123456',   // CÓDIGO DE BARRAS
            'Ejemplo de item', // DESCRIPCIÓN
            'Algodón',  // BASE TELA
            'Hilaza 20',// BASE HILAZA
            'Adicional',// CAMPO ADICIONAL
            'Nike',     // MARCA
            'Mundo 1',  // MUNDO
            'Línea A',  // LÍNEAS
            'Redonda',  // TIPO PUNTERA
            'M',        // TALLA
            '42',       // MEDIDA
            'cm',       // UNIDAD DE MEDIDA
            '',         // NOMBRE (formula, se sobrescribe abajo)
            'REF001',   // REFERENCIA
        ];

        // Hoja principal: items
        $mainSheet = $spreadsheet->getActiveSheet();
        $mainSheet->setTitle('items');
        $mainSheet->fromArray([$headers, $exampleRow], '', 'A1');

        // Poner los encabezados en negrita
        $headerColEnd = $mainSheet->getHighestColumn(1);
        $mainSheet->getStyle("A1:{$headerColEnd}1")->getFont()->setBold(true);

        // Ajustar el ancho de las columnas automaticamente al texto
        foreach (range('A', $headerColEnd) as $col) {
            $mainSheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Formula para autocompletar el nombre igual que en el frontend
        $colLetters = range('A', 'Z');
        $unidadColIdx = array_search('UNIDAD DE MEDIDA', $headers); // 15 (P)
        $nombreColIdx = array_search('NOMBRE', $headers); // 16 (Q)
        $formula = '=A2';
        for ($i = 6; $i < 6 + count($extraFieldNames); $i++) {
            $formula .= ' & IF(' . $colLetters[$i] . '2<>""," " & ' . $colLetters[$i] . '2,"")';
        }
        $formula .= ' & IF(B2<>""," " & B2,"")';
        // Asignar la formula en la columna correcta de "NOMBRE"
        if ($nombreColIdx !== false && isset($colLetters[$nombreColIdx])) {
            $mainSheet->setCellValue($colLetters[$nombreColIdx] . '2', $formula);
        }

        // Hoja: Subgrupos
        $subGroups = CategorySubGroup::select('id', 'name')->get()->toArray();
        $subGroupSheet = $spreadsheet->createSheet();
        $subGroupSheet->setTitle('Subgrupos');
        $subGroupSheet->fromArray(['ID', 'Nombre'], null, 'A1');
        if (!empty($subGroups)) {
            $subGroupSheet->fromArray($subGroups, null, 'A2');
        }
        $rowS = count($subGroups) + 1;

        // Hoja: Unidades de medida
        $unitMeasures = [
            'cm',
            'm',
            'mm',
            'kg',
            'g',
            'lb',
            'oz',
            'l',
            'ml',
            'unidad',
            'par',
            'docena',
            'rollo',
            'caja',
            'bolsa'
        ];
        $unitSheet = $spreadsheet->createSheet();
        $unitSheet->setTitle('Unidades');
        $unitSheet->fromArray(['Nombre'], null, 'A1');
        $unitSheet->fromArray(array_map(fn($u) => [$u], $unitMeasures), null, 'A2');
        $rowU = count($unitMeasures) + 1;

        // Hojas para campos adicionales con opciones (relacionados a modelos)
        $extraFieldOptions = [
            'Base tela' => FabricBase::select('id', 'name')->get()->toArray(),
            'Base hilaza' => YarnBase::select('id', 'name')->get()->toArray(),
            'Marca' => Brand::select('id', 'name')->get()->toArray(),
            'Mundo' => World::select('id', 'name')->get()->toArray(),
            // 'Líneas', 'Talla', 'Medida' quedan abiertos (sin hoja ni validación)
        ];

        foreach ($extraFieldOptions as $fieldName => $options) {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($fieldName);
            $sheet->fromArray(['ID', 'Nombre'], null, 'A1');
            if (!empty($options)) {
                $sheet->fromArray($options, null, 'A2');
            }
        }

        // Validaciones desplegables por nombre
        $mainSheet = $spreadsheet->setActiveSheetIndex(0);
        $subGroupLastRow = $rowS;
        $unitLastRow = $rowU;

        $maxValidationRow = 1000;

        // Validación para subgrupo
        if ($subGroupLastRow >= 2) {
            $validationSubGroup = new \PhpOffice\PhpSpreadsheet\Cell\DataValidation();
            $validationSubGroup->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST)
                ->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP)
                ->setAllowBlank(false)
                ->setShowInputMessage(true)
                ->setShowErrorMessage(true)
                ->setShowDropDown(true)
                ->setFormula1("'Subgrupos'!B2:B{$subGroupLastRow}");
            for ($row = 2; $row <= $maxValidationRow; $row++) {
                $mainSheet->getCell('A' . $row)->setDataValidation(clone $validationSubGroup);
            }
        }

        // Validacion para unidad de medida
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
                $mainSheet->getCell($unitCol . $row)->setDataValidation(clone $validationUnit);
            }
            $mainSheet->getColumnDimension($unitCol)->setAutoSize(true);
        }

        // Validaciones para campos extra con opciones
        foreach ($extraFieldNames as $idx => $fieldName) {
            if (isset($extraFieldOptions[$fieldName])) {
                $sheet = $spreadsheet->getSheetByName($fieldName);
                $lastRow = $sheet->getHighestRow();
                $col = $colLetters[6 + $idx];
                $validation = new \PhpOffice\PhpSpreadsheet\Cell\DataValidation();
                $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST)
                    ->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP)
                    ->setAllowBlank(true)
                    ->setShowInputMessage(true)
                    ->setShowErrorMessage(true)
                    ->setShowDropDown(true)
                    ->setFormula1("'{$fieldName}'!B2:B{$lastRow}");
                for ($row = 2; $row <= $maxValidationRow; $row++) {
                    $mainSheet->getCell($col . $row)->setDataValidation(clone $validation);
                }
                $mainSheet->getColumnDimension($col)->setAutoSize(true);
            }
            // Para 'Líneas', 'Talla', 'Medida' no se agrega validación, quedan abiertos
        }

        $spreadsheet->setActiveSheetIndex(0);

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');

        // Esperar si el archivo esta siendo usado por otro proceso (maximo 5 intentos)
        $maxAttempts = 5;
        $attempt = 0;
        $waitMs = 200;
        while (file_exists($filePath) && !is_writable($filePath) && $attempt < $maxAttempts) {
            usleep($waitMs * 1000);
            $attempt++;
        }

        try {
            $writer->save($filePath);
            Log::info('Plantilla guardada exitosamente', ['path' => $filePath, 'size' => filesize($filePath)]);
        } catch (\Exception $e) {
            Log::error('No se pudo guardar la plantilla Excel', [
                'error' => $e->getMessage(),
                'path' => $filePath
            ]);
            return response()->json([
                'message' => 'No se pudo guardar la plantilla Excel. Intente cerrar el archivo si está abierto y vuelva a intentarlo.',
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
        $article = Item::findOrFail($id);
        return response()->json($article->getWarehouseStocks());
    }

    // Nueva función para exportar todos los items a Excel
    public function exportExcel()
    {
        $articles = Item::with(['categorySubGroup', 'tax'])->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('items');

        $headers = [
            'ID',
            'NOMBRE',
            'REFERENCIA',
            'SUBGRUPO',
            'COLOR',
            'IMPUESTO',
            'IMPUESTO (%)',
            'CÓDIGO DE BARRAS',
            'DESCRIPCIÓN',
            'BASE TELA',
            'BASE HILAZA',
            'CAMPO ADICIONAL',
            'MARCA',
            'MUNDO',
            'LÍNEAS',
            'TIPO PUNTERA',
            'TALLA',
            'MEDIDA',
            'UNIDAD DE MEDIDA',
            'ESTADO',
            'TIPO DE ÍTEM',
        ];
        $sheet->fromArray($headers, null, 'A1');

        $rowNum = 2;
        foreach ($articles as $article) {
            $extra = is_array($article->extra_fields) ? $article->extra_fields : (json_decode($article->extra_fields, true) ?: []);
            $normalizedExtra = [];
            foreach ($extra as $key => $value) {
                $normalizedExtra[mb_strtoupper((string) $key, 'UTF-8')] = $value;
            }

            $resolveExtra = function (string $key) use ($normalizedExtra) {
                return $normalizedExtra[$key] ?? '';
            };

            $tax = $article->tax;
            $statusLabel = $article->status ? 'Activo' : 'Inactivo';
            $typeLabel = $article->item_type === 'service' ? 'Servicio' : 'Producto';

            $rowData = [
                $article->id,
                $article->name,
                $article->reference,
                optional($article->categorySubGroup)->name,
                $resolveExtra('COLOR'),
                optional($tax)->name,
                $tax ? number_format((float) $tax->percentage, 2, '.', '') : '',
                $article->bar_code,
                $article->description,
                $resolveExtra('BASE TELA'),
                $resolveExtra('BASE HILAZA'),
                $resolveExtra('CAMPO ADICIONAL'),
                $resolveExtra('MARCA'),
                $resolveExtra('MUNDO'),
                $resolveExtra('LÍNEAS'),
                $resolveExtra('TIPO PUNTERA'),
                $resolveExtra('TALLA'),
                $resolveExtra('MEDIDA'),
                $article->unit_measure,
                $statusLabel,
                $typeLabel,
            ];

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
        return Inertia::render('Inventory/Item/List', [
            'articles' => Item::with(['categorySubGroup', 'tax'])->get(),
        ]);
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
}
