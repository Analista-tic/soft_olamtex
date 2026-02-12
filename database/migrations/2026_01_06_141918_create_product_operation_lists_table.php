<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('machines')) {
            Schema::create('machines', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name')->unique();
                $table->decimal('supplement_factor', 8, 4)->default(1.0);
                $table->boolean('is_active')->default(true)->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('operation_sections')) {
            Schema::create('operation_sections', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('type');
                $table->boolean('is_active')->default(true)->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('operations')) {
            Schema::create('operations', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 50)->unique()->nullable();
                $table->string('action');
                $table->string('garment_part');
                $table->unsignedBigInteger('section_id')->nullable();
                $table->string('location', 100)->nullable();
                $table->decimal('measure', 8, 4)->nullable();
                $table->text('description')->nullable();
                $table->decimal('sam_base', 8, 4)->nullable();
                $table->unsignedBigInteger('product_sheet_id_reference')->nullable();
                $table->unsignedBigInteger('machine_id_reference')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['action', 'garment_part'], 'operations_action_garment_unique');
                $table->foreign('section_id')->references('id')->on('operation_sections')->onDelete('set null');
                $table->foreign('product_sheet_id_reference')->references('id')->on('product_sheets')->onDelete('set null');
                $table->foreign('machine_id_reference')->references('id')->on('machines')->onDelete('set null');
            });
        }

        if (Schema::hasTable('product_operation_lists')) {
            Schema::drop('product_operation_lists');
        }

        Schema::create('product_operation_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_sheet_id')->constrained('product_sheets')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('operation_id')->constrained('operations')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('machine_id')->constrained('machines')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('operation_section_id')->constrained('operation_sections')->cascadeOnUpdate()->restrictOnDelete();

            $table->integer('sequence')->default(10);
            $table->unsignedBigInteger('fabric_base_id')->nullable();
            $table->integer('quantity')->default(1);

            $table->index('product_sheet_id', 'pol_sheet_idx');
            $table->index('operation_id', 'pol_operation_idx');
            $table->index('machine_id', 'pol_machine_idx');
            $table->index('operation_section_id', 'pol_section_idx');
            $table->index('sequence');
            $table->index(['product_sheet_id', 'sequence']);

            $table->boolean('is_active')->default(true)->index();

            $table->decimal('sam_base', 8, 4)->nullable();
            $table->decimal('sam', 10, 4)->nullable();
            $table->decimal('operator_efficiency_factor', 6, 3)->default(1.250);
            $table->decimal('machine_supplement_factor', 6, 3)->default(1.000);
            $table->decimal('fabric_base_factor', 8, 4)->default(1.0);
            $table->decimal('sam_total_unitario', 8, 4)->nullable();
            $table->decimal('sam_total_operacion', 8, 4)->nullable();

            $table->decimal('optimal_time', 10, 4)->nullable();
            $table->decimal('production_time', 10, 4)->nullable();
            $table->decimal('actual_time', 10, 4)->nullable();
            $table->decimal('variance', 8, 2)->nullable();

            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_sheet_id', 'operation_section_id'], 'pol_sheet_section_idx');
            $table->index(['product_sheet_id', 'is_active'], 'pol_sheet_active_idx');
        });

        try {
            DB::statement('ALTER TABLE product_operation_lists ADD CONSTRAINT chk_operator_efficiency_factor CHECK (operator_efficiency_factor >= 1.0 AND operator_efficiency_factor <= 2.0)');
            DB::statement('ALTER TABLE product_operation_lists ADD CONSTRAINT chk_machine_supplement_factor CHECK (machine_supplement_factor >= 1.0)');
        } catch (\Exception $e) {
            // El motor de base de datos podría no soportar CHECK; no interrumpimos la migración.
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_operation_lists');
        Schema::dropIfExists('operations');
        Schema::dropIfExists('operation_sections');
        Schema::dropIfExists('machines');
    }
};
