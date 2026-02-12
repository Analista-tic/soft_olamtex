<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_sheet_operation_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_sheet_operation_id');
            $table->decimal('sam_base_anterior', 8, 4)->nullable();
            $table->decimal('sam_base_nuevo', 8, 4)->nullable();
            $table->decimal('machine_supplement_factor_anterior', 8, 4)->nullable();
            $table->decimal('machine_supplement_factor_nuevo', 8, 4)->nullable();
            $table->decimal('fabric_base_factor_anterior', 8, 4)->nullable();
            $table->decimal('fabric_base_factor_nuevo', 8, 4)->nullable();
            $table->decimal('operator_efficiency_factor_anterior', 8, 4)->nullable();
            $table->decimal('operator_efficiency_factor_nuevo', 8, 4)->nullable();
            $table->decimal('sam_total_unitario_anterior', 8, 4)->nullable();
            $table->decimal('sam_total_unitario_nuevo', 8, 4)->nullable();
            $table->decimal('sam_total_operacion_anterior', 8, 4)->nullable();
            $table->decimal('sam_total_operacion_nuevo', 8, 4)->nullable();
            $table->text('motivo')->nullable();
            $table->unsignedBigInteger('usuario_id');
            $table->timestamps();

            $table->foreign('product_sheet_operation_id', 'psoh_pol_id_foreign')
                ->references('id')
                ->on('product_operation_lists')
                ->onDelete('cascade');
            $table->foreign('usuario_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_sheet_operation_history');
    }
};
