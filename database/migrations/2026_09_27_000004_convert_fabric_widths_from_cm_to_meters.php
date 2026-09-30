<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El ancho de tela se maneja en metros (orden, factura, recepción e inventario).
 * Antes la captura pedía centímetros, así que quedaron valores como 150 en vez de 1.50.
 * Ninguna tela mide más de 10 m de ancho: todo valor mayor a 10 se entiende como centímetros.
 * Es idempotente (tras convertir, el valor queda por debajo de 10).
 */
return new class extends Migration
{
    private const MAX_WIDTH_IN_METERS = 10;

    public function up(): void
    {
        DB::table('purchase_document_items')
            ->where('order_width', '>', self::MAX_WIDTH_IN_METERS)
            ->update(['order_width' => DB::raw('ROUND(order_width / 100, 3)')]);

        DB::table('fabric_rolls')
            ->where('roll_width', '>', self::MAX_WIDTH_IN_METERS)
            ->update(['roll_width' => DB::raw('ROUND(roll_width / 100, 2)')]);
    }

    public function down(): void
    {
        // Sin reversa: no se puede distinguir qué valores en metros venían de centímetros.
    }
};
