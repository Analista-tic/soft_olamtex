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
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Inertia\Inertia;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Models\Common\CategorySubGroup;
use Illuminate\Support\Facades\Log;

class ItemController extends Controller
{
    public function index()
    {
        return Inertia::render('Inventory/Item/Index', [
            'articles' => Item::with(['warehouses', 'categorySubGroup', 'tax'])->get(),
        ]);
    }

    public function show($id)
    {
        return Item::with(['categorySubGroup', 'tax'])->findOrFail($id);
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
                'reference' => 'required|string|unique:items,reference',
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

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('articles', 'public');
            $data['img'] = $path;
        }

        $extraFields = [];
        if (!empty($data['extra_fields'])) {
            $extraFields = is_array($data['extra_fields'])
                ? $data['extra_fields']
                : (json_decode($data['extra_fields'], true) ?: []);
        }
        $data['extra_fields'] = $extraFields;

        $data['negative_movement'] = filter_var($data['negative_movement'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;

        $subGroupName = optional(CategorySubGroup::find($data['category_sub_group_id']))->name ?? '';
        $extrasString = $this->buildExtraFieldsNameSegment($extraFields);
        $colorName = $this->extractColorNameFromExtraFields($extraFields);
        $data['name'] = trim("$subGroupName $extrasString $colorName");

        if (!isset($data['status'])) {
            $data['status'] = true;
        }

        if (empty($data['item_type'])) {
            $data['item_type'] = 'product';
        }

        $article = Item::create($data);

        // Relacionar bodegas si se envía
        if ($request->has('warehouses')) {
            $warehouses = $request->input('warehouses');
            if (is_string($warehouses)) {
                $warehouses = json_decode($warehouses, true);
            }
            if (is_array($warehouses)) {
                foreach ($warehouses as $w) {
                    $article->warehouses()->attach(
                        $w['warehouse_id'],
                        [
                            'quantity' => $w['quantity'] ?? 0,
                            'stock_min' => $w['stock_min'] ?? 0,
                            'stock_max' => $w['stock_max'] ?? 0,
                        ]
                    );
                }
            }
        }

        return $article;
    }

    public function update(Request $request, $id)
    {
        $article = Item::findOrFail($id);

        if ($request->has('extra_fields') && is_string($request->input('extra_fields'))) {
            $decoded = json_decode($request->input('extra_fields'), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $request->merge(['extra_fields' => $decoded]);
            }
        }

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
                'reference' => 'required|string|unique:items,reference,' . $article->id,
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

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('articles', 'public');
            $data['img'] = $path;
        }

        $extraFields = [];
        if (array_key_exists('extra_fields', $data) && !empty($data['extra_fields'])) {
            $extraFields = is_array($data['extra_fields'])
                ? $data['extra_fields']
                : (json_decode($data['extra_fields'], true) ?: []);
        } else {
            $extraFields = $article->extra_fields ?? [];
        }
        $data['extra_fields'] = $extraFields;

        if (array_key_exists('negative_movement', $data)) {
            $data['negative_movement'] = filter_var($data['negative_movement'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }

        $subGroupId = $data['category_sub_group_id'] ?? $article->category_sub_group_id;
        $subGroupName = optional(CategorySubGroup::find($subGroupId))->name ?? '';
        $extrasString = $this->buildExtraFieldsNameSegment($extraFields);
        $colorName = $this->extractColorNameFromExtraFields($extraFields);
        $data['name'] = trim("$subGroupName $extrasString $colorName");

        $article->update($data);

        // Actualizar bodegas si se envía
        if ($request->has('warehouses')) {
            $warehouses = $request->input('warehouses');
            if (is_string($warehouses)) {
                $warehouses = json_decode($warehouses, true);
            }
            if (is_array($warehouses)) {
                $syncData = [];
                foreach ($warehouses as $w) {
                    $syncData[$w['warehouse_id']] = [
                        'quantity' => $w['quantity'] ?? 0,
                        'stock_min' => $w['stock_min'] ?? 0,
                        'stock_max' => $w['stock_max'] ?? 0,
                    ];
                }
                $article->warehouses()->sync($syncData);
            }
        }

        return $article;
    }

    public function destroy($id)
    {
        $article = Item::findOrFail($id);

        // Validaciones previas para evitar borrar si hay relaciones activas.
        $reasons = [];

        $warehouseCount = $article->warehouses()->count();
        if ($warehouseCount > 0) {
            $reasons[] = $warehouseCount === 1
                ? '1 bodega asignada'
                : "{$warehouseCount} bodegas asignadas";
        }

        $poCount = PurchaseOrderItem::where('item_id', $article->id)->count();
        if ($poCount > 0) {
            $reasons[] = $poCount === 1
                ? '1 orden de compra'
                : "{$poCount} ordenes de compra";
        }

        $invoiceCount = PurchaseInvoiceItem::where('item_id', $article->id)->count();
        if ($invoiceCount > 0) {
            $reasons[] = $invoiceCount === 1
                ? '1 factura de compra'
                : "{$invoiceCount} facturas de compra";
        }

        $adjustmentCount = InventoryAdjustmentLine::where('item_id', $article->id)->count();
        if ($adjustmentCount > 0) {
            $reasons[] = $adjustmentCount === 1
                ? '1 movimiento de inventario'
                : "{$adjustmentCount} movimientos de inventario";
        }

        if (!empty($reasons)) {
            $list = count($reasons) === 1
                ? $reasons[0]
                : implode(', ', array_slice($reasons, 0, -1)) . ' y ' . end($reasons);

            return response()->json([
                'message' => "No se puede eliminar el item porque tiene {$list}.",
            ], 409);
        }

        $article->warehouses()->detach();
        try {
            $article->delete();
            return response()->noContent();
        } catch (QueryException $e) {
            // Si hay claves foraneas (ajustes, compras, etc.) informamos y devolvemos 409 en vez de 500
            if ((int) $e->getCode() === 23000) {
                return response()->json([
                    'message' => 'No se puede eliminar el item porque tiene registros relacionados (ajustes de inventario, compras u otros movimientos). Elimine o reasigne esos registros primero.'
                ], 409);
            }

            throw $e;
        }
    }

    public function getData(Request $request)
    {
        $query = Item::with(['categorySubGroup', 'tax', 'warehouses']);

        if ($request->has('search') && trim($request->search) !== '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%$search%")
                    ->orWhere('bar_code', 'like', "%$search%")
                    // ->orWhereHas('warehouse', function ($w) use ($search) {
                    //     $w->where('name', 'like', "%$search%");
                    // })
                    ->orWhereHas('categorySubGroup', function ($sg) use ($search) {
                        $sg->where('name', 'like', "%$search%");
                    });
            });
        }

        if ($request->has('status') && $request->status !== '') {
            $query->where('status', (bool) $request->status);
        }

        $perPage = $request->input('per_page', 10);
        $articles = $query->paginate($perPage);

        return response()->json($articles);
    }

