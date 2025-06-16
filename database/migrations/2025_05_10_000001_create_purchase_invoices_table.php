<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePurchaseInvoicesTable extends Migration
{
    public function up()
    {
        Schema::create('purchase_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained();
            $table->foreignId('purchase_order_id')->constrained();
            $table->date('date');
            $table->date('delivery_date')->nullable();
            $table->string('supplier_invoice_number')->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('purchase_invoices');
    }
}
