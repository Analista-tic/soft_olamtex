<?php

namespace App\Services\Production;

use App\Models\Production\ProductSheet;
use App\Services\Settings\SettingService;

/**
 * Interpreta el array leído del Excel de sábanas de producto (mismo formato
 * generado por ProductSheetExcelService) y crea o actualiza registros.
 *
 * Reglas de negocio replicadas de BaseProductSheetRequest / ProductSheetController
 * / ProductSheetCreationService para que la carga masiva se comporte igual que
 * el formulario normal.
 */
class ProductSheetBulkImportService
{
    public function __construct(
        private SettingService $settingService,
        private FabricBaseService $fabricBaseService,
        private YarnBaseService $yarnBaseService,
        private CollectionService $collectionService,
        private ProductSheetCreationService $creationService,
        private ProductSheetPersistenceService $persistenceService,
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $rows Salida de Worksheet::toArray(null, true, true, true)
     * @return array{inserted: int, updated: int, errors: string[]}
     */
    public function processBulkUploadArray(array $rows): array
    {
        if (empty($rows) || !isset($rows[1])) {
            return ['inserted' => 0, 'updated' => 0, 'errors' => ['El archivo no tiene datos.']];
        }

        $catalogs = $this->loadCatalogs();
        $headerToKey = $this->buildHeaderMap($catalogs);

        $colToKey = [];
        foreach ($rows[1] as $colLetter => $rawHeader) {
            $normalized = ProductSheetTemplateSchema::normalizeHeader((string) $rawHeader);
            if (isset($headerToKey[$normalized])) {
                $colToKey[$colLetter] = $headerToKey[$normalized];
            }
        }
        unset($rows[1]);

        $inserted = 0;
        $updated = 0;
        $errors = [];

        foreach ($rows as $rowNum => $row) {
            $hasContent = false;
            foreach ($row as $value) {
                if (trim((string) $value) !== '') {
                    $hasContent = true;
                    break;
                }
            }
            if (!$hasContent) {
                continue;
            }

            $data = [];
            foreach ($colToKey as $colLetter => $key) {
                $data[$key] = $row[$colLetter] ?? null;
            }

            $idRaw = trim((string) ($data['id'] ?? ''));
            $existing = null;

            if ($idRaw !== '') {
                if (!ctype_digit($idRaw) || !($existing = ProductSheet::find((int) $idRaw))) {
                    $errors[] = "Fila {$rowNum}: no existe una sábana con ID '{$idRaw}'.";
                    continue;
                }
            }

            $rowErrors = [];
            $attributes = $this->buildAttributesFromRow($data, $catalogs, $rowNum, $existing?->id, $rowErrors);

            if (!empty($rowErrors)) {
                array_push($errors, ...$rowErrors);
                continue;
            }

            try {
                if ($existing === null) {
                    $this->creationService->create($attributes);
                    $inserted++;
                    continue;
                }

                if (
                    $existing->type === 'muestra'
                    && ($attributes['type'] ?? null) === 'muestra'
                    && ($attributes['sample_reference'] ?? null) !== $existing->sample_reference
                ) {
                    $errors[] = "Fila {$rowNum}: la referencia de muestra no se puede modificar; se mantuvo '{$existing->sample_reference}'.";
                    $attributes['sample_reference'] = $existing->sample_reference;
                }

                $existing->update($this->creationService->sanitizeAttributes($attributes));
                $updated++;
            } catch (\Throwable $e) {
                $errors[] = "Fila {$rowNum}: error inesperado al guardar — " . $e->getMessage();
            }
        }

        return compact('inserted', 'updated', 'errors');
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

    private function buildHeaderMap(array $catalogs): array
    {
        $map = [];
        foreach (ProductSheetTemplateSchema::FIXED_COLUMNS as $col) {
            $map[ProductSheetTemplateSchema::normalizeHeader($col['header'])] = $col['key'];
        }
        foreach ($catalogs['sizes'] as $size) {
            $map[ProductSheetTemplateSchema::normalizeHeader(ProductSheetTemplateSchema::sizeHeader($size))] = ProductSheetTemplateSchema::sizeDataKey($size);
        }
        foreach ($catalogs['processes'] as $process) {
            $map[ProductSheetTemplateSchema::normalizeHeader(ProductSheetTemplateSchema::processHeader($process))] = ProductSheetTemplateSchema::processDataKey($process);
        }

        return $map;
    }

    // ─── Construcción de atributos por fila ──────────────────

    private function buildAttributesFromRow(array $data, array $catalogs, int $rowNum, ?int $excludeId, array &$errors): array
    {
        $type = mb_strtolower(trim((string) ($data['tipo'] ?? '')));
        if (!in_array($type, ['producto', 'muestra'], true)) {
            $errors[] = "Fila {$rowNum}: TIPO debe ser 'producto' o 'muestra'.";
        }

        $garmentTypeRaw = mb_strtolower(trim((string) ($data['tipo_prenda'] ?? '')));
        $garmentType = in_array($garmentTypeRaw, ['superior', 'inferior'], true) ? $garmentTypeRaw : null;

        $sectionId = $this->resolveCatalogId($catalogs['sections'], $data['seccion'] ?? null, 'SECCIÓN', true, $errors, $rowNum);
        $groupId = $this->resolveCatalogId($catalogs['groups'], $data['grupo'] ?? null, 'GRUPO', true, $errors, $rowNum);
        $brandId = $this->resolveCatalogId($catalogs['brands'], $data['marca'] ?? null, 'MARCA', true, $errors, $rowNum);
        $worldId = $this->resolveCatalogId($catalogs['worlds'], $data['mundo'] ?? null, 'MUNDO', true, $errors, $rowNum);
        $collectionId = $this->resolveCatalogId($catalogs['collections'], $data['coleccion'] ?? null, 'COLECCIÓN', false, $errors, $rowNum);
        $operationalListId = $this->resolveCatalogId($catalogs['operational_lists'], $data['lista_operacional'] ?? null, 'LISTA OPERACIONAL', false, $errors, $rowNum);

        $wovenRaw = mb_strtoupper(trim((string) ($data['es_tejido'] ?? '')));
        if (!in_array($wovenRaw, ['SI', 'NO'], true)) {
            $errors[] = "Fila {$rowNum}: ES TEJIDO debe ser SI o NO.";
        }
        $woven = $wovenRaw === 'SI';

        $fabricBaseId = $this->resolveCatalogId($catalogs['fabric_bases'], $data['base_tejido'] ?? null, 'BASE TEJIDO', !$woven, $errors, $rowNum);
        $yarnBaseId = $this->resolveCatalogId($catalogs['yarn_bases'], $data['base_hilaza'] ?? null, 'BASE HILAZA', $woven, $errors, $rowNum);

        // Se normaliza a mayúsculas aquí (y no solo en sanitizeAttributes) porque las
        // referencias ya se guardan en mayúsculas en BD; si no, la validación de
        // duplicados de abajo podría no detectar 'm001' contra un 'M001' existente.
        $sampleReference = ($v = trim((string) ($data['ref_muestra'] ?? ''))) !== '' ? mb_strtoupper($v) : null;
        $productReference = ($v = trim((string) ($data['ref_producto'] ?? ''))) !== '' ? mb_strtoupper($v) : null;
        $productReference2 = ($v = trim((string) ($data['ref_producto_2'] ?? ''))) !== '' ? mb_strtoupper($v) : null;

        if ($type === 'muestra') {
            if (!$sampleReference) {
                $errors[] = "Fila {$rowNum}: REF. MUESTRA es obligatoria cuando TIPO es muestra.";
            } elseif (
                ProductSheet::where('sample_reference', $sampleReference)
                    ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                    ->exists()
            ) {
                $errors[] = "Fila {$rowNum}: la REF. MUESTRA '{$sampleReference}' ya existe en otra sábana.";
            }
        }

        if ($type === 'producto') {
            if (!$productReference) {
                $errors[] = "Fila {$rowNum}: REF. PRODUCTO es obligatoria cuando TIPO es producto.";
            } elseif (
                ProductSheet::where('product_reference', $productReference)
                    ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                    ->exists()
            ) {
                $errors[] = "Fila {$rowNum}: la REF. PRODUCTO '{$productReference}' ya existe en otra sábana.";
            }
        }

        $sellingPrice = $this->parseNumber($data['precio_venta'] ?? null);
        if ($sellingPrice === null) {
            $errors[] = "Fila {$rowNum}: PRECIO VENTA es obligatorio y debe ser numérico.";
        }

        $description = trim((string) ($data['descripcion'] ?? ''));
        if ($description === '') {
            $errors[] = "Fila {$rowNum}: DESCRIPCIÓN es obligatoria.";
        }

        $sizes = [];
        foreach ($catalogs['sizes'] as $size) {
            $key = ProductSheetTemplateSchema::sizeDataKey($size);
            $sizes[$size] = mb_strtoupper(trim((string) ($data[$key] ?? ''))) === 'SI';
        }

        $processesRaw = [];
        foreach ($catalogs['processes'] as $process) {
            $key = ProductSheetTemplateSchema::processDataKey($process);
            $processesRaw[$process] = mb_strtoupper(trim((string) ($data[$key] ?? ''))) === 'SI';
        }
        $allowedForType = config('product-sheet.processes_allowed_by_type.' . $type, []);
        $processes = [];
        foreach ($catalogs['processes'] as $process) {
            if (($processesRaw[$process] ?? false) && in_array($process, $allowedForType, true)) {
                $processes[$process] = true;
            }
        }

        if (($processes['Muestras'] ?? false) && !$operationalListId) {
            $errors[] = "Fila {$rowNum}: LISTA OPERACIONAL es obligatoria porque el proceso 'Muestras' está activo.";
        }

        $habilitadoRaw = mb_strtoupper(trim((string) ($data['habilitado'] ?? 'SI')));
        $isEnabled = $habilitadoRaw !== 'NO';

        return [
            'type' => $type,
            'garment_type' => $garmentType,
            'section_id' => $sectionId,
            'group_id' => $groupId,
            'brand_id' => $brandId,
            'world_id' => $worldId,
            'collection_id' => $collectionId,
            'operational_list_id' => $operationalListId,
            'fabric_base_id' => $fabricBaseId,
            'yarn_base_id' => $yarnBaseId,
            'woven_product' => $woven,
            'sample_reference' => $sampleReference,
            'product_reference' => $productReference,
            'product_reference_2' => $productReference2,
            'billing_price' => $this->parseNumber($data['precio_facturacion'] ?? null),
            'costo' => $this->parseNumber($data['costo'] ?? null),
            'selling_price' => $sellingPrice ?? 0,
            'description' => $description,
            'observaciones' => trim((string) ($data['observaciones'] ?? '')) ?: null,
            'sizes' => $this->persistenceService->normalizeSizes($sizes),
            'processes' => $processes,
            'status' => 'incomplete',
            'is_enabled' => $isEnabled,
        ];
    }

    /**
     * Resuelve el nombre elegido en el Excel contra un catálogo {id, name}.
     * Reporta error si es obligatorio y viene vacío, si no hay coincidencia,
     * o si hay más de una coincidencia (nombres duplicados en el catálogo).
     */
    private function resolveCatalogId(array $catalog, ?string $rawName, string $label, bool $required, array &$errors, int $rowNum): ?int
    {
        $name = trim((string) $rawName);
        if ($name === '') {
            if ($required) {
                $errors[] = "Fila {$rowNum}: {$label} es obligatorio.";
            }
            return null;
        }

        $normalized = ProductSheetTemplateSchema::normalizeCatalogName($name);
        $matches = array_values(array_filter(
            $catalog,
            fn ($option) => ProductSheetTemplateSchema::normalizeCatalogName((string) $option['name']) === $normalized,
        ));

        if (count($matches) === 0) {
            $errors[] = "Fila {$rowNum}: {$label} '{$name}' no existe en el catálogo.";
            return null;
        }

        if (count($matches) > 1) {
            $errors[] = "Fila {$rowNum}: existen " . count($matches) . " registros de {$label} con el nombre '{$name}'. Corrige el catálogo antes de importar.";
            return null;
        }

        return (int) $matches[0]['id'];
    }

    private function parseNumber(mixed $value): ?float
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $clean = str_replace([' ', '$'], '', $raw);
        $clean = str_replace(',', '.', $clean);

        return is_numeric($clean) ? (float) $clean : null;
    }
}
