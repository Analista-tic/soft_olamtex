<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $setting = DB::table('settings')
            ->where('kind', 'parameter')
            ->where('key', 'fabric_bases')
            ->first();

        if (!$setting) {
            return;
        }

        $items = json_decode($setting->value, true);
        if (!is_array($items)) {
            return;
        }

        $now = now();

        foreach ($items as $item) {
            $id     = (int) ($item['id'] ?? 0);
            $name   = strtoupper(trim((string) ($item['name'] ?? $item['nombre'] ?? '')));
            $factor = (float) ($item['factor'] ?? $item['factor_base'] ?? $item['factor_base_tela'] ?? 1.0);

            if ($id <= 0 || $name === '') {
                continue;
            }

            // Generar código automático: BT-001, BT-002, ...
            $codigo = 'BT-' . str_pad($id, 3, '0', STR_PAD_LEFT);

            DB::table('fabric_bases')->insertOrIgnore([
                'id'         => $id,
                'codigo'     => $codigo,
                'nombre'     => $name,
                'factor'     => $factor > 0 ? $factor : 1.0,
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('fabric_bases')->truncate();
    }
};
