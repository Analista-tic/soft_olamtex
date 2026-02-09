<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_request_id')
                ->constrained('inventory_requests')
                ->cascadeOnDelete()
                ->index();
            $table->foreignId('item_id')->nullable()->constrained('items')->cascadeOnDelete();
            $table->string('item_name')->nullable();
            $table->decimal('requested_quantity', 15, 4);
            $table->decimal('delivered_quantity', 15, 4)->nullable();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->timestamps();

            $table->index(['inventory_request_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_request_items');
    }
};
