<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_document_item_receptions', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('purchase_document_item_id');
            $table->foreign('purchase_document_item_id', 'pdi_receptions_item_fk')
                ->references('id')
                ->on('purchase_document_items')
                ->onDelete('cascade');

            $table->unsignedBigInteger('warehouse_id');
            $table->foreign('warehouse_id', 'pdi_receptions_warehouse_fk')
                ->references('id')
                ->on('warehouses')
                ->onDelete('restrict');

            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_price', 15, 2)->nullable();
            $table->text('over_received_reason')->nullable();

            $table->unsignedBigInteger('inventory_movement_id')->nullable();
            $table->foreign('inventory_movement_id', 'pdi_receptions_movement_fk')
                ->references('id')
                ->on('inventory_movements')
                ->onDelete('set null');

            $table->unsignedBigInteger('received_by_user_id');
            $table->foreign('received_by_user_id', 'pdi_receptions_user_fk')
                ->references('id')
                ->on('users')
                ->onDelete('restrict');

            $table->timestamp('received_at');

            $table->timestamps();

            $table->index('purchase_document_item_id', 'pdi_receptions_item_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_document_item_receptions');
    }
};
