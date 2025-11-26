<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Article;
use App\Models\ProductSheet\Brand;
use App\Models\ProductSheet\FabricBase;
use App\Models\ProductSheet\Group;
use App\Models\ProductSheet\Section;
use App\Models\ProductSheet\World;
use App\Models\ProductSheet\YarnBase;
use App\Models\Expenses\PurchaseOrderItem;
use App\Models\Expenses\PurchaseInvoiceItem;
use App\Models\Inventory\InventoryAdjustmentLine;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Models\Common\Warehouse;
use App\Models\Common\CategorySubGroup;
use App\Models\Common\Color;
use Illuminate\Support\Facades\Log;

class ArticleController extends Controller
{
    public function index()
    {
        return Inertia::render('Inventory/Articles/Index', [
            // 'articles' => Article::with(['warehouse', 'categorySubGroup', 'color'])->get(),
            'articles' => Article::with(['categorySubGroup', 'color', 'tax'])->get(),
        ]);
    }

    public function show($id)
    {
        return Article::with(['categorySubGroup', 'color', 'tax'])->findOrFail($id);
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'category_sub_group_id' => 'required|integer',
                'color_id' => 'required|integer',
                'img' => 'nullable|string',
                'tax_id' => 'nullable|integer', // <-- AÃ±adido
                'tax' => 'required|numeric',
                'bar_code' => 'nullable|string',
                'negative_movement' => 'required',
                'description' => 'required|string',
                'extra_fields' => 'nullable',
                'reference' => 'required|string|unique:articles,reference',
                'status' => 'sometimes|boolean',
                'unit_measure' => 'nullable|string',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Errores de validaciÃ³n',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'OcurriÃ³ un error inesperado al procesar la solicitud.',
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
        $colorName = optional(Color::find($data['color_id']))->name ?? '';
        $extrasString = implode(' ', array_filter(array_map('trim', array_values($extraFields))));
        $data['name'] = trim("$subGroupName $extrasString $colorName");

        if (!isset($data['status'])) {
            $data['status'] = true;
        }

        $article = Article::create($data);

        // Relacionar bodegas si se envÃ­a
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
        $article = Article::findOrFail($id);

        if ($request->has('extra_fields') && is_string($request->input('extra_fields'))) {
            $decoded = json_decode($request->input('extra_fields'), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $request->merge(['extra_fields' => $decoded]);
            }
        }

        try {
            $data = $request->validate([
                'category_sub_group_id' => 'sometimes|integer',
                'color_id' => 'sometimes|integer',
                'img' => 'nullable|string',
                'tax_id' => 'nullable|integer', // <-- AÃ±adido
                'tax' => 'sometimes|numeric',
                'bar_code' => 'nullable|string',
                'negative_movement' => 'sometimes',
                'description' => 'sometimes|string',
                'extra_fields' => 'nullable',
                'reference' => 'required|string|unique:articles,reference,' . $article->id,
                'status' => 'sometimes|boolean',
                'unit_measure' => 'nullable|string',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Errores de validaciÃ³n',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'OcurriÃ³ un error inesperado al procesar la solicitud.',
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
        $colorId = $data['color_id'] ?? $article->color_id;
        $subGroupName = optional(CategorySubGroup::find($subGroupId))->name ?? '';
        $colorName = optional(Color::find($colorId))->name ?? '';
        $extrasString = implode(' ', array_filter(array_map('trim', array_values($extraFields))));
        $data['name'] = trim("$subGroupName $extrasString $colorName");

        $article->update($data);

        // Actualizar bodegas si se envÃ­a
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
        $article = Article::findOrFail($id);

        // Validaciones previas para evitar borrar si hay relaciones activas.
        $reasons = [];

        $warehouseCount = $article->warehouses()->count();
        if ($warehouseCount > 0) {
            $reasons[] = $warehouseCount === 1
                ? '1 bodega asignada'
                : "{$warehouseCount} bodegas asignadas";
        }

        $poCount = PurchaseOrderItem::where('article_id', $article->id)->count();
        if ($poCount > 0) {
            $reasons[] = $poCount === 1
                ? '1 orden de compra'
                : "{$poCount} ordenes de compra";
        }

        $invoiceCount = PurchaseInvoiceItem::where('article_id', $article->id)->count();
        if ($invoiceCount > 0) {
            $reasons[] = $invoiceCount === 1
                ? '1 factura de compra'
                : "{$invoiceCount} facturas de compra";
        }

        $adjustmentCount = InventoryAdjustmentLine::where('article_id', $article->id)->count();
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
                'message' => "No se puede eliminar el articulo porque tiene {$list}.",
            ], 409);
        }

        $article->warehouses()->detach();
        try {
            $article->delete();
            return response()->noContent();
        } catch (QueryException $e) {
            // Si hay claves forÃ¡neas (ajustes, compras, etc.) informamos y devolvemos 409 en vez de 500
            if ((int) $e->getCode() === 23000) {
                return response()->json([
                    'message' => 'No se puede eliminar el articulo porque tiene registros relacionados (ajustes de inventario, compras u otros movimientos). Elimine o reasigne esos registros primero.'
                ], 409);
            }

            throw $e;
        }
    }

