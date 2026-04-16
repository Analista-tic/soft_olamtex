<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWarehouseItemsTable extends Migration
{
    public function up()
    {
        Schema::create('warehouse_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 15, 4)->default(0);
            $table->decimal('stock_min', 15, 4)->nullable()->default(0);
            $table->decimal('stock_max', 15, 4)->nullable()->default(0);
            $table->decimal('weighted_average_price', 15, 4)->nullable()->default(null);
            $table->unique(['item_id', 'warehouse_id'], 'warehouse_items_item_warehouse_unique');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('warehouse_items');
    }
}
