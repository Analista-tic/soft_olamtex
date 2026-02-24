<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Proceso actual en el que se encuentra la orden de producción para Control de lotes.
     * Valores posibles: I&D, Logística, Desarrollo de muestras, Muestras, Costos,
     * Confección, Estampado, Bordado, Sublimado, Corte, Tejido, Lavandería, Bodega.
     */
    public function up(): void
    {
        Schema::table('programmers', function (Blueprint $table) {
            $table->string('current_process', 80)->nullable()->after('status')
                ->comment('Proceso actual para Control de lotes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('programmers', function (Blueprint $table) {
            $table->dropColumn('current_process');
        });
    }
};
