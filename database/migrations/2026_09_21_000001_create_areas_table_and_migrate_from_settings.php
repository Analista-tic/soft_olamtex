<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las áreas vivían como un JSON dentro de la fila settings(key='areas') y
 * users.area_id tenía una FK a settings.id (la fila equivocada). Se convierten
 * en una tabla relacional propia conservando los ids del JSON, que son los que
 * ya guarda users.area_id, de modo que no hay que remapear usuarios.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        $catalog = DB::table('settings')
            ->where('kind', 'parameter')
            ->where('key', 'areas')
            ->first();

        $items = $catalog ? json_decode((string) $catalog->value, true) : [];
        $seen = [];

        foreach (is_array($items) ? array_values($items) : [] as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $name = mb_strtoupper(trim((string) ($item['name'] ?? $item['nombre'] ?? '')), 'UTF-8');
            $id = isset($item['id']) && is_numeric($item['id']) ? (int) $item['id'] : $index + 1;

            if ($name === '' || isset($seen[$name]) || DB::table('areas')->where('id', $id)->exists()) {
                continue;
            }

            $seen[$name] = true;
            DB::table('areas')->insert([
                'id' => $id,
                'name' => $name,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['area_id']);
        });

        // Usuarios cuya área no existe en el catálogo quedan sin área en vez de romper la FK.
        DB::table('users')
            ->whereNotNull('area_id')
            ->whereNotIn('area_id', DB::table('areas')->select('id'))
            ->update(['area_id' => null]);

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('area_id')->references('id')->on('areas')->nullOnDelete();
        });

        DB::table('settings')
            ->where('kind', 'parameter')
            ->where('key', 'areas')
            ->delete();
    }

    public function down(): void
    {
        $now = now();
        $items = DB::table('areas')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn ($area) => ['id' => (int) $area->id, 'name' => $area->name])
            ->all();

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['area_id']);
        });

        DB::table('settings')->insert([
            'kind' => 'parameter',
            'key' => 'areas',
            'label' => 'Áreas',
            'description' => 'Catálogo de áreas organizacionales',
            'value' => json_encode($items, JSON_UNESCAPED_UNICODE),
            'status' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // El diseño previo enlazaba users.area_id con settings.id; solo se conservan las áreas que tengan fila equivalente.
        DB::table('users')
            ->whereNotNull('area_id')
            ->whereNotIn('area_id', DB::table('settings')->select('id'))
            ->update(['area_id' => null]);

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('area_id')->references('id')->on('settings')->nullOnDelete();
        });

        Schema::dropIfExists('areas');
    }
};
