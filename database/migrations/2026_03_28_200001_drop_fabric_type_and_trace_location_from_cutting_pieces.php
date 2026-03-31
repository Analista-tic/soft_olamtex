<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina los campos fabric_type y trace_location de la tabla de piezas de despiece.
 * - fabric_type: integrado directamente a través del uid de tela (fabric_uid).
 * - trace_location: campo eliminado del flujo de captura por simplificación del proceso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technical_sheet_cutting_pieces', function (Blueprint $table) {
            $table->dropColumn(['fabric_type', 'trace_location']);
        });
    }

    public function down(): void
    {
        Schema::table('technical_sheet_cutting_pieces', function (Blueprint $table) {
            $table->string('fabric_type', 30)->default('PRINCIPAL')->after('piece_type');
            $table->string('trace_location', 30)->nullable()->after('fabric_uid');
        });
    }
};
