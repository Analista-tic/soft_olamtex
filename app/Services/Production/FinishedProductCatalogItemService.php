<?php

namespace App\Services\Production;

use App\Models\Finance\Tax;
use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Color;
use App\Models\MasterData\Item;
use App\Models\Production\FabricBase;
use App\Models\Production\ProductionOrder;
use App\Models\Production\ProductSheet;
use App\Models\Production\YarnBase;
use App\Services\Settings\SettingService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class FinishedProductCatalogItemService
{
    private const CATEGORY_GROUP_NAME = 'PRODUCTO TERMINADO';
    private const DEFAULT_UNIT_MEASURE = 'unidad';
    private const PRODUCT_SHEET_TYPE = 'producto';

    public function __construct(
        private SettingService $settingService,
    ) {
    }

    public function ensureForProductionOrder(ProductionOrder $order): Item
    {
        $order->loadMissing('productSheet');

        $productSheet = $order->productSheet;
        if (!$productSheet) {
            throw new \RuntimeException('La orden no tiene una sábana de producto asociada.');
        }

        if (($productSheet->type ?? null) !== self::PRODUCT_SHEET_TYPE) {
            throw new \RuntimeException('Solo se puede crear el ítem automático desde sábanas de tipo producto.');
        }

        $productName = $this->extractProductName($productSheet->description);
        if ($productName === '') {
            throw new \RuntimeException('La sábana asociada no tiene nombre de producto.');
        }

        [$subGroupName, $extraFields] = $this->extractSubGroupAndExtraFields($productName, $productSheet);

        $group = $this->resolveCategoryGroup();
        $subGroup = $this->resolveCategorySubGroup($group, $subGroupName);
        $taxId = $this->resolveDefaultTaxId();
        $reference = $this->resolveReference($productSheet->product_reference, $productSheet->sample_reference);

        $item = $this->resolveExistingItem($reference, $productName);
        if ($item?->trashed()) {
            $item->restore();
        }

        $payload = [
            'category_sub_group_id' => $subGroup->id,
            'name' => $productName,
            'description' => $productName,
            'extra_fields' => $extraFields,
            'item_type' => 'product',
            'tracks_inventory' => true,
            'negative_movement' => false,
            'status' => true,
            'unit_measure' => self::DEFAULT_UNIT_MEASURE,
            'tax_id' => $taxId,
        ];

        if ($reference !== null) {
            $payload['reference'] = $reference;
        }

        if ($item) {
            $item->update($payload);

            return $item->fresh();
        }

        return Item::create($payload + [
            'origin_type' => Item::ORIGIN_SYSTEM,
        ]);
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function extractSubGroupAndExtraFields(string $productName, ProductSheet $productSheet): array
    {
        // El subgrupo sale de la Sección estructurada de la sábana, no del
        // nombre libre del producto — el nombre puede ser cualquier texto
        // que escriba el usuario (ver ProductSheetDialog.vue) y ya no tiene
        // por qué empezar con la sección.
        $subGroupName = $this->resolveCatalogOptionName('sections', $productSheet->section_id ?? null);
        if ($subGroupName === '') {
            // Caso borde: la opción de sección se borró/renombró en Settings
            // después de crear la sábana. section_id es NOT NULL + required,
            // así que esto no debería pasar en el flujo normal (y la capa de
            // prevención en ParameterUsageService lo bloquea desde el origen),
            // pero se degrada con gracia en vez de reventar la recepción de
            // bodega por un catálogo desactualizado.
            $subGroupName = $this->firstWordFallback($productName);
        }
        if ($subGroupName === '') {
            throw new \RuntimeException('No fue posible determinar el subgrupo del producto.');
        }
        $subGroupName = mb_strtoupper($subGroupName, 'UTF-8');

        $extraFields = [];

        $fabricBase = $this->resolveFabricBaseName($productSheet->fabric_base_id ?? null);
        if ($fabricBase !== '') {
            $extraFields['Base'] = mb_strtoupper($fabricBase, 'UTF-8');
        }

        $yarnBase = $this->resolveYarnBaseName($productSheet->yarn_base_id ?? null);
        if ($yarnBase !== '') {
            $extraFields['Base Hilaza'] = mb_strtoupper($yarnBase, 'UTF-8');
        }

        $world = $this->resolveCatalogOptionName('worlds', $productSheet->world_id ?? null);
        if ($world !== '') {
            $extraFields['Mundo'] = mb_strtoupper($world, 'UTF-8');
        }

        Log::info('finished_product_catalog_item.extra_fields_resolved', [
            'product_sheet_id' => $productSheet->id,
            'product_name' => $productName,
            'section_id' => $productSheet->section_id,
            'sub_group' => $subGroupName,
            'fabric_base_id' => $productSheet->fabric_base_id,
            'fabric_base' => $fabricBase,
            'yarn_base_id' => $productSheet->yarn_base_id,
            'yarn_base' => $yarnBase,
            'world_id' => $productSheet->world_id,
            'world' => $world,
            'extra_fields' => $extraFields,
        ]);

        return [$subGroupName, $extraFields];
    }

    /**
     * Respaldo del comportamiento anterior: primera palabra del nombre del
     * producto. Solo se usa si la Sección de la sábana no se pudo resolver
     * contra el catálogo (ver comentario arriba).
     */
    private function firstWordFallback(string $productName): string
    {
        $parts = preg_split('/\s+/u', $productName) ?: [];
        $parts = array_values(array_filter(array_map(
            fn ($part) => $this->normalizeText($part),
            $parts
        )));

        return $parts === [] ? '' : mb_strtoupper((string) $parts[0], 'UTF-8');
    }

    private function resolveFabricBaseName(int|string|null $fabricBaseId): string
    {
        if ($fabricBaseId === null || $fabricBaseId === '') {
            return '';
        }

        $fabricBase = FabricBase::find((int) $fabricBaseId);

        return $fabricBase ? $this->normalizeText($fabricBase->nombre) : '';
    }

    private function resolveYarnBaseName(int|string|null $yarnBaseId): string
    {
        if ($yarnBaseId === null || $yarnBaseId === '') {
            return '';
        }

        $yarnBase = YarnBase::find((int) $yarnBaseId);

        return $yarnBase ? $this->normalizeText($yarnBase->nombre) : '';
    }

    private function resolveCatalogOptionName(string $catalogKey, int|string|null $optionId): string
    {
        if ($optionId === null || $optionId === '') {
            return '';
        }

        $option = $this->settingService->getCatalogOptionById($catalogKey, $optionId);
        if (!is_array($option)) {
            return '';
        }

        $value = $option['name'] ?? $option['nombre'] ?? $option['description'] ?? null;
        if (!is_string($value) && !is_numeric($value)) {
            return '';
        }

        return $this->normalizeText((string) $value);
    }

    private function resolveCategoryGroup(): CategoryGroup
    {
        $existing = CategoryGroup::query()
            ->get()
            ->first(function (CategoryGroup $group) {
                return $this->semanticKey($group->name) === $this->semanticKey(self::CATEGORY_GROUP_NAME);
            });

        if ($existing) {
            return $existing;
        }

        return CategoryGroup::create([
            'name' => self::CATEGORY_GROUP_NAME,
        ]);
    }

    private function resolveCategorySubGroup(CategoryGroup $group, string $name): CategorySubGroup
    {
        $normalizedName = $this->normalizeText($name);

        $existing = CategorySubGroup::query()
            ->where('category_group_id', $group->id)
            ->get()
            ->first(function (CategorySubGroup $subGroup) use ($normalizedName) {
                return $this->semanticKey($subGroup->name) === $this->semanticKey($normalizedName);
            });

        if ($existing) {
            return $existing;
        }

        return CategorySubGroup::create([
            'category_group_id' => $group->id,
            'name' => mb_strtoupper($normalizedName, 'UTF-8'),
        ]);
    }

    private function resolveDefaultTaxId(): int
    {
        $tax = Tax::query()
            ->where('status', true)
            ->where('percentage', 0)
            ->orderBy('id')
            ->first();

        if ($tax) {
            return (int) $tax->id;
        }

        $tax = Tax::create([
            'name' => 'Ninguno (0%)',
            'percentage' => 0,
            'status' => true,
        ]);

        return (int) $tax->id;
    }

    private function resolveReference(?string $productReference, ?string $sampleReference): ?string
    {
        $reference = $this->normalizeText($productReference) ?: $this->normalizeText($sampleReference);

        return $reference !== '' ? Str::upper($reference) : null;
    }

    private function resolveExistingItem(?string $reference, string $productName): ?Item
    {
        $referenceItem = $reference !== null ? $this->resolveItemByReference($reference) : null;
        $nameItem = $this->resolveItemByName($productName);

        if ($referenceItem && $nameItem && $referenceItem->id !== $nameItem->id) {
            throw new \RuntimeException(
                'Existe un conflicto de duplicados: una coincidencia por referencia y otra distinta por nombre.'
            );
        }

        return $referenceItem ?? $nameItem;
    }

    private function resolveItemByReference(string $reference): ?Item
    {
        return Item::withTrashed()
            ->whereRaw('LOWER(reference) = ?', [mb_strtolower($reference, 'UTF-8')])
            ->first();
    }

    private function resolveItemByName(string $productName): ?Item
    {
        $semanticName = $this->semanticKey($productName);

        return Item::withTrashed()
            ->get()
            ->first(function (Item $item) use ($semanticName) {
                return $this->semanticKey($item->name) === $semanticName;
            });
    }

    /**
     * Crea o actualiza un ítem por cada combinación color+talla en remision_curves.
     * Referencia generada: {product_reference}{color_code_3dig}{talla} → ej. 9056790004S
     *
     * @param  array<int, array<string, mixed>>  $remisionCurves
     * @return array<int, int>  [item_id => cantidad]
     */
    public function ensureItemsForCurves(ProductionOrder $order, array $remisionCurves): array
    {
        $order->loadMissing('productSheet');
        $productSheet = $order->productSheet;

        if (!$productSheet || ($productSheet->type ?? null) !== self::PRODUCT_SHEET_TYPE) {
            throw new \RuntimeException('Solo se puede crear ítems automáticos desde sábanas de tipo producto.');
        }

        $productName = $this->extractProductName($productSheet->description);
        if ($productName === '') {
            throw new \RuntimeException('La sábana asociada no tiene nombre de producto.');
        }

        [$subGroupName, $baseExtraFields] = $this->extractSubGroupAndExtraFields($productName, $productSheet);

        $group    = $this->resolveCategoryGroup();
        $subGroup = $this->resolveCategorySubGroup($group, $subGroupName);
        $taxId    = $this->resolveDefaultTaxId();
        $productRef = $this->resolveReference($productSheet->product_reference, $productSheet->sample_reference) ?? '';

        $nonSizeKeys = ['color', 'color_code', 'total'];
        $result = [];

        foreach ($remisionCurves as $curve) {
            $colorName  = mb_strtoupper(trim((string) ($curve['color'] ?? '')), 'UTF-8');
            $colorHex   = trim((string) ($curve['color_code'] ?? ''));

            $colorModel  = $this->resolveColor($colorName, $colorHex);
            $colorCode3  = $colorModel?->code;
            $colorId     = $colorModel?->id;

            foreach ($curve as $key => $qty) {
                if (in_array(strtolower((string) $key), $nonSizeKeys, true)) {
                    continue;
                }

                $qty = (int) ($qty ?? 0);
                if ($qty <= 0) {
                    continue;
                }

                $talla       = mb_strtoupper(trim((string) $key), 'UTF-8');
                $colorSuffix = $colorCode3 ?? Str::upper(Str::limit($colorName, 6, ''));
                $reference   = mb_strtoupper("{$productRef}{$colorSuffix}{$talla}", 'UTF-8');
                // Color antes que talla, igual que el reference de arriba
                // (nomenclatura de la empresa) — sin mezclar el código
                // numérico de referencia en el nombre, que sigue siendo texto
                // legible (Sección + color + talla).
                $name        = mb_strtoupper(trim("{$subGroupName} {$colorName} {$talla}"), 'UTF-8');

                $extraFields          = $baseExtraFields;
                $extraFields['TALLA'] = $talla;

                $item = $this->resolveItemByReference($reference);
                if ($item?->trashed()) {
                    $item->restore();
                }

                $payload = [
                    'category_sub_group_id' => $subGroup->id,
                    'name'                  => $name,
                    'description'           => $name,
                    'reference'             => $reference,
                    'extra_fields'          => $extraFields,
                    'item_type'             => 'product',
                    'tracks_inventory'      => true,
                    'negative_movement'     => false,
                    'status'                => true,
                    'unit_measure'          => self::DEFAULT_UNIT_MEASURE,
                    'tax_id'                => $taxId,
                    'color_id'              => $colorId,
                ];

                if ($item) {
                    $item->update($payload);
                    $item = $item->fresh();
                } else {
                    $item = Item::create($payload + ['origin_type' => Item::ORIGIN_SYSTEM]);
                }

                $result[$item->id] = $qty;
            }
        }

        return $result;
    }

    private function resolveColor(string $colorName, string $colorHex): ?Color
    {
        if ($colorName !== '') {
            $match = Color::whereRaw('UPPER(name) = ?', [$colorName])->first();
            if ($match) {
                return $match;
            }
        }

        if ($colorHex !== '') {
            $match = Color::whereRaw('LOWER(hexadecimal) = ?', [mb_strtolower($colorHex, 'UTF-8')])->first();
            if ($match) {
                return $match;
            }
        }

        return null;
    }

    private function extractProductName(?string $value): string
    {
        $name = trim((string) $value);
        $name = preg_replace('/[\r\n\t]+/u', ' ', $name);

        return is_string($name) ? trim($name) : '';
    }

    private function normalizeText(?string $value): string
    {
        return preg_replace('/\s+/u', ' ', trim((string) $value)) ?? '';
    }

    private function semanticKey(?string $value): string
    {
        return mb_strtolower(Str::ascii($this->normalizeText($value)), 'UTF-8');
    }
}
