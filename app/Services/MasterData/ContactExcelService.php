<?php

namespace App\Services\MasterData;

use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\Contact;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Servicio responsable de la generación y exportación de archivos Excel
 * para el módulo de Contactos (plantilla de importación y exportación de datos).
 *
 * Sigue el mismo patrón que ItemExcelService.
 */
class ContactExcelService
{
    // ─── Encabezados ─────────────────────────────────────────

    // Encabezados para exportación (incluye ID para identificar contactos al reimportar)
    public function templateHeaders(): array
    {
        return array_merge(['ID'], $this->importTemplateHeaders());
    }

    // Encabezados para plantilla vacía (sin ID — lo asigna la BD automáticamente)
    public function importTemplateHeaders(): array
    {
        return [
            'TIPO DE CONTACTO',
            'TIPO DE IDENTIFICACION',
            'NUMERO DE IDENTIFICACION',
            'DV',
            'NOMBRE COMPLETO',
            'TIPO DE PERSONA',
            'GRUPOS',
            'MUNICIPIO',
            'DEPARTAMENTO',
            'DIRECCION',
            'EMAIL',
            'TELEFONO',
            'CONTACTO ADICIONAL NOMBRE',
            'CONTACTO ADICIONAL TELEFONO',
            'CONTACTO ADICIONAL EMAIL',
            'SUCURSAL NOMBRE',
            'SUCURSAL DIRECCION',
            'SUCURSAL TELEFONO',
        ];
    }

    // ─── Descarga de plantilla vacía ─────────────────────────

