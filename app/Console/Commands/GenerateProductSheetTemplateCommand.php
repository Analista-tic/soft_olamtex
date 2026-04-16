<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GenerateProductSheetTemplateCommand extends Command
{
    protected $signature = 'product-sheets:template {--output= : Ruta de salida del archivo}';
    protected $description = 'Genera una plantilla Excel para importación masiva de sábanas de producto';

    // Tallas disponibles en el sistema
    private const SIZES = ['XS','S','M','L','XL','2XL','U','2','3','4','5','6','8','10','12','14','16','28','30','32','34','36','38'];

    // Procesos disponibles
    private const PROCESSES = ['Muestras','Confección','Terminación','Bodega','Desarrollo de muestras'];

    public function handle(): int
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setTitle('Plantilla Sábana de Productos')
            ->setDescription('Plantilla para importación masiva de sábanas de producto - soft_olamtex');

        // Hoja 1: Datos
        $dataSheet = $spreadsheet->getActiveSheet();
        $dataSheet->setTitle('Productos');

        // Hoja 2: Referencia
        $refSheet = new Worksheet($spreadsheet, 'Referencia');
        $spreadsheet->addSheet($refSheet);

        $this->buildDataSheet($dataSheet);
        $this->buildReferenceSheet($refSheet);

        // Ordenar hojas: Productos primero
        $spreadsheet->setActiveSheetIndex(0);

        $outputPath = $this->option('output') ?? storage_path('app/plantilla_sabana_productos.xlsx');
        $writer = new Xlsx($spreadsheet);
        $writer->save($outputPath);

        $this->info("Plantilla generada: {$outputPath}");
        return self::SUCCESS;
    }

    private function buildDataSheet(Worksheet $sheet): void
    {
        // --- Definir columnas ---
        $columns = $this->getColumns();

        // Fila 1: Título principal
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($columns));
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', 'PLANTILLA IMPORTACIÓN MASIVA - SÁBANAS DE PRODUCTO');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        // Fila 2: Encabezados
        foreach ($columns as $colIdx => $col) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1);
            $sheet->setCellValue("{$colLetter}2", $col['header']);

            $bgColor = $col['required'] ? 'C0392B' : ($col['group'] === 'tallas' ? '1A5276' : ($col['group'] === 'procesos' ? '117A65' : '2C3E50'));

            $sheet->getStyle("{$colLetter}2")->applyFromArray([
                'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
            ]);

            $sheet->getColumnDimensionByColumn($colIdx + 1)->setWidth($col['width'] ?? 14);
        }
        $sheet->getRowDimension(2)->setRowHeight(32);

        // Fila 3: Descripción de campos
        foreach ($columns as $colIdx => $col) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1);
            $sheet->setCellValue("{$colLetter}3", $col['hint'] ?? '');
            $sheet->getStyle("{$colLetter}3")->applyFromArray([
                'font' => ['italic' => true, 'size' => 8, 'color' => ['rgb' => '666666']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0F0F0']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
            ]);
        }
        $sheet->getRowDimension(3)->setRowHeight(28);

        // Filas de ejemplo (4-6)
        $examples = $this->getExampleRows();
        foreach ($examples as $rowOffset => $example) {
            $rowNum = 4 + $rowOffset;
            foreach ($columns as $colIdx => $col) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1);
                $value = $example[$col['field']] ?? '';
                $sheet->setCellValue("{$colLetter}{$rowNum}", $value);
            }
            $bgRow = $rowOffset % 2 === 0 ? 'FDFEFE' : 'EBF5FB';
            $sheet->getStyle("A{$rowNum}:{$lastCol}{$rowNum}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgRow]],
                'font' => ['size' => 9, 'color' => ['rgb' => '555555'], 'italic' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
        }

        // Filas vacías para datos reales (7-106 → 100 filas)
        for ($row = count($examples) + 4; $row <= count($examples) + 103; $row++) {
            $bgRow = ($row % 2 === 0) ? 'FFFFFF' : 'F9F9F9';
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgRow]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'DDDDDD']]],
                'font' => ['size' => 9],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
        }

        // Bordes para encabezados
        $sheet->getStyle("A2:{$lastCol}3")->applyFromArray([
            'borders' => ['outline' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '333333']]],
        ]);

        // Congelar filas 1-3
        $sheet->freezePane('A4');

        // Nota al pie
        $noteRow = count($examples) + 104;
        $sheet->mergeCells("A{$noteRow}:{$lastCol}{$noteRow}");
        $sheet->setCellValue("A{$noteRow}",
            '* Columnas en ROJO son obligatorias. Tallas: escribir SI o dejar vacío. Procesos: escribir SI o dejar vacío. ' .
            'Para campos de selección use exactamente los nombres de la hoja "Referencia".'
        );
        $sheet->getStyle("A{$noteRow}")->applyFromArray([
            'font' => ['italic' => true, 'size' => 8, 'color' => ['rgb' => '7F0000']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF3CD']],
        ]);
    }

    private function buildReferenceSheet(Worksheet $sheet): void
    {
        $headers = ['TIPO', 'TIPO_PRENDA', 'TALLAS DISPONIBLES', 'PROCESOS DISPONIBLES'];
        $values = [
            ['muestra', 'superior', implode(', ', self::SIZES), implode(', ', self::PROCESSES)],
            ['producto', 'inferior', '', ''],
        ];

        $notes = [
            'Usar exactamente uno de estos valores en la columna "tipo"',
            'Usar exactamente uno de estos valores en la columna "tipo_prenda"',
            'Poner SI en la columna de cada talla que aplique',
            'Poner SI en la columna de cada proceso que aplique',
        ];

        foreach ($headers as $i => $header) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue("{$col}1", $header);
            $sheet->getStyle("{$col}1")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            $sheet->getColumnDimensionByColumn($i + 1)->setWidth(40);
        }

        foreach ($values as $rowOffset => $row) {
            foreach ($row as $colOffset => $value) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colOffset + 1);
                $sheet->setCellValue("{$col}" . ($rowOffset + 2), $value);
                $sheet->getStyle("{$col}" . ($rowOffset + 2))->applyFromArray([
                    'alignment' => ['wrapText' => true],
                    'font' => ['size' => 9],
                ]);
            }
        }

        // Notas
        $sheet->getRowDimension(5)->setRowHeight(18);
        foreach ($notes as $i => $note) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue("{$col}5", $note);
            $sheet->getStyle("{$col}5")->applyFromArray([
                'font' => ['italic' => true, 'size' => 8, 'color' => ['rgb' => '555555']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF9C4']],
            ]);
        }

        $sheet->getRowDimension(2)->setRowHeight(20);
        $sheet->getRowDimension(3)->setRowHeight(20);
        $sheet->getRowDimension(4)->setRowHeight(50);

        // Título
        $sheet->mergeCells('A6:D7');
        $sheet->setCellValue('A6',
            'NOTA: Para los campos "sección", "grupo", "marca", "mundo", "base_tejido", "base_hilaza", "lista_operacional" y "colección", ' .
            'use los nombres exactos configurados en el sistema (ver Settings > Parámetros).'
        );
        $sheet->getStyle('A6')->applyFromArray([
            'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '7F0000']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFEBEE']],
            'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
    }

    private function getColumns(): array
    {
        $cols = [
            // Identificación
            ['field' => 'tipo',              'header' => 'TIPO *',             'hint' => 'muestra / producto',                'required' => true,  'group' => 'id',      'width' => 12],
            ['field' => 'ref_muestra',        'header' => 'REF. MUESTRA',       'hint' => 'Ej: M001',                          'required' => false, 'group' => 'id',      'width' => 14],
            ['field' => 'ref_producto',       'header' => 'REF. PRODUCTO',      'hint' => 'Ej: P001',                          'required' => false, 'group' => 'id',      'width' => 14],
            ['field' => 'ref_producto_2',     'header' => 'REF. PROD 2',        'hint' => 'Referencia alternativa',            'required' => false, 'group' => 'id',      'width' => 14],
            ['field' => 'ref_exportacion',    'header' => 'REF. EXPORTACIÓN',   'hint' => 'Para exportaciones',               'required' => false, 'group' => 'id',      'width' => 16],
            // Clasificación
            ['field' => 'seccion',            'header' => 'SECCIÓN *',          'hint' => 'Nombre exacto del sistema',        'required' => true,  'group' => 'class',   'width' => 16],
            ['field' => 'grupo',              'header' => 'GRUPO',              'hint' => 'Nombre exacto del sistema',        'required' => false, 'group' => 'class',   'width' => 16],
            ['field' => 'marca',              'header' => 'MARCA',              'hint' => 'Nombre exacto del sistema',        'required' => false, 'group' => 'class',   'width' => 14],
            ['field' => 'mundo',              'header' => 'MUNDO',              'hint' => 'Nombre exacto del sistema',        'required' => false, 'group' => 'class',   'width' => 14],
            ['field' => 'coleccion',          'header' => 'COLECCIÓN',          'hint' => 'Nombre de la colección',           'required' => false, 'group' => 'class',   'width' => 16],
            ['field' => 'tipo_prenda',        'header' => 'TIPO PRENDA',        'hint' => 'superior / inferior',              'required' => false, 'group' => 'class',   'width' => 13],
            // Material
            ['field' => 'base_tejido',        'header' => 'BASE TEJIDO',        'hint' => 'Nombre exacto del sistema',        'required' => false, 'group' => 'mat',     'width' => 16],
            ['field' => 'base_hilaza',        'header' => 'BASE HILAZA',        'hint' => 'Nombre exacto del sistema',        'required' => false, 'group' => 'mat',     'width' => 16],
            ['field' => 'es_tejido',          'header' => 'ES TEJIDO',          'hint' => 'SI / NO',                          'required' => false, 'group' => 'mat',     'width' => 10],
            // Lista operacional
            ['field' => 'lista_operacional',  'header' => 'LISTA OPERACIONAL',  'hint' => 'Nombre exacto del sistema',        'required' => false, 'group' => 'mat',     'width' => 18],
            // Precios
            ['field' => 'precio_facturacion', 'header' => 'PRECIO FACT.',       'hint' => 'Número sin puntos ni $',           'required' => false, 'group' => 'price',   'width' => 14],
            ['field' => 'precio_venta',       'header' => 'PRECIO VENTA',       'hint' => 'Número sin puntos ni $',           'required' => false, 'group' => 'price',   'width' => 14],
            ['field' => 'costo',              'header' => 'COSTO',              'hint' => 'Número sin puntos ni $',           'required' => false, 'group' => 'price',   'width' => 14],
            // Descripción
            ['field' => 'descripcion',        'header' => 'DESCRIPCIÓN',        'hint' => 'Texto libre',                      'required' => false, 'group' => 'misc',    'width' => 24],
            ['field' => 'observaciones',      'header' => 'OBSERVACIONES',      'hint' => 'Texto libre (interno)',             'required' => false, 'group' => 'misc',    'width' => 30],
        ];

        // Tallas
        foreach (self::SIZES as $size) {
            $cols[] = [
                'field'    => "talla_{$size}",
                'header'   => "TALLA\n{$size}",
                'hint'     => 'SI / vacío',
                'required' => false,
                'group'    => 'tallas',
                'width'    => 8,
            ];
        }

        // Procesos
        foreach (self::PROCESSES as $process) {
            $shortName = mb_strtoupper(mb_substr($process, 0, 10));
            $cols[] = [
                'field'    => "proceso_{$process}",
                'header'   => "PROCESO\n{$shortName}",
                'hint'     => 'SI / vacío',
                'required' => false,
                'group'    => 'procesos',
                'width'    => 10,
            ];
        }

        return $cols;
    }

    private function getExampleRows(): array
    {
        return [
            [
                'tipo'              => 'muestra',
                'ref_muestra'       => 'M001',
                'ref_producto'      => '',
                'ref_producto_2'    => '',
                'ref_exportacion'   => '',
                'seccion'           => 'Camisetas',
                'grupo'             => 'Línea continua',
                'marca'             => 'Marca A',
                'mundo'             => 'Hombre',
                'coleccion'         => 'Verano 2025',
                'tipo_prenda'       => 'superior',
                'base_tejido'       => 'Jersey',
                'base_hilaza'       => '',
                'es_tejido'         => 'SI',
                'lista_operacional' => 'Lista Básica',
                'precio_facturacion'=> '25000',
                'costo'             => '20000',
                'precio_venta'      => '35000',
                'descripcion'       => 'Camiseta básica cuello redondo',
                'observaciones'     => 'Revisar calidad de costuras antes de despacho',
                'talla_S'           => 'SI',
                'talla_M'           => 'SI',
                'talla_L'           => 'SI',
                'talla_XL'          => 'SI',
                'proceso_Muestras'  => 'SI',
                'proceso_Confección'=> 'SI',
                'proceso_Terminación' => 'SI',
                'proceso_Bodega'    => 'SI',
            ],
            [
                'tipo'              => 'producto',
                'ref_muestra'       => '',
                'ref_producto'      => 'P002',
                'ref_producto_2'    => 'EXT-P002',
                'ref_exportacion'   => 'EXP-002',
                'seccion'           => 'Pantalones',
                'grupo'             => 'Lotes periódicos',
                'marca'             => 'Marca B',
                'mundo'             => 'Mujer',
                'coleccion'         => 'Básicos',
                'tipo_prenda'       => 'inferior',
                'base_tejido'       => '',
                'base_hilaza'       => 'Algodón 30/1',
                'es_tejido'         => 'NO',
                'lista_operacional' => 'Lista Completa',
                'precio_facturacion'=> '45000',
                'costo'             => '38000',
                'precio_venta'      => '65000',
                'descripcion'       => 'Pantalón clásico corte recto',
                'observaciones'     => '',
                'talla_28'          => 'SI',
                'talla_30'          => 'SI',
                'talla_32'          => 'SI',
                'talla_34'          => 'SI',
                'proceso_Muestras'  => 'SI',
                'proceso_Confección'=> 'SI',
                'proceso_Terminación' => 'SI',
                'proceso_Bodega'    => 'SI',
            ],
            [
                'tipo'              => 'muestra',
                'ref_muestra'       => 'M003',
                'ref_producto'      => '',
                'ref_producto_2'    => '',
                'ref_exportacion'   => '',
                'seccion'           => 'Ropa Interior',
                'grupo'             => '',
                'marca'             => '',
                'mundo'             => 'Infantil',
                'coleccion'         => '',
                'tipo_prenda'       => 'superior',
                'base_tejido'       => 'Rib',
                'base_hilaza'       => '',
                'es_tejido'         => 'SI',
                'lista_operacional' => '',
                'precio_facturacion'=> '',
                'costo'             => '',
                'precio_venta'      => '',
                'descripcion'       => '',
                'observaciones'     => 'Muestra en desarrollo, pendiente aprobación',
                'talla_2'           => 'SI',
                'talla_4'           => 'SI',
                'talla_6'           => 'SI',
                'talla_8'           => 'SI',
                'proceso_Muestras'  => 'SI',
                'proceso_Desarrollo de muestras' => 'SI',
            ],
        ];
    }
}
