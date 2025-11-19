<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('fabric_rolls', function (Blueprint $table) {
            $table->id();
            $table->string('roll_number')->unique(); // # de rollo
            $table->foreignId('purchase_invoice_item_id')->constrained();
            $table->decimal('quantity', 10, 2); // Cantidad del rollo
            $table->decimal('roll_width', 8, 2)->nullable(); // Ancho del rollo
            $table->string('location')->nullable(); // Ubicación en bodega
            $table->text('description')->nullable(); // Descripción/observaciones
            $table->decimal('partial_output', 10, 2)->default(0); // Salida parcial
            $table->decimal('actual_output', 10, 2)->default(0); // Salida real
            $table->date('entry_date'); // Fecha de ingreso
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('fabric_rolls');
    }
};
