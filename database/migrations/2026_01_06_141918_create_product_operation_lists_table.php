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
                $table->boolean('is_active')->default(true)->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('operations')) {
            Schema::create('operations', function (Blueprint $table) {
                $table->id();
                $table->string('action');
                $table->string('garment_part');
                $table->boolean('is_active')->default(true)->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
                
                $table->unique(['action', 'garment_part'], 'operations_action_garment_unique');
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

        if (Schema::hasTable('product_operation_lists')) {
            Schema::drop('product_operation_lists');
        }

        Schema::create('product_operation_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_sheet_id')->constrained('product_sheets')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('operation_id')->constrained('operations')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('machine_id')->constrained('machines')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('operation_section_id')->constrained('operation_sections')->cascadeOnUpdate()->restrictOnDelete();

            $table->index('product_sheet_id', 'pol_sheet_idx');
            $table->index('operation_id', 'pol_operation_idx');
            $table->index('machine_id', 'pol_machine_idx');
            $table->index('operation_section_id', 'pol_section_idx');

            $table->unsignedInteger('sequence_order')->default(1);

            $table->boolean('is_active')->default(true)->index();

            $table->decimal('optimal_time', 10, 4);
            $table->decimal('sam', 10, 4);
            $table->decimal('operator_efficiency_factor', 6, 3)->default(1.250);
            $table->decimal('machine_supplement_factor', 6, 3)->default(1.000);
            $table->decimal('production_time', 10, 4);

            $table->decimal('actual_time', 10, 4)->nullable();
            $table->decimal('variance', 8, 2)->nullable();

            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_sheet_id', 'operation_section_id'], 'pol_sheet_section_idx');
            $table->index(['product_sheet_id', 'is_active'], 'pol_sheet_active_idx');
            $table->index(['product_sheet_id', 'sequence_order'], 'pol_sheet_order_idx');
            $table->unique(['product_sheet_id', 'sequence_order'], 'pol_sheet_order_unique');
        });

        try {
            DB::statement("ALTER TABLE product_operation_lists ADD CONSTRAINT chk_operator_efficiency_factor CHECK (operator_efficiency_factor >= 1.0 AND operator_efficiency_factor <= 2.0)");
            DB::statement("ALTER TABLE product_operation_lists ADD CONSTRAINT chk_machine_supplement_factor CHECK (machine_supplement_factor >= 1.0)");
            DB::statement("ALTER TABLE product_operation_lists ADD CONSTRAINT chk_sequence_order_positive CHECK (sequence_order >= 1)");
        } catch (\Exception $e) {
            // El motor de base de datos podría no soportar CHECK; no interrumpimos la migración.
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_operation_lists');
        Schema::dropIfExists('operation_sections');
        Schema::dropIfExists('operations');
        Schema::dropIfExists('machines');
    }
};
