<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Compras y recepción manejan máximo 2 decimales: cantidades de ítems, ancho pedido y cantidades recibidas.
 * Se redondea primero para que el cambio de escala no dependa de cómo el motor trunca.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('purchase_document_items')->update([
            'quantity' => DB::raw('ROUND(quantity, 2)'),
            'order_width' => DB::raw('ROUND(order_width, 2)'),
        ]);
        DB::table('purchase_document_item_receptions')->update(['quantity' => DB::raw('ROUND(quantity, 2)')]);

        Schema::table('purchase_document_items', function (Blueprint $table) {
            $table->decimal('quantity', 15, 2)->default(0)->change();
            $table->decimal('order_width', 10, 2)->nullable()->change();
        });
        Schema::table('purchase_document_item_receptions', function (Blueprint $table) {
            $table->decimal('quantity', 15, 2)->change();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_document_items', function (Blueprint $table) {
            $table->decimal('quantity', 15, 3)->default(0)->change();
            $table->decimal('order_width', 10, 3)->nullable()->change();
        });
        Schema::table('purchase_document_item_receptions', function (Blueprint $table) {
            $table->decimal('quantity', 15, 3)->change();
        });
    }
};
