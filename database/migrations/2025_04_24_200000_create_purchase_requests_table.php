<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crear tabla de solicitudes de compra.
     * Los items se guardan como JSON en lugar de tabla separada.
     */
    public function up(): void
    {
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->string('provider_name')->nullable();
            $table->string('provider_phone')->nullable();
            $table->date('date');
            $table->longText('description')->nullable();
            $table->json('items')->nullable();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['pending', 'processed'])->default('pending')->index();
            $table->timestamp('processed_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            $table->index('date');
            $table->index('created_by_user_id');
        });
    }

    /**
     * Revertir la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_requests');
    }
};