    public function togglestatus($id)
    {
        $article = Item::findOrFail($id);
        $article->status = !$article->status;
        $article->save();

        return response()->json([
            'status' => $article->status,
            'message' => $article->status ? 'item habilitado' : 'item deshabilitado'
        ]);
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

        // Leer encabezados y normalizarlos
        $header = array_map('trim', $rows[1]);
        unset($rows[1]);
        $inserted = 0;
        $errors = [];

        // Mapeo de encabezados a campos de base de datos
        $headerMap = [
            'SUBGRUPO' => 'category_sub_group_id',
            'IMPUesta (%)' => 'tax',
            'CÓDIGO DE BARRAS' => 'bar_code',
            'MOVIMIENTO NEGATIVO' => 'negative_movement',
            'DESCRIPCIÓN' => 'description',
            'BASE TELA' => 'base_tela',
            'BASE HILAZA' => 'base_hilaza',
            'CAMPO ADICIONAL' => 'campo_adicional',
            'MARCA' => 'marca',
            'MUNDO' => 'mundo',
            'LÍNEAS' => 'lineas',
            'TIPO PUNTERA' => 'tipo_puntera',
            'TALLA' => 'talla',
            'MEDIDA' => 'medida',
            'UNIDAD DE MEDIDA' => 'unit_measure',
            'NOMBRE' => 'name',
            'REFERENCIA' => 'reference',
        ];

        // Campos extra_fields que se guardaron como array/JSON
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

        foreach ($rows as $rowNum => $row) {
            // Saltar filas vacías
            if (empty(array_filter($row))) {
                continue;
            }

            try {
                // Mapear los datos de la fila usando el encabezado
                $rowData = [];
                foreach ($header as $colIdx => $colName) {
                    $rowData[$colName] = $row[$colIdx] ?? null;
                }

                // Buscar ID de subgrupo por nombre
                $subGroup = CategorySubGroup::where('name', $rowData['SUBGRUPO'] ?? '')->first();

                // Construir extra_fields
                $extraFields = [];
                foreach ($extraFieldsKeys as $key) {
                    if (isset($rowData[$key]) && $rowData[$key] !== '') {
                        $extraFields[$key] = $rowData[$key];
                    }
                }

                // Generar el nombre igual que en la plantilla
                $extrasString = $this->buildExtraFieldsNameSegment($extraFields);
                $colorValue = $this->extractColorNameFromExtraFields($extraFields);
                $nameParts = array_filter([
                    optional($subGroup)->name,
                    $extrasString,
                    $colorValue,
                ]);
                $name = trim(implode(' ', $nameParts));

                // Usar solo los campos que existen en la base de datos
                $articleData = [
                    'category_sub_group_id' => $subGroup->id,
                    // 'impuesta' o 'tax' segun tu base de datos, si no existe ninguno, omite esta campo
                    // 'unit_measure' eliminado porque no existe en la tabla
                    'bar_code' => $rowData['CÓDIGO DE BARRAS'] ?? null,
                    'negative_movement' => filter_var($rowData['MOVIMIENTO NEGATIVO'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
                    'description' => $rowData['DESCRIPCIÓN'] ?? '',
                    'extra_fields' => $extraFields,
                    'name' => $name,
                    'reference' => $rowData['REFERENCIA'] ?? '',
                    'status' => true,
                ];

                Item::create($articleData);
                $inserted++;
            } catch (\Throwable $e) {
                $errors[] = "Fila $rowNum: " . $e->getMessage();
                Log::error("Error en fila $rowNum al cargar items", [
                    'error' => $e->getMessage(),
                    'data' => $row
                ]);
            }
        }

        if ($inserted === 0 && !empty($errors)) {
            return response()->json([
                'message' => 'No se pudo insertar ningun item. Errores: ' . implode('; ', $errors)
            ], 422);
        }

        return response()->json([
            'message' => "Carga masiva completada. items insertados: $inserted",
            'errors' => $errors,
            'inserted' => $inserted
        ]);
    }

    private function buildExtraFieldsNameSegment(array $extraFields): string
    {
        $filtered = array_filter($extraFields, function ($value, $key) {
            if (!is_string($key)) {
                return true;
            }
            return strcasecmp(trim($key), 'color') !== 0;
        }, ARRAY_FILTER_USE_BOTH);

        $trimmed = array_filter(array_map(function ($value) {
            return trim((string) ($value ?? ''));
        }, array_values($filtered)));

        return implode(' ', $trimmed);
    }

    private function extractColorNameFromExtraFields(array $extraFields): string
    {
        foreach ($extraFields as $key => $value) {
            if (is_string($key) && strcasecmp(trim($key), 'color') === 0) {
                return trim((string) ($value ?? ''));
            }
        }

        return '';
    }

    public function getWarehouses($id)
    {
        $article = Item::findOrFail($id);
        return response()->json($article->getWarehouseStocks());
    }

    // Nueva función para exportar todos los items a Excel
    public function exportExcel()
    {
        $articles = Item::with(['categorySubGroup'])->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('items');

        // Encabezados en el mismo orden que la plantilla de carga masiva
        $headers = [
            'SUBGRUPO',
            'COLOR',
            'IMPUesta (%)',
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
        $sheet->fromArray($headers, null, 'A1');

        // Datos
        $rowNum = 2;
        foreach ($articles as $article) {
            // Extra fields
            $extra = is_array($article->extra_fields) ? $article->extra_fields : (json_decode($article->extra_fields, true) ?: []);
            $colorValue = $extra['COLOR'] ?? $extra['Color'] ?? '';
            $sheet->setCellValue("A$rowNum", optional($article->categorySubGroup)->name); // SUBGRUPO
            $sheet->setCellValue("B$rowNum", $colorValue); // COLOR
            $sheet->setCellValue("C$rowNum", $article->tax); // IMPUesta (%)
            $sheet->setCellValue("D$rowNum", $article->bar_code); // CÓDIGO DE BARRAS
            $sheet->setCellValue("E$rowNum", $article->description); // DESCRIPCIÓN
            $sheet->setCellValue("F$rowNum", $extra['BASE TELA'] ?? ''); // BASE TELA
            $sheet->setCellValue("G$rowNum", $extra['BASE HILAZA'] ?? ''); // BASE HILAZA
            $sheet->setCellValue("H$rowNum", $extra['CAMPO ADICIONAL'] ?? ''); // CAMPO ADICIONAL
            $sheet->setCellValue("I$rowNum", $extra['MARCA'] ?? ''); // MARCA
            $sheet->setCellValue("J$rowNum", $extra['MUNDO'] ?? ''); // MUNDO
            $sheet->setCellValue("K$rowNum", $extra['LÍNEAS'] ?? ''); // LÍNEAS
            $sheet->setCellValue("L$rowNum", $extra['TIPO PUNTERA'] ?? ''); // TIPO PUNTERA
            $sheet->setCellValue("M$rowNum", $extra['TALLA'] ?? ''); // TALLA
            $sheet->setCellValue("N$rowNum", $extra['MEDIDA'] ?? ''); // MEDIDA
            $sheet->setCellValue("O$rowNum", $article->unit_measure); // UNIDAD DE MEDIDA
            $sheet->setCellValue("P$rowNum", $article->name); // NOMBRE
            $sheet->setCellValue("Q$rowNum", $article->reference); // REFERENCIA
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

}
