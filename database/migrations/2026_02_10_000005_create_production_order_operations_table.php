<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_order_operations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('programmer_id');
            $table->integer('sequence');
            $table->unsignedBigInteger('operation_id');
            $table->unsignedBigInteger('machine_id')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('sam_base', 8, 4);
            $table->decimal('machine_supplement_factor', 8, 4);
            $table->decimal('fabric_base_factor', 8, 4);
            $table->decimal('operator_efficiency_factor', 8, 4);
            $table->decimal('sam_total_unitario', 8, 4);
            $table->decimal('sam_total_operacion', 8, 4);
            $table->decimal('sam_total_lote', 10, 4)->comment('sam_total_operacion × cantidad_lote');
            $table->integer('quantity_progressed')->default(0);
            $table->enum('status', ['PENDIENTE', 'EN_PROCESO', 'COMPLETADA'])->default('PENDIENTE');
            $table->timestamps();

            $table->foreign('programmer_id')
                ->references('id')
                ->on('programmers')
                ->onDelete('cascade');
            $table->foreign('operation_id')
                ->references('id')
                ->on('operations')
                ->onDelete('restrict');
            $table->foreign('machine_id')
                ->references('id')
                ->on('machines')
                ->onDelete('set null');
            $table->index(['programmer_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_order_operations');
    }
};
