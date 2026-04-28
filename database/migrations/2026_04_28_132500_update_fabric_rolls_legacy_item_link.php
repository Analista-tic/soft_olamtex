<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fabric_rolls', function (Blueprint $table) {
            $table->dropForeign(['purchase_document_item_id']);
            $table->unsignedBigInteger('purchase_document_item_id')->nullable()->change();
            $table->foreignId('item_id')->nullable()->after('purchase_document_item_id');
        });

        Schema::table('fabric_rolls', function (Blueprint $table) {
            $table->foreign('purchase_document_item_id')
                ->references('id')
                ->on('purchase_document_items');

            $table->foreign('item_id')
                ->references('id')
                ->on('items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fabric_rolls', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
            $table->dropForeign(['purchase_document_item_id']);
            $table->dropColumn('item_id');
            $table->unsignedBigInteger('purchase_document_item_id')->nullable(false)->change();
        });

        Schema::table('fabric_rolls', function (Blueprint $table) {
            $table->foreign('purchase_document_item_id')
                ->references('id')
                ->on('purchase_document_items');
        });
    }
};
