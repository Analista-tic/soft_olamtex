<?php

namespace App\Services\Inventory;

use App\Models\Inventory\InventoryMovement;
use App\Models\Production\ProductionOrder;
use App\Models\Settings\User;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Excel de movimientos de inventario con los mismos filtros de la pantalla.
 * Formato simple para cargar las salidas en el sistema anterior: incluye el
 * código de referencia del ítem, su código legacy y la OP relacionada.
 */
class InventoryMovementExcelService
{
    public const HEADERS = [
        'Fecha',
        'Documento',
        'OP',
        'Código referencia',
        'Código legacy',
        'Ítem',
        'Bodega',
        'Tipo',
        'Cantidad',
        'Costo unitario',
        'Costo total',
        'Usuario',
        'Notas',
    ];

    public function __construct(protected InventoryMovementService $movements) {}

    /** Genera el xlsx en un archivo temporal y devuelve su ruta. */
    public function export(array $filters, ?User $user): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Movimientos');
        $sheet->fromArray(self::HEADERS, null, 'A1');
        $sheet->getStyle('A1:M1')->getFont()->setBold(true);

        $line = 2;
        foreach ($this->movements->buildFilteredQuery($filters, $user)->lazy(500) as $movement) {
            $sheet->fromArray($this->row($movement), null, "A{$line}");
            // Los códigos se escriben como texto para no perder ceros a la izquierda.
            $sheet->setCellValueExplicit("D{$line}", (string) ($movement->item?->reference ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("E{$line}", (string) ($movement->item?->legacy_code ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $line++;
        }

        foreach (range('A', 'M') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $lastRow = $line - 1;
        if ($lastRow >= 2) {
            $sheet->getStyle("J2:K{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'inv_movs_').'.xlsx';
        (new Xlsx($spreadsheet))->save($tempFile);

        return $tempFile;
    }

    /** @return array<int, mixed> */
    private function row(InventoryMovement $movement): array
    {
        $quantity = (float) $movement->quantity;
        $unitCost = $movement->unit_price !== null
            ? (float) $movement->unit_price
            : (float) ($movement->previous_weighted_price ?? 0);

        return [
            $movement->date ? \Illuminate\Support\Carbon::parse($movement->date)->format('Y-m-d') : '',
            $movement->display_document_number ?? $movement->document_number,
            self::opCode($movement),
            '',
            '',
            $movement->item?->name ?? '',
            $movement->warehouse?->name ?? '',
            $movement->type,
            $quantity,
            round($unitCost, 2),
            round($movement->total_cost !== null ? (float) $movement->total_cost : $quantity * $unitCost, 2),
            $movement->createdBy?->name ?? '',
            $movement->notes ?? '',
        ];
    }

    /** OP del movimiento: referencia guardada o, si no, la orden de producción de origen. */
    public static function opCode(InventoryMovement $movement): string
    {
        if ($movement->source_type === ProductionOrder::class) {
            return (string) ($movement->source?->op_code ?? $movement->source_reference ?? '');
        }

        return '';
    }
}
