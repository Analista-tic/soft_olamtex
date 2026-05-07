<?php

namespace App\Services\MasterData;

use App\Models\Finance\Tax;
use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Color;
use App\Models\MasterData\Item;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\WarehouseItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Servicio dedicado a la importación histórica de ítems desde archivos CSV legacy.
 *
 * NO modifica el esquema de la BD — la trazabilidad se almacena dentro de extra_fields (JSON).
 * NO reutiliza el flujo normal de bulkUpload/processBulkUploadArray.
 * Las restricciones de unicidad se relajan para preservar datos históricos.
 */
class LegacyItemsMigrationService
{
    // ───────────────────────────────────────────────
    // Mapeo columnas CSV → campos del modelo Item
    // Clave = encabezado normalizado (MAYÚSCULAS, sin acentos).
    // Valor = campo destino (prefijo _ = campo virtual/resolver).
    // ───────────────────────────────────────────────
    protected const COLUMN_MAP = [
        'NOMBRE'               => 'name',
        'NAME'                 => 'name',
        'REFERENCIA'           => 'reference',
        'REFERENCE'            => 'reference',
        'DESCRIPCION'          => 'description',
        'DESCRIPCION DEL ARTICULO' => 'description',
        'DESCRIPTION'          => 'description',
        'CODIGO DE BARRAS'     => 'bar_code',
        'CODIGO'               => 'bar_code',
        'BARCODE'              => 'bar_code',
        'SUBGRUPO'             => '_subgroup_name',
        'SUB GRUPO'            => '_subgroup_name',
        'GRUPO'                => '_group_name',
        'IMPUESTO'             => '_tax_pct',
        'IMPUESTO %'           => '_tax_pct',
        'TAX'                  => '_tax_pct',
        'UNIDAD DE MEDIDA'     => 'unit_measure',
        'UNIDAD'               => 'unit_measure',
        'TIPO DE ITEM'         => '_item_type_label',
        'TIPO'                 => '_item_type_label',
        'ESTADO'               => '_status_label',
        'STATUS'               => '_status_label',
        'MOVIMIENTO NEGATIVO'  => '_negative_movement_label',
        'BODEGA'               => '_warehouse_name',
        'BODEGAS'              => '_warehouse_name',
        'CANTIDAD'             => '_warehouse_qty',
        'TOTAL CANTIDAD'       => '_warehouse_qty',
        'CANTIDAD MIN'         => '_warehouse_min',
        'CANTIDAD MINIMA'      => '_warehouse_min',
        'CANTIDAD MAX'         => '_warehouse_max',
        'CANTIDAD MAXIMA'      => '_warehouse_max',
        'CAMPOS ADICIONALES'   => '_extra_fields_keys',
        'VALORES ADICIONALES'  => '_extra_fields_values',
        'COLOR'                => '_color',
        'MARCA'                => 'brand',
        'MODELO'               => 'model',
        'PRECIO'               => '_price',
    ];

    /** Valores por defecto para campos obligatorios faltantes */
    protected const DEFAULTS = [
        'name'              => 'Item legado sin nombre',
        'description'       => 'Migrated from legacy system',
        'reference'         => '',
        'item_type'         => 'product',
        'status'            => true,
        'tracks_inventory'  => true,
        'negative_movement' => 0,
        'unit_measure'      => null,
        'bar_code'          => null,
        'brand'             => null,
        'model'             => null,
    ];

    /** Nombre del subgrupo fallback cuando el CSV no trae subgrupo o no existe */
    protected const FALLBACK_SUBGROUP = 'MIGRADOS SIN CLASIFICAR';
    protected const LEGACY_GROUP      = 'Migración Legacy';

    // Caches en memoria para evitar N+1
    protected array $subGroupCache = [];
    protected array $warehouseCache = [];
    protected array $taxCache = [];
    /** Mapa normalizado_color_name → color_id */
    protected array $colorCache = [];
    protected ?int $fallbackSubGroupId = null;
    protected ?int $defaultTaxId = null;

