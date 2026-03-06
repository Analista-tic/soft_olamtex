<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Procesos de la Ficha Técnica con costo fijo (snapshot).
 *
 * Almacena los procesos (CORTE, CONFECCION, TERMINACION, ESTAMPADO, etc.)
 * vinculados a una TechnicalSheet, con el costo congelado al momento de guardar,
 * evitando que cambios en parámetros o listado operacional alteren costos históricos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technical_sheet_processes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('technical_sheet_id')->comment('FK a technical_sheets');
            $table->unsignedTinyInteger('sequence')->comment('Orden de visualización');
            $table->string('type', 50)->comment('CORTE|CONFECCION|TERMINACION|ESTAMPADO|BORDADO|LAVANDERIA|OTRO');
            $table->string('supplier', 150)->nullable()->comment('Proveedor (ej. OLAMTEX para fijos)');
            $table->decimal('manual_price', 12, 4)->nullable()->comment('Precio manual para tipos variables');
            $table->decimal('fixed_cost', 12, 4)->default(0)->comment('Costo fijo congelado al guardar');
            $table->timestamps();

            $table->foreign('technical_sheet_id')
                ->references('id')
                ->on('technical_sheets')
                ->cascadeOnDelete();

            $table->index(['technical_sheet_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_sheet_processes');
    }
};
