<?php

// Escribir un .xlsx real con PhpSpreadsheet (vía ZipStream) necesita más cabeza que el
// resto de la suite: al ejecutarse junto con las ~240 pruebas restantes en un mismo
// proceso PHP, el límite de memoria por defecto (128M) queda al borde y esta prueba es
// la que lo agota. No afecta el límite de memoria en producción, solo el de este archivo.
ini_set('memory_limit', '256M');

use App\Models\Settings\User;
use App\Models\Textil\FabricRoll;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;

/**
 * Encabezados del histórico legacy (15 columnas posicionales). El índice de cada
 * columna es el que consume FabricRollService::importFromCsv(): 0=ROLLO, 9=CANTIDAD
 * INGRESADA, 10=SALIDA PARCIAL, 12=CANTIDAD SALIDA, 14=FECHA DE INGRESO.
 */
const LEGACY_ROLL_HEADERS = [
    'ROLLO', 'UBICACION', 'N PEDIDO', 'PROVEEDOR', 'BASE TELA', 'TELA', 'COLOR',
    'CODIGO COLOR', 'ANCHO', 'CANTIDAD INGRESADA', 'SALIDA PARCIAL',
    'DISPONIBILIDAD PARCIAL', 'CANTIDAD SALIDA', 'DISPONIBILIDAD REAL', 'FECHA DE INGRESO',
];

function legacyRollDataRow(string $rollNumber, string $entryDate): array
{
    return [$rollNumber, 'F3', 'PED1', 'PROV1', 'DENIM', 'INDIGO', 'AZUL OSCURO', 'NA', '1.57', '100', '50', '50', '0', '100', $entryDate];
}

function makeLegacyImportUser(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('ver telas rollos', 'web'));
    $user->givePermissionTo(Permission::findOrCreate('editar telas rollos', 'web'));

    return $user;
}

/**
 * Genera un .xlsx real en disco (no un mock): la celda de fecha se guarda como fecha
 * de Excel con formato m/d/yyyy, igual que el archivo legacy real que motivó este
 * cambio, para que la prueba viaje por el mismo camino de PhpSpreadsheet::toArray().
 */
function makeLegacyRollXlsx(string $rollNumber, DateTimeImmutable $entryDate): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray(LEGACY_ROLL_HEADERS, null, 'A1');

    $row = legacyRollDataRow($rollNumber, '');
    $sheet->fromArray($row, null, 'A2');
    $sheet->setCellValue('O2', ExcelDate::dateTimeToExcel($entryDate));
    $sheet->getStyle('O2')->getNumberFormat()->setFormatCode('m/d/yyyy');

    $path = tempnam(sys_get_temp_dir(), 'legacy_roll_').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);
    // PhpSpreadsheet mantiene referencias circulares; sin esto el objeto no se libera
    // hasta el final de la suite y infla la memoria de toda la corrida de Pest.
    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet);

    tempXlsxFiles()[] = $path;

    return new UploadedFile($path, $rollNumber.'.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

/** Registro de archivos temporales generados en el test, para borrarlos en afterEach(). */
function &tempXlsxFiles(): array
{
    static $paths = [];

    return $paths;
}

beforeEach(function () {
    $this->actingAs(makeLegacyImportUser());
});

afterEach(function () {
    foreach (tempXlsxFiles() as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
    array_splice(tempXlsxFiles(), 0);
});

it('imports a legacy roll from an xlsx file without swapping day and month in the date', function () {
    // Fecha ambigua a propósito (día <= 12): si no se normaliza con normalizeImportDate()
    // antes de llegar al parser d/m/Y del CSV histórico, julio 4 se guardaría como abril 7.
    $entryDate = new DateTimeImmutable('2022-07-04');
    $file = makeLegacyRollXlsx('RL-XLSX-1', $entryDate);

    $response = $this->postJson(route('kardex-telas.inventario-rollos.importar-csv'), ['file' => $file]);

    $response->assertOk();
    expect($response->json('inserted'))->toBe(1);

    $roll = FabricRoll::where('roll_number', 'RL-XLSX-1')->firstOrFail();
    expect($roll->entry_date->toDateString())->toBe('2022-07-04')
        ->and((float) $roll->quantity)->toBe(100.0)
        ->and((float) $roll->partial_output)->toBe(50.0)
        ->and((float) $roll->actual_output)->toBe(0.0);
});

it('still imports a legacy roll from a comma-delimited csv (regression)', function () {
    $content = implode(',', LEGACY_ROLL_HEADERS)."\n".implode(',', legacyRollDataRow('RL-CSV-COMMA', '04/07/2022'));
    $file = UploadedFile::fake()->createWithContent('historico.csv', $content);

    $response = $this->postJson(route('kardex-telas.inventario-rollos.importar-csv'), ['file' => $file]);

    $response->assertOk();
    expect($response->json('inserted'))->toBe(1);

    $roll = FabricRoll::where('roll_number', 'RL-CSV-COMMA')->firstOrFail();
    expect($roll->entry_date->toDateString())->toBe('2022-07-04');
});

it('imports a legacy roll from a semicolon-delimited csv', function () {
    $content = implode(';', LEGACY_ROLL_HEADERS)."\n".implode(';', legacyRollDataRow('RL-CSV-SEMI', '04/07/2022'));
    $file = UploadedFile::fake()->createWithContent('historico.csv', $content);

    $response = $this->postJson(route('kardex-telas.inventario-rollos.importar-csv'), ['file' => $file]);

    $response->assertOk();
    expect($response->json('inserted'))->toBe(1);

    $roll = FabricRoll::where('roll_number', 'RL-CSV-SEMI')->firstOrFail();
    expect($roll->entry_date->toDateString())->toBe('2022-07-04')
        ->and((float) $roll->quantity)->toBe(100.0);
});

it('rejects a legacy row whose partial output exceeds the real availability instead of creating an impossible roll', function () {
    // CANTIDAD INGRESADA=50, SALIDA PARCIAL=60, CANTIDAD SALIDA=0: reservar 60 de un real
    // disponible de 50 deja el rollo descuadrado desde su creación (lo mismo que detecta
    // AuditRollBalancesCommand más tarde, pero evitado aquí en el origen del dato).
    $row = [...legacyRollDataRow('RL-CSV-OVERPARTIAL', '04/07/2022')];
    $row[9] = '50';
    $row[10] = '60';
    $row[12] = '0';
    $content = implode(',', LEGACY_ROLL_HEADERS)."\n".implode(',', $row);
    $file = UploadedFile::fake()->createWithContent('historico.csv', $content);

    $response = $this->postJson(route('kardex-telas.inventario-rollos.importar-csv'), ['file' => $file]);

    $response->assertStatus(422);
    expect($response->json('inserted'))->toBe(0)
        ->and($response->json('skipped'))->toBe(1)
        ->and($response->json('errors.0.message'))->toContain('supera la disponibilidad real');

    expect(FabricRoll::where('roll_number', 'RL-CSV-OVERPARTIAL')->exists())->toBeFalse();
});

it('rejects a file extension that is neither csv/txt nor xlsx/xls', function () {
    $file = UploadedFile::fake()->create('historico.pdf', 10, 'application/pdf');

    $response = $this->postJson(route('kardex-telas.inventario-rollos.importar-csv'), ['file' => $file]);

    $response->assertStatus(422);
});
