<?php

use App\Support\MasterData\MaterialClassifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mueve la clasificación tela/hilaza de config('master_data.*') (hardcodeada, exige
 * desplegar para cambiarla) a un dato editable por subgrupo.
 *
 * El backfill usa los ids reales que tenía esa config — [1] "Tela" y [2] "Hilaza",
 * ambos bajo Materia Prima, confirmados sobre el dump de producción en una fase
 * anterior de este refactor — en vez de leer config('master_data.*'): esa misma fase
 * borra config/master_data.php en el mismo commit, así que depender de ella aquí
 * dejaría el backfill vacío en cualquier entorno donde ambos cambios se apliquen
 * juntos (ocurrió al implementar: primero se borró el archivo, luego se corrió esta
 * migración, y el backfill quedó en blanco en dev hasta revertir y corregir esto).
 * Si tu entorno tiene esos subgrupos con otros ids, ajusta las dos constantes de
 * abajo antes de migrar — es un valor de datos, no de código.
 */
return new class extends Migration
{
    private const FABRIC_SUBGROUP_IDS = [1];

    private const YARN_SUBGROUP_IDS = [2];

    public function up(): void
    {
        Schema::table('category_sub_groups', function (Blueprint $table) {
            $table->enum('material_kind', ['fabric', 'yarn'])->nullable()->after('category_group_id');
        });

        DB::table('category_sub_groups')->whereIn('id', self::FABRIC_SUBGROUP_IDS)->update(['material_kind' => 'fabric']);
        DB::table('category_sub_groups')->whereIn('id', self::YARN_SUBGROUP_IDS)->update(['material_kind' => 'yarn']);

        // El clasificador cachea las listas de ids con el driver de caché de la app
        // (no una static, ver MaterialClassifier). Si esta migración se re-ejecuta
        // sobre un entorno donde ya se había consultado antes (ej. tras un rollback
        // manual para corregir algo), esa caché quedaría desactualizada hasta su TTL.
        MaterialClassifier::forgetCache();
    }

    public function down(): void
    {
        Schema::table('category_sub_groups', function (Blueprint $table) {
            $table->dropColumn('material_kind');
        });

        MaterialClassifier::forgetCache();
    }
};
