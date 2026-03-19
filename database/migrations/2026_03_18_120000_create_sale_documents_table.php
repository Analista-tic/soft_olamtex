<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_documents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('document_number')->nullable()->index();
            $table->string('document_type')->index();

            $table->unsignedBigInteger('parent_document_id')->nullable()->index();
            $table->foreign('parent_document_id')
                ->references('id')
                ->on('sale_documents')
                ->nullOnDelete();

            $table->unsignedBigInteger('quote_id')->nullable()->index();
            $table->foreign('quote_id')
                ->references('id')
                ->on('sale_documents')
                ->nullOnDelete();

            $table->unsignedBigInteger('contact_id')->index();
            $table->foreign('contact_id')
                ->references('id')
                ->on('contacts')
                ->restrictOnDelete();

            $table->date('date');
            $table->date('due_date')->nullable();
            $table->date('delivery_date')->nullable();
            $table->text('description')->nullable();
            $table->string('payment_terms')->nullable();
            $table->integer('payment_days')->nullable();
            $table->string('payment_status')->default('unpaid')->index();
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->string('customer_order_number')->nullable();
            $table->string('status')->index();
            $table->json('withholdings')->nullable();
            $table->timestamp('invoiced_at')->nullable();

            $table->unsignedBigInteger('created_by_user_id')->index();
            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();

            $table->unsignedBigInteger('executed_by_user_id')->nullable()->index();
            $table->foreign('executed_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['document_type', 'status']);
            $table->index(['contact_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::table('sale_documents', function (Blueprint $table) {
            $table->dropForeign(['parent_document_id']);
            $table->dropForeign(['quote_id']);
            $table->dropForeign(['contact_id']);
            $table->dropForeign(['created_by_user_id']);
            $table->dropForeign(['executed_by_user_id']);
        });

        Schema::dropIfExists('sale_documents');
    }
};
