<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('operation_audit_logs')) {
            Schema::create('operation_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('operation_id');
                $table->string('field_changed', 50);
                $table->text('old_value')->nullable();
                $table->text('new_value')->nullable();
                $table->text('motivo')->nullable();
                $table->unsignedBigInteger('changed_by')->nullable();
                $table->timestamps();

                $table->foreign('operation_id')
                    ->references('id')
                    ->on('operations')
                    ->onDelete('cascade');
                $table->foreign('changed_by')
                    ->references('id')
                    ->on('users')
                    ->onDelete('set null');
                $table->index('operation_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_audit_logs');
    }
};
