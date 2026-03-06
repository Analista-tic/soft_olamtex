<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Líneas de materia prima de la ficha técnica (snapshot en BD).
 * Permite API para reemplazar localStorage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technical_sheet_raw_materials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('technical_sheet_id')->unique();
            $table->json('rows')->nullable()->comment('Filas de materia prima (tela, ancho, consumo, etc.)');
            $table->timestamps();

            $table->foreign('technical_sheet_id')
                ->references('id')
                ->on('technical_sheets')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_sheet_raw_materials');
    }
};
