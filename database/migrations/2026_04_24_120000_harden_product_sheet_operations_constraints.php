<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            if (Schema::hasTable('product_sheet_operations')) {
                // Limpiar soft-deletes para permitir constraints únicos reales.
                DB::table('product_sheet_operations')
                    ->whereNotNull('deleted_at')
                    ->delete();

                // Deduplicar por producto + operación (conservar el registro más reciente).
                DB::statement("\n                    DELETE p1\n                    FROM product_sheet_operations p1\n                    INNER JOIN product_sheet_operations p2\n                        ON p1.product_sheet_id = p2.product_sheet_id\n                        AND p1.operation_id = p2.operation_id\n                        AND p1.id < p2.id\n                ");

                // Deduplicar secuencias por producto (conservar el registro más reciente).
                DB::statement("\n                    DELETE p1\n                    FROM product_sheet_operations p1\n                    INNER JOIN product_sheet_operations p2\n                        ON p1.product_sheet_id = p2.product_sheet_id\n                        AND p1.sequence = p2.sequence\n                        AND p1.id < p2.id\n                ");
            }
        });

        Schema::table('product_sheet_operations', function (Blueprint $table) {
            if (!Schema::hasColumn('product_sheet_operations', 'has_override')) {
                $table->boolean('has_override')->default(false)->after('quantity');
            }

            // machine_id como override opcional por producto.
            $table->unsignedBigInteger('machine_id')->nullable()->change();

            $table->unique(['product_sheet_id', 'operation_id'], 'unique_product_operation');
            $table->unique(['product_sheet_id', 'sequence'], 'unique_sequence_per_sheet');
        });
    }

    public function down(): void
    {
        Schema::table('product_sheet_operations', function (Blueprint $table) {
            $table->dropUnique('unique_product_operation');
            $table->dropUnique('unique_sequence_per_sheet');

            if (Schema::hasColumn('product_sheet_operations', 'has_override')) {
                $table->dropColumn('has_override');
            }
        });
    }
};