    // Aliases para subgrupos (valores RAW del CSV -> nombre canónico en BD)
    protected array $subgroupAliases = [
        // Materia Prima
        'ENTRETELA'              => 'TELA',
        'KOALITA'                => 'TELA',
        'MASSALA'                => 'TELA',
        // Insumos → Accesorios
        'ABROCHADURA'            => 'ACCESORIOS',
        'ARANDELA'               => 'ACCESORIOS',
        'CAMPANA'                => 'ACCESORIOS',
        'GANCHO'                 => 'ACCESORIOS',
        'MEDIA'                  => 'ACCESORIOS',
        'PERLA'                  => 'ACCESORIOS',
        'TANCA'                  => 'ACCESORIOS',
        'TERMINAL'               => 'ACCESORIOS',
        // Insumos → Cierre
        'CIERRES'                => 'CIERRE',
        // Insumos → Cremallera
        'CERMALLERA'             => 'CREMALLERA',
        'CREMALLER'              => 'CREMALLERA',
        // Insumos → Cordón
        'CORDON'                 => 'CORDÓN',
        'CORODON'                => 'CORDÓN',
        // Insumos → Elástico
        'ELASTICO'               => 'ELÁSTICO',
        // Insumos → Cinta
        'FRAMILON'               => 'CINTA',
        'RESINA'                 => 'CINTA',
        'TERMOENCOGIBLE'         => 'CINTA',
        'VELCRO'                 => 'CINTA',
        // Insumos → Código
        'CODIGOS'                => 'CÓDIGO',
        'CODIGO'                 => 'CÓDIGO',
        // Insumos → Hilo
        'CONO'                   => 'HILO',
        // Insumos → Marquilla
        'MARQ'                   => 'MARQUILLA',
        'MARQ.'                  => 'MARQUILLA',
        // Insumos → Placa
        'PLAQUETA'               => 'PLACA',
        // Insumos → Remache
        'TACHE'                  => 'REMACHE',
        // Insumos → Varios
        'CAMIONETA'              => 'VARIOS',
        'ROLLO'                  => 'VARIOS',
        // Suministros → Consumibles
        'ACEITE'                 => 'CONSUMIBLES',
        'BANDA'                  => 'CONSUMIBLES',
        'CREMA'                  => 'CONSUMIBLES',
        'DEDALE'                 => 'CONSUMIBLES',
        'ENTINTADOR'             => 'CONSUMIBLES',
        'FILTRO'                 => 'CONSUMIBLES',
        'PAPEL'                  => 'CONSUMIBLES',
        'PIEDRA'                 => 'CONSUMIBLES',
        'PUNTERA'                => 'CONSUMIBLES',
        'TINTA'                  => 'CONSUMIBLES',
        'TIZAS'                  => 'CONSUMIBLES',
        // Suministros → Empaque
        'ZUNCHO'                 => 'EMPAQUE',
        // Aditamentos → Accesorios maquina
        'AFILADORES'             => 'ACCESORIOS MAQUINA',
        'AGUJA'                  => 'ACCESORIOS MAQUINA',
        'CARRETEL'               => 'ACCESORIOS MAQUINA',
        'CARRETELES'             => 'ACCESORIOS MAQUINA',
        'CEPILLO'                => 'ACCESORIOS MAQUINA',
        'DIENTE'                 => 'ACCESORIOS MAQUINA',
        'LONG'                   => 'ACCESORIOS MAQUINA',
        'SINKER'                 => 'ACCESORIOS MAQUINA',
        'ZAPATO'                 => 'ACCESORIOS MAQUINA',
        // Aditamentos → Cuchilla
        'CONTRACUCHILLA'         => 'CUCHILLA',
        // Aditamentos → Paño
        'PANO'                   => 'PAÑO',
        // Herramientas
        'REVISADOR(CHEQUEADOR)'  => 'CHEQUEADOR',
        // Repuestos → General
        'AISLADOR'               => 'GENERAL',
        'ARANDELAS'              => 'GENERAL',
        'ESCOBILLAS'             => 'GENERAL',
        'FUSIBLE'                => 'GENERAL',
        'LOOPER'                 => 'GENERAL',
        'TORNILLO'               => 'GENERAL',
        // Papelería y Aseo
        'ALCOHOL'                => 'DESINFECTANTE',
        'AMBIENTADOR'            => 'VARIOS ASEO',
        'BOLSILLO'               => 'VARIOS PAPELERÍA',
        'BOLIGRAFO'              => 'BOLÍGRAFO',
        'CANDELA'                => 'VARIOS ASEO',
        'CLIP'                   => 'VARIOS PAPELERÍA',
        'CORRECTOR'              => 'VARIOS PAPELERÍA',
        'CUADERNOS'              => 'VARIOS PAPELERÍA',
        'ESCOBA'                 => 'LIMPIEZA PISOS',
        'GANCHO'                 => 'VARIOS PAPELERÍA',
        'GUIA'                   => 'VARIOS PAPELERÍA',
        'LAPIZ'                  => 'VARIOS PAPELERÍA',
        'LEGAJADOR'              => 'VARIOS PAPELERÍA',
        'MANGO'                  => 'LIMPIEZA PISOS',
        'MOUSE'                  => 'TECNOLOGÍA',
        'PAD'                    => 'TECNOLOGÍA',
        'PEGANOTAS'              => 'VARIOS PAPELERÍA',
        'PEGASTICK'              => 'VARIOS PAPELERÍA',
        'PILAS'                  => 'TECNOLOGÍA',
        'RECOGEDOR'              => 'LIMPIEZA PISOS',
        'REPUESTO'               => 'TECNOLOGÍA',
        'RESALTADOR'             => 'VARIOS PAPELERÍA',
        'SACAGANCHOS'            => 'VARIOS PAPELERÍA',
        'TOALLAS'                => 'TOALLA',
        'TRAPERO'                => 'LIMPIEZA PISOS',
    ];

