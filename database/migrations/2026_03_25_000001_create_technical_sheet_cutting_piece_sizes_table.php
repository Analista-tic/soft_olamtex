<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla para desglose de unidades por talla y curva de cada pieza de despiece de corte.
 * Permite trazabilidad y auditoría de cantidades por talla en cada pieza.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technical_sheet_cutting_piece_sizes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cutting_piece_id');
            $table->foreign('cutting_piece_id')
                ->references('id')
                ->on('technical_sheet_cutting_pieces')
                ->cascadeOnDelete();
            $table->string('size', 30); // Talla (ej: S, M, L, 8, 10)
            $table->unsignedSmallInteger('units')->default(0); // Unidades para esta talla
            $table->string('curve', 30)->nullable(); // Curva (si aplica)
            $table->timestamps();
            $table->unique(['cutting_piece_id', 'size', 'curve'], 'uniq_piece_size_curve');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_sheet_cutting_piece_sizes');
    }
};
