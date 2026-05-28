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
        Schema::create('fabric_roll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fabric_roll_id')->constrained('fabric_rolls')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->enum('type', ['entrada', 'salida', 'correction', 'import_update']);
            $table->decimal('quantity_before', 10, 2);
            $table->decimal('quantity_after', 10, 2);
            $table->decimal('partial_before', 10, 2)->nullable();
            $table->decimal('partial_after', 10, 2)->nullable();
            $table->text('reason');
            $table->string('reference', 100)->nullable();
            $table->foreignId('inventory_movement_id')->nullable()->constrained('inventory_movements')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fabric_roll_adjustments');
    }
};
