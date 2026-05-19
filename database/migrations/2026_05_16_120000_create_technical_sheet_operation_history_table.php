<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technical_sheet_operation_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('technical_sheet_id');
            $table->unsignedBigInteger('technical_sheet_operation_id')->nullable();
            $table->string('event_type', 60);
            $table->unsignedBigInteger('changed_by_user_id')->nullable();
            $table->text('reason')->nullable();

            $table->integer('before_sequence')->nullable();
            $table->unsignedBigInteger('before_operation_id')->nullable();
            $table->unsignedBigInteger('before_machine_id')->nullable();
            $table->integer('before_quantity')->nullable();
            $table->decimal('before_sam_base', 10, 4)->nullable();
            $table->decimal('before_machine_supplement_factor', 10, 4)->nullable();
            $table->decimal('before_fabric_base_factor', 10, 4)->nullable();
            $table->decimal('before_operator_efficiency_factor', 10, 4)->nullable();
            $table->decimal('before_sam_total_unitario', 10, 4)->nullable();
            $table->decimal('before_sam_total_operacion', 10, 4)->nullable();
            $table->decimal('before_sam_total_lote', 12, 4)->nullable();
            $table->integer('before_quantity_progressed')->nullable();
            $table->string('before_status', 40)->nullable();

            $table->integer('after_sequence')->nullable();
            $table->unsignedBigInteger('after_operation_id')->nullable();
            $table->unsignedBigInteger('after_machine_id')->nullable();
            $table->integer('after_quantity')->nullable();
            $table->decimal('after_sam_base', 10, 4)->nullable();
            $table->decimal('after_machine_supplement_factor', 10, 4)->nullable();
            $table->decimal('after_fabric_base_factor', 10, 4)->nullable();
            $table->decimal('after_operator_efficiency_factor', 10, 4)->nullable();
            $table->decimal('after_sam_total_unitario', 10, 4)->nullable();
            $table->decimal('after_sam_total_operacion', 10, 4)->nullable();
            $table->decimal('after_sam_total_lote', 12, 4)->nullable();
            $table->integer('after_quantity_progressed')->nullable();
            $table->string('after_status', 40)->nullable();

            $table->timestamps();

            $table->foreign('technical_sheet_id', 'tso_history_ts_fk')
                ->references('id')
                ->on('technical_sheets')
                ->cascadeOnDelete();

            $table->foreign('technical_sheet_operation_id', 'tso_history_tso_fk')
                ->references('id')
                ->on('technical_sheet_operations')
                ->nullOnDelete();

            $table->foreign('changed_by_user_id', 'tso_history_user_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['technical_sheet_id', 'event_type'], 'tso_history_ts_event_idx');
            $table->index('technical_sheet_operation_id', 'tso_history_tso_idx');
            $table->index('changed_by_user_id', 'tso_history_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_sheet_operation_history');
    }
};
