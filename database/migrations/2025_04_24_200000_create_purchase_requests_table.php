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
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->nullable()->unique();
            $table->string('provider_name')->nullable();
            $table->foreignId('requester_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date');
            $table->string('destination')->nullable();
            $table->decimal('estimated_amount', 12, 2)->nullable();
            $table->longText('description')->nullable();
            $table->json('items')->nullable();
            $table->enum('status', ['draft', 'pending', 'approved', 'processed', 'rejected', 'cancelled'])->default('draft');
            
            // Campos de aprobación
            $table->unsignedBigInteger('approved_by_user_id')->nullable()->index();
            $table->foreign('approved_by_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            
            // Campos de rechazo
            $table->unsignedBigInteger('rejected_by_user_id')->nullable()->index();
            $table->foreign('rejected_by_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            
            // Campos de procesamiento (mantener compatibilidad)
            $table->timestamp('processed_at')->nullable();
            $table->unsignedBigInteger('processed_by_user_id')->nullable()->index();
            $table->foreign('processed_by_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
            
            $table->timestamps();
            $table->softDeletes();

            $table->index('date');
            $table->index('requester_user_id');
            $table->index('status');
            $table->index('provider_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_requests');
    }
};
