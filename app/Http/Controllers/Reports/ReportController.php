<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Expenses\PurchaseDocument;
use App\Models\Inventory\WarehouseItem;
use App\Models\Production\ProductionOrder;
use App\Models\Production\ProductionRollConsumption;
use App\Models\Production\TechnicalSheetSupply;
use App\Models\Textil\FabricRoll;
use App\Services\Reports\ReportPdfService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function index()
    {
        return Inertia::render('Reports/Index');
    }

    public function textil()
    {
        return Inertia::render('Reports/Textil/Index');
    }

    public function produccion()
    {
        return Inertia::render('Reports/Produccion/Index');
    }

    public function inventario()
    {
        return Inertia::render('Reports/Inventario/Index');
    }

    public function financiero()
    {
        return Inertia::render('Reports/Financiero/Index');
    }

    public function textilData(Request $request)
    {
        $report = $request->input('report', 'disponibilidad');
        $perPage = (int) $request->input('per_page', 15);

        $query = FabricRoll::query()
            ->with(['item'])
            ->select([
                'id',
                'roll_number',
                'item_id',
                'internal_color_name',
                'internal_color_code',
                'quantity',
                'partial_output',
                'actual_output',
                'approval_status',
                'entry_date',
                'batch',
                'location',
                'updated_at',
            ]);

        if ($report === 'sin_movimiento') {
            $query->whereRaw('(quantity - actual_output) > 0')
                ->where('updated_at', '<', now()->subDays(30));
        } elseif ($report === 'agotados') {
            $query->whereRaw('(quantity - actual_output) <= 0');
        }

        if ($search = $request->input('search')) {
            $query->where('roll_number', 'like', "%{$search}%");
        }

        if ($color = $request->input('color')) {
            $query->where('internal_color_name', 'like', "%{$color}%");
        }

        if ($status = $request->input('approval_status')) {
            $query->where('approval_status', $status);
        }

        $all = $request->boolean('all');
        $rolls = $all
            ? $query->orderByDesc('id')->get()
            : $query->orderByDesc('id')->paginate($perPage);

        $totalRolls = FabricRoll::count();
        $availableMeters = (float) FabricRoll::selectRaw('SUM(quantity - actual_output) as total')
            ->whereRaw('(quantity - actual_output) > 0')
            ->value('total');
        $noMovement = FabricRoll::whereRaw('(quantity - actual_output) > 0')
            ->where('updated_at', '<', now()->subDays(30))
            ->count();

        return response()->json([
            'data' => $rolls,
            'summary' => [
                'total_rolls'      => $totalRolls,
                'available_meters' => round($availableMeters, 2),
                'no_movement'      => $noMovement,
            ],
        ]);
    }

    public function produccionData(Request $request)
    {
        $perPage  = (int) $request->input('per_page', 15);
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');

        $query = ProductionOrder::query()
            ->with(['productSheet', 'technicalSheet'])
            ->select([
                'id',
                'op_code',
                'product_sheet_id',
                'status',
                'current_process',
                'season',
                'units',
                'estimated_delivery_date',
                'created_at',
            ]);

        if ($search = $request->input('search')) {
            $query->where('op_code', 'like', "%{$search}%");
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Con filtro de fecha: solo órdenes con consumo en ese rango
        if ($dateFrom || $dateTo) {
            $rollIds = ProductionRollConsumption::query()
                ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
                ->when($dateTo,   fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
                ->distinct()->pluck('production_order_id');

            $supplyIds = TechnicalSheetSupply::query()
                ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
                ->when($dateTo,   fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
                ->distinct()->pluck('production_order_id');

            $orderIds = $rollIds->merge($supplyIds)->unique()->values();
            $query->whereIn('id', $orderIds);
        }

        $all = $request->boolean('all');
        $orders = $all
            ? $query->orderByDesc('id')->get()
            : $query->orderByDesc('id')->paginate($perPage);

        $summary = [
            'total'       => ProductionOrder::count(),
            'activas'     => ProductionOrder::where('status', 'active')->count(),
            'completadas' => ProductionOrder::where('status', 'completed')->count(),
        ];

        return response()->json([
            'data'    => $orders,
            'summary' => $summary,
        ]);
    }

    public function produccionConsolidadoData(Request $request)
    {
        $search   = $request->input('search');
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');

        $rollIds = ProductionRollConsumption::query()
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo,   fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->distinct()->pluck('production_order_id');

        $supplyIds = TechnicalSheetSupply::query()
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo,   fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->distinct()->pluck('production_order_id');

        $orderIds = $rollIds->merge($supplyIds)->unique()->values();

        $ordersQuery = ProductionOrder::with(['productSheet'])
            ->whereIn('id', $orderIds)
            ->orderBy('op_code');

        if ($search) {
            $ordersQuery->where('op_code', 'like', "%{$search}%");
        }

        $orders = $ordersQuery->get();

        $result = $orders->map(function ($order) use ($dateFrom, $dateTo) {
            $units = max(1, (int) ($order->units ?? 1));

            $supplies = TechnicalSheetSupply::where('production_order_id', $order->id)
                ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
                ->when($dateTo,   fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
                ->get()
                ->map(function ($supply) use ($units) {
                    $qty   = round((float) $supply->qty_per_unit * $units, 4);
                    $price = (float) $supply->unit_cost;
                    return [
                        'type'      => 'Insumo',
                        'reference' => $supply->uid ?? '—',
                        'name'      => $supply->name ?? $supply->requested_name ?? '—',
                        'iva'       => 0,
                        'qty'       => $qty,
                        'uom'       => $supply->uom ?? '—',
                        'price'     => $price,
                        'total'     => round($qty * $price, 2),
                    ];
                });

            $rolls = ProductionRollConsumption::where('production_order_id', $order->id)
                ->with(['fabricRoll.item'])
                ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
                ->when($dateTo,   fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
                ->get()
                ->map(function ($consumption) {
                    $qty   = (float) $consumption->meters_consumed;
                    $price = (float) ($consumption->fabricRoll?->price_per_meter ?? 0);
                    return [
                        'type'      => 'Tela',
                        'reference' => $consumption->fabricRoll?->roll_number ?? '—',
                        'name'      => $consumption->fabricRoll?->item?->name ?? '—',
                        'iva'       => 0,
                        'qty'       => $qty,
                        'uom'       => 'mts',
                        'price'     => $price,
                        'total'     => round($qty * $price, 2),
                    ];
                });

            $lines = $rolls->concat($supplies)->values();

            return [
                'id'        => $order->id,
                'op_code'   => $order->op_code,
                'reference' => $order->getDisplayReference(),
                'season'    => $order->season,
                'units'     => $order->units,
                'lines'     => $lines,
                'subtotal'  => round($lines->sum('total'), 2),
            ];
        })->filter(fn ($o) => count($o['lines']) > 0)->values();

        return response()->json(['data' => $result]);
    }

    public function produccionDetalleData(Request $request)
    {
        $orderId = (int) $request->input('op_id');

        $order = ProductionOrder::with(['productSheet', 'technicalSheet'])
            ->findOrFail($orderId);
        $units = max(1, (int) ($order->units ?? 1));

        // Insumos: qty_per_unit es por prenda; total = qty_per_unit × unidades
        $supplies = TechnicalSheetSupply::where('production_order_id', $orderId)
            ->get()
            ->map(function ($supply) use ($units) {
                $qtyTotal = round((float) $supply->qty_per_unit * $units, 4);
                $price    = (float) $supply->unit_cost;
                $total    = round($qtyTotal * $price, 2);
                return [
                    'type'      => 'Insumo',
                    'reference' => $supply->uid ?? '—',
                    'name'      => $supply->name ?? $supply->requested_name ?? '—',
                    'iva'       => 0,
                    'qty'       => $qtyTotal,
                    'uom'       => $supply->uom ?? '—',
                    'price'     => $price,
                    'total'     => $total,
                ];
            });

        // Telas: consumo real registrado post-corte
        $rolls = ProductionRollConsumption::where('production_order_id', $orderId)
            ->with(['fabricRoll.item'])
            ->get()
            ->map(function ($consumption) {
                $qty   = (float) $consumption->meters_consumed;
                $price = (float) ($consumption->fabricRoll?->price_per_meter ?? 0);
                $total = round($qty * $price, 2);
                return [
                    'type'      => 'Tela',
                    'reference' => $consumption->fabricRoll?->roll_number ?? '—',
                    'name'      => $consumption->fabricRoll?->item?->name ?? '—',
                    'iva'       => 0,
                    'qty'       => $qty,
                    'uom'       => 'mts',
                    'price'     => $price,
                    'total'     => $total,
                ];
            });

        $lines = $rolls->concat($supplies)->values();

        return response()->json([
            'order' => [
                'id'                => $order->id,
                'op_code'           => $order->op_code,
                'season'            => $order->season,
                'units'             => $order->units,
                'status'            => $order->status,
                'reference'         => $order->getDisplayReference(),
                'raw_material_cost' => (float) ($order->technicalSheet?->raw_material_cost ?? 0),
                'supplies_cost'     => (float) ($order->technicalSheet?->supplies_cost ?? 0),
                'processes_cost'    => (float) ($order->technicalSheet?->processes_cost ?? 0),
                'total_cost'        => (float) ($order->technicalSheet?->total_cost ?? 0),
            ],
            'lines'   => $lines,
            'summary' => [
                'total_telas'   => round($rolls->sum('total'), 2),
                'total_insumos' => round($supplies->sum('total'), 2),
                'grand_total'   => round($lines->sum('total'), 2),
            ],
        ]);
    }

    public function inventarioData(Request $request)
    {
        $perPage = (int) $request->input('per_page', 15);

        $query = WarehouseItem::query()
            ->with(['item', 'warehouse'])
            ->select([
                'id',
                'item_id',
                'warehouse_id',
                'quantity',
                'stock_min',
                'stock_max',
                'weighted_average_price',
                'updated_at',
            ]);

        if ($search = $request->input('search')) {
            $query->whereHas('item', fn ($q) => $q->where('name', 'like', "%{$search}%"));
        }

        if ($warehouseId = $request->input('warehouse_id')) {
            $query->where('warehouse_id', $warehouseId);
        }

        $all = $request->boolean('all');
        $items = $all
            ? $query->orderByDesc('id')->get()
            : $query->orderByDesc('id')->paginate($perPage);

        $summary = [
            'total_items' => WarehouseItem::count(),
            'sin_stock'   => WarehouseItem::where('quantity', '<=', 0)->count(),
            'con_stock'   => WarehouseItem::where('quantity', '>', 0)->count(),
        ];

        return response()->json([
            'data'    => $items,
            'summary' => $summary,
        ]);
    }

    public function textilPdf(Request $request, ReportPdfService $pdf)
    {
        return $pdf->textil($request->only(['report', 'search', 'color', 'approval_status']));
    }

    public function produccionPdf(Request $request, ReportPdfService $pdf)
    {
        return $pdf->produccion($request->only(['search', 'status']));
    }

    public function inventarioPdf(Request $request, ReportPdfService $pdf)
    {
        return $pdf->inventario($request->only(['search', 'warehouse_id']));
    }

    public function financieroPdf(Request $request, ReportPdfService $pdf)
    {
        return $pdf->financiero($request->only(['search', 'date_from', 'date_to']));
    }

    public function financieroData(Request $request)
    {
        $perPage = (int) $request->input('per_page', 15);

        $query = PurchaseDocument::query()
            ->with(['contact'])
            ->select([
                'id',
                'document_number',
                'document_type',
                'contact_id',
                'status',
                'approval_status',
                'date',
                'delivery_date',
            ]);

        if ($search = $request->input('search')) {
            $query->where('document_number', 'like', "%{$search}%");
        }

        if ($from = $request->input('date_from')) {
            $query->whereDate('date', '>=', $from);
        }

        if ($to = $request->input('date_to')) {
            $query->whereDate('date', '<=', $to);
        }

        $all = $request->boolean('all');
        $docs = $all
            ? $query->orderByDesc('id')->get()
            : $query->orderByDesc('id')->paginate($perPage);

        $summary = [
            'total_docs' => PurchaseDocument::count(),
            'pendientes' => PurchaseDocument::where('status', 'pending')->count(),
            'aprobados'  => PurchaseDocument::where('approval_status', 'approved')->count(),
        ];

        return response()->json([
            'data'    => $docs,
            'summary' => $summary,
        ]);
    }
}
