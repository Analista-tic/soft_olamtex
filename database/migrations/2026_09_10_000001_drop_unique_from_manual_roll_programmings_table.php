<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un mismo rollo puede programarse más de una vez bajo el mismo código de orden
 * (por ejemplo, en dos lotes de corte distintos). El índice único anterior obligaba
 * a fusionar cualquier nueva programación con la línea ya existente para ese
 * rollo+código, lo que terminaba sobrescribiendo la cantidad programada en vez de
 * sumarla o registrarla como un evento aparte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manual_roll_programmings', function (Blueprint $table) {
            // El índice único es el que respalda la FK de fabric_roll_id ante InnoDB:
            // hay que darle un índice de reemplazo antes de poder soltarlo.
            $table->index('fabric_roll_id', 'manual_roll_programmings_fabric_roll_id_index');
            $table->dropUnique('manual_roll_programmings_roll_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('manual_roll_programmings', function (Blueprint $table) {
            $table->unique(['fabric_roll_id', 'manual_order_code'], 'manual_roll_programmings_roll_code_unique');
            $table->dropIndex('manual_roll_programmings_fabric_roll_id_index');
        });
    }
};
