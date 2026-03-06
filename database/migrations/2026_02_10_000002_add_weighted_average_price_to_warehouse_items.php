<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PP por bodega. El precio ponderado vive en warehouse_items.
     * Índice único evita duplicados item+warehouse.
     */
    public function up(): void
    {
        Schema::table('warehouse_items', function (Blueprint $table) {
            $table->decimal('weighted_average_price', 20, 6)->nullable()->after('quantity');
        });

        // Poblar PP desde último movimiento por item+warehouse
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("
                UPDATE warehouse_items wi
                INNER JOIN (
                    SELECT m.item_id, m.warehouse_id, m.new_weighted_price
                    FROM inventory_movements m
                    INNER JOIN (
                        SELECT item_id, warehouse_id, MAX(id) as max_id
                        FROM inventory_movements
                        GROUP BY item_id, warehouse_id
                    ) latest ON m.item_id = latest.item_id AND m.warehouse_id = latest.warehouse_id AND m.id = latest.max_id
                ) last ON wi.item_id = last.item_id AND wi.warehouse_id = last.warehouse_id
                SET wi.weighted_average_price = last.new_weighted_price
            ");
        }

        // Índice único evita duplicados item+warehouse
        Schema::table('warehouse_items', function (Blueprint $table) {
            $table->unique(['item_id', 'warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_items', function (Blueprint $table) {
            $table->dropUnique(['item_id', 'warehouse_id']);
        });
        Schema::table('warehouse_items', function (Blueprint $table) {
            $table->dropColumn('weighted_average_price');
        });
    }
};
