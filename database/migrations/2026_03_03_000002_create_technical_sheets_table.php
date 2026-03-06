<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Refactorización DDD: Crea tabla `technical_sheets` como Aggregate Root
 * de la Ficha Técnica de una Orden de Producción.
 *
 * Persiste costos calculados para trazabilidad y análisis financiero.
 * ProductionOrder hasOne TechnicalSheet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technical_sheets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('production_order_id')->comment('FK a production_orders');
            $table->string('status')->default('borrador')->comment('borrador|en_progreso|completada');

            // === Costos persistidos (congelados al guardar) ===
            $table->decimal('raw_material_cost', 12, 4)->nullable()->comment('Costo total materia prima');
            $table->decimal('supplies_cost', 12, 4)->nullable()->comment('Costo total insumos');
            $table->decimal('processes_cost', 12, 4)->nullable()->comment('Costo total procesos');
            $table->decimal('cut_cost', 12, 4)->nullable()->comment('Costo de corte');
            $table->decimal('confection_cost', 12, 4)->nullable()->comment('Costo confección');
            $table->decimal('finishing_cost', 12, 4)->nullable()->comment('Costo terminación');
            $table->decimal('total_cost', 12, 4)->nullable()->comment('Costo total de la ficha');

            // === Precios de referencia congelados ===
            $table->decimal('billing_price', 12, 4)->nullable()->comment('Precio factura congelado');
            $table->decimal('selling_price', 12, 4)->nullable()->comment('Precio venta congelado');

            // === Rendimiento y trazabilidad ===
            $table->decimal('rendimiento', 8, 4)->nullable()->comment('Rendimiento calculado');
            $table->decimal('costo_minuto', 8, 4)->nullable()->comment('Costo por minuto congelado');

            $table->timestamps();
            $table->softDeletes();

            // FK
            $table->foreign('production_order_id', 'ts_production_order_fk')
                ->references('id')
                ->on('production_orders')
                ->cascadeOnDelete();

            // Indices
            $table->unique('production_order_id', 'ts_production_order_unique');
            $table->index('status');
        });

        // Crear una TechnicalSheet para cada ProductionOrder existente
        $orders = DB::table('production_orders')->select('id')->get();
        foreach ($orders as $order) {
            DB::table('technical_sheets')->insert([
                'production_order_id' => $order->id,
                'status' => 'borrador',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_sheets');
    }
};
