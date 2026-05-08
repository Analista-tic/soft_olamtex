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
        Schema::create('product_sheet_cutting_pieces', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_sheet_id');
            $table->string('piece_uid', 100)->nullable()->index();
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->string('name', 150);
            $table->string('piece_type', 30)->default('PRINCIPAL');
            $table->string('fabric_type', 30)->default('PRINCIPAL');
            $table->string('fabric_uid', 100)->nullable();
            $table->string('trace_location', 30)->nullable();
            $table->string('side', 20)->default('AMBOS');
            $table->string('grain', 10)->default('90');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();

            // Índices
            $table->index(['product_sheet_id', 'sequence']);
            $table->unique(['product_sheet_id', 'piece_uid'], 'unique_product_sheet_piece_uid');

            // FK
            $table->foreign('product_sheet_id')
                ->references('id')
                ->on('product_sheets')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_sheet_cutting_pieces');
    }
};
