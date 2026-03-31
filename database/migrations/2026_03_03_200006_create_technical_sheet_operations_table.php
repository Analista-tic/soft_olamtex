<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technical_sheet_operations', function (Blueprint $table) {
            $table->id();

            // Referencia a TechnicalSheet (Aggregate Root de la Ficha Técnica)
            $table->unsignedBigInteger('technical_sheet_id')->comment('FK a technical_sheets');

            // Relación a operación fuente (ProductSheetOperation original)
            $table->unsignedBigInteger('product_sheet_operation_id')->nullable()->comment('FK a product_sheet_operations (fuente del snapshot)');

            // Datos de la operación (congelados al crear la orden)
            $table->integer('sequence')->comment('Secuencia en la orden (10, 20, 30...)');
            $table->unsignedBigInteger('operation_id')->comment('FK a operations (catálogo maestro)');
            $table->unsignedBigInteger('machine_id')->nullable()->comment('FK a machines (máquina asignada)');

            // Cantidad y factores (CONGELADOS - no pueden editarse excepto quantity_progressed)
            $table->integer('quantity')->default(1)->comment('Cantidad de piezas');
            $table->decimal('sam_base', 8, 4)->comment('SAM base original (no editable)');
            $table->decimal('machine_supplement_factor', 8, 4)->comment('Factor de suplemento de máquina (no editable)');
            $table->decimal('fabric_base_factor', 8, 4)->comment('Factor de base de tela (no editable)');
            $table->decimal('operator_efficiency_factor', 8, 4)->comment('Factor de operario (no editable)');

            // SAMs calculados (CONGELADOS)
            $table->decimal('sam_total_unitario', 8, 4)->comment('SAM total por unidad (no editable)');
            $table->decimal('sam_total_operacion', 8, 4)->comment('SAM total de operación (no editable)');
            $table->decimal('sam_total_lote', 10, 4)->comment('SAM total del lote (no editable)');

            // ÚNICO CAMPO EDITABLE: Progreso y motivo
            $table->integer('quantity_progressed')->default(0)->comment('Cantidad progresada (ÚNICO CAMPO EDITABLE)');
            $table->text('motivo_progreso')->nullable()->comment('Motivo o comentario de la actualización de progreso');

            // Estado de ejecución
            $table->enum('status', [
                'PENDIENTE',
                'EN_EJECUCION',
                'PAUSADA',
                'COMPLETADA',
                'CANCELADA',
            ])->default('PENDIENTE')->comment('Estado de ejecución de la operación');

            // Timestamps
            $table->timestamp('progressed_at')->nullable()->comment('Última actualización de progreso');
            $table->timestamps();
            $table->softDeletes()->comment('Soft delete para preservar histórico');

            // Foreign Keys
            $table->foreign('technical_sheet_id', 'tso_technical_sheet_fk')
                ->references('id')
                ->on('technical_sheets')
                ->cascadeOnDelete();
            $table->foreign('operation_id', 'tso_operation_fk')
                ->references('id')
                ->on('operations')
                ->restrictOnDelete();
            $table->foreign('machine_id', 'tso_machine_fk')
                ->references('id')
                ->on('machines')
                ->nullOnDelete();
            $table->foreign('product_sheet_operation_id', 'tso_pso_fk')
                ->references('id')
                ->on('product_sheet_operations')
                ->nullOnDelete();

            // Índices
            $table->index(['technical_sheet_id', 'sequence']);
            $table->index('status');
            $table->index('operation_id');
            $table->index(['technical_sheet_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_sheet_operations');
    }
};
