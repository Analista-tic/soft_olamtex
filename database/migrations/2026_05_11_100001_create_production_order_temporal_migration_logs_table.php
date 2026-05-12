<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase A — Plan definitivo Mes/Año.
 *
 * Tabla de trazabilidad del proceso de backfill.
 * Registra cómo se resolvió cada orden y permite detectar ambiguos para revisión manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_order_temporal_migration_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('production_order_id');
            $table->foreign('production_order_id')
                ->references('id')->on('production_orders')
                ->cascadeOnDelete();

            // Valores legacy originales
            $table->string('legacy_season')->nullable();
            $table->string('legacy_dispatch_month')->nullable();

            // Valores resueltos (canónicos)
            $table->tinyInteger('resolved_season_month')->unsigned()->nullable();
            $table->smallInteger('resolved_season_year')->unsigned()->nullable();
            $table->tinyInteger('resolved_dispatch_month_num')->unsigned()->nullable();
            $table->smallInteger('resolved_dispatch_year')->unsigned()->nullable();

            // Trazabilidad de la resolución
            $table->enum('resolution_source', [
                'explicit',             // año estaba en el string
                'estimated_delivery_date', // año inferido de fecha estimada
                'created_at',           // año inferido de created_at
                'manual',               // resuelto manualmente por un usuario
            ])->default('explicit');

            $table->boolean('is_ambiguous')->default(false)
                ->comment('Casos que requieren revisión manual');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('resolved_by_user_id')->nullable();
            $table->foreign('resolved_by_user_id')
                ->references('id')->on('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('production_order_id');
            $table->index('is_ambiguous');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_order_temporal_migration_logs');
    }
};
