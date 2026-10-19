<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cantidad vigente de cada evento de descargue y vínculo de sus correcciones.
 *
 * Antes la cantidad de un descargue se derivaba de actual_output_after − actual_output_before
 * del registro original, que nunca cambiaba: una segunda corrección volvía a aplicar la
 * diferencia contra la cantidad ORIGINAL (y revertía otra vez el movimiento original).
 * `quantity` guarda la cantidad vigente del evento y `corrects_adjustment_id` enlaza cada
 * corrección con el descargue que corrige.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fabric_roll_adjustments', function (Blueprint $table) {
            $table->decimal('quantity', 12, 2)->nullable()->after('type');
            $table->foreignId('corrects_adjustment_id')
                ->nullable()
                ->after('reference')
                ->constrained('fabric_roll_adjustments', 'id', 'fra_corrects_adjustment_fk')
                ->nullOnDelete();
        });

        // Cantidad del evento para descargues ya registrados.
        DB::table('fabric_roll_adjustments')
            ->where('type', 'salida')
            ->whereNotNull('actual_output_after')
            ->whereNotNull('actual_output_before')
            ->update(['quantity' => DB::raw('actual_output_after - actual_output_before')]);

        // Correcciones de descargue manual existentes: "Corrección de descargue #ID: X m -> Y m".
        DB::table('fabric_roll_adjustments')
            ->where('type', 'correction')
            ->where('reason', 'like', 'Corrección de descargue #%')
            ->orderBy('id')
            ->get(['id', 'reason'])
            ->each(function ($correction) {
                if (! preg_match('/#(\d+):\s*[-\d.]+\s*m\s*->\s*([-\d.]+)\s*m/u', (string) $correction->reason, $m)) {
                    return;
                }

                $originalId = (int) $m[1];
                if (! DB::table('fabric_roll_adjustments')->where('id', $originalId)->exists()) {
                    return;
                }

                DB::table('fabric_roll_adjustments')
                    ->where('id', $correction->id)
                    ->update(['corrects_adjustment_id' => $originalId]);

                // Recorre en orden: la última corrección deja la cantidad vigente.
                DB::table('fabric_roll_adjustments')
                    ->where('id', $originalId)
                    ->update(['quantity' => (float) $m[2]]);
            });
    }

    public function down(): void
    {
        Schema::table('fabric_roll_adjustments', function (Blueprint $table) {
            $table->dropForeign('fra_corrects_adjustment_fk');
            $table->dropColumn('corrects_adjustment_id');
            $table->dropColumn('quantity');
        });
    }
};
