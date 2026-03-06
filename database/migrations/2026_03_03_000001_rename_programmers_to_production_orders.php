<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Refactorización DDD: Renombra tabla `programmers` → `production_orders`.
 *
 * El concepto "Programmer" se reemplaza por "ProductionOrder" para alinear
 * el código con el dominio de negocio real.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('programmers') && ! Schema::hasTable('production_orders')) {
            Schema::rename('programmers', 'production_orders');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('production_orders') && ! Schema::hasTable('programmers')) {
            Schema::rename('production_orders', 'programmers');
        }
    }
};
