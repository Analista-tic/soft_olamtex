<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cambiar el enum de type en operation_sections de ('confeccion', 'presentacion')
        // a ('PREPARACION', 'ENSAMBLE', 'TERMINACION')
        
        // Estrategia MySQL: cambiar enum directamente manteniendo orden
        Schema::table('operation_sections', function (Blueprint $table) {
            $table->enum('type', ['PREPARACION', 'ENSAMBLE', 'TERMINACION'])
                ->change()
                ->comment('Tipo de sección: PREPARACION, ENSAMBLE, TERMINACION');
        });
    }

    public function down(): void
    {
        // Volver al enum anterior para mantener compatibilidad de rollback
        Schema::table('operation_sections', function (Blueprint $table) {
            $table->enum('type', ['confeccion', 'presentacion'])
                ->change()
                ->comment('Tipo de sección: confeccion o presentacion');
        });
    }
};
