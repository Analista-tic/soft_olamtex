<?php

namespace App\Services\MasterData;

use App\Models\Finance\Tax;
use App\Models\Inventory\Warehouse;
use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Item;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
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
    // ─── Encabezados oficiales de la plantilla ───────────────

    public function templateHeaders(): array
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
        $headers = $this->templateHeaders();
        $catalogData = $this->loadCatalogData();

        // Hoja principal con encabezados + fila de ejemplo
        $mainSheet = $spreadsheet->getActiveSheet();
        $mainSheet->setTitle('items');

        $exampleGroup = $catalogData['subGroups'][0]['group'] ?? ($catalogData['groups']->first()?->name ?? 'Suministros');
        $exampleSubGroup = $catalogData['subGroups'][0]['name'] ?? 'Papel';

        $exampleRow = [
            'Producto',
            'Ejemplo articulo',
            $exampleGroup,
            $exampleSubGroup,
            '19',
            'Principal',
            '',           // PRECIO — derivado del inventario, dejar vacío en plantilla
            '100',
            '10',
            '200',
            'Kilogramo',
            'ART-001',
            'ART-001',
            'Descripcion de articulo de prueba',
            'NO',
            'BASE TELA',
            'RIB',
            'Rojo',
            'Activo',
        ];

        $mainSheet->fromArray([$headers, $exampleRow], '', 'A1');

        // Estilo del encabezado
        $lastCol = $mainSheet->getHighestColumn();
        $mainSheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
        foreach (range('A', $lastCol) as $col) {
            $mainSheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Construir hojas de referencia y aplicar validaciones
        $sheetData = $this->buildReferenceSheets($spreadsheet, $catalogData);
        $mainSheet = $spreadsheet->setActiveSheetIndex(0);
        $this->applyAllValidations($mainSheet, $headers, $sheetData, 1000);

        $spreadsheet->setActiveSheetIndex(0);

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
        $items = Item::with(['categorySubGroup.categoryGroup', 'tax', 'warehouses', 'warehouseItems', 'color'])->get();

        // Pre-cargar precios derivados por item_id (sin N+1).
        $priceByItem = $this->loadLatestPriceByItem();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('items');

        $headers = $this->templateHeaders();
        $sheet->fromArray($headers, null, 'A1');

        $rowNum = 2;
        foreach ($items as $item) {
            $price = $priceByItem[(int) $item->id] ?? null;
            $sheet->fromArray($this->exportRowForItem($item, $price), null, "A{$rowNum}");
            $rowNum++;
        }

        $catalogData = $this->loadCatalogData();
        $sheetData = $this->buildReferenceSheets($spreadsheet, $catalogData);
        $mainSheet = $spreadsheet->setActiveSheetIndex(0);
        $this->applyAllValidations($mainSheet, $headers, $sheetData, max(1000, $rowNum));

        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

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
    public function exportRowForItem($item, ?float $resolvedPrice = null): array
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
        $warehouse = $item->warehouses->first();
        $warehouseItems = $item->warehouseItems ?? collect();
        $warehouseName = optional($warehouse)->name ?? '';
        $warehousePivot = $warehouse ? $warehouse->pivot : null;
        $quantityValue = $warehouseItems->count() === 1
            ? ($warehouseItems->first()?->quantity ?? 0)
            : $warehouseItems->sum('quantity');
        $stockMinValue = $warehousePivot && isset($warehousePivot->stock_min) ? $warehousePivot->stock_min : '';
        $stockMaxValue = $warehousePivot && isset($warehousePivot->stock_max) ? $warehousePivot->stock_max : '';
        $descriptionValue = trim((string) ($item->description ?? ''));

        if (
            mb_stripos($descriptionValue, 'Migrated from legacy system', 0, 'UTF-8') !== false
            || mb_stripos($descriptionValue, 'legacy', 0, 'UTF-8') !== false
        ) {
            $descriptionValue = '';
        }

        return [
            $typeLabel,
            $item->name,
            $groupName,
            $subGroupName,
            $tax ? number_format((float) $tax->percentage, 2, '.', '') : '',
            $warehouseName,
            $resolvedPrice !== null ? number_format($resolvedPrice, 4, '.', '') : '',
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
}
