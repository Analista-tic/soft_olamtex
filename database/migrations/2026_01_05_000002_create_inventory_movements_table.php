<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crear tabla de movimientos de inventario con soporte para
     * cálculo automático de precio ponderado.
     * 
     * Registra todas las entradas y salidas de inventario desde:
     * - Facturas de compra aprobadas
     * - Ajustes manuales de inventario
     * - Devoluciones
     * - Consumos de producción
     */
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            
            // Consecutivo único del movimiento
            $table->string('document_number', 50)->unique()->index(); // MOV-2026-001
            
            // Identificación del item y bodega
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            
            // Origen del movimiento (polimórfico)
            $table->string('source_type')->index(); // PurchaseDocument, InventoryAdjustment, etc.
            $table->unsignedBigInteger('source_id')->index();
            
            // Tipo de movimiento y fecha
            $table->enum('type', ['entrada', 'salida'])->index();
            $table->date('date')->index();
            
            // Cantidades y precios
            $table->decimal('quantity', 15, 4); // Cantidad del movimiento (positiva)
            $table->decimal('unit_price', 15, 4)->nullable(); // Precio unitario (solo entradas)
            $table->decimal('total_cost', 15, 4)->nullable(); // quantity * unit_price
            
            // Cálculo de precio ponderado
            $table->decimal('previous_stock', 15, 4)->default(0); // Stock antes del movimiento
            $table->decimal('previous_weighted_price', 15, 4)->default(0); // Precio ponderado anterior
            $table->decimal('new_stock', 15, 4); // Stock después del movimiento
            $table->decimal('new_weighted_price', 15, 4); // Nuevo precio ponderado calculado
            
            // Observaciones y auditoría
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices compuestos para consultas frecuentes
            $table->index(['item_id', 'warehouse_id', 'date']);
            $table->index(['source_type', 'source_id']);
            $table->index(['type', 'date']);
        });
    }

    /**
     * Revertir la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
