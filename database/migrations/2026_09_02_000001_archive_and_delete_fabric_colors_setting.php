<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Borra el parámetro 'fabric_colors' ("Colores de tela") de `settings` — código
 * muerto confirmado: sin ningún consumidor en el frontend (grep exhaustivo sobre
 * resources/js), el catálogo real de colores es el modelo Color (tabla `colors`),
 * usado por items.color_id. Se archiva antes de borrar, reutilizando `settings_archive`
 * (creada en 2026_09_01_000002), para poder revertir sin depender de un dump manual.
 */
return new class extends Migration
{
    private const KIND = 'parameter';

    private const KEY = 'fabric_colors';

    public function up(): void
    {
        $rows = DB::table('settings')
            ->where('kind', self::KIND)
            ->where('key', self::KEY)
            ->get();

        foreach ($rows as $row) {
            $data = (array) $row;
            $data['archived_at'] = now();

            DB::table('settings_archive')->insertOrIgnore($data);
        }

        DB::table('settings')
            ->where('kind', self::KIND)
            ->where('key', self::KEY)
            ->delete();
    }

    public function down(): void
    {
        $rows = DB::table('settings_archive')
            ->where('kind', self::KIND)
            ->where('key', self::KEY)
            ->get();

        foreach ($rows as $row) {
            $data = (array) $row;
            unset($data['archived_at']);

            DB::table('settings')->insertOrIgnore($data);
        }

        DB::table('settings_archive')
            ->where('kind', self::KIND)
            ->where('key', self::KEY)
            ->delete();
    }
};
