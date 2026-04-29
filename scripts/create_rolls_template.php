<?php
require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$headers = [
    'N° ROLLO', 'UBICACIÓN', 'BASE TELA', 'TELA', 'COLOR',
    'CÓDIGO COLOR', 'ANCHO (m)', 'CANTIDAD (m)', 'SALIDA PARCIAL',
    'DISPONIBILIDAD REAL', 'PROVEEDOR', 'N° PEDIDO', 'FECHA INGRESO', 'REFERENCIA ÍTEM'
];
$sheet->fromArray($headers, null, 'A1');

$writer = new Xlsx($spreadsheet);
$dest = __DIR__ . '/../storage/app/public/plantilla_importacion_rollos.xlsx';
$writer->save($dest);

echo "CREATED: $dest\n";
