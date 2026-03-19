<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_cash_receipts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('receipt_number')->unique();

            $table->unsignedBigInteger('contact_id')->nullable()->index();
            $table->foreign('contact_id')
                ->references('id')
                ->on('contacts')
                ->onDelete('set null');

            $table->date('payment_date')->nullable()->index();
            $table->unsignedBigInteger('account_id')->nullable()->index();
            $table->string('payment_method', 50)->nullable();
            $table->string('reference', 120)->nullable();
            $table->text('notes')->nullable();

            $table->unsignedBigInteger('created_by_user_id')->nullable()->index();
            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->timestamps();
        });

        DB::table('finance_document_sequences')->updateOrInsert(
            ['document_type' => 'cash_receipt'],
            [
                'current_value' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        Schema::table('finance_cash_receipts', function (Blueprint $table) {
            $table->dropForeign(['contact_id']);
            $table->dropForeign(['created_by_user_id']);
        });

        Schema::dropIfExists('finance_cash_receipts');

        DB::table('finance_document_sequences')
            ->where('document_type', 'cash_receipt')
            ->delete();
    }
};
