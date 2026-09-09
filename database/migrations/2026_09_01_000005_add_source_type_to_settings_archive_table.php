<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `settings_archive` (creada en 2026_09_01_000002) copia columna por columna las filas
 * de `settings` en el momento en que se archivan. Al añadir `source_type`/`catalog_key`
 * a `settings` (2026_09_01_000004), cualquier archivado nuevo fallaba con "column not
 * found" porque `settings_archive` no las tenía. Se agregan aquí, sin tocar la
 * migración original ya ejecutada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings_archive')) {
            return;
        }

        Schema::table('settings_archive', function (Blueprint $table) {
            if (! Schema::hasColumn('settings_archive', 'source_type')) {
                $table->string('source_type')->nullable()->after('options');
            }
            if (! Schema::hasColumn('settings_archive', 'catalog_key')) {
                $table->string('catalog_key')->nullable()->after('source_type');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('settings_archive')) {
            return;
        }

        Schema::table('settings_archive', function (Blueprint $table) {
            if (Schema::hasColumn('settings_archive', 'catalog_key')) {
                $table->dropColumn('catalog_key');
            }
            if (Schema::hasColumn('settings_archive', 'source_type')) {
                $table->dropColumn('source_type');
            }
        });
    }
};
