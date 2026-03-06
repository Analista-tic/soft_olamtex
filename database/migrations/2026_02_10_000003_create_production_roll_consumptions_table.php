<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Trazabilidad: consumo de rollos por orden de producción.
     */
    public function up(): void
    {
        if (Schema::hasTable('production_roll_consumptions')) {
            $this->addIndexIfMissing();
            return;
        }

        $productionOrderTable = Schema::hasTable('production_orders') ? 'production_orders' : 'programmers';

        Schema::create('production_roll_consumptions', function (Blueprint $table) use ($productionOrderTable) {
            $table->id();
            $table->foreignId('production_order_id')->nullable()->constrained($productionOrderTable)->nullOnDelete();
            $table->foreignId('fabric_roll_id')->constrained('fabric_rolls')->cascadeOnDelete();
            $table->decimal('meters_consumed', 20, 6);
            $table->timestamps();

            $table->index(['production_order_id', 'fabric_roll_id'], 'prc_order_roll_idx');
        });
    }

    private function addIndexIfMissing(): void
    {
        $indexes = \Illuminate\Support\Facades\DB::select("SHOW INDEX FROM production_roll_consumptions WHERE Key_name = 'prc_order_roll_idx'");
        if (!empty($indexes)) {
            return;
        }
        Schema::table('production_roll_consumptions', function (Blueprint $table) {
            $table->index(['production_order_id', 'fabric_roll_id'], 'prc_order_roll_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_roll_consumptions');
    }
};
