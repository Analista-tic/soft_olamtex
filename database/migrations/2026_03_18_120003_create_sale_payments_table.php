<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('cash_receipt_id')->nullable()->index();
            $table->unsignedBigInteger('cash_receipt_number')->nullable()->index();

            $table->foreign('cash_receipt_id')
                ->references('id')
                ->on('finance_cash_receipts')
                ->onDelete('cascade');

            $table->unsignedBigInteger('sale_invoice_id')->index();
            $table->foreign('sale_invoice_id')
                ->references('id')
                ->on('sale_documents')
                ->onDelete('cascade');

            $table->date('payment_date')->index();
            $table->decimal('amount', 15, 2);
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();

            $table->unsignedBigInteger('created_by_user_id')->nullable()->index();
            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->timestamps();

            $table->index(['sale_invoice_id', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::table('sale_payments', function (Blueprint $table) {
            $table->dropForeign(['cash_receipt_id']);
            $table->dropForeign(['sale_invoice_id']);
            $table->dropForeign(['created_by_user_id']);
        });

        Schema::dropIfExists('sale_payments');
    }
};
