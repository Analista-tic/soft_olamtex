<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega garment_type a product_sheets.
 * Este campo centraliza el tipo de prenda (superior/inferior) en el contexto
 * donde tiene sentido: la sábana de producto, no la operación individual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_sheets', function (Blueprint $table) {
            $table->enum('garment_type', ['superior', 'inferior'])
                ->nullable()
                ->after('type')
                ->comment('Tipo de prenda: superior (camisetas, chaquetas, etc.) o inferior (pantalones, shorts, faldas)');
        });
    }

    public function down(): void
    {
        Schema::table('product_sheets', function (Blueprint $table) {
            $table->dropColumn('garment_type');
        });
    }
};
