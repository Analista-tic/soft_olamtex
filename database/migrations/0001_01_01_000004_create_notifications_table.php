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
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->softDeletes(); // deleted_at
            $table->timestamps();

            // Índices para optimizar queries comunes
            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
            $table->index('created_at');
            $table->index('archived_at');
            $table->index('deleted_at');
            // Índice compuesto para unreadCount
            $table->index(
                ['notifiable_type', 'notifiable_id', 'read_at', 'deleted_at', 'archived_at'],
                'notifications_unread_count_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
