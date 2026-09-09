<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fila de secuencia para la referencia automática de ítems (consecutivo de 5
 * dígitos), reutilizando la tabla genérica `finance_document_sequences` — mismo
 * mecanismo que ya usan cotizaciones, pedidos, facturas y notas de venta.
 */
return new class extends Migration
{
    private const DOCUMENT_TYPE = 'item_auto_reference';

    public function up(): void
    {
        DB::table('finance_document_sequences')->updateOrInsert(
            ['document_type' => self::DOCUMENT_TYPE],
            [
                'current_value' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('finance_document_sequences')
            ->where('document_type', self::DOCUMENT_TYPE)
            ->delete();
    }
};