    /**
     * Punto de entrada: importar todos los CSVs legacy.
     *
     * @param  array  $csvPaths  Rutas absolutas a los archivos CSV.
     * @param  string $batchId   Identificador único del lote (para trazabilidad).
     * @return array  Resumen con total_read, inserted, skipped, errors.
     */
    public function importAll(array $csvPaths, string $batchId): array
    {
        $this->warmCaches();

        $summary = ['total_read' => 0, 'inserted' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($csvPaths as $csvPath) {
            $sourceName = basename($csvPath);
            Log::info('legacy_import.start', ['file' => $sourceName, 'batch' => $batchId]);

            $result = $this->importCsv($csvPath, $sourceName, $batchId);

            $summary['total_read'] += $result['total_read'];
            $summary['inserted']   += $result['inserted'];
            $summary['skipped']    += $result['skipped'];
            $summary['errors']      = array_merge($summary['errors'], $result['errors']);

            Log::info('legacy_import.file_done', [
                'file'     => $sourceName,
                'read'     => $result['total_read'],
                'inserted' => $result['inserted'],
                'skipped'  => $result['skipped'],
                'errors'   => count($result['errors']),
            ]);
        }

        Log::info('legacy_import.complete', [
            'batch'    => $batchId,
            'read'     => $summary['total_read'],
            'inserted' => $summary['inserted'],
            'skipped'  => $summary['skipped'],
            'errors'   => count($summary['errors']),
        ]);

        return $summary;
    }

    // ─────────────────────────────────────────────────────
    // Lectura y procesamiento de un CSV individual
    // ─────────────────────────────────────────────────────

    protected function importCsv(string $path, string $sourceName, string $batchId): array
    {
        $result = ['total_read' => 0, 'inserted' => 0, 'skipped' => 0, 'errors' => []];

        if (!file_exists($path) || !is_readable($path)) {
            $result['errors'][] = "Archivo no encontrado o no legible: {$path}";
            Log::error('legacy_import.file_missing', ['path' => $path]);
            return $result;
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            $result['errors'][] = "No se pudo abrir: {$path}";
            return $result;
        }

        // Detectar BOM UTF-8 y leer encabezados
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }
        // Forzar conversión a UTF-8 en el stream (evita problemas con fgetcsv en Windows)
        @stream_filter_append($handle, 'convert.iconv.UTF-8/UTF-8');

        $rawHeaders = fgetcsv($handle, 0, ',', '"', '\\');
        if ($rawHeaders === false || empty($rawHeaders)) {
            fclose($handle);
            $result['errors'][] = "Sin encabezados en: {$path}";
            return $result;
        }

        $headerMap = $this->buildHeaderMap($rawHeaders);

        $rowNum = 1; // encabezado ya leído
        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $rowNum++;
            $result['total_read']++;

            if ($this->isEmptyRow($row)) {
                $result['skipped']++;
                continue;
            }

            try {
                $mapped = $this->mapRowToFields($row, $headerMap, $rawHeaders);
                $payload = $this->buildItemPayload($mapped, $sourceName, $batchId, $rowNum);

                DB::transaction(function () use ($payload) {
                    $item = Item::create($payload['attributes']);

                    if (!empty($payload['warehouses'])) {
                        $mv  = $payload['initial_movement'];
                        $whIdx = 0;

                        foreach ($payload['warehouses'] as $wh) {
                            $qty = (float) $wh['quantity'];
                            $unitPrice = $mv['unit_price'];

                            WarehouseItem::create([
                                'item_id'      => $item->id,
                                'warehouse_id' => $wh['warehouse_id'],
                                'quantity'     => $qty,
                                'stock_min'    => $wh['stock_min'],
                                'stock_max'    => $wh['stock_max'],
                                'weighted_average_price' => $qty > 0 && $unitPrice !== null ? $unitPrice : null,
                            ]);

                            if ($qty <= 0) {
                                $whIdx++;
                                continue;
                            }

                            // Movimiento inicial de inventario (saldo de apertura legacy).
                            $totalCost  = ($unitPrice !== null && $qty > 0)
                                ? round($qty * $unitPrice, 4)
                                : null;

                            // document_number único: LM-{batchId}-{rowNum}-{whIdx}
                            $docNumber = mb_substr(
                                'LM-' . $mv['batch_id'] . '-' . $mv['row_num'] . '-' . $whIdx,
                                0, 50
                            );

                            InventoryMovement::create([
                                'document_number'         => $docNumber,
                                'item_id'                 => $item->id,
                                'warehouse_id'            => $wh['warehouse_id'],
                                'source_type'             => $mv['source_type'],
                                'source_id'               => null,
                                'type'                    => 'entrada',
                                'date'                    => $mv['date'],
                                'quantity'                => $qty,
                                'unit_price'              => $unitPrice,
                                'total_cost'              => $totalCost,
                                'previous_stock'          => 0,
                                'previous_weighted_price' => 0,
                                'new_stock'               => $qty,
                                'new_weighted_price'      => $unitPrice ?? 0,
                                'notes'                   => 'Saldo inicial importado — ' . $mv['source_name'],
                                'created_by_user_id'      => null,
                            ]);

                            $whIdx++;
                        }
                    }
                });

                $result['inserted']++;

                // Log de progreso cada 500 filas
                if ($result['inserted'] % 500 === 0) {
                    Log::info('legacy_import.progress', [
                        'file' => $sourceName, 'row' => $rowNum, 'inserted' => $result['inserted'],
                    ]);
                }
            } catch (\Throwable $e) {
                $errorMsg = "Fila {$rowNum}: {$e->getMessage()}";
                $result['errors'][] = $errorMsg;
                $result['skipped']++;
                Log::warning('legacy_import.row_error', [
                    'file' => $sourceName, 'row' => $rowNum, 'error' => $e->getMessage(),
                ]);
            }
        }