    public function getData(Request $request)
    {
        $query = Article::with(['categorySubGroup', 'color', 'tax']); // <-- AÃ±adido 'tax'

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
                    })
                    ->orWhereHas('color', function ($c) use ($search) {
                        $c->where('name', 'like', "%$search%");
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

    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:2048'
        ]);
        $path = $request->file('image')->store('articles', 'public');
        return response()->json([
            'url' => Storage::url($path)
        ]);
    }

    public function apiIndex(Request $request)
    {
        $query = Article::with(['categorySubGroup', 'color', 'tax']);

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
                    })
                    ->orWhereHas('color', function ($c) use ($search) {
                        $c->where('name', 'like', "%$search%");
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
        $article = Article::findOrFail($id);
        $article->status = !$article->status;
        $article->save();

        return response()->json([
            'status' => $article->status,
            'message' => $article->status ? 'articulo habilitado' : 'articulo deshabilitado'
        ]);
    }

    public function downloadTemplate()
    {
        $fileName = 'plantilla_articulos.xlsx';
        $filePath = storage_path('app/public/' . $fileName);

        $spreadsheet = new Spreadsheet();

        // Extra fields (EspaÃ±ol, para la plantilla)
        $extraFieldNames = [
            'Base tela',
            'Base hilaza',
            'Campo adicional',
            'Marca',
            'Mundo',
            'LÃ­neas',
            'Tipo puntera',
            'Talla',
            'Medida'
        ];

        // Encabezados en espaÃ±ol y mayÃºscula
        $headers = [
            'SUBGRUPO',
            'COLOR',
            'IMPUesta (%)',
            'CÃ“DIGO DE BARRAS',
            'DESCRIPCIÃ“N',
            'BASE TELA',
            'BASE HILAZA',
            'CAMPO ADICIONAL',
            'MARCA',
            'MUNDO',
            'LÃNEAS',
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
            '123456',   // CÃ“DIGO DE BARRAS
            'Ejemplo de articulo', // DESCRIPCIÃ“N
            'AlgodÃ³n',  // BASE TELA
            'Hilaza 20',// BASE HILAZA
            'Adicional',// CAMPO ADICIONAL
            'Nike',     // MARCA
            'Mundo 1',  // MUNDO
            'LÃ­nea A',  // LÃNEAS
            'Redonda',  // TIPO PUNTERA
            'M',        // TALLA
            '42',       // MEDIDA
            'cm',       // UNIDAD DE MEDIDA
            '',         // NOMBRE (fÃ³rmula, se sobrescribe abajo)
            'REF001',   // REFERENCIA
        ];

        // Hoja principal: articulos
        $mainSheet = $spreadsheet->getActiveSheet();
        $mainSheet->setTitle('articulos');
        $mainSheet->fromArray([$headers, $exampleRow], '', 'A1');

        // Poner los encabezados en negrita
        $headerColEnd = $mainSheet->getHighestaolumn(1);
        $mainSheet->getStyle("A1:{$headerColEnd}1")->getFont()->setBold(true);

        // Ajustar el ancho de las columnas automÃ¡ticamente al texto
        foreach (range('A', $headerColEnd) as $col) {
            $mainSheet->getColumnDimension($col)->setAutoSize(true);
        }

        // FÃ³rmula para autocompletar el nombre igual que en el frontend
        $colLetters = range('A', 'Z');
        $unidadColIdx = array_search('UNIDAD DE MEDIDA', $headers); // 15 (P)
        $nombreColIdx = array_search('NOMBRE', $headers); // 16 (Q)
        $formula = '=A2';
        for ($i = 6; $i < 6 + count($extraFieldNames); $i++) {
            $formula .= ' & IF(' . $colLetters[$i] . '2<>""," " & ' . $colLetters[$i] . '2,"")';
        }
        $formula .= ' & IF(B2<>""," " & B2,"")';
        // Asignar la fÃ³rmula en la columna correcta de "NOMBRE"
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

        // Hoja: Colores
        $colors = Color::select('id', 'name')->get()->toArray();
        $colorSheet = $spreadsheet->createSheet();
        $colorSheet->setTitle('Colores');
        $colorSheet->fromArray(['ID', 'Nombre'], null, 'A1');
        if (!empty($colors)) {
            $colorSheet->fromArray($colors, null, 'A2');
        }
        $rowC = count($colors) + 1;

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
            // 'LÃ­neas', 'Talla', 'Medida' quedan abiertos (sin hoja ni validaciÃ³n)
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
        $colorLastRow = $rowC;
        $unitLastRow = $rowU;

        $maxValidationRow = 1000;

        // ValidaciÃ³n para subgrupo
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

        // ValidaciÃ³n para color
        if ($colorLastRow >= 2) {
            $validationColor = new \PhpOffice\PhpSpreadsheet\Cell\DataValidation();
            $validationColor->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST)
                ->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP)
                ->setAllowBlank(false)
                ->setShowInputMessage(true)
                ->setShowErrorMessage(true)
                ->setShowDropDown(true)
                ->setFormula1("'Colores'!B2:B{$colorLastRow}");
            for ($row = 2; $row <= $maxValidationRow; $row++) {
                $mainSheet->getCell('B' . $row)->setDataValidation(clone $validationColor);
            }
        }

        // ValidaciÃ³n para unidad de medida
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
                $lastRow = $sheet->getHighestaow();
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
            // Para 'LÃ­neas', 'Talla', 'Medida' no se agrega validaciÃ³n, quedan abiertos
        }

        $spreadsheet->setActiveSheetIndex(0);

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');

        // Esperar si el archivo esta¡ siendo usado por otro proceso (mÃ¡ximo 5 intentos)
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
                'message' => 'No se pudo guardar la plantilla Excel. Intente cerrar el archivo si esta¡ abierto y vuelva a intentarlo.',
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

    public function generateAndSaveTemplate($withResponse = true)
    {
        Log::info('Generando plantilla de articulos');

        $spreadsheet = new Spreadsheet();

        // Hoja principal: articulos
        $mainSheet = $spreadsheet->getActiveSheet();
        $mainSheet->setTitle('articulos');
        // Encabezados correctos para la carga masiva
        $mainSheet->fromArray([
            [
                'category_sub_group_id',
                'color_id',
                'img',
                'tax', // Cambiado de 'impuesta' a 'tax'
                'bar_code',
                'negative_movement',
                'description',
                'extra_fields',
                'name',
                'reference',
                'status'
            ],
            [
                '1', // category_sub_group_id
                '1', // color_id
                '',  // img
                '19', // tax
                '1234567890123', // bar_code
                '1', // negative_movement
                'Ejemplo de articulo', // description
                '{"talla":"M","material":"algodÃ³n"}', // extra_fields
                'Nombre generado', // name
                'REF001', // reference
                '1' // status
            ]
        ], '', 'A1'); // Cambiado null por ''

        // Hoja: Bodegas
        $warehouseSheet = $spreadsheet->createSheet();
        $warehouseSheet->setTitle('Bodegas');
        $warehouses = Warehouse::select('id', 'name')->get();
        $warehouseSheet->fromArray(['ID', 'Nombre'], null, 'A1');
        $row = 2;
        foreach ($warehouses as $warehouse) {
            $warehouseSheet->setCellValue("A$row", $warehouse->id);
            $warehouseSheet->setCellValue("B$row", $warehouse->name);
            $row++;
        }

        // Hoja: Subgrupos
        $subGroupSheet = $spreadsheet->createSheet();
        $subGroupSheet->setTitle('Subgrupos');
        $subGroups = CategorySubGroup::select('id', 'name')->get();
        $subGroupSheet->fromArray(['ID', 'Nombre'], null, 'A1');
        $row = 2;
        foreach ($subGroups as $sg) {
            $subGroupSheet->setCellValue("A$row", $sg->id);
            $subGroupSheet->setCellValue("B$row", $sg->name);
            $row++;
        }

        // Hoja: Colores
        $colorSheet = $spreadsheet->createSheet();
        $colorSheet->setTitle('Colores');
        $colors = Color::select('id', 'name')->get();
        $colorSheet->fromArray(['ID', 'Nombre'], null, 'A1');
        $row = 2;
        foreach ($colors as $color) {
            $colorSheet->setCellValue("A$row", $color->id);
            $colorSheet->setCellValue("B$row", $color->name);
            $row++;
        }

        $spreadsheet->setActiveSheetIndex(0);

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $fileName = 'plantilla_articulos.xlsx';
        $path = storage_path('app/public/' . $fileName);

        // Asegurarse que el directorio existe
        if (!file_exists(dirname($path))) {
            if (!mkdir(dirname($path), 0755, true)) {
                Log::error('No se pudo crear el directorio para la plantilla', ['path' => dirname($path)]);
                throw new \Exception("No se pudo crear el directorio para guardar la plantilla.");
            }
        }

        // Esperar si el archivo esta¡ siendo usado por otro proceso (mÃ¡ximo 5 intentos)
        $maxAttempts = 5;
        $attempt = 0;
        $waitMs = 200;
        while (file_exists($path) && !is_writable($path) && $attempt < $maxAttempts) {
            usleep($waitMs * 1000); // Espera en microsegundos
            $attempt++;
        }

        try {
            $writer->save($path);
            Log::info('Plantilla guardada exitosamente', ['path' => $path, 'size' => filesize($path)]);

            if ($withResponse) {
                return response()->json([
                    'message' => 'Plantilla generada y guardada correctamente',
                    'path' => $path
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Error al guardar la plantilla', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            if ($withResponse) {
                return response()->json([
                    'message' => 'Error al guardar la plantilla: ' . $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ], 500);
            }
            throw $e;
        }
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
            'COLOR' => 'color_id',
            'IMPUesta (%)' => 'tax',
            'CÃ“DIGO DE BARRAS' => 'bar_code',
            'MOVIMIENTO NEGATIVO' => 'negative_movement',
            'DESCRIPCIÃ“N' => 'description',
            'BASE TELA' => 'base_tela',
            'BASE HILAZA' => 'base_hilaza',
            'CAMPO ADICIONAL' => 'campo_adicional',
            'MARCA' => 'marca',
            'MUNDO' => 'mundo',
            'LÃNEAS' => 'lineas',
            'TIPO PUNTERA' => 'tipo_puntera',
            'TALLA' => 'talla',
            'MEDIDA' => 'medida',
            'UNIDAD DE MEDIDA' => 'unit_measure',
            'NOMBRE' => 'name',
            'REFERENCIA' => 'reference',
        ];

        // Campos extra_fields que se guardarÃ¡n como array/JSON
        $extraFieldsKeys = [
            'BASE TELA',
            'BASE HILAZA',
            'CAMPO ADICIONAL',
            'MARCA',
            'MUNDO',
            'LÃNEAS',
            'TIPO PUNTERA',
            'TALLA',
            'MEDIDA'
        ];

        foreach ($rows as $rowNum => $row) {
            // Saltar filas vacÃ­as
            if (empty(array_filter($row))) {
                continue;
            }

            try {
                // Mapear los datos de la fila usando el encabezado
                $rowData = [];
                foreach ($header as $colIdx => $colName) {
                    $rowData[$colName] = $row[$colIdx] ?? null;
                }

                // Buscar IDs para subgrupo y color por nombre
                $subGroup = CategorySubGroup::where('name', $rowData['SUBGRUPO'] ?? '')->first();
                $color = Color::where('name', $rowData['COLOR'] ?? '')->first();

                if (!$subGroup || !$color) {
                    $errors[] = "Fila $rowNum: Subgrupo o color no encontrado";
                    continue;
                }

                // Construir extra_fields
                $extraFields = [];
                foreach ($extraFieldsKeys as $key) {
                    if (isset($rowData[$key]) && $rowData[$key] !== '') {
                        $extraFields[$key] = $rowData[$key];
                    }
                }

                // Generar el nombre igual que en la plantilla
                $extrasString = implode(' ', array_filter(array_map('trim', array_values($extraFields))));
                $name = trim(
                    $subGroup->name . ' ' .
                    $extrasString . ' ' .
                    ($color->name ?? '')
                );

                // Usar solo los campos que existen en la base de datos
                $articleData = [
                    'category_sub_group_id' => $subGroup->id,
                    'color_id' => $color->id,
                    // 'impuesta' o 'tax' segÃºn tu base de datos, si no existe ninguno, omite esta campo
                    // 'unit_measure' eliminado porque no existe en la tabla
                    'bar_code' => $rowData['CÃ“DIGO DE BARRAS'] ?? null,
                    'negative_movement' => filter_var($rowData['MOVIMIENTO NEGATIVO'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
                    'description' => $rowData['DESCRIPCIÃ“N'] ?? '',
                    'extra_fields' => $extraFields,
                    'name' => $name,
                    'reference' => $rowData['REFERENCIA'] ?? '',
                    'status' => true,
                ];

                Article::create($articleData);
                $inserted++;
            } catch (\Throwable $e) {
                $errors[] = "Fila $rowNum: " . $e->getMessage();
                Log::error("Error en fila $rowNum al cargar articulos", [
                    'error' => $e->getMessage(),
                    'data' => $row
                ]);
            }
        }

        if ($inserted === 0 && !empty($errors)) {
            return response()->json([
                'message' => 'No se pudo insertar ningÃºn articulo. Errores: ' . implode('; ', $errors)
            ], 422);
        }

        return response()->json([
            'message' => "Carga masiva completada. articulos insertados: $inserted",
            'errors' => $errors,
            'inserted' => $inserted
        ]);
    }

    // Nuevo mÃ©todo para obtener bodegas y stock de un articulo
    public function getWarehouses($id)
    {
        $article = Article::findOrFail($id);
        return response()->json($article->getWarehousestacks());
    }

    // Nuevo mÃ©todo para actualizar stock de un articulo en una bodega
    public function updateWarehousestack(Request $request, $id)
    {
        $article = Article::findOrFail($id);
        $request->validate([
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'quantity' => 'required|integer',
            'stock_min' => 'required|integer',
            'stock_max' => 'required|integer',
        ]);
        $article->setWarehousestack(
            $request->warehouse_id,
            $request->quantity,
            $request->stock_min,
            $request->stock_max
        );
        return response()->json(['success' => true]);
    }

    // Nuevo mÃ©todo para eliminar la relaciÃ³n con una bodega
    public function detachWarehouse($id, $warehouseId)
    {
        $article = Article::findOrFail($id);
        $article->removeWarehouse($warehouseId);
        return response()->json(['success' => true]);
    }

    // Nueva funciÃ³n para exportar todos los articulos a Excel
    public function exportExcel()
    {
        $articles = Article::with(['categorySubGroup', 'color'])->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('articulos');

        // Encabezados en el mismo orden que la plantilla de carga masiva
        $headers = [
            'SUBGRUPO',
            'COLOR',
            'IMPUesta (%)',
            'CÃ“DIGO DE BARRAS',
            'DESCRIPCIÃ“N',
            'BASE TELA',
            'BASE HILAZA',
            'CAMPO ADICIONAL',
            'MARCA',
            'MUNDO',
            'LÃNEAS',
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
            $sheet->setCellValue("A$rowNum", optional($article->categorySubGroup)->name); // SUBGRUPO
            $sheet->setCellValue("B$rowNum", optional($article->color)->name); // COLOR
            $sheet->setCellValue("C$rowNum", $article->tax); // IMPUesta (%)
            $sheet->setCellValue("D$rowNum", $article->bar_code); // CÃ“DIGO DE BARRAS
            $sheet->setCellValue("E$rowNum", $article->description); // DESCRIPCIÃ“N
            $sheet->setCellValue("F$rowNum", $extra['BASE TELA'] ?? ''); // BASE TELA
            $sheet->setCellValue("G$rowNum", $extra['BASE HILAZA'] ?? ''); // BASE HILAZA
            $sheet->setCellValue("H$rowNum", $extra['CAMPO ADICIONAL'] ?? ''); // CAMPO ADICIONAL
            $sheet->setCellValue("I$rowNum", $extra['MARCA'] ?? ''); // MARCA
            $sheet->setCellValue("J$rowNum", $extra['MUNDO'] ?? ''); // MUNDO
            $sheet->setCellValue("K$rowNum", $extra['LÃNEAS'] ?? ''); // LÃNEAS
            $sheet->setCellValue("L$rowNum", $extra['TIPO PUNTERA'] ?? ''); // TIPO PUNTERA
            $sheet->setCellValue("M$rowNum", $extra['TALLA'] ?? ''); // TALLA
            $sheet->setCellValue("N$rowNum", $extra['MEDIDA'] ?? ''); // MEDIDA
            $sheet->setCellValue("O$rowNum", $article->unit_measure); // UNIDAD DE MEDIDA
            $sheet->setCellValue("P$rowNum", $article->name); // NOMBRE
            $sheet->setCellValue("Q$rowNum", $article->reference); // REFERENCIA
            $rowNum++;
        }

        // Ajustar ancho de columnas
        foreach (range('A', $sheet->getHighestaolumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Descargar archivo
        $fileName = 'articulos_exportados.xlsx';
        $tempFile = tempnam(sys_get_temp_dir(), 'articulos_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);
    }

    public function listView()
    {
        return Inertia::render('Inventory/Articles/List', [
            'articles' => Article::with(['categorySubGroup', 'color', 'tax'])->get(),
        ]);
    }

    public function adjustmentView()
    {
        return Inertia::render('Inventory/Articles/InventoryAdjustment');
    }

}








