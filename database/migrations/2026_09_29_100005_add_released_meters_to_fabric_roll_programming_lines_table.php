<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Metros de la reserva de ficha técnica ya liberados por el descargue de corte.
 *
 * El corte libera la reserva de la OP en su primer descargue, pero la línea seguía
 * mostrando el total programado: ese total se volvía a sumar al recalcular partial_output
 * (reset de ficha, recalibración) y al calcular el disponible del reporte, contando dos
 * veces metros que ya estaban en actual_output. Reserva pendiente = programado − liberado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fabric_roll_programming_lines', function (Blueprint $table) {
            $table->decimal('released_meters', 12, 2)->default(0)->after('programmed_meters');
        });

        // Backfill: órdenes que ya registraron consumo de corte liberaron la reserva de
        // cada rollo descargado.
        $orders = DB::table('production_orders')
            ->whereNotNull('cut_fabric_consumption')
            ->get(['id', 'cut_fabric_consumption']);

        foreach ($orders as $order) {
            $payload = json_decode((string) $order->cut_fabric_consumption, true);
            $lines = is_array($payload['lines'] ?? null) ? $payload['lines'] : [];

            $rollCodes = collect($lines)
                ->filter(fn ($line) => is_array($line) && (float) ($line['total_consumption'] ?? 0) > 0)
                ->map(fn ($line) => trim((string) ($line['roll_code'] ?? '')))
                ->filter()
                ->unique()
                ->values()
                ->all();

            if ($rollCodes === []) {
                continue;
            }

            $rollIds = DB::table('fabric_rolls')->whereIn('roll_number', $rollCodes)->pluck('id')->all();
            $sheetIds = DB::table('technical_sheets')->where('production_order_id', $order->id)->pluck('id')->all();

            if ($rollIds === [] || $sheetIds === []) {
                continue;
            }

            DB::table('fabric_roll_programming_lines')
                ->whereIn('technical_sheet_id', $sheetIds)
                ->whereIn('fabric_roll_id', $rollIds)
                ->where('programmed_meters', '>', 0)
                ->update(['released_meters' => DB::raw('programmed_meters')]);
        }
    }

    public function down(): void
    {
        Schema::table('fabric_roll_programming_lines', function (Blueprint $table) {
            $table->dropColumn('released_meters');
        });
    }
};