        fclose($handle);
        return $result;
    }

    // ─────────────────────────────────────────────────────
    // Mapeo de encabezados
    // ─────────────────────────────────────────────────────

    /**
     * Construye [índice_columna => campo_destino] usando COLUMN_MAP.
     * Columnas no reconocidas se prefijan con _unmapped:.
     */
    protected function buildHeaderMap(array $rawHeaders): array
    {
        $map = [];
        foreach ($rawHeaders as $idx => $header) {
            $normalized = $this->normalizeHeader($header);
            if ($normalized === '') {
                continue;
            }

            if (isset(self::COLUMN_MAP[$normalized])) {
                $map[$idx] = self::COLUMN_MAP[$normalized];
            } else {
                $map[$idx] = '_unmapped:' . $normalized;
            }
        }
        return $map;
    }

    /**
     * Normaliza encabezados: quita controles, colapsa espacios, MAYÚSCULAS,
     * transliterar acentos a ASCII y elimina puntuación.
     */
    protected function normalizeHeader(string $value): string
    {
        $val = preg_replace('/[[:cntrl:]]+/u', ' ', $value);
        $val = preg_replace('/\p{Z}+/u', ' ', $val);
        $val = preg_replace('/\s+/u', ' ', trim($val));
        $clean = mb_strtoupper($val, 'UTF-8');

        // Transliterar acentos a ASCII
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $clean);
        $result = ($transliterated !== false && $transliterated !== '') ? $transliterated : $clean;

        // Quitar puntuación: paréntesis, corchetes, comas, puntos, etc.
        $result = preg_replace('/[()\[\].,;:\/\\\\-]+/u', ' ', $result);
        $result = preg_replace('/\s+/u', ' ', trim($result));

        return $result;
    }

    /**
     * Mapea una fila CSV a campos usando el headerMap.
     * También guarda la fila cruda asociada a los encabezados originales.
     */
    protected function mapRowToFields(array $row, array $headerMap, array $rawHeaders): array
    {
        $fields = [];
        foreach ($headerMap as $idx => $fieldName) {
            $fields[$fieldName] = isset($row[$idx]) ? trim((string) $row[$idx]) : '';
        }

        // Guardar fila cruda completa indexada por encabezados originales
        $rawRow = [];
        foreach ($rawHeaders as $idx => $header) {
            $rawRow[trim($header)] = $row[$idx] ?? '';
        }
        $fields['_raw_row'] = $rawRow;

        return $fields;
    }

    protected function isEmptyRow(array $row): bool
    {
        return empty(array_filter($row, fn($v) => $v !== null && trim((string) $v) !== ''));
    }

    // ─────────────────────────────────────────────────────
    // Construcción del payload de Item
    // ─────────────────────────────────────────────────────

    protected function buildItemPayload(array $mapped, string $sourceName, string $batchId, int $rowNum): array
    {
        $attributes = [];

        // --- Campos directos con defaults ---
        $attributes['name']        = $this->val($mapped, 'name', self::DEFAULTS['name']);
        $attributes['reference']   = $this->val($mapped, 'reference', self::DEFAULTS['reference']);
        $attributes['description'] = $this->val($mapped, 'description', self::DEFAULTS['description']);
        $attributes['bar_code']    = $this->val($mapped, 'bar_code', self::DEFAULTS['bar_code']);
        $attributes['unit_measure'] = $this->val($mapped, 'unit_measure', self::DEFAULTS['unit_measure']);
        $attributes['brand']       = $this->val($mapped, 'brand', self::DEFAULTS['brand']);
        $attributes['model']       = $this->val($mapped, 'model', self::DEFAULTS['model']);

        // --- Subgrupo ---
        $attributes['category_sub_group_id'] = $this->resolveSubGroupId(
            $mapped['_subgroup_name'] ?? '',
            $mapped['_group_name'] ?? ''
        );

        // --- Impuesto ---
        $attributes['tax_id'] = $this->resolveTaxId($mapped['_tax_pct'] ?? '');

        // --- Tipo, estado, movimiento negativo ---
        $attributes['item_type']         = $this->resolveItemType($mapped['_item_type_label'] ?? '');
        $attributes['status']            = $this->resolveStatus($mapped['_status_label'] ?? '');
        $attributes['tracks_inventory']  = self::DEFAULTS['tracks_inventory'];
        $attributes['negative_movement'] = $this->resolveNegativeMovement($mapped['_negative_movement_label'] ?? '');
        $attributes['origin_type']        = Item::ORIGIN_MIGRATION;
        $attributes['was_edited_after_import'] = false;
        $attributes['imported_at']        = now();
        $attributes['import_batch_id']    = $batchId;

        // --- Color ---
        [$colorId, $colorName] = $this->resolveColorId($mapped['_color'] ?? '');
        $attributes['color_id'] = $colorId;

        // --- Costo inicial (columna PRECIO = ultimocosto del legacy) ---
        $rawPrice  = $mapped['_price'] ?? '';
        $unitCost  = $this->decimalOrNull($rawPrice);

        // Advertencia si vino valor pero no fue parseable.
        if ($rawPrice !== '' && $unitCost === null) {
            Log::warning('legacy_import.unit_cost_invalid', [
                'file'      => $sourceName,
                'row'       => $rowNum,
                'raw_price' => $rawPrice,
            ]);
        }

        // --- extra_fields: campos adicionales + trazabilidad legacy ---
        $extraFields = $this->resolveExtraFields($mapped);

        // Conservar el nombre original del color en extra_fields (trazabilidad).
        if ($colorName !== '') {
            $extraFields['legacy_color_name'] = $colorName;
        }

        // Trazabilidad del costo inicial (no se guarda como precio funcional del ítem).
        $extraFields['legacy_initial_unit_cost']        = $unitCost;
        $extraFields['legacy_initial_unit_cost_source'] = 'ultimocosto';

        // Conservar trazabilidad legacy detallada dentro de extra_fields.
        $extraFields['legacy_import']     = true;
        $extraFields['legacy_source']     = $sourceName;
        $extraFields['migration_batch']   = $batchId;
        $extraFields['legacy_row_number'] = $rowNum;
        $extraFields['legacy_raw_row']    = $mapped['_raw_row'] ?? [];

        $attributes['extra_fields'] = $extraFields;

        // --- Bodegas ---
        $warehouses = $this->resolveWarehouse($mapped);

        // --- Movimiento inicial de inventario ---
        $initialMovement = [
            'unit_price'  => $unitCost,
            'source_type' => 'legacy_import',
            'date'        => now()->toDateString(),
            'batch_id'    => $batchId,
            'row_num'     => $rowNum,
            'source_name' => $sourceName,
        ];

        return [
            'attributes'       => $attributes,
            'warehouses'       => $warehouses,
            'initial_movement' => $initialMovement,
        ];
    }

    /** Devuelve valor del campo mapeado o el default si está vacío. */
    protected function val(array $mapped, string $key, mixed $default): mixed
    {
        $v = $mapped[$key] ?? '';
        return ($v !== '' && $v !== null) ? $v : $default;
    }

    // ─────────────────────────────────────────────────────
    // Resolvers
    // ─────────────────────────────────────────────────────

    protected function resolveSubGroupId(string $name, string $groupName = ''): int
    {
        $nameUpper  = mb_strtoupper(trim($name), 'UTF-8');
        $groupUpper = mb_strtoupper(trim($groupName), 'UTF-8');

        // DEBUG: logear valores recibidos para depuración de matching
        Log::debug('legacy_subgroup_lookup', [
            'name'      => $name,
            'nameUpper' => $nameUpper,
            'group'     => $groupName,
            'groupUpper'=> $groupUpper,
        ]);

        if ($nameUpper === '' || $nameUpper === '-') {
            return $this->getFallbackSubGroupId();
        }

        // Normalizar y aplicar aliases antes de buscar en cache/BD
        $normalizedName  = $this->normalize($name);
        $normalizedGroup = $this->normalize($groupName);

        // Aplicar alias si existe (clave en MAYÚSCULAS sin normalizar)
        $aliasKey = mb_strtoupper(trim($name), 'UTF-8');
        if (isset($this->subgroupAliases[$aliasKey])) {
            $name = $this->subgroupAliases[$aliasKey];
            $nameUpper = mb_strtoupper(trim($name), 'UTF-8');
            $normalizedName = $this->normalize($name);
        }

        // Intentar primero con grupo (clave combinada)
        if ($groupUpper !== '') {
            $normalizedName  = $this->normalize($name);
            $normalizedGroup = $this->normalize($groupName);
            $normKey = $normalizedName . '|' . $normalizedGroup;
            if (isset($this->subGroupCache[$normKey])) {
                return $this->subGroupCache[$normKey];
            }

            $cacheKey = $nameUpper . '|' . $groupUpper;
            if (isset($this->subGroupCache[$cacheKey])) {
                return $this->subGroupCache[$cacheKey];
            }

            // Fallback a consulta en BD si no está en cache (todavía respetando COLLATE correcto)
            $subGroup = CategorySubGroup::query()
                ->join('category_groups', 'category_groups.id', '=', 'category_sub_groups.category_group_id')
                ->whereRaw('UPPER(category_sub_groups.name) COLLATE utf8mb4_0900_ai_ci = UPPER(?) COLLATE utf8mb4_0900_ai_ci', [$nameUpper])
                ->whereRaw('UPPER(category_groups.name) COLLATE utf8mb4_0900_ai_ci = UPPER(?) COLLATE utf8mb4_0900_ai_ci', [$groupUpper])
                ->select('category_sub_groups.id')
                ->first();

            if ($subGroup) {
                $this->subGroupCache[$cacheKey] = $subGroup->id;
                $this->subGroupCache[$nameUpper] = $subGroup->id;
                // also store normalized versions for future
                $this->subGroupCache[$normKey] = $subGroup->id;
                $this->subGroupCache[$normalizedName] = $subGroup->id;
                return $subGroup->id;
            }
        }

        // Segundo intento: solo por nombre (sin grupo)
        if (isset($this->subGroupCache[$nameUpper])) {
            return $this->subGroupCache[$nameUpper];
        }

        $subGroup = CategorySubGroup::whereRaw(
            'UPPER(name) COLLATE utf8mb4_0900_ai_ci = UPPER(?) COLLATE utf8mb4_0900_ai_ci',
            [$nameUpper]
        )->first();

        if ($subGroup) {
            $this->subGroupCache[$nameUpper] = $subGroup->id;
            return $subGroup->id;
        }

        Log::info('legacy_import.subgroup_not_found', [
            'name'           => $name,
            'group'          => $groupName,
            'using_fallback' => self::FALLBACK_SUBGROUP,
        ]);
        return $this->getFallbackSubGroupId();
    }

    protected function getFallbackSubGroupId(): int
    {
        if ($this->fallbackSubGroupId !== null) {
            return $this->fallbackSubGroupId;
        }

        $groupId = $this->getOrCreateLegacyGroupId();
        $sub = CategorySubGroup::firstOrCreate(
            ['name' => self::FALLBACK_SUBGROUP, 'category_group_id' => $groupId],
        );
        $this->fallbackSubGroupId = $sub->id;
        return $sub->id;
    }

    protected function getOrCreateLegacyGroupId(): int
    {
        static $groupId = null;
        if ($groupId !== null) {
            return $groupId;
        }

        $group = CategoryGroup::firstOrCreate(['name' => self::LEGACY_GROUP]);
        $groupId = $group->id;
        return $groupId;
    }

    protected function resolveTaxId(string $value): ?int
    {
        $pct = $this->parseTaxPercentage($value);
        if ($pct === null) {
            return $this->getDefaultTaxId();
        }

        $rounded = round($pct, 2);
        $key = (string) $rounded;
        if (isset($this->taxCache[$key])) {
            return $this->taxCache[$key];
        }

        $tax = Tax::firstOrCreate(
            ['percentage' => $rounded],
            ['name' => $rounded == 0 ? 'Ninguno (0%)' : "Impuesto {$rounded}%", 'status' => true],
        );
        $this->taxCache[$key] = $tax->id;
        return $tax->id;
    }

    protected function parseTaxPercentage(string $value): ?float
    {
        $raw = trim($value);
        if ($raw === '') {
            return null;
        }
        $sanitized = preg_replace('/[^0-9,.\-]/', '', $raw);
        if ($sanitized === '') {
            return null;
        }
        $normalized = str_replace(',', '.', $sanitized);
        return is_numeric($normalized) ? (float) $normalized : null;
    }

    protected function getDefaultTaxId(): int
    {
        if ($this->defaultTaxId !== null) {
            return $this->defaultTaxId;
        }
        $tax = Tax::firstOrCreate(
            ['percentage' => 0],
            ['name' => 'Ninguno (0%)', 'status' => true],
        );
        $this->defaultTaxId = $tax->id;
        return $tax->id;
    }

    protected function resolveItemType(string $label): string
    {
        $clean = mb_strtolower(trim($label), 'UTF-8');
        if (str_contains($clean, 'serv')) {
            return 'service';
        }
        return 'product';
    }

    protected function resolveStatus(string $label): bool
    {
        $clean = mb_strtolower(trim($label), 'UTF-8');
        if ($clean === '') {
            return true;
        }
        if (preg_match('/^(0|false|deshabil|inactiv|inhabil|no|off)/', $clean)) {
            return false;
        }
        return true;
    }

    protected function resolveNegativeMovement(string $label): int
    {
        $clean = mb_strtoupper(trim($label), 'UTF-8');
        if (in_array($clean, ['SI', 'S', 'YES', 'Y', 'TRUE', '1'], true)) {
            return 1;
        }
        return 0;
    }

    protected function resolveExtraFields(array $mapped): array
    {
        $extra = [];

        // Pares CAMPOS ADICIONALES / VALORES ADICIONALES (separados por ;)
        $keys = array_filter(
            array_map('trim', explode(';', $mapped['_extra_fields_keys'] ?? '')),
            fn($v) => $v !== ''
        );
        $vals = array_filter(
            array_map('trim', explode(';', $mapped['_extra_fields_values'] ?? '')),
            fn($v) => $v !== ''
        );
        $count = min(count($keys), count($vals));
        for ($i = 0; $i < $count; $i++) {
            $extra[$keys[$i]] = $vals[$i];
        }

        // El nombre original del color se agrega en buildItemPayload como legacy_color_name.
        // No duplicar aquí para evitar sobreescritura.

        // Recoger columnas no mapeadas como campos extra (preservar datos)
        foreach ($mapped as $key => $value) {
            if (str_starts_with($key, '_unmapped:') && $value !== '') {
                $fieldName = str_replace('_unmapped:', '', $key);
                $extra[$fieldName] = $value;
            }
        }

        return $extra;
    }

    protected function resolveWarehouse(array $mapped): array
    {
        $whName = $mapped['_warehouse_name'] ?? '';
        if ($whName === '') {
            return [];
        }

        $normalizedWh = mb_strtoupper(trim($whName), 'UTF-8');
        if (isset($this->warehouseCache[$normalizedWh])) {
            $whId = $this->warehouseCache[$normalizedWh];
        } else {
            $wh = \App\Models\Inventory\Warehouse::whereRaw('UPPER(name) = ?', [$normalizedWh])->first();
            if (!$wh) {
                Log::warning('legacy_import.warehouse_not_found', [
                    'warehouse_name' => $whName,
                    'hint' => 'Crear la bodega en la tabla warehouses antes de correr el seeder',
                ]);
                return [];
            }
            $this->warehouseCache[$normalizedWh] = $wh->id;
            $whId = $wh->id;
        }

        return [[
            'warehouse_id' => $whId,
            'quantity'      => $this->parseNumber($mapped['_warehouse_qty'] ?? ''),
            'stock_min'     => $this->parseNumber($mapped['_warehouse_min'] ?? ''),
            'stock_max'     => $this->parseNumber($mapped['_warehouse_max'] ?? ''),
        ]];
    }

    protected function parseNumber(string $value): float
    {
        $normalized = str_replace(',', '.', trim($value));
        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    // ─────────────────────────────────────────────────────
    // Carga de caches al inicio (evita N+1)
    // ─────────────────────────────────────────────────────

    protected function warmCaches(): void
    {
        // Pre-cargar subgrupos existentes
        CategorySubGroup::with('categoryGroup')->get()->each(function ($sg) {
            $nameKey  = mb_strtoupper(trim($sg->name), 'UTF-8');
            $groupKey = $sg->categoryGroup
                ? mb_strtoupper(trim($sg->categoryGroup->name), 'UTF-8')
                : '';
            $combined = $nameKey . '|' . $groupKey;
            if ($nameKey !== '') {
                $this->subGroupCache[$nameKey]  = $sg->id;
                $this->subGroupCache[$combined] = $sg->id;
                // also store normalized (ASCII-transliterated) keys
                $normName = $this->normalize($sg->name);
                $normGroup = $this->normalize($groupKey);
                $this->subGroupCache[$normName] = $sg->id;
                $this->subGroupCache[$normName.'|'.$normGroup] = $sg->id;
            }
        });

        // Pre-cargar impuestos existentes
        Tax::all(['id', 'percentage'])->each(function ($tax) {
            $this->taxCache[(string) round((float) $tax->percentage, 2)] = $tax->id;
        });

        // Pre-cargar bodegas existentes
        \App\Models\Inventory\Warehouse::all(['id', 'name'])->each(function ($wh) {
            $this->warehouseCache[mb_strtoupper(trim($wh->name), 'UTF-8')] = $wh->id;
        });

        // Pre-cargar colores existentes (clave = nombre normalizado)
        Color::all(['id', 'name'])->each(function (Color $c) {
            $this->colorCache[$this->normalize($c->name)] = $c->id;
        });

        Log::info('legacy_import.caches_warmed', [
            'subgroups'  => count($this->subGroupCache),
            'taxes'      => count($this->taxCache),
            'warehouses' => count($this->warehouseCache),
            'colors'     => count($this->colorCache),
        ]);
    }

    /**
     * Parsea un valor monetario/decimal del CSV a float o null.
     *
     * Soporta:
     *   - Coma como separador decimal: "1.234,56" → 1234.56
     *   - Punto como separador decimal: "1234.56" → 1234.56
     *   - Símbolos de moneda y espacios: "$ 1.234,56" → 1234.56
     *   - Vacío o solo texto → null
     */
    protected function decimalOrNull(string $value): ?float
    {
        $raw = trim($value);
        if ($raw === '') {
            return null;
        }

        // Quitar símbolos de moneda, espacios y caracteres no numéricos salvo . , -
        $clean = preg_replace('/[^0-9,.\ -]/', '', $raw);
        $clean = trim((string) $clean);

        if ($clean === '' || $clean === '-') {
            return null;
        }

        // Detectar si usa coma como separador decimal (formato europeo/colombiano)
        // Ejemplo: "1.234,56" → hay punto como separador de miles y coma como decimal
        if (preg_match('/[.,]/', $clean)) {
            $lastComma = strrpos($clean, ',');
            $lastDot   = strrpos($clean, '.');

            if ($lastComma !== false && $lastDot !== false) {
                // Ambos presentes: el último es el decimal
                if ($lastComma > $lastDot) {
                    // Formato: 1.234,56 → quitar puntos, cambiar coma
                    $clean = str_replace('.', '', $clean);
                    $clean = str_replace(',', '.', $clean);
                } else {
                    // Formato: 1,234.56 → quitar comas
                    $clean = str_replace(',', '', $clean);
                }
            } elseif ($lastComma !== false) {
                // Solo coma: es el separador decimal
                $clean = str_replace(',', '.', $clean);
            }
            // Solo punto: ya es formato correcto
        }

        // Quitar espacios restantes (miles con espacio)
        $clean = str_replace(' ', '', $clean);

        return is_numeric($clean) ? (float) $clean : null;
    }

    /**
     * Resuelve el color_id a partir del nombre de color del CSV.
     *
     * @return array{0: int|null, 1: string}  [color_id|null, nombre_original]
     */
    protected function resolveColorId(string $rawColor): array
    {
        $rawColor = trim($rawColor);
        if ($rawColor === '') {
            return [null, ''];
        }

        $normalized = $this->normalize($rawColor);

        if (isset($this->colorCache[$normalized])) {
            return [$this->colorCache[$normalized], $rawColor];
        }

        // Buscar en BD por si fue creado después de warmCaches (raro, pero seguro)
        $color = Color::whereRaw(
            'UPPER(name) COLLATE utf8mb4_0900_ai_ci = UPPER(?) COLLATE utf8mb4_0900_ai_ci',
            [$rawColor]
        )->first();

        if ($color) {
            $this->colorCache[$normalized] = $color->id;
            return [$color->id, $rawColor];
        }

        Log::warning('legacy_import.color_not_found', [
            'color_raw'        => $rawColor,
            'color_normalized' => $normalized,
            'hint'             => 'El color no existe en la tabla colors. Se deja color_id = null.',
        ]);

        return [null, $rawColor];
    }

    /** Normaliza un valor: trim + mayúsculas + transliteración ASCII (sin acentos). */
    protected function normalize(string $value): string
    {
        $upper = mb_strtoupper(trim($value), 'UTF-8');
        // Colapsar espacios dobles
        $upper = (string) preg_replace('/\s+/u', ' ', trim($upper));
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $upper);
        return ($ascii !== false && $ascii !== '') ? (string) preg_replace('/\s+/', ' ', trim($ascii)) : $upper;
    }
}
