<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Refactorización DDD: Renombra `production_order_operations` → `technical_sheet_operations`.
 * Reemplaza FK `programmer_id` → `technical_sheet_id`.
 *
 * TechnicalSheetOperation pertenece a TechnicalSheet, no directamente a ProductionOrder.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Si ya existe technical_sheet_operations, la migración ya se aplicó
        if (Schema::hasTable('technical_sheet_operations')) {
            return;
        }

        $tableName = 'production_order_operations';
        if (! Schema::hasTable($tableName)) {
            return;
        }

        $hasTechnicalSheetId = Schema::hasColumn($tableName, 'technical_sheet_id');
        $hasProgrammerId = Schema::hasColumn($tableName, 'programmer_id');

        // 1. Agregar columna technical_sheet_id si no existe
        if (! $hasTechnicalSheetId) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('technical_sheet_id')
                    ->nullable()
                    ->after('id')
                    ->comment('FK a technical_sheets');
            });
        }

        // 2. Migrar datos solo si existe programmer_id (mapper programmer_id → technical_sheet_id)
        if ($hasProgrammerId && Schema::hasTable('technical_sheets')) {
            DB::statement('
                UPDATE production_order_operations poo
                INNER JOIN technical_sheets ts ON ts.production_order_id = poo.programmer_id
                SET poo.technical_sheet_id = ts.id
            ');
        }

        // 3. Eliminar FK y columna antigua si existe programmer_id
        if (Schema::hasColumn($tableName, 'programmer_id')) {
            Schema::table($tableName, function (Blueprint $table) {
                try {
                    $table->dropForeign('poo_programmer_fk');
                } catch (\Throwable $e) {
                    // FK puede no existir
                }
                $table->dropColumn('programmer_id');
            });
        }

        // 4. Agregar FK de technical_sheet_id si no existe
        if (Schema::hasColumn($tableName, 'technical_sheet_id')) {
            try {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreign('technical_sheet_id', 'tso_technical_sheet_fk')
                        ->references('id')
                        ->on('technical_sheets')
                        ->cascadeOnDelete();
                });
            } catch (\Throwable $e) {
                // FK ya existe
            }
        }

        // 5. Renombrar tabla
        if (Schema::hasTable($tableName) && ! Schema::hasTable('technical_sheet_operations')) {
            Schema::rename($tableName, 'technical_sheet_operations');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('technical_sheet_operations')) {
            return;
        }

        Schema::rename('technical_sheet_operations', 'production_order_operations');

        $tableName = 'production_order_operations';

        if (Schema::hasColumn($tableName, 'technical_sheet_id')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign('tso_technical_sheet_fk');
                $table->dropColumn('technical_sheet_id');
            });
        }

        if (! Schema::hasColumn($tableName, 'programmer_id')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('programmer_id')
                    ->nullable()
                    ->after('id')
                    ->comment('FK a production_orders (ex-programmers)');
            });
        }

        if (Schema::hasTable('technical_sheets')) {
            DB::statement('
                UPDATE production_order_operations poo
                INNER JOIN technical_sheets ts ON ts.id = poo.technical_sheet_id
                SET poo.programmer_id = ts.production_order_id
            ');
        }

        if (Schema::hasColumn($tableName, 'programmer_id')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreign('programmer_id', 'poo_programmer_fk')
                    ->references('id')
                    ->on('production_orders')
                    ->cascadeOnDelete();
            });
        }
    }
};
