<?php

namespace App\Services\Production;

use App\Models\Production\ProductSheet;
use App\Services\Settings\SettingService;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Genera la plantilla Excel de sábanas de producto: trae las sábanas existentes
 * (para editarlas y actualizarlas) más filas en blanco (para crear nuevas), con
 * listas desplegables por catálogo y una hoja de instrucciones.
 *
 * Todos los catálogos se leen en vivo (SettingService/FabricBaseService/
 * CollectionService) para que la plantilla nunca quede desactualizada.
 */
class ProductSheetExcelService
{
    private const BLANK_ROWS = 30;

    public function __construct(
        private SettingService $settingService,
        private FabricBaseService $fabricBaseService,
        private YarnBaseService $yarnBaseService,
        private CollectionService $collectionService,
    ) {
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        $catalogs = $this->loadCatalogs();
        $columns = $this->buildColumns($catalogs);

        $spreadsheet = new Spreadsheet();
        $mainSheet = $spreadsheet->getActiveSheet();
        $mainSheet->setTitle('Sábanas');

        $existingCount = $this->buildMainSheet($mainSheet, $columns, $catalogs);
        $sheetData = $this->buildReferenceSheets($spreadsheet, $catalogs);
        $maxRow = 1 + $existingCount + self::BLANK_ROWS;
        $this->applyValidations($mainSheet, $columns, $sheetData, $maxRow);
        $this->buildInstructionsSheet($spreadsheet);
        $this->moveInstructionsToFirst($spreadsheet);

        $fileName = 'plantilla_sabana_productos.xlsx';
        $tempFile = tempnam(sys_get_temp_dir(), 'sabanas_') . '.xlsx';
        (new Xlsx($spreadsheet))->save($tempFile);

        return response()->download($tempFile, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ])->deleteFileAfterSend(true);
    }

    // ─── Catálogos ────────────────────────────────────────────

    private function loadCatalogs(): array
    {
        $mapOptions = fn ($collection) => collect($collection)
            ->map(fn ($item) => ['id' => $item['id'], 'name' => $item['name']])
            ->values()
            ->all();

        return [
            'sections' => $mapOptions($this->settingService->getCatalogOptions('sections')),
            'groups' => $mapOptions($this->settingService->getCatalogOptions('groups')),
            'brands' => $mapOptions($this->settingService->getCatalogOptions('brands')),
            'worlds' => $mapOptions($this->settingService->getCatalogOptions('worlds')),
            'yarn_bases' => $mapOptions($this->yarnBaseService->options()),
            'operational_lists' => $mapOptions($this->settingService->getCatalogOptions('operational_lists')),
            'fabric_bases' => $mapOptions($this->fabricBaseService->options()),
            'collections' => $this->collectionService->allActive()
                ->map(fn (\App\Models\Production\Collection $c) => ['id' => $c->id, 'name' => $c->name])
                ->values()
                ->all(),
            'sizes' => $this->settingService->getCatalogOptions('sizes')->pluck('name')->values()->all(),
            'processes' => config('product-sheet.batch_control_processes', []),
        ];
    }

    private function buildColumns(array $catalogs): array
    {
        $columns = ProductSheetTemplateSchema::FIXED_COLUMNS;

        foreach ($catalogs['sizes'] as $size) {
            $columns[] = [
                'header' => ProductSheetTemplateSchema::sizeHeader($size),
                'key' => ProductSheetTemplateSchema::sizeDataKey($size),
                'required' => false,
            ];
        }

        foreach ($catalogs['processes'] as $process) {
            $columns[] = [
                'header' => ProductSheetTemplateSchema::processHeader($process),
                'key' => ProductSheetTemplateSchema::processDataKey($process),
                'required' => false,
            ];
        }

        return $columns;
    }

    // ─── Hoja principal ───────────────────────────────────────

    /**
     * @return int Cantidad de filas de sábanas existentes escritas.
     */
    private function buildMainSheet(Worksheet $sheet, array $columns, array $catalogs): int
    {
        $idToName = fn (array $catalog) => collect($catalog)->pluck('name', 'id')->all();

        $sections = $idToName($catalogs['sections']);
        $groups = $idToName($catalogs['groups']);
        $brands = $idToName($catalogs['brands']);
        $worlds = $idToName($catalogs['worlds']);
        $collections = $idToName($catalogs['collections']);
        $fabricBases = $idToName($catalogs['fabric_bases']);
        $yarnBases = $idToName($catalogs['yarn_bases']);
        $operationalLists = $idToName($catalogs['operational_lists']);

        foreach ($columns as $idx => $col) {
            $colLetter = Coordinate::stringFromColumnIndex($idx + 1);
            $sheet->setCellValue("{$colLetter}1", $col['header']);
            $sheet->getColumnDimension($colLetter)->setWidth($col['required'] ? 18 : 14);

            $bgColor = $col['required'] ? 'C0392B' : '2C3E50';
            $sheet->getStyle("{$colLetter}1")->applyFromArray([
                'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            ]);
        }
        $sheet->getRowDimension(1)->setRowHeight(32);

        $existingSheets = ProductSheet::query()->orderByDesc('id')->get();

        $rowNum = 2;
        foreach ($existingSheets as $productSheet) {
            foreach ($columns as $idx => $col) {
                $colLetter = Coordinate::stringFromColumnIndex($idx + 1);
                $value = $this->resolveExportValue(
                    $col['key'],
                    $productSheet,
                    $sections,
                    $groups,
                    $brands,
                    $worlds,
                    $collections,
                    $fabricBases,
                    $yarnBases,
                    $operationalLists,
                );
                $sheet->setCellValue("{$colLetter}{$rowNum}", $value);
            }
            $rowNum++;
        }

        $sheet->freezePane('A2');

        return $existingSheets->count();
    }

    private function resolveExportValue(
        string $key,
        ProductSheet $productSheet,
        array $sections,
        array $groups,
        array $brands,
        array $worlds,
        array $collections,
        array $fabricBases,
        array $yarnBases,
        array $operationalLists,
    ): string {
        if (str_starts_with($key, 'talla:')) {
            return $this->hasActiveFlag($productSheet->sizes, substr($key, 6)) ? 'SI' : '';
        }

        if (str_starts_with($key, 'proceso:')) {
            return $this->hasActiveFlag($productSheet->processes, substr($key, 8)) ? 'SI' : '';
        }

        return match ($key) {
            'id' => (string) $productSheet->id,
            'tipo' => (string) $productSheet->type,
            'tipo_prenda' => (string) ($productSheet->garment_type ?? ''),
            'seccion' => $sections[$productSheet->section_id] ?? '',
            'grupo' => $groups[$productSheet->group_id] ?? '',
            'marca' => $brands[$productSheet->brand_id] ?? '',
            'mundo' => $worlds[$productSheet->world_id] ?? '',
            'coleccion' => $productSheet->collection_id ? ($collections[$productSheet->collection_id] ?? '') : '',
            'es_tejido' => $productSheet->woven_product ? 'SI' : 'NO',
            'base_tejido' => $productSheet->fabric_base_id ? ($fabricBases[$productSheet->fabric_base_id] ?? '') : '',
            'base_hilaza' => $productSheet->yarn_base_id ? ($yarnBases[$productSheet->yarn_base_id] ?? '') : '',
            'lista_operacional' => $productSheet->operational_list_id ? ($operationalLists[$productSheet->operational_list_id] ?? '') : '',
            'ref_muestra' => (string) ($productSheet->sample_reference ?? ''),
            'ref_producto' => (string) ($productSheet->product_reference ?? ''),
            'ref_producto_2' => (string) ($productSheet->product_reference_2 ?? ''),
            'precio_facturacion' => $productSheet->billing_price !== null ? (string) $productSheet->billing_price : '',
            'costo' => $productSheet->costo !== null ? (string) $productSheet->costo : '',
            'precio_venta' => $productSheet->selling_price !== null ? (string) $productSheet->selling_price : '',
            'descripcion' => (string) ($productSheet->description ?? ''),
            'observaciones' => (string) ($productSheet->observaciones ?? ''),
            'habilitado' => $productSheet->is_enabled ? 'SI' : 'NO',
            default => '',
        };
    }

    private function hasActiveFlag(mixed $flags, string $normalizedName): bool
    {
        if (!is_array($flags)) {
            return false;
        }

        foreach ($flags as $name => $active) {
            if ($active && ProductSheetTemplateSchema::normalizeCatalogName((string) $name) === $normalizedName) {
                return true;
            }
        }

        return false;
    }

    // ─── Hojas de referencia (catálogos + listas fijas) ──────

    private function buildReferenceSheets(Spreadsheet $spreadsheet, array $catalogs): array
    {
        $sheetData = [
            'sections' => $this->buildNameSheet($spreadsheet, 'Secciones', $catalogs['sections']),
            'groups' => $this->buildNameSheet($spreadsheet, 'Grupos', $catalogs['groups']),
            'brands' => $this->buildNameSheet($spreadsheet, 'Marcas', $catalogs['brands']),
            'worlds' => $this->buildNameSheet($spreadsheet, 'Mundos', $catalogs['worlds']),
            'collections' => $this->buildNameSheet($spreadsheet, 'Colecciones', $catalogs['collections']),
            'fabric_bases' => $this->buildNameSheet($spreadsheet, 'Bases Tejido', $catalogs['fabric_bases']),
            'yarn_bases' => $this->buildNameSheet($spreadsheet, 'Bases Hilaza', $catalogs['yarn_bases']),
            'operational_lists' => $this->buildNameSheet($spreadsheet, 'Listas Operacionales', $catalogs['operational_lists']),
        ];

        $listasSheet = $spreadsheet->createSheet();
        $listasSheet->setTitle('Listas');
        $listasSheet->fromArray(['TIPO', 'TIPO_PRENDA', 'SI_NO'], null, 'A1');
        $listasSheet->setCellValue('A2', 'producto');
        $listasSheet->setCellValue('A3', 'muestra');
        $listasSheet->setCellValue('B2', 'superior');
        $listasSheet->setCellValue('B3', 'inferior');
        $listasSheet->setCellValue('C2', 'SI');
        $listasSheet->setCellValue('C3', 'NO');
        $listasSheet->getStyle('A1:C1')->getFont()->setBold(true);

        return $sheetData;
    }

    private function buildNameSheet(Spreadsheet $spreadsheet, string $title, array $catalog): int
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($title);
        $sheet->fromArray(['ID', 'Nombre'], null, 'A1');
        $sheet->getStyle('A1:B1')->getFont()->setBold(true);
        $sheet->getColumnDimension('B')->setWidth(32);

        $row = 2;
        foreach ($catalog as $item) {
            $sheet->setCellValue("A{$row}", $item['id']);
            $sheet->setCellValue("B{$row}", $item['name']);
            $row++;
        }

        return max(2, $row - 1);
    }

    // ─── Validaciones (dropdowns) ─────────────────────────────

    private function applyValidations(Worksheet $sheet, array $columns, array $sheetData, int $maxRow): void
    {
        $siNoRange = "'Listas'!\$C\$2:\$C\$3";

        $dropdownFormulas = [
            'tipo' => "'Listas'!\$A\$2:\$A\$3",
            'tipo_prenda' => "'Listas'!\$B\$2:\$B\$3",
            'seccion' => "'Secciones'!\$B\$2:\$B\${$sheetData['sections']}",
            'grupo' => "'Grupos'!\$B\$2:\$B\${$sheetData['groups']}",
            'marca' => "'Marcas'!\$B\$2:\$B\${$sheetData['brands']}",
            'mundo' => "'Mundos'!\$B\$2:\$B\${$sheetData['worlds']}",
            'coleccion' => "'Colecciones'!\$B\$2:\$B\${$sheetData['collections']}",
            'es_tejido' => $siNoRange,
            'base_tejido' => "'Bases Tejido'!\$B\$2:\$B\${$sheetData['fabric_bases']}",
            'base_hilaza' => "'Bases Hilaza'!\$B\$2:\$B\${$sheetData['yarn_bases']}",
            'lista_operacional' => "'Listas Operacionales'!\$B\$2:\$B\${$sheetData['operational_lists']}",
            'habilitado' => $siNoRange,
        ];

        foreach ($columns as $idx => $col) {
            $key = $col['key'];
            $formula = $dropdownFormulas[$key]
                ?? (str_starts_with($key, 'talla:') || str_starts_with($key, 'proceso:') ? $siNoRange : null);

            if ($formula === null) {
                continue;
            }

            $colLetter = Coordinate::stringFromColumnIndex($idx + 1);
            $this->applyListValidation($sheet, $colLetter, $formula, $maxRow);
        }
    }

    private function applyListValidation(Worksheet $sheet, string $colLetter, string $formula, int $maxRow): void
    {
        for ($row = 2; $row <= $maxRow; $row++) {
            $validation = $sheet->getCell("{$colLetter}{$row}")->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST)
                ->setErrorStyle(DataValidation::STYLE_STOP)
                ->setAllowBlank(true)
                ->setShowInputMessage(true)
                ->setShowErrorMessage(true)
                ->setShowDropDown(true)
                ->setFormula1($formula);
        }
    }

    // ─── Instrucciones ────────────────────────────────────────

    private function buildInstructionsSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Instrucciones');

        $sheet->setCellValue('A1', 'GUÍA PARA DILIGENCIAR LA PLANTILLA DE SÁBANAS DE PRODUCTO');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F4E79');
        $sheet->mergeCells('A1:E1');
        $sheet->getRowDimension(1)->setRowHeight(25);

        $sheet->setCellValue('A3', 'REGLAS GENERALES');
        $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2E75B6');
        $sheet->mergeCells('A3:E3');

        $generalRules = [
            '1. La hoja "Sábanas" ya trae las sábanas existentes con su ID lleno. NO borres ni edites la columna ID de esas filas.',
            '2. ID vacío = se crea una sábana nueva. ID con un número existente = se actualiza esa sábana con los datos de la fila.',
            '3. Las columnas con lista desplegable solo aceptan los valores de esa lista (ver hojas de referencia: Secciones, Grupos, Marcas, etc.).',
            '4. Las columnas marcadas con * son obligatorias.',
            '5. Si ES TEJIDO = NO, debes indicar BASE TEJIDO. Si ES TEJIDO = SI, debes indicar BASE HILAZA.',
            '6. Si activas el proceso "Muestras" (columna PROCESO MUESTRAS = SI), LISTA OPERACIONAL pasa a ser obligatoria.',
            '7. En las columnas de TALLA y PROCESO escribe SI para activarlas, o déjalas vacías.',
            '8. La referencia de muestra de una sábana existente de tipo "muestra" no se puede modificar; si la cambias, se ignora ese cambio y se reporta como advertencia.',
        ];

        $row = 4;
        foreach ($generalRules as $rule) {
            $sheet->setCellValue("A{$row}", $rule);
            $sheet->mergeCells("A{$row}:E{$row}");
            $sheet->getRowDimension($row)->setRowHeight(18);
            $row++;
        }

        $row += 1;
        $sheet->setCellValue("A{$row}", 'DETALLE POR COLUMNA');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2E75B6');
        $sheet->mergeCells("A{$row}:E{$row}");
        $row++;

        $sheet->fromArray(['COLUMNA', 'OBLIGATORIO', 'VALORES VÁLIDOS', 'EJEMPLO'], null, "A{$row}");
        $sheet->getStyle("A{$row}:D{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:D{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('BDD7EE');
        $row++;

        $columnsHelp = [
            ['ID', 'NO', 'Número (solo para actualizar)', 'Vacío = nueva sábana'],
            ['TIPO', 'SÍ', 'producto / muestra', 'producto'],
            ['TIPO PRENDA', 'NO', 'superior / inferior', 'superior'],
            ['SECCIÓN', 'SÍ', 'Ver hoja "Secciones"', 'Camiseta'],
            ['GRUPO', 'SÍ', 'Ver hoja "Grupos"', 'Línea'],
            ['MARCA', 'SÍ', 'Ver hoja "Marcas"', 'Marca A'],
            ['MUNDO', 'SÍ', 'Ver hoja "Mundos"', 'Dama'],
            ['COLECCIÓN', 'NO', 'Ver hoja "Colecciones"', 'Verano 2026'],
            ['ES TEJIDO', 'SÍ', 'SI / NO', 'NO'],
            ['BASE TEJIDO', 'Condicional (si ES TEJIDO=NO)', 'Ver hoja "Bases Tejido"', 'Jersey'],
            ['BASE HILAZA', 'Condicional (si ES TEJIDO=SI)', 'Ver hoja "Bases Hilaza"', 'Algodón'],
            ['LISTA OPERACIONAL', 'Condicional (si proceso Muestras=SI)', 'Ver hoja "Listas Operacionales"', 'Lista Básica'],
            ['REF. MUESTRA', 'Obligatoria si TIPO=muestra', 'Texto libre', 'M001'],
            ['REF. PRODUCTO', 'Obligatoria si TIPO=producto', 'Texto libre', 'P001'],
            ['REF. PRODUCTO 2', 'NO', 'Texto libre', 'EXT-P001'],
            ['PRECIO FACTURACIÓN', 'NO', 'Número', '25000'],
            ['COSTO', 'NO', 'Número', '20000'],
            ['PRECIO VENTA', 'SÍ', 'Número', '35000'],
            ['DESCRIPCIÓN', 'SÍ', 'Texto libre', 'Camiseta básica cuello redondo'],
            ['OBSERVACIONES', 'NO', 'Texto libre', 'Revisar costuras'],
            ['HABILITADO', 'NO', 'SI / NO (por defecto SI)', 'SI'],
            ['TALLA <talla>', 'NO', 'SI / vacío', 'SI'],
            ['PROCESO <proceso>', 'NO', 'SI / vacío', 'SI'],
        ];

        foreach ($columnsHelp as $i => $line) {
            $bg = $i % 2 === 0 ? 'F2F2F2' : 'FFFFFF';
            $sheet->fromArray($line, null, "A{$row}");
            $sheet->getStyle("A{$row}:D{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($bg);
            $row++;
        }

        $sheet->getColumnDimension('A')->setWidth(22);
        $sheet->getColumnDimension('B')->setWidth(26);
        $sheet->getColumnDimension('C')->setWidth(34);
        $sheet->getColumnDimension('D')->setWidth(30);
    }

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
}
