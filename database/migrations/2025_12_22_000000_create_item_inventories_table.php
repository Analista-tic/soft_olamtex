<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateItemInventoriesTable extends Migration
{
    public function up(): void
    {
        // Tabla eliminada: item_inventories era una proyección
        // sincronizada desde warehouse_items. Reemplazada por
        // JOIN directo en ItemInventoryService.
        Schema::dropIfExists('item_inventories');
    }

    public function down(): void
    {
        Schema::create('item_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')
                  ->constrained('items')
                  ->cascadeOnDelete();
            $table->foreignId('warehouse_id')
                  ->constrained('warehouses')
                  ->cascadeOnDelete();
            $table->string('item_name');
            $table->string('reference')->nullable();
            $table->decimal('quantity', 14, 4)->default(0);
            $table->boolean('status')->default(true);
            $table->decimal('average_cost', 15, 4)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->unique(['item_id', 'warehouse_id'],
                           'item_inventory_item_warehouse_unique');
            $table->timestamps();
        });
    }
}
