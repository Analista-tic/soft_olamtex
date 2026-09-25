<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->backfillCodes('category_groups');
        $this->backfillCodes('category_sub_groups');
    }

    public function down(): void
    {
        // Los códigos asignados pueden estar siendo usados por referencias de ítems.
    }

    private function backfillCodes(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $nextCode = (int) (DB::table($table)->max('code') ?? 0);

        DB::table($table)
            ->whereNull('code')
            ->orderBy('id')
            ->eachById(function (object $row) use ($table, &$nextCode): void {
                $nextCode++;

                if ($nextCode > 99) {
                    throw new RuntimeException("No hay códigos disponibles para {$table}.");
                }

                DB::table($table)
                    ->where('id', $row->id)
                    ->update(['code' => str_pad((string) $nextCode, 2, '0', STR_PAD_LEFT)]);
            });
    }
};