    public function downloadTemplate(): BinaryFileResponse|\Illuminate\Http\JsonResponse
    {
        $fileName = 'plantilla_contactos.xlsx';
        $filePath = storage_path('app/public/'.$fileName);

        $spreadsheet = new Spreadsheet();
        $headers = $this->importTemplateHeaders();

        $mainSheet = $spreadsheet->getActiveSheet();
        $mainSheet->setTitle('contactos');
        $mainSheet->fromArray([$headers], '', 'A1');

        $lastCol = $mainSheet->getHighestColumn();
        $mainSheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
        foreach (range('A', $lastCol) as $col) {
            $mainSheet->getColumnDimension($col)->setAutoSize(true);
        }

        $this->buildInstructionsSheet($spreadsheet);

        $groups = $this->loadGroups();
        $sheetData = $this->buildReferenceSheets($spreadsheet, $groups);
        $mainSheet = $spreadsheet->setActiveSheetIndex(0);
        $this->applyAllValidations($mainSheet, $headers, $sheetData, 1000);

        $this->moveInstructionsToFirst($spreadsheet);

        try {
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save($filePath);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'No se pudo guardar la plantilla Excel. Intente cerrar el archivo si está abierto y vuelva a intentarlo.',
                'error' => $e->getMessage(),
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

    public function exportAll(): BinaryFileResponse
    {
        $contacts = Contact::query()->orderBy('id')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('contactos');

        $headers = $this->templateHeaders();
        $sheet->fromArray($headers, null, 'A1');

        $groupNamesById = CategoryGroup::query()->pluck('name', 'id');

        $rowNum = 2;
        foreach ($contacts as $contact) {
            $sheet->fromArray($this->exportRowForContact($contact, $groupNamesById), null, "A{$rowNum}");
            $rowNum++;
        }

        $groups = $this->loadGroups();
        $sheetData = $this->buildReferenceSheets($spreadsheet, $groups);
        $mainSheet = $spreadsheet->setActiveSheetIndex(0);
        $this->applyAllValidations($mainSheet, $headers, $sheetData, max(1000, $rowNum));

        $lastCol = $sheet->getHighestColumn();
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $this->buildInstructionsSheet($spreadsheet);
        $this->moveInstructionsToFirst($spreadsheet);

        $fileName = 'contactos_exportados.xlsx';
        $tempFile = tempnam(sys_get_temp_dir(), 'contactos_').'.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);
    }

    /**
     * Convierte un Contact a un array representando una fila de exportación Excel.
     */
    public function exportRowForContact(Contact $contact, $groupNamesById = null): array
    {
        $groupNamesById ??= CategoryGroup::query()->pluck('name', 'id');

        $groupIds = is_array($contact->groups) ? $contact->groups : [];
        $groupNames = collect($groupIds)
            ->map(fn ($id) => $groupNamesById[$id] ?? null)
            ->filter()
            ->implode('; ');

        $additional = is_array($contact->additional_contacts) ? ($contact->additional_contacts[0] ?? []) : [];
        $branch = is_array($contact->branches) ? ($contact->branches[0] ?? []) : [];

        return [
            $contact->id,
            $contact->contact_type,
            $contact->id_type,
            $contact->id_number,
            $contact->dv,
            $contact->full_name,
            $contact->person_type,
            $groupNames,
            $contact->municipality,
            $contact->department,
            $contact->address,
            $contact->email,
            $contact->phone,
            $additional['name'] ?? '',
            $additional['phone'] ?? '',
            $additional['email'] ?? '',
            $branch['name'] ?? '',
            $branch['address'] ?? '',
            $branch['phone'] ?? '',
        ];
    }

    // ─── Métodos internos: catálogos y hojas de referencia ───

    private function loadGroups()
    {
        return CategoryGroup::query()->orderBy('name')->get(['id', 'name']);
    }

    private function buildReferenceSheets(Spreadsheet $spreadsheet, $groups): array
    {
        $listRows = [
            ['CLIENTE', 'NIT', 'PERSONA_NATURAL'],
            ['PROVEEDOR', 'CC', 'PERSONA_JURIDICA'],
            ['CLIENTE_PROVEEDOR', 'CE', ''],
            ['', 'TE', ''],
            ['', 'TI', ''],
            ['', 'RC', ''],
            ['', 'NUIP', ''],
            ['', 'PP', ''],
            ['', 'PPT', ''],
            ['', 'PEP', ''],
            ['', 'DIE', ''],
            ['', 'NIT_OTRO_PAIS', ''],
        ];
        $listSheet = $spreadsheet->createSheet();
        $listSheet->setTitle('Listas');
        $listSheet->fromArray(['TIPO DE CONTACTO', 'TIPO DE IDENTIFICACION', 'TIPO DE PERSONA'], null, 'A1');
        $listSheet->fromArray($listRows, null, 'A2');
        $contactTypeLastRow = 4; // 3 valores + encabezado
        $idTypeLastRow = count($listRows) + 1;
        $personTypeLastRow = 3; // 2 valores + encabezado

        $groupSheet = $spreadsheet->createSheet();
        $groupSheet->setTitle('Grupos');
        $groupSheet->fromArray(['ID', 'Nombre'], null, 'A1');
        if ($groups->isNotEmpty()) {
            $groupSheet->fromArray(
                $groups->map(fn ($group) => [$group->id, $group->name])->toArray(),
                null,
                'A2'
            );
        }
        $groupLastRow = $groups->count() + 1;

        return compact('contactTypeLastRow', 'idTypeLastRow', 'personTypeLastRow', 'groupLastRow', 'groups');
    }

    // ─── Validaciones de celdas Excel ────────────────────────

    private function applyAllValidations(Worksheet $mainSheet, array $headers, array $sheetData, int $maxValidationRow): void
    {
        $this->applyListValidationToSheet(
            $mainSheet, $headers, 'TIPO DE CONTACTO',
            "'Listas'!A2:A{$sheetData['contactTypeLastRow']}",
            $maxValidationRow, false
        );

        $this->applyListValidationToSheet(
            $mainSheet, $headers, 'TIPO DE IDENTIFICACION',
            "'Listas'!B2:B{$sheetData['idTypeLastRow']}",
            $maxValidationRow, false
        );

        $this->applyListValidationToSheet(
            $mainSheet, $headers, 'TIPO DE PERSONA',
            "'Listas'!C2:C{$sheetData['personTypeLastRow']}",
            $maxValidationRow, false
        );

        if ($sheetData['groups']->isNotEmpty()) {
            $this->applyListValidationToSheet(
                $mainSheet, $headers, 'GRUPOS',
                "'Grupos'!B2:B{$sheetData['groupLastRow']}",
                $maxValidationRow, true
            );
        }
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
        if ($colIdx === false || ! isset($colLetters[$colIdx])) {
            return;
        }

        $col = $colLetters[$colIdx];
        $validation = $this->buildListValidation($formula, $allowBlank);

        for ($row = 2; $row <= $maxValidationRow; $row++) {
            $sheet->getCell($col.$row)->setDataValidation(clone $validation);
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

    private function buildInstructionsSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Instrucciones');

        $sheet->setCellValue('A1', 'GUÍA PARA DILIGENCIAR EL ARCHIVO DE CONTACTOS');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F4E79');
        $sheet->getStyle('A1')->getFont()->getColor()->setRGB('FFFFFF');
        $sheet->mergeCells('A1:E1');
        $sheet->getRowDimension(1)->setRowHeight(25);

        $sheet->setCellValue('A3', 'REGLAS GENERALES');
        $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2E75B6');
        $sheet->getStyle('A3')->getFont()->getColor()->setRGB('FFFFFF');
        $sheet->mergeCells('A3:E3');

        $generalRules = [
            '1. No modifique los encabezados (fila 1) del archivo.',
            '2. Las columnas con lista desplegable (▼) solo aceptan los valores de esa lista.',
            '3. Las columnas marcadas como OBLIGATORIO no pueden quedar vacías.',
            '4. Si NUMERO DE IDENTIFICACION coincide con un contacto ya existente, esa fila lo actualiza en vez de crear uno nuevo.',
            '5. Para asignar varios GRUPOS use punto y coma (;) como separador, con el nombre exacto de la hoja "Grupos".',
            '6. Si deja EMAIL vacío, el sistema genera uno automático a partir del número de identificación.',
        ];

        $row = 4;
        foreach ($generalRules as $rule) {
            $sheet->setCellValue("A{$row}", $rule);
            $sheet->mergeCells("A{$row}:E{$row}");
            $row++;
        }

        $row += 1;
        $sheet->setCellValue('A'.$row, 'DETALLE POR COLUMNA');
        $sheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A'.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2E75B6');
        $sheet->getStyle('A'.$row)->getFont()->getColor()->setRGB('FFFFFF');
        $sheet->mergeCells("A{$row}:E{$row}");
        $row++;

        $sheet->setCellValue("A{$row}", 'COLUMNA');
        $sheet->setCellValue("B{$row}", 'OBLIGATORIO');
        $sheet->setCellValue("C{$row}", 'VALORES VÁLIDOS');
        $sheet->setCellValue("D{$row}", 'EJEMPLO');
        $sheet->setCellValue("E{$row}", 'OBSERVACIÓN');
        $sheet->getStyle("A{$row}:E{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:E{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('BDD7EE');
        $row++;

        $columns = [
            ['TIPO DE CONTACTO', 'SÍ', 'Ver lista desplegable (▼)', 'CLIENTE', 'CLIENTE, PROVEEDOR o CLIENTE_PROVEEDOR.'],
            ['TIPO DE IDENTIFICACION', 'SÍ', 'Ver lista desplegable (▼)', 'NIT', 'NIT, CC, CE, TE, TI, RC, NUIP, PP, PPT, PEP, DIE o NIT_OTRO_PAIS.'],
            ['NUMERO DE IDENTIFICACION', 'SÍ', 'Texto/número único', '900123456', 'Identifica al contacto; se usa para actualizar si ya existe.'],
            ['DV', 'NO', 'Un dígito', '8', 'Dígito de verificación (solo aplica a NIT).'],
            ['NOMBRE COMPLETO', 'SÍ', 'Texto libre', 'TEXTILES ANDINOS SAS', 'Razón social o nombre completo.'],
            ['TIPO DE PERSONA', 'SÍ', 'Ver lista desplegable (▼)', 'PERSONA_JURIDICA', 'PERSONA_NATURAL o PERSONA_JURIDICA.'],
            ['GRUPOS', 'NO', 'Nombres separados por punto y coma (;)', 'Materia Prima;Insumos', 'Deben existir en la hoja "Grupos".'],
            ['MUNICIPIO', 'NO', 'Texto libre', 'Pereira', ''],
            ['DEPARTAMENTO', 'NO', 'Texto libre', 'Risaralda', ''],
            ['DIRECCION', 'NO', 'Texto libre', 'CL 33 # 38 26', ''],
            ['EMAIL', 'NO', 'Correo válido', 'contacto@empresa.com', 'Si se deja vacío se genera uno automático.'],
            ['TELEFONO', 'NO', 'Texto/número', '3101234567', ''],
            ['CONTACTO ADICIONAL NOMBRE', 'NO', 'Texto libre', 'Juan Pérez', 'Un contacto adicional por fila.'],
            ['CONTACTO ADICIONAL TELEFONO', 'NO', 'Texto/número', '3111234567', ''],
            ['CONTACTO ADICIONAL EMAIL', 'NO', 'Correo válido', 'juan@empresa.com', ''],
            ['SUCURSAL NOMBRE', 'NO', 'Texto libre', 'Sucursal Norte', 'Una sucursal por fila.'],
            ['SUCURSAL DIRECCION', 'NO', 'Texto libre', 'CL 40 # 10 20', ''],
            ['SUCURSAL TELEFONO', 'NO', 'Texto/número', '3121234567', ''],
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

        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(14);
        $sheet->getColumnDimension('C')->setWidth(38);
        $sheet->getColumnDimension('D')->setWidth(24);
        $sheet->getColumnDimension('E')->setWidth(55);
    }
}
