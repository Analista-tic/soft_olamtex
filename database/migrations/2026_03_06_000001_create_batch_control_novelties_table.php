<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_control_novelties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')
                ->constrained('production_orders')
                ->cascadeOnDelete();
            $table->string('from_process', 80)->comment('Estado que reporta la novedad (estado actual)');
            $table->string('to_process', 80)->comment('Estado al que se le reporta (estado anterior que debe corregir)');
            $table->text('description')->comment('Descripción detallada de la novedad');
            $table->enum('status', ['pending', 'resolved'])->default('pending');
            $table->foreignId('reported_by')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('resolved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable()->comment('Notas de resolución');
            $table->timestamps();

            $table->index('production_order_id');
            $table->index(['production_order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_control_novelties');
    }
};
