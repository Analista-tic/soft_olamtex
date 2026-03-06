<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parámetros y líneas de hilos de la ficha técnica (snapshot en BD).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technical_sheet_threads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('technical_sheet_id')->unique();
            $table->json('params')->nullable()->comment('defaultYear, method');
            $table->json('lines')->nullable()->comment('Líneas de hilos');
            $table->timestamps();

            $table->foreign('technical_sheet_id')
                ->references('id')
                ->on('technical_sheets')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_sheet_threads');
    }
};
