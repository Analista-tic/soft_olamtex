<?php

namespace App\Console\Commands;

use App\Models\MasterData\Item;
use App\Services\MasterData\ItemService;
use Illuminate\Console\Command;

/**
 * Solo lectura: compara el `name` guardado de cada ítem contra el que produciría
 * ItemService::buildName() ahora que incorpora el segmento de material (Fase 4 del
 * refactor de materiales). Ningún ítem cambia de nombre solo por esto — el nombre
 * real se recalcula recién cuando el ítem se guarda de nuevo (alta manual o carga
 * masiva). Este comando es la revisión previa a desplegar ese cambio.
 */
class PreviewItemNameChangesCommand extends Command
{
    protected $signature = 'items:preview-name-changes';

    protected $description = 'Compara el nombre guardado de cada ítem contra el que calcularía buildName() con la regla actual';

    public function handle(ItemService $itemService): int
    {
        $items = Item::with(['fabricName.fabricBase', 'yarnName.yarnBase'])
            ->whereNotNull('name')
            ->get(['id', 'name', 'category_sub_group_id', 'extra_fields', 'color_id', 'unit_measure', 'fabric_name_id', 'yarn_name_id']);

        $changed = [];

        foreach ($items as $item) {
            $extraFields = is_array($item->extra_fields) ? $item->extra_fields : [];
            $computedName = $itemService->computeName(
                $item->category_sub_group_id,
                $extraFields,
                $item->color_id,
                $item->unit_measure,
                $item->fabric_name_id,
                $item->yarn_name_id,
            );

            if ($computedName !== $item->name) {
                $changed[] = [$item->id, $item->name, $computedName];
            }
        }

        $this->info("Ítems revisados: {$items->count()}");

        if (empty($changed)) {
            $this->info('Ningún ítem cambiaría de nombre. Nada que revisar.');

            return self::SUCCESS;
        }

        $this->warn(count($changed).' ítem(s) cambiarían de nombre la próxima vez que se guarden:');
        $this->table(['item_id', 'nombre actual', 'nombre calculado'], $changed);
        $this->line('');
        $this->line('Ningún ítem se modifica al correr este comando — es de solo lectura.');

        return self::SUCCESS;
    }
}
