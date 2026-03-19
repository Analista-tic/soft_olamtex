<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_document_items', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('sale_document_id');
            $table->foreign('sale_document_id')
                ->references('id')
                ->on('sale_documents')
                ->onDelete('cascade');

            $table->unsignedBigInteger('item_id')->nullable()->index();
            $table->foreign('item_id')
                ->references('id')
                ->on('items')
                ->nullOnDelete();

            $table->string('item_name')->nullable();
            $table->decimal('quantity', 15, 3)->default(0);
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('discount', 8, 2)->default(0);
            $table->decimal('tax', 8, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('total_tax', 15, 2)->default(0);
            $table->string('supplier_code', 100)->nullable();
            $table->string('supplier_color', 100)->nullable();
            $table->decimal('order_width', 10, 3)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['sale_document_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::table('sale_document_items', function (Blueprint $table) {
            $table->dropForeign(['sale_document_id']);
            $table->dropForeign(['item_id']);
        });

        Schema::dropIfExists('sale_document_items');
    }
};
