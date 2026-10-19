<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Origen de cada consumo de rollo por orden.
 *
 * El guardado de consumo de corte borraba TODOS los consumos de la OP para reescribirlos,
 * incluidos los registrados por un ajuste o un descargue manual. Con `source` cada flujo
 * solo reemplaza lo suyo, y `fabric_roll_adjustment_id` permite corregir el consumo del
 * descargue exacto que se corrige.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_roll_consumptions', function (Blueprint $table) {
            // cut | manual | adjustment (null = registro anterior a esta migración)
            $table->string('source', 20)->nullable()->after('meters_consumed');
            $table->foreignId('fabric_roll_adjustment_id')
                ->nullable()
                ->after('source')
                ->constrained('fabric_roll_adjustments', 'id', 'prc_adjustment_fk')
                ->nullOnDelete();

            $table->index(['production_order_id', 'source'], 'prc_order_source_idx');
        });

        // Backfill: los consumos de rollos que aparecen en el registro de corte de su OP
        // los escribió el corte.
        $orders = DB::table('production_orders')
            ->whereNotNull('cut_fabric_consumption')
            ->get(['id', 'cut_fabric_consumption']);

        foreach ($orders as $order) {
            $payload = json_decode((string) $order->cut_fabric_consumption, true);
            $rollCodes = collect(is_array($payload['lines'] ?? null) ? $payload['lines'] : [])
                ->map(fn ($line) => is_array($line) ? trim((string) ($line['roll_code'] ?? '')) : '')
                ->filter()
                ->unique()
                ->values()
                ->all();

            if ($rollCodes === []) {
                continue;
            }

            $rollIds = DB::table('fabric_rolls')->whereIn('roll_number', $rollCodes)->pluck('id')->all();

            DB::table('production_roll_consumptions')
                ->where('production_order_id', $order->id)
                ->whereIn('fabric_roll_id', $rollIds)
                ->whereNull('source')
                ->update(['source' => 'cut']);
        }
    }

    public function down(): void
    {
        Schema::table('production_roll_consumptions', function (Blueprint $table) {
            $table->dropIndex('prc_order_source_idx');
            $table->dropForeign('prc_adjustment_fk');
            $table->dropColumn(['fabric_roll_adjustment_id', 'source']);
        });
    }
};
