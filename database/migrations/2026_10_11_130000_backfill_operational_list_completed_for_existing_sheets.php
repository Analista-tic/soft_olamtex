<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Las sábanas que hoy ya tienen listado operacional se consideran con el listado completo (no hay forma de saber
     * quién lo revisó, así que queda sin usuario). Sin esto, la nueva regla de "sábana completa" las degradaría todas
     * en cuanto se tocara una línea.
     */
    public function up(): void
    {
        DB::table('product_sheets')
            ->whereNull('operational_list_completed_at')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('product_sheet_operations')
                    ->whereColumn('product_sheet_operations.product_sheet_id', 'product_sheets.id')
                    ->whereNull('product_sheet_operations.deleted_at');
            })
            ->update(['operational_list_completed_at' => now()]);
    }

    public function down(): void
    {
        DB::table('product_sheets')
            ->whereNull('operational_list_completed_by_user_id')
            ->update(['operational_list_completed_at' => null]);
    }
};
