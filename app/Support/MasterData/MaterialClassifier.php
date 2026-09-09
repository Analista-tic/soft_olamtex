<?php

namespace App\Support\MasterData;

use App\Models\Expenses\PurchaseDocumentItem;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Item;
use Illuminate\Support\Facades\Cache;

/**
 * Clasifica subgrupos/ítems como tela u hilaza leyendo la columna
 * `category_sub_groups.material_kind` — un dato editable desde Datos Maestros, no una
 * lista de ids hardcodeada en config/master_data.php (retirada en esta misma fase).
 *
 * Sigue comparando por `category_sub_group_id` (nunca carga la relación
 * `categorySubGroup`): este proyecto activa `Model::preventLazyLoading()` fuera de
 * producción, así que depender de una relación no precargada rompería en tests y en
 * dev cualquiera de los ~10 call sites existentes, que hoy no la cargan porque nunca
 * la necesitaron.
 *
 * Los ids se cachean con el driver de caché de la app (mismo patrón que
 * AdditionalFieldService::OPTIONS_CACHE_KEY), NO en una propiedad `static`: en PHP-FPM
 * un worker sirve múltiples requests reutilizando su memoria, así que una `static`
 * quedaría obsoleta para el siguiente usuario que caiga en ese mismo worker tras una
 * reclasificación. Cache::forget() se llama al guardar un subgrupo.
 */
class MaterialClassifier
{
    private const CACHE_TTL = 3600; // 1 hora

    public static function isFabricSubgroup(?CategorySubGroup $subGroup): bool
    {
        if (! $subGroup) {
            return false;
        }

        return in_array($subGroup->id, self::subgroupIds('fabric'), true);
    }

    public static function isYarnSubgroup(?CategorySubGroup $subGroup): bool
    {
        if (! $subGroup) {
            return false;
        }

        return in_array($subGroup->id, self::subgroupIds('yarn'), true);
    }

    public static function isFabricItem(?Item $item): bool
    {
        if (! $item) {
            return false;
        }

        return in_array($item->category_sub_group_id, self::subgroupIds('fabric'), true);
    }

    public static function isYarnItem(?Item $item): bool
    {
        if (! $item) {
            return false;
        }

        return in_array($item->category_sub_group_id, self::subgroupIds('yarn'), true);
    }

    public static function isFabricPurchaseDocumentItem(?PurchaseDocumentItem $purchaseDocumentItem): bool
    {
        return self::isFabricItem($purchaseDocumentItem?->item);
    }

    /** Invalidar tras crear/editar/eliminar un CategorySubGroup con material_kind. */
    public static function forgetCache(): void
    {
        Cache::forget('material_classifier_subgroup_ids_fabric');
        Cache::forget('material_classifier_subgroup_ids_yarn');
    }

    private static function subgroupIds(string $kind): array
    {
        return Cache::remember(
            "material_classifier_subgroup_ids_{$kind}",
            self::CACHE_TTL,
            fn () => CategorySubGroup::where('material_kind', $kind)->pluck('id')->all(),
        );
    }
}
