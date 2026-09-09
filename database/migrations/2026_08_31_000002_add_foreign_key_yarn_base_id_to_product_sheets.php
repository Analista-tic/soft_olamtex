<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Limpiar referencias huérfanas antes de agregar la FK
        DB::statement('
            UPDATE product_sheets
            SET yarn_base_id = NULL
            WHERE yarn_base_id IS NOT NULL
              AND yarn_base_id NOT IN (SELECT id FROM yarn_bases)
        ');

        Schema::table('product_sheets', function (Blueprint $table) {
            $table->foreign('yarn_base_id')
                  ->references('id')
                  ->on('yarn_bases')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_sheets', function (Blueprint $table) {
            $table->dropForeign(['yarn_base_id']);
        });
    }
};
