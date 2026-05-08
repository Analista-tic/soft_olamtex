<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_sheet_operations', function (Blueprint $table) {
            if (Schema::hasColumn('product_sheet_operations', 'operation_section_id')) {
                try {
                    $table->dropForeign(['operation_section_id']);
                } catch (\Throwable $e) {
                }

                if (Schema::hasColumn('product_sheet_operations', 'operation_section_id')) {
                    $table->dropColumn('operation_section_id');
                }
            }

            if (Schema::hasColumn('product_sheet_operations', 'fabric_base_id')) {
                $table->dropColumn('fabric_base_id');
            }

            if (Schema::hasColumn('product_sheet_operations', 'quantity')) {
                $table->dropColumn('quantity');
            }

            if (Schema::hasColumn('product_sheet_operations', 'has_override')) {
                $table->dropColumn('has_override');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_sheet_operations', function (Blueprint $table) {
            if (!Schema::hasColumn('product_sheet_operations', 'operation_section_id')) {
                $table->foreignId('operation_section_id')
                    ->nullable()
                    ->after('operation_id')
                    ->constrained('operation_sections')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
            }

            if (!Schema::hasColumn('product_sheet_operations', 'fabric_base_id')) {
                $table->unsignedBigInteger('fabric_base_id')->nullable()->after('sequence');
            }

            if (!Schema::hasColumn('product_sheet_operations', 'quantity')) {
                $table->integer('quantity')->default(1)->after('fabric_base_id');
            }

            if (!Schema::hasColumn('product_sheet_operations', 'has_override')) {
                $table->boolean('has_override')->default(false)->after('quantity');
            }
        });
    }
};
