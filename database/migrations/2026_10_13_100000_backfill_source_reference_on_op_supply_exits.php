<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Las salidas de insumos a OP (Control de Lotes > Salida Logística) guardaban la OP
 * solo dentro de `notes`. Se copia el op_code a `source_reference` para poder
 * filtrarlas y exportarlas por OP en Movimientos de inventario.
 * Solo toca filas sin referencia; el consumo de rollos (misma OP como origen) ya
 * usa `source_reference` para el número de rollo y no se modifica.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('inventory_movements')
            ->where('source_type', 'App\\Models\\Production\\ProductionOrder')
            ->where('type', 'salida')
            ->where('notes', 'like', 'Salida Logística OP %')
            ->whereNull('source_reference')
            ->update([
                'source_reference' => DB::raw(
                    '(select production_orders.op_code from production_orders where production_orders.id = inventory_movements.source_id)'
                ),
            ]);
    }

    public function down(): void
    {
        // Relleno de datos: no se revierte (la referencia de OP es correcta y no rompe nada).
    }
};
