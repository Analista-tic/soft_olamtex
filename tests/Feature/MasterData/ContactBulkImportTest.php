<?php

use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\Contact;
use App\Services\MasterData\ContactBulkImportService;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Tests de humo para la carga masiva de contactos (ContactBulkImportService).
 */
function contactBulkImportTestColumns(): array
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

/**
 * Construye el array en el mismo formato que produce
 * Worksheet::toArray(null, true, true, true) (fila 1 = encabezados,
 * datos desde fila 2, ambos indexados por letra de columna).
 *
 * @param  array<int, array<string, string>>  $dataRows  Cada fila como [encabezado => valor]
 */
function contactBulkImportTestRows(array $dataRows, array $headers = null): array
{
    $columns = $headers ?? contactBulkImportTestColumns();

    $headerRow = [];
    foreach ($columns as $idx => $header) {
        $headerRow[Coordinate::stringFromColumnIndex($idx + 1)] = $header;
    }

    $rows = [1 => $headerRow];
    $rowNum = 2;
    foreach ($dataRows as $dataRow) {
        $row = [];
        foreach ($columns as $idx => $header) {
            $row[Coordinate::stringFromColumnIndex($idx + 1)] = $dataRow[$header] ?? '';
        }
        $rows[$rowNum] = $row;
        $rowNum++;
    }

    return $rows;
}

beforeEach(function () {
    $this->service = app(ContactBulkImportService::class);
});

test('bulk import creates a new contact from a row without an existing id_number', function () {
    $idNumber = '900'.random_int(100000, 999999);

    $rows = contactBulkImportTestRows([
        [
            'TIPO DE CONTACTO' => 'PROVEEDOR',
            'TIPO DE IDENTIFICACION' => 'NIT',
            'NUMERO DE IDENTIFICACION' => $idNumber,
            'NOMBRE COMPLETO' => 'Proveedor de prueba carga masiva',
            'TIPO DE PERSONA' => 'PERSONA_JURIDICA',
            'EMAIL' => "proveedor{$idNumber}@test.local",
            'TELEFONO' => '3101234567',
        ],
    ]);

    $result = $this->service->processBulkUploadArray($rows);

    expect($result['errors'])->toBe([]);
    expect($result['inserted'])->toBe(1);
    expect($result['updated'])->toBe(0);

    $created = Contact::where('id_number', $idNumber)->first();
    expect($created)->not->toBeNull();
    expect($created->contact_type)->toBe('proveedor');
    expect($created->id_type)->toBe('nit');
    expect($created->person_type)->toBe('persona_juridica');
    expect($created->email)->toBe("proveedor{$idNumber}@test.local");
});

test('bulk import generates an email when left blank', function () {
    $idNumber = '901'.random_int(100000, 999999);

    $rows = contactBulkImportTestRows([
        [
            'TIPO DE CONTACTO' => 'CLIENTE',
            'TIPO DE IDENTIFICACION' => 'CC',
            'NUMERO DE IDENTIFICACION' => $idNumber,
            'NOMBRE COMPLETO' => 'Cliente sin correo',
            'TIPO DE PERSONA' => 'PERSONA_NATURAL',
        ],
    ]);

    $result = $this->service->processBulkUploadArray($rows);

    expect($result['errors'])->toBe([]);
    expect($result['inserted'])->toBe(1);

    $created = Contact::where('id_number', $idNumber)->first();
    expect($created->email)->toBe("{$idNumber}@import.local");
});

test('bulk import updates an existing contact when id_number matches', function () {
    $group = CategoryGroup::create(['name' => 'Grupo Prueba '.uniqid()]);

    $existing = Contact::create([
        'contact_type' => 'cliente',
        'id_type' => 'cc',
        'id_number' => '902'.random_int(100000, 999999),
        'full_name' => 'Nombre original',
        'person_type' => 'persona_natural',
        'email' => 'original'.uniqid().'@test.local',
        'groups' => [],
        'additional_contacts' => [],
        'branches' => [],
    ]);

    $rows = contactBulkImportTestRows([
        [
            'TIPO DE CONTACTO' => 'CLIENTE',
            'TIPO DE IDENTIFICACION' => 'CC',
            'NUMERO DE IDENTIFICACION' => $existing->id_number,
            'NOMBRE COMPLETO' => 'Nombre actualizado',
            'TIPO DE PERSONA' => 'PERSONA_NATURAL',
            'GRUPOS' => $group->name,
            'EMAIL' => $existing->email,
        ],
    ]);

    $result = $this->service->processBulkUploadArray($rows);

    expect($result['errors'])->toBe([]);
    expect($result['inserted'])->toBe(0);
    expect($result['updated'])->toBe(1);

    $existing->refresh();
    expect($existing->full_name)->toBe('Nombre actualizado');
    expect($existing->groups)->toBe([$group->id]);
});

test('bulk import reports an error for an invalid TIPO DE CONTACTO without aborting the batch', function () {
    $rows = contactBulkImportTestRows([
        [
            'TIPO DE CONTACTO' => 'NO_VALIDO',
            'TIPO DE IDENTIFICACION' => 'CC',
            'NUMERO DE IDENTIFICACION' => '903'.random_int(100000, 999999),
            'NOMBRE COMPLETO' => 'Fila inválida',
            'TIPO DE PERSONA' => 'PERSONA_NATURAL',
        ],
    ]);

    $result = $this->service->processBulkUploadArray($rows);

    expect($result['inserted'])->toBe(0);
    expect($result['updated'])->toBe(0);
    expect($result['errors'])->not->toBe([]);
});
