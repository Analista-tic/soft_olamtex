<?php

namespace App\Http\Controllers\Production;

use App\Helpers\MonthLabel;
use App\Helpers\SeasonParser;
use App\Http\Controllers\Controller;
use App\Models\Production\ProductionOrder;
use App\Services\Production\ProductionOrderService;
use App\Services\Settings\SettingService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProductionOrderController extends Controller
{
    public function __construct(
        private SettingService $settingService,
        private ProductionOrderService $productionOrderService,
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('Production/ProductionOrder/Index', [
            'sections' => [],
            'groups' => [],
            'brands' => [],
            'worlds' => [],
            'fabricBases' => [],
            'productSheets' => [],
            'destinations' => [],
            'yarnBases' => [],
            'months' => [],
            'priorities' => [],
        ]);
    }

    public function show(ProductionOrder $order): JsonResponse
    {
        $legacyTemporalFallbackEnabled = (bool) config('product-sheet.temporal_legacy_fallback_enabled', true);

        $order->load([
            'productSheet:id,type,sample_reference,product_reference,section_id,group_id,world_id,brand_id,fabric_base_id,yarn_base_id,billing_price,selling_price,sizes,description,status,flat_sketch_path',
        ]);

        $destinations = $this->settingService->getCatalogOptions('destinations')->keyBy('id');
        $sections = $this->settingService->getCatalogOptions('sections')->keyBy('id');
        $groups = $this->settingService->getCatalogOptions('groups')->keyBy('id');
        $brands = $this->settingService->getCatalogOptions('brands')->keyBy('id');
        $worlds = $this->settingService->getCatalogOptions('worlds')->keyBy('id');
        $fabricBases = $this->settingService->getCatalogOptions('fabric_bases')->keyBy('id');
        $yarnBases = $this->settingService->getCatalogOptions('yarn_bases')->keyBy('id');

        $destination = $destinations->get($order->destination_id, [
            'id' => $order->destination_id,
            'name' => 'N/A',
        ]);

        $productSheet = $order->productSheet;
        if ($productSheet) {
            $productSheet->section = $productSheet->section_id
                ? $sections->get($productSheet->section_id, ['id' => $productSheet->section_id, 'name' => 'N/A'])
                : null;
            $productSheet->group = $productSheet->group_id
                ? $groups->get($productSheet->group_id, ['id' => $productSheet->group_id, 'name' => 'N/A'])
                : null;
            $productSheet->brand = $productSheet->brand_id
                ? $brands->get($productSheet->brand_id, ['id' => $productSheet->brand_id, 'name' => 'N/A'])
                : null;
            $productSheet->world = $productSheet->world_id
                ? $worlds->get($productSheet->world_id, ['id' => $productSheet->world_id, 'name' => 'N/A'])
                : null;
            $productSheet->fabric_base = $productSheet->fabric_base_id
                ? $fabricBases->get($productSheet->fabric_base_id, ['id' => $productSheet->fabric_base_id, 'name' => 'N/A'])
                : null;
            $productSheet->yarn_base = $productSheet->yarn_base_id
                ? $yarnBases->get($productSheet->yarn_base_id, ['id' => $productSheet->yarn_base_id, 'name' => 'N/A'])
                : null;
        }

        $season = $order->season;
        if ($order->season_month !== null) {
            $monthName = SeasonParser::monthName((int) $order->season_month)
                ?? MonthLabel::fromNumber((int) $order->season_month);
            $seasonDisplay = $order->season_year !== null
                ? sprintf('%s %d', $monthName, (int) $order->season_year)
                : $monthName;
        } else {
            $seasonDisplay = $legacyTemporalFallbackEnabled && is_numeric($season)
                ? MonthLabel::fromNumber((int) $season)
                : ($legacyTemporalFallbackEnabled ? $season : '-');
        }

        $productionOrder = [
            'id' => $order->id,
            'op_code' => $order->op_code,
            'status' => $order->status,
            'source' => $order->source,
            'season' => $order->season,
            'season_month' => $order->season_month,
            'season_year' => $order->season_year,
            'dispatch_month' => $order->dispatch_month,
            'dispatch_month_num' => $order->dispatch_month_num,
            'dispatch_year' => $order->dispatch_year,
            'season_display' => $seasonDisplay,
            'destination_id' => $order->destination_id,
            'destination' => $destination,
            'priority' => $order->priority,
            'units' => $order->units,
            'estimated_delivery_date' => optional($order->estimated_delivery_date)?->toDateString(),
            'is_pending_completion' => !$order->units || ($order->isAutoGenerated() && !$order->estimated_delivery_date),
        ];

        return response()->json([
            'production_order' => $productionOrder,
            'product_sheet' => $productSheet,
        ]);
    }

}
