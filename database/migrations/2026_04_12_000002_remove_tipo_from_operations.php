<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina el campo `tipo` de la tabla operations.
 * El tipo de prenda ya no vive en la operación (catálogo maestro reutilizable).
 * Ahora vive en product_sheets.garment_type, que es el contexto correcto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operations', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }

    public function down(): void
    {
        Schema::table('operations', function (Blueprint $table) {
            $table->string('tipo')->nullable()->after('garment_part')
                ->comment('Tipo de prenda (eliminado — use product_sheets.garment_type)');
        });
    }
};
