<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Índices únicos para los consecutivos de Inventario y Ventas (antes solo tenían índice simple, así que la
 * base de datos aceptaba números repetidos). Los consecutivos nuevos los asigna App\Support\DocumentSequence
 * y nunca reutilizan el de un registro eliminado, por lo que el índice único sobre todas las filas es seguro.
 *
 * Si en algún entorno ya hay números repetidos, la migración se detiene indicando cuáles: no se renumeran
 * automáticamente porque esos números pueden estar impresos o contabilizados.
 */
return new class extends Migration
{
    /** tabla => [columnas únicas, nombre del índice] */
    private const UNIQUES = [
        'inventory_requests' => [['document_number'], 'inventory_requests_document_number_unique'],
        'warehouse_transfers' => [['document_number'], 'warehouse_transfers_document_number_unique'],
        'sale_documents' => [['document_type', 'document_number'], 'sale_documents_type_number_unique'],
    ];

    public function up(): void
    {
        foreach (self::UNIQUES as $table => [$columns, $index]) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $duplicates = DB::table($table)
                ->select($columns)
                ->selectRaw('COUNT(*) AS total')
                ->whereNotNull('document_number')
                ->groupBy($columns)
                ->havingRaw('COUNT(*) > 1')
                ->get();

            if ($duplicates->isNotEmpty()) {
                throw new RuntimeException(
                    "No se puede crear el índice único de {$table}: hay números repetidos. Corrígelos primero: "
                    . $duplicates->map(fn ($row) => json_encode($row, JSON_UNESCAPED_UNICODE))->implode('; ')
                );
            }

            Schema::table($table, function (Blueprint $blueprint) use ($columns, $index) {
                $blueprint->unique($columns, $index);
            });
        }
    }

    public function down(): void
    {
        foreach (self::UNIQUES as $table => [$columns, $index]) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $blueprint) use ($index) {
                    $blueprint->dropUnique($index);
                });
            }
        }
    }
};
