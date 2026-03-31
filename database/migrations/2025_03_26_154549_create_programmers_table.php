<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_sheet_id')->constrained();
            $table->string('op_code')->nullable()->unique();
            $table->string('season');
            $table->unsignedBigInteger('destination_id'); // ID referenciado en parameters - sin foreign key
            $table->string('priority')->nullable();
            $table->integer('units')->nullable();
            $table->date('estimated_delivery_date')->nullable();
            $table->json('ecommerce_units')->nullable();
            $table->string('status');
            $table->enum('source', ['auto', 'manual'])->default('manual'); // Origen del registro: auto=desde sábana, manual=creado directo
            $table->string('current_process', 80)->nullable()->comment('Proceso actual para Control de lotes');
            $table->unsignedInteger('batch_priority')->default(0);
            $table->json('process_estimated_dates')->nullable();
            $table->json('cut_fabric_consumption')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_orders');
    }
};
