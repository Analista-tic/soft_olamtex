<?php

namespace App\Services\Production;

use App\Models\Finance\Tax;
use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Item;
use App\Models\Production\ProductionOrder;
use Illuminate\Support\Str;

class FinishedProductCatalogItemService
{
    private const CATEGORY_GROUP_NAME = 'PRODUCTO TERMINADO';
    private const DEFAULT_UNIT_MEASURE = 'unidad';
    private const PRODUCT_SHEET_TYPE = 'producto';

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

        [$subGroupName, $extraFields] = $this->extractSubGroupAndExtraFields($productName);

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
    private function extractSubGroupAndExtraFields(string $productName): array
    {
        $parts = preg_split('/\s+/u', $productName) ?: [];
        $parts = array_values(array_filter(array_map(
            fn ($part) => $this->normalizeText($part),
            $parts
        )));

        if ($parts === []) {
            throw new \RuntimeException('No fue posible descomponer el nombre del producto para generar el subgrupo.');
        }

        $subGroupName = mb_strtoupper((string) array_shift($parts), 'UTF-8');
        $extraFields = [];

        foreach ($parts as $index => $part) {
            $extraFields['Campo ' . ($index + 1)] = $part;
        }

        return [$subGroupName, $extraFields];
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
