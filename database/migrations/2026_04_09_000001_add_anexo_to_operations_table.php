<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operations', function (Blueprint $table) {
            $table->string('anexo', 255)->nullable()
                ->after('garment_part')
                ->comment('Campo libre que complementa el nombre de la operación (ej: FRENTE DERECHO, INTERIOR, etc.)');
        });
    }

    public function down(): void
    {
        Schema::table('operations', function (Blueprint $table) {
            $table->dropColumn('anexo');
        });
    }
};
