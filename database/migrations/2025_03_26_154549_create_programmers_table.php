<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('programmers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_sheet_id')->constrained();
            $table->string('season');
            $table->foreignId('destination_id')->constrained();
            $table->string('priority');
            $table->integer('units');
            $table->json('ecommerce_units')->nullable(); // Asegúrate de que sea JSON
            $table->string('status');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('programmers');
    }
};
