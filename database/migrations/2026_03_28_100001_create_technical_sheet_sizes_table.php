<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technical_sheet_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technical_sheet_id')
                  ->constrained('technical_sheets')
                  ->cascadeOnDelete();
            $table->string('size', 50);
            $table->unsignedInteger('units')->default(0);
            $table->decimal('curve', 10, 2)->nullable();
            $table->timestamps();

            // Una talla única por ficha técnica
            $table->unique(['technical_sheet_id', 'size']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_sheet_sizes');
    }
};
