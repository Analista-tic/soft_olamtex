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
            $table->integer('quantity')->default(0);
            $table->integer('stock_min')->default(0);
            $table->integer('stock_max')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('warehouse_items');
    }
}
