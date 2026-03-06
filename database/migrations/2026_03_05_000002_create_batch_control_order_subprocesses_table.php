<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_control_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')
                ->constrained('production_orders')
                ->cascadeOnDelete();
            $table->string('from_process', 80)->comment('Estado/proceso que se completó');
            $table->string('to_process', 80)->comment('Estado/proceso al que se entrega');
            $table->text('observations')->nullable()->comment('Observaciones o novedades de la entrega');
            $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('production_order_id');
            $table->index(['production_order_id', 'from_process']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_control_deliveries');
    }
};
