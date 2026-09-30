<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Antes, la ubicación de recepción de rollos de tela era una lista fija (A1-F3) en el
     * frontend. Ahora ese select se alimenta del catálogo `warehouse_locations`, así que se
     * siembran esos mismos códigos aquí para no perder las ubicaciones que ya se usaban.
     */
    public function up(): void
    {
        $codes = [];
        foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $row) {
            foreach ([1, 2, 3] as $col) {
                $codes[] = "{$row}{$col}";
            }
        }

        $existing = DB::table('warehouse_locations')
            ->whereIn('name', $codes)
            ->pluck('name')
            ->all();

        $now = now();
        $rows = collect($codes)
            ->diff($existing)
            ->map(fn (string $name) => [
                'name'       => $name,
                'code'       => null,
                'description'=> null,
                'status'     => true,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->values()
            ->all();

        if (! empty($rows)) {
            DB::table('warehouse_locations')->insert($rows);
        }
    }

    public function down(): void
    {
        $codes = [];
        foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $row) {
            foreach ([1, 2, 3] as $col) {
                $codes[] = "{$row}{$col}";
            }
        }

        DB::table('warehouse_locations')->whereIn('name', $codes)->delete();
    }
};
