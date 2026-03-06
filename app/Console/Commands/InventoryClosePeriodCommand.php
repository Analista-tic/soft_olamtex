<?php

namespace App\Console\Commands;

use App\Models\Inventory\InventoryPeriodLock;
use Illuminate\Console\Command;

class InventoryClosePeriodCommand extends Command
{
    protected $signature = 'inventory:close-period {year : Año (ej. 2026)} {month : Mes (1-12)}';

    protected $description = 'Cierra un período de inventario para impedir nuevos movimientos';

    public function handle(): int
    {
        $year = (int) $this->argument('year');
        $month = (int) $this->argument('month');

        if ($month < 1 || $month > 12) {
            $this->error('El mes debe estar entre 1 y 12.');
            return 1;
        }

        if (InventoryPeriodLock::isPeriodClosed($year, $month)) {
            $this->warn("El período {$year}-" . str_pad((string) $month, 2, '0', STR_PAD_LEFT) . ' ya está cerrado.');
            return 0;
        }

        InventoryPeriodLock::create([
            'year' => $year,
            'month' => $month,
            'closed_at' => now(),
        ]);

        $this->info("Período {$year}-" . str_pad((string) $month, 2, '0', STR_PAD_LEFT) . ' cerrado correctamente.');
        return 0;
    }
}
