<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_sheet_operations', function (Blueprint $table) {
            // Agregar campo grupo_preparacion (solo para operaciones de tipo PREPARACION)
            $table->string('grupo_preparacion', 100)
                ->nullable()
                ->after('operation_section_id')
                ->index()
                ->comment('Grouping para operaciones PREPARACION (ej: Corte, Alistamiento, Marcación). NULL para otras secciones.');
        });
    }

    public function down(): void
    {
        Schema::table('product_sheet_operations', function (Blueprint $table) {
            $table->dropIndex('product_sheet_operations_grupo_preparacion_index');
            $table->dropColumn('grupo_preparacion');
        });
    }
};
