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
            $table->unsignedBigInteger('purchase_invoice_id');
            $table->unsignedBigInteger('article_id');
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

            $table->foreign('purchase_invoice_id')->references('id')->on('purchase_invoices')->onDelete('cascade');
            $table->foreign('article_id')->references('id')->on('articles');
        });
    }

    public function down()
    {
        Schema::dropIfExists('purchase_invoice_items');
    }
}
