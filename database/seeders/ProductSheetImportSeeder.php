<?php

namespace Database\Seeders;

use App\Models\Production\Collection as ProductCollection;
use App\Models\Production\ProductSheet;
use App\Services\Settings\SettingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Importa sábanas de producto desde la plantilla Excel generada por:
 *   php artisan product-sheets:template
 *
 * Uso:
 *   1. Coloca el Excel en: storage/app/plantilla_sabana_productos.xlsx
 *   2. Ejecuta: php artisan db:seed --class=ProductSheetImportSeeder
 *
 * Opciones de entorno (exportar antes de correr):
 *   PRODUCT_SHEET_EXCEL=storage/app/mi_archivo.xlsx   (ruta personalizada)
 *   PRODUCT_SHEET_DRY_RUN=true                        (solo valida, no inserta)
 */
class ProductSheetImportSeeder extends Seeder
{
    private const SIZES = ['XS','S','M','L','XL','2XL','U','2','3','4','5','6','8','10','12','14','16','28','30','32','34','36','38'];
    private const PROCESSES = ['Muestras','Confección','Terminación','Bodega','Desarrollo de muestras'];

    private SettingService $settingService;

    // Catálogos cargados una vez
    private array $sections    = [];
    private array $groups      = [];
    private array $brands      = [];
    private array $worlds      = [];
    private array $fabricBases = [];
    private array $yarnBases   = [];
    private array $opLists     = [];
    private array $collections = [];

    private int $created  = 0;
    private int $skipped  = 0;
    private array $errors = [];

    public function __construct(SettingService $settingService)
    {
        $this->settingService = $settingService;
    }

    public function run(): void
    {
        $path   = env('PRODUCT_SHEET_EXCEL', storage_path('app/plantilla_sabana_productos.xlsx'));
        $dryRun = filter_var(env('PRODUCT_SHEET_DRY_RUN', false), FILTER_VALIDATE_BOOLEAN);

        if (!file_exists($path)) {
            $this->command->error("Archivo no encontrado: {$path}");
            $this->command->line("Coloca el Excel en: storage/app/plantilla_sabana_productos.xlsx");
            return;
        }

        $this->command->info("Leyendo: {$path}");
        $this->loadCatalogs();

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheetByName('Productos');

        if (!$sheet) {
            $this->command->error('No se encontró la hoja "Productos" en el archivo.');
            return;
        }

        // Leer encabezados de fila 2 para mapear columnas dinámicamente
        $headers = [];
        $maxCol  = $sheet->getHighestColumn();
        $maxColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($maxCol);

        $coordFromIndex = fn(int $col, int $row): string =>
            \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $row;

        for ($c = 1; $c <= $maxColIndex; $c++) {
            $raw = $sheet->getCell($coordFromIndex($c, 2))->getValue();
            $normalized = $this->normalizeHeader((string) $raw);
            $headers[$c] = $normalized;
        }

        // Leer filas de datos (desde fila 4; fila 3 = hints)
        $maxRow = $sheet->getHighestRow();
        $dataRows = [];
        for ($r = 4; $r <= $maxRow; $r++) {
            $row = [];
            $hasContent = false;
            for ($c = 1; $c <= $maxColIndex; $c++) {
                $val = trim((string) $sheet->getCell($coordFromIndex($c, $r))->getValue());
                $row[$headers[$c]] = $val;
                if ($val !== '') $hasContent = true;
            }
            if ($hasContent) {
                $dataRows[] = ['num' => $r, 'data' => $row];
            }
        }

        $total = count($dataRows);
        $this->command->info("Filas con datos encontradas: {$total}");

        if ($dryRun) {
            $this->command->warn('MODO DRY-RUN activado: no se insertará nada.');
        }

        $bar = $this->command->getOutput()->createProgressBar($total);
        $bar->start();

        DB::beginTransaction();
        try {
            foreach ($dataRows as $entry) {
                $this->processRow($entry['num'], $entry['data'], $dryRun);
                $bar->advance();
            }
            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->command->newLine();
            $this->command->error("Error fatal en fila, se hizo rollback: " . $e->getMessage());
            return;
        }

        $bar->finish();
        $this->command->newLine(2);
        $this->command->info("Creados: {$this->created} | Omitidos (duplicados): {$this->skipped}");

        if (!empty($this->errors)) {
            $this->command->warn('Filas con advertencias:');
            foreach ($this->errors as $msg) {
                $this->command->line("  - {$msg}");
            }
        }
    }

