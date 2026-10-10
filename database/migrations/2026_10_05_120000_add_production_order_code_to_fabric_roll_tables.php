<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El "Ajuste de rollo" pedía el id interno de production_orders, que el usuario nunca ve
 * en pantalla (solo ve el op_code, ej. "MF-5005"). Una orden del lote físico puede además
 * no existir todavía como ProductionOrder en el sistema.
 *
 * production_order_code guarda el texto que el usuario escribió o eligió, siempre, sin
 * exigir que exista como ProductionOrder. production_order_id se sigue resolviendo
 * server-side solo cuando el código coincide con una orden real; queda null si no.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fabric_roll_adjustments', function (Blueprint $table) {
            $table->string('production_order_code', 50)->nullable()->after('reference');
        });

        Schema::table('production_roll_consumptions', function (Blueprint $table) {
            $table->string('production_order_code', 50)->nullable()->after('production_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('fabric_roll_adjustments', function (Blueprint $table) {
            $table->dropColumn('production_order_code');
        });

        Schema::table('production_roll_consumptions', function (Blueprint $table) {
            $table->dropColumn('production_order_code');
        });
    }
};
