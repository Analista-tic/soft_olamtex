<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_sections', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Nombre de la sección: DELANTERO, TRASERO, ENSAMBLE, TERMINADO');
            $table->enum('type', ['confeccion', 'presentacion'])->comment('Tipo de sección: confeccion o presentacion');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['name', 'is_active']);
            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_sections');
    }
};
