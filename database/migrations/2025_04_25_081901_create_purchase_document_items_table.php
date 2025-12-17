<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_document_items', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Relación con el documento unificado
            $table->unsignedBigInteger('purchase_document_id');
            $table->foreign('purchase_document_id')
                ->references('id')
                ->on('purchase_documents')
                ->onDelete('cascade');

            // Ítem (antes article_id → ahora item_id)
            $table->unsignedBigInteger('item_id')->nullable()->index();
            $table->foreign('item_id')
                ->references('id')
                ->on('items')   // tu tabla actual de ítems
                ->onDelete('set null');

            // Datos de la línea
            $table->decimal('quantity', 15, 3)->default(0);       // Cantidad
            $table->decimal('price', 15, 2)->default(0);          // Precio unitario SIN impuesto
            $table->decimal('tax', 8, 2)->default(0);             // % impuesto
            $table->decimal('total', 15, 2)->default(0);          // Total sin impuestos
            $table->decimal('total_tax', 15, 2)->default(0);      // Total de impuestos

            // Campos específicos que ya usas en las tablas de factura/orden
            $table->string('supplier_code', 100)->nullable();
            $table->string('supplier_color', 100)->nullable();
            $table->decimal('order_width', 10, 3)->nullable();     // ancho pedido
            $table->text('description')->nullable();

            $table->timestamps();

            $table->index(['purchase_document_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::table('purchase_document_items', function (Blueprint $table) {
            $table->dropForeign(['purchase_document_id']);
            $table->dropForeign(['item_id']);
        });

        Schema::dropIfExists('purchase_document_items');
    }
};
