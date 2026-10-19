<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * El SAM de la operación en los listados pasa a incluir las repeticiones por pieza (`operations.quantity`).
     * Se recalcula solo en los listados de sábanas; las copias por orden de producción (technical_sheet_operations)
     * conservan lo que tenían cuando se crearon.
     */
    public function up(): void
    {
        DB::statement(
            'UPDATE product_sheet_operations pso
             JOIN operations o ON o.id = pso.operation_id
             SET pso.sam_total_operacion = ROUND(COALESCE(pso.sam_total_unitario, 0) * GREATEST(COALESCE(o.quantity, 1), 1), 4)
             WHERE pso.sam_total_unitario IS NOT NULL'
        );
    }

    public function down(): void
    {
        DB::statement(
            'UPDATE product_sheet_operations SET sam_total_operacion = sam_total_unitario WHERE sam_total_unitario IS NOT NULL'
        );
    }
};
