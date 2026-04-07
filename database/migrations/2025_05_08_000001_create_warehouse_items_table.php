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
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('item_id')->constrained();
            $table->decimal('quantity', 15, 4)->default(0);
            $table->decimal('stock_min', 15, 4)->default(0)->nullable();
            $table->decimal('stock_max', 15, 4)->default(0)->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('warehouse_items');
    }
}
