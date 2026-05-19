<?php

namespace App\Console\Commands;

use App\Models\Inventory\WarehouseItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class InventorySeedOpeningBalanceCostsCommand extends Command
{
    protected $signature = 'inventory:seed-opening-balance-costs
        {--export= : Ruta destino CSV de plantilla}
        {--apply-from= : Ruta CSV para cargar costos}
        {--item_id= : Filtrar por item_id}
        {--warehouse_id= : Filtrar por warehouse_id}
        {--source_reference= : Referencia de trazabilidad para la carga}';

    protected $description = 'Exporta y/o carga costos base para saldos iniciales pendientes antes del saneamiento de PP';

    public function handle(): int
    {
        $exportPath = $this->option('export');
        $applyFrom = $this->option('apply-from');

        if (!$exportPath && !$applyFrom) {
            $exportPath = storage_path('app/inventory/opening-balance-costs-template-' . now()->format('Ymd-His') . '.csv');
        }

        if ($exportPath) {
            $this->exportTemplate((string) $exportPath);
        }

        if ($applyFrom) {
            $this->applyFromCsv((string) $applyFrom);
        }

        if (!$applyFrom) {
            $this->line('Siguiente paso: completa unit_price en el CSV y ejecuta:');
            $this->line('php artisan inventory:seed-opening-balance-costs --apply-from="RUTA_ARCHIVO.csv"');
        }

        return self::SUCCESS;
    }

    private function exportTemplate(string $path): void
    {
        $rows = $this->candidateQuery()->orderBy('id')->get();

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $handle = fopen($path, 'w');
        if ($handle === false) {
            $this->error("No se pudo crear el archivo: {$path}");
            return;
        }

        fputcsv($handle, [
            'warehouse_item_id',
            'item_id',
            'item_name',
            'warehouse_id',
            'warehouse_name',
            'quantity',
            'unit_price',
            'current_last_unit_cost',
            'notes',
        ]);

        foreach ($rows as $row) {
            fputcsv($handle, [
                (int) $row->id,
                (int) $row->item_id,
                (string) ($row->item?->name ?? ''),
                (int) $row->warehouse_id,
                (string) ($row->warehouse?->name ?? ''),
                (float) $row->quantity,
                '',
                $row->last_unit_cost !== null ? (float) $row->last_unit_cost : '',
                'Completar unit_price > 0',
            ]);
        }

        fclose($handle);

        $this->info('Plantilla exportada: ' . $path);
        $this->line('Registros exportados: ' . $rows->count());
    }

    private function applyFromCsv(string $path): void
    {
        if (!is_file($path)) {
            $this->error("No existe el archivo CSV: {$path}");
            return;
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            $this->error("No se pudo abrir el archivo CSV: {$path}");
            return;
        }

        $header = fgetcsv($handle);
        if (!is_array($header)) {
            fclose($handle);
            $this->error('CSV inválido: no tiene encabezados.');
            return;
        }

        $indexMap = $this->buildHeaderMap($header);
        if (!isset($indexMap['warehouse_item_id'], $indexMap['unit_price'])) {
            fclose($handle);
            $this->error('CSV inválido: requiere columnas warehouse_item_id y unit_price.');
            return;
        }

        $sourceReference = (string) ($this->option('source_reference') ?: ('opening-balance-cost-seed-' . now()->format('Ymd-His')));

        $updated = 0;
        $skipped = 0;
        $failed = 0;
        $lineNo = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $lineNo++;
            $warehouseItemId = (int) ($row[$indexMap['warehouse_item_id']] ?? 0);
            $unitPriceRaw = $row[$indexMap['unit_price']] ?? null;
            $unitPrice = is_numeric($unitPriceRaw) ? (float) $unitPriceRaw : 0.0;

            if ($warehouseItemId <= 0 || $unitPrice <= 0) {
                $skipped++;
                continue;
            }

            /** @var WarehouseItem|null $warehouseItem */
            $warehouseItem = $this->candidateQuery()->where('id', $warehouseItemId)->first();
            if (!$warehouseItem) {
                $skipped++;
                continue;
            }

            try {
                $warehouseItem->update([
                    'last_unit_cost' => $unitPrice,
                    'source_type' => 'opening_balance_cost_seed',
                    'source_reference' => $sourceReference,
                ]);
                $updated++;
            } catch (\Throwable $e) {
                $failed++;
                $this->error("Línea {$lineNo}: {$e->getMessage()}");
            }
        }

        fclose($handle);

        $this->newLine();
        $this->info('Resumen carga de costos base:');
        $this->line("- Actualizados: {$updated}");
        $this->line("- Omitidos: {$skipped}");
        $this->line("- Fallidos: {$failed}");

        $this->newLine();
        $this->line('Siguiente paso:');
        $this->line('1) php artisan inventory:repair-opening-balances');
        $this->line('2) php artisan inventory:repair-opening-balances --apply');
    }

    private function buildHeaderMap(array $header): array
    {
        $map = [];
        foreach ($header as $index => $column) {
            $normalized = strtolower(trim((string) $column));
            if ($normalized !== '') {
                $map[$normalized] = $index;
            }
        }

        return $map;
    }

    private function candidateQuery()
    {
        $query = WarehouseItem::query()
            ->with(['item:id,name', 'warehouse:id,name'])
            ->where('quantity', '>', 0)
            ->where(function ($q) {
                $q->whereNull('weighted_average_price')
                    ->orWhere('weighted_average_price', '<=', 0);
            });

        $itemId = $this->option('item_id');
        if ($itemId !== null && $itemId !== '') {
            $query->where('item_id', (int) $itemId);
        }

        $warehouseId = $this->option('warehouse_id');
        if ($warehouseId !== null && $warehouseId !== '') {
            $query->where('warehouse_id', (int) $warehouseId);
        }

        $query->whereNotExists(function ($sub) {
            $sub->selectRaw('1')
                ->from('inventory_movements as im')
                ->whereColumn('im.item_id', 'warehouse_items.item_id')
                ->whereColumn('im.warehouse_id', 'warehouse_items.warehouse_id');
        });

        if (Schema::hasColumn('warehouse_items', 'last_unit_cost')) {
            $query->where(function ($q) {
                $q->whereNull('last_unit_cost')
                    ->orWhere('last_unit_cost', '<=', 0);
            });
        }

        return $query;
    }
}
