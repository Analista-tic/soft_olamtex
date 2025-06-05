<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePurchaseOrdersTable extends Migration
{
    public function up()
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained();
            $table->date(column: 'date');
            $table->date('delivery_date')->nullable();
            $table->string('supplier_order_number')->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'invoiced'])->default('pending');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('purchase_orders');
    }
}
