<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePurchaseOrderItemsTable extends Migration
{
    public function up()
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained();
            $table->foreignId('item_id')->constrained();
            $table->decimal('price', 15, 2);
            $table->decimal('tax', 8, 2)->nullable();
            $table->integer('quantity');
            $table->text('description')->nullable();
            // Campos adicionales para tela
            $table->string('supplier_code')->nullable();
            $table->string('supplier_color')->nullable();
            $table->float('order_width')->nullable();
            $table->decimal('total', 15, 2);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('purchase_order_items');
    }
}
