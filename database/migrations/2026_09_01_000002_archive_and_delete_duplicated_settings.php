<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina de `settings` los catálogos de material que quedaron duplicados tras
 * migrarlos a tablas relacionales dedicadas.
 *
 * Ninguno de ellos tiene ya consumidores:
 *  - kind='field'     base_tela / base_hilaza → sustituidos por items.fabric_name_id
 *                     y items.yarn_name_id (FK reales a fabric_names / yarn_names).
 *  - kind='parameter' fabrics ("Telas")       → sustituido por la tabla fabric_names.
 *  - kind='parameter' fabric_bases/yarn_bases → filas huérfanas que ninguna migración
 *                     borró al mover esos catálogos a fabric_bases / yarn_bases.
 *
 * Se archivan antes de borrar para que down() pueda restaurarlas sin depender de un
 * dump manual. Los valores ya guardados en items.extra_fields NO se tocan: son el
 * histórico de los ítems y se resuelven en una fase posterior.
 */
return new class extends Migration
{
    private const KEYS = [
        ['kind' => 'field', 'key' => 'base_tela'],
        ['kind' => 'field', 'key' => 'base_hilaza'],
        ['kind' => 'parameter', 'key' => 'fabrics'],
        ['kind' => 'parameter', 'key' => 'fabric_bases'],
        ['kind' => 'parameter', 'key' => 'yarn_bases'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('settings_archive')) {
            Schema::create('settings_archive', function (Blueprint $table) {
                $table->unsignedBigInteger('id')->primary();
                $table->string('kind', 20);
                $table->string('key');
                $table->string('label')->nullable();
                $table->text('description')->nullable();
                $table->json('value')->nullable();
                $table->string('field_type')->nullable();
                $table->json('options')->nullable();
                $table->string('default_value')->nullable();
                $table->boolean('is_required')->nullable();
                $table->integer('position')->nullable();
                $table->boolean('status')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->timestamp('archived_at')->useCurrent();
            });
        }

        foreach (self::KEYS as $target) {
            $rows = DB::table('settings')
                ->where('kind', $target['kind'])
                ->where('key', $target['key'])
                ->get();

            foreach ($rows as $row) {
                $data = (array) $row;
                $data['archived_at'] = now();

                DB::table('settings_archive')->insertOrIgnore($data);
            }

            DB::table('settings')
                ->where('kind', $target['kind'])
                ->where('key', $target['key'])
                ->delete();
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('settings_archive')) {
            return;
        }

        $keys = array_column(self::KEYS, 'key');

        $rows = DB::table('settings_archive')->whereIn('key', $keys)->get();

        foreach ($rows as $row) {
            $data = (array) $row;
            unset($data['archived_at']);

            DB::table('settings')->insertOrIgnore($data);
        }

        DB::table('settings_archive')->whereIn('key', $keys)->delete();
    }
};
