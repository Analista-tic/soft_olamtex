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
        Schema::create('product_sheet_cutting_piece_sizes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cutting_piece_id');
            $table->string('size', 30);
            $table->unsignedSmallInteger('units')->default(0);
            $table->string('curve', 30)->nullable();
            $table->timestamps();

            // Índice único
            $table->unique(['cutting_piece_id', 'size', 'curve'], 'unique_cutting_piece_size_curve');

            // FK
            $table->foreign('cutting_piece_id')
                ->references('id')
                ->on('product_sheet_cutting_pieces')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_sheet_cutting_piece_sizes');
    }
};
