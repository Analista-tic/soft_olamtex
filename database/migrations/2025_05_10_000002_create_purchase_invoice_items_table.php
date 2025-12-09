<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePurchaseInvoiceItemsTable extends Migration
{
    public function up()
    {
        Schema::create('purchase_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_invoice_id')->constrained();
            $table->foreignId('item_id')->constrained('items');
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('tax', 5, 2)->default(0);
            $table->integer('quantity')->default(1);
            $table->string('description')->nullable();
            // Campos adicionales para tela
            $table->string('supplier_code')->nullable();
            $table->string('supplier_color')->nullable();
            $table->float('order_width')->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('total_tax', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('purchase_invoice_items');
    }
}