    private function processRow(int $rowNum, array $row, bool $dryRun): void
    {
        $tipo = strtolower($row['tipo'] ?? '');
        if (!in_array($tipo, ['muestra', 'producto'], true)) {
            $this->errors[] = "Fila {$rowNum}: tipo inválido '{$tipo}' (debe ser muestra o producto). Omitida.";
            $this->skipped++;
            return;
        }

        $sampleRef  = $this->clean($row['ref_muestra']  ?? '');
        $productRef = $this->clean($row['ref_producto']  ?? '');
        $ref2       = $this->clean($row['ref_producto_2'] ?? '');

        // Validar que al menos una referencia esté presente
        if ($sampleRef === '' && $productRef === '' && $ref2 === '') {
            $this->errors[] = "Fila {$rowNum}: sin ninguna referencia. Omitida.";
            $this->skipped++;
            return;
        }

        // Detectar duplicado por referencia principal
        $existsQuery = ProductSheet::query();
        if ($sampleRef !== '')  $existsQuery->where('sample_reference', $sampleRef);
        elseif ($productRef !== '') $existsQuery->where('product_reference', $productRef);

        if ($existsQuery->exists()) {
            $ref = $sampleRef ?: $productRef;
            $this->errors[] = "Fila {$rowNum}: referencia '{$ref}' ya existe. Omitida.";
            $this->skipped++;
            return;
        }

        // Sección obligatoria
        $sectionName = $this->clean($row['seccion'] ?? '');
        $sectionId = $this->resolveId($this->sections, $sectionName);
        if ($sectionId === null && $sectionName !== '') {
            $this->errors[] = "Fila {$rowNum}: sección '{$sectionName}' no encontrada en catálogo.";
        }

        // Campos opcionales de catálogo
        $groupId      = $this->resolveId($this->groups,      $this->clean($row['grupo']              ?? ''));
        $brandId      = $this->resolveId($this->brands,      $this->clean($row['marca']               ?? ''));
        $worldId      = $this->resolveId($this->worlds,      $this->clean($row['mundo']              ?? ''));
        $fabricBaseId = $this->resolveId($this->fabricBases, $this->clean($row['base_tejido']        ?? ''));
        $yarnBaseId   = $this->resolveId($this->yarnBases,   $this->clean($row['base_hilaza']        ?? ''));
        $opListId     = $this->resolveId($this->opLists,     $this->clean($row['lista_operacional']  ?? ''));
        $collectionId = $this->resolveCollectionId($this->clean($row['coleccion'] ?? ''));

        // Tallas
        $sizes = [];
        foreach (self::SIZES as $size) {
            $colKey = "talla_{$size}";
            $sizes[$size] = strtoupper($row[$colKey] ?? '') === 'SI';
        }

        // Procesos
        $processes = [];
        foreach (self::PROCESSES as $process) {
            $colKey = "proceso_{$process}";
            $processes[$process] = strtoupper($row[$colKey] ?? '') === 'SI';
        }

        // Tipo prenda
        $garmentTypeRaw = strtolower($this->clean($row['tipo_prenda'] ?? ''));
        $garmentType    = in_array($garmentTypeRaw, ['superior', 'inferior'], true) ? $garmentTypeRaw : null;

        // Precios
        $billingPrice = $this->parseNumber($row['precio_facturacion'] ?? '');
        $sellingPrice = $this->parseNumber($row['precio_venta']       ?? '');
        $costoRaw     = $this->clean($row['costo'] ?? '');
        $costo        = $costoRaw !== '' ? $this->parseNumber($costoRaw) : null;

        // Es tejido
        $wovenProduct = strtoupper($this->clean($row['es_tejido'] ?? '')) === 'SI';

        $attributes = [
            'type'                => $tipo,
            'sample_reference'    => $sampleRef   !== '' ? $sampleRef   : null,
            'product_reference'   => $productRef  !== '' ? $productRef  : null,
            'product_reference_2' => $ref2         !== '' ? $ref2        : null,
            'export_reference'    => $this->clean($row['ref_exportacion'] ?? '') ?: null,
            'section_id'          => $sectionId,
            'group_id'            => $groupId,
            'brand_id'            => $brandId,
            'world_id'            => $worldId,
            'fabric_base_id'      => $fabricBaseId,
            'yarn_base_id'        => $yarnBaseId,
            'operational_list_id' => $opListId,
            'collection_id'       => $collectionId,
            'garment_type'        => $garmentType,
            'billing_price'       => $billingPrice,
            'selling_price'       => $sellingPrice,
            'costo'               => $costo,
            'woven_product'       => $wovenProduct,
            'description'         => $this->clean($row['descripcion'] ?? '') ?: null,
            'observaciones'       => $this->clean($row['observaciones'] ?? '') ?: null,
            'sizes'               => $sizes,
            'processes'           => $processes,
            'status'              => 'incomplete',
            'version'             => 1,
            'is_enabled'          => true,
        ];

        if (!$dryRun) {
            ProductSheet::create($attributes);
        }

        $this->created++;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function loadCatalogs(): void
    {
        $map = fn($collection) => $collection
            ->mapWithKeys(fn($item) => [mb_strtolower(trim((string) $item['name'])) => $item['id']])
            ->all();

        $this->sections    = $map($this->settingService->getCatalogOptions('sections'));
        $this->groups      = $map($this->settingService->getCatalogOptions('groups'));
        $this->brands      = $map($this->settingService->getCatalogOptions('brands'));
        $this->worlds      = $map($this->settingService->getCatalogOptions('worlds'));
        $this->fabricBases = $map($this->settingService->getCatalogOptions('fabric_bases'));
        $this->yarnBases   = $map($this->settingService->getCatalogOptions('yarn_bases'));
        $this->opLists     = $map($this->settingService->getCatalogOptions('operational_lists'));

        $this->collections = ProductCollection::where('is_active', true)
            ->get(['id', 'name'])
            ->mapWithKeys(fn($c) => [mb_strtolower(trim($c->name)) => $c->id])
            ->all();

        $this->command->line('Catálogos cargados: ' .
            count($this->sections) . ' secciones, ' .
            count($this->groups) . ' grupos, ' .
            count($this->worlds) . ' mundos, ' .
            count($this->fabricBases) . ' bases tejido.'
        );
    }

    private function resolveId(array $catalog, string $name): ?int
    {
        if ($name === '') return null;
        $key = mb_strtolower(trim($name));
        return isset($catalog[$key]) ? (int) $catalog[$key] : null;
    }

    private function resolveCollectionId(string $name): ?int
    {
        if ($name === '') return null;
        $key = mb_strtolower(trim($name));
        if (isset($this->collections[$key])) {
            return (int) $this->collections[$key];
        }

        // Crear colección si no existe
        $collection = ProductCollection::create(['name' => $name, 'status' => true]);
        $this->collections[$key] = $collection->id;
        $this->command->line("  + Colección creada: '{$name}'");
        return $collection->id;
    }

    private function normalizeHeader(string $raw): string
    {
        // Quitar saltos de línea, espacios múltiples y convertir a snake_case básico
        $clean = preg_replace('/\s+/', '_', strtolower(trim(str_replace(["\n", "\r", '*'], '', $raw))));
        // Normalizar tildes básicas
        $clean = str_replace(['á','é','í','ó','ú','ñ','ü'], ['a','e','i','o','u','n','u'], $clean);
        return $clean;
    }

    private function clean(string $value): string
    {
        return trim($value);
    }

    private function parseNumber(string $value): float
    {
        if ($value === '') return 0.0;
        // Eliminar separadores de miles y símbolo $
        $clean = str_replace(['$', '.', ' ', ','], ['', '', '', '.'], $value);
        return is_numeric($clean) ? (float) $clean : 0.0;
    }
}
