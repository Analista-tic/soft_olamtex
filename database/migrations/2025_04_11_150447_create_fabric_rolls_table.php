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
        Schema::create('fabric_rolls', function (Blueprint $table) {
            $table->id()->startingValue(9000);
            $table->string('location');
            $table->foreignId('fabric_color_id')->constrained();
            $table->foreignId('fabric_order_item_id')->constrained();
            $table->decimal('received_quantity', 8, 2)->nullable();
            $table->decimal('width', 8, 2);
            $table->decimal('quantity', 8, 2);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fabric_rolls');
    }
};
