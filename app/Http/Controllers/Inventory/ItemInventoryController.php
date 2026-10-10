<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Services\Inventory\ItemInventoryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ItemInventoryController extends Controller
{
    public function __construct(protected ItemInventoryService $service) {}

    public function index()
    {
        return Inertia::render('Inventory/ItemInventory/Index');
    }

    public function getData(Request $request)
    {
        $filters = $request->only(['search', 'warehouse_id', 'status', 'sort_by', 'sort_dir']);
        $perPage = (int) $request->input('per_page', 10);

        $data = $this->service->paginate($filters, $perPage);

        return response()->json($data);
    }

    /** Excel con lo mismo que muestra la pantalla (mismos filtros y orden), sin paginar. */
    public function exportExcel(Request $request)
    {
        $filters = $request->only(['search', 'warehouse_id', 'status', 'sort_by', 'sort_dir']);
        $rows = $this->service->all($filters);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Inventario de items');
        $sheet->fromArray(['Ítem', 'Referencia', 'Bodega', 'Cantidad', 'Restringido', 'Estado', 'Costo promedio', 'Costo total'], null, 'A1');
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);

        $line = 2;
        foreach ($rows as $row) {
            $sheet->fromArray([
                $row->item_name,
                $row->reference,
                $row->warehouse_name,
                (float) $row->quantity,
                (float) $row->restricted_quantity,
                $row->status ? 'Habilitado' : 'Deshabilitado',
                round((float) $row->average_cost, 2),
                round((float) $row->total_cost, 2),
            ], null, "A{$line}");
            $line++;
        }

        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $sheet->getStyle("G2:H{$line}")->getNumberFormat()->setFormatCode('#,##0.00');

        $tempFile = tempnam(sys_get_temp_dir(), 'inv_items_').'.xlsx';
        (new Xlsx($spreadsheet))->save($tempFile);

        return response()->download($tempFile, 'inventario_items_'.now()->format('Ymd_His').'.xlsx')->deleteFileAfterSend(true);
    }
}
