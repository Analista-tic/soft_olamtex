<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `quantity` deja de cambiar por descargar. El sobrante (+) y el faltante (−) de metraje
 * detectados al descargar pasan a `length_adjustment_meters` (neto de reversos):
 *
 *   metraje vigente = quantity + length_adjustment_meters
 *
 * Backfill: neto por rollo = Σ sobrante − Σ faltante de fabric_roll_length_differences
 * (los reversos ya vienen con el tipo opuesto), que es exactamente lo que el código
 * anterior sumaba/restaba a `quantity`. Se mueve ese neto de quantity al ajuste.
 * Se usa DB::table para no disparar el observer ni la sincronización de received_quantity.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('fabric_rolls', 'length_adjustment_meters')) {
            return; // idempotente: el backfill ya se aplicó
        }

        Schema::table('fabric_rolls', function (Blueprint $table) {
            $table->decimal('length_adjustment_meters', 10, 2)->default(0)->after('received_quantity');
        });

        $nets = DB::table('fabric_roll_length_differences')
            ->selectRaw("fabric_roll_id, SUM(CASE WHEN type = 'sobrante' THEN meters ELSE -meters END) AS net")
            ->groupBy('fabric_roll_id')
            ->get();

        foreach ($nets as $row) {
            $net = round((float) $row->net, 2);
            if (abs($net) < 0.005) {
                continue;
            }

            DB::table('fabric_rolls')
                ->where('id', $row->fabric_roll_id)
                ->update([
                    'length_adjustment_meters' => $net,
                    'quantity' => DB::raw('quantity - '.number_format($net, 2, '.', '')),
                ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('fabric_rolls', 'length_adjustment_meters')) {
            return;
        }

        // Devuelve el ajuste a quantity (comportamiento anterior) antes de quitar la columna.
        DB::table('fabric_rolls')
            ->where('length_adjustment_meters', '!=', 0)
            ->update(['quantity' => DB::raw('quantity + length_adjustment_meters')]);

        Schema::table('fabric_rolls', function (Blueprint $table) {
            $table->dropColumn('length_adjustment_meters');
        });
    }
};
