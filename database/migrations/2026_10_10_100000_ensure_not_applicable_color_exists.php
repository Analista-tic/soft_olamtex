<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Garantiza el color "NO APLICA" (código 000), usado para ítems sin color: entra en la
 * referencia automática pero no en el nombre (Color::NOT_APPLICABLE_CODE). Es idempotente:
 * si ya existe por código no hace nada, y si existe por nombre sin código le asigna 000.
 */
return new class extends Migration
{
    private const CODE = '000';

    private const NAME = 'NO APLICA';

    public function up(): void
    {
        if (DB::table('colors')->where('code', self::CODE)->exists()) {
            return;
        }

        $byName = DB::table('colors')->where('name', self::NAME)->first();
        if ($byName !== null) {
            if ($byName->code === null || $byName->code === '') {
                DB::table('colors')->where('id', $byName->id)->update(['code' => self::CODE, 'updated_at' => now()]);
            }

            return;
        }

        DB::table('colors')->insert([
            'code' => self::CODE,
            'name' => self::NAME,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // No se borra: puede haber ítems referenciando el color.
    }
};
