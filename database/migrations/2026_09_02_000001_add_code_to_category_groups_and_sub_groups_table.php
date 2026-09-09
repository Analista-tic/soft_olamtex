<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `code` (2 dígitos) para grupos y subgrupos de categoría, mismo patrón ya usado en
 * `colors.code` (2026_05_25_120000_add_code_to_colors_table.php). Se usa para
 * componer la referencia automática de ítems (consecutivo+grupo+subgrupo+color).
 * Sin backfill: no hay forma razonable de derivarlo del nombre — quedan `null` hasta
 * que se completen manualmente desde la UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('category_groups', function (Blueprint $table) {
            $table->string('code', 2)->nullable()->after('name')->unique();
        });

        Schema::table('category_sub_groups', function (Blueprint $table) {
            $table->string('code', 2)->nullable()->after('name')->unique();
        });
    }

    public function down(): void
    {
        Schema::table('category_groups', function (Blueprint $table) {
            $table->dropUnique('category_groups_code_unique');
            $table->dropColumn('code');
        });

        Schema::table('category_sub_groups', function (Blueprint $table) {
            $table->dropUnique('category_sub_groups_code_unique');
            $table->dropColumn('code');
        });
    }
};
