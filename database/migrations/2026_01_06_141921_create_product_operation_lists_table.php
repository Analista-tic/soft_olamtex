<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_sheet_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_sheet_id')->constrained('product_sheets')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('operation_id')->constrained('operations')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('machine_id')->constrained('machines')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('operation_section_id')->constrained('operation_sections')->cascadeOnUpdate()->restrictOnDelete();

            $table->integer('sequence')->default(10)->comment('Orden de ejecución de operación (múltiplos de 10)');
            $table->unsignedBigInteger('fabric_base_id')->nullable()->comment('Referencia a base de tela');
            $table->integer('quantity')->default(1)->comment('Cantidad de operaciones en este ProductSheet');

            // Índices de búsqueda
            $table->index('product_sheet_id', 'pol_sheet_idx');
            $table->index('operation_id', 'pol_operation_idx');
            $table->index('machine_id', 'pol_machine_idx');
            $table->index('operation_section_id', 'pol_section_idx');
            $table->index('sequence');
            $table->index(['product_sheet_id', 'sequence']);

            $table->boolean('is_active')->default(true)->index();

            // Campos de cálculo SAM
            $table->decimal('sam_base', 8, 4)->nullable()->comment('SAM base de operación (referencia)');
            $table->decimal('sam', 10, 4)->nullable()->comment('SAM calculado');
            $table->decimal('operator_efficiency_factor', 6, 3)->default(1.250)->comment('Factor de eficiencia de operario (1.0 - 2.0)');
            $table->decimal('machine_supplement_factor', 6, 3)->default(1.000)->comment('Factor de suplemento de máquina');
            $table->decimal('fabric_base_factor', 8, 4)->default(1.0)->comment('Factor de base de tela');
            $table->decimal('sam_total_unitario', 8, 4)->nullable()->comment('SAM total por unidad');
            $table->decimal('sam_total_operacion', 8, 4)->nullable()->comment('SAM total de operación');

            // Tiempos de producción
            $table->decimal('optimal_time', 10, 4)->nullable()->comment('Tiempo óptimo estimado');
            $table->decimal('production_time', 10, 4)->nullable()->comment('Tiempo de producción real');
            $table->decimal('actual_time', 10, 4)->nullable()->comment('Tiempo actual registrado');
            $table->decimal('variance', 8, 2)->nullable()->comment('Varianza respecto a óptimo');

            $table->text('notes')->nullable()->comment('Notas de la operación en ProductSheet');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_sheet_operations');
    }
};
