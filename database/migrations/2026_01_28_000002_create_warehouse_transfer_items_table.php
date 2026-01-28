<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_transfer_id')
                ->constrained('warehouse_transfers')
                ->cascadeOnDelete()
                ->name('wh_transfer_items_transfer_fk');
            $table->foreignId('item_id')
                ->constrained('items')
                ->restrictOnDelete()
                ->name('wh_transfer_items_item_fk');
            $table->decimal('quantity', 15, 4);
            $table->timestamps();

            $table->index(['warehouse_transfer_id', 'item_id'], 'wh_transfer_items_transfer_item_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_transfer_items');
    }
};
