<?php

namespace App\Services\Reports;

use App\Models\Expenses\PurchaseDocument;
use App\Models\Inventory\WarehouseItem;
use App\Models\Production\ProductionOrder;
use App\Models\Textil\FabricRoll;
use Barryvdh\DomPDF\Facade\Pdf as DomPDF;
use Illuminate\Http\Response;

class ReportPdfService
{
    private const PDF_LIMIT = 300;

    public function textil(array $filters): Response
    {
        $query = FabricRoll::query()->with(['item'])->select([
            'id', 'roll_number', 'item_id', 'internal_color_name',
            'quantity', 'actual_output', 'approval_status', 'entry_date', 'batch', 'location', 'updated_at',
        ]);

        $report = $filters['report'] ?? 'disponibilidad';

        if ($report === 'sin_movimiento') {
            $query->whereRaw('(quantity - actual_output) > 0')->where('updated_at', '<', now()->subDays(30));
        } elseif ($report === 'agotados') {
            $query->whereRaw('(quantity - actual_output) <= 0');
        }

        if (!empty($filters['search'])) {
            $query->where('roll_number', 'like', '%' . $filters['search'] . '%');
        }
        if (!empty($filters['color'])) {
            $query->where('internal_color_name', 'like', '%' . $filters['color'] . '%');
        }
        if (!empty($filters['approval_status'])) {
            $query->where('approval_status', $filters['approval_status']);
        }

        $rolls = $query->orderByDesc('id')->limit(self::PDF_LIMIT)->get();

        $totalMeters = (float) FabricRoll::selectRaw('SUM(quantity - actual_output) as total')
            ->whereRaw('(quantity - actual_output) > 0')->value('total');

        $reportLabels = [
            'disponibilidad' => 'Disponibilidad de Rollos',
            'sin_movimiento' => 'Rollos Sin Movimiento (+30 días)',
            'agotados'       => 'Rollos Agotados',
        ];

        $approvalLabels = [
            'approved'         => 'Aprobado',
            'pending_approval' => 'Pendiente',
            'not_approved'     => 'No aprobado',
        ];

        $data = [
            'title'          => 'Informe Textil',
            'subtitle'       => $reportLabels[$report] ?? 'Disponibilidad',
            'printDate'      => now()->format('d/m/Y H:i'),
            'rolls'          => $rolls,
            'totalMeters'    => round($totalMeters, 2),
            'approvalLabels' => $approvalLabels,
        ];

        $pdf = DomPDF::loadView('pdf.reports.textil', $data);
        $pdf->setPaper('letter', 'landscape');

        return $pdf->stream('informe_textil_' . now()->format('Ymd_His') . '.pdf');
    }

    public function produccion(array $filters): Response
    {
        $query = ProductionOrder::query()->with(['productSheet'])->select([
            'id', 'op_code', 'product_sheet_id', 'status',
            'current_process', 'season', 'units', 'estimated_delivery_date', 'created_at',
        ]);

        if (!empty($filters['search'])) {
            $query->where('op_code', 'like', '%' . $filters['search'] . '%');
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $orders = $query->orderByDesc('id')->get();

        $summary = [
            'total'       => $orders->count(),
            'activas'     => $orders->where('status', 'active')->count(),
            'completadas' => $orders->where('status', 'completed')->count(),
        ];

        $statusLabels = [
            'active'    => 'Activa',
            'completed' => 'Completada',
            'cancelled' => 'Cancelada',
        ];

        $data = [
            'title'        => 'Informe Producción',
            'subtitle'     => 'Estado de Órdenes',
            'printDate'    => now()->format('d/m/Y H:i'),
            'orders'       => $orders,
            'summary'      => $summary,
            'statusLabels' => $statusLabels,
        ];

        $pdf = DomPDF::loadView('pdf.reports.produccion', $data);
        $pdf->setPaper('letter', 'portrait');

        return $pdf->stream('informe_produccion_' . now()->format('Ymd_His') . '.pdf');
    }

    public function inventario(array $filters): Response
    {
        $query = WarehouseItem::query()->with(['item', 'warehouse'])->select([
            'id', 'item_id', 'warehouse_id', 'quantity',
            'stock_min', 'stock_max', 'weighted_average_price', 'updated_at',
        ]);

        if (!empty($filters['search'])) {
            $query->whereHas('item', fn ($q) => $q->where('name', 'like', '%' . $filters['search'] . '%'));
        }
        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        $items = $query->orderByDesc('id')->get();

        $summary = [
            'total'     => $items->count(),
            'con_stock' => $items->where('quantity', '>', 0)->count(),
            'sin_stock' => $items->where('quantity', '<=', 0)->count(),
        ];

        $data = [
            'title'     => 'Informe Inventario',
            'subtitle'  => 'Stock por Bodega',
            'printDate' => now()->format('d/m/Y H:i'),
            'items'     => $items,
            'summary'   => $summary,
        ];

        $pdf = DomPDF::loadView('pdf.reports.inventario', $data);
        $pdf->setPaper('letter', 'portrait');

        return $pdf->stream('informe_inventario_' . now()->format('Ymd_His') . '.pdf');
    }

    public function financiero(array $filters): Response
    {
        $query = PurchaseDocument::query()->with(['contact'])->select([
            'id', 'document_number', 'document_type', 'contact_id',
            'status', 'approval_status', 'date', 'delivery_date',
        ]);

        if (!empty($filters['search'])) {
            $query->where('document_number', 'like', '%' . $filters['search'] . '%');
        }
        if (!empty($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }

        $docs = $query->orderByDesc('id')->get();

        $summary = [
            'total'      => $docs->count(),
            'pendientes' => $docs->where('status', 'pending')->count(),
            'aprobados'  => $docs->where('approval_status', 'approved')->count(),
        ];

        $statusLabels = [
            'pending'   => 'Pendiente',
            'approved'  => 'Aprobado',
            'rejected'  => 'Rechazado',
            'completed' => 'Completado',
        ];

        $docTypeLabels = [
            'purchase_invoice'      => 'Factura compra',
            'purchase_invoice_tela' => 'Factura tela',
            'credit_note'           => 'Nota crédito',
            'debit_note'            => 'Nota débito',
        ];

        $data = [
            'title'         => 'Informe Financiero',
            'subtitle'      => 'Documentos de Compra',
            'printDate'     => now()->format('d/m/Y H:i'),
            'docs'          => $docs,
            'summary'       => $summary,
            'statusLabels'  => $statusLabels,
            'docTypeLabels' => $docTypeLabels,
            'dateFrom'      => $filters['date_from'] ?? null,
            'dateTo'        => $filters['date_to'] ?? null,
        ];

        $pdf = DomPDF::loadView('pdf.reports.financiero', $data);
        $pdf->setPaper('letter', 'portrait');

        return $pdf->stream('informe_financiero_' . now()->format('Ymd_His') . '.pdf');
    }
}
