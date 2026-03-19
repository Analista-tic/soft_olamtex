<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('voucher_number')->nullable()->index();

            $table->unsignedBigInteger('purchase_invoice_id')->index();
            $table->foreign('purchase_invoice_id')
                ->references('id')
                ->on('purchase_documents')
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

            $table->index(['purchase_invoice_id', 'payment_date']);
        });

        Schema::create('finance_document_sequences', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('document_type', 80)->unique();
            $table->unsignedBigInteger('current_value')->default(0);
            $table->timestamps();
        });

        DB::table('finance_document_sequences')->insert([
            [
                'document_type' => 'expense_voucher',
                'current_value' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'document_type' => 'quote',
                'current_value' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'document_type' => 'sales_order',
                'current_value' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'document_type' => 'sales_invoice',
                'current_value' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'document_type' => 'sale_credit_note',
                'current_value' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'document_type' => 'sale_debit_note',
                'current_value' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::table('purchase_payments', function (Blueprint $table) {
            $table->dropForeign(['purchase_invoice_id']);
            $table->dropForeign(['created_by_user_id']);
        });

        Schema::dropIfExists('purchase_payments');
        Schema::dropIfExists('finance_document_sequences');
    }
};
