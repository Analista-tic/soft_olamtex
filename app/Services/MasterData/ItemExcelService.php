<?php

namespace App\Services\MasterData;

use App\Models\Finance\Tax;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseItem;
use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Item;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Comment;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Servicio responsable de la generación y exportación de archivos Excel
 * para el módulo de Ítems (plantilla de importación y exportación de datos).
 *
 * Extraído de ItemController para separar responsabilidades.
 */
class ItemExcelService
{
    // ─── Encabezados ─────────────────────────────────────────

    // Encabezados para exportación (incluye N° ITEM para identificar ítems al reimportar)
    public function templateHeaders(): array
    {
        return array_merge(['N° ITEM'], $this->importTemplateHeaders());
    }

    // Encabezados para plantilla vacía (sin N° ITEM — el ID lo asigna la BD automáticamente)
    public function importTemplateHeaders(): array
    {
        return [
            'TIPO DE ITEM',
            'NOMBRE',
            'GRUPO',
            'SUBGRUPO',
            'IMPUESTO (%)',
            'BODEGA',
            'PRECIO',
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
            'COLOR',
            'ESTADO',
        ];
    }

    // ─── Descarga de plantilla vacía ─────────────────────────

    /**
     * Genera y retorna la descarga de la plantilla Excel para importación masiva.
     */
    public function downloadTemplate(): BinaryFileResponse|\Illuminate\Http\JsonResponse
    {
        $fileName = 'plantilla_items.xlsx';
        $filePath = storage_path('app/public/' . $fileName);

        $spreadsheet = new Spreadsheet();
        $headers = $this->importTemplateHeaders();
        $catalogData = $this->loadCatalogData();

        // Hoja principal con encabezados + fila de ejemplo
        $mainSheet = $spreadsheet->getActiveSheet();
        $mainSheet->setTitle('items');

        $mainSheet->fromArray([$headers], '', 'A1');

        // Estilo del encabezado
        $lastCol = $mainSheet->getHighestColumn();
        $mainSheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
        foreach (range('A', $lastCol) as $col) {
            $mainSheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Color de fondo compartido para CAMPOS ADICIONALES y VALORES ADICIONALES
        $this->applyAdditionalFieldsStyle($mainSheet, $headers);

        // Comentarios flotantes en CAMPOS ADICIONALES y VALORES ADICIONALES
        $this->applyAdditionalFieldsComments($mainSheet, $headers);

        // Hoja de instrucciones
        $this->buildInstructionsSheet($spreadsheet);

        // Construir hojas de referencia y aplicar validaciones
        $sheetData = $this->buildReferenceSheets($spreadsheet, $catalogData);
        $mainSheet = $spreadsheet->setActiveSheetIndex(0);
        $this->applyAllValidations($mainSheet, $headers, $sheetData, 1000);

        // Mover Instrucciones al inicio para que sea la primera hoja visible
        $this->moveInstructionsToFirst($spreadsheet);

        try {
            $this->saveSpreadsheetToPath($spreadsheet, $filePath);
        } catch (\Exception $e) {
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

    // ─── Exportación de datos existentes ─────────────────────

    /**
     * Genera y retorna la descarga del archivo Excel con todos los ítems existentes.
     */
    public function exportAll(): BinaryFileResponse
    {
        // Eager loading: warehouseItems.warehouse evita N+1.
        // No se carga 'warehouses' porque esa relación es hasManyThrough (sin pivot usable).
        $items = Item::with(['categorySubGroup.categoryGroup', 'tax', 'warehouseItems.warehouse', 'color'])->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('items');

        $headers = $this->templateHeaders();
        $sheet->fromArray($headers, null, 'A1');

        // Política: una fila por ítem+bodega, porque BODEGA, PRECIO y CANTIDAD son por bodega.
        $rowNum = 2;
        foreach ($items as $item) {
            $itemWarehouseItems = $item->warehouseItems;
            if ($itemWarehouseItems->isEmpty()) {
                // Ítem sin stock registrado: exportar con columnas de bodega vacías.
                $sheet->fromArray($this->exportRowForItem($item, null), null, "A{$rowNum}");
                $rowNum++;
            } else {
                foreach ($itemWarehouseItems as $warehouseItem) {
                    $sheet->fromArray($this->exportRowForItem($item, $warehouseItem), null, "A{$rowNum}");
                    $rowNum++;
                }
            }
        }

        $catalogData = $this->loadCatalogData();
        $sheetData = $this->buildReferenceSheets($spreadsheet, $catalogData);
        $mainSheet = $spreadsheet->setActiveSheetIndex(0);
        $this->applyAllValidations($mainSheet, $headers, $sheetData, max(1000, $rowNum));

        $lastCol = $sheet->getHighestColumn();
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $this->applyAdditionalFieldsStyle($sheet, $headers);
        $this->applyAdditionalFieldsComments($sheet, $headers);
        $this->buildInstructionsSheet($spreadsheet);

        // Mover Instrucciones al inicio para que sea la primera hoja visible
        $this->moveInstructionsToFirst($spreadsheet);

        $fileName = 'items_exportados.xlsx';
        $tempFile = tempnam(sys_get_temp_dir(), 'items_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);
    }

    /**
     * Resuelve el último precio unitario conocido para cada item_id con exactamente 2 queries.
     *
     * Prioridad:
     *   1. Último unit_price de línea de factura de compra (purchase_invoice).
     *   2. Último unit_price de movimiento de inventario (entrada).
     *
     * @return array<int, float>  item_id → precio
     */
    private function loadLatestPriceByItem(): array
    {
        // 1. Último precio por factura de compra.
        $invoicePrices = DB::table('purchase_document_items as pdi')
            ->join('purchase_documents as pd', 'pd.id', '=', 'pdi.purchase_document_id')
            ->where('pd.document_type', 'purchase_invoice')
            ->whereNotNull('pdi.item_id')
            ->where('pdi.price', '>', 0)
            ->orderByDesc('pd.invoiced_at')
            ->orderByDesc('pd.created_at')
            ->orderByDesc('pdi.id')
            ->select('pdi.item_id', 'pdi.price')
            ->get()
            ->unique('item_id')
            ->pluck('price', 'item_id')
            ->map(fn($v) => (float) $v)
            ->all();

        // 2. Último unit_price de movimiento de inventario (fallback).
        $movementPrices = DB::table('inventory_movements')
            ->where('type', 'entrada')
            ->whereNotNull('unit_price')
            ->where('unit_price', '>', 0)
            ->whereNotNull('item_id')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->select('item_id', 'unit_price')
            ->get()
            ->unique('item_id')
            ->pluck('unit_price', 'item_id')
            ->map(fn($v) => (float) $v)
            ->all();

        // Combinar: factura tiene prioridad sobre movimiento.
        $result = $movementPrices;
        foreach ($invoicePrices as $itemId => $price) {
            $result[(int) $itemId] = $price;
        }

        return $result;
    }

    // ─── Métodos internos: catálogos y hojas de referencia ───

    /**
     * Carga todos los datos de catálogo necesarios para las hojas de referencia.
     * Unifica la carga que antes se duplicaba en downloadTemplate y exportExcel.
     */
    private function loadCatalogData(): array
    {
        $groups = CategoryGroup::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $subGroups = CategorySubGroup::with('categoryGroup')->get()->map(function ($sg) {
            return [
                'id' => $sg->id,
                'name' => $sg->name,
                'group' => optional($sg->categoryGroup)->name ?? '',
            ];
        })->toArray();

        $taxes = Tax::query()
            ->where('status', true)
            ->orderBy('percentage')
            ->get(['id', 'name', 'percentage']);

        $warehouses = Warehouse::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $unitMeasures = $this->loadUnitMeasureOptionsForTemplate();

        return compact('groups', 'subGroups', 'taxes', 'warehouses', 'unitMeasures');
    }

    /**
     * Construye todas las hojas de referencia (Subgrupos, Grupos, Unidades, Impuestos, Bodegas, Listas)
     * y retorna los datos de recuento necesarios para las validaciones.
     *
     * Este método unifica la lógica que estaba duplicada entre downloadTemplate() y exportExcel().
     */
    private function buildReferenceSheets(Spreadsheet $spreadsheet, array $catalogData): array
    {
        $groups = $catalogData['groups'];
        $subGroups = $catalogData['subGroups'];
        $taxes = $catalogData['taxes'];
        $warehouses = $catalogData['warehouses'];
        $unitMeasures = $catalogData['unitMeasures'];

        // Hoja Subgrupos
        $subGroupSheet = $spreadsheet->createSheet();
        $subGroupSheet->setTitle('Subgrupos');
        $subGroupSheet->fromArray(['ID', 'Nombre', 'Grupo'], null, 'A1');
        if (!empty($subGroups)) {
            $rows = array_map(fn($s) => [$s['id'], $s['name'], $s['group']], $subGroups);
            $subGroupSheet->fromArray($rows, null, 'A2');
        }
        $subGroupLastRow = count($subGroups) + 1;

        // Hoja Grupos
        $groupSheet = $spreadsheet->createSheet();
        $groupSheet->setTitle('Grupos');
        $groupSheet->fromArray(['ID', 'Nombre'], null, 'A1');
        if ($groups->isNotEmpty()) {
            $groupSheet->fromArray(
                $groups->map(fn($group) => [$group->id, $group->name])->toArray(),
                null,
                'A2'
            );
        }
        $groupLastRow = $groups->count() + 1;

        // Hoja Unidades
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

        // Hoja Impuestos
        $taxSheet = $spreadsheet->createSheet();
        $taxSheet->setTitle('Impuestos');
        $taxSheet->fromArray(['ID', 'Nombre', 'Porcentaje'], null, 'A1');
        if ($taxes->isNotEmpty()) {
            $taxSheet->fromArray(
                $taxes->map(fn($tax) => [
                    $tax->id,
                    $tax->name,
                    number_format((float) $tax->percentage, 2, '.', ''),
                ])->toArray(),
                null,
                'A2'
            );
        }
        $taxLastRow = $taxes->count() + 1;

        // Hoja Bodegas
        $warehouseSheet = $spreadsheet->createSheet();
        $warehouseSheet->setTitle('Bodegas');
        $warehouseSheet->fromArray(['ID', 'Nombre'], null, 'A1');
        if ($warehouses->isNotEmpty()) {
            $warehouseSheet->fromArray(
                $warehouses->map(fn($warehouse) => [
                    $warehouse->id,
                    $warehouse->name,
                ])->toArray(),
                null,
                'A2'
            );
        }
        $warehouseLastRow = $warehouses->count() + 1;

        // Hoja Listas (Estado, Movimiento negativo)
        $listRows = [
            ['Activo', 'NO'],
            ['Inactivo', 'SI'],
        ];
        $listSheet = $spreadsheet->createSheet();
        $listSheet->setTitle('Listas');
        $listSheet->fromArray(['ESTADO', 'MOVIMIENTO NEGATIVO'], null, 'A1');
        $listSheet->fromArray($listRows, null, 'A2');
        $listLastRow = count($listRows) + 1;

        return compact(
            'groups', 'subGroupLastRow', 'groupLastRow',
            'unitLastRow', 'taxes', 'taxLastRow',
            'warehouses', 'warehouseLastRow', 'listLastRow'
        );
    }

    // ─── Validaciones de celdas Excel ────────────────────────

    /**
     * Aplica todas las validaciones de lista desplegable a la hoja principal.
     */
    private function applyAllValidations(
        Worksheet $mainSheet,
        array $headers,
        array $sheetData,
        int $maxValidationRow,
    ): void {
        // Subgrupos
        if ($sheetData['subGroupLastRow'] >= 2) {
            $this->applyListValidationToSheet(
                $mainSheet, $headers, 'SUBGRUPO',
                "'Subgrupos'!B2:B{$sheetData['subGroupLastRow']}",
                $maxValidationRow, false
            );
        }

        // Unidades
        $this->applyUnitValidationToSheet($mainSheet, $headers, $sheetData['unitLastRow'], $maxValidationRow);

        // Grupos
        if ($sheetData['groups']->isNotEmpty()) {
            $this->applyListValidationToSheet(
                $mainSheet, $headers, 'GRUPO',
                "'Grupos'!B2:B{$sheetData['groupLastRow']}",
                $maxValidationRow, true
            );
        }

        // Impuestos
        if ($sheetData['taxes']->isNotEmpty()) {
            $this->applyListValidationToSheet(
                $mainSheet, $headers, 'IMPUESTO (%)',
                "'Impuestos'!C2:C{$sheetData['taxLastRow']}",
                $maxValidationRow, true
            );
        }

        // Bodegas
        if ($sheetData['warehouses']->isNotEmpty()) {
            $this->applyListValidationToSheet(
                $mainSheet, $headers, 'BODEGA',
                "'Bodegas'!B2:B{$sheetData['warehouseLastRow']}",
                $maxValidationRow, true
            );
        }

        // Estado
        $this->applyListValidationToSheet(
            $mainSheet, $headers, 'ESTADO',
            "'Listas'!A2:A{$sheetData['listLastRow']}",
            $maxValidationRow, true
        );

        // Movimiento negativo
        $this->applyListValidationToSheet(
            $mainSheet, $headers, 'MOVIMIENTO NEGATIVO',
            "'Listas'!B2:B{$sheetData['listLastRow']}",
            $maxValidationRow, true
        );
    }

    private function applyUnitValidationToSheet(
        Worksheet $sheet,
        array $headers,
        int $unitLastRow,
        int $maxValidationRow = 1000,
    ): void {
        $colLetters = range('A', 'Z');
        $unitColIdx = array_search('UNIDAD DE MEDIDA', $headers);
        if ($unitColIdx === false || !isset($colLetters[$unitColIdx])) {
            return;
        }

        $unitCol = $colLetters[$unitColIdx];
        $validation = $this->buildListValidation("'Unidades'!A2:A{$unitLastRow}", true);

        for ($row = 2; $row <= $maxValidationRow; $row++) {
            $sheet->getCell($unitCol . $row)->setDataValidation(clone $validation);
        }
        $sheet->getColumnDimension($unitCol)->setAutoSize(true);
    }

    private function applyListValidationToSheet(
        Worksheet $sheet,
        array $headers,
        string $headerName,
        string $formula,
        int $maxValidationRow = 1000,
        bool $allowBlank = true,
    ): void {
        $colLetters = range('A', 'Z');
        $colIdx = array_search($headerName, $headers);
        if ($colIdx === false || !isset($colLetters[$colIdx])) {
            return;
        }

        $col = $colLetters[$colIdx];
        $validation = $this->buildListValidation($formula, $allowBlank);

        for ($row = 2; $row <= $maxValidationRow; $row++) {
            $sheet->getCell($col . $row)->setDataValidation(clone $validation);
        }
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    private function buildListValidation(string $formula, bool $allowBlank): DataValidation
    {
        $validation = new DataValidation();
        $validation->setType(DataValidation::TYPE_LIST)
            ->setErrorStyle(DataValidation::STYLE_STOP)
            ->setAllowBlank($allowBlank)
            ->setShowInputMessage(true)
            ->setShowErrorMessage(true)
            ->setShowDropDown(true)
            ->setFormula1($formula);

        return $validation;
    }

    // ─── Helpers de datos ────────────────────────────────────

    /**
     * Convierte un Item a un array representando una fila de exportación Excel.
     */
    public function exportRowForItem($item, ?WarehouseItem $warehouseItem = null): array
    {
        $extra = is_array($item->extra_fields) ? $item->extra_fields : (json_decode($item->extra_fields, true) ?: []);
        $migrationTerms = ['LEGACY', 'MIGRATION', 'SOURCE', 'RAW_ROW', 'BATCH'];
        $extra = array_filter($extra, function ($value, $key) use ($migrationTerms): bool {
            $normalizedKey = mb_strtoupper((string) $key, 'UTF-8');

            foreach ($migrationTerms as $term) {
                if (str_contains($normalizedKey, $term)) {
                    return false;
                }
            }

            return true;
        }, ARRAY_FILTER_USE_BOTH);

        $tax = $item->tax;
        $statusLabel = $item->status ? 'Activo' : 'Inactivo';
        $typeLabel = $item->item_type === 'service' ? 'Servicio' : 'Producto';

        $extraKeys = array_map(fn($k) => mb_strtoupper((string) $k, 'UTF-8'), array_keys($extra));
        $extraVals = array_map(function ($v): string {
            if (is_scalar($v) || $v === null) {
                return (string) $v;
            }

            return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }, array_values($extra));
        $fieldsCol = implode('; ', $extraKeys);
        $valuesCol = implode('; ', $extraVals);

        $groupName = optional(optional($item->categorySubGroup)->categoryGroup)->name ?? '';
        $subGroupName = optional($item->categorySubGroup)->name;

        // Datos de bodega desde warehouse_items (relación hasMany, no belongsToMany → sin pivot).
        $warehouseName = optional($warehouseItem?->warehouse)->name ?? '';
        $priceValue    = $warehouseItem?->weighted_average_price;
        $quantityValue = $warehouseItem !== null ? $warehouseItem->quantity : '';
        $stockMinValue = $warehouseItem !== null ? $warehouseItem->stock_min : '';
        $stockMaxValue = $warehouseItem !== null ? $warehouseItem->stock_max : '';

        $descriptionValue = trim((string) ($item->description ?? ''));

        if (
            mb_stripos($descriptionValue, 'Migrated from legacy system', 0, 'UTF-8') !== false
            || mb_stripos($descriptionValue, 'legacy', 0, 'UTF-8') !== false
        ) {
            $descriptionValue = '';
        }

        return [
            $item->id,
            $typeLabel,
            $item->name,
            $groupName,
            $subGroupName,
            $tax ? number_format((float) $tax->percentage, 2, '.', '') : '',
            $warehouseName,
            $priceValue !== null ? number_format((float) $priceValue, 4, '.', '') : '',
            $quantityValue,
            $stockMinValue,
            $stockMaxValue,
            $item->unit_measure,
            $item->bar_code,
            $item->reference,
            $descriptionValue,
            $item->negative_movement ? 'SI' : 'NO',
            $fieldsCol,
            $valuesCol,
            optional($item->color)->name ?? '',
            $statusLabel,
        ];
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

    private function saveSpreadsheetToPath(Spreadsheet $spreadsheet, string $filePath): void
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

    // ─── Orden de hojas ──────────────────────────────────────

    private function moveInstructionsToFirst(Spreadsheet $spreadsheet): void
    {
        $instrSheet = $spreadsheet->getSheetByName('Instrucciones');
        if ($instrSheet === null) {
            return;
        }
        $spreadsheet->removeSheetByIndex($spreadsheet->getIndex($instrSheet));
        $spreadsheet->addSheet($instrSheet, 0);
        $spreadsheet->setActiveSheetIndex(0);
    }

    // ─── Estilo: CAMPOS y VALORES ADICIONALES ────────────────

    private function applyAdditionalFieldsStyle(Worksheet $sheet, array $headers): void
    {
        $colLetters = range('A', 'Z');
        $camposIdx  = array_search('CAMPOS ADICIONALES', $headers);
        $valoresIdx = array_search('VALORES ADICIONALES', $headers);

        foreach ([$camposIdx, $valoresIdx] as $idx) {
            if ($idx === false || !isset($colLetters[$idx])) {
                continue;
            }
            $col = $colLetters[$idx];
            $sheet->getStyle("{$col}1")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('BDD7EE'); // azul claro
        }
    }

    private function applyAdditionalFieldsComments(Worksheet $sheet, array $headers): void
    {
        $colLetters = range('A', 'Z');

        $camposIdx = array_search('CAMPOS ADICIONALES', $headers);
        if ($camposIdx !== false && isset($colLetters[$camposIdx])) {
            $col  = $colLetters[$camposIdx] . '1';
            $note = $sheet->getComment($col);
            $note->getText()->createTextRun(
                "Escribe los nombres de los campos separados por punto y coma (;).\n" .
                "Ejemplo: BASE TELA;COLOR;TALLA\n\n" .
                "Debe tener el mismo número de valores que la columna VALORES ADICIONALES."
            );
            $note->setWidth('300pt');
            $note->setHeight('80pt');
        }

        $valoresIdx = array_search('VALORES ADICIONALES', $headers);
        if ($valoresIdx !== false && isset($colLetters[$valoresIdx])) {
            $col  = $colLetters[$valoresIdx] . '1';
            $note = $sheet->getComment($col);
            $note->getText()->createTextRun(
                "Escribe los valores correspondientes a CAMPOS ADICIONALES, separados por punto y coma (;).\n" .
                "Ejemplo: RIB;Mocca;S\n\n" .
                "El primer valor corresponde al primer campo, el segundo al segundo, y así."
            );
            $note->setWidth('300pt');
            $note->setHeight('120pt');
        }
    }

    private function buildInstructionsSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Instrucciones');

        // ── Título principal ──────────────────────────────────
        $sheet->setCellValue('A1', 'GUÍA PARA DILIGENCIAR EL ARCHIVO DE ÍTEMS');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F4E79');
        $sheet->getStyle('A1')->getFont()->getColor()->setRGB('FFFFFF');
        $sheet->mergeCells('A1:E1');
        $sheet->getRowDimension(1)->setRowHeight(25);

        // ── Sección 1: Reglas generales ───────────────────────
        $sheet->setCellValue('A3', 'REGLAS GENERALES');
        $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2E75B6');
        $sheet->getStyle('A3')->getFont()->getColor()->setRGB('FFFFFF');
        $sheet->mergeCells('A3:E3');

        $generalRules = [
            '1. Escriba todos los datos en LETRA MAYUSCULA.',
            '2. No modifique los encabezados (fila 1) del archivo.',
            '3. Las columnas con lista desplegable (▼) solo aceptan los valores de esa lista.',
            '4. Las columnas marcadas como OBLIGATORIO no pueden quedar vacías.',
            '5. La columna NOMBRE se genera automáticamente — no es necesario llenarla.',
            '6. Si exportó ítems existentes, NO modifique la columna N° ITEM.',
            '7. Para agregar campos adicionales use punto y coma (;) como separador.',
        ];

        $row = 4;
        foreach ($generalRules as $rule) {
            $sheet->setCellValue("A{$row}", $rule);
            $sheet->mergeCells("A{$row}:E{$row}");
            $row++;
        }

        // ── Sección 2: Detalle por columna ────────────────────
        $row += 1;
        $sheet->setCellValue('A' . $row, 'DETALLE POR COLUMNA');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2E75B6');
        $sheet->getStyle('A' . $row)->getFont()->getColor()->setRGB('FFFFFF');
        $sheet->mergeCells("A{$row}:E{$row}");
        $row++;

        // Encabezados de tabla
        $sheet->setCellValue("A{$row}", 'COLUMNA');
        $sheet->setCellValue("B{$row}", 'OBLIGATORIO');
        $sheet->setCellValue("C{$row}", 'VALORES VÁLIDOS');
        $sheet->setCellValue("D{$row}", 'EJEMPLO');
        $sheet->setCellValue("E{$row}", 'OBSERVACIÓN');
        $sheet->getStyle("A{$row}:E{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:E{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('BDD7EE');
        $headerRow = $row;
        $row++;

        // Datos por columna
        $columns = [
            ['TIPO DE ITEM',          'SÍ',  'Producto / Servicio',                          'Producto',          'Si se deja vacío se asigna Producto por defecto.'],
            ['NOMBRE',                'NO',  'Se genera automáticamente',                    '—',                 'El sistema lo construye con Subgrupo + campos adicionales + color.'],
            ['GRUPO',                 'SÍ',  'Debe existir en el sistema (ver lista ▼)',      'Insumos',           'Use exactamente el mismo nombre que aparece en la lista desplegable.'],
            ['SUBGRUPO',              'SÍ',  'Debe existir en el sistema (ver lista ▼)',      'Tela',              'Use exactamente el mismo nombre que aparece en la lista desplegable.'],
            ['IMPUESTO (%)',          'NO',  'Porcentaje existente en el sistema (ver lista ▼)', '19',             'Si se deja vacío no se aplica impuesto.'],
            ['BODEGA',                'NO',  'Debe existir en el sistema (ver lista ▼)',      'Almacén 008',       'Si se deja vacío el ítem no queda asignado a ninguna bodega.'],
            ['PRECIO',                'NO',  'Número decimal (use punto como separador)',     '15000',             'Precio del ítem en la bodega indicada.'],
            ['CANTIDAD',              'NO',  'Número entero o decimal',                       '100',               'Stock inicial del ítem en la bodega.'],
            ['CANTIDAD MIN',          'NO',  'Número entero o decimal',                       '10',                'Cantidad mínima permitida en bodega.'],
            ['CANTIDAD MAX',          'NO',  'Número entero o decimal',                       '500',               'Cantidad máxima permitida en bodega.'],
            ['UNIDAD DE MEDIDA',      'NO',  'Ver lista desplegable (▼)',                     'Kilogramo',         'Unidad con la que se mide el ítem.'],
            ['CODIGO DE BARRAS',      'NO',  'Texto alfanumérico único',                      'ART-001',           'No puede repetirse entre ítems.'],
            ['REFERENCIA',            'NO',  'Texto alfanumérico',                            'REF-2024-001',      'Código interno de referencia del ítem.'],
            ['DESCRIPCION',           'NO',  'Texto libre en MAYÚSCULAS',                     'TELA BASE RIB',     'Descripción del ítem.'],
            ['MOVIMIENTO NEGATIVO',   'NO',  'SI / NO',                                       'NO',                'Indica si el ítem puede tener stock negativo.'],
            ['CAMPOS ADICIONALES',    'NO',  'Nombres separados por punto y coma (;)',         'BASE TELA;COLOR',   'Debe tener el mismo número de valores que VALORES ADICIONALES.'],
            ['VALORES ADICIONALES',   'NO',  'Valores separados por punto y coma (;)',         'RIB;Mocca',         'El orden debe coincidir exactamente con CAMPOS ADICIONALES.'],
            ['COLOR',                 'NO',  'Debe existir en el sistema',                    'Negro',             'Color principal del ítem.'],
            ['ESTADO',                'NO',  'Activo / Inactivo',                             'Activo',            'Si se deja vacío se asigna Activo por defecto.'],
        ];

        foreach ($columns as $i => $col) {
            $bgColor = $i % 2 === 0 ? 'F2F2F2' : 'FFFFFF';
            $sheet->setCellValue("A{$row}", $col[0]);
            $sheet->setCellValue("B{$row}", $col[1]);
            $sheet->setCellValue("C{$row}", $col[2]);
            $sheet->setCellValue("D{$row}", $col[3]);
            $sheet->setCellValue("E{$row}", $col[4]);
            $sheet->getStyle("A{$row}:E{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($bgColor);
            $row++;
        }

        // ── Sección 3: Ejemplo CAMPOS/VALORES ADICIONALES ─────
        $row += 1;
        $sheet->setCellValue('A' . $row, 'EJEMPLOS: CAMPOS ADICIONALES y VALORES ADICIONALES');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2E75B6');
        $sheet->getStyle('A' . $row)->getFont()->getColor()->setRGB('FFFFFF');
        $sheet->mergeCells("A{$row}:E{$row}");
        $row++;

        $sheet->setCellValue("A{$row}", 'CAMPOS ADICIONALES');
        $sheet->setCellValue("B{$row}", 'VALORES ADICIONALES');
        $sheet->setCellValue("C{$row}", 'Resultado en el sistema');
        $sheet->getStyle("A{$row}:C{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:C{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('BDD7EE');
        $row++;

        $examples = [
            ['BASE TELA;COLOR',       'RIB;Mocca',    'BASE TELA = RIB  |  COLOR = Mocca'],
            ['TALLA;MEDIDA',          'S;7X2.5',      'TALLA = S  |  MEDIDA = 7X2.5'],
            ['BASE TELA;COLOR;TALLA', 'RIB;Negro;M',  'BASE TELA = RIB  |  COLOR = Negro  |  TALLA = M'],
            ['MARCA',                 'Nike',         'MARCA = Nike'],
        ];

        foreach ($examples as $i => $example) {
            $bgColor = $i % 2 === 0 ? 'F2F2F2' : 'FFFFFF';
            $sheet->setCellValue("A{$row}", $example[0]);
            $sheet->setCellValue("B{$row}", $example[1]);
            $sheet->setCellValue("C{$row}", $example[2]);
            $sheet->getStyle("A{$row}:C{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($bgColor);
            $row++;
        }

        // ── Anchos de columna ─────────────────────────────────
        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(45);
        $sheet->getColumnDimension('D')->setWidth(25);
        $sheet->getColumnDimension('E')->setWidth(55);
    }
}
