<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_control_warehouse_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->json('received_curves');
            $table->json('imperfect_curves');
            $table->string('responsible', 255)->nullable();
            $table->text('imperfect_observations')->nullable();
            $table->json('remision_curves');
            $table->text('remision_observations')->nullable();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->timestamp('remisionado_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_control_warehouse_receipts');
    }
};
