<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Metraje recibido inmutable y estado de revisión de diferencias de metraje.
 *
 * `quantity` pasa a ser el metraje vigente del rollo (recibido + sobrantes − faltantes
 * + ajustes). `received_quantity` conserva lo que llegó y nunca cambia después de
 * aprobada la recepción, para no perder la trazabilidad contra la factura del proveedor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fabric_rolls', function (Blueprint $table) {
            $table->decimal('received_quantity', 10, 2)->nullable()->after('quantity');
            // none | pending | reviewed
            $table->string('length_review_status', 20)->default('none')->after('tolerance_status');

            $table->index('length_review_status', 'fabric_rolls_length_review_status_idx');
        });

        // Backfill: lo recibido es la cantidad anterior al primer ajuste registrado del
        // rollo; sin ajustes, la cantidad actual nunca cambió.
        DB::table('fabric_rolls')->orderBy('id')->chunkById(500, function ($rolls) {
            foreach ($rolls as $roll) {
                $firstBefore = DB::table('fabric_roll_adjustments')
                    ->where('fabric_roll_id', $roll->id)
                    ->orderBy('id')
                    ->value('quantity_before');

                DB::table('fabric_rolls')
                    ->where('id', $roll->id)
                    ->update(['received_quantity' => $firstBefore ?? $roll->quantity]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('fabric_rolls', function (Blueprint $table) {
            $table->dropIndex('fabric_rolls_length_review_status_idx');
            $table->dropColumn(['received_quantity', 'length_review_status']);
        });
    }
};
