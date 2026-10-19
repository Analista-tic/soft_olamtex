<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Metros de la reserva manual liberados sin consumirse: cuando un rollo se declara
 * terminado (faltante), las reservas pendientes de otras órdenes ya no tienen tela que
 * las respalde. Se liberan sin tocar lo programado, para conservar qué se pidió.
 * Reserva pendiente = programado − consumido − liberado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manual_roll_programmings', function (Blueprint $table) {
            $table->decimal('released_meters', 20, 6)->default(0)->after('consumed_meters');
        });
    }

    public function down(): void
    {
        Schema::table('manual_roll_programmings', function (Blueprint $table) {
            $table->dropColumn('released_meters');
        });
    }
};
