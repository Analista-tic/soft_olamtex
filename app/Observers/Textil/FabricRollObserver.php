<?php

namespace App\Observers\Textil;

use App\Models\Textil\FabricRoll;

class FabricRollObserver
{
    public function saved(FabricRoll $roll): void
    {
        // El stock resumido por item+bodega se mantiene exclusivamente
        // desde inventory_movements para conservar el histórico trazable.
    }

    public function deleted(FabricRoll $roll): void
    {
        // El stock resumido por item+bodega se mantiene exclusivamente
        // desde inventory_movements para conservar el histórico trazable.
    }
}
